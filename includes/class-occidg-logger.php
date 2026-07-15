<?php
/**
 * Logging support for the plugin.
 *
 * @package    Occidg
 * @subpackage Occidg/includes
 */

defined( 'ABSPATH' ) || exit;

/**
 * Provides lightweight file logging for the plugin.
 */
class Occidg_Logger {

	/**
	 * Shared logger instance.
	 *
	 * @var Occidg_Logger|null
	 */
	private static $instance = null;

	/**
	 * Get the shared logger instance.
	 *
	 * @return Occidg_Logger
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Write a debug log entry.
	 *
	 * @param string $message Log message.
	 * @param array  $context Optional context data.
	 * @return bool
	 */
	public static function debug( $message, $context = array() ) {
		$enabled = defined( 'OCC_IDG_DEBUG' ) ? (bool) OCC_IDG_DEBUG : (bool) get_option( 'occ_idg_debug_logging', false );
		if ( ! $enabled ) {
			return false;
		}
		return self::instance()->log( 'debug', $message, $context );
	}

	/**
	 * Write an informational log entry.
	 *
	 * @param string $message Log message.
	 * @param array  $context Optional context data.
	 * @return bool
	 */
	public static function info( $message, $context = array() ) {
		return self::instance()->log( 'info', $message, $context );
	}

	/**
	 * Write a warning log entry.
	 *
	 * @param string $message Log message.
	 * @param array  $context Optional context data.
	 * @return bool
	 */
	public static function warning( $message, $context = array() ) {
		return self::instance()->log( 'warning', $message, $context );
	}

	/**
	 * Write an error log entry.
	 *
	 * @param string $message Log message.
	 * @param array  $context Optional context data.
	 * @return bool
	 */
	public static function error( $message, $context = array() ) {
		return self::instance()->log( 'error', $message, $context );
	}

	/**
	 * Write a log entry to the configured plugin log file.
	 *
	 * @param string $level   Log level.
	 * @param string $message Log message.
	 * @param array  $context Optional context data.
	 * @return bool
	 */
	public function log( $level, $message, $context = array() ) {
		$log_file = $this->get_log_file();
		if ( empty( $log_file ) ) {
			return false;
		}

		$log_directory = dirname( $log_file );
		if ( ! is_dir( $log_directory ) ) {
			if ( function_exists( 'wp_mkdir_p' ) ) {
				$created_directory = wp_mkdir_p( $log_directory );
			} else {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir -- Test harness fallback when WordPress is not loaded.
				$created_directory = mkdir( $log_directory, 0755, true );
			}

			if ( ! $created_directory && ! is_dir( $log_directory ) ) {
				return false;
			}
		}

		$entry = $this->format_entry( $level, $message, $context );

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Dedicated plugin logger writing to an explicit log file.
		return false !== error_log( $entry, 3, $log_file );
	}

	/**
	 * Resolve the current log file path.
	 *
	 * @return string
	 */
	private function get_log_file() {
		$default_path = defined( 'OCCIDG_LOG_FILE' ) ? OCCIDG_LOG_FILE : dirname( __DIR__ ) . '/plugin-error.log';

		if ( function_exists( 'apply_filters' ) ) {
			$default_path = apply_filters( 'occidg_log_file', $default_path );
		}

		return is_string( $default_path ) ? $default_path : '';
	}

	/**
	 * Format a log entry line.
	 *
	 * @param string $level   Log level.
	 * @param string $message Log message.
	 * @param array  $context Optional context data.
	 * @return string
	 */
	private function format_entry( $level, $message, $context ) {
		$entry = sprintf(
			'[%s] [%s] %s',
			gmdate( 'c' ),
			strtoupper( (string) $level ),
			(string) $message
		);

		if ( ! empty( $context ) ) {
			$entry .= ' ' . $this->encode_context( $context );
		}

		return $entry . PHP_EOL;
	}

	/**
	 * Encode structured context data.
	 *
	 * @param array $context Context to encode.
	 * @return string
	 */
	private function encode_context( $context ) {
		$context = $this->redact_context( $context );
		if ( function_exists( 'wp_json_encode' ) ) {
			return (string) wp_json_encode( $context );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- Test/runtime fallback when wp_json_encode() is unavailable.
		return (string) json_encode( $context );
	}

	/**
	 * Recursively redact credentials and authorization data.
	 *
	 * @param array $context Context data.
	 * @return array Redacted context.
	 */
	private function redact_context( $context ) {
		$redacted = array();
		foreach ( (array) $context as $key => $value ) {
			$key_text = strtolower( (string) $key );
			if ( false !== strpos( $key_text, 'api_key' ) || false !== strpos( $key_text, 'authorization' ) || false !== strpos( $key_text, 'credential' ) || 'token' === $key_text ) {
				$redacted[ $key ] = '[redacted]';
			} else {
				$redacted[ $key ] = is_array( $value ) ? $this->redact_context( $value ) : $value;
			}
		}
		return $redacted;
	}
}
