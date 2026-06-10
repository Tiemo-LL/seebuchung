<?php
/**
 * Plugin Name:       Seebuchung
 * Description:       Buchung von Tageskontingenten an verwalteten Tauchseen — Doppel-Opt-in, PayPal-Zahlung, QR-Bestätigung und Blockaden-Self-Service für Landesverbände.
 * Version:           0.1.0
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Author:            Landesverband Sporttauchen Rheinland-Pfalz e.V.
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       seebuchung
 * Domain Path:       /languages
 *
 * @package Seebuchung
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SEEBUCHUNG_VERSION', '0.1.0' );
define( 'SEEBUCHUNG_PLUGIN_FILE', __FILE__ );
define( 'SEEBUCHUNG_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SEEBUCHUNG_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Composer-Autoloader (PSR-4 für includes/).
if ( file_exists( SEEBUCHUNG_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
	require_once SEEBUCHUNG_PLUGIN_DIR . 'vendor/autoload.php';
}

register_activation_hook( __FILE__, array( \Seebuchung\Activator::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( \Seebuchung\Deactivator::class, 'deactivate' ) );

add_action(
	'plugins_loaded',
	static function () {
		\Seebuchung\Plugin::instance()->init();
	}
);
