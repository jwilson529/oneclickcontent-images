<?php
/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link       https://oneclickcontent.com
 * @since      1.0.0
 *
 * @package    OneClickContent_Images
 * @subpackage OneClickContent_Images
 */

/**
 * Plugin Name:       OneClickContent - Image Detail Generator
 * Plugin URI:        https://oneclickcontent.com
 * Description:       Boost images with OneClickContent AI: auto titles, descs, captions & alt text!
 * Version:           1.1.9
 * Author:            James Wilson
 * Author URI:        https://oneclickcontent.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       oneclickcontent-image-detail-generator
 * Domain Path:       /languages
 *
 * @wordpress-plugin
 */

/**
 * Prevent direct access to this file.
 *
 * @since 1.0.0
 */
if ( ! defined( 'WPINC' ) ) {
	die( 'No direct access permitted.' );
}

/**
 * Define plugin constants.
 *
 * Defines the current version and product slug for the plugin.
 *
 * @since 1.0.0
 */
define( 'OCC_IMAGES_VERSION', '1.1.9' );
define( 'OCC_IMAGES_PRODUCT_SLUG', 'oneclickcontent-image-meta-generator' );

/**
 * The code that runs during plugin activation.
 *
 * This action is documented in includes/class-oneclickcontent-images-activator.php.
 *
 * @since 1.0.0
 */
function oneclick_images_activate() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-oneclickcontent-images-activator.php';
	OneClickContent_Images_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 *
 * This action is documented in includes/class-oneclickcontent-images-deactivator.php.
 *
 * @since 1.0.0
 */
function oneclick_images_deactivate() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-oneclickcontent-images-deactivator.php';
	OneClickContent_Images_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'oneclick_images_activate' );
register_deactivation_hook( __FILE__, 'oneclick_images_deactivate' );

/**
 * The core plugin class that defines internationalization, admin-specific hooks,
 * and public-facing site hooks.
 *
 * @since 1.0.0
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-oneclickcontent-images.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks, kicking off the plugin
 * from this point does not affect the page life cycle.
 *
 * @since 1.0.0
 */
function run_oneclick_images() {
	$plugin = new OneClickContent_Images();
	$plugin->run();
}
run_oneclick_images();
