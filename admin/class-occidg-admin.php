<?php
/**
 * Admin-specific functionality for the OneClickContent Image Details plugin.
 *
 * Defines admin-specific hooks and functions to enqueue styles, scripts, and add
 * custom functionality to the Media Library.
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
 * Class Occidg_Admin
 *
 * Handles admin-specific functionality for the OneClickContent Image Details plugin,
 * including enqueuing scripts/styles and adding Media Library enhancements.
 *
 * @since 1.0.0
 */
class Occidg_Admin {

	/**
	 * The name of the plugin.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private $plugin_name;

	/**
	 * The version of the plugin.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private $version;

	/**
	 * Constructor for the admin class.
	 *
	 * Initializes the plugin name and version.
	 *
	 * @since 1.0.0
	 * @param string $plugin_name The name of the plugin.
	 * @param string $version     The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;
	}

	/**
	 * Register the top-level admin menu with tabs.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_admin_menu() {
		add_menu_page(
			__( 'OneClickContent Image Metadata', 'occidg' ), // Page title (detailed for context).
			__( 'Image Metadata', 'occidg' ),         // Menu title (shortened to avoid wrapping).
			'edit_posts',                                             // Capability (minimum for bulk edit; settings will check manage_options).
			'occidg',                                 // Menu slug.
			array( $this, 'render_admin_page' ),                      // Callback.
			'dashicons-images-alt2',                                  // Icon.
			25                                                        // Position.
		);
	}

	/**
	 * Render the admin page with tabs.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_admin_page() {
		// Verify nonce for settings form submission.
		$nonce = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
		if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === $_SERVER['REQUEST_METHOD'] && ! wp_verify_nonce( $nonce, 'occidg_settings-options' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'occidg' ) );
		}

		$tab                   = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'settings';
		$plugin_admin_settings = new Occidg_Admin_Settings(); // Temporary; ideally injected.
		$plugin_bulk_edit      = new Occidg_Bulk_Edit(); // Temporary; ideally injected.
		$license_status        = get_option( 'occidg_license_status', 'unknown' );
		$header_image_url      = plugin_dir_url( __FILE__ ) . 'assets/header-image.webp';
		$first_time_key        = 'occidg_first_time';
		$is_first_time         = get_option( $first_time_key, true );
		$tab_nonce             = wp_create_nonce( 'oneclickcontent_tab_switch' );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'OneClickContent Images', 'occidg' ); ?></h1>
			<h2 class="nav-tab-wrapper">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=occidg&tab=settings&_wpnonce=' . $tab_nonce ) ); ?>" class="nav-tab <?php echo 'settings' === $tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Settings', 'occidg' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=occidg&tab=bulk-edit&_wpnonce=' . $tab_nonce ) ); ?>" class="nav-tab <?php echo 'bulk-edit' === $tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Bulk Edit', 'occidg' ); ?>
				</a>
			</h2>

			<?php if ( 'settings' === $tab ) : ?>
				<!-- All settings output is encapsulated within #occidg_images -->
				<div id="occidg_images" class="wrap">
					<?php if ( 'active' === $license_status ) : ?>
						<!-- License Active: Display usage info -->
						<div class="usage-info-section">
							<h2><?php esc_html_e( 'Your Usage', 'occidg' ); ?></h2>
							<div id="usage_status" class="usage-summary">
								<strong id="usage_count"><?php esc_html_e( 'Loading usage data...', 'occidg' ); ?></strong>
								<div class="progress">
									<div id="usage_progress" class="progress-bar bg-success"
										role="progressbar"
										aria-valuenow="0"
										aria-valuemin="0"
										aria-valuemax="100"
										style="width: 0%;">
										0%
									</div>
								</div>
							</div>
						</div>
					<?php else : ?>
						<!-- License Inactive: Display license warning -->
						<div class="bulk-edit-license-warning compact">
							<div class="cta-left">
								<img src="<?php echo esc_url( plugin_dir_url( __FILE__ ) . 'assets/icon.png' ); ?>" alt="One Click Content Icon" style="float: left; margin-right: 10px; width: 50px; height: auto;">
								<h2><?php esc_html_e( 'Never Forget an Alt Tag Again!', 'occidg' ); ?></h2>
								<p><?php esc_html_e( 'Upgrade now to automatically generate metadata for your images. Save time and boost your site’s SEO, accessibility, and image searchability.', 'occidg' ); ?></p>
								<ul class="benefits-list">
									<li><?php esc_html_e( 'Save time with automated metadata generation', 'occidg' ); ?></li>
									<li><?php esc_html_e( 'Ensure every image has a descriptive alt tag', 'occidg' ); ?></li>
									<li><?php esc_html_e( 'Improve SEO, ADA compliance, and user experience', 'occidg' ); ?></li>
								</ul>
							</div>
							<div class="cta-right">
								<a href="https://oneclickcontent.com/image-detail-generator/" target="_blank" class="btn-license">
									<?php esc_html_e( 'Activate License Now', 'occidg' ); ?>
								</a>
							</div>
						</div>
					<?php endif; ?>

					<!-- Bulk generation options (shown regardless of license status) -->
					<div class="bulk-edit-header">
						<button id="generate-all-metadata-settings" class="button button-primary button-hero">
							<?php esc_html_e( 'Generate All Metadata', 'occidg' ); ?>
						</button>
						<button id="stop-bulk-generation-settings" class="button button-secondary" style="display:none;">
							<?php esc_html_e( 'Stop Generation', 'occidg' ); ?>
						</button>
						<p class="description">
							<?php esc_html_e( 'Click to generate metadata for all your images. Free trial users get 10 generations!', 'occidg' ); ?>
						</p>
					</div>
					<div id="bulk-generate-status-settings" class="bulk-generate-status" style="display: none;">
						<h3><?php esc_html_e( 'Bulk Generation Progress', 'occidg' ); ?></h3>
						<div id="bulk-generate-progress-container-settings" class="bulk-generate-progress-container">
							<div id="bulk-generate-progress-bar-settings" class="bulk-generate-progress-bar"></div>
						</div>
						<div id="bulk-generate-message-settings" class="bulk-generate-message"></div>
					</div>

					<!-- Settings Form: Always inside #occidg_images -->
					<form method="post" action="options.php" id="occidg_settings_form">
						<?php
						settings_fields( 'occidg_settings' );
						do_settings_sections( 'occidg_settings' );
						submit_button();
						?>
					</form>

					<!-- Bulk Generate Modal (within the same container) -->
					<div id="bulk-generate-modal" class="modal" style="display:none;">
						<div class="modal-content">
							<h2><?php esc_html_e( 'Confirm Bulk Metadata Generation', 'occidg' ); ?></h2>
							<p><?php esc_html_e( 'Generate metadata for all images in your library? This may take some time.', 'occidg' ); ?></p>
							<div id="bulk-generate-warning" class="warning" style="display:none;">
								<p><strong><?php esc_html_e( 'Warning:', 'occidg' ); ?></strong>
									<?php esc_html_e( 'This will overwrite any existing image metadata.', 'occidg' ); ?>
								</p>
							</div>
							<div class="modal-buttons">
								<button id="confirm-bulk-generate" class="button button-primary">
									<?php esc_html_e( 'Yes, Generate', 'occidg' ); ?>
								</button>
								<button id="cancel-bulk-generate" class="button button-secondary">
									<?php esc_html_e( 'Cancel', 'occidg' ); ?>
								</button>
							</div>
						</div>
					</div>
				</div><!-- End #occidg_images -->
			<?php elseif ( 'bulk-edit' === $tab ) : ?>
				<?php $plugin_bulk_edit->render_bulk_edit_tab(); ?>
			<?php endif; ?>
		</div>

		<?php if ( $is_first_time ) : ?>
			<?php $fallback_image_url = plugin_dir_url( __FILE__ ) . 'assets/icon.png'; ?>
			<div id="oneclick-images-first-time-modal" class="modal" style="display:block;">
				<div class="modal-content">
					<div class="modal-header" style="display: flex; align-items: center; gap: 15px;">
						<img src="<?php echo esc_url( $fallback_image_url ); ?>" alt="Plugin Icon" style="width: 50px; height: 50px;">
						<h3 style="margin: 0;"><?php esc_html_e( 'Welcome to OneClickContent Image Detail Generator!', 'occidg' ); ?></h3>
					</div>
					<p><?php esc_html_e( 'This plugin helps you effortlessly manage image metadata — including alt text, titles, captions, and descriptions — so your site looks great, loads better, and ranks higher.', 'occidg' ); ?></p>
					<p>
						<strong><?php esc_html_e( 'No license? No problem.', 'occidg' ); ?></strong><br>
						<?php esc_html_e( 'You can still use the Bulk Edit tab to search and edit all your images in a beautiful, lightning-fast table. It’s perfect for cleanup, audits, or just staying on top of things.', 'occidg' ); ?>
					</p>
					<p>
						<strong><?php esc_html_e( 'Free Trial Included!', 'occidg' ); ?></strong><br>
						<?php esc_html_e( 'Try it out right now with 10 free image detail generations — no license required. You can test the full automation experience risk-free.', 'occidg' ); ?>
					</p>
					<p>
						<strong><?php esc_html_e( 'Want to save even more time?', 'occidg' ); ?></strong><br>
						<?php esc_html_e( 'With a license, the plugin automatically generates metadata for every new image you upload — no more manual editing. Set it once and forget it.', 'occidg' ); ?>
					</p>
					<p><?php esc_html_e( 'Here’s how to get started:', 'occidg' ); ?></p>
					<ol>
						<li>
							<strong><?php esc_html_e( 'Settings Tab:', 'occidg' ); ?></strong>
							<?php esc_html_e( 'Choose which fields to automatically generate. Alt text, titles, captions, descriptions — your choice.', 'occidg' ); ?>
						</li>
						<li>
							<strong><?php esc_html_e( 'Bulk Edit Tab:', 'occidg' ); ?></strong>
							<?php esc_html_e( 'Instantly view and update all your images in one place. Edit any field inline and it saves automatically.', 'occidg' ); ?>
						</li>
						<li>
							<strong><?php esc_html_e( 'Automatic Generation:', 'occidg' ); ?></strong>
							<?php esc_html_e( 'Activate a license to generate metadata for every new image as it’s uploaded — no clicks needed.', 'occidg' ); ?>
						</li>
					</ol>
					<p>
						<strong><?php esc_html_e( 'Your images deserve better.', 'occidg' ); ?></strong><br>
						<?php esc_html_e( 'Give your media library the attention it deserves — for better SEO, accessibility, and user experience.', 'occidg' ); ?>
					</p>
					<div class="modal-buttons" style="margin-top: 20px; text-align: right;">
						<a href="https://oneclickcontent.com/image-detail-generator/" target="_blank" class="button button-secondary">
							<?php esc_html_e( 'Upgrade to Pro', 'occidg' ); ?>
						</a>
						<button id="close-first-time-modal" class="button button-primary">
							<?php esc_html_e( 'Let’s Get Started', 'occidg' ); ?>
						</button>
					</div>
				</div>
			</div>
			<?php
		endif;
	}

	/**
	 * Enqueue admin-specific stylesheets for the plugin.
	 *
	 * Loads CSS files on relevant admin screens (e.g., Media Library, plugin pages).
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function enqueue_styles() {
		$screen = get_current_screen();
		if ( ! $screen instanceof WP_Screen ) {
			return; // Exit early if screen is not available.
		}

		$allowed_screens = array( 'upload', 'post', 'post-new', 'toplevel_page_occidg' );

		if ( in_array( $screen->base, $allowed_screens, true ) ) {
			wp_enqueue_style(
				$this->plugin_name,
				plugin_dir_url( __FILE__ ) . 'css/occidg-admin.css',
				array(),
				$this->version,
				'all'
			);

			// Only proceed for the plugin's admin page.
			if ( 'toplevel_page_occidg' === $screen->id ) {
				$tab   = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general';
				$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';

				// Fallback to 'general' if nonce is invalid or missing.
				if ( empty( $tab ) || ! wp_verify_nonce( $nonce, 'oneclickcontent_tab_switch' ) ) {
					$tab = 'general';
				}

				// Note: Tab parameter is for UI state only; nonce adds an extra layer of verification.
				if ( 'bulk-edit' === $tab ) {
					wp_enqueue_style(
						$this->plugin_name . '-datatables',
						plugin_dir_url( __FILE__ ) . 'css/datatables.min.css', // Combined file with Buttons styling.
						array(),
						'2.2.2' // Version from the combined build.
					);
				}
			}
		}
	}

	/**
	 * Enqueue admin-specific JavaScript files for the plugin.
	 *
	 * Loads JavaScript files and localized data on relevant admin screens.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function enqueue_scripts() {
		if ( ! is_admin() ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen instanceof WP_Screen ) {
			return;
		}

		// Verify nonce for any POST data processing if applicable.
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === $_SERVER['REQUEST_METHOD'] && ! wp_verify_nonce( $nonce, 'occidg_bulk_edit' ) ) {
			return; // Silently exit instead of wp_die to avoid breaking script loading.
		}

		$allowed_screens = array( 'upload', 'post', 'post-new', 'toplevel_page_occidg' );

		if ( in_array( $screen->base, $allowed_screens, true ) ) {
			// Core admin script.
			wp_enqueue_script(
				$this->plugin_name,
				plugin_dir_url( __FILE__ ) . 'js/occidg-admin.js',
				array( 'jquery' ),
				$this->version,
				true
			);

			wp_enqueue_script(
				$this->plugin_name . '-error-check',
				plugin_dir_url( __FILE__ ) . 'js/one-click-error-check.js',
				array( 'jquery' ),
				$this->version,
				true
			);

			wp_enqueue_media();

			$selected_fields = wp_parse_args(
				get_option( 'occidg_metadata_fields', array() ),
				array(
					'title'       => false,
					'description' => false,
					'alt_text'    => false,
					'caption'     => false,
				)
			);

			$license_status = get_option( 'occidg_license_status', 'unknown' );

			$admin_vars = array(
				'ajax_url'                 => admin_url( 'admin-ajax.php' ),
				'occidg_ajax_nonce'        => wp_create_nonce( 'occidg_ajax_nonce' ),
				'selected_fields'          => $selected_fields,
				'license_status'           => sanitize_text_field( $license_status ),
				'upload_base_url'          => wp_upload_dir()['baseurl'],
				'fallback_image_url'       => plugin_dir_url( __FILE__ ) . 'assets/icon.png',
				'dismiss_first_time_nonce' => wp_create_nonce( 'occidg_dismiss_first_time' ),
			);
			wp_localize_script( $this->plugin_name, 'occidg_admin_vars', $admin_vars );

			wp_localize_script(
				$this->plugin_name . '-error-check',
				'occidg_error_vars',
				array(
					'ajax_url'          => $admin_vars['ajax_url'],
					'occidg_ajax_nonce' => $admin_vars['occidg_ajax_nonce'],
				)
			);

			// Load settings-bulk-generate.js on both settings and bulk edit tabs.
			if ( 'toplevel_page_occidg' === $screen->id ) {
				wp_enqueue_script(
					$this->plugin_name . '-settings',
					plugin_dir_url( __FILE__ ) . 'js/settings-bulk-generate.js',
					array( 'jquery', $this->plugin_name ),
					$this->version,
					true
				);
				wp_localize_script(
					$this->plugin_name . '-settings',
					'occidg_bulk_vars',
					array(
						'ajax_url' => admin_url( 'admin-ajax.php' ),
						'nonce'    => wp_create_nonce( 'occidg_bulk_edit' ),
					)
				);
			}

			// Bulk edit tab specific scripts.
			if ( 'toplevel_page_occidg' === $screen->id &&
				isset( $_GET['tab'] ) && 'bulk-edit' === sanitize_key( $_GET['tab'] ) ) {
				wp_enqueue_script(
					$this->plugin_name . '-datatables',
					plugin_dir_url( __FILE__ ) . 'js/datatables.min.js', // Combined file with Buttons functionality.
					array( 'jquery' ),
					'2.2.2', // Version from the combined build.
					true
				);
				wp_enqueue_script(
					$this->plugin_name . '-bulk-edit',
					plugin_dir_url( __FILE__ ) . 'js/bulk-edit.js',
					array(
						$this->plugin_name . '-datatables',
						$this->plugin_name . '-settings',
					),
					$this->version,
					true
				);
				wp_localize_script(
					$this->plugin_name . '-bulk-edit',
					'occidg_bulk_vars',
					array(
						'ajax_url' => admin_url( 'admin-ajax.php' ),
						'nonce'    => wp_create_nonce( 'occidg_bulk_edit' ),
					)
				);
			}

			// Enqueue settings-bulk-generate.js on media edit screens.
			if ( 'post' === $screen->base && 'attachment' === $screen->post_type && 'edit' === $screen->action ) {
				wp_enqueue_script(
					$this->plugin_name . '-settings',
					plugin_dir_url( __FILE__ ) . 'js/settings-bulk-generate.js',
					array( 'jquery', $this->plugin_name ),
					$this->version,
					true
				);
				wp_localize_script(
					$this->plugin_name . '-settings',
					'occidg_bulk_vars',
					array(
						'ajax_url' => admin_url( 'admin-ajax.php' ),
						'nonce'    => wp_create_nonce( 'occidg_bulk_edit' ),
					)
				);
			}
		}
	}

	/**
	 * Add a "Generate Metadata" button to the Media Library attachment details.
	 *
	 * Modifies the Media Library form to include a button for generating metadata.
	 *
	 * @since 1.0.0
	 * @param array   $form_fields An array of attachment form fields.
	 * @param WP_Post $post        The attachment post object.
	 * @return array Modified form fields with the custom button.
	 */
	/**
	 * Add a "Generate Metadata" button to the Media Library attachment details.
	 *
	 * Modifies the Media Library form to include a button for generating metadata.
	 *
	 * @since 1.0.0
	 * @param array   $form_fields An array of attachment form fields.
	 * @param WP_Post $post        The attachment post object.
	 * @return array Modified form fields with the custom button.
	 */
	public function add_generate_metadata_button( $form_fields, $post ) {
		if ( ! preg_match( '/^image\//', $post->post_mime_type ) ) {
			return $form_fields;
		}

		$license_key      = get_option( 'occidg_license_key', '' );
		$origin_url       = home_url();
		$product_slug     = 'occidg';
		$is_valid_license = false;
		$is_trial         = empty( $license_key );
		$trial_expired    = get_option( 'occidg_trial_expired', false );
		$trial_limit      = 10;
		$trial_usage      = (int) get_option( 'occidg_trial_usage', 0 );
		$usage_data       = array(
			'used_count'      => $is_trial ? $trial_usage : 0,
			'total_allowed'   => $is_trial ? $trial_limit : 100,
			'remaining_count' => $is_trial ? max( $trial_limit - $trial_usage, 0 ) : 0,
		);

		if ( ! empty( $license_key ) ) {
			$usage_response = wp_remote_post(
				rest_url( 'subscriber/v1/check-usage' ),
				array(
					'body'    => wp_json_encode(
						array(
							'license_key'  => $license_key,
							'origin_url'   => $origin_url,
							'product_slug' => $product_slug,
						)
					),
					'headers' => array( 'Content-Type' => 'application/json' ),
					'timeout' => 10,
				)
			);

			if ( ! is_wp_error( $usage_response ) ) {
				$body = json_decode( wp_remote_retrieve_body( $usage_response ), true );
				if ( isset( $body['success'] ) && $body['success'] ) {
					$is_valid_license              = true;
					$is_trial                      = false;
					$usage_data['used_count']      = $body['used_count'] ?? 0;
					$usage_data['total_allowed']   = ( $body['usage_limit'] ?? 0 ) + ( $body['addon_count'] ?? 0 );
					$usage_data['remaining_count'] = $body['remaining_count'] ?? 0;

					if ( $trial_expired ) {
						update_option( 'occidg_trial_expired', false );
						$trial_expired = false;
					}
				}
			}
		} elseif ( $is_trial && $trial_usage >= $trial_limit ) {
			$trial_expired = true;
			update_option( 'occidg_trial_expired', true );
		}

		$is_disabled  = $trial_expired || ( $is_valid_license && $usage_data['remaining_count'] <= 0 );
		$button_text  = esc_html__( 'Generate Metadata', 'occidg' );
		$settings_url = admin_url( 'admin.php?page=occidg' );

		$form_fields['generate_metadata'] = array(
			'label' => __( 'Generate Metadata', 'occidg' ),
			'input' => 'html',
			'html'  => sprintf(
				'<button type="button" class="button" id="generate_metadata_button" data-image-id="%s" %s>%s</button>' .
				( $is_disabled ? ' <a href="%s" class="button">%s</a>' : '' ),
				esc_attr( $post->ID ),
				$is_disabled ? 'disabled' : '',
				$button_text,
				esc_url( $settings_url ),
				esc_html__( 'Enter License Key', 'occidg' )
			),
		);

		wp_localize_script(
			'occidg-admin',
			'occidg_admin_vars',
			array(
				'ajax_url'          => admin_url( 'admin-ajax.php' ),
				'occidg_ajax_nonce' => wp_create_nonce( 'occidg_ajax_nonce' ),
				'is_valid_license'  => $is_valid_license,
				'is_trial'          => $is_trial,
				'trial_expired'     => $trial_expired,
				'usage'             => $usage_data,
				'settings_url'      => $settings_url,
			)
		);

		return $form_fields;
	}

