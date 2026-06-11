<?php
/**
 * Datenzugriff für Vereine.
 *
 * @package Seebuchung
 */

namespace Seebuchung\Database;

/**
 * Lookup auf seebuchung_vereine.
 */
final class VereinsRepository {

	/**
	 * Aktiven Verein über die Vereins-Nr. finden.
	 *
	 * @param string $nummer Vereins-Nr. (wie vom Taucher eingegeben).
	 * @return array<string, mixed>|null
	 */
	public function per_nummer( string $nummer ): ?array {
		global $wpdb;
		$tabelle = Schema::table( 'vereine' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Eigene Tabelle.
		$zeile = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$tabelle} WHERE nummer = %s AND aktiv = 1", $nummer ), ARRAY_A );
		return null === $zeile ? null : $zeile;
	}
}
