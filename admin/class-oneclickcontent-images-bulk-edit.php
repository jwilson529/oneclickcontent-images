<?php
/**
 * Bulk edit functionality for the OneClickContent Image Details plugin.
 *
 * Handles the bulk edit tab and related AJAX actions for editing and generating image metadata.
 *
 * @package    One_Click_Images
 * @subpackage One_Click_Images/admin
 * @author     OneClickContent <support@oneclickcontent.com>
 * @since      1.0.0
 * @copyright  2025 OneClickContent
 * @license    GPL-2.0+
 * @link       https://oneclickcontent.com
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Class OneClickContent_Images_Bulk_Edit
 *
 * Manages bulk editing and metadata generation in the Media Library.
 *
 * @since 1.0.0
 */
class OneClickContent_Images_Bulk_Edit {

    /**
     * Render the bulk edit tab content.
     *
     * @since 1.0.0
     * @return void
     */
    public function render_bulk_edit_tab() {
        $license_status = get_option( 'oneclick_images_license_status', 'unknown' );
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Bulk Edit Metadata', 'oneclickcontent-images' ); ?></h1>
            <div class="bulk-edit-header">
                <button id="generate-all-metadata" class="button button-primary button-hero">
                    <?php esc_html_e( 'Generate All Metadata', 'oneclickcontent-images' ); ?>
                </button>
                <p class="description"><?php esc_html_e( 'Edit metadata manually below, or let our AI generate it for you—one image at a time or all at once!', 'oneclickcontent-images' ); ?></p>
            </div>

            <!-- Usage Information Section -->
            <div class="usage-info-section">
                <h2><?php esc_html_e( 'Your Usage', 'oneclickcontent-images' ); ?></h2>
                <?php if ( 'active' !== $license_status ) : ?>
                	<div class="bulk-edit-license-warning compact">
                	  <div class="cta-left">
                	    <h2><?php esc_html_e( 'Never Forget an Alt Tag Again!', 'oneclickcontent-images' ); ?></h2>
                	    <p>
                	      <?php esc_html_e( 'Upgrade now to automatically generate metadata for your images. Save time and boost your site’s SEO, accessibility, and image searchability.', 'oneclickcontent-images' ); ?>
                	    </p>
                	    <ul class="benefits-list">
                	      <li><?php esc_html_e( 'Save time with automated metadata generation', 'oneclickcontent-images' ); ?></li>
                	      <li><?php esc_html_e( 'Ensure every image has a descriptive alt tag', 'oneclickcontent-images' ); ?></li>
                	      <li><?php esc_html_e( 'Improve SEO, ADA compliance, and user experience', 'oneclickcontent-images' ); ?></li>
                	    </ul>
                	  </div>
                	  <div class="cta-right">
                	    <a href="https://oneclickcontent.com/image-detail-generator/" target="_blank" class="btn-license">
                	      <?php esc_html_e( 'Activate License Now', 'oneclickcontent-images' ); ?>
                	    </a>
                	  </div>
                	</div>
                <?php else : ?>
                    <p><?php esc_html_e( 'Track your usage and remaining allowance.', 'oneclickcontent-images' ); ?></p>
                    <div id="usage_status" class="usage-summary">
                        <strong id="usage_count"><?php esc_html_e( 'Loading usage data...', 'oneclickcontent-images' ); ?></strong>
                        <div class="progress">
                            <div 
                                id="usage_progress" 
                                class="progress-bar bg-success" 
                                role="progressbar" 
                                aria-valuenow="0" 
                                aria-valuemin="0" 
                                aria-valuemax="100" 
                                style="width: 0%;"
                            >
                                0%
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <hr class="settings-divider" />

            <!-- Move bulk-generate-status above the table -->
            <div id="bulk-generate-status" class="bulk-generate-status">
                <!-- Status messages will appear here -->
            </div>

            <table id="image-metadata-table" class="display" style="width:100%">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Thumbnail', 'oneclickcontent-images' ); ?></th>
                        <th><?php esc_html_e( 'Title', 'oneclickcontent-images' ); ?></th>
                        <th><?php esc_html_e( 'Alt Text', 'oneclickcontent-images' ); ?></th>
                        <th><?php esc_html_e( 'Description', 'oneclickcontent-images' ); ?></th>
                        <th><?php esc_html_e( 'Caption', 'oneclickcontent-images' ); ?></th>
                        <th><?php esc_html_e( 'Actions', 'oneclickcontent-images' ); ?></th>
                    </tr>
                </thead>
            </table>
        </div>
        <?php
    }

