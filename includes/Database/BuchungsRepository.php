<?php
/**
 * Datenzugriff für Buchungen.
 *
 * @package Seebuchung
 */

namespace Seebuchung\Database;

use Seebuchung\Domain\Buchungsstatus;

/**
 * CRUD und Statuswechsel auf seebuchung_buchungen.
 */
final class BuchungsRepository {

	/**
	 * Buchung anlegen.
	 *
	 * @param array<string, mixed> $daten Spaltenwerte (ohne id/created_at/updated_at).
	 * @return int Neue Buchungs-ID.
	 */
	public function anlegen( array $daten ): int {
		global $wpdb;
		$jetzt               = current_time( 'mysql' );
		$daten['created_at'] = $jetzt;
		$daten['updated_at'] = $jetzt;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Eigene Tabelle.
		$wpdb->insert( Schema::table( 'buchungen' ), $daten );
		return (int) $wpdb->insert_id;
	}

	/**
	 * Buchung über den Token-Hash finden.
	 *
	 * @param string $token_hash SHA-256-Hash des Link-Tokens.
	 * @return array<string, mixed>|null
	 */
	public function per_token_hash( string $token_hash ): ?array {
		global $wpdb;
		$tabelle = Schema::table( 'buchungen' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Eigene Tabelle.
		$zeile = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$tabelle} WHERE token_hash = %s", $token_hash ), ARRAY_A );
		return null === $zeile ? null : $zeile;
	}

	/**
	 * Status wechseln (setzt updated_at, optional weitere Spalten).
	 *
	 * @param int                  $id     Buchungs-ID.
	 * @param string               $status Neuer Status.
	 * @param array<string, mixed> $extra  Zusätzliche Spalten (z. B. bestaetigt_am).
	 */
	public function status_setzen( int $id, string $status, array $extra = array() ): void {
		global $wpdb;
		$werte = array_merge(
			$extra,
			array(
				'status'     => $status,
				'updated_at' => current_time( 'mysql' ),
			)
		);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Eigene Tabelle.
		$wpdb->update( Schema::table( 'buchungen' ), $werte, array( 'id' => $id ) );
	}

	/**
	 * Hat dieselbe E-Mail an dem Tag/See schon eine aktive Buchung?
	 *
	 * @param int    $see_id See-ID.
	 * @param string $datum  Datum (Y-m-d).
	 * @param string $email  E-Mail-Adresse.
	 */
	public function aktive_buchung_existiert( int $see_id, string $datum, string $email ): bool {
		global $wpdb;
		$tabelle     = Schema::table( 'buchungen' );
		$platzhalter = implode( ',', array_fill( 0, count( Buchungsstatus::BELEGT_KONTINGENT ), '%s' ) );
		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Eigene Tabelle, IN-Liste dynamisch.
		$anzahl = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$tabelle} WHERE see_id = %d AND datum = %s AND email = %s AND status IN ({$platzhalter})",
				array_merge( array( $see_id, $datum, $email ), Buchungsstatus::BELEGT_KONTINGENT )
			)
		);
		// phpcs:enable
		return $anzahl > 0;
	}

	/**
	 * Unbestätigte Anfragen vor dem Stichtag auf "verfallen" setzen.
	 *
	 * @param string $stichtag MySQL-Datetime; ältere angefragte Buchungen verfallen.
	 * @return int Anzahl verfallener Buchungen.
	 */
	public function verfall_markieren( string $stichtag ): int {
		global $wpdb;
		$tabelle = Schema::table( 'buchungen' );
		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Eigene Tabelle.
		return (int) $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$tabelle} SET status = %s, updated_at = %s WHERE status = %s AND created_at < %s",
				Buchungsstatus::VERFALLEN,
				current_time( 'mysql' ),
				Buchungsstatus::ANGEFRAGT,
				$stichtag
			)
		);
		// phpcs:enable
	}
}
