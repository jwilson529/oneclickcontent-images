<?php
/**
 * Media-library condition metrics and filtered attachment queries.
 *
 * @package Occidg
 */

defined( 'ABSPATH' ) || exit;

/** Read-only preflight and audit queries. */
class Occidg_Preflight {
	/**
	 * Return dashboard metrics using SQL counts rather than loading attachments.
	 *
	 * @param array $enabled_fields Fields that define completeness.
	 * @return array Condition metrics.
	 */
	public function get_metrics( $enabled_fields = array() ) {
		global $wpdb;
		$posts  = $wpdb->posts;
		$meta   = $wpdb->postmeta;
		$base   = "p.post_type = 'attachment' AND p.post_mime_type LIKE 'image/%' AND p.post_status = 'inherit'";
		$counts = array(
			'total'               => (int) $wpdb->get_var( "SELECT COUNT(*) FROM $posts p WHERE $base" ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			'missing_alt_text'    => (int) $wpdb->get_var( "SELECT COUNT(DISTINCT p.ID) FROM $posts p LEFT JOIN $meta a ON a.post_id=p.ID AND a.meta_key='_wp_attachment_image_alt' LEFT JOIN $meta d ON d.post_id=p.ID AND d.meta_key='_occ_idg_decorative' WHERE $base AND (a.meta_value IS NULL OR TRIM(a.meta_value)='') AND (d.meta_value IS NULL OR d.meta_value!='1')" ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			'missing_title'       => (int) $wpdb->get_var( "SELECT COUNT(*) FROM $posts p WHERE $base AND TRIM(p.post_title)=''" ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			'missing_caption'     => (int) $wpdb->get_var( "SELECT COUNT(*) FROM $posts p WHERE $base AND TRIM(p.post_excerpt)=''" ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			'missing_description' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM $posts p WHERE $base AND TRIM(p.post_content)=''" ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			'processed'           => $this->count_meta_flag( '_occ_idg_last_processed', true ),
			'human_reviewed'      => $this->count_meta_flag( '_occ_idg_last_reviewed', true ),
			'failed'              => $this->count_meta_value( '_occ_idg_review_status', 'failed' ),
			'decorative'          => $this->count_meta_value( '_occ_idg_decorative', '1' ),
			'queued_processing'   => $this->count_batch_items( array( 'queued', 'processing', 'retrying' ) ),
			'pending_suggestions' => $this->count_suggestions( 'pending' ),
		);

		$enabled = Occidg_Metadata::normalize_fields( $enabled_fields );
		if ( empty( $enabled ) ) {
			$enabled = array_keys( Occidg_Metadata::FIELDS );
		}
		$complete_conditions = array();
		foreach ( $enabled as $field ) {
			if ( 'alt_text' === $field ) {
				$complete_conditions[] = "(EXISTS (SELECT 1 FROM $meta ca WHERE ca.post_id=p.ID AND ca.meta_key='_wp_attachment_image_alt' AND TRIM(ca.meta_value)!='') OR EXISTS (SELECT 1 FROM $meta cd WHERE cd.post_id=p.ID AND cd.meta_key='_occ_idg_decorative' AND cd.meta_value='1'))";
			} elseif ( 'title' === $field ) {
				$complete_conditions[] = "TRIM(p.post_title)!=''";
			} elseif ( 'caption' === $field ) {
				$complete_conditions[] = "TRIM(p.post_excerpt)!=''";
			} elseif ( 'description' === $field ) {
				$complete_conditions[] = "TRIM(p.post_content)!=''";
			}
		}
		$counts['complete_enabled_fields'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $posts p WHERE $base AND " . implode( ' AND ', $complete_conditions ) ); // phpcs:ignore

		return $counts;
	}

	/**
	 * Return filtered image IDs for audit screens, jobs, and CLI.
	 *
	 * @param array $filters Audit filters.
	 * @param int   $limit   Maximum results.
	 * @param int   $offset  Result offset.
	 * @return array Attachment IDs.
	 */
	public function query_ids( $filters = array(), $limit = 100, $offset = 0 ) {
		global $wpdb;
		$filters = is_array( $filters ) ? $filters : array();
		$where   = array( "p.post_type='attachment'", "p.post_status='inherit'", "p.post_mime_type LIKE 'image/%'" );
		$params  = array();

		if ( ! empty( $filters['missing_field'] ) ) {
			$field = sanitize_key( $filters['missing_field'] );
			$map   = array(
				'title'       => 'post_title',
				'caption'     => 'post_excerpt',
				'description' => 'post_content',
			);
			if ( 'alt_text' === $field ) {
				$where[] = "NOT EXISTS (SELECT 1 FROM {$wpdb->postmeta} am WHERE am.post_id=p.ID AND am.meta_key='_wp_attachment_image_alt' AND TRIM(am.meta_value)!='')";
				if ( empty( $filters['include_decorative'] ) ) {
					$where[] = "NOT EXISTS (SELECT 1 FROM {$wpdb->postmeta} dm WHERE dm.post_id=p.ID AND dm.meta_key='_occ_idg_decorative' AND dm.meta_value='1')";
				}
			} elseif ( isset( $map[ $field ] ) ) {
				$where[] = 'TRIM(p.' . $map[ $field ] . ")=''";
			}
		}
		if ( ! empty( $filters['uploader'] ) ) {
			$where[]  = 'p.post_author=%d';
			$params[] = absint( $filters['uploader'] );
		}
		if ( ! empty( $filters['parent'] ) ) {
			$where[]  = 'p.post_parent=%d';
			$params[] = absint( $filters['parent'] );
		}
		if ( ! empty( $filters['mime_type'] ) ) {
			$where[]  = 'p.post_mime_type=%s';
			$params[] = sanitize_mime_type( $filters['mime_type'] );
		}
		if ( ! empty( $filters['date_from'] ) ) {
			$where[]  = 'p.post_date >= %s';
			$params[] = sanitize_text_field( $filters['date_from'] ) . ' 00:00:00';
		}
		if ( ! empty( $filters['date_to'] ) ) {
			$where[]  = 'p.post_date <= %s';
			$params[] = sanitize_text_field( $filters['date_to'] ) . ' 23:59:59';
		}
		if ( isset( $filters['processed'] ) && '' !== $filters['processed'] ) {
			$where[] = (bool) $filters['processed']
				? "EXISTS (SELECT 1 FROM {$wpdb->postmeta} pm WHERE pm.post_id=p.ID AND pm.meta_key='_occ_idg_last_processed' AND pm.meta_value!='')"
				: "NOT EXISTS (SELECT 1 FROM {$wpdb->postmeta} pm WHERE pm.post_id=p.ID AND pm.meta_key='_occ_idg_last_processed' AND pm.meta_value!='')";
		}
		if ( ! empty( $filters['review_status'] ) ) {
			$where[]  = "EXISTS (SELECT 1 FROM {$wpdb->postmeta} rm WHERE rm.post_id=p.ID AND rm.meta_key='_occ_idg_review_status' AND rm.meta_value=%s)";
			$params[] = sanitize_key( $filters['review_status'] );
		}
		if ( ! empty( $filters['provider'] ) ) {
			$where[]  = "EXISTS (SELECT 1 FROM {$wpdb->postmeta} pm2 WHERE pm2.post_id=p.ID AND pm2.meta_key='_occ_idg_last_provider' AND pm2.meta_value=%s)";
			$params[] = sanitize_key( $filters['provider'] );
		}
		if ( ! empty( $filters['decorative_status'] ) ) {
			$where[] = 'decorative' === $filters['decorative_status']
				? "EXISTS (SELECT 1 FROM {$wpdb->postmeta} dm2 WHERE dm2.post_id=p.ID AND dm2.meta_key='_occ_idg_decorative' AND dm2.meta_value='1')"
				: "NOT EXISTS (SELECT 1 FROM {$wpdb->postmeta} dm2 WHERE dm2.post_id=p.ID AND dm2.meta_key='_occ_idg_decorative' AND dm2.meta_value='1')";
		}
		if ( ! empty( $filters['batch_id'] ) ) {
			$batch_items = Occidg_Database::table( 'batch_items' );
			$where[]     = "EXISTS (SELECT 1 FROM $batch_items bi WHERE bi.attachment_id=p.ID AND bi.batch_id=%d)";
			$params[]    = absint( $filters['batch_id'] );
		}
		if ( ! empty( $filters['processing_status'] ) ) {
			$batch_items = Occidg_Database::table( 'batch_items' );
			$where[]     = "EXISTS (SELECT 1 FROM $batch_items bis WHERE bis.attachment_id=p.ID AND bis.status=%s)";
			$params[]    = sanitize_key( $filters['processing_status'] );
		}
		if ( ! empty( $filters['confidence'] ) ) {
			$suggestions = Occidg_Database::table( 'suggestions' );
			$where[]     = "EXISTS (SELECT 1 FROM $suggestions sg WHERE sg.attachment_id=p.ID AND sg.confidence=%s)";
			$params[]    = sanitize_key( $filters['confidence'] );
		}
		if ( ! empty( $filters['extension'] ) ) {
			$extension = ltrim( strtolower( preg_replace( '/[^a-z0-9]/i', '', $filters['extension'] ) ), '.' );
			if ( $extension ) {
				$where[]  = "EXISTS (SELECT 1 FROM {$wpdb->postmeta} fm WHERE fm.post_id=p.ID AND fm.meta_key='_wp_attached_file' AND fm.meta_value LIKE %s)";
				$params[] = '%.' . $wpdb->esc_like( $extension );
			}
		}

		$order = 'oldest' === ( isset( $filters['order'] ) ? $filters['order'] : '' ) ? 'ASC' : 'DESC';
		if ( 'random' === ( isset( $filters['order'] ) ? $filters['order'] : '' ) ) {
			$order_by = 'RAND()';
		} else {
			$order_by = 'p.post_date ' . $order . ', p.ID ' . $order;
		}
		$has_file_filters = ! empty( $filters['min_width'] ) || ! empty( $filters['max_width'] ) || ! empty( $filters['min_height'] ) || ! empty( $filters['max_height'] ) || ! empty( $filters['min_file_size'] ) || ! empty( $filters['max_file_size'] );
		$params[]         = $has_file_filters ? 10000 : min( 10000, max( 1, absint( $limit ) ) );
		$params[]         = $has_file_filters ? 0 : max( 0, absint( $offset ) );
		$query            = "SELECT p.ID FROM {$wpdb->posts} p WHERE " . implode( ' AND ', $where ) . " ORDER BY $order_by LIMIT %d OFFSET %d";
		$sql              = $wpdb->prepare( $query, ...$params ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$ids = array_map( 'absint', (array) $wpdb->get_col( $sql ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		if ( $has_file_filters ) {
			$ids = array_values(
				array_filter(
					$ids,
					function ( $attachment_id ) use ( $filters ) {
						$metadata = wp_get_attachment_metadata( $attachment_id );
						$width    = isset( $metadata['width'] ) ? (int) $metadata['width'] : 0;
						$height   = isset( $metadata['height'] ) ? (int) $metadata['height'] : 0;
						$file     = get_attached_file( $attachment_id );
						$size     = $file && file_exists( $file ) ? (int) filesize( $file ) : 0;
						return ( empty( $filters['min_width'] ) || $width >= (int) $filters['min_width'] )
							&& ( empty( $filters['max_width'] ) || $width <= (int) $filters['max_width'] )
							&& ( empty( $filters['min_height'] ) || $height >= (int) $filters['min_height'] )
							&& ( empty( $filters['max_height'] ) || $height <= (int) $filters['max_height'] )
							&& ( empty( $filters['min_file_size'] ) || $size >= (int) $filters['min_file_size'] )
							&& ( empty( $filters['max_file_size'] ) || $size <= (int) $filters['max_file_size'] );
					}
				)
			);
			$ids = array_slice( $ids, max( 0, absint( $offset ) ), min( 10000, max( 1, absint( $limit ) ) ) );
		}
		return $ids;
	}

	/**
	 * Count image attachments with a metadata flag.
	 *
	 * @param string $key       Metadata key.
	 * @param bool   $non_empty Require a non-empty value.
	 * @return int Matching attachment count.
	 */
	private function count_meta_flag( $key, $non_empty ) {
		global $wpdb;
		$comparison = $non_empty ? "AND m.meta_value!=''" : '';
		$sql        = $wpdb->prepare( "SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} m ON m.post_id=p.ID AND m.meta_key=%s $comparison WHERE p.post_type='attachment' AND p.post_mime_type LIKE 'image/%%'", $key ); // phpcs:ignore
		return (int) $wpdb->get_var( $sql ); // phpcs:ignore
	}

	/**
	 * Count image attachments with a metadata value.
	 *
	 * @param string $key   Metadata key.
	 * @param string $value Metadata value.
	 * @return int Matching attachment count.
	 */
	private function count_meta_value( $key, $value ) {
		global $wpdb;
		$sql = $wpdb->prepare( "SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} m ON m.post_id=p.ID AND m.meta_key=%s AND m.meta_value=%s WHERE p.post_type='attachment' AND p.post_mime_type LIKE 'image/%%'", $key, $value ); // phpcs:ignore
		return (int) $wpdb->get_var( $sql ); // phpcs:ignore
	}

	/**
	 * Count attachments with suggestions in a given status.
	 *
	 * @param string $status Suggestion status.
	 * @return int Matching attachment count.
	 */
	private function count_suggestions( $status ) {
		global $wpdb;
		$table = Occidg_Database::table( 'suggestions' );
		if ( ! $table ) {
			return 0;
		}
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT attachment_id) FROM $table WHERE status=%s", $status ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Count batch items with any supplied status.
	 *
	 * @param array $statuses Batch item statuses.
	 * @return int Matching item count.
	 */
	private function count_batch_items( $statuses ) {
		global $wpdb;
		$table = Occidg_Database::table( 'batch_items' );
		if ( ! $table ) {
			return 0;
		}
		$placeholders = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table WHERE status IN ($placeholders)", ...$statuses ) ); // phpcs:ignore
	}
}
