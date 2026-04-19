<?php
/**
 * PHPUnit bootstrap file.
 *
 * @package Occidg
 */

$occidg_tests_dir = getenv( 'WP_TESTS_DIR' );

defined( 'ABSPATH' ) || $occidg_tests_dir || define( 'ABSPATH', dirname( __DIR__ ) . '/' );
defined( 'ABSPATH' ) || $occidg_tests_dir || exit;

$_tests_dir = $occidg_tests_dir;

if ( ! $_tests_dir ) {
	$_tests_dir = dirname( __DIR__ ) . '/.wp-tests';
}

if ( $_tests_dir && ! defined( 'WP_TESTS_DIR' ) ) {
	define( 'WP_TESTS_DIR', $_tests_dir );
}

if ( defined( 'WP_TESTS_DIR' ) && is_dir( WP_TESTS_DIR ) ) {
	require_once WP_TESTS_DIR . '/includes/functions.php';

	/**
	 * Load the plugin for integration tests.
	 *
	 * @return void
	 */
	function occidg_load_plugin_for_wp_tests() {
		require dirname( __DIR__ ) . '/occidg.php';
	}

	tests_add_filter( 'muplugins_loaded', 'occidg_load_plugin_for_wp_tests' );

	require_once WP_TESTS_DIR . '/includes/bootstrap.php';
	return;
}

$GLOBALS['occidg_registered_actions'] = array();
$GLOBALS['occidg_registered_filters'] = array();
$GLOBALS['occidg_runtime_filters']    = array();
$GLOBALS['occidg_loaded_textdomain']  = array();
$GLOBALS['occidg_options']            = array();
$GLOBALS['occidg_transients']         = array();
$GLOBALS['occidg_scheduled_events']   = array();
$GLOBALS['occidg_activation_hooks']   = array();
$GLOBALS['occidg_deactivation_hooks'] = array();

if ( ! function_exists( 'add_action' ) ) {
	/**
	 * Test double for add_action().
	 *
	 * @param string   $hook          Hook name.
	 * @param callable $callback      Callback to register.
	 * @param int      $priority      Hook priority.
	 * @param int      $accepted_args Accepted arguments.
	 * @return true
	 */
	function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		$GLOBALS['occidg_registered_actions'][] = array(
			'hook'          => $hook,
			'callback'      => $callback,
			'priority'      => $priority,
			'accepted_args' => $accepted_args,
		);

		return true;
	}
}

if ( ! function_exists( 'register_activation_hook' ) ) {
	/**
	 * Test double for register_activation_hook().
	 *
	 * @param string   $file     Plugin file path.
	 * @param callable $callback Activation callback.
	 * @return true
	 */
	function register_activation_hook( $file, $callback ) {
		$GLOBALS['occidg_activation_hooks'][] = array(
			'file'     => $file,
			'callback' => $callback,
		);

		return true;
	}
}

if ( ! function_exists( 'register_deactivation_hook' ) ) {
	/**
	 * Test double for register_deactivation_hook().
	 *
	 * @param string   $file     Plugin file path.
	 * @param callable $callback Deactivation callback.
	 * @return true
	 */
	function register_deactivation_hook( $file, $callback ) {
		$GLOBALS['occidg_deactivation_hooks'][] = array(
			'file'     => $file,
			'callback' => $callback,
		);

		return true;
	}
}

if ( ! function_exists( 'add_filter' ) ) {
	/**
	 * Test double for add_filter().
	 *
	 * @param string   $hook          Hook name.
	 * @param callable $callback      Callback to register.
	 * @param int      $priority      Hook priority.
	 * @param int      $accepted_args Accepted arguments.
	 * @return true
	 */
	function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		$GLOBALS['occidg_registered_filters'][] = array(
			'hook'          => $hook,
			'callback'      => $callback,
			'priority'      => $priority,
			'accepted_args' => $accepted_args,
		);

		if ( ! isset( $GLOBALS['occidg_runtime_filters'][ $hook ] ) ) {
			$GLOBALS['occidg_runtime_filters'][ $hook ] = array();
		}

		$GLOBALS['occidg_runtime_filters'][ $hook ][] = array(
			'callback'      => $callback,
			'priority'      => $priority,
			'accepted_args' => $accepted_args,
		);

		return true;
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	/**
	 * Test double for apply_filters().
	 *
	 * @param string $hook  Hook name.
	 * @param mixed  $value Filtered value.
	 * @return mixed
	 */
	function apply_filters( $hook, $value ) {
		if ( empty( $GLOBALS['occidg_runtime_filters'][ $hook ] ) ) {
			return $value;
		}

		usort(
			$GLOBALS['occidg_runtime_filters'][ $hook ],
			static function ( $left, $right ) {
				return $left['priority'] <=> $right['priority'];
			}
		);

		foreach ( $GLOBALS['occidg_runtime_filters'][ $hook ] as $filter ) {
			$value = call_user_func( $filter['callback'], $value );
		}

		return $value;
	}
}

