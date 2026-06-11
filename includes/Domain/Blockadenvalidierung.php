<?php
/**
 * Validierung von Vereins-Blockadeanträgen.
 *
 * @package Seebuchung
 */

namespace Seebuchung\Domain;

use DateTimeImmutable;

/**
 * Regeln aus dem Altsystem (F2):
 * - Anträge nur im Antragsfenster (Standard 1.10.–1.2., über den Jahreswechsel).
 * - Anträge gelten fürs Folgejahr (ab Oktober: nächstes Jahr; im Januar: laufendes Jahr).
 * - Max. 2 Blockaden pro Jahr, See und Verein.
 * - Max. 3 Stunden; am Tagessee zählt der ganze Tag.
 *
 * Pure Klasse — alle Kontextwerte werden injiziert.
 */
final class Blockadenvalidierung {

	public const FEHLER_FENSTER_ZU    = 'fenster_zu';
	public const FEHLER_FALSCHES_JAHR = 'falsches_jahr';
	public const FEHLER_MAX_PRO_JAHR  = 'max_pro_jahr';
	public const FEHLER_ZU_LANG       = 'zu_lang';
	public const FEHLER_ZEITRAUM      = 'zeitraum';
	public const MAX_PRO_JAHR         = 2;
	public const MAX_STUNDEN          = 3;

	/**
	 * Liegt "heute" im Antragsfenster?
	 *
	 * Fenster im Format MM-TT; bei von > bis läuft es über den Jahreswechsel
	 * (z. B. 10-01 bis 02-01).
	 *
	 * @param string $heute_md MM-TT von heute.
	 * @param string $von_md   Fensterbeginn (MM-TT).
	 * @param string $bis_md   Fensterende (MM-TT).
	 */
	public static function im_antragsfenster( string $heute_md, string $von_md, string $bis_md ): bool {
		if ( $von_md <= $bis_md ) {
			return $heute_md >= $von_md && $heute_md <= $bis_md;
		}
		return $heute_md >= $von_md || $heute_md <= $bis_md;
	}

	/**
	 * Zieljahr eines Antrags: ab Fensterbeginn das Folgejahr, im
	 * Jahresanfangs-Teil des Fensters das laufende Jahr.
	 *
	 * @param string $heute  Heutiges Datum (Y-m-d).
	 * @param string $von_md Fensterbeginn (MM-TT).
	 */
	public static function zieljahr( string $heute, string $von_md ): int {
		$jahr = (int) substr( $heute, 0, 4 );
		return substr( $heute, 5 ) >= $von_md ? $jahr + 1 : $jahr;
	}

	/**
	 * Antrag prüfen.
	 *
	 * @param string   $heute               Heutiges Datum (Y-m-d).
	 * @param string   $fenster_von         Fensterbeginn (MM-TT).
	 * @param string   $fenster_bis         Fensterende (MM-TT).
	 * @param string   $datum               Gewünschtes Blockade-Datum (Y-m-d).
	 * @param bool     $ganzer_tag          Ob ganztägig beantragt wird.
	 * @param int|null $stunde_von          Beginn-Slot (bei Stundenblockade).
	 * @param int|null $stunde_bis          Ende-Slot exklusiv (bei Stundenblockade).
	 * @param bool     $ist_tagessee        Ob der See ein Tagessee ist (erzwingt ganzen Tag).
	 * @param int      $bestehende_im_jahr  Bisherige Blockaden (beantragt + genehmigt) des Vereins an dem See im Zieljahr.
	 * @return string[] Fehlercodes, leer wenn gültig.
	 */
	public static function pruefen(
		string $heute,
		string $fenster_von,
		string $fenster_bis,
		string $datum,
		bool $ganzer_tag,
		?int $stunde_von,
		?int $stunde_bis,
		bool $ist_tagessee,
		int $bestehende_im_jahr
	): array {
		$fehler = array();

		if ( ! self::im_antragsfenster( substr( $heute, 5 ), $fenster_von, $fenster_bis ) ) {
			return array( self::FEHLER_FENSTER_ZU );
		}

		if ( (int) substr( $datum, 0, 4 ) !== self::zieljahr( $heute, $fenster_von ) ) {
			$fehler[] = self::FEHLER_FALSCHES_JAHR;
		}

		if ( $bestehende_im_jahr >= self::MAX_PRO_JAHR ) {
			$fehler[] = self::FEHLER_MAX_PRO_JAHR;
		}

		if ( ! $ist_tagessee && ! $ganzer_tag ) {
			if ( null === $stunde_von || null === $stunde_bis || $stunde_von >= $stunde_bis ) {
				$fehler[] = self::FEHLER_ZEITRAUM;
			} elseif ( $stunde_bis - $stunde_von > self::MAX_STUNDEN ) {
				$fehler[] = self::FEHLER_ZU_LANG;
			}
		}

		return $fehler;
	}
}
