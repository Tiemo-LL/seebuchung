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
		Database\Schema::install();
		Rollen::registrieren();

		if ( false === wp_next_scheduled( 'seebuchung_verfall' ) ) {
			wp_schedule_event( time(), 'hourly', 'seebuchung_verfall' );
		}
	}
}