    /**
     * Fetch image metadata for DataTables via AJAX with server-side pagination.
     *
     * @since 1.0.0
     * @return void
     */
    public function get_image_metadata() {
        check_ajax_referer( 'oneclick_images_bulk_edit', 'nonce' );
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( 'Permission denied.' );
        }

        $draw = isset( $_REQUEST['draw'] ) ? intval( $_REQUEST['draw'] ) : 1;
        $start = isset( $_REQUEST['start'] ) ? intval( $_REQUEST['start'] ) : 0;
        $length = isset( $_REQUEST['length'] ) ? intval( $_REQUEST['length'] ) : 10;
        $search_value = isset( $_REQUEST['search']['value'] ) ? sanitize_text_field( $_REQUEST['search']['value'] ) : '';

        $page = $start / $length + 1;

        $args = array(
            'post_type'      => 'attachment',
            'post_mime_type' => 'image',
            'post_status'    => 'inherit',
            'posts_per_page' => $length,
            'paged'          => $page,
            'orderby'        => 'post_date',
            'order'          => 'DESC',
        );

        if ( ! empty( $search_value ) ) {
            $args['s'] = $search_value;
        }

        $total_query = new WP_Query( array_merge( $args, array( 'posts_per_page' => -1, 'fields' => 'ids' ) ) );
        $records_total = $total_query->found_posts;

        $query = new WP_Query( $args );
        $records_filtered = $query->found_posts;

        $data = array();
        foreach ( $query->posts as $post ) {
            $thumbnail = wp_get_attachment_image( $post->ID, 'thumbnail', false, array( 'style' => 'max-width: 50px;' ) );
            $data[] = array(
                'thumbnail'   => $thumbnail,
                'title'       => esc_html( get_the_title( $post->ID ) ),
                'alt_text'    => esc_attr( get_post_meta( $post->ID, '_wp_attachment_image_alt', true ) ),
                'description' => esc_textarea( $post->post_content ),
                'caption'     => esc_textarea( $post->post_excerpt ),
                'id'          => $post->ID,
            );
        }

        wp_send_json( array(
            'draw'            => $draw,
            'recordsTotal'    => $records_total,
            'recordsFiltered' => $records_filtered,
            'data'            => $data,
        ) );
    }

    /**
     * Save edited metadata via AJAX.
     *
     * @since 1.0.0
     * @return void
     */
    public function save_bulk_metadata() {
        check_ajax_referer('oneclick_images_bulk_edit', 'nonce');
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Permission denied.');
        }

        $image_id = isset($_POST['image_id']) ? absint($_POST['image_id']) : 0;
        $title = isset($_POST['title']) ? sanitize_text_field($_POST['title']) : '';
        $alt_text = isset($_POST['alt_text']) ? sanitize_text_field($_POST['alt_text']) : '';
        $description = isset($_POST['description']) ? sanitize_textarea_field($_POST['description']) : '';
        $caption = isset($_POST['caption']) ? sanitize_textarea_field($_POST['caption']) : '';

        if (!$image_id) {
            wp_send_json_error('Invalid image ID.');
        }

        // Update the metadata
        update_post_meta($image_id, '_wp_attachment_image_alt', $alt_text);
        wp_update_post([
            'ID'           => $image_id,
            'post_title'   => $title,
            'post_content' => $description,
            'post_excerpt' => $caption,
        ]);

        // Return the updated metadata
        $updated_data = [
            'id'          => $image_id,
            'title'       => $title,
            'alt_text'    => $alt_text,
            'description' => $description,
            'caption'     => $caption,
            // Optionally include thumbnail if your DataTable needs it
            'thumbnail'   => wp_get_attachment_image($image_id, 'thumbnail', false, ['class' => 'thumbnail-preview']),
        ];

        wp_send_json_success($updated_data);
    }
}