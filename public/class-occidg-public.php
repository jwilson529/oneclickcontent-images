<?php
/**
 * Public-facing functionality for the plugin.
 *
 * @package    Occidg
 * @subpackage Occidg/public
 */

defined( 'ABSPATH' ) || exit;

/**
 * Defines public-facing hooks for the plugin.
 */
class Occidg_Public {

	/**
	 * Plugin slug.
	 *
	 * @var string
	 */
	private $plugin_name;

	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	private $version;

	/**
	 * Constructor.
	 *
	 * @param string $plugin_name Plugin slug.
	 * @param string $version     Plugin version.
	 */
	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;
	}

	/**
	 * Enqueue public styles when a stylesheet exists.
	 *
	 * @return void
	 */
	public function enqueue_styles() {
		$stylesheet = plugin_dir_path( __FILE__ ) . 'css/occidg-public.css';

		if ( ! file_exists( $stylesheet ) ) {
			return;
		}

		wp_enqueue_style(
			$this->plugin_name . '-public',
			plugin_dir_url( __FILE__ ) . 'css/occidg-public.css',
			array(),
			$this->version
		);
	}

	/**
	 * Enqueue public scripts when a script exists.
	 *
	 * @return void
	 */
	public function enqueue_scripts() {
		$script = plugin_dir_path( __FILE__ ) . 'js/occidg-public.js';

		if ( ! file_exists( $script ) ) {
			return;
		}

		wp_enqueue_script(
			$this->plugin_name . '-public',
			plugin_dir_url( __FILE__ ) . 'js/occidg-public.js',
			array( 'jquery' ),
			$this->version,
			true
		);
	}
}
