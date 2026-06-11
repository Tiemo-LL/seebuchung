<?php
/**
 * Datenzugriff für Brevets.
 *
 * @package Seebuchung
 */

namespace Seebuchung\Database;

/**
 * Listen-Zugriff auf seebuchung_brevets (Formular-Auswahl).
 */
final class BrevetsRepository {

	/**
	 * Aktive Brevets in Pflege-Reihenfolge.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function aktive(): array {
		global $wpdb;
		$tabelle = Schema::table( 'brevets' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL -- Eigene Tabelle, keine Parameter.
		return (array) $wpdb->get_results( "SELECT id, bezeichnung FROM {$tabelle} WHERE aktiv = 1 ORDER BY sortierung, bezeichnung", ARRAY_A );
	}
}