if ( ! function_exists( 'load_plugin_textdomain' ) ) {
	/**
	 * Test double for load_plugin_textdomain().
	 *
	 * @param string $domain           Text domain.
	 * @param bool   $deprecated       Deprecated argument.
	 * @param string $plugin_rel_path  Languages path.
	 * @return true
	 */
	function load_plugin_textdomain( $domain, $deprecated = false, $plugin_rel_path = '' ) {
		$GLOBALS['occidg_loaded_textdomain'] = array(
			'domain'          => $domain,
			'deprecated'      => $deprecated,
			'plugin_rel_path' => $plugin_rel_path,
		);

		return true;
	}
}

if ( ! function_exists( 'plugin_basename' ) ) {
	/**
	 * Test double for plugin_basename().
	 *
	 * @param string $file Plugin file path.
	 * @return string
	 */
	function plugin_basename( $file ) {
		return basename( dirname( $file ) ) . '/' . basename( $file );
	}
}

if ( ! function_exists( 'plugin_dir_path' ) ) {
	/**
	 * Test double for plugin_dir_path().
	 *
	 * @param string $file File path.
	 * @return string
	 */
	function plugin_dir_path( $file ) {
		return trailingslashit( dirname( $file ) );
	}
}

if ( ! function_exists( 'trailingslashit' ) ) {
	/**
	 * Test double for trailingslashit().
	 *
	 * @param string $value Value to normalize.
	 * @return string
	 */
	function trailingslashit( $value ) {
		return rtrim( (string) $value, '/\\' ) . '/';
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	/**
	 * Test double for sanitize_text_field().
	 *
	 * @param mixed $value Value to sanitize.
	 * @return string
	 */
	function sanitize_text_field( $value ) {
		return is_scalar( $value ) ? trim( (string) $value ) : '';
	}
}

if ( ! function_exists( 'sanitize_textarea_field' ) ) {
	/**
	 * Test double for sanitize_textarea_field().
	 *
	 * @param mixed $value Value to sanitize.
	 * @return string
	 */
	function sanitize_textarea_field( $value ) {
		return is_scalar( $value ) ? trim( (string) $value ) : '';
	}
}

if ( ! function_exists( '__' ) ) {
	/**
	 * Test double for translation helper __().
	 *
	 * @param string $text   Text to translate.
	 * @param string $domain Text domain.
	 * @return string
	 */
	function __( $text, $domain = '' ) {
		unset( $domain );

		return (string) $text;
	}
}

if ( ! function_exists( 'wp_unslash' ) ) {
	/**
	 * Test double for wp_unslash().
	 *
	 * @param mixed $value Value to unslash.
	 * @return mixed
	 */
	function wp_unslash( $value ) {
		if ( is_array( $value ) ) {
			return array_map( 'wp_unslash', $value );
		}

		if ( is_scalar( $value ) ) {
			return str_replace( array( '\\"', "\\'", '\\\\' ), array( '"', '\'', '\\' ), (string) $value );
		}

		return $value;
	}
}

if ( ! function_exists( 'esc_url_raw' ) ) {
	/**
	 * Test double for esc_url_raw().
	 *
	 * @param string $value Value to sanitize.
	 * @return string
	 */
	function esc_url_raw( $value ) {
		return is_string( $value ) ? $value : '';
	}
}

if ( ! function_exists( 'wp_mkdir_p' ) ) {
	/**
	 * Test double for wp_mkdir_p().
	 *
	 * @param string $target Directory path.
	 * @return bool
	 */
	function wp_mkdir_p( $target ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir -- Test harness fallback when WordPress is not loaded.
		return is_dir( $target ) || mkdir( $target, 0755, true );
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	/**
	 * Test double for is_wp_error().
	 *
	 * @param mixed $thing Thing to test.
	 * @return bool
	 */
	function is_wp_error( $thing ) {
		return is_object( $thing ) && class_exists( 'WP_Error', false ) && $thing instanceof WP_Error;
	}
}

if ( ! function_exists( 'wp_remote_retrieve_response_code' ) ) {
	/**
	 * Test double for wp_remote_retrieve_response_code().
	 *
	 * @param mixed $response A response value.
	 * @return int
	 */
	function wp_remote_retrieve_response_code( $response ) {
		if ( is_array( $response ) && isset( $response['response']['code'] ) ) {
			return (int) $response['response']['code'];
		}

		if ( is_array( $response ) && isset( $response['status_code'] ) ) {
			return (int) $response['status_code'];
		}

		return 0;
	}
}

if ( ! function_exists( 'wp_remote_retrieve_body' ) ) {
	/**
	 * Test double for wp_remote_retrieve_body().
	 *
	 * @param mixed $response A response value.
	 * @return string
	 */
	function wp_remote_retrieve_body( $response ) {
		return isset( $response['body'] ) && is_string( $response['body'] ) ? $response['body'] : '';
	}
}

if ( ! function_exists( 'set_transient' ) ) {
	/**
	 * Test double for set_transient().
	 *
	 * @param string $transient  Transient key.
	 * @param mixed  $value      Stored value.
	 * @param int    $expiration Expiration in seconds.
	 * @return bool
	 */
	function set_transient( $transient, $value, $expiration = 0 ) {
		$GLOBALS['occidg_transients'][ $transient ] = array(
			'value'      => $value,
			'expiration' => (int) $expiration,
		);

		return true;
	}
}

if ( ! function_exists( 'get_transient' ) ) {
	/**
	 * Test double for get_transient().
	 *
	 * @param string $transient Transient key.
	 * @return mixed
	 */
	function get_transient( $transient ) {
		if ( ! isset( $GLOBALS['occidg_transients'][ $transient ] ) ) {
			return false;
		}

		return $GLOBALS['occidg_transients'][ $transient ]['value'];
	}
}

if ( ! function_exists( 'delete_transient' ) ) {
	/**
	 * Test double for delete_transient().
	 *
	 * @param string $transient Transient key.
	 * @return bool
	 */
	function delete_transient( $transient ) {
		if ( ! isset( $GLOBALS['occidg_transients'][ $transient ] ) ) {
			return false;
		}

		unset( $GLOBALS['occidg_transients'][ $transient ] );

		return true;
	}
}

if ( ! function_exists( 'wp_schedule_single_event' ) ) {
	/**
	 * Test double for wp_schedule_single_event().
	 *
	 * @param int    $timestamp Event timestamp.
	 * @param string $hook      Hook name.
	 * @param array  $args      Event arguments.
	 * @return bool
	 */
	function wp_schedule_single_event( $timestamp, $hook, $args = array() ) {
		$GLOBALS['occidg_scheduled_events'][] = array(
			'timestamp' => (int) $timestamp,
			'hook'      => (string) $hook,
			'args'      => is_array( $args ) ? $args : array(),
		);

		return true;
	}
}

if ( ! function_exists( 'wp_next_scheduled' ) ) {
	/**
	 * Test double for wp_next_scheduled().
	 *
	 * @param string $hook Hook name.
	 * @param array  $args Hook args.
	 * @return int|false
	 */
	function wp_next_scheduled( $hook, $args = array() ) {
		foreach ( $GLOBALS['occidg_scheduled_events'] as $event ) {
			if ( $hook === $event['hook'] && (array) $args === $event['args'] ) {
				return $event['timestamp'];
			}
		}

		return false;
	}
}

if ( ! function_exists( 'wp_unschedule_event' ) ) {
	/**
	 * Test double for wp_unschedule_event().
	 *
	 * @param int    $timestamp Event timestamp.
	 * @param string $hook      Hook name.
	 * @param array  $args      Hook args.
	 * @return bool
	 */
	function wp_unschedule_event( $timestamp, $hook, $args = array() ) {
		foreach ( $GLOBALS['occidg_scheduled_events'] as $index => $event ) {
			if ( (int) $timestamp === (int) $event['timestamp'] && $hook === $event['hook'] && (array) $args === $event['args'] ) {
				unset( $GLOBALS['occidg_scheduled_events'][ $index ] );
				$GLOBALS['occidg_scheduled_events'] = array_values( $GLOBALS['occidg_scheduled_events'] );
				return true;
			}
		}

		return false;
	}
}

if ( ! function_exists( 'add_option' ) ) {
	/**
	 * Test double for add_option().
	 *
	 * @param string $option Option name.
	 * @param mixed  $value  Option value.
	 * @return bool
	 */
	function add_option( $option, $value = '' ) {
		if ( isset( $GLOBALS['occidg_options'][ $option ] ) ) {
			return false;
		}

		$GLOBALS['occidg_options'][ $option ] = $value;

		return true;
	}
}

if ( ! function_exists( 'get_option' ) ) {
	/**
	 * Test double for get_option().
	 *
	 * @param string $option  Option name.
	 * @param mixed  $default_value Default value.
	 * @return mixed
	 */
	function get_option( $option, $default_value = false ) {
		if ( isset( $GLOBALS['occidg_options'][ $option ] ) ) {
			return $GLOBALS['occidg_options'][ $option ];
		}

		return $default_value;
	}
}

if ( ! function_exists( 'update_option' ) ) {
	/**
	 * Test double for update_option().
	 *
	 * @param string $option Option name.
	 * @param mixed  $value  Option value.
	 * @return bool
	 */
	function update_option( $option, $value ) {
		$GLOBALS['occidg_options'][ $option ] = $value;

		return true;
	}
}

if ( ! function_exists( 'delete_option' ) ) {
	/**
	 * Test double for delete_option().
	 *
	 * @param string $option Option name.
	 * @return bool
	 */
	function delete_option( $option ) {
		if ( ! isset( $GLOBALS['occidg_options'][ $option ] ) ) {
			return false;
		}

		unset( $GLOBALS['occidg_options'][ $option ] );

		return true;
	}
}

require_once dirname( __DIR__ ) . '/includes/class-occidg-loader.php';
require_once dirname( __DIR__ ) . '/includes/class-occidg-background-jobs.php';
require_once dirname( __DIR__ ) . '/includes/class-occidg-background-worker.php';
require_once dirname( __DIR__ ) . '/includes/class-occidg-i18n.php';
require_once dirname( __DIR__ ) . '/includes/class-occidg-logger.php';
require_once dirname( __DIR__ ) . '/admin/class-occidg-background-jobs-admin.php';
