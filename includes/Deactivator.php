<?php
/**
 * Deaktivierungslogik.
 *
 * @package Seebuchung
 */

namespace Seebuchung;

/**
 * Wird bei Plugin-Deaktivierung ausgeführt (Cron-Events aufräumen).
 *
 * Daten und Rollen bleiben erhalten — endgültiges Aufräumen gehört in uninstall.php.
 */
final class Deactivator {

	/**
	 * Deaktivierungs-Hook.
	 */
	public static function deactivate(): void {
		wp_clear_scheduled_hook( 'seebuchung_verfall' );
		wp_clear_scheduled_hook( 'seebuchung_taeglich' );
	}
}
