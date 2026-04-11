<?php
/**
 * The file that defines the core plugin class.
 *
 * @link       https://github.com/jwilson529/oneclickcontent-images
 * @since      1.0.0
 *
 * @package    Occidg
 * @subpackage Occidg/includes
 */

defined( 'ABSPATH' ) || exit;

/**
 * The core plugin class.
 *
 * Defines admin-specific hooks and public-facing hooks.
 *
 * @since      1.0.0
 * @package    Occidg
 * @subpackage Occidg/includes
 * @author     James Wilson <james@oneclickcontent.com>
 */
class Occidg {

	/**
	 * The loader responsible for registering hooks with WordPress.
	 *
	 * @since 1.0.0
	 * @var Occidg_Loader
	 */
	protected $loader;

	/**
	 * The unique identifier for this plugin.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected $plugin_name;

	/**
	 * The current plugin version.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected $version;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->version     = defined( 'OCCIDG_VERSION' ) ? OCCIDG_VERSION : '1.0.0';
		$this->plugin_name = 'occidg';

		$this->load_dependencies();
		$this->define_admin_hooks();
		$this->define_public_hooks();
	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * @return void
	 */
	private function load_dependencies() {
		require_once plugin_dir_path( __DIR__ ) . 'includes/class-occidg-loader.php';
		require_once plugin_dir_path( __DIR__ ) . 'includes/class-occidg-logger.php';
		require_once plugin_dir_path( __DIR__ ) . 'admin/class-occidg-admin.php';
		require_once plugin_dir_path( __DIR__ ) . 'admin/class-occidg-admin-settings.php';
		require_once plugin_dir_path( __DIR__ ) . 'admin/class-occidg-auto-generate.php';
		require_once plugin_dir_path( __DIR__ ) . 'admin/class-occidg-bulk-edit.php';
		require_once plugin_dir_path( __DIR__ ) . 'public/class-occidg-public.php';

		$this->loader = new Occidg_Loader();
	}

	/**
	 * Register all hooks related to the admin area.
	 *
	 * @return void
	 */
	private function define_admin_hooks() {
		$plugin_admin_settings = new Occidg_Admin_Settings();
		$plugin_auto_generate  = new Occidg_Auto_Generate();
		$plugin_bulk_edit      = new Occidg_Bulk_Edit();
		$plugin_admin          = new Occidg_Admin(
			$this->get_occidg_images(),
			$this->get_version(),
			$plugin_bulk_edit,
			$plugin_admin_settings
		);

		$this->loader->add_action( 'admin_menu', $plugin_admin, 'register_admin_menu' );
		$this->loader->add_action( 'wp_ajax_occidg_check_override_metadata', $plugin_admin, 'check_override_metadata' );
		$this->loader->add_action( 'wp_ajax_occidg_dismiss_first_time', $plugin_admin, 'dismiss_first_time' );

		$this->loader->add_action( 'admin_init', $plugin_admin_settings, 'occidg_register_settings' );
		$this->loader->add_action( 'admin_notices', $plugin_admin_settings, 'display_admin_notices' );
		$this->loader->add_action( 'wp_ajax_occidg_save_settings', $plugin_admin_settings, 'occidg_save_settings' );
		$this->loader->add_action( 'wp_ajax_occidg_generate_metadata', $plugin_admin_settings, 'occidg_ajax_generate_metadata' );
		$this->loader->add_action( 'wp_ajax_occidg_refresh_nonce', $plugin_admin_settings, 'occidg_ajax_refresh_nonce' );

		$this->loader->add_filter( 'attachment_fields_to_edit', $plugin_admin, 'add_generate_metadata_button', 10, 2 );
		$this->loader->add_filter( 'bulk_actions-upload', $plugin_admin, 'add_generate_details_bulk_action' );
		$this->loader->add_filter( 'handle_bulk_actions-upload', $plugin_admin, 'handle_generate_details_bulk_action', 10, 3 );
		$this->loader->add_action( 'admin_notices', $plugin_admin, 'generate_details_bulk_action_admin_notice' );
		$this->loader->add_action( 'admin_init', $plugin_admin, 'activation_redirect' );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );

		$this->loader->add_filter( 'wp_generate_attachment_metadata', $plugin_auto_generate, 'auto_generate_metadata', 10, 2 );
		$this->loader->add_action( 'wp_ajax_occidg_get_all_media_ids', $plugin_auto_generate, 'occidg_get_all_media_ids' );
		$this->loader->add_action( 'wp_ajax_check_image_error', $plugin_auto_generate, 'check_image_error' );
		$this->loader->add_action( 'wp_ajax_occidg_remove_image_error_transient', $plugin_auto_generate, 'occidg_remove_image_error_transient' );

		$this->loader->add_action( 'plugins_loaded', $plugin_admin, 'occidg_register_custom_image_size' );
		$this->loader->add_filter( 'image_size_names_choose', $plugin_admin, 'occidg_add_custom_image_sizes' );
		$this->loader->add_action( 'wp_ajax_get_thumbnail', $plugin_admin, 'get_thumbnail' );

		$this->loader->add_action( 'wp_ajax_occidg_get_image_metadata', $plugin_bulk_edit, 'get_image_metadata' );
		$this->loader->add_action( 'wp_ajax_occidg_save_bulk_metadata', $plugin_bulk_edit, 'save_bulk_metadata' );
	}

	/**
	 * Register all hooks related to the public-facing side of the site.
	 *
	 * @return void
	 */
	private function define_public_hooks() {
		$plugin_public = new Occidg_Public( $this->get_occidg_images(), $this->get_version() );

		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_styles' );
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_scripts' );
	}

	/**
	 * Run the loader to execute all hooks with WordPress.
	 *
	 * @return void
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * Retrieve the name of the plugin.
	 *
	 * @return string
	 */
	public function get_occidg_images() {
		return $this->plugin_name;
	}

	/**
	 * Retrieve the reference to the class that orchestrates the hooks.
	 *
	 * @return Occidg_Loader
	 */
	public function get_loader() {
		return $this->loader;
	}

	/**
	 * Retrieve the plugin version.
	 *
	 * @return string
	 */
	public function get_version() {
		return $this->version;
	}
}
