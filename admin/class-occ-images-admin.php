<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://oneclickcontent.com
 * @since      1.0.0
 *
 * @package    Occ_Images
 * @subpackage Occ_Images/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Occ_Images
 * @subpackage Occ_Images/admin
 * @author     James Wilson <james@oneclickcontent.com>
 */
class Occ_Images_Admin {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $occ_images    The ID of this plugin.
	 */
	private $occ_images;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string $occ_images       The name of this plugin.
	 * @param      string $version    The version of this plugin.
	 */
	public function __construct( $occ_images, $version ) {

		$this->occ_images = $occ_images;
		$this->version    = $version;
	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Occ_Images_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Occ_Images_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_style( $this->occ_images, plugin_dir_url( __FILE__ ) . 'css/occ-images-admin.css', array(), $this->version, 'all' );
	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Occ_Images_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Occ_Images_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_script( $this->occ_images, plugin_dir_url( __FILE__ ) . 'js/occ-images-admin.js', array( 'jquery' ), $this->version, false );
	}
}
