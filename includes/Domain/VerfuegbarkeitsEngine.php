<?php
/**
 * Verfügbarkeits-Engine.
 *
 * @package Seebuchung
 */

namespace Seebuchung\Domain;

use DateTimeImmutable;

/**
 * Berechnet das Restkontingent je See, Datum und Stunde.
 *
 * Pure Domänenlogik ohne WP-/DB-Zugriff — alle Daten werden injiziert
 * (siehe Database\VerfuegbarkeitsRepository für die Beschaffung).
 *
 * Berücksichtigt: Buchungen, genehmigte Blockaden, Nachttermine, Saison,
 * Buchungsfenster, Öffnungszeiten Woche/Wochenende, aktiv-Flag.
 *
 * Konventionen:
 * - Wochentage ISO-8601: 1 = Montag … 7 = Sonntag; Wochenende = 6/7.
 * - Stundenslots: "9" = 09:00–10:00; stunde_bis ist exklusiv.
 * - Blockade ohne anzahl_taucher sperrt den Slot/Tag komplett, mit
 *   anzahl_taucher reduziert sie das Kontingent um diese Anzahl.
 * - Am Tagessee zählt jede Blockade für den ganzen Tag (Regel "Tagessee
 *   = ganzer Tag").
 * - Nachttermine schalten ihre Stunden zusätzlich zu den Öffnungszeiten
 *   frei; das Kontingent dieser Stunden kommt regulär aus den
 *   Kontingent-Zeilen.
 */
final class VerfuegbarkeitsEngine {

	/**
	 * Schlüssel für Tageskontingent in Übersichten/Belegungen.
	 */
	public const TAGESSCHLUESSEL = 'tag';

	/**
	 * Kontingent-Maxima: "wochentag|stunde" bzw. "wochentag|tag" => max_taucher.
	 *
	 * @var array<string, int>
	 */
	private array $maxima = array();

	/**
	 * Konstruktor.
	 *
	 * @param See                                             $see          See-Konfiguration.
	 * @param array<int, array<string, int|null>>             $kontingente  Zeilen mit wochentag (1–7),
	 *                                                                      stunde (0–23 oder null = Tag), max_taucher.
	 * @param array<string, array<int|string, int>>           $belegungen   Belegte Plätze: 'Y-m-d' => (stunde|'tag') => Summe.
	 * @param array<int, array<string, int|bool|string|null>> $blockaden Genehmigte Blockaden: datum (Y-m-d),
	 *                                                               ganzer_tag (bool), stunde_von, stunde_bis,
	 *                                                               anzahl_taucher (int|null = Vollsperrung).
	 * @param array<string, array<int, int>>                  $nachttermine 'Y-m-d' => [stunde_von, stunde_bis].
	 * @param string                                          $heute        Heutiges Datum (Y-m-d) — injiziert für Tests.
	 */
	public function __construct(
		private readonly See $see,
		array $kontingente,
		private readonly array $belegungen,
		private readonly array $blockaden,
		private readonly array $nachttermine,
		private readonly string $heute
	) {
		foreach ( $kontingente as $zeile ) {
			$stunde                      = $zeile['stunde'] ?? null;
			$schluessel                  = $zeile['wochentag'] . '|' . ( null === $stunde ? self::TAGESSCHLUESSEL : (int) $stunde );
			$this->maxima[ $schluessel ] = (int) $zeile['max_taucher'];
		}
	}

	/**
	 * Ist das Datum grundsätzlich buchbar (aktiv, Saison, Buchungsfenster)?
	 *
	 * @param string $datum Datum (Y-m-d).
	 */
	public function ist_buchbar_am( string $datum ): bool {
		return $this->see->aktiv
			&& $this->ist_in_saison( $datum )
			&& $this->ist_im_buchungsfenster( $datum );
	}

	/**
	 * Liegt das Datum innerhalb der Saison? Ohne Saisongrenzen: ganzjährig.
	 *
	 * @param string $datum Datum (Y-m-d).
	 */
	public function ist_in_saison( string $datum ): bool {
		if ( null !== $this->see->saison_von && $datum < $this->see->saison_von ) {
			return false;
		}
		if ( null !== $this->see->saison_bis && $datum > $this->see->saison_bis ) {
			return false;
		}
		return true;
	}

	/**
	 * Liegt das Datum im Buchungsfenster (heute bis heute + X Wochen)?
	 *
	 * @param string $datum Datum (Y-m-d).
	 */
	public function ist_im_buchungsfenster( string $datum ): bool {
		if ( $datum < $this->heute ) {
			return false;
		}
		$ende = ( new DateTimeImmutable( $this->heute ) )
			->modify( '+' . ( $this->see->buchungsfenster_wochen * 7 ) . ' days' )
			->format( 'Y-m-d' );
		return $datum <= $ende;
	}

