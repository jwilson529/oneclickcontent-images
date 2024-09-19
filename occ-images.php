<?php
/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://oneclickcontent.com
 * @since             1.0.0
 * @package           Occ_Images
 *
 * @wordpress-plugin
 * Plugin Name:       occ-images
 * Plugin URI:        https://oneclickcontent.com
 * Description:       Uploads images to OpenAI in order to auto generate titles, descriptions, captions and alt automatically.
 * Version:           1.0.0
 * Author:            James Wilson
 * Author URI:        https://oneclickcontent.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       occ-images
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'OCC_IMAGES_VERSION', '1.0.0' );

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-occ-images-activator.php
 */
function occ_images_activate() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-occ-images-activator.php';
	Occ_Images_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-occ-images-deactivator.php
 */
function occ_images_deactivate() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-occ-images-deactivator.php';
	Occ_Images_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'occ_images_activate' );
register_deactivation_hook( __FILE__, 'occ_images_deactivate' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-occ-images.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_occ_images() {

	$plugin = new Occ_Images();
	$plugin->run();
}
run_occ_images();
