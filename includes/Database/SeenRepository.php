<?php
/**
 * Datenzugriff für Seen.
 *
 * @package Seebuchung
 */

namespace Seebuchung\Database;

use Seebuchung\Domain\See;

/**
 * Listen-Zugriffe auf seebuchung_seen.
 */
final class SeenRepository {

	/**
	 * Alle aktiven Seen, alphabetisch.
	 *
	 * @return See[]
	 */
	public function aktive(): array {
		global $wpdb;
		$tabelle = Schema::table( 'seen' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL -- Eigene Tabelle, keine Parameter.
		$zeilen = (array) $wpdb->get_results( "SELECT * FROM {$tabelle} WHERE aktiv = 1 ORDER BY name", ARRAY_A );
		return array_map( array( See::class, 'from_row' ), $zeilen );
	}
}
