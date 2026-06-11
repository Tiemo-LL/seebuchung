<?php
/**
 * Datenzugriff für Blockaden.
 *
 * @package Seebuchung
 */

namespace Seebuchung\Database;

/**
 * CRUD und Zählungen auf seebuchung_blockaden.
 */
final class BlockadenRepository {

	/**
	 * Blockade anlegen.
	 *
	 * @param array<string, mixed> $daten Spaltenwerte ohne id/Zeitstempel.
	 * @return int Neue Blockade-ID.
	 */
	public function anlegen( array $daten ): int {
		global $wpdb;
		$jetzt               = current_time( 'mysql' );
		$daten['created_at'] = $jetzt;
		$daten['updated_at'] = $jetzt;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Eigene Tabelle.
		$wpdb->insert( Schema::table( 'blockaden' ), $daten );
		return (int) $wpdb->insert_id;
	}

	/**
	 * Blockade per ID.
	 *
	 * @param int $id Blockade-ID.
	 * @return array<string, mixed>|null
	 */
	public function per_id( int $id ): ?array {
		global $wpdb;
		$tabelle = Schema::table( 'blockaden' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Eigene Tabelle.
		$zeile = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$tabelle} WHERE id = %d", $id ), ARRAY_A );
		return null === $zeile ? null : $zeile;
	}

	/**
	 * Anträge + Genehmigungen eines Vereins an einem See in einem Jahr.
	 *
	 * @param int $see_id    See-ID.
	 * @param int $verein_id Vereins-ID.
	 * @param int $jahr      Kalenderjahr des Blockade-Datums.
	 */
	public function anzahl_im_jahr( int $see_id, int $verein_id, int $jahr ): int {
		global $wpdb;
		$tabelle = Schema::table( 'blockaden' );
		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Eigene Tabelle.
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$tabelle}
				WHERE see_id = %d AND verein_id = %d AND YEAR(datum) = %d AND status IN ('beantragt','genehmigt')",
				$see_id,
				$verein_id,
				$jahr
			)
		);
		// phpcs:enable
	}

	/**
	 * Blockaden für den Admin (neueste zuerst, optional nach Status).
	 *
	 * @param string $status Status-Filter (leer = alle).
	 * @return array<int, array<string, mixed>>
	 */
	public function alle( string $status = '' ): array {
		global $wpdb;
		$tabelle = Schema::table( 'blockaden' );
		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Eigene Tabelle.
		if ( '' !== $status ) {
			return (array) $wpdb->get_results(
				$wpdb->prepare( "SELECT * FROM {$tabelle} WHERE status = %s ORDER BY datum DESC, id DESC LIMIT 300", $status ),
				ARRAY_A
			);
		}
		return (array) $wpdb->get_results( "SELECT * FROM {$tabelle} ORDER BY datum DESC, id DESC LIMIT 300", ARRAY_A );
		// phpcs:enable
	}

	/**
	 * Status setzen.
	 *
	 * @param int    $id     Blockade-ID.
	 * @param string $status Neuer Status (genehmigt/abgelehnt).
	 */
	public function status_setzen( int $id, string $status ): void {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Eigene Tabelle.
		$wpdb->update(
			Schema::table( 'blockaden' ),
			array(
				'status'     => $status,
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => $id )
		);
	}
}
