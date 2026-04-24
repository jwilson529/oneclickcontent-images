<?php
/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link       https://github.com/jwilson529/oneclickcontent-images
 * @since      1.0.0
 *
 * @package    Occidg
 * @subpackage Occidg
 */

/**
 * Plugin Name:       OneClickContent - Image Detail Generator
 * Plugin URI:        https://github.com/jwilson529/oneclickcontent-images
 * Description:       Free AI-powered image metadata generation for Media Library images using your own OpenAI or Gemini API key.
 * Version:           1.2.2
 * Author:            James Wilson
 * Author URI:        https://github.com/jwilson529
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       occidg
 * Domain Path:       /languages
 *
 * @wordpress-plugin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Define plugin constants.
 *
 * Defines the current version and product slug for the plugin.
 *
 * @since 1.0.0
 */
define( 'OCCIDG_VERSION', '1.2.2' );
define( 'OCCIDG_PRODUCT_SLUG', 'oneclickcontent-image-meta-generator' );
define( 'OCCIDG_PLUGIN_FILE', __FILE__ );
define( 'OCCIDG_LOG_FILE', plugin_dir_path( __FILE__ ) . 'plugin-error.log' );

require_once plugin_dir_path( __FILE__ ) . 'includes/class-occidg-i18n.php';

/**
 * The code that runs during plugin activation.
 *
 * This action is documented in includes/class-occidg-activator.php.
 *
 * @since 1.0.0
 */
function occidg_activate() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-occidg-logger.php';
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-occidg-activator.php';
	Occidg_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 *
 * This action is documented in includes/class-occidg-deactivator.php.
 *
 * @since 1.0.0
 */
function occidg_deactivate() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-occidg-logger.php';
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-occidg-deactivator.php';
	Occidg_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'occidg_activate' );
register_deactivation_hook( __FILE__, 'occidg_deactivate' );

/**
 * Load the plugin text domain.
 *
 * @since 1.0.0
 * @return void
 */
function occidg_load_textdomain() {
	$plugin_i18n = new Occidg_I18n();
	$plugin_i18n->load_plugin_textdomain();
}

add_action( 'plugins_loaded', 'occidg_load_textdomain' );

/**
 * The core plugin class that defines internationalization, admin-specific hooks,
 * and public-facing site hooks.
 *
 * @since 1.0.0
 */
require_once plugin_dir_path( __FILE__ ) . 'includes/class-occidg.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks, kicking off the plugin
 * from this point does not affect the page life cycle.
 *
 * @since 1.0.0
 */
function occidg_run() {
	$plugin = new Occidg();
	$plugin->run();
}

occidg_run();
