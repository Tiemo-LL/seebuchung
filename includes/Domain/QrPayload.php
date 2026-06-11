<?php
/**
 * Signierter QR-Payload für Tauchbestätigungen.
 *
 * @package Seebuchung
 */

namespace Seebuchung\Domain;

/**
 * Kompakter, HMAC-signierter Payload: "SB1:<buchungs_id>:<signatur>".
 *
 * Der Kontrolleur-Scan prüft die Signatur offline-fähig gegen das
 * Verbands-Secret; die Buchungsdetails kommen anschließend per Lookup.
 * Pure Klasse (Secret wird injiziert) — per Unit-Test abgedeckt.
 */
final class QrPayload {

	/**
	 * Versionspräfix (für spätere Formatwechsel).
	 */
	private const PREFIX = 'SB1';

	/**
	 * Länge der gekürzten HMAC-Signatur (hex).
	 */
	private const SIGNATUR_LAENGE = 20;

	/**
	 * Payload für eine Buchung erzeugen.
	 *
	 * @param int    $buchungs_id Buchungs-ID.
	 * @param string $secret      Signatur-Secret.
	 */
	public static function erzeugen( int $buchungs_id, string $secret ): string {
		return self::PREFIX . ':' . $buchungs_id . ':' . self::signatur( $buchungs_id, $secret );
	}

	/**
	 * Payload prüfen.
	 *
	 * @param string $payload Gescannter Inhalt.
	 * @param string $secret  Signatur-Secret.
	 * @return int|null Buchungs-ID bei gültiger Signatur, sonst null.
	 */
	public static function pruefen( string $payload, string $secret ): ?int {
		$teile = explode( ':', $payload );
		if ( 3 !== count( $teile ) || self::PREFIX !== $teile[0] || ! ctype_digit( $teile[1] ) ) {
			return null;
		}
		$buchungs_id = (int) $teile[1];
		if ( ! hash_equals( self::signatur( $buchungs_id, $secret ), $teile[2] ) ) {
			return null;
		}
		return $buchungs_id;
	}

	/**
	 * Gekürzte HMAC-Signatur.
	 *
	 * @param int    $buchungs_id Buchungs-ID.
	 * @param string $secret      Signatur-Secret.
	 */
	private static function signatur( int $buchungs_id, string $secret ): string {
		return substr( hash_hmac( 'sha256', self::PREFIX . ':' . $buchungs_id, $secret ), 0, self::SIGNATUR_LAENGE );
	}
}
