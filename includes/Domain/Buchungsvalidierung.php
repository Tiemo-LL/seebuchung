<?php
/**
 * Validierung einer Buchungsanfrage.
 *
 * @package Seebuchung
 */

namespace Seebuchung\Domain;

/**
 * Prüft eine Buchungsanfrage gegen See-Regeln und Verfügbarkeit.
 *
 * Pure Klasse: Engine und Kontextflags werden injiziert, Rückgabe ist eine
 * Liste von Fehlercodes (leer = gültig). Die Texte zu den Codes liefert die
 * Frontend-Schicht (i18n).
 */
final class Buchungsvalidierung {

	public const FEHLER_NICHT_BUCHBAR       = 'nicht_buchbar';
	public const FEHLER_ALLE_ZAHLER         = 'alle_zahler';
	public const FEHLER_STUNDE_FEHLT        = 'stunde_fehlt';
	public const FEHLER_KEIN_KONTINGENT     = 'kein_kontingent';
	public const FEHLER_ANZAHL_MIN          = 'anzahl_min';
	public const FEHLER_ANZAHL_MAX          = 'anzahl_max';
	public const FEHLER_ZAHLER_ANZAHL       = 'zahler_anzahl';
	public const FEHLER_ZAHLER_ERFORDERLICH = 'zahler_erforderlich';
	public const FEHLER_VEREIN_UNBEKANNT    = 'verein_unbekannt';
	public const FEHLER_DOPPELBUCHUNG       = 'doppelbuchung';

	/**
	 * Konstruktor.
	 *
	 * @param See                   $see    See-Konfiguration.
	 * @param VerfuegbarkeitsEngine $engine Engine für denselben See.
	 */
	public function __construct(
		private readonly See $see,
		private readonly VerfuegbarkeitsEngine $engine
	) {
	}

	/**
	 * Anfrage prüfen.
	 *
	 * @param string   $datum            Wunschdatum (Y-m-d).
	 * @param int|null $stunde           Stundenslot (null am Tagessee).
	 * @param int      $anzahl_taucher   Gruppengröße.
	 * @param int      $anzahl_zahler    Zahlungspflichtige davon.
	 * @param bool     $hat_verein       Ob eine gültige Vereins-Nr. angegeben wurde.
	 * @param bool     $verein_unbekannt Ob eine Vereins-Nr. angegeben, aber nicht gefunden wurde.
	 * @param bool     $bereits_gebucht  Ob dieselbe E-Mail an dem Tag schon aktiv gebucht hat.
	 * @return string[] Fehlercodes, leer wenn gültig.
	 */
	public function pruefen(
		string $datum,
		?int $stunde,
		int $anzahl_taucher,
		int $anzahl_zahler,
		bool $hat_verein,
		bool $verein_unbekannt,
		bool $bereits_gebucht
	): array {
		$fehler = array();

		if ( ! $this->engine->ist_buchbar_am( $datum ) ) {
			$fehler[] = self::FEHLER_NICHT_BUCHBAR;
			return $fehler;
		}

		if ( Seemodus::STUNDE === $this->see->modus && null === $stunde ) {
			$fehler[] = self::FEHLER_STUNDE_FEHLT;
			return $fehler;
		}

		$rest = Seemodus::TAG === $this->see->modus
			? $this->engine->rest_fuer_tag( $datum )
			: $this->engine->rest_fuer_stunde( $datum, (int) $stunde );
		if ( $anzahl_taucher > $rest ) {
			$fehler[] = self::FEHLER_KEIN_KONTINGENT;
		}

		if ( $anzahl_taucher < $this->see->min_anmelder ) {
			$fehler[] = self::FEHLER_ANZAHL_MIN;
		}
		if ( $anzahl_taucher > $this->see->max_pro_buchung ) {
			$fehler[] = self::FEHLER_ANZAHL_MAX;
		}
		if ( $anzahl_zahler > $anzahl_taucher || $anzahl_zahler < 0 ) {
			$fehler[] = self::FEHLER_ZAHLER_ANZAHL;
		}
		if ( $verein_unbekannt ) {
			$fehler[] = self::FEHLER_VEREIN_UNBEKANNT;
		}
		// Ohne Verbands-Verein zahlt mindestens die buchende Person (Alt-Regel 112).
		if ( ! $hat_verein && ! $verein_unbekannt && $anzahl_zahler < 1 ) {
			$fehler[] = self::FEHLER_ZAHLER_ERFORDERLICH;
		}
		// Kostenpflichtiger See: ALLE Taucher sind zahlungspflichtig (Alt-Semantik von isKostenpflichtig).
		if ( $this->see->kostenpflichtig && $anzahl_zahler !== $anzahl_taucher ) {
			$fehler[] = self::FEHLER_ALLE_ZAHLER;
		}
		if ( $bereits_gebucht ) {
			$fehler[] = self::FEHLER_DOPPELBUCHUNG;
		}

		return $fehler;
	}
}
