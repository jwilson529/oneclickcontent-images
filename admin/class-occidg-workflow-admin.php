<?php
/**
 * Administration screens and secured workflow actions.
 *
 * @package Occidg
 */

defined( 'ABSPATH' ) || exit;

/** Renders the production safety, review, batch, and history experience. */
class Occidg_Workflow_Admin {
	/** User-meta key for the last valid batch planner choices. */
	const BATCH_PREFERENCES_META_KEY = '_occ_idg_batch_planner_preferences';

	/**
	 * Workflow service.
	 *
	 * @var Occidg_Workflow
	 */
	private $workflow;

	/**
	 * Metadata service.
	 *
	 * @var Occidg_Metadata
	 */
	private $metadata;

	/**
	 * Database service.
	 *
	 * @var Occidg_Database
	 */
	private $database;

	/**
	 * Preflight service.
	 *
	 * @var Occidg_Preflight
	 */
	private $preflight;

	/**
	 * Background jobs controller.
	 *
	 * @var Occidg_Background_Jobs_Admin
	 */
	private $jobs_admin;

	/**
	 * Editable metadata table controller.
	 *
	 * @var Occidg_Bulk_Edit
	 */
	private $bulk_edit;

	/**
	 * Construct the workflow administration controller.
	 *
	 * @param Occidg_Workflow              $workflow   Workflow service.
	 * @param Occidg_Metadata              $metadata   Metadata service.
	 * @param Occidg_Database              $database   Database service.
	 * @param Occidg_Preflight             $preflight  Preflight service.
	 * @param Occidg_Background_Jobs_Admin $jobs_admin Background jobs controller.
	 * @param Occidg_Bulk_Edit             $bulk_edit  Editable metadata table controller.
	 */
	public function __construct( $workflow, $metadata, $database, $preflight, $jobs_admin, $bulk_edit = null ) {
		$this->workflow   = $workflow;
		$this->metadata   = $metadata;
		$this->database   = $database;
		$this->preflight  = $preflight;
		$this->jobs_admin = $jobs_admin;
		$this->bulk_edit  = $bulk_edit ? $bulk_edit : new Occidg_Bulk_Edit( $database );
	}

	/** Register focused submenus beneath the existing Image Metadata menu. */
	public function register_menus() {
		add_submenu_page( 'occidg', __( 'Metadata Preflight', 'occidg' ), __( 'Dashboard', 'occidg' ), 'occ_idg_view_dashboard', 'occ-idg-dashboard', array( $this, 'render_dashboard' ) );
		add_submenu_page( 'occidg', __( 'Image Library', 'occidg' ), __( 'Image Library', 'occidg' ), 'occ_idg_view_dashboard', 'occ-idg-audit', array( $this, 'render_audit' ) );
		add_submenu_page( null, __( 'Suggestion Review', 'occidg' ), __( 'Review Queue', 'occidg' ), 'occ_idg_review_suggestions', 'occ-idg-review', array( $this, 'render_review' ) );
		add_submenu_page( 'occidg', __( 'Metadata Batches', 'occidg' ), __( 'Batches', 'occidg' ), 'occ_idg_manage_batches', 'occ-idg-batches', array( $this, 'render_batches' ) );
		add_submenu_page( 'occidg', __( 'Metadata Change History', 'occidg' ), __( 'History', 'occidg' ), 'occ_idg_restore_metadata', 'occ-idg-history', array( $this, 'render_history' ) );
	}

	/** Register safety and generation settings with strict sanitization. */
	public function register_settings() {
		$settings = array(
			'occ_idg_default_mode'                   => array( 'fill_missing', 'sanitize_key' ),
			'occ_idg_default_batch_size'             => array( 25, 'absint' ),
			'occ_idg_max_batch_size'                 => array( 1000, 'absint' ),
			'occ_idg_retry_count'                    => array( 3, 'absint' ),
			'occ_idg_request_timeout'                => array( 60, 'absint' ),
			'occ_idg_images_per_queue_action'        => array( 5, 'absint' ),
			'occ_idg_delay_between_requests'         => array( 5, 'absint' ),
			'occ_idg_max_response_tokens'            => array( 500, 'absint' ),
			'occ_idg_history_retention_days'         => array( 0, 'absint' ),
			'occ_idg_remove_data_on_uninstall'       => array( false, 'rest_sanitize_boolean' ),
			'occ_idg_organization_name'              => array( '', 'sanitize_text_field' ),
			'occ_idg_site_description'               => array( '', 'sanitize_textarea_field' ),
			'occ_idg_editorial_tone'                 => array( '', 'sanitize_text_field' ),
			'occ_idg_preferred_terminology'          => array( '', 'sanitize_textarea_field' ),
			'occ_idg_prohibited_terminology'         => array( '', 'sanitize_textarea_field' ),
			'occ_idg_title_capitalization'           => array( 'title', 'sanitize_key' ),
			'occ_idg_max_alt_length'                 => array( 160, 'absint' ),
			'occ_idg_custom_prompt_instructions'     => array( '', 'sanitize_textarea_field' ),
			'occ_idg_allow_unpublished_context'      => array( false, 'rest_sanitize_boolean' ),
			'occ_idg_allow_private_context'          => array( false, 'rest_sanitize_boolean' ),
			'occ_idg_allow_overwrite'                => array( false, 'rest_sanitize_boolean' ),
			'occ_idg_require_overwrite_confirmation' => array( true, 'rest_sanitize_boolean' ),
			'occ_idg_require_caption_review'         => array( true, 'rest_sanitize_boolean' ),
			'occ_idg_require_low_confidence_review'  => array( true, 'rest_sanitize_boolean' ),
			'occ_idg_preserve_human_metadata'        => array( true, 'rest_sanitize_boolean' ),
			'occ_idg_daily_request_ceiling'          => array( 500, 'absint' ),
			'occ_idg_max_estimated_batch_cost'       => array( 0, 'floatval' ),
			'occ_idg_openai_cost_per_request'        => array( 0, 'floatval' ),
			'occ_idg_gemini_cost_per_request'        => array( 0, 'floatval' ),
		);
		foreach ( $settings as $name => $config ) {
			register_setting(
				'occidg_settings',
				$name,
				array(
					'sanitize_callback' => $config[1],
					'default'           => $config[0],
				)
			);
		}
		add_settings_section( 'occ_idg_safety_section', __( 'Safety and Review', 'occidg' ), array( $this, 'render_settings_intro' ), 'occidg_settings' );
		add_settings_field( 'occ_idg_workflow_options', __( 'Recommended Protections', 'occidg' ), array( $this, 'render_workflow_settings' ), 'occidg_settings', 'occ_idg_safety_section' );
	}

	/** Render the safety settings introduction. */
	public function render_settings_intro() {
		echo '<p>' . esc_html__( 'The recommended settings protect existing work and send uncertain results to a person for review.', 'occidg' ) . '</p>';
		echo '<div class="occidg-safety-summary"><span class="dashicons dashicons-shield" aria-hidden="true"></span><div><strong>' . esc_html__( 'Safe defaults are already on.', 'occidg' ) . '</strong><p>' . esc_html__( 'You only need the advanced controls when your site has a special editorial, privacy, or processing requirement.', 'occidg' ) . '</p></div></div>';
	}

