<?php
/**
 * Datenbeschaffung für die Verfügbarkeits-Engine.
 *
 * @package Seebuchung
 */

namespace Seebuchung\Database;

use Seebuchung\Domain\Buchungsstatus;
use Seebuchung\Domain\See;
use Seebuchung\Domain\VerfuegbarkeitsEngine;

/**
 * Lädt See, Kontingente, Belegungen, Blockaden und Nachttermine aus den
 * Plugin-Tabellen und baut daraus eine VerfuegbarkeitsEngine.
 *
 * Direkte Queries sind hier bewusst: eigene Tabellen, kein WP-Cache-Layer.
 */
final class VerfuegbarkeitsRepository {

	/**
	 * Engine für einen See bauen.
	 *
	 * Lädt Belegungen/Blockaden/Nachttermine ab heute (Vergangenheit ist
	 * für die Verfügbarkeit irrelevant).
	 *
	 * @param int         $see_id See-ID.
	 * @param string|null $heute  Stichtag (Y-m-d); Standard: heute in WP-Zeitzone.
	 * @return VerfuegbarkeitsEngine|null Null, wenn der See nicht existiert.
	 */
	public function engine_fuer_see( int $see_id, ?string $heute = null ): ?VerfuegbarkeitsEngine {
		global $wpdb;

		$heute = $heute ?? current_time( 'Y-m-d' );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Eigene Plugin-Tabellen; Tabellennamen aus Schema::table(), Platzhalterzahl der IN-Liste ist dynamisch.
		$seen_tabelle = Schema::table( 'seen' );
		$see_row      = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$seen_tabelle} WHERE id = %d", $see_id ),
			ARRAY_A
		);
		if ( null === $see_row ) {
			return null;
		}

		$kontingente_tabelle = Schema::table( 'kontingente' );
		$kontingente         = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT wochentag, stunde, max_taucher FROM {$kontingente_tabelle} WHERE see_id = %d",
				$see_id
			),
			ARRAY_A
		);

		$status_platzhalter = implode( ',', array_fill( 0, count( Buchungsstatus::BELEGT_KONTINGENT ), '%s' ) );
		$buchungen_tabelle  = Schema::table( 'buchungen' );
		$belegung_rows      = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT datum, stunde, SUM(anzahl_taucher) AS summe
				FROM {$buchungen_tabelle}
				WHERE see_id = %d AND datum >= %s AND status IN ({$status_platzhalter})
				GROUP BY datum, stunde",
				array_merge( array( $see_id, $heute ), Buchungsstatus::BELEGT_KONTINGENT )
			),
			ARRAY_A
		);

		$blockaden_tabelle = Schema::table( 'blockaden' );
		$blockade_rows     = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT datum, ganzer_tag, stunde_von, stunde_bis, anzahl_taucher
				FROM {$blockaden_tabelle}
				WHERE see_id = %d AND datum >= %s AND status = %s",
				$see_id,
				$heute,
				'genehmigt'
			),
			ARRAY_A
		);

		$nachttermine_tabelle = Schema::table( 'nachttermine' );
		$nachttermin_rows     = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT datum, stunde_von, stunde_bis FROM {$nachttermine_tabelle} WHERE see_id = %d AND datum >= %s",
				$see_id,
				$heute
			),
			ARRAY_A
		);
		// phpcs:enable

		return new VerfuegbarkeitsEngine(
			See::from_row( $see_row ),
			self::kontingente_normalisieren( $kontingente ),
			self::belegungen_normalisieren( $belegung_rows ),
			self::blockaden_normalisieren( $blockade_rows ),
			self::nachttermine_normalisieren( $nachttermin_rows ),
			$heute
		);
	}

	/**
	 * Kontingent-Zeilen in Engine-Form bringen.
	 *
	 * @param array<int, array<string, string|null>> $rows DB-Zeilen.
	 * @return array<int, array<string, int|null>>
	 */
	private static function kontingente_normalisieren( array $rows ): array {
		return array_map(
			static fn ( array $row ): array => array(
				'wochentag'   => (int) $row['wochentag'],
				'stunde'      => null === $row['stunde'] ? null : (int) $row['stunde'],
				'max_taucher' => (int) $row['max_taucher'],
			),
			$rows
		);
	}

	/**
	 * Aggregierte Buchungen als 'Y-m-d' => (stunde|'tag') => Summe.
	 *
	 * @param array<int, array<string, string|null>> $rows DB-Zeilen.
	 * @return array<string, array<int|string, int>>
	 */
	private static function belegungen_normalisieren( array $rows ): array {
		$belegungen = array();
		foreach ( $rows as $row ) {
			$slot = null === $row['stunde'] ? VerfuegbarkeitsEngine::TAGESSCHLUESSEL : (int) $row['stunde'];

			$belegungen[ $row['datum'] ][ $slot ] = (int) $row['summe'];
		}
		return $belegungen;
	}

	/**
	 * Blockade-Zeilen in Engine-Form bringen.
	 *
	 * @param array<int, array<string, string|null>> $rows DB-Zeilen.
	 * @return array<int, array<string, int|bool|string|null>>
	 */
	private static function blockaden_normalisieren( array $rows ): array {
		return array_map(
			static fn ( array $row ): array => array(
				'datum'          => (string) $row['datum'],
				'ganzer_tag'     => (bool) $row['ganzer_tag'],
				'stunde_von'     => null === $row['stunde_von'] ? null : (int) $row['stunde_von'],
				'stunde_bis'     => null === $row['stunde_bis'] ? null : (int) $row['stunde_bis'],
				'anzahl_taucher' => null === $row['anzahl_taucher'] ? null : (int) $row['anzahl_taucher'],
			),
			$rows
		);
	}

	/**
	 * Nachttermine als 'Y-m-d' => [von, bis].
	 *
	 * @param array<int, array<string, string>> $rows DB-Zeilen.
	 * @return array<string, array<int, int>>
	 */
	private static function nachttermine_normalisieren( array $rows ): array {
		$termine = array();
		foreach ( $rows as $row ) {
			$termine[ $row['datum'] ] = array( (int) $row['stunde_von'], (int) $row['stunde_bis'] );
		}
		return $termine;
	}
}
