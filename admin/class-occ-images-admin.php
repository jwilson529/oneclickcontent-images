<?php
/**
 * Admin-specific functionality of the plugin.
 *
 * This class defines all the admin-specific hooks and functions that handle
 * enqueueing admin styles, scripts, and adding custom functionality to the Media Library.
 *
 * @link       https://oneclickcontent.com
 * @since      1.0.0
 * @package    Occ_Images
 * @subpackage Occ_Images/admin
 */

/**
 * Class Occ_Images_Admin
 *
 * Admin-specific functionality of the OCC Images plugin.
 *
 * @since 1.0.0
 */
class Occ_Images_Admin {

	/**
	 * The name of the plugin.
	 *
	 * @var string
	 */
	private $plugin_name;

	/**
	 * The version of the plugin.
	 *
	 * @var string
	 */
	private $version;

	/**
	 * Constructor for the admin class.
	 *
	 * @param string $plugin_name The name of the plugin.
	 * @param string $version     The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;
	}

	/**
	 * Enqueue the admin-specific stylesheets for the plugin.
	 *
	 * This method ensures the admin area has the necessary CSS files
	 * loaded for any custom styles used by the plugin.
	 */
	public function enqueue_styles() {
		wp_enqueue_style(
			$this->plugin_name,
			plugin_dir_url( __FILE__ ) . 'css/oneclickcontent-images-admin.css',
			array(),
			$this->version,
			'all'
		);
	}

	/**
	 * Enqueue the admin-specific JavaScript files for the plugin.
	 *
	 * This method ensures the admin area has the necessary JavaScript files loaded,
	 * including any necessary libraries like jQuery and the WordPress media library.
	 */
	public function enqueue_scripts() {
		// Enqueue the plugin's admin JavaScript file.
		wp_enqueue_script(
			$this->plugin_name,
			plugin_dir_url( __FILE__ ) . 'js/oneclickcontent-images-admin.js',
			array( 'jquery' ),
			$this->version,
			true
		);

		// Enqueue the WordPress media library.
		wp_enqueue_media();

		// Localize the script to pass dynamic data to the JavaScript file.
		wp_localize_script(
			$this->plugin_name,
			'occ_images_admin_vars',
			array(
				'ajax_url'              => admin_url( 'admin-ajax.php' ),
				'occ_images_ajax_nonce' => wp_create_nonce( 'occ_images_ajax_nonce' ),
			)
		);
	}

	/**
	 * Add a custom "Generate Metadata" button to the Media Library's attachment details.
	 *
	 * This method modifies the Media Library form to include a button that allows
	 * users to generate metadata for the selected image.
	 *
	 * @param array   $form_fields An array of attachment form fields.
	 * @param WP_Post $post        The WP_Post attachment object.
	 * @return array Modified form fields including the custom button.
	 */
	public function add_generate_metadata_button( $form_fields, $post ) {
		// Add a custom "Generate Metadata" button to the Media Library.
		$form_fields['generate_metadata'] = array(
			'label' => __( 'Generate Metadata', 'oneclickcontent-images' ),
			'input' => 'html',
			'html'  => '<button type="button" class="button" id="generate_metadata_button" data-image-id="' . esc_attr( $post->ID ) . '">' . esc_html__( 'Generate Metadata', 'oneclickcontent-images' ) . '</button>',
		);

		return $form_fields;
	}
}
