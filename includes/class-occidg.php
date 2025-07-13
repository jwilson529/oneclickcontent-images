<?php
/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       https://oneclickcontent.com
 * @since      1.0.0
 *
 * @package    Occidg
 * @subpackage Occidg/includes
 */

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    Occidg
 * @subpackage Occidg/includes
 * @author     James Wilson <james@oneclickcontent.com>
 */
class Occidg {

	/**
	 * The loader responsible for maintaining and registering all hooks that power the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      Occidg_Loader    $loader    Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $occidg_images    The string used to uniquely identify this plugin.
	 */
	protected $occidg_images;

	/**
	 * The current version of the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $version    The current version of the plugin.
	 */
	protected $version;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and version, load dependencies, define the locale,
	 * and set the hooks for the admin area and public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		if ( defined( 'OCCIDG_VERSION' ) ) {
			$this->version = OCCIDG_VERSION;
		} else {
			$this->version = '1.0.0';
		}
		$this->occidg_images = 'occidg';

		$this->load_dependencies();
		$this->define_admin_hooks();
	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * Include the following files that make up the plugin:
	 *
	 * - Occidg_Loader: Orchestrates the hooks of the plugin.
	 * - Occidg_Admin: Defines all hooks for the admin area.
	 * - Occidg_Admin_Settings: Handles admin settings.
	 * - Occidg_Auto_Generate: Handles automatic metadata generation.
	 * - Occidg_License_Update: Manages license validation and usage tracking.
	 * - Occidg_Public: Defines all hooks for the public side of the site.
	 *
	 * Create an instance of the loader to register the hooks with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function load_dependencies() {
		/**
		 * The class responsible for orchestrating the actions and filters of the core plugin.
		 */
		require_once plugin_dir_path( __DIR__ ) . 'includes/class-occidg-loader.php';

		/**
		 * The class responsible for defining all actions that occur in the admin area.
		 */
		require_once plugin_dir_path( __DIR__ ) . 'admin/class-occidg-admin.php';

		/**
		 * The class responsible for defining admin settings and metadata generation.
		 */
		require_once plugin_dir_path( __DIR__ ) . 'admin/class-occidg-admin-settings.php';

		/**
		 * The class responsible for automatic metadata generation based on user settings.
		 */
		require_once plugin_dir_path( __DIR__ ) . 'admin/class-occidg-auto-generate.php';

		/**
		 * The class responsible for license validation and usage tracking.
		 */
		require_once plugin_dir_path( __DIR__ ) . 'admin/class-occidg-license-update.php';

		/**
		 * The class responsible for the bulk edit screen and functions.
		 */
		require_once plugin_dir_path( __DIR__ ) . 'admin/class-occidg-bulk-edit.php';

		$this->loader = new Occidg_Loader();
	}

	/**
	 * Register all hooks related to the admin area functionality of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_admin_hooks() {
		$plugin_admin          = new Occidg_Admin( $this->get_occidg_images(), $this->get_version() );
		$plugin_admin_settings = new Occidg_Admin_Settings();
		$plugin_auto_generate  = new Occidg_Auto_Generate();
		$plugin_bulk_edit      = new Occidg_Bulk_Edit();
		$plugin_license_update = new Occidg_License_Update(
			'https://oneclickcontent.com/wp-json/oneclick/v1/auth/validate-license',
			'occidg'
		);

		// Register top-level menu (single callback).
		$this->loader->add_action( 'admin_menu', $plugin_admin, 'register_admin_menu' );
		$this->loader->add_action( 'wp_ajax_occidg_check_override_metadata', $plugin_admin, 'check_override_metadata' );
		$this->loader->add_action( 'wp_ajax_occidg_dismiss_first_time', $plugin_admin, 'dismiss_first_time' );

		// Register settings for the Settings tab.
		$this->loader->add_action( 'admin_init', $plugin_admin_settings, 'occidg_register_settings' );

		// Handle admin notices.
		$this->loader->add_action( 'admin_notices', $plugin_admin_settings, 'display_admin_notices' );
		$this->loader->add_action( 'wp_ajax_occidg_save_settings', $plugin_admin_settings, 'occidg_save_settings' );

		// AJAX action for generating metadata.
		$this->loader->add_action( 'wp_ajax_occidg_generate_metadata', $plugin_admin_settings, 'occidg_ajax_generate_metadata' );
		$this->loader->add_action( 'wp_ajax_occidg_refresh_nonce', $plugin_admin_settings, 'occidg_ajax_refresh_nonce' );

		// Add the "Generate Metadata" button to the Media Library.
		$this->loader->add_filter( 'attachment_fields_to_edit', $plugin_admin, 'add_generate_metadata_button', 10, 2 );
		$this->loader->add_filter( 'bulk_actions-upload', $plugin_admin, 'add_generate_details_bulk_action' );
		$this->loader->add_filter( 'handle_bulk_actions-upload', $plugin_admin, 'handle_generate_details_bulk_action', 10, 3 );
		$this->loader->add_action( 'admin_notices', $plugin_admin, 'generate_details_bulk_action_admin_notice' );
		$this->loader->add_action( 'admin_init', $plugin_admin, 'activation_redirect' );

		// Enqueue admin styles and scripts.
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );

		// Auto-generate and error handling hooks.
		$this->loader->add_action( 'wp_ajax_occidg_get_all_media_ids', $plugin_auto_generate, 'occidg_get_all_media_ids' );
		$this->loader->add_action( 'wp_ajax_check_image_error', $plugin_auto_generate, 'check_image_error' );
		$this->loader->add_action( 'wp_ajax_occidg_remove_image_error_transient', $plugin_auto_generate, 'occidg_remove_image_error_transient' );

		// Image size hooks.
		$this->loader->add_action( 'plugins_loaded', $plugin_admin, 'occidg_register_custom_image_size' );
		$this->loader->add_filter( 'image_size_names_choose', $plugin_admin, 'occidg_add_custom_image_sizes' );
		$this->loader->add_action( 'wp_ajax_get_thumbnail', $plugin_admin, 'get_thumbnail' );

		// License validation and usage hooks.
		$this->loader->add_action( 'wp_ajax_occidg_validate_license', $plugin_license_update, 'ajax_validate_license' );
		$this->loader->add_action( 'wp_ajax_occidg_get_license_status', $plugin_license_update, 'ajax_get_license_status' );
		$this->loader->add_action( 'wp_ajax_occidg_check_usage', $plugin_license_update, 'occidg_ajax_check_usage' );

		// Bulk edit AJAX handlers.
		$this->loader->add_action( 'wp_ajax_occidg_get_image_metadata', $plugin_bulk_edit, 'get_image_metadata' );
		$this->loader->add_action( 'wp_ajax_occidg_save_bulk_metadata', $plugin_bulk_edit, 'save_bulk_metadata' );
	}

	/**
	 * Run the loader to execute all hooks with WordPress.
	 *
	 * @since    1.0.0
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * Retrieve the name of the plugin.
	 *
	 * The name is used to uniquely identify the plugin within WordPress
	 * and to define internationalization functionality.
	 *
	 * @since    1.0.0
	 * @return   string    The name of the plugin.
	 */
	public function get_occidg_images() {
		return $this->occidg_images;
	}

	/**
	 * Retrieve the reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @since    1.0.0
	 * @return   Occidg_Loader    Orchestrates the hooks of the plugin.
	 */
	public function get_loader() {
		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @since    1.0.0
	 * @return   string    The version number of the plugin.
	 */
	public function get_version() {
		return $this->version;
	}
}
