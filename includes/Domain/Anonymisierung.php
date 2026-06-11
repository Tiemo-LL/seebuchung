<?php
/**
 * Anonymisierungsfristen (DSGVO, F3).
 *
 * @package Seebuchung
 */

namespace Seebuchung\Domain;

use DateTimeImmutable;

/**
 * Berechnet den Stichtag: Buchungen mit Tauchtag vor dem Stichtag haben
 * ihre Aufbewahrungsfrist (X Tage nach dem Tauchtag) überschritten.
 *
 * Pure Klasse mit Unit-Tests.
 */
final class Anonymisierung {

	/**
	 * Stichtag berechnen.
	 *
	 * @param string $heute Heutiges Datum (Y-m-d).
	 * @param int    $tage  Aufbewahrungsfrist in Tagen nach dem Tauchtag.
	 * @return string Datum (Y-m-d); Tauchtage davor sind zu anonymisieren.
	 */
	public static function stichtag( string $heute, int $tage ): string {
		return ( new DateTimeImmutable( $heute ) )->modify( '-' . max( 0, $tage ) . ' days' )->format( 'Y-m-d' );
	}
}
