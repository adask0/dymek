<?php
namespace Dymek;

defined( 'ABSPATH' ) || exit;

/**
 * Creates and upgrades the plugin tables.
 */
final class Schema {

	private const VERSION = 1;
	private const OPTION  = 'dymek_db_version';

	public static function maybe_upgrade(): void {
		if ( (int) get_option( self::OPTION ) < self::VERSION ) {
			self::install();
		}
	}

	public static function install(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table   = Nodes::table();
		$charset = $wpdb->get_charset_collate();

		dbDelta(
			"CREATE TABLE {$table} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				slug varchar(100) NOT NULL,
				parent_id bigint(20) unsigned NOT NULL DEFAULT 0,
				type varchar(20) NOT NULL,
				label varchar(255) NOT NULL,
				content longtext NOT NULL,
				keywords text NOT NULL,
				url text NOT NULL,
				sort int(11) NOT NULL DEFAULT 0,
				PRIMARY KEY  (id),
				UNIQUE KEY slug (slug),
				KEY parent_id (parent_id)
			) {$charset};"
		);

		update_option( self::OPTION, self::VERSION, false );
	}
}
