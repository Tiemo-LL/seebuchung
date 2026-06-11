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

	/**
	 * Alle Seen (auch inaktive), alphabetisch — für den Admin.
	 *
	 * @return See[]
	 */
	public function alle(): array {
		global $wpdb;
		$tabelle = Schema::table( 'seen' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL -- Eigene Tabelle, keine Parameter.
		$zeilen = (array) $wpdb->get_results( "SELECT * FROM {$tabelle} ORDER BY name", ARRAY_A );
		return array_map( array( See::class, 'from_row' ), $zeilen );
	}

	/**
	 * See anlegen oder aktualisieren.
	 *
	 * @param array<string, mixed> $daten Spaltenwerte ohne id/Zeitstempel.
	 * @param int                  $id    Vorhandene ID oder 0 für neu.
	 * @return int See-ID.
	 */
	public function speichern( array $daten, int $id = 0 ): int {
		global $wpdb;
		$daten['updated_at'] = current_time( 'mysql' );
		// phpcs:disable WordPress.DB.DirectDatabaseQuery -- Eigene Tabelle.
		if ( $id > 0 ) {
			$wpdb->update( Schema::table( 'seen' ), $daten, array( 'id' => $id ) );
			return $id;
		}
		$daten['created_at'] = $daten['updated_at'];
		$wpdb->insert( Schema::table( 'seen' ), $daten );
		// phpcs:enable
		return (int) $wpdb->insert_id;
	}

	/**
	 * Kontingent-Zeilen eines Sees (für die Matrix).
	 *
	 * @param int $see_id See-ID.
	 * @return array<int, array<string, string|null>>
	 */
	public function kontingente_fuer( int $see_id ): array {
		global $wpdb;
		$tabelle = Schema::table( 'kontingente' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Eigene Tabelle.
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT wochentag, stunde, max_taucher FROM {$tabelle} WHERE see_id = %d", $see_id ), ARRAY_A );
	}

	/**
	 * Kontingente eines Sees komplett ersetzen.
	 *
	 * @param int                                 $see_id See-ID.
	 * @param array<int, array<string, int|null>> $zeilen Zeilen mit wochentag, stunde, max_taucher.
	 */
	public function kontingente_ersetzen( int $see_id, array $zeilen ): void {
		global $wpdb;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery -- Eigene Tabelle.
		$wpdb->delete( Schema::table( 'kontingente' ), array( 'see_id' => $see_id ) );
		foreach ( $zeilen as $zeile ) {
			$zeile['see_id'] = $see_id;
			$wpdb->insert( Schema::table( 'kontingente' ), $zeile );
		}
		// phpcs:enable
	}
}
