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

defined( 'ABSPATH' ) || exit;

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
	 * Bulk edit controller instance.
	 *
	 * @since 1.0.0
	 * @var Occidg_Bulk_Edit
	 */
	private $bulk_edit;

	/**
	 * Admin settings controller instance.
	 *
	 * @since 1.0.0
	 * @var Occidg_Admin_Settings
	 */
	private $admin_settings;

	/**
	 * Constructor for the admin class.
	 *
	 * Initializes the plugin name and version.
	 *
	 * @since 1.0.0
	 * @param string                     $plugin_name    The name of the plugin.
	 * @param string                     $version        The version of this plugin.
	 * @param Occidg_Bulk_Edit|null      $bulk_edit      Optional bulk edit controller.
	 * @param Occidg_Admin_Settings|null $admin_settings Optional settings controller.
	 */
	public function __construct( $plugin_name, $version, $bulk_edit = null, $admin_settings = null ) {
		$this->plugin_name    = $plugin_name;
		$this->version        = $version;
		$this->bulk_edit      = $bulk_edit instanceof Occidg_Bulk_Edit ? $bulk_edit : new Occidg_Bulk_Edit();
		$this->admin_settings = $admin_settings instanceof Occidg_Admin_Settings ? $admin_settings : new Occidg_Admin_Settings();
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
		$tab            = $this->get_active_tab();
		$first_time_key = 'occidg_first_time';
		$is_first_time  = get_option( $first_time_key, true );
		$tab_nonce      = wp_create_nonce( 'oneclickcontent_tab_switch' );
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
					<div class="bulk-edit-license-warning compact">
						<div class="cta-left">
							<img src="<?php echo esc_url( plugin_dir_url( __FILE__ ) . 'assets/icon.png' ); ?>" alt="<?php esc_attr_e( 'Plugin icon', 'occidg' ); ?>" style="float: left; margin-right: 10px; width: 50px; height: auto;">
							<h2><?php esc_html_e( 'Bring your own AI provider', 'occidg' ); ?></h2>
							<p><?php esc_html_e( 'Configure OpenAI or Gemini, choose which metadata fields to fill, and generate title, description, alt text, and caption directly from your own account.', 'occidg' ); ?></p>
							<ul class="benefits-list">
								<li><?php esc_html_e( 'Use your own API key', 'occidg' ); ?></li>
								<li><?php esc_html_e( 'Keep metadata generation focused on your Media Library', 'occidg' ); ?></li>
								<li><?php esc_html_e( 'Control which fields get updated', 'occidg' ); ?></li>
							</ul>
						</div>
					</div>

					<!-- Bulk generation options (shown regardless of license status) -->
					<div class="bulk-edit-header">
						<button id="generate-all-metadata-settings" class="button button-primary button-hero">
							<?php esc_html_e( 'Generate All Metadata', 'occidg' ); ?>
						</button>
						<button id="stop-bulk-generation-settings" class="button button-secondary" style="display:none;">
							<?php esc_html_e( 'Stop Generation', 'occidg' ); ?>
						</button>
						<p class="description">
							<?php esc_html_e( 'Click to generate metadata for all your images using your configured provider.', 'occidg' ); ?>
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
				<?php $this->bulk_edit->render_bulk_edit_tab(); ?>
			<?php endif; ?>
		</div>

		<?php if ( $is_first_time ) : ?>
			<?php $fallback_image_url = plugin_dir_url( __FILE__ ) . 'assets/icon.png'; ?>
			<div id="occidg-first-time-modal" class="modal" style="display:block;">
				<div class="modal-content">
					<div class="modal-header" style="display: flex; align-items: center; gap: 15px;">
						<img src="<?php echo esc_url( $fallback_image_url ); ?>" alt="<?php esc_attr_e( 'Plugin icon', 'occidg' ); ?>" style="width: 50px; height: 50px;">
						<h3 style="margin: 0;"><?php esc_html_e( 'Welcome to OneClickContent Image Detail Generator!', 'occidg' ); ?></h3>
					</div>
					<p><?php esc_html_e( 'This plugin helps you effortlessly manage image metadata — including alt text, titles, captions, and descriptions — so your site looks great, loads better, and ranks higher.', 'occidg' ); ?></p>
					<p>
						<strong><?php esc_html_e( 'Use your own provider.', 'occidg' ); ?></strong><br>
						<?php esc_html_e( 'Add an OpenAI or Gemini API key in Settings, then use AI generation wherever you need it.', 'occidg' ); ?>
					</p>
					<p>
						<strong><?php esc_html_e( 'Bulk Edit stays useful too.', 'occidg' ); ?></strong><br>
						<?php esc_html_e( 'You can still review, clean up, and edit all image metadata in one fast table whenever you want.', 'occidg' ); ?>
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
							<?php esc_html_e( 'Turn on auto-generation to create metadata for new uploads using your selected provider.', 'occidg' ); ?>
						</li>
					</ol>
					<p>
						<strong><?php esc_html_e( 'Your images deserve better.', 'occidg' ); ?></strong><br>
						<?php esc_html_e( 'Give your media library the attention it deserves — for better SEO, accessibility, and user experience.', 'occidg' ); ?>
					</p>
					<div class="modal-buttons" style="margin-top: 20px; text-align: right;">
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
	 * Loads CSS on Media Library, post edit, and plugin pages.
	 *
	 * @since 1.0.0
	 */
	public function enqueue_styles() {
		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->base, array( 'upload', 'post', 'post-new', 'toplevel_page_occidg' ), true ) ) {
			return;
		}

		// Core admin styles.
		wp_enqueue_style(
			$this->plugin_name,
			plugin_dir_url( __FILE__ ) . 'css/occidg-admin.css',
			array(),
			$this->version
		);

		// DataTables on Bulk-Edit tab.
		if ( 'toplevel_page_occidg' === $screen->id && $this->is_bulk_edit_tab() ) {
			wp_enqueue_style(
				"{$this->plugin_name}-datatables",
				plugin_dir_url( __FILE__ ) . 'css/datatables.min.css',
				array(),
				'2.2.2'
			);
		}
	}

	/**
	 * Enqueue admin-specific JavaScript files for the plugin.
	 *
	 * Loads JS and localizes data on Media Library, post edit, and plugin pages.
	 *
	 * @since 1.0.0
	 */
	public function enqueue_scripts() {
		if ( ! is_admin() ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->base, array( 'upload', 'post', 'post-new', 'toplevel_page_occidg' ), true ) ) {
			return;
		}

		// Core admin scripts.
		wp_enqueue_script(
			$this->plugin_name,
			plugin_dir_url( __FILE__ ) . 'js/occidg-admin.js',
			array( 'jquery' ),
			$this->version,
			true
		);
		wp_enqueue_media();

		wp_localize_script(
			$this->plugin_name,
			'occidg_admin_vars',
			array(
				'ajax_url'                 => admin_url( 'admin-ajax.php' ),
				'occidg_ajax_nonce'        => wp_create_nonce( 'occidg_ajax_nonce' ),
				'dismiss_first_time_nonce' => wp_create_nonce( 'occidg_dismiss_first_time' ),
				'provider'                 => get_option( 'occidg_provider', 'openai' ),
				'has_openai_key'           => ! empty( get_option( 'occidg_openai_api_key', '' ) ),
				'has_gemini_key'           => ! empty( get_option( 'occidg_gemini_api_key', '' ) ),
				'fallback_image_url'       => plugin_dir_url( __FILE__ ) . 'assets/icon.png',
				'settings_url'             => admin_url( 'admin.php?page=occidg' ),
			)
		);

		// Plugin settings scripts.
		if ( 'toplevel_page_occidg' === $screen->id ) {
			wp_enqueue_script(
				"{$this->plugin_name}-settings",
				plugin_dir_url( __FILE__ ) . 'js/settings-bulk-generate.js',
				array( 'jquery', $this->plugin_name ),
				$this->version,
				true
			);
			wp_localize_script(
				"{$this->plugin_name}-settings",
				'occidg_bulk_vars',
				array(
					'ajax_url' => admin_url( 'admin-ajax.php' ),
					'nonce'    => wp_create_nonce( 'occidg_bulk_edit' ),
				)
			);

			// Bulk-Edit tab extras.
			if ( $this->is_bulk_edit_tab() ) {
				wp_enqueue_script(
					"{$this->plugin_name}-datatables",
					plugin_dir_url( __FILE__ ) . 'js/datatables.min.js',
					array( 'jquery' ),
					'2.2.2',
					true
				);
				wp_enqueue_script(
					"{$this->plugin_name}-bulk-edit",
					plugin_dir_url( __FILE__ ) . 'js/bulk-edit.js',
					array( "{$this->plugin_name}-datatables", "{$this->plugin_name}-settings" ),
					$this->version,
					true
				);
				wp_localize_script(
					"{$this->plugin_name}-bulk-edit",
					'occidg_bulk_vars',
					array(
						'ajax_url' => admin_url( 'admin-ajax.php' ),
						'nonce'    => wp_create_nonce( 'occidg_bulk_edit' ),
					)
				);
			}
		}

		// Media-edit screen settings.
		if ( 'post' === $screen->base && 'attachment' === $screen->post_type && 'edit' === $screen->action ) {
			wp_enqueue_script(
				"{$this->plugin_name}-settings",
				plugin_dir_url( __FILE__ ) . 'js/settings-bulk-generate.js',
				array( 'jquery', $this->plugin_name ),
				$this->version,
				true
			);
			wp_localize_script(
				"{$this->plugin_name}-settings",
				'occidg_bulk_vars',
				array(
					'ajax_url' => admin_url( 'admin-ajax.php' ),
					'nonce'    => wp_create_nonce( 'occidg_bulk_edit' ),
				)
			);
		}
	}

	/**
	 * Add a "Generate Metadata" button to the Media Library attachment details.
	 *
	 * Only outputs the button HTML—enable/disable and usage counts
	 * are handled in occidg-admin.js via occidg_admin_vars.
	 *
	 * @since 1.0.0
	 *
	 * @param array   $form_fields Existing form fields.
	 * @param WP_Post $post        Attachment post object.
	 * @return array Modified form fields.
	 */
	public function add_generate_metadata_button( $form_fields, $post ) {
		// Bail if not an image.
		if ( strpos( $post->post_mime_type, 'image/' ) !== 0 ) {
			return $form_fields;
		}

		$form_fields['generate_metadata'] = array(
			'label' => __( 'Generate Metadata', 'occidg' ),
			'input' => 'html',
			'html'  => sprintf(
				'<button type="button" class="button generate-metadata" data-image-id="%d">%s</button>',
				(int) $post->ID,
				esc_html__( 'Generate Metadata', 'occidg' )
			),
		);

		return $form_fields;
	}

	/**
	 * Retrieve placeholder usage data for the BYO-key version.
	 *
	 * @since 1.0.0
	 * @return array Usage metrics.
	 */
	protected function get_usage_data() {
		return array(
			'success'         => true,
			'used_count'      => 0,
			'usage_limit'     => 0,
			'addon_count'     => 0,
			'remaining_count' => 0,
		);
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

		$image_ids = array();
		foreach ( $post_ids as $post_id ) {
			if ( wp_attachment_is_image( $post_id ) ) {
				$image_ids[] = intval( $post_id );
			}
		}

		$skipped_count = count( $post_ids ) - count( $image_ids );

		foreach ( $image_ids as $image_id ) {
			$this->admin_settings->occidg_generate_metadata( $image_id );
		}

		$generated_count = count( $image_ids );
		$nonce           = wp_create_nonce( 'generated_details_nonce' );
		$args            = array(
			'generated_details'       => $generated_count,
			'generated_details_nonce' => $nonce,
		);
		if ( $skipped_count ) {
			$args['skipped_non_images'] = $skipped_count;
		}

		return add_query_arg( $args, $redirect_to );
	}

	/**
	 * Display an admin notice after processing the "Generate Details" bulk action.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function generate_details_bulk_action_admin_notice() {
		// Bail if required params are missing.
		if ( ! isset( $_REQUEST['generated_details'], $_REQUEST['generated_details_nonce'] ) ) {
			return;
		}

		// Verify nonce.
		$nonce = sanitize_text_field( wp_unslash( $_REQUEST['generated_details_nonce'] ) );
		if ( ! wp_verify_nonce( $nonce, 'generated_details_nonce' ) ) {
			return;
		}

		// How many items were generated?
		$generated = intval( wp_unslash( $_REQUEST['generated_details'] ) );

		// Build the base message.
		$message = sprintf(
			/* translators: %d: number of media items. */
			_n( 'Metadata generated for %d media item.', 'Metadata generated for %d media items.', $generated, 'occidg' ),
			$generated
		);

		// If any non‑images were skipped, append that count.
		if ( ! empty( $_REQUEST['skipped_non_images'] ) ) {
			$skipped = intval( wp_unslash( $_REQUEST['skipped_non_images'] ) );

			$message .= ' ' . sprintf(
				/* translators: %d: number of skipped items. */
				_n( 'Skipped %d non-image.', 'Skipped %d non-images.', $skipped, 'occidg' ),
				$skipped
			);
		}

		// Output the admin notice.
		printf(
			'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
			esc_html( $message )
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

		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'occidg' ) ) );
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

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'occidg' ) ) );
			return;
		}

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
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'occidg' ) ) );
			return;
		}

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'occidg' ) ) );
			return;
		}

		// Attempt to update the option to dismiss the first time modal.
		$updated = update_option( 'occidg_first_time', false );
		if ( false === $updated ) {
			// If update_option fails, try adding the option.
			add_option( 'occidg_first_time', false );
		}

		wp_send_json_success();
	}

	/**
	 * Get the active plugin admin tab.
	 *
	 * @return string
	 */
	private function get_active_tab() {
		$tab = filter_input( INPUT_GET, 'tab', FILTER_UNSAFE_RAW );

		if ( ! is_string( $tab ) || '' === $tab ) {
			return 'settings';
		}

		$tab = sanitize_key( $tab );
		if ( ! in_array( $tab, array( 'settings', 'bulk-edit' ), true ) ) {
			return 'settings';
		}

		$nonce = filter_input( INPUT_GET, '_wpnonce', FILTER_UNSAFE_RAW );
		if ( ! is_string( $nonce ) || ! wp_verify_nonce( sanitize_text_field( $nonce ), 'oneclickcontent_tab_switch' ) ) {
			return 'settings';
		}

		return $tab;
	}

	/**
	 * Determine whether the current admin tab is the bulk-edit view.
	 *
	 * @return bool
	 */
	private function is_bulk_edit_tab() {
		return 'bulk-edit' === $this->get_active_tab();
	}
}