	/**
	 * Buchbare Stundenslots eines Datums (Öffnungszeiten + Nachttermin).
	 *
	 * Leer am Tagessee oder wenn das Datum nicht buchbar ist.
	 *
	 * @param string $datum Datum (Y-m-d).
	 * @return int[] Aufsteigend sortierte Stunden.
	 */
	public function buchbare_stunden( string $datum ): array {
		if ( Seemodus::STUNDE !== $this->see->modus || ! $this->ist_buchbar_am( $datum ) ) {
			return array();
		}

		$wochenende = $this->wochentag( $datum ) >= 6;
		$von        = $wochenende ? $this->see->stunde_von_wochenende : $this->see->stunde_von_woche;
		$bis        = $wochenende ? $this->see->stunde_bis_wochenende : $this->see->stunde_bis_woche;

		$stunden = ( null !== $von && null !== $bis && $von < $bis ) ? range( $von, $bis - 1 ) : array();

		if ( isset( $this->nachttermine[ $datum ] ) ) {
			list( $nacht_von, $nacht_bis ) = $this->nachttermine[ $datum ];
			if ( $nacht_von < $nacht_bis ) {
				$stunden = array_merge( $stunden, range( $nacht_von, $nacht_bis - 1 ) );
			}
		}

		$stunden = array_values( array_unique( $stunden ) );
		sort( $stunden );
		return $stunden;
	}

	/**
	 * Restkontingent für einen Stundenslot (0, wenn nicht buchbar).
	 *
	 * @param string $datum  Datum (Y-m-d).
	 * @param int    $stunde Stundenslot (0–23).
	 */
	public function rest_fuer_stunde( string $datum, int $stunde ): int {
		if ( ! in_array( $stunde, $this->buchbare_stunden( $datum ), true ) ) {
			return 0;
		}

		$max  = $this->maxima[ $this->wochentag( $datum ) . '|' . $stunde ] ?? 0;
		$rest = $max - ( $this->belegungen[ $datum ][ $stunde ] ?? 0 );

		foreach ( $this->blockaden_fuer( $datum ) as $blockade ) {
			$trifft = ! empty( $blockade['ganzer_tag'] )
				|| ( null !== $blockade['stunde_von'] && null !== $blockade['stunde_bis']
					&& $stunde >= $blockade['stunde_von'] && $stunde < $blockade['stunde_bis'] );
			if ( ! $trifft ) {
				continue;
			}
			if ( null === $blockade['anzahl_taucher'] ) {
				return 0;
			}
			$rest -= (int) $blockade['anzahl_taucher'];
		}

		return max( 0, $rest );
	}

	/**
	 * Restkontingent für einen Tag am Tagessee (0, wenn nicht buchbar).
	 *
	 * @param string $datum Datum (Y-m-d).
	 */
	public function rest_fuer_tag( string $datum ): int {
		if ( Seemodus::TAG !== $this->see->modus || ! $this->ist_buchbar_am( $datum ) ) {
			return 0;
		}

		$max  = $this->maxima[ $this->wochentag( $datum ) . '|' . self::TAGESSCHLUESSEL ] ?? 0;
		$rest = $max - ( $this->belegungen[ $datum ][ self::TAGESSCHLUESSEL ] ?? 0 );

		// Tagessee: jede Blockade gilt für den ganzen Tag.
		foreach ( $this->blockaden_fuer( $datum ) as $blockade ) {
			if ( null === $blockade['anzahl_taucher'] ) {
				return 0;
			}
			$rest -= (int) $blockade['anzahl_taucher'];
		}

		return max( 0, $rest );
	}

	/**
	 * Restkontingente eines Datums als Übersicht.
	 *
	 * Tagessee: array( 'tag' => rest ) — Stundensee: array( stunde => rest, … ).
	 *
	 * @param string $datum Datum (Y-m-d).
	 * @return array<int|string, int>
	 */
	public function tagesuebersicht( string $datum ): array {
		if ( Seemodus::TAG === $this->see->modus ) {
			return array( self::TAGESSCHLUESSEL => $this->rest_fuer_tag( $datum ) );
		}

		$uebersicht = array();
		foreach ( $this->buchbare_stunden( $datum ) as $stunde ) {
			$uebersicht[ $stunde ] = $this->rest_fuer_stunde( $datum, $stunde );
		}
		return $uebersicht;
	}

	/**
	 * ISO-Wochentag (1 = Montag … 7 = Sonntag).
	 *
	 * @param string $datum Datum (Y-m-d).
	 */
	private function wochentag( string $datum ): int {
		return (int) ( new DateTimeImmutable( $datum ) )->format( 'N' );
	}

	/**
	 * Blockaden eines Datums.
	 *
	 * @param string $datum Datum (Y-m-d).
	 * @return array<int, array<string, int|bool|string|null>>
	 */
	private function blockaden_fuer( string $datum ): array {
		return array_values(
			array_filter(
				$this->blockaden,
				static fn ( array $blockade ): bool => $blockade['datum'] === $datum
			)
		);
	}
}
