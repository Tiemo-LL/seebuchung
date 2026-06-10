<?php

namespace Seebuchung\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Seebuchung\Domain\See;
use Seebuchung\Domain\Seemodus;
use Seebuchung\Domain\VerfuegbarkeitsEngine;

/**
 * Verfügbarkeits-Engine: Kontingent-Logik (Kern der Fachlichkeit, lt. CLAUDE.md testpflichtig).
 *
 * Fixe Daten: "heute" = Mi 2026-06-10. 2026-06-12 = Freitag, 2026-06-13 = Samstag.
 */
final class VerfuegbarkeitsEngineTest extends TestCase {

	private const HEUTE   = '2026-06-10';
	private const FREITAG = '2026-06-12';
	private const SAMSTAG = '2026-06-13';

	private function see( array $overrides = array() ): See {
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
			'kostenpflichtig'        => true,
			'preis_pro_person'       => 4.50,
		);
		$werte    = array_merge( $defaults, $overrides );
		return new See( ...array_values( $werte ) );
	}

	/**
	 * Kontingent 10 für jede Stunde 0–23 an allen Wochentagen.
	 */
	private function volle_kontingente( int $max = 10 ): array {
		$kontingente = array();
		foreach ( range( 1, 7 ) as $wochentag ) {
			foreach ( range( 0, 23 ) as $stunde ) {
				$kontingente[] = array(
					'wochentag'   => $wochentag,
					'stunde'      => $stunde,
					'max_taucher' => $max,
				);
			}
		}
		return $kontingente;
	}

	private function engine(
		?See $see = null,
		?array $kontingente = null,
		array $belegungen = array(),
		array $blockaden = array(),
		array $nachttermine = array(),
		string $heute = self::HEUTE
	): VerfuegbarkeitsEngine {
		return new VerfuegbarkeitsEngine(
			$see ?? $this->see(),
			$kontingente ?? $this->volle_kontingente(),
			$belegungen,
			$blockaden,
			$nachttermine,
			$heute
		);
	}

	// --- Saison + Buchungsfenster + aktiv ---

	public function test_innerhalb_saison_und_fenster_buchbar(): void {
		self::assertTrue( $this->engine()->ist_buchbar_am( self::FREITAG ) );
	}

	public function test_vor_und_nach_saison_nicht_buchbar(): void {
		$see    = $this->see(
			array(
				'saison_von' => '2026-07-01',
				'saison_bis' => '2026-07-31',
			)
		);
		$engine = $this->engine( $see );
		self::assertFalse( $engine->ist_in_saison( '2026-06-30' ) );
		self::assertTrue( $engine->ist_in_saison( '2026-07-01' ) );
		self::assertTrue( $engine->ist_in_saison( '2026-07-31' ) );
		self::assertFalse( $engine->ist_in_saison( '2026-08-01' ) );
	}

	public function test_ohne_saisongrenzen_ganzjaehrig(): void {
		$see = $this->see(
			array(
				'saison_von' => null,
				'saison_bis' => null,
			)
		);
		self::assertTrue( $this->engine( $see )->ist_in_saison( '2026-01-01' ) );
	}

	public function test_buchungsfenster_grenzen(): void {
		$engine = $this->engine(); // 4 Wochen ab 2026-06-10.
		self::assertFalse( $engine->ist_im_buchungsfenster( '2026-06-09' ) ); // Vergangenheit.
		self::assertTrue( $engine->ist_im_buchungsfenster( self::HEUTE ) ); // Heute buchbar.
		self::assertTrue( $engine->ist_im_buchungsfenster( '2026-07-08' ) ); // Genau +28 Tage.
		self::assertFalse( $engine->ist_im_buchungsfenster( '2026-07-09' ) ); // Einen Tag drüber.
	}

	public function test_inaktiver_see_nie_buchbar(): void {
		$engine = $this->engine( $this->see( array( 'aktiv' => false ) ) );
		self::assertFalse( $engine->ist_buchbar_am( self::FREITAG ) );
		self::assertSame( 0, $engine->rest_fuer_stunde( self::FREITAG, 10 ) );
	}

	// --- Öffnungszeiten Woche/Wochenende + Nachttermine ---

	public function test_oeffnungszeiten_woche_vs_wochenende(): void {
		$engine = $this->engine();
		self::assertSame( range( 9, 19 ), $engine->buchbare_stunden( self::FREITAG ) );
		self::assertSame( range( 8, 17 ), $engine->buchbare_stunden( self::SAMSTAG ) );
	}

	public function test_ausserhalb_oeffnungszeiten_rest_null(): void {
		$engine = $this->engine();
		self::assertSame( 0, $engine->rest_fuer_stunde( self::FREITAG, 8 ) ); // Woche öffnet erst um 9.
		self::assertSame( 0, $engine->rest_fuer_stunde( self::FREITAG, 20 ) ); // stunde_bis exklusiv.
		self::assertSame( 10, $engine->rest_fuer_stunde( self::FREITAG, 19 ) ); // Letzter Slot.
	}

	public function test_nachttermin_schaltet_stunden_frei(): void {
		$ohne = $this->engine();
		$mit  = $this->engine( null, null, array(), array(), array( self::FREITAG => array( 21, 24 ) ) );

		self::assertSame( 0, $ohne->rest_fuer_stunde( self::FREITAG, 22 ) );
		self::assertSame( 10, $mit->rest_fuer_stunde( self::FREITAG, 22 ) );
		self::assertContains( 23, $mit->buchbare_stunden( self::FREITAG ) );
		// Nur am Termin-Datum, nicht an anderen Tagen.
		self::assertSame( 0, $mit->rest_fuer_stunde( self::SAMSTAG, 22 ) );
	}

	// --- Kontingent + Belegung ---

	public function test_belegung_reduziert_rest(): void {
		$engine = $this->engine( null, null, array( self::FREITAG => array( 10 => 7 ) ) );
		self::assertSame( 3, $engine->rest_fuer_stunde( self::FREITAG, 10 ) );
		self::assertSame( 10, $engine->rest_fuer_stunde( self::FREITAG, 11 ) ); // Andere Stunde unberührt.
	}

	public function test_ueberbelegung_klemmt_bei_null(): void {
		$engine = $this->engine( null, null, array( self::FREITAG => array( 10 => 99 ) ) );
		self::assertSame( 0, $engine->rest_fuer_stunde( self::FREITAG, 10 ) );
	}

	public function test_ohne_kontingentzeile_rest_null(): void {
		$engine = $this->engine( null, array() ); // Keine Kontingente gepflegt.
		self::assertSame( 0, $engine->rest_fuer_stunde( self::FREITAG, 10 ) );
	}

	// --- Blockaden ---

	public function test_blockade_ganzer_tag_sperrt_alle_stunden(): void {
		$blockade = array(
			'datum'          => self::FREITAG,
			'ganzer_tag'     => true,
			'stunde_von'     => null,
			'stunde_bis'     => null,
			'anzahl_taucher' => null,
		);
		$engine   = $this->engine( null, null, array(), array( $blockade ) );
		self::assertSame( 0, $engine->rest_fuer_stunde( self::FREITAG, 10 ) );
		self::assertSame( 0, $engine->rest_fuer_stunde( self::FREITAG, 19 ) );
		self::assertSame( 10, $engine->rest_fuer_stunde( self::SAMSTAG, 10 ) ); // Anderer Tag frei.
	}

	public function test_stundenblockade_mit_anzahl_reduziert_nur_ihre_stunden(): void {
		$blockade = array(
			'datum'          => self::FREITAG,
			'ganzer_tag'     => false,
			'stunde_von'     => 9,
			'stunde_bis'     => 12,
			'anzahl_taucher' => 6,
		);
		$engine   = $this->engine( null, null, array(), array( $blockade ) );
		self::assertSame( 4, $engine->rest_fuer_stunde( self::FREITAG, 9 ) );
		self::assertSame( 4, $engine->rest_fuer_stunde( self::FREITAG, 11 ) );
		self::assertSame( 10, $engine->rest_fuer_stunde( self::FREITAG, 12 ) ); // stunde_bis exklusiv.
	}

	public function test_stundenblockade_ohne_anzahl_sperrt_ihre_stunden(): void {
		$blockade = array(
			'datum'          => self::FREITAG,
			'ganzer_tag'     => false,
			'stunde_von'     => 9,
			'stunde_bis'     => 12,
			'anzahl_taucher' => null,
		);
		$engine   = $this->engine( null, null, array(), array( $blockade ) );
		self::assertSame( 0, $engine->rest_fuer_stunde( self::FREITAG, 10 ) );
		self::assertSame( 10, $engine->rest_fuer_stunde( self::FREITAG, 12 ) );
	}

	// --- Tagessee ---

	private function tagessee_kontingente( int $max = 30 ): array {
		$kontingente = array();
		foreach ( range( 1, 7 ) as $wochentag ) {
			$kontingente[] = array(
				'wochentag'   => $wochentag,
				'stunde'      => null,
				'max_taucher' => $max,
			);
		}
		return $kontingente;
	}

	public function test_tagessee_rest_und_belegung(): void {
		$see    = $this->see( array( 'modus' => Seemodus::TAG ) );
		$engine = $this->engine(
			$see,
			$this->tagessee_kontingente(),
			array( self::FREITAG => array( VerfuegbarkeitsEngine::TAGESSCHLUESSEL => 12 ) )
		);
		self::assertSame( 18, $engine->rest_fuer_tag( self::FREITAG ) );
		self::assertSame( 30, $engine->rest_fuer_tag( self::SAMSTAG ) );
	}

	public function test_tagessee_blockade_zaehlt_fuer_ganzen_tag(): void {
		$see      = $this->see( array( 'modus' => Seemodus::TAG ) );
		$blockade = array(
			'datum'          => self::FREITAG,
			'ganzer_tag'     => false, // Auch Stundenblockade trifft den ganzen Tag.
			'stunde_von'     => 9,
			'stunde_bis'     => 12,
			'anzahl_taucher' => 10,
		);
		$engine   = $this->engine( $see, $this->tagessee_kontingente(), array(), array( $blockade ) );
		self::assertSame( 20, $engine->rest_fuer_tag( self::FREITAG ) );
	}

	public function test_modus_grenzen(): void {
		$stundensee = $this->engine();
		self::assertSame( 0, $stundensee->rest_fuer_tag( self::FREITAG ) ); // Kein Tageskontingent am Stundensee.

		$tagessee = $this->engine( $this->see( array( 'modus' => Seemodus::TAG ) ), $this->tagessee_kontingente() );
		self::assertSame( array(), $tagessee->buchbare_stunden( self::FREITAG ) );
		self::assertSame( 0, $tagessee->rest_fuer_stunde( self::FREITAG, 10 ) );
	}

	// --- Tagesübersicht ---

	public function test_tagesuebersicht_stundensee(): void {
		$engine     = $this->engine( null, null, array( self::FREITAG => array( 10 => 7 ) ) );
		$uebersicht = $engine->tagesuebersicht( self::FREITAG );
		self::assertSame( range( 9, 19 ), array_keys( $uebersicht ) );
		self::assertSame( 3, $uebersicht[10] );
		self::assertSame( 10, $uebersicht[9] );
	}

	public function test_tagesuebersicht_tagessee(): void {
		$see    = $this->see( array( 'modus' => Seemodus::TAG ) );
		$engine = $this->engine( $see, $this->tagessee_kontingente() );
		self::assertSame( array( VerfuegbarkeitsEngine::TAGESSCHLUESSEL => 30 ), $engine->tagesuebersicht( self::FREITAG ) );
	}
}