	/**
	 * Add "Generate Details" bulk action to the Media Library.
	 *
	 * @since 1.0.0
	 * @param array $bulk_actions Existing bulk actions.
	 * @return array Modified bulk actions with "Generate Details" added.
	 */
	public function add_generate_details_bulk_action( $bulk_actions ) {
		$bulk_actions['generate_details'] = __( 'Generate Details', 'occidg' );
		return $bulk_actions;
	}

	/**
	 * Handle the "Generate Details" bulk action in the Media Library.
	 *
	 * Processes selected media items to generate metadata and redirects with a success message.
	 *
	 * @since 1.0.0
	 * @param string $redirect_to The URL to redirect to after processing.
	 * @param string $action      The bulk action being processed.
	 * @param array  $post_ids    Array of selected media item IDs.
	 * @return string Modified redirect URL with success query parameter.
	 */
	public function handle_generate_details_bulk_action( $redirect_to, $action, $post_ids ) {
		if ( 'generate_details' !== $action ) {
			return $redirect_to;
		}

		$nonce = isset( $_REQUEST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'bulk-media' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'occidg' ) );
		}

		if ( ! class_exists( 'Occidg_Admin_Settings' ) ) {
			return $redirect_to;
		}

		$admin_settings = new Occidg_Admin_Settings();

		foreach ( $post_ids as $post_id ) {
			$admin_settings->occidg_generate_metadata( intval( $post_id ) );
		}

		$generated_details_nonce = wp_create_nonce( 'generated_details_nonce' );
		$args                    = array(
			'generated_details'       => count( $post_ids ),
			'generated_details_nonce' => $generated_details_nonce,
		);
		return add_query_arg( $args, $redirect_to );
	}

	/**
	 * Display an admin notice after processing the "Generate Details" bulk action.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function generate_details_bulk_action_admin_notice() {
		if ( ! isset( $_REQUEST['generated_details'], $_REQUEST['generated_details_nonce'] ) ) {
			return;
		}

		$nonce = sanitize_text_field( wp_unslash( $_REQUEST['generated_details_nonce'] ) );
		if ( ! wp_verify_nonce( $nonce, 'generated_details_nonce' ) ) {
			return;
		}

		$count = intval( wp_unslash( $_REQUEST['generated_details'] ) );

		printf(
			'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
			esc_html(
				sprintf(
					/* translators: %d is the number of media items processed */
					__( 'Metadata generated for %d media items.', 'occidg' ),
					$count
				)
			)
		);
	}

	/**
	 * Register a custom image size for the plugin.
	 *
	 * Adds a 500x500 pixel cropped image size for API usage.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function occidg_register_custom_image_size() {
		add_image_size( 'one-click-image-api', 500, 500, true );
	}

	/**
	 * Add custom image size to the Media Library image size dropdown.
	 *
	 * Allows users to select the 'OCC Image' size when inserting images.
	 *
	 * @since 1.0.0
	 * @param array $sizes Existing image sizes.
	 * @return array Modified list of image sizes.
	 */
	public function occidg_add_custom_image_sizes( $sizes ) {
		return array_merge(
			$sizes,
			array(
				'one-click-image-api' => __( 'OCC Image', 'occidg' ),
			)
		);
	}

	/**
	 * Fetch the thumbnail URL for a given image ID via AJAX.
	 *
	 * @since 1.0.0
	 * @return void Outputs JSON response with the thumbnail URL or an error message.
	 */
	public function get_thumbnail() {
		$nonce = isset( $_GET['occidg_ajax_nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['occidg_ajax_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'occidg_ajax_nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'occidg' ) ) );
			return;
		}

		$image_id = isset( $_GET['image_id'] ) ? absint( wp_unslash( $_GET['image_id'] ) ) : 0;
		if ( ! $image_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid image ID.', 'occidg' ) ) );
			return;
		}

		$thumbnail_url = wp_get_attachment_thumb_url( $image_id );

		if ( $thumbnail_url ) {
			wp_send_json_success( array( 'thumbnail' => esc_url_raw( $thumbnail_url ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Thumbnail not found.', 'occidg' ) ) );
		}
	}

	/**
	 * Redirect to the settings page after plugin activation.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function activation_redirect() {
		if ( ! get_option( 'occidg_activation_redirect', false ) ) {
			return;
		}

		delete_option( 'occidg_activation_redirect' );

		// Use Yoda conditions for clarity and consistency.
		if ( is_network_admin() !== true && wp_doing_ajax() !== true && wp_doing_cron() !== true && ! defined( 'REST_REQUEST' ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=occidg' ) ); // Updated to match menu slug.
			exit;
		}
	}

	/**
	 * Check if metadata override is enabled via AJAX.
	 *
	 * @since 1.0.0
	 * @return void Outputs JSON response with override status.
	 */
	public function check_override_metadata() {
		check_ajax_referer( 'occidg_ajax_nonce', 'nonce' );

		$override = get_option( 'occidg_override_metadata', false );
		wp_send_json_success( array( 'override' => $override ) );
	}

	/**
	 * Dismiss the first time modal via AJAX.
	 *
	 * This function updates the 'occidg_first_time' option to false,
	 * effectively dismissing the first time modal. It first verifies the AJAX nonce,
	 * then attempts to update the option. If updating fails, it adds the option.
	 * Finally, it returns a JSON success response.
	 *
	 * @since 1.0.0
	 * @return void JSON response indicating success or failure.
	 */
	public function dismiss_first_time() {
		// Verify the nonce for security.
		if ( ! check_ajax_referer( 'occidg_dismiss_first_time', 'dismiss_first_time_nonce', false ) ) {
			wp_send_json_error( array( 'message' => 'Security check failed.' ) );
			return;
		}

		// Attempt to update the option to dismiss the first time modal.
		$updated = update_option( 'occidg_first_time', false );
		if ( false === $updated ) {
			// If update_option fails, try adding the option.
			$added = add_option( 'occidg_first_time', false );
		}

		wp_send_json_success();
	}
}