<?php
/**
 * Plugin Name:       Dymek – FAQ Chat
 * Description:       A lightweight FAQ chat with ready-made answer paths. Runs entirely on your site: no account, no external services, no tracking.
 * Version:           0.1.0
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Author:            Adam Trojecki
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       dymek
 * Domain Path:       /languages
 */

defined( 'ABSPATH' ) || exit;

define( 'DYMEK_VERSION', '0.1.0' );
define( 'DYMEK_FILE', __FILE__ );
define( 'DYMEK_DIR', plugin_dir_path( __FILE__ ) );
define( 'DYMEK_URL', plugin_dir_url( __FILE__ ) );

require_once DYMEK_DIR . 'includes/class-schema.php';
require_once DYMEK_DIR . 'includes/class-nodes.php';
require_once DYMEK_DIR . 'includes/class-csv.php';
require_once DYMEK_DIR . 'includes/class-settings.php';
require_once DYMEK_DIR . 'includes/class-rest.php';
require_once DYMEK_DIR . 'includes/class-widget.php';
require_once DYMEK_DIR . 'includes/class-admin.php';
require_once DYMEK_DIR . 'includes/class-import.php';

register_activation_hook( __FILE__, array( Dymek\Schema::class, 'install' ) );

add_action(
	'init',
	static function () {
		load_plugin_textdomain( 'dymek', false, dirname( plugin_basename( DYMEK_FILE ) ) . '/languages' );
	}
);

add_action(
	'plugins_loaded',
	static function () {
		Dymek\Schema::maybe_upgrade();
		Dymek\Rest::init();
		Dymek\Widget::init();

		if ( is_admin() ) {
			Dymek\Settings::init();
			Dymek\Admin::init();
			Dymek\Import::init();
		}
	}
);