	/** Render workflow safety and limit settings. */
	public function render_workflow_settings() {
		$recommended = array(
			'occ_idg_preserve_human_metadata'       => array( __( 'Protect human-written metadata', 'occidg' ), __( 'Keep existing titles, alt text, captions, and descriptions unless overwrite mode is deliberately enabled.', 'occidg' ), true ),
			'occ_idg_require_caption_review'        => array( __( 'Review automatic captions before publishing', 'occidg' ), __( 'Automatic uploads hold captions for review. An explicit Fill missing action applies its caption; Create review suggestions keeps every selected field pending.', 'occidg' ), true ),
			'occ_idg_require_low_confidence_review' => array( __( 'Review uncertain suggestions', 'occidg' ), __( 'Keep low-confidence results as saved suggestions in the Image Library instead of applying them automatically.', 'occidg' ), true ),
		);

		echo '<div class="occidg-workflow-basics">';
		foreach ( $recommended as $name => $setting ) {
			printf(
				'<label class="occidg-toggle-card" for="%1$s"><input id="%1$s" type="checkbox" name="%1$s" value="1" %2$s><span class="occidg-toggle-copy"><span class="occidg-toggle-title">%3$s</span><span class="occidg-toggle-description">%4$s</span></span></label>',
				esc_attr( $name ),
				checked( (bool) get_option( $name, $setting[2] ), true, false ),
				esc_html( $setting[0] ),
				esc_html( $setting[1] )
			);
		}
		echo '</div>';

		echo '<details class="occidg-disclosure occidg-advanced-settings"><summary><span><strong>' . esc_html__( 'Advanced workflow settings', 'occidg' ) . '</strong><small>' . esc_html__( 'Processing limits, editorial guidance, privacy, overwrite, and data removal', 'occidg' ) . '</small></span></summary><div class="occidg-disclosure__content">';

		echo '<section class="occidg-settings-group"><h3>' . esc_html__( 'Processing limits', 'occidg' ) . '</h3><p>' . esc_html__( 'The defaults are intentionally conservative and work well for most sites.', 'occidg' ) . '</p><div class="occidg-compact-grid">';
		$numbers = array(
			'occ_idg_default_batch_size'       => array( __( 'Default batch size', 'occidg' ), 25, 1 ),
			'occ_idg_max_batch_size'           => array( __( 'Maximum batch size', 'occidg' ), 1000, 1 ),
			'occ_idg_retry_count'              => array( __( 'Retry attempts', 'occidg' ), 3, 1 ),
			'occ_idg_request_timeout'          => array( __( 'Request timeout (seconds)', 'occidg' ), 60, 1 ),
			'occ_idg_images_per_queue_action'  => array( __( 'Images per queue step', 'occidg' ), 5, 1 ),
			'occ_idg_delay_between_requests'   => array( __( 'Delay between queue steps', 'occidg' ), 5, 1 ),
			'occ_idg_max_response_tokens'      => array( __( 'Maximum response tokens', 'occidg' ), 500, 1 ),
			'occ_idg_daily_request_ceiling'    => array( __( 'Daily request ceiling', 'occidg' ), 500, 1 ),
			'occ_idg_max_alt_length'           => array( __( 'Maximum alt-text length', 'occidg' ), 160, 1 ),
			'occ_idg_history_retention_days'   => array( __( 'History retention days (0 keeps all)', 'occidg' ), 0, 1 ),
			'occ_idg_max_estimated_batch_cost' => array( __( 'Estimated batch cost cap in USD (0 disables)', 'occidg' ), 0, 0.01 ),
		);
		foreach ( $numbers as $name => $setting ) {
			printf( '<label><span>%1$s</span><input type="number" min="0" step="%2$s" name="%3$s" value="%4$s"></label>', esc_html( $setting[0] ), esc_attr( $setting[2] ), esc_attr( $name ), esc_attr( get_option( $name, $setting[1] ) ) );
		}
		echo '</div></section>';

		echo '<section class="occidg-settings-group"><h3>' . esc_html__( 'Editorial guidance', 'occidg' ) . '</h3><p>' . esc_html__( 'Leave these blank unless generated text needs to follow an established house style.', 'occidg' ) . '</p><div class="occidg-editorial-grid">';
		$texts = array(
			'occ_idg_organization_name'          => __( 'Organization name', 'occidg' ),
			'occ_idg_site_description'           => __( 'Site or audience description', 'occidg' ),
			'occ_idg_editorial_tone'             => __( 'Editorial tone', 'occidg' ),
			'occ_idg_preferred_terminology'      => __( 'Preferred terminology', 'occidg' ),
			'occ_idg_prohibited_terminology'     => __( 'Words or phrases to avoid', 'occidg' ),
			'occ_idg_custom_prompt_instructions' => __( 'Additional instructions', 'occidg' ),
		);
		foreach ( $texts as $name => $label ) {
			printf( '<label><span>%1$s</span><textarea class="large-text" rows="2" name="%2$s">%3$s</textarea></label>', esc_html( $label ), esc_attr( $name ), esc_textarea( get_option( $name, '' ) ) );
		}
		echo '</div></section>';

		echo '<section class="occidg-settings-group"><h3>' . esc_html__( 'Overwrite and privacy', 'occidg' ) . '</h3><div class="occidg-workflow-basics">';
		$advanced_checks = array(
			'occ_idg_allow_overwrite'                => array( __( 'Allow overwrite mode', 'occidg' ), __( 'Makes the destructive overwrite option available to authorized users.', 'occidg' ), false ),
			'occ_idg_require_overwrite_confirmation' => array( __( 'Require overwrite confirmation', 'occidg' ), __( 'Ask for an explicit confirmation every time overwrite mode is used.', 'occidg' ), true ),
			'occ_idg_allow_unpublished_context'      => array( __( 'Use unpublished parent context', 'occidg' ), __( 'Allow draft and pending parent content to guide metadata.', 'occidg' ), false ),
			'occ_idg_allow_private_context'          => array( __( 'Use private parent context', 'occidg' ), __( 'Private content may be sent to the selected AI provider. Leave this off unless approved.', 'occidg' ), false ),
		);
		foreach ( $advanced_checks as $name => $setting ) {
			printf( '<label class="occidg-toggle-card" for="%1$s"><input id="%1$s" type="checkbox" name="%1$s" value="1" %2$s><span class="occidg-toggle-copy"><span class="occidg-toggle-title">%3$s</span><span class="occidg-toggle-description">%4$s</span></span></label>', esc_attr( $name ), checked( (bool) get_option( $name, $setting[2] ), true, false ), esc_html( $setting[0] ), esc_html( $setting[1] ) );
		}
		echo '</div><div class="occidg-privacy-note"><strong>' . esc_html__( 'Privacy reminder', 'occidg' ) . '</strong><p>' . esc_html__( 'The selected image and configured context are sent to the external AI provider. An AI API does not automatically make processing compliant with HIPAA, GDPR, or another regulation.', 'occidg' ) . '</p></div></section>';

		echo '<section class="occidg-settings-group occidg-danger-zone"><h3>' . esc_html__( 'Data removal', 'occidg' ) . '</h3><label class="occidg-toggle-card" for="occ_idg_remove_data_on_uninstall"><input id="occ_idg_remove_data_on_uninstall" type="checkbox" name="occ_idg_remove_data_on_uninstall" value="1" ' . checked( (bool) get_option( 'occ_idg_remove_data_on_uninstall', false ), true, false ) . '><span class="occidg-toggle-copy"><span class="occidg-toggle-title">' . esc_html__( 'Delete plugin data when uninstalled', 'occidg' ) . '</span><span class="occidg-toggle-description">' . esc_html__( 'Permanently remove workflow history, suggestions, batches, and plugin settings during uninstall.', 'occidg' ) . '</span></span></label></section>';

		echo '</div></details>';
	}

