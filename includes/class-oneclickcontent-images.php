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
 * @package    One_Click_Images
 * @subpackage One_Click_Images/includes
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
 * @package    One_Click_Images
 * @subpackage One_Click_Images/includes
 * @author     James Wilson <james@oneclickcontent.com>
 */
class One_Click_Images {

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      One_Click_Images_Loader    $loader    Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $oneclick_images    The string used to uniquely identify this plugin.
	 */
	protected $oneclick_images;

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
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		if ( defined( 'OCC_IMAGES_VERSION' ) ) {
			$this->version = OCC_IMAGES_VERSION;
		} else {
			$this->version = '1.0.0';
		}
		$this->oneclick_images = 'oneclickcontent-images';

		$this->load_dependencies();
		$this->set_locale();
		$this->define_admin_hooks();
	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * Include the following files that make up the plugin:
	 *
	 * - One_Click_Images_Loader. Orchestrates the hooks of the plugin.
	 * - One_Click_Images_I18n. Defines internationalization functionality.
	 * - One_Click_Images_Admin. Defines all hooks for the admin area.
	 * - One_Click_Images_Public. Defines all hooks for the public side of the site.
	 *
	 * Create an instance of the loader which will be used to register the hooks
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function load_dependencies() {

		/**
		 * The class responsible for orchestrating the actions and filters of the
		 * core plugin.
		 */
		require_once plugin_dir_path( __DIR__ ) . 'includes/class-oneclickcontent-images-loader.php';

		/**
		 * The class responsible for defining internationalization functionality
		 * of the plugin.
		 */
		require_once plugin_dir_path( __DIR__ ) . 'includes/class-oneclickcontent-images-i18n.php';

		/**
		 * The class responsible for defining all actions that occur in the admin area.
		 */
		require_once plugin_dir_path( __DIR__ ) . 'admin/class-oneclickcontent-images-admin.php';

		/**
		 * The class responsible for defining all actions that occur in the admin area.
		 */
		require_once plugin_dir_path( __DIR__ ) . 'admin/class-oneclickcontent-images-admin-settings.php';

		/**
		 * The class responsible for automatically running based on the user setting.
		 */
		require_once plugin_dir_path( __DIR__ ) . 'admin/class-oneclickcontent-images-auto-generate.php';
		require_once plugin_dir_path( __DIR__ ) . 'admin/class-oneclickcontent-images-license-update.php';

		$this->loader = new One_Click_Images_Loader();
	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the One_Click_Images_I18n class in order to set the domain and to register the hook
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function set_locale() {

		$plugin_i18n = new One_Click_Images_I18n();

		$this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );
	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_admin_hooks() {

		$plugin_admin          = new One_Click_Images_Admin( $this->get_oneclick_images(), $this->get_version() );
		$plugin_admin_settings = new One_Click_Images_Admin_Settings();

		$plugin_auto_generate = new One_Click_Images_Auto_Generate();

		$plugin_license_update = new One_Click_Images_License_Update(
			'https://oneclickcontent.local/wp-json/oneclick/v1/updates/',
			'oneclickcontent-images',
			$this->get_version()
		);

		// Register the settings page and settings.
		$this->loader->add_action( 'admin_menu', $plugin_admin_settings, 'oneclick_images_register_options_page' );
		$this->loader->add_action( 'admin_init', $plugin_admin_settings, 'oneclick_images_register_settings' );

		// Handle admin notices.
		$this->loader->add_action( 'admin_notices', $plugin_admin_settings, 'display_admin_notices' );
		$this->loader->add_action( 'wp_ajax_oneclick_images_save_settings', $plugin_admin_settings, 'oneclick_images_save_settings' );

		// AJAX action for generating metadata.
		$this->loader->add_action( 'wp_ajax_oneclick_images_generate_metadata', $plugin_admin_settings, 'oneclick_images_ajax_generate_metadata' );

		// Add the "Generate Metadata" button to the Media Library.
		$this->loader->add_filter( 'attachment_fields_to_edit', $plugin_admin, 'add_generate_metadata_button', 10, 2 );
		$this->loader->add_filter( 'bulk_actions-upload', $plugin_admin, 'add_generate_details_bulk_action', 10, 2 );
		$this->loader->add_filter( 'handle_bulk_actions-upload', $plugin_admin, 'handle_generate_details_bulk_action', 10, 3 );
		$this->loader->add_filter( 'admin_notices', $plugin_admin, 'generate_details_bulk_action_admin_notice' );
		$this->loader->add_filter( 'admin_init', $plugin_admin, 'activation_redirect' );

		// Enqueue admin styles and scripts.
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );
		$this->loader->add_action( 'wp_ajax_oneclick_images_get_all_media_ids', $plugin_auto_generate, 'oneclick_images_get_all_media_ids' );
		$this->loader->add_action( 'wp_ajax_check_image_error', $plugin_auto_generate, 'check_image_error' );
		$this->loader->add_action( 'wp_ajax_remove_image_error_transient', $plugin_auto_generate, 'oneclick_remove_image_error_transient' );

		$this->loader->add_action( 'plugins_loaded', $plugin_admin, 'oneclick_register_custom_image_size' );

		$this->loader->add_filter( 'image_size_names_choose', $plugin_admin, 'oneclick_add_custom_image_sizes' );
		$this->loader->add_action( 'wp_ajax_get_thumbnail', $plugin_admin, 'get_thumbnail' );

		// Hooking methods related to license update
		$this->loader->add_filter( 'pre_set_site_transient_update_plugins', $plugin_license_update, 'check_for_update' );
		$this->loader->add_action( 'admin_init', $plugin_license_update, 'validate_license_on_init' );
		$this->loader->add_filter( 'site_transient_update_plugins', $plugin_license_update, 'one_click_images_add_update_icon', 20 );
		$this->loader->add_action( 'wp_ajax_validate_license', $plugin_license_update, 'ajax_validate_license' );
		$this->loader->add_action( 'wp_ajax_get_license_status', $plugin_license_update, 'ajax_get_license_status' );
		$this->loader->add_action( 'wp_ajax_check_usage', $plugin_license_update, 'oneclick_images_ajax_check_usage' );
		$this->loader->add_action( 'plugins_api', $plugin_license_update, 'oneclickcontent_plugin_popup_info', 10, 3 );
	}



	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_public_hooks() {

		$plugin_public = new One_Click_Images_Public( $this->get_oneclick_images(), $this->get_version() );

		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_styles' );
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_scripts' );
	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    1.0.0
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @since     1.0.0
	 * @return    string    The name of the plugin.
	 */
	public function get_oneclick_images() {
		return $this->oneclick_images;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @since     1.0.0
	 * @return    One_Click_Images_Loader    Orchestrates the hooks of the plugin.
	 */
	public function get_loader() {
		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @since     1.0.0
	 * @return    string    The version number of the plugin.
	 */
	public function get_version() {
		return $this->version;
	}
}
