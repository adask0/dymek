<?php
/**
 * Removes the answers table and the settings, unless the user chose to keep them.
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$dymek_settings = (array) get_option( 'dymek_settings', array() );

if ( 'yes' !== ( $dymek_settings['keep_data'] ?? 'no' ) ) {
	global $wpdb;

	$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . 'dymek_nodes' ) );

	delete_option( 'dymek_settings' );
	delete_option( 'dymek_db_version' );
	delete_transient( 'dymek_tree' );
}
