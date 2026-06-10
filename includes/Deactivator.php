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
		// Phase 1: geplante Cron-Events (Verfall, Anonymisierung, Wochenbericht) entfernen.
	}
}
