<?php
/**
 * Zentraler Einstiegspunkt des Plugins.
 *
 * @package Seebuchung
 */

namespace Seebuchung;

/**
 * Initialisiert alle Plugin-Komponenten (Hooks, Shortcodes, Admin-Seiten).
 */
final class Plugin {

	/**
	 * Singleton-Instanz.
	 *
	 * @var Plugin|null
	 */
	private static ?Plugin $instance = null;

	/**
	 * Liefert die Singleton-Instanz.
	 */
	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Privater Konstruktor — Instanzierung nur über instance().
	 */
	private function __construct() {
	}

	/**
	 * Registriert Hooks. Wird auf `plugins_loaded` aufgerufen.
	 */
	public function init(): void {
		load_plugin_textdomain( 'seebuchung', false, dirname( plugin_basename( SEEBUCHUNG_PLUGIN_FILE ) ) . '/languages' );

		Database\Schema::maybe_upgrade();

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( 'seebuchung import-alt', Cli\ImportAltCommand::class );
		}

		// Phase 1: Shortcode [seebuchung], Admin-Seiten, Rollen.
	}
}
