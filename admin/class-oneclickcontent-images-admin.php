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
 * Class OneClickContent_Images_Admin
 *
 * Handles admin-specific functionality for the OneClickContent Image Details plugin,
 * including enqueuing scripts/styles and adding Media Library enhancements.
 *
 * @since 1.0.0
 */
class OneClickContent_Images_Admin {

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
			__( 'OneClickContent Image Metadata', 'oneclickcontent-images' ), // Page title (detailed for context).
			__( 'Image Metadata', 'oneclickcontent-images' ),         // Menu title (shortened to avoid wrapping).
			'edit_posts',                                             // Capability (minimum for bulk edit; settings will check manage_options).
			'oneclickcontent-images',                                 // Menu slug.
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
		if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === $_SERVER['REQUEST_METHOD'] && ! wp_verify_nonce( $nonce, 'oneclick_images_settings-options' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'oneclickcontent-images' ) );
		}

		$tab                   = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'settings';
		$plugin_admin_settings = new OneClickContent_Images_Admin_Settings(); // Temporary; ideally injected.
		$plugin_bulk_edit      = new OneClickContent_Images_Bulk_Edit(); // Temporary; ideally injected.
		$license_status        = get_option( 'oneclick_images_license_status', 'unknown' );
		$header_image_url      = plugin_dir_url( __FILE__ ) . 'assets/header-image.webp';
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'OneClickContent Images', 'oneclickcontent-images' ); ?></h1>
			<h2 class="nav-tab-wrapper">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=oneclickcontent-images&tab=settings' ) ); ?>" class="nav-tab <?php echo 'settings' === $tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Settings', 'oneclickcontent-images' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=oneclickcontent-images&tab=bulk-edit' ) ); ?>" class="nav-tab <?php echo 'bulk-edit' === $tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Bulk Edit', 'oneclickcontent-images' ); ?>
				</a>
			</h2>

			<?php if ( 'settings' === $tab ) : ?>
				<div id="oneclick_images" class="wrap">

					<?php if ( 'active' === $license_status ) : ?>
						<!-- Usage Counter & Bulk Generation (for licensed users) -->
						<div class="usage-info-section">
							<h2><?php esc_html_e( 'Your Usage', 'oneclickcontent-images' ); ?></h2>
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

							<div class="bulk-edit-header">
								<button id="generate-all-metadata-settings" class="button button-primary button-hero">
									<?php esc_html_e( 'Generate All Metadata', 'oneclickcontent-images' ); ?>
								</button>
								<button id="stop-bulk-generation-settings" class="button button-secondary" style="display:none;">
									<?php esc_html_e( 'Stop Generation', 'oneclickcontent-images' ); ?>
								</button>
								<p class="description"><?php esc_html_e( 'Click to generate metadata for all your images.', 'oneclickcontent-images' ); ?></p>
							</div>

							<div id="bulk-generate-status-settings" class="bulk-generate-status" style="display: none;">
								<h3>Bulk Generation Progress</h3>
								<div id="bulk-generate-progress-container-settings" class="bulk-generate-progress-container">
									<div id="bulk-generate-progress-bar-settings" class="bulk-generate-progress-bar"></div>
								</div>
								<div id="bulk-generate-message-settings" class="bulk-generate-message"></div>
							</div>
						</div>
					<?php else : ?>
						<!-- CTA for unlicensed users -->
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
					<?php endif; ?>

					<!-- Settings Form -->
					<form method="post" action="options.php" id="oneclick_images_settings_form">
						<?php
						settings_fields( 'oneclick_images_settings' );
						do_settings_sections( 'oneclick_images_settings' );
						submit_button();
						?>
					</form>
					<!-- Modal for Bulk Generation Confirmation -->
					<div id="bulk-generate-modal" class="modal" style="display:none;">
						<div class="modal-content">
							<h2><?php esc_html_e( 'Confirm Bulk Metadata Generation', 'oneclickcontent-images' ); ?></h2>
							<p><?php esc_html_e( 'Generate metadata for all images in your library? This may take some time.', 'oneclickcontent-images' ); ?></p>
							<div id="bulk-generate-warning" class="warning" style="display:none;">
								<p><strong><?php esc_html_e( 'Warning:', 'oneclickcontent-images' ); ?></strong> <?php esc_html_e( 'This will overwrite any existing image metadata.', 'oneclickcontent-images' ); ?></p>
							</div>
							<div class="modal-buttons">
								<button id="confirm-bulk-generate" class="button button-primary"><?php esc_html_e( 'Yes, Generate', 'oneclickcontent-images' ); ?></button>
								<button id="cancel-bulk-generate" class="button button-secondary"><?php esc_html_e( 'Cancel', 'oneclickcontent-images' ); ?></button>
							</div>
						</div>
					</div>
				</div>
			<?php elseif ( 'bulk-edit' === $tab ) : ?>
				<?php $plugin_bulk_edit->render_bulk_edit_tab(); ?>
			<?php endif; ?>
		</div>
		<?php
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

		$allowed_screens = array( 'upload', 'post', 'post-new', 'toplevel_page_oneclickcontent-images' );

		if ( in_array( $screen->base, $allowed_screens, true ) ) {
			wp_enqueue_style(
				$this->plugin_name,
				plugin_dir_url( __FILE__ ) . 'css/oneclickcontent-images-admin.css',
				array(),
				$this->version,
				'all'
			);

			// Only proceed for the plugin's admin page.
			if ( 'toplevel_page_oneclickcontent-images' === $screen->id ) {
				// Safely get the current tab.
				// Note: This is just a UI state parameter, not processing form submissions,
				// so nonce verification is not necessary.
				$tab = '';
				if ( isset( $_GET['tab'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- UI state parameter only.
					$tab = sanitize_key( wp_unslash( $_GET['tab'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- UI state parameter only.
				}

				// Default to 'general' tab if none specified.
				if ( empty( $tab ) ) {
					$tab = 'general';
				}

				// Only load DataTables CSS for bulk-edit tab.
				if ( 'bulk-edit' === $tab ) {
					wp_enqueue_style(
						$this->plugin_name . '-datatables',
						plugin_dir_url( __FILE__ ) . 'css/jquery.dataTables.min.css',
						array(),
						'1.13.1'
					);
					wp_enqueue_style(
						$this->plugin_name . '-datatables-buttons',
						plugin_dir_url( __FILE__ ) . 'css/buttons.dataTables.min.css',
						array( $this->plugin_name . '-datatables' ),
						'2.4.2'
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
		if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === $_SERVER['REQUEST_METHOD'] && ! wp_verify_nonce( $nonce, 'oneclick_images_bulk_edit' ) ) {
			return; // Silently exit instead of wp_die to avoid breaking script loading.
		}

		$allowed_screens = array( 'upload', 'post', 'post-new', 'toplevel_page_oneclickcontent-images' );

		if ( in_array( $screen->base, $allowed_screens, true ) ) {
			// Core admin script.
			wp_enqueue_script(
				$this->plugin_name,
				plugin_dir_url( __FILE__ ) . 'js/oneclickcontent-images-admin.js',
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
				get_option( 'oneclick_images_metadata_fields', array() ),
				array(
					'title'       => false,
					'description' => false,
					'alt_text'    => false,
					'caption'     => false,
				)
			);

			$license_status = get_option( 'oneclick_images_license_status', 'unknown' );

			$admin_vars = array(
				'ajax_url'                   => admin_url( 'admin-ajax.php' ),
				'oneclick_images_ajax_nonce' => wp_create_nonce( 'oneclick_images_ajax_nonce' ),
				'selected_fields'            => $selected_fields,
				'license_status'             => sanitize_text_field( $license_status ),
				'upload_base_url'            => wp_upload_dir()['baseurl'],
				'fallback_image_url'         => plugin_dir_url( __FILE__ ) . 'assets/icon.png',
			);

			wp_localize_script( $this->plugin_name, 'oneclick_images_admin_vars', $admin_vars );
			wp_localize_script(
				$this->plugin_name . '-error-check',
				'oneclick_images_error_vars',
				array(
					'ajax_url'                   => $admin_vars['ajax_url'],
					'oneclick_images_ajax_nonce' => $admin_vars['oneclick_images_ajax_nonce'],
				)
			);

			// Load settings-bulk-generate.js on both settings and bulk edit tabs.
			if ( 'toplevel_page_oneclickcontent-images' === $screen->id ) {
				wp_enqueue_script(
					$this->plugin_name . '-settings',
					plugin_dir_url( __FILE__ ) . 'js/settings-bulk-generate.js',
					array( 'jquery', $this->plugin_name ),
					$this->version,
					true
				);
				wp_localize_script(
					$this->plugin_name . '-settings',
					'oneclick_images_bulk_vars',
					array(
						'ajax_url' => admin_url( 'admin-ajax.php' ),
						'nonce'    => wp_create_nonce( 'oneclick_images_bulk_edit' ),
					)
				);
			}

			// Bulk edit tab specific scripts.
			if ( 'toplevel_page_oneclickcontent-images' === $screen->id &&
				isset( $_GET['tab'] ) && 'bulk-edit' === sanitize_key( $_GET['tab'] ) ) {
				wp_enqueue_script(
					$this->plugin_name . '-datatables',
					plugin_dir_url( __FILE__ ) . 'js/jquery.dataTables.min.js',
					array( 'jquery' ),
					'1.13.1',
					true
				);
				wp_enqueue_script(
					$this->plugin_name . '-datatables-buttons',
					plugin_dir_url( __FILE__ ) . 'js/dataTables.buttons.min.js',
					array( $this->plugin_name . '-datatables' ),
					'2.4.2',
					true
				);
				wp_enqueue_script(
					$this->plugin_name . '-datatables-buttons-html5',
					plugin_dir_url( __FILE__ ) . 'js/buttons.html5.min.js',
					array( $this->plugin_name . '-datatables-buttons' ),
					'2.4.2',
					true
				);
				wp_enqueue_script(
					$this->plugin_name . '-bulk-edit',
					plugin_dir_url( __FILE__ ) . 'js/bulk-edit.js',
					array( $this->plugin_name . '-datatables', $this->plugin_name . '-datatables-buttons-html5', $this->plugin_name . '-settings' ),
					$this->version,
					true
				);
				wp_localize_script(
					$this->plugin_name . '-bulk-edit',
					'oneclick_images_bulk_vars',
					array(
						'ajax_url' => admin_url( 'admin-ajax.php' ),
						'nonce'    => wp_create_nonce( 'oneclick_images_bulk_edit' ),
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
					'oneclick_images_bulk_vars',
					array(
						'ajax_url' => admin_url( 'admin-ajax.php' ),
						'nonce'    => wp_create_nonce( 'oneclick_images_bulk_edit' ),
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
	public function add_generate_metadata_button( $form_fields, $post ) {
		if ( ! preg_match( '/^image\//', $post->post_mime_type ) ) {
			return $form_fields;
		}

		$form_fields['generate_metadata'] = array(
			'label' => __( 'Generate Metadata', 'oneclickcontent-images' ),
			'input' => 'html',
			'html'  => sprintf(
				'<button type="button" class="button" id="generate_metadata_button" data-image-id="%s">%s</button>',
				esc_attr( $post->ID ),
				esc_html__( 'Generate Metadata', 'oneclickcontent-images' )
			),
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
		$bulk_actions['generate_details'] = __( 'Generate Details', 'oneclickcontent-images' );
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
			wp_die( esc_html__( 'Security check failed.', 'oneclickcontent-images' ) );
		}

		if ( ! class_exists( 'OneClickContent_Images_Admin_Settings' ) ) {
			return $redirect_to;
		}

		$admin_settings = new OneClickContent_Images_Admin_Settings();

		foreach ( $post_ids as $post_id ) {
			$admin_settings->oneclick_images_generate_metadata( intval( $post_id ) );
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
					__( 'Metadata generated for %d media items.', 'oneclickcontent-images' ),
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
	public function oneclick_register_custom_image_size() {
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
	public function oneclick_add_custom_image_sizes( $sizes ) {
		return array_merge(
			$sizes,
			array(
				'one-click-image-api' => __( 'OCC Image', 'oneclickcontent-images' ),
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
		$nonce = isset( $_GET['oneclick_images_ajax_nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['oneclick_images_ajax_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'oneclick_images_ajax_nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'oneclickcontent-images' ) ) );
			return;
		}

		$image_id = isset( $_GET['image_id'] ) ? absint( wp_unslash( $_GET['image_id'] ) ) : 0;
		if ( ! $image_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid image ID.', 'oneclickcontent-images' ) ) );
			return;
		}

		$thumbnail_url = wp_get_attachment_thumb_url( $image_id );

		if ( $thumbnail_url ) {
			wp_send_json_success( array( 'thumbnail' => esc_url_raw( $thumbnail_url ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Thumbnail not found.', 'oneclickcontent-images' ) ) );
		}
	}

	/**
	 * Redirect to the settings page after plugin activation.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function activation_redirect() {
		if ( ! get_option( 'oneclick_images_activation_redirect', false ) ) {
			return;
		}

		delete_option( 'oneclick_images_activation_redirect' );

		// Use Yoda conditions for clarity and consistency.
		if ( is_network_admin() !== true && wp_doing_ajax() !== true && wp_doing_cron() !== true && ! defined( 'REST_REQUEST' ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=oneclickcontent-images' ) ); // Updated to match menu slug.
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
		check_ajax_referer( 'oneclick_images_ajax_nonce', 'nonce' );

		$override = get_option( 'oneclick_images_override_metadata', false );
		wp_send_json_success( array( 'override' => $override ) );
	}
}