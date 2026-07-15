<?php
/**
 * Plugin role capabilities.
 *
 * @package Occidg
 */

defined( 'ABSPATH' ) || exit;

/** Installs and removes OCCIDG capabilities. */
class Occidg_Capabilities {
	const ALL = array(
		'occ_idg_view_dashboard',
		'occ_idg_generate_metadata',
		'occ_idg_review_suggestions',
		'occ_idg_overwrite_metadata',
		'occ_idg_restore_metadata',
		'occ_idg_manage_batches',
		'occ_idg_manage_settings',
		'occ_idg_export_reports',
	);

	/** Add plugin capabilities to configured roles. */
	public static function install() {
		$administrator = get_role( 'administrator' );
		if ( $administrator ) {
			foreach ( self::ALL as $capability ) {
				$administrator->add_cap( $capability );
			}
		}
		$editor = get_role( 'editor' );
		if ( $editor && get_option( 'occ_idg_grant_editor_capabilities', false ) ) {
			foreach ( array( 'occ_idg_view_dashboard', 'occ_idg_generate_metadata', 'occ_idg_review_suggestions', 'occ_idg_export_reports' ) as $capability ) {
				$editor->add_cap( $capability );
			}
		}
	}

	/** Remove plugin capabilities from roles. */
	public static function uninstall() {
		foreach ( array( 'administrator', 'editor' ) as $role_name ) {
			$role = get_role( $role_name );
			if ( $role ) {
				foreach ( self::ALL as $capability ) {
					$role->remove_cap( $capability );
				}
			}
		}
	}
}
