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
	 * Buchung per ID.
	 *
	 * @param int $id Buchungs-ID.
	 * @return array<string, mixed>|null
	 */
	public function per_id( int $id ): ?array {
		global $wpdb;
		$tabelle = Schema::table( 'buchungen' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Eigene Tabelle.
		$zeile = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$tabelle} WHERE id = %d", $id ), ARRAY_A );
		return null === $zeile ? null : $zeile;
	}

	/**
	 * Buchungen für die Admin-Übersicht suchen.
	 *
	 * @param int    $see_id See-Filter (0 = alle).
	 * @param string $datum  Datums-Filter (Y-m-d, leer = alle).
	 * @param string $status Status-Filter (leer = alle).
	 * @param int    $limit  Max. Zeilen.
	 * @return array<int, array<string, mixed>>
	 */
	public function suchen( int $see_id, string $datum, string $status, int $limit = 200 ): array {
		global $wpdb;
		$tabelle = Schema::table( 'buchungen' );

		$bedingungen = array( '1=1' );
		$parameter   = array();
		if ( $see_id > 0 ) {
			$bedingungen[] = 'see_id = %d';
			$parameter[]   = $see_id;
		}
		if ( '' !== $datum ) {
			$bedingungen[] = 'datum = %s';
			$parameter[]   = $datum;
		}
		if ( '' !== $status ) {
			$bedingungen[] = 'status = %s';
			$parameter[]   = $status;
		}
		$parameter[] = $limit;

		$where = implode( ' AND ', $bedingungen );
		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Eigene Tabelle, dynamische Filter.
		return (array) $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$tabelle} WHERE {$where} ORDER BY datum DESC, stunde, id DESC LIMIT %d", $parameter ),
			ARRAY_A
		);
		// phpcs:enable
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
	 * Personenbezogene Daten alter Buchungen entfernen (DSGVO).
	 *
	 * Statistikfelder (See, Datum, Stunde, Anzahl, Status, Preis) bleiben
	 * erhalten; der token_hash wird entwertet (deterministisch eindeutig,
	 * da UNIQUE und NOT NULL).
	 *
	 * @param string $stichtag Buchungen mit Tauchtag vor diesem Datum (Y-m-d) werden anonymisiert.
	 * @return int Anzahl anonymisierter Buchungen.
	 */
	public function anonymisieren( string $stichtag ): int {
		global $wpdb;
		$tabelle = Schema::table( 'buchungen' );
		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Eigene Tabelle.
		return (int) $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$tabelle}
				SET name = '', vorname = '', email = '', telefon = '',
					token_hash = SHA2(CONCAT('anonymisiert-', id), 256),
					anonymisiert_am = %s, updated_at = %s
				WHERE datum < %s AND anonymisiert_am IS NULL",
				current_time( 'mysql' ),
				current_time( 'mysql' ),
				$stichtag
			)
		);
		// phpcs:enable
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
