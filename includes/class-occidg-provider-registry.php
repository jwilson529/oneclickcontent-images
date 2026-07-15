<?php
/**
 * Extensible provider registry.
 *
 * @package Occidg
 */

defined( 'ABSPATH' ) || exit;

/** Resolves provider implementations for the generation workflow. */
class Occidg_Provider_Registry {
	/**
	 * Registered provider instances.
	 *
	 * @var array
	 */
	private $providers = array();

	/**
	 * Register a provider implementation.
	 *
	 * @param string                     $slug     Provider slug.
	 * @param OCC_IDG_Provider_Interface $provider Provider instance.
	 * @return Occidg_Provider_Registry Registry instance.
	 */
	public function register( $slug, $provider ) {
		if ( $provider instanceof OCC_IDG_Provider_Interface ) {
			$this->providers[ $this->sanitize_slug( $slug ) ] = $provider;
		}
		return $this;
	}

	/**
	 * Resolve a provider by slug.
	 *
	 * @param string $slug Provider slug.
	 * @return OCC_IDG_Provider_Interface|false Provider instance or false.
	 */
	public function get( $slug ) {
		$slug      = $this->sanitize_slug( $slug );
		$providers = apply_filters( 'occ_idg_providers', $this->providers );
		return isset( $providers[ $slug ] ) && $providers[ $slug ] instanceof OCC_IDG_Provider_Interface ? $providers[ $slug ] : false;
	}

	/**
	 * Return all registered providers.
	 *
	 * @return array All registered providers.
	 */
	public function all() {
		return apply_filters( 'occ_idg_providers', $this->providers );
	}

	/**
	 * Normalize a provider slug.
	 *
	 * @param string $slug Candidate slug.
	 * @return string Normalized slug.
	 */
	private function sanitize_slug( $slug ) {
		return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', (string) $slug ) );
	}
}
