<?php

namespace Seebuchung\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Seebuchung\Domain\Buchungsvalidierung;
use Seebuchung\Domain\See;
use Seebuchung\Domain\Seemodus;
use Seebuchung\Domain\VerfuegbarkeitsEngine;

/**
 * Buchungsvalidierung gegen einen Stundensee: Fr 2026-06-12, Kontingent 10
 * je Stunde 9–19, min 1 / max 10 Taucher, 4,50 € je Zahler.
 *
 * "kostenpflichtig" (Default false) bedeutet: ALLE Taucher müssen zahlen.
 */
final class BuchungsvalidierungTest extends TestCase {

	private const FREITAG = '2026-06-12';

	private function validierung( array $see_overrides = array(), array $belegungen = array() ): Buchungsvalidierung {
		$defaults = array(
			'id'                     => 1,
			'name'                   => 'Testsee',
			'aktiv'                  => true,
			'modus'                  => Seemodus::STUNDE,
			'saison_von'             => '2026-04-01',
			'saison_bis'             => '2026-10-31',
			'buchungsfenster_wochen' => 4,
			'stunde_von_woche'       => 9,
			'stunde_bis_woche'       => 20,
			'stunde_von_wochenende'  => 8,
			'stunde_bis_wochenende'  => 18,
			'min_anmelder'           => 1,
			'max_pro_buchung'        => 10,
			'kostenpflichtig'        => false,
			'preis_pro_person'       => 4.50,
		);
		$see      = new See( ...array_values( array_merge( $defaults, $see_overrides ) ) );

		$kontingente = array();
		foreach ( range( 1, 7 ) as $wochentag ) {
			foreach ( range( 0, 23 ) as $stunde ) {
				$kontingente[] = array(
					'wochentag'   => $wochentag,
					'stunde'      => $stunde,
					'max_taucher' => 10,
				);
			}
		}

		$engine = new VerfuegbarkeitsEngine( $see, $kontingente, $belegungen, array(), array(), '2026-06-10' );
		return new Buchungsvalidierung( $see, $engine );
	}

	public function test_gueltige_buchung_ohne_fehler(): void {
		$fehler = $this->validierung()->pruefen( self::FREITAG, 10, 4, 0, true, false, false );
		self::assertSame( array(), $fehler );
	}

	public function test_datum_ausserhalb_fenster(): void {
		$fehler = $this->validierung()->pruefen( '2026-09-01', 10, 4, 0, true, false, false );
		self::assertSame( array( Buchungsvalidierung::FEHLER_NICHT_BUCHBAR ), $fehler );
	}

	public function test_stundensee_braucht_stunde(): void {
		$fehler = $this->validierung()->pruefen( self::FREITAG, null, 4, 0, true, false, false );
		self::assertSame( array( Buchungsvalidierung::FEHLER_STUNDE_FEHLT ), $fehler );
	}

	public function test_kontingent_reicht_nicht(): void {
		$belegungen = array( self::FREITAG => array( 10 => 8 ) ); // Rest 2.
		$fehler     = $this->validierung( array(), $belegungen )->pruefen( self::FREITAG, 10, 3, 0, true, false, false );
		self::assertContains( Buchungsvalidierung::FEHLER_KEIN_KONTINGENT, $fehler );
	}

	public function test_gruppengroesse_grenzen(): void {
		$validierung = $this->validierung( array( 'min_anmelder' => 2 ) );
		self::assertContains( Buchungsvalidierung::FEHLER_ANZAHL_MIN, $validierung->pruefen( self::FREITAG, 10, 1, 0, true, false, false ) );
		self::assertContains( Buchungsvalidierung::FEHLER_ANZAHL_MAX, $validierung->pruefen( self::FREITAG, 10, 11, 0, true, false, false ) );
	}

	public function test_zahler_nicht_mehr_als_taucher(): void {
		$fehler = $this->validierung()->pruefen( self::FREITAG, 10, 2, 3, true, false, false );
		self::assertContains( Buchungsvalidierung::FEHLER_ZAHLER_ANZAHL, $fehler );
	}

	public function test_ohne_verein_mindestens_ein_zahler(): void {
		$fehler = $this->validierung()->pruefen( self::FREITAG, 10, 2, 0, false, false, false );
		self::assertContains( Buchungsvalidierung::FEHLER_ZAHLER_ERFORDERLICH, $fehler );
	}

	public function test_mit_verein_keine_zahler_noetig(): void {
		$fehler = $this->validierung()->pruefen( self::FREITAG, 10, 2, 0, true, false, false );
		self::assertSame( array(), $fehler );
	}

	public function test_kostenpflichtiger_see_alle_muessen_zahlen(): void {
		$validierung = $this->validierung( array( 'kostenpflichtig' => true ) );
		// 3 Taucher, nur 1 Zahler → Fehler, auch mit Verein.
		self::assertContains( Buchungsvalidierung::FEHLER_ALLE_ZAHLER, $validierung->pruefen( self::FREITAG, 10, 3, 1, true, false, false ) );
		// 3 Taucher, 3 Zahler → ok.
		self::assertSame( array(), $validierung->pruefen( self::FREITAG, 10, 3, 3, true, false, false ) );
	}

	public function test_unbekannter_verein(): void {
		$fehler = $this->validierung()->pruefen( self::FREITAG, 10, 2, 1, false, true, false );
		self::assertContains( Buchungsvalidierung::FEHLER_VEREIN_UNBEKANNT, $fehler );
	}

	public function test_doppelbuchung_gleicher_tag(): void {
		$fehler = $this->validierung()->pruefen( self::FREITAG, 10, 2, 0, true, false, true );
		self::assertContains( Buchungsvalidierung::FEHLER_DOPPELBUCHUNG, $fehler );
	}
}
