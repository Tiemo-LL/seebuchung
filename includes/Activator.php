<?php
/**
 * Aktivierungslogik.
 *
 * @package Seebuchung
 */

namespace Seebuchung;

/**
 * Wird bei Plugin-Aktivierung ausgeführt (Tabellen, Rollen, Default-Settings).
 */
final class Activator {

	/**
	 * Aktivierungs-Hook.
	 */
	public static function activate(): void {
		// Phase 1: Tabellen via dbDelta anlegen, Rollen registrieren, Schema-Version setzen.
	}
}
