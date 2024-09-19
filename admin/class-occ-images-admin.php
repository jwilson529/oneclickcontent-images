<?php
/**
 * Admin-specific functionality of the plugin.
 *
 * @link       https://oneclickcontent.com
 * @since      1.0.0
 *
 * @package    Occ_Images
 * @subpackage Occ_Images/admin
 */

/**
 * Admin-specific functionality of the plugin.
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
     * Constructor.
     *
     * @param string $plugin_name The name of the plugin.
     * @param string $version     The version of this plugin.
     */
    public function __construct( $plugin_name, $version ) {
        $this->plugin_name = $plugin_name;
        $this->version     = $version;
    }

    /**
     * Enqueue admin styles.
     */
    public function enqueue_styles() {
        wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/occ-images-admin.css', array(), $this->version, 'all' );
    }

    /**
     * Enqueue admin scripts.
     */
    public function enqueue_scripts() {
        wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/occ-images-admin.js', array( 'jquery' ), $this->version, true );

        // Localize the script with necessary data.
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
     * Add a "Generate Metadata" button to the attachment details in the Media Library.
     *
     * @param array   $form_fields An array of attachment form fields.
     * @param WP_Post $post        The WP_Post attachment object.
     * @return array Modified form fields.
     */
    public function add_generate_metadata_button( $form_fields, $post ) {
        // Add our custom button.
        $form_fields['generate_metadata'] = array(
            'label' => __( 'Generate Metadata', 'occ-images' ),
            'input' => 'html',
            'html'  => '<button type="button" class="button" id="generate_metadata_button" data-image-id="' . esc_attr( $post->ID ) . '">' . esc_html__( 'Generate Metadata', 'occ-images' ) . '</button>',
        );

        return $form_fields;
    }

}