	/**
	 * Render the shared heading and local navigation for workflow screens.
	 *
	 * @param string $title       Page title.
	 * @param string $description Short page description.
	 * @param string $active      Active navigation slug.
	 */
	private function render_workflow_header( $title, $description, $active ) {
		$library_url = admin_url( 'admin.php?page=occ-idg-audit' );
		$items       = array(
			'dashboard' => array( __( 'Dashboard', 'occidg' ), 'occ-idg-dashboard', 'occ_idg_view_dashboard' ),
			'library'   => array( __( 'Image Library', 'occidg' ), 'occ-idg-audit', 'occ_idg_view_dashboard' ),
			'batches'   => array( __( 'Batches', 'occidg' ), 'occ-idg-batches', 'occ_idg_manage_batches' ),
			'history'   => array( __( 'History', 'occidg' ), 'occ-idg-history', 'occ_idg_restore_metadata' ),
		);

		echo '<header class="occidg-page-header"><div><p class="occidg-eyebrow">' . esc_html__( 'Image Metadata workflow', 'occidg' ) . '</p><h1>' . esc_html( $title ) . '</h1><p>' . esc_html( $description ) . '</p></div>';
		if ( 'library' !== $active ) {
			echo '<a class="button button-primary button-hero" href="' . esc_url( $library_url ) . '">' . esc_html__( 'Open Image Library', 'occidg' ) . '</a>';
		}
		echo '</header>';
		echo '<nav class="occidg-workflow-nav" aria-label="' . esc_attr__( 'Image metadata workflow', 'occidg' ) . '">';
		foreach ( $items as $slug => $item ) {
			if ( ! current_user_can( $item[2] ) ) {
				continue;
			}
			printf( '<a href="%1$s" class="%2$s" %3$s>%4$s</a>', esc_url( admin_url( 'admin.php?page=' . $item[1] ) ), esc_attr( $slug === $active ? 'is-active' : '' ), $slug === $active ? 'aria-current="page"' : '', esc_html( $item[0] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed aria-current attribute.
		}
		echo '</nav>';
	}

	/** Render preflight counts and safe batch launcher. */
	public function render_dashboard() {
		$this->guard( 'occ_idg_view_dashboard' );
		$metrics = $this->preflight->get_metrics( get_option( 'occidg_metadata_fields', array() ) );
		echo '<div class="wrap occidg-admin-shell occidg-workflow-page">';
		$this->render_workflow_header( __( 'Metadata Dashboard', 'occidg' ), __( 'See what needs attention, then run a safe preview before changing anything.', 'occidg' ), 'dashboard' );
		echo '<section class="occidg-section"><div class="occidg-section-heading"><div><p class="occidg-eyebrow">' . esc_html__( 'At a glance', 'occidg' ) . '</p><h2>' . esc_html__( 'Your Media Library', 'occidg' ) . '</h2></div><a class="button" href="' . esc_url( admin_url( 'admin.php?page=occ-idg-audit' ) ) . '">' . esc_html__( 'Open Image Library', 'occidg' ) . '</a></div><div class="occ-idg-metrics occidg-metrics-primary">';
		$labels       = array(
			'total'                   => __( 'Total image attachments', 'occidg' ),
			'missing_alt_text'        => __( 'Missing alt text', 'occidg' ),
			'missing_title'           => __( 'Missing titles', 'occidg' ),
			'missing_caption'         => __( 'Missing captions', 'occidg' ),
			'missing_description'     => __( 'Missing descriptions', 'occidg' ),
			'complete_enabled_fields' => __( 'Complete for enabled fields', 'occidg' ),
			'pending_suggestions'     => __( 'Pending suggestions', 'occidg' ),
			'processed'               => __( 'Previously processed', 'occidg' ),
			'human_reviewed'          => __( 'Human reviewed', 'occidg' ),
			'failed'                  => __( 'Failed attempts', 'occidg' ),
			'decorative'              => __( 'Decorative', 'occidg' ),
			'queued_processing'       => __( 'Queued or processing', 'occidg' ),
		);
		$primary_keys = array( 'total', 'missing_alt_text', 'pending_suggestions', 'failed' );
		foreach ( $primary_keys as $key ) {
			printf( '<div class="occidg-metric-card"><p>%1$s</p><strong>%2$s</strong></div>', esc_html( $labels[ $key ] ), esc_html( number_format_i18n( $metrics[ $key ] ) ) );
		}
		echo '</div><details class="occidg-disclosure occidg-library-details"><summary><span><strong>' . esc_html__( 'More library details', 'occidg' ) . '</strong><small>' . esc_html__( 'Titles, captions, descriptions, review status, and processing totals', 'occidg' ) . '</small></span></summary><div class="occidg-disclosure__content"><div class="occ-idg-metrics">';
		foreach ( array_diff( array_keys( $labels ), $primary_keys ) as $key ) {
			printf( '<div class="occidg-metric-card is-secondary"><p>%1$s</p><strong>%2$s</strong></div>', esc_html( $labels[ $key ] ), esc_html( number_format_i18n( $metrics[ $key ] ) ) );
		}
		echo '</div></div></details></section>';

		$this->render_batch_form();

		if ( current_user_can( 'occ_idg_export_reports' ) ) {
			echo '<details class="occidg-disclosure occidg-admin-tools"><summary><span><strong>' . esc_html__( 'Reports and advanced tools', 'occidg' ) . '</strong><small>' . esc_html__( 'Download CSV reports for analysis or record keeping', 'occidg' ) . '</small></span></summary><div class="occidg-disclosure__content"><div class="occ-idg-export-links">';
			foreach ( array(
				'missing'     => __( 'Missing metadata', 'occidg' ),
				'suggestions' => __( 'Suggestions', 'occidg' ),
				'failures'    => __( 'Failures', 'occidg' ),
				'history'     => __( 'History', 'occidg' ),
				'usage'       => __( 'Provider usage', 'occidg' ),
			) as $type => $label ) {
				$url = wp_nonce_url( admin_url( 'admin-post.php?action=occ_idg_export&type=' . $type ), 'occ_idg_export' );
				printf( '<a class="button" href="%1$s">%2$s</a> ', esc_url( $url ), esc_html( $label ) );
			}
			echo '</div></div></details>';
		}
		if ( ! empty( $_GET['dry_run'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$report = get_transient( 'occ_idg_dry_run_' . get_current_user_id() );
			if ( is_array( $report ) ) {
				$this->render_dry_run_report( $report );
			}
		}
		echo '</div>';
	}

	/**
	 * Sanitize saved or submitted batch planner choices.
	 *
	 * @param array $preferences Candidate planner preferences.
	 * @return array Normalized preferences.
	 */
	private function normalize_batch_planner_preferences( $preferences ) {
		$preferences = is_array( $preferences ) ? $preferences : array();
		$limit       = isset( $preferences['limit'] ) ? (string) $preferences['limit'] : '25';
		$limit       = in_array( $limit, array( '10', '25', '50', '100', 'custom' ), true ) ? $limit : '25';
		$max_limit   = max( 1, absint( get_option( 'occ_idg_max_batch_size', 1000 ) ) );
		$custom      = isset( $preferences['custom_limit'] ) ? absint( $preferences['custom_limit'] ) : 25;
		$mode        = isset( $preferences['mode'] ) ? sanitize_key( $preferences['mode'] ) : 'dry_run';
		$order       = isset( $preferences['order'] ) ? sanitize_key( $preferences['order'] ) : 'oldest';
		$missing     = isset( $preferences['missing_field'] ) ? sanitize_key( $preferences['missing_field'] ) : '';

		return array(
			'fields'        => Occidg_Metadata::normalize_fields( isset( $preferences['fields'] ) ? $preferences['fields'] : array( 'alt_text', 'title' ) ),
			'limit'         => $limit,
			'custom_limit'  => min( max( 1, $custom ), $max_limit ),
			'mode'          => in_array( $mode, array( 'dry_run', 'suggestion', 'fill_missing', 'overwrite' ), true ) ? $mode : 'dry_run',
			'order'         => in_array( $order, array( 'oldest', 'newest', 'random' ), true ) ? $order : 'oldest',
			'missing_field' => in_array( $missing, array( '', 'alt_text', 'title', 'caption', 'description' ), true ) ? $missing : '',
		);
	}

	/** Render the controlled batch planning form. */
	private function render_batch_form() {
		if ( ! current_user_can( 'occ_idg_generate_metadata' ) ) {
			return;
		}

		$saved_preferences = get_user_meta( get_current_user_id(), self::BATCH_PREFERENCES_META_KEY, true );
		$preferences       = $this->normalize_batch_planner_preferences( is_array( $saved_preferences ) ? $saved_preferences : array() );
		if ( empty( $preferences['fields'] ) ) {
			$preferences['fields'] = array( 'alt_text', 'title' );
		}
		if ( 'overwrite' === $preferences['mode'] && ( ! get_option( 'occ_idg_allow_overwrite', false ) || ! current_user_can( 'occ_idg_overwrite_metadata' ) ) ) {
			$preferences['mode'] = 'dry_run';
		}
		$advanced_open = 'dry_run' !== $preferences['mode'] || 'oldest' !== $preferences['order'] || '' !== $preferences['missing_field'];
		$mode_labels   = array(
			'dry_run'      => __( 'Run Preview', 'occidg' ),
			'suggestion'   => __( 'Create Suggestions', 'occidg' ),
			'fill_missing' => __( 'Fill Missing Metadata', 'occidg' ),
			'overwrite'    => __( 'Run Overwrite', 'occidg' ),
		);
		?>
		<section class="occidg-section occidg-batch-planner">
			<div class="occidg-section-heading">
				<div>
					<p class="occidg-eyebrow"><?php esc_html_e( 'Batch planner', 'occidg' ); ?></p>
					<h2><?php esc_html_e( 'Choose what to run', 'occidg' ); ?></h2>
					<p><?php esc_html_e( 'Your choices are remembered for next time. Preview mode estimates the work without changing metadata.', 'occidg' ); ?></p>
				</div>
				<span class="occidg-status-pill is-success">
					<?php echo esc_html( is_array( $saved_preferences ) && ! empty( $saved_preferences ) ? __( 'Last choices restored', 'occidg' ) : __( 'Safe defaults selected', 'occidg' ) ); ?>
				</span>
			</div>
			<form class="occidg-batch-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php
		wp_nonce_field( 'occ_idg_create_batch' );
		echo '<input type="hidden" name="action" value="occ_idg_create_batch"><div class="occidg-batch-steps"><fieldset><legend><span>1</span>' . esc_html__( 'Choose the details to check', 'occidg' ) . '</legend><div class="occidg-choice-grid">';
		foreach ( array(
			'alt_text'    => array( __( 'Alternative text', 'occidg' ), __( 'Recommended for accessibility.', 'occidg' ) ),
			'title'       => array( __( 'Title', 'occidg' ), __( 'Helps organize the Media Library.', 'occidg' ) ),
			'caption'     => array( __( 'Caption', 'occidg' ), __( 'May be visible to website visitors.', 'occidg' ) ),
			'description' => array( __( 'Description', 'occidg' ), __( 'Adds longer internal context.', 'occidg' ) ),
		) as $field => $setting ) {
			printf( '<label class="occidg-choice-card"><input type="checkbox" name="fields[]" value="%1$s" %2$s><span class="occidg-choice-copy"><span class="occidg-choice-title">%3$s</span><span class="occidg-choice-description">%4$s</span></span></label>', esc_attr( $field ), checked( in_array( $field, $preferences['fields'], true ), true, false ), esc_html( $setting[0] ), esc_html( $setting[1] ) );
		}
		echo '</div></fieldset><fieldset><legend><span>2</span>' . esc_html__( 'Choose how many images to include', 'occidg' ) . '</legend><div class="occidg-inline-field"><label for="occidg-batch-limit">' . esc_html__( 'Batch size', 'occidg' ) . '</label><select id="occidg-batch-limit" name="limit">';
		foreach ( array( '10', '25', '50', '100' ) as $limit_choice ) {
			printf( '<option value="%1$s" %2$s>%1$s</option>', esc_attr( $limit_choice ), selected( $preferences['limit'], $limit_choice, false ) );
		}
		printf( '<option value="custom" %1$s>%2$s</option></select><input type="number" name="custom_limit" min="1" value="%3$d" aria-label="%4$s"></div></fieldset></div>', selected( $preferences['limit'], 'custom', false ), esc_html__( 'Custom', 'occidg' ), (int) $preferences['custom_limit'], esc_attr__( 'Custom batch size', 'occidg' ) );

		printf( '<details class="occidg-disclosure occidg-batch-advanced" %1$s><summary><span><strong>%2$s</strong><small>%3$s</small></span></summary><div class="occidg-disclosure__content occidg-compact-grid"><label><span>%4$s</span><select name="mode"><option value="dry_run" %5$s>%6$s</option><option value="suggestion" %7$s>%8$s</option><option value="fill_missing" %9$s>%10$s</option>', $advanced_open ? 'open' : '', esc_html__( 'Advanced batch options', 'occidg' ), esc_html__( 'Apply suggestions, fill missing fields, change image selection, or use overwrite mode', 'occidg' ), esc_html__( 'What should this batch do?', 'occidg' ), selected( $preferences['mode'], 'dry_run', false ), esc_html__( 'Preview only — change nothing', 'occidg' ), selected( $preferences['mode'], 'suggestion', false ), esc_html__( 'Create suggestions for review', 'occidg' ), selected( $preferences['mode'], 'fill_missing', false ), esc_html__( 'Fill missing fields', 'occidg' ) );
		if ( get_option( 'occ_idg_allow_overwrite', false ) && current_user_can( 'occ_idg_overwrite_metadata' ) ) {
			printf( '<option value="overwrite" %1$s>%2$s</option>', selected( $preferences['mode'], 'overwrite', false ), esc_html__( 'Overwrite selected fields', 'occidg' ) );
		}
		echo '</select></label><label><span>' . esc_html__( 'Which images come first?', 'occidg' ) . '</span><select name="order"><option value="oldest" ' . selected( $preferences['order'], 'oldest', false ) . '>' . esc_html__( 'Oldest eligible', 'occidg' ) . '</option><option value="newest" ' . selected( $preferences['order'], 'newest', false ) . '>' . esc_html__( 'Newest eligible', 'occidg' ) . '</option><option value="random" ' . selected( $preferences['order'], 'random', false ) . '>' . esc_html__( 'Random sample', 'occidg' ) . '</option></select></label><label><span>' . esc_html__( 'Only include images missing', 'occidg' ) . '</span><select name="missing_field"><option value="" ' . selected( $preferences['missing_field'], '', false ) . '>' . esc_html__( 'Any selected detail', 'occidg' ) . '</option><option value="alt_text" ' . selected( $preferences['missing_field'], 'alt_text', false ) . '>' . esc_html__( 'Alternative text', 'occidg' ) . '</option><option value="title" ' . selected( $preferences['missing_field'], 'title', false ) . '>' . esc_html__( 'Title', 'occidg' ) . '</option><option value="caption" ' . selected( $preferences['missing_field'], 'caption', false ) . '>' . esc_html__( 'Caption', 'occidg' ) . '</option><option value="description" ' . selected( $preferences['missing_field'], 'description', false ) . '>' . esc_html__( 'Description', 'occidg' ) . '</option></select></label><label class="occ-idg-overwrite-confirm"><input type="checkbox" name="overwrite_confirmed" value="1"> <span>' . esc_html__( 'I understand overwrite mode can replace existing metadata.', 'occidg' ) . '</span></label></div></details>';
		echo '<p class="occidg-form-memory-note">' . esc_html__( 'These choices will be saved to your WordPress account when you run the batch. Overwrite confirmation is never remembered.', 'occidg' ) . '</p>';
		submit_button(
			$mode_labels[ $preferences['mode'] ],
			'primary',
			'submit',
			true,
			array(
				'class'                   => 'button button-primary button-hero',
				'data-dry-run-label'      => $mode_labels['dry_run'],
				'data-suggestion-label'   => $mode_labels['suggestion'],
				'data-fill-missing-label' => $mode_labels['fill_missing'],
				'data-overwrite-label'    => $mode_labels['overwrite'],
			)
		);
		echo '</form></section>';
	}

	/** Validate and create a dry run or resumable background batch. */
	public function handle_create_batch() {
		$this->guard( 'occ_idg_generate_metadata' );
		check_admin_referer( 'occ_idg_create_batch' );
		$preferences = $this->normalize_batch_planner_preferences(
			array(
				'fields'        => isset( $_POST['fields'] ) && is_array( $_POST['fields'] ) ? map_deep( wp_unslash( $_POST['fields'] ), 'sanitize_key' ) : array(),
				'limit'         => isset( $_POST['limit'] ) && ! is_array( $_POST['limit'] ) ? sanitize_text_field( wp_unslash( $_POST['limit'] ) ) : '25',
				'custom_limit'  => isset( $_POST['custom_limit'] ) && ! is_array( $_POST['custom_limit'] ) ? absint( $_POST['custom_limit'] ) : 25,
				'mode'          => isset( $_POST['mode'] ) && ! is_array( $_POST['mode'] ) ? sanitize_key( wp_unslash( $_POST['mode'] ) ) : 'dry_run',
				'order'         => isset( $_POST['order'] ) && ! is_array( $_POST['order'] ) ? sanitize_key( wp_unslash( $_POST['order'] ) ) : 'oldest',
				'missing_field' => isset( $_POST['missing_field'] ) && ! is_array( $_POST['missing_field'] ) ? sanitize_key( wp_unslash( $_POST['missing_field'] ) ) : '',
			)
		);
		$mode        = $preferences['mode'];
		$fields      = $preferences['fields'];
		$limit       = 'custom' === $preferences['limit'] ? $preferences['custom_limit'] : absint( $preferences['limit'] );
		if ( empty( $fields ) ) {
			wp_die( esc_html__( 'Select at least one metadata field.', 'occidg' ) );
		}
		if ( 'overwrite' === $mode && ( ! current_user_can( 'occ_idg_overwrite_metadata' ) || ! get_option( 'occ_idg_allow_overwrite', false ) || empty( $_POST['overwrite_confirmed'] ) ) ) {
			wp_die( esc_html__( 'Overwrite mode requires permission, settings activation, and immediate confirmation.', 'occidg' ) );
		}
		$filters = array(
			'order'         => $preferences['order'],
			'missing_field' => $preferences['missing_field'],
		);
		update_user_meta( get_current_user_id(), self::BATCH_PREFERENCES_META_KEY, $preferences );
		$ids = $this->preflight->query_ids( $filters, $limit );
		if ( 'dry_run' === $mode ) {
			$report = $this->workflow->dry_run( $ids, $fields, 'fill_missing' );
			set_transient( 'occ_idg_dry_run_' . get_current_user_id(), $report, 30 * MINUTE_IN_SECONDS );
			wp_safe_redirect( admin_url( 'admin.php?page=occ-idg-dashboard&dry_run=1' ) );
			exit;
		}
		$provider = get_option( 'occidg_provider', 'openai' );
		$model    = get_option( 'gemini' === $provider ? 'occidg_gemini_model' : 'occidg_openai_model', '' );
		$estimate = $this->workflow->dry_run( $ids, $fields, $mode, $provider );
		$cost_cap = (float) get_option( 'occ_idg_max_estimated_batch_cost', 0 );
		if ( $cost_cap > 0 && $estimate['estimated_cost'] > $cost_cap ) {
			wp_die( esc_html__( 'The estimated batch cost exceeds the configured safety limit.', 'occidg' ) );
		}
		$batch_id = $this->database->create_batch(
			array(
				/* translators: 1: processing mode, 2: batch creation time. */
				'name'               => sprintf( __( '%1$s batch — %2$s', 'occidg' ), ucfirst( str_replace( '_', ' ', $mode ) ), current_time( 'mysql' ) ),
				'mode'               => $mode,
				'provider'           => $provider,
				'model'              => $model,
				'requested_fields'   => $fields,
				'filters'            => wp_json_encode( $filters ),
				'estimated_requests' => $estimate['estimated_requests'],
				'estimated_cost'     => $estimate['estimated_cost'],
			),
			$ids
		);
		if ( false === $batch_id ) {
			wp_die( esc_html__( 'The batch could not be saved. Verify that eligible images and metadata fields are selected, then try again.', 'occidg' ) );
		}
		$job = $this->jobs_admin->create_job_from_image_ids(
			$ids,
			/* translators: %d: metadata batch ID. */
				sprintf( __( 'Metadata batch #%d', 'occidg' ), $batch_id ),
			array(
				'mode'                => $mode,
				'batch_id'            => $batch_id,
				'selected_fields'     => array_fill_keys( $fields, '1' ),
				'override_metadata'   => 'overwrite' === $mode,
				'overwrite_confirmed' => ! empty( $_POST['overwrite_confirmed'] ),
				'initiated_by'        => get_current_user_id(),
			)
		);
		if ( false === $job ) {
			$this->database->update_batch(
				$batch_id,
				array(
					'status'       => 'failed',
					'completed_at' => current_time( 'mysql', true ),
				)
			);
			wp_die( esc_html__( 'The background batch could not be queued. Verify provider credentials.', 'occidg' ) );
		}
		$this->database->update_batch(
			$batch_id,
			array(
				'job_key' => $job['id'],
				'status'  => 'queued',
			)
		);
		wp_safe_redirect( admin_url( 'admin.php?page=occ-idg-batches' ) );
		exit;
	}

	/**
	 * Queue a durable batch for explicitly selected Image Library attachments.
	 *
	 * This AJAX request validates and records the work only. Provider requests are
	 * performed later by the background worker in small, resumable steps.
	 *
	 * @since 2.0.2
	 * @return void
	 */
	public function ajax_create_selected_batch() {
		check_ajax_referer( 'occidg_bulk_edit', 'nonce' );
		if ( ! current_user_can( 'occ_idg_generate_metadata' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to generate metadata.', 'occidg' ) ) );
			return;
		}

		$mode = isset( $_POST['mode'] ) && ! is_array( $_POST['mode'] ) ? sanitize_key( wp_unslash( $_POST['mode'] ) ) : '';
		if ( ! in_array( $mode, array( 'fill_missing', 'suggestion' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Choose a supported bulk generation mode.', 'occidg' ) ) );
			return;
		}

		$raw_ids = isset( $_POST['image_ids'] ) && is_array( $_POST['image_ids'] ) ? wp_unslash( $_POST['image_ids'] ) : array();
		$ids     = $this->normalize_selected_image_ids( $raw_ids );
		$max     = max( 1, absint( get_option( 'occ_idg_max_batch_size', 1000 ) ) );
		if ( count( $ids ) > $max ) {
			wp_send_json_error(
				array(
					'message' => sprintf(
						/* translators: %s: maximum batch size. */
						__( 'This selection is above the %s-image batch limit.', 'occidg' ),
						number_format_i18n( $max )
					),
				)
			);
			return;
		}

		$ids = array_values(
			array_filter(
				$ids,
				function ( $attachment_id ) {
					return 'attachment' === get_post_type( $attachment_id )
						&& 0 === strpos( (string) get_post_mime_type( $attachment_id ), 'image/' )
						&& ! Occidg_Image_Support::is_svg_attachment( $attachment_id )
						&& current_user_can( 'edit_post', $attachment_id );
				}
			)
		);
		if ( empty( $ids ) ) {
			wp_send_json_error( array( 'message' => __( 'Select at least one eligible image.', 'occidg' ) ) );
			return;
		}

		$fields = Occidg_Metadata::normalize_fields( get_option( 'occidg_metadata_fields', array() ) );
		if ( empty( $fields ) ) {
			wp_send_json_error( array( 'message' => __( 'Enable at least one metadata field in Settings before queueing a batch.', 'occidg' ) ) );
			return;
		}

		$gate_state = Occidg_Admin_Settings::get_generation_gate_state();
		if ( empty( $gate_state['has_selected_provider_key'] ) ) {
			wp_send_json_error( array( 'message' => $gate_state['missing_key_message'] ) );
			return;
		}

		$provider           = $gate_state['provider'];
		$model              = get_option( 'gemini' === $provider ? 'occidg_gemini_model' : 'occidg_openai_model', '' );
		$estimated_requests = count( $ids );
		$estimated_cost     = round( $estimated_requests * (float) get_option( 'occ_idg_' . $provider . '_cost_per_request', 0 ), 6 );
		$cost_cap           = (float) get_option( 'occ_idg_max_estimated_batch_cost', 0 );
		if ( $cost_cap > 0 && $estimated_cost > $cost_cap ) {
			wp_send_json_error( array( 'message' => __( 'The estimated batch cost exceeds the configured safety limit.', 'occidg' ) ) );
			return;
		}

		$mode_label = 'suggestion' === $mode ? __( 'Review suggestions', 'occidg' ) : __( 'Fill missing metadata', 'occidg' );
		$batch_id   = $this->database->create_batch(
			array(
				/* translators: 1: batch mode label, 2: batch creation time. */
				'name'               => sprintf( __( '%1$s — selected images — %2$s', 'occidg' ), $mode_label, current_time( 'mysql' ) ),
				'mode'               => $mode,
				'provider'           => $provider,
				'model'              => $model,
				'requested_fields'   => $fields,
				'filters'            => array( 'source' => 'image_library_selection' ),
				'estimated_requests' => $estimated_requests,
				'estimated_cost'     => $estimated_cost,
			),
			$ids
		);
		if ( false === $batch_id ) {
			wp_send_json_error( array( 'message' => __( 'The selected-image batch could not be saved.', 'occidg' ) ) );
			return;
		}

		$job = $this->jobs_admin->create_job_from_image_ids(
			$ids,
			/* translators: %d: metadata batch ID. */
			sprintf( __( 'Metadata batch #%d', 'occidg' ), $batch_id ),
			array(
				'mode'                     => $mode,
				'batch_id'                 => $batch_id,
				'selected_fields'          => array_fill_keys( $fields, '1' ),
				'override_metadata'        => false,
				'overwrite_confirmed'      => false,
				'caption_review_confirmed' => 'fill_missing' === $mode,
				'initiated_by'             => get_current_user_id(),
			)
		);
		if ( false === $job ) {
			$this->database->update_batch(
				$batch_id,
				array(
					'status'       => 'failed',
					'completed_at' => current_time( 'mysql', true ),
				)
			);
			wp_send_json_error( array( 'message' => __( 'The background batch could not be queued. Verify provider credentials.', 'occidg' ) ) );
			return;
		}

		$this->database->update_batch(
			$batch_id,
			array(
				'job_key' => $job['id'],
				'status'  => 'queued',
			)
		);

		wp_send_json_success(
			array(
				'batch_id'    => $batch_id,
				'job_id'      => $job['id'],
				'total'       => count( $ids ),
				'batches_url' => admin_url( 'admin.php?page=occ-idg-batches' ),
				'message'     => sprintf(
					/* translators: 1: batch mode label, 2: selected image count. */
					_n( '%1$s queued for %2$s image.', '%1$s queued for %2$s images.', count( $ids ), 'occidg' ),
					$mode_label,
					number_format_i18n( count( $ids ) )
				),
			)
		);
	}

	/**
	 * Normalize an explicitly submitted attachment ID list.
	 *
	 * @param mixed $image_ids Candidate attachment IDs.
	 * @return array Unique positive IDs.
	 */
	private function normalize_selected_image_ids( $image_ids ) {
		if ( ! is_array( $image_ids ) ) {
			return array();
		}

		return array_values( array_filter( array_unique( array_map( 'absint', $image_ids ) ) ) );
	}

	/** Review pending field suggestions with edit-before-approval. */
	public function render_review() {
		$this->guard( 'occ_idg_review_suggestions' );
		wp_safe_redirect( admin_url( 'admin.php?page=occ-idg-audit&review_status=suggestion_ready' ) );
		exit;
	}

	/** Handle an individual suggestion review decision. */
	public function handle_review_suggestion() {
		$this->guard( 'occ_idg_review_suggestions' );
		$id = isset( $_POST['suggestion_id'] ) ? absint( $_POST['suggestion_id'] ) : 0;
		check_admin_referer( 'occ_idg_suggestion_' . $id );
		$suggestion = $this->database->get_suggestion( $id );
		if ( ! $suggestion || ! current_user_can( 'edit_post', (int) $suggestion['attachment_id'] ) ) {
			wp_die( esc_html__( 'This suggestion is unavailable or you cannot edit its image.', 'occidg' ) );
		}
		$action = isset( $_POST['review_action'] ) ? sanitize_key( wp_unslash( $_POST['review_action'] ) ) : '';
		if ( 'approve' === $action ) {
			$value = isset( $_POST['approved_value'] ) ? sanitize_textarea_field( wp_unslash( $_POST['approved_value'] ) ) : '';
			$this->workflow->approve_suggestion( $id, $value );
		} else {
			$status = array(
				'reject' => 'rejected',
				'defer'  => 'deferred',
				'manual' => 'needs_manual_review',
			);
			$this->workflow->set_suggestion_status( $id, isset( $status[ $action ] ) ? $status[ $action ] : 'rejected' );
		}
		$remaining = $this->database->get_suggestions(
			array(
				'status'        => 'pending',
				'attachment_id' => (int) $suggestion['attachment_id'],
				'limit'         => 1,
			)
		);
		if ( ! empty( $remaining ) ) {
			update_post_meta( (int) $suggestion['attachment_id'], '_occ_idg_review_status', 'suggestion_ready' );
		} elseif ( 'approve' === $action ) {
			update_post_meta( (int) $suggestion['attachment_id'], '_occ_idg_review_status', 'approved' );
		}
		$redirect = wp_get_referer();
		if ( ! $redirect || false === strpos( $redirect, 'page=occ-idg-audit' ) ) {
			$redirect = admin_url( 'admin.php?page=occ-idg-audit&review_status=suggestion_ready' );
		}
		wp_safe_redirect( $redirect );
		exit;
	}

	/** Render audit rows with completeness and current workflow state. */
	public function render_audit() {
		$this->guard( 'occ_idg_view_dashboard' );
		$missing = isset( $_GET['missing_field'] ) ? sanitize_key( wp_unslash( $_GET['missing_field'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$filters = array(
			'missing_field'      => $missing,
			'include_decorative' => ! empty( $_GET['include_decorative'] ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		);
		foreach ( array( 'uploader', 'parent', 'batch_id', 'min_width', 'max_width', 'min_height', 'max_height', 'min_file_size', 'max_file_size' ) as $number_filter ) {
			$filters[ $number_filter ] = isset( $_GET[ $number_filter ] ) ? absint( $_GET[ $number_filter ] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}
		foreach ( array( 'mime_type', 'extension', 'date_from', 'date_to', 'review_status', 'provider', 'decorative_status', 'processing_status', 'confidence' ) as $text_filter ) {
			$filters[ $text_filter ] = isset( $_GET[ $text_filter ] ) ? sanitize_text_field( wp_unslash( $_GET[ $text_filter ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}
		if ( isset( $_GET['processed'] ) && in_array( $_GET['processed'], array( '0', '1' ), true ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$filters['processed'] = (int) $_GET['processed']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}
		$filter_active = isset( $_GET['processed'] ) || (bool) array_filter( // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$filters,
			function ( $value ) {
				return '' !== $value && 0 !== $value && false !== $value;
			}
		);
		$ids           = $filter_active ? $this->preflight->query_ids( $filters, 10000 ) : array();
		echo '<div class="wrap occidg-admin-shell occidg-workflow-page toplevel_page_occidg">';
		$this->render_workflow_header( __( 'Image Library', 'occidg' ), __( 'Find, edit, preview, generate, and review image metadata without leaving this page.', 'occidg' ), 'library' );
		echo '<div class="occidg-library-quick-filters" aria-label="' . esc_attr__( 'Quick filters', 'occidg' ) . '"><a class="button" href="' . esc_url( admin_url( 'admin.php?page=occ-idg-audit' ) ) . '">' . esc_html__( 'All images', 'occidg' ) . '</a><a class="button" href="' . esc_url( admin_url( 'admin.php?page=occ-idg-audit&missing_field=alt_text' ) ) . '">' . esc_html__( 'Missing alt text', 'occidg' ) . '</a><a class="button" href="' . esc_url( admin_url( 'admin.php?page=occ-idg-audit&missing_field=caption' ) ) . '">' . esc_html__( 'Missing captions', 'occidg' ) . '</a><a class="button" href="' . esc_url( admin_url( 'admin.php?page=occ-idg-audit&review_status=suggestion_ready' ) ) . '">' . esc_html__( 'Awaiting review', 'occidg' ) . '</a></div>';
		echo '<form class="occidg-filter-card" method="get"><input type="hidden" name="page" value="occ-idg-audit"><div class="occidg-filter-basics"><label for="occidg-missing-field"><span>' . esc_html__( 'Show me', 'occidg' ) . '</span><select id="occidg-missing-field" name="missing_field"><option value="">' . esc_html__( 'All images', 'occidg' ) . '</option>';
		foreach ( array(
			'alt_text'    => 'Alternative text',
			'title'       => 'Title',
			'caption'     => 'Caption',
			'description' => 'Description',
		) as $key => $label ) {
			/* translators: %s: metadata field label. */
			printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $key ), selected( $missing, $key, false ), esc_html( sprintf( __( 'Missing %s', 'occidg' ), $label ) ) );
		}
		echo '</select></label><label for="occidg-review-status"><span>' . esc_html__( 'Review status', 'occidg' ) . '</span><select id="occidg-review-status" name="review_status"><option value="">' . esc_html__( 'Any status', 'occidg' ) . '</option><option value="suggestion_ready" ' . selected( $filters['review_status'], 'suggestion_ready', false ) . '>' . esc_html__( 'Awaiting review', 'occidg' ) . '</option><option value="approved" ' . selected( $filters['review_status'], 'approved', false ) . '>' . esc_html__( 'Approved', 'occidg' ) . '</option><option value="failed" ' . selected( $filters['review_status'], 'failed', false ) . '>' . esc_html__( 'Failed', 'occidg' ) . '</option><option value="needs_manual_review" ' . selected( $filters['review_status'], 'needs_manual_review', false ) . '>' . esc_html__( 'Needs manual review', 'occidg' ) . '</option></select></label><label class="occidg-checkbox-line"><input type="checkbox" name="include_decorative" value="1" ' . checked( ! empty( $filters['include_decorative'] ), true, false ) . '> ' . esc_html__( 'Include decorative images', 'occidg' ) . '</label><button class="button button-primary" type="submit">' . esc_html__( 'Apply filters', 'occidg' ) . '</button></div>';
		echo '<details class="occidg-disclosure occidg-filter-advanced"><summary><span><strong>' . esc_html__( 'Advanced filters', 'occidg' ) . '</strong><small>' . esc_html__( 'File type, owner, processing history, confidence, or batch', 'occidg' ) . '</small></span></summary><div class="occidg-disclosure__content occidg-compact-grid">';
		echo '<label><span>' . esc_html__( 'MIME type', 'occidg' ) . '</span><input type="text" name="mime_type" placeholder="image/jpeg" value="' . esc_attr( $filters['mime_type'] ) . '"></label><label><span>' . esc_html__( 'File extension', 'occidg' ) . '</span><input type="text" name="extension" placeholder="jpg" value="' . esc_attr( $filters['extension'] ) . '"></label>';
		echo '<label><span>' . esc_html__( 'Uploader ID', 'occidg' ) . '</span><input type="number" name="uploader" value="' . esc_attr( $filters['uploader'] ? $filters['uploader'] : '' ) . '"></label><label><span>' . esc_html__( 'Parent post ID', 'occidg' ) . '</span><input type="number" name="parent" value="' . esc_attr( $filters['parent'] ? $filters['parent'] : '' ) . '"></label>';
		echo '<label><span>' . esc_html__( 'Processing history', 'occidg' ) . '</span><select name="processed"><option value="">' . esc_html__( 'Any history', 'occidg' ) . '</option><option value="0" ' . selected( isset( $filters['processed'] ) ? (string) $filters['processed'] : '', '0', false ) . '>' . esc_html__( 'Never processed', 'occidg' ) . '</option><option value="1" ' . selected( isset( $filters['processed'] ) ? (string) $filters['processed'] : '', '1', false ) . '>' . esc_html__( 'Previously processed', 'occidg' ) . '</option></select></label>';
		echo '<label><span>' . esc_html__( 'Decorative status', 'occidg' ) . '</span><select name="decorative_status"><option value="">' . esc_html__( 'Any status', 'occidg' ) . '</option><option value="decorative" ' . selected( $filters['decorative_status'], 'decorative', false ) . '>' . esc_html__( 'Decorative', 'occidg' ) . '</option><option value="not_decorative" ' . selected( $filters['decorative_status'], 'not_decorative', false ) . '>' . esc_html__( 'Not decorative', 'occidg' ) . '</option></select></label>';
		echo '<label><span>' . esc_html__( 'Confidence', 'occidg' ) . '</span><select name="confidence"><option value="">' . esc_html__( 'Any confidence', 'occidg' ) . '</option><option value="high" ' . selected( $filters['confidence'], 'high', false ) . '>' . esc_html__( 'High', 'occidg' ) . '</option><option value="medium" ' . selected( $filters['confidence'], 'medium', false ) . '>' . esc_html__( 'Medium', 'occidg' ) . '</option><option value="low" ' . selected( $filters['confidence'], 'low', false ) . '>' . esc_html__( 'Low', 'occidg' ) . '</option></select></label>';
		echo '<label><span>' . esc_html__( 'Batch ID', 'occidg' ) . '</span><input type="number" name="batch_id" value="' . esc_attr( $filters['batch_id'] ? $filters['batch_id'] : '' ) . '"></label></div></details></form>';
		if ( $filter_active ) {
			/* translators: %s: number of matching Media Library images. */
			$heading = sprintf( _n( '%s matching image', '%s matching images', count( $ids ), 'occidg' ), number_format_i18n( count( $ids ) ) );
		} else {
			$heading = __( 'All image metadata', 'occidg' );
		}
		echo '<section class="occidg-table-card occidg-library-table-card"><div class="occidg-section-heading"><div><p class="occidg-eyebrow">' . esc_html__( 'Edit and generate', 'occidg' ) . '</p><h2>' . esc_html( $heading ) . '</h2><p>' . esc_html__( 'Changes you type are saved automatically. Preview lets you choose field by field; Generate applies your saved rules.', 'occidg' ) . '</p></div></div><div class="occidg-table-scroll">';
		$this->bulk_edit->render_library_table( $filters, $filter_active );
		echo '</div></section></div>';
	}

	/** Render durable batch progress and controls. */
	public function render_batches() {
		$this->guard( 'occ_idg_manage_batches' );
		$rows = $this->database->get_batches();
		echo '<div class="wrap occidg-admin-shell occidg-workflow-page">';
		$this->render_workflow_header( __( 'Batches', 'occidg' ), __( 'Follow active jobs, retry failures, or restore a completed batch when needed.', 'occidg' ), 'batches' );
		if ( empty( $rows ) ) {
			echo '<div class="occidg-empty-state"><span class="dashicons dashicons-controls-repeat" aria-hidden="true"></span><h2>' . esc_html__( 'No batches yet', 'occidg' ) . '</h2><p>' . esc_html__( 'Run a safe preview from the Dashboard when you are ready.', 'occidg' ) . '</p><a class="button button-primary" href="' . esc_url( admin_url( 'admin.php?page=occ-idg-dashboard' ) ) . '">' . esc_html__( 'Plan a Preview', 'occidg' ) . '</a></div></div>';
			return;
		}
		echo '<section class="occidg-table-card"><div class="occidg-table-scroll"><table class="wp-list-table widefat striped occidg-data-table"><thead><tr><th>' . esc_html__( 'Batch', 'occidg' ) . '</th><th>' . esc_html__( 'What it does', 'occidg' ) . '</th><th>' . esc_html__( 'AI provider', 'occidg' ) . '</th><th>' . esc_html__( 'Progress', 'occidg' ) . '</th><th>' . esc_html__( 'Estimated usage', 'occidg' ) . '</th><th>' . esc_html__( 'Status and actions', 'occidg' ) . '</th></tr></thead><tbody>';
		foreach ( $rows as $row ) {
			$job       = $row['job_key'] ? $this->jobs_admin->get_job_payload( $row['job_key'] ) : false;
			$processed = $job ? $job['processed'] : (int) $row['completed_items'];
			$total     = $job ? $job['total'] : (int) $row['total_items'];
			$percent   = $job ? $job['percent_complete'] : ( $total > 0 ? (int) round( ( $processed / $total ) * 100 ) : 0 );
			$status    = $job ? $job['status_label'] : $row['status'];
			printf( '<tr><td><strong>#%1$d %2$s</strong><br><span class="occidg-muted">%3$s</span></td><td><strong>%4$s</strong><br>%5$s</td><td>%6$s<br><span class="occidg-muted">%7$s</span></td><td><progress value="%8$d" max="100">%8$d%%</progress><br><strong>%9$d of %10$d</strong>', (int) $row['id'], esc_html( $row['name'] ), esc_html( $row['created_at'] ), esc_html( ucfirst( str_replace( '_', ' ', $row['mode'] ) ) ), esc_html( implode( ', ', (array) json_decode( $row['requested_fields'], true ) ) ), esc_html( ucfirst( $row['provider'] ) ), esc_html( $row['model'] ), esc_html( $percent ), esc_html( $processed ), esc_html( $total ) );
			if ( $job && ( $job['failed'] || $job['skipped'] ) ) {
				printf( '<br><span class="occidg-muted">%1$d failed · %2$d skipped</span>', (int) $job['failed'], (int) $job['skipped'] );
			}
			printf( '</td><td>%1$d ' . esc_html__( 'requests', 'occidg' ) . '<br><span class="occidg-muted">$%2$s ' . esc_html__( 'estimated', 'occidg' ) . '</span></td><td><span class="occidg-status-pill">%3$s</span><div class="occidg-row-actions">', (int) $row['estimated_requests'], esc_html( $row['estimated_cost'] ), esc_html( $status ) );
			if ( $job ) {
				$this->render_job_actions( $job ); }
			if ( current_user_can( 'occ_idg_restore_metadata' ) ) {
				printf( '<details><summary>%1$s</summary><p>%2$s</p><a class="button" href="%3$s">%4$s</a></details>', esc_html__( 'Restore options', 'occidg' ), esc_html__( 'Restore metadata changed by this batch when the current values still match.', 'occidg' ), esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=occ_idg_restore_batch&batch_id=' . (int) $row['id'] ), 'occ_idg_restore_batch_' . (int) $row['id'] ) ), esc_html__( 'Restore Batch', 'occidg' ) ); }
			echo '</div></td></tr>';
		}
		echo '</tbody></table></div></section></div>';
	}

	/**
	 * Render actions allowed for a queue job.
	 *
	 * @param array $job Normalized job payload.
	 */
	private function render_job_actions( $job ) {
		$actions = array();
		if ( $job['can_pause'] ) {
			$actions['pause'] = __( 'Pause', 'occidg' ); }
		if ( $job['can_resume'] ) {
			$actions['resume'] = __( 'Resume', 'occidg' ); }
		if ( $job['can_cancel'] ) {
			$actions['cancel'] = __( 'Cancel', 'occidg' ); }
		if ( $job['can_retry'] ) {
			$actions['retry'] = __( 'Retry failures', 'occidg' ); }
		foreach ( $actions as $action => $label ) {
			$url = wp_nonce_url( admin_url( 'admin-post.php?action=occ_idg_batch_action&batch_action=' . $action . '&job_id=' . rawurlencode( $job['id'] ) ), 'occ_idg_batch_action_' . $job['id'] );
			printf( '<a class="button" href="%1$s">%2$s</a>', esc_url( $url ), esc_html( $label ) );
		}
	}

	/** Handle a pause, resume, cancel, or retry request. */
	public function handle_batch_action() {
		$this->guard( 'occ_idg_manage_batches' );
		$job_id = isset( $_GET['job_id'] ) ? sanitize_text_field( wp_unslash( $_GET['job_id'] ) ) : '';
		check_admin_referer( 'occ_idg_batch_action_' . $job_id );
		$action = isset( $_GET['batch_action'] ) ? sanitize_key( wp_unslash( $_GET['batch_action'] ) ) : '';
		if ( 'pause' === $action ) {
			$this->jobs_admin->pause_job_payload( $job_id ); }
		if ( 'resume' === $action ) {
			$this->jobs_admin->resume_job_payload( $job_id ); }
		if ( 'cancel' === $action ) {
			$this->jobs_admin->cancel_job_payload( $job_id ); }
		if ( 'retry' === $action ) {
			$this->jobs_admin->retry_job_payload( $job_id ); }
		wp_safe_redirect( admin_url( 'admin.php?page=occ-idg-batches' ) );
		exit;
	}

	/** Render the metadata change ledger. */
	public function render_history() {
		$this->guard( 'occ_idg_restore_metadata' );
		$attachment_id = isset( $_GET['attachment_id'] ) ? absint( $_GET['attachment_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$rows          = $this->database->get_history_rows(
			array(
				'attachment_id' => $attachment_id,
				'limit'         => 500,
			)
		);
		echo '<div class="wrap occidg-admin-shell occidg-workflow-page">';
		$this->render_workflow_header( __( 'Change History', 'occidg' ), __( 'See what changed and restore an earlier value when a result is not right.', 'occidg' ), 'history' );
		if ( $attachment_id ) {
			/* translators: %d: WordPress attachment ID. */
			echo '<div class="occidg-filter-summary"><span>' . esc_html( sprintf( __( 'Showing history for image #%d', 'occidg' ), $attachment_id ) ) . '</span><a href="' . esc_url( admin_url( 'admin.php?page=occ-idg-history' ) ) . '">' . esc_html__( 'Show all history', 'occidg' ) . '</a></div>';
		}
		if ( empty( $rows ) ) {
			echo '<div class="occidg-empty-state"><span class="dashicons dashicons-backup" aria-hidden="true"></span><h2>' . esc_html__( 'No changes recorded yet', 'occidg' ) . '</h2><p>' . esc_html__( 'Changes made through the workflow will appear here.', 'occidg' ) . '</p></div></div>';
			return;
		}
		echo '<section class="occidg-table-card"><div class="occidg-table-scroll"><table class="wp-list-table widefat striped occidg-data-table"><thead><tr><th>' . esc_html__( 'When', 'occidg' ) . '</th><th>' . esc_html__( 'Image and field', 'occidg' ) . '</th><th>' . esc_html__( 'Previous value', 'occidg' ) . '</th><th>' . esc_html__( 'New value', 'occidg' ) . '</th><th>' . esc_html__( 'How it changed', 'occidg' ) . '</th><th>' . esc_html__( 'People', 'occidg' ) . '</th><th>' . esc_html__( 'Action', 'occidg' ) . '</th></tr></thead><tbody>';
		foreach ( $rows as $row ) {
			$url          = wp_nonce_url( admin_url( 'admin-post.php?action=occ_idg_restore_history&history_id=' . (int) $row['id'] ), 'occ_idg_restore_history_' . (int) $row['id'] );
			$initiated_by = $row['initiated_by'] ? get_the_author_meta( 'display_name', (int) $row['initiated_by'] ) : __( 'System', 'occidg' );
			$approved_by  = $row['approved_by'] ? get_the_author_meta( 'display_name', (int) $row['approved_by'] ) : '';
			/* translators: %s: display name of the user who approved the metadata change. */
			$approved_label = $approved_by ? sprintf( __( 'Approved by %s', 'occidg' ), $approved_by ) : '';
			$approved_html  = $approved_label ? '<br><span class="occidg-muted">' . esc_html( $approved_label ) . '</span>' : '';
			printf( '<tr><td>%1$s</td><td><strong>%2$s</strong><br><span class="occidg-muted">#%3$d · %4$s</span></td><td><div class="occidg-change-value">%5$s</div></td><td><div class="occidg-change-value">%6$s</div></td><td><strong>%7$s</strong><br>%8$s / %9$s<br><span class="occidg-muted">%10$s · %11$s confidence</span></td><td>%12$s%13$s</td><td><a class="button" href="%14$s">%15$s</a></td></tr>', esc_html( $row['created_at'] ), esc_html( get_the_title( $row['attachment_id'] ) ), (int) $row['attachment_id'], esc_html( ucfirst( str_replace( '_', ' ', $row['field_name'] ) ) ), esc_html( $row['old_value'] ), esc_html( $row['new_value'] ), esc_html( ucfirst( str_replace( '_', ' ', $row['action_type'] ) ) ), esc_html( ucfirst( $row['provider'] ) ), esc_html( $row['model'] ), esc_html( ucfirst( str_replace( '_', ' ', $row['processing_mode'] ) ) ), esc_html( $row['confidence'] ), esc_html( $initiated_by ), wp_kses_post( $approved_html ), esc_url( $url ), esc_html__( 'Restore', 'occidg' ) );
		}
		echo '</tbody></table></div></section></div>';
	}

	/** Restore a selected history event with conflict confirmation. */
	public function handle_restore_history() {
		$this->guard( 'occ_idg_restore_metadata' );
		$id = isset( $_GET['history_id'] ) ? absint( $_GET['history_id'] ) : 0;
		check_admin_referer( 'occ_idg_restore_history_' . $id );
		$result = $this->metadata->restore_history( $id, ! empty( $_GET['force'] ) );
		if ( ! empty( $result['conflict'] ) ) {
			$url = wp_nonce_url( admin_url( 'admin-post.php?action=occ_idg_restore_history&force=1&history_id=' . $id ), 'occ_idg_restore_history_' . $id );
			wp_die( wp_kses_post( '<p>' . esc_html( $result['error'] ) . '</p><p><a class="button button-primary" href="' . esc_url( $url ) . '">' . esc_html__( 'Confirm restore over newer edit', 'occidg' ) . '</a></p>' ) );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=occ-idg-history' ) );
		exit;
	}

	/** Restore compatible history events from a selected batch. */
	public function handle_restore_batch() {
		$this->guard( 'occ_idg_restore_metadata' );
		$id = isset( $_GET['batch_id'] ) ? absint( $_GET['batch_id'] ) : 0;
		check_admin_referer( 'occ_idg_restore_batch_' . $id );
		$this->metadata->restore_batch( $id, false );
		wp_safe_redirect( admin_url( 'admin.php?page=occ-idg-batches' ) );
		exit;
	}

	/** Handle decorative and reviewed status decisions. */
	public function handle_attachment_status() {
		$this->guard( 'occ_idg_review_suggestions' );
		$attachment_id = isset( $_GET['attachment_id'] ) ? absint( $_GET['attachment_id'] ) : 0;
		check_admin_referer( 'occ_idg_attachment_status_' . $attachment_id );
		$status = isset( $_GET['status_action'] ) ? sanitize_key( wp_unslash( $_GET['status_action'] ) ) : '';
		if ( 'decorative' === $status ) {
			$this->metadata->set_decorative( $attachment_id, true, isset( $_GET['reason'] ) ? sanitize_textarea_field( wp_unslash( $_GET['reason'] ) ) : '' );
		} elseif ( 'not_decorative' === $status ) {
			$this->metadata->set_decorative( $attachment_id, false );
		} elseif ( 'reviewed' === $status ) {
			update_post_meta( $attachment_id, '_occ_idg_last_reviewed', current_time( 'mysql', true ) );
			update_post_meta( $attachment_id, '_occ_idg_review_status', 'approved' );
		}
		wp_safe_redirect( get_edit_post_link( $attachment_id, 'raw' ) );
		exit;
	}

	/**
	 * Add a compact status link to the Media Library.
	 *
	 * @param array $columns Existing media columns.
	 * @return array Media columns.
	 */
	public function media_columns( $columns ) {
		$columns['occ_idg_status'] = __( 'Metadata status', 'occidg' );
		return $columns; }
	/**
	 * Render the media-library workflow status column.
	 *
	 * @param string $column        Column name.
	 * @param int    $attachment_id Attachment ID.
	 */
	public function media_column( $column, $attachment_id ) {
		if ( 'occ_idg_status' !== $column || ! wp_attachment_is_image( $attachment_id ) ) {
			return; }
		$status = get_post_meta( $attachment_id, '_occ_idg_review_status', true );
		if ( get_post_meta( $attachment_id, '_occ_idg_decorative', true ) ) {
			$status = 'decorative'; }
		printf( '<a href="%1$s">%2$s</a>', esc_url( admin_url( 'admin.php?page=occ-idg-audit&attachment_id=' . $attachment_id ) ), esc_html( $status ? str_replace( '_', ' ', $status ) : __( 'Not reviewed', 'occidg' ) ) );
	}

	/**
	 * Enrich the individual attachment screen without exposing provider secrets.
	 *
	 * @param array   $fields Existing attachment fields.
	 * @param WP_Post $post   Attachment post.
	 * @return array Attachment fields.
	 */
	public function attachment_panel( $fields, $post ) {
		if ( 0 !== strpos( (string) $post->post_mime_type, 'image/' ) ) {
			return $fields; }
		$values         = $this->metadata->get_all( $post->ID );
		$applied_fields = array();
		foreach ( $values as $field => $value ) {
			if ( ! Occidg_Metadata::is_empty( $value ) ) {
				$applied_fields[] = $field; }
		}
		$pending_fields = array();
		$suggestions    = $this->database->get_suggestions(
			array(
				'attachment_id' => $post->ID,
				'status'        => 'pending',
				'limit'         => 20,
			)
		);
		foreach ( $suggestions as $suggestion ) {
			$field = isset( $suggestion['field_name'] ) ? sanitize_key( $suggestion['field_name'] ) : '';
			$value = isset( $suggestion['suggested_value'] ) ? $suggestion['suggested_value'] : '';
			if ( isset( Occidg_Metadata::FIELDS[ $field ] ) && ! Occidg_Metadata::is_empty( $value ) ) {
				$pending_fields[] = $field;
			}
		}
		$pending_fields = array_values( array_unique( $pending_fields ) );
		$generated      = count( array_unique( array_merge( $applied_fields, $pending_fields ) ) );
		$applied        = count( $applied_fields );
		/* translators: 1: number of generated fields, 2: number of applied fields. */
		$html = '<p>' . esc_html( sprintf( __( '%1$d of 4 metadata fields generated; %2$d applied.', 'occidg' ), $generated, $applied ) ) . '</p>';
		if ( ! empty( $pending_fields ) ) {
			$field_labels  = array(
				'title'       => __( 'Title', 'occidg' ),
				'alt_text'    => __( 'Alt Text', 'occidg' ),
				'caption'     => __( 'Caption', 'occidg' ),
				'description' => __( 'Description', 'occidg' ),
			);
			$review_labels = array_map(
				function ( $field ) use ( $field_labels ) {
					return isset( $field_labels[ $field ] ) ? $field_labels[ $field ] : $field;
				},
				$pending_fields
			);
			$html         .= '<p><strong>' . esc_html__( 'Awaiting review:', 'occidg' ) . '</strong> ' . esc_html( implode( ', ', $review_labels ) ) . '</p>';
		}
		$review_status              = get_post_meta( $post->ID, '_occ_idg_review_status', true );
		$review_status              = $review_status ? $review_status : 'not_reviewed';
		$last_processed             = get_post_meta( $post->ID, '_occ_idg_last_processed', true );
		$last_processed             = $last_processed ? $last_processed : '—';
		$html                      .= '<p><strong>' . esc_html__( 'Review:', 'occidg' ) . '</strong> ' . esc_html( $review_status ) . '<br><strong>' . esc_html__( 'Decorative:', 'occidg' ) . '</strong> ' . ( get_post_meta( $post->ID, '_occ_idg_decorative', true ) ? esc_html__( 'Yes', 'occidg' ) : esc_html__( 'No', 'occidg' ) ) . '<br><strong>' . esc_html__( 'Last generated:', 'occidg' ) . '</strong> ' . esc_html( $last_processed ) . '<br><strong>' . esc_html__( 'Provider / model:', 'occidg' ) . '</strong> ' . esc_html( get_post_meta( $post->ID, '_occ_idg_last_provider', true ) . ' / ' . get_post_meta( $post->ID, '_occ_idg_last_model', true ) ) . '</p>';
		$status_action              = get_post_meta( $post->ID, '_occ_idg_decorative', true ) ? 'not_decorative' : 'decorative';
		$status_label               = 'decorative' === $status_action ? __( 'Mark decorative', 'occidg' ) : __( 'Remove decorative status', 'occidg' );
		$status_url                 = wp_nonce_url( admin_url( 'admin-post.php?action=occ_idg_attachment_status&status_action=' . $status_action . '&attachment_id=' . $post->ID ), 'occ_idg_attachment_status_' . $post->ID );
		$reviewed_url               = wp_nonce_url( admin_url( 'admin-post.php?action=occ_idg_attachment_status&status_action=reviewed&attachment_id=' . $post->ID ), 'occ_idg_attachment_status_' . $post->ID );
		$html                      .= '<p><a class="button" href="' . esc_url( admin_url( 'admin.php?page=occ-idg-audit&review_status=suggestion_ready' ) ) . '">' . esc_html__( 'Review suggestions', 'occidg' ) . '</a> <a class="button" href="' . esc_url( admin_url( 'admin.php?page=occ-idg-history&attachment_id=' . $post->ID ) ) . '">' . esc_html__( 'View history / restore', 'occidg' ) . '</a> <a class="button" href="' . esc_url( $status_url ) . '">' . esc_html( $status_label ) . '</a> <a class="button" href="' . esc_url( $reviewed_url ) . '">' . esc_html__( 'Mark reviewed', 'occidg' ) . '</a></p>';
		$fields['occ_idg_workflow'] = array(
			'label' => __( 'Image Detail Generator', 'occidg' ),
			'input' => 'html',
			'html'  => $html,
		);
		return $fields;
	}

	/** Stream an authorized CSV export. */
	public function export_csv() {
		$this->guard( 'occ_idg_export_reports' );
		check_admin_referer( 'occ_idg_export' );
		$type = isset( $_GET['type'] ) ? sanitize_key( wp_unslash( $_GET['type'] ) ) : 'history';
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=occ-idg-' . $type . '-' . gmdate( 'Y-m-d' ) . '.csv' );
		$output = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( in_array( $type, array( 'suggestions', 'pending_suggestions', 'approved_suggestions', 'rejected_suggestions' ), true ) ) {
			$status_map = array(
				'pending_suggestions'  => 'pending',
				'approved_suggestions' => 'approved',
				'rejected_suggestions' => 'rejected',
			);
			$rows       = $this->database->get_suggestions(
				array(
					'status' => isset( $status_map[ $type ] ) ? $status_map[ $type ] : '',
					'limit'  => 500,
				)
			);
		} elseif ( 'missing' === $type ) {
			$field = isset( $_GET['field'] ) ? sanitize_key( wp_unslash( $_GET['field'] ) ) : 'alt_text';
			$ids   = $this->preflight->query_ids( array( 'missing_field' => $field ), 10000 );
			$rows  = array_map(
				function ( $id ) use ( $field ) {
					return array(
						'attachment_id' => $id,
						'missing_field' => $field,
						'filename'      => wp_basename( get_attached_file( $id ) ),
						'edit_url'      => get_edit_post_link( $id, 'raw' ),
					);
				},
				$ids
			);
		} elseif ( 'failures' === $type ) {
			$rows = $this->preflight->query_ids( array( 'review_status' => 'failed' ), 10000 );
			$rows = array_map(
				function ( $id ) {
					return array(
						'attachment_id' => $id,
						'edit_url'      => get_edit_post_link( $id, 'raw' ),
					);
				},
				$rows
			);
		} elseif ( 'batches' === $type || 'usage' === $type ) {
			$rows = $this->database->get_batches( 500 );
		} elseif ( 'dry_run' === $type ) {
			$report = get_transient( 'occ_idg_dry_run_' . get_current_user_id() );
			$rows   = array();
			foreach ( (array) $report as $key => $value ) {
				if ( is_array( $value ) ) {
					foreach ( $value as $subkey => $subvalue ) {
						$rows[] = array(
							'metric' => $key . '.' . $subkey,
							'value'  => $subvalue,
						);
					}
				} else {
					$rows[] = array(
						'metric' => $key,
						'value'  => $value,
					);
				}
			}
		} else {
			$rows = $this->database->get_history_rows( array( 'limit' => 1000 ) );
		}
		foreach ( $rows as &$row ) {
			if ( isset( $row['attachment_id'] ) ) {
				$row['attachment_edit_url'] = get_edit_post_link( $row['attachment_id'], 'raw' );
			}
		}
		unset( $row );
		if ( ! empty( $rows ) ) {
			fputcsv( $output, array_keys( $rows[0] ) );
			foreach ( $rows as $row ) {
				fputcsv( $output, $row );
			}
		} // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fputcsv
		fclose( $output ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}

	/**
	 * Render a read-only dry-run report.
	 *
	 * @param array $report Dry-run report.
	 */
	private function render_dry_run_report( $report ) {
		echo '<section class="occidg-section occidg-preview-report"><div class="occidg-section-heading"><div><p class="occidg-eyebrow">' . esc_html__( 'Preview complete', 'occidg' ) . '</p><h2>' . esc_html__( 'No metadata was changed', 'occidg' ) . '</h2><p>' . esc_html__( 'Use this estimate to decide whether you want to create suggestions or fill missing fields.', 'occidg' ) . '</p></div><span class="occidg-status-pill is-success">' . esc_html__( 'Safe preview', 'occidg' ) . '</span></div><div class="occidg-preview-grid">';
		foreach ( $report as $key => $value ) {
			if ( ! is_array( $value ) ) {
				printf( '<div><span>%1$s</span><strong>%2$s</strong></div>', esc_html( ucwords( str_replace( '_', ' ', $key ) ) ), esc_html( $value ) ); }
		}
		$export_url = wp_nonce_url( admin_url( 'admin-post.php?action=occ_idg_export&type=dry_run' ), 'occ_idg_export' );
		echo '</div><p><a class="button" href="' . esc_url( $export_url ) . '">' . esc_html__( 'Download Preview as CSV', 'occidg' ) . '</a></p></section>';
	}

	/**
	 * Require a custom workflow capability.
	 *
	 * @param string $capability Required capability.
	 */
	private function guard( $capability ) {
		if ( ! current_user_can( $capability ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'occidg' ) ); }
	}
}
