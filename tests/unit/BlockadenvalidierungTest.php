<?php

namespace Seebuchung\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Seebuchung\Domain\Blockadenvalidierung as BV;

final class BlockadenvalidierungTest extends TestCase {

	// --- Antragsfenster (1.10.–1.2., über den Jahreswechsel) ---

	public function test_fenster_ueber_jahreswechsel(): void {
		self::assertTrue( BV::im_antragsfenster( '10-01', '10-01', '02-01' ) );
		self::assertTrue( BV::im_antragsfenster( '12-24', '10-01', '02-01' ) );
		self::assertTrue( BV::im_antragsfenster( '01-15', '10-01', '02-01' ) );
		self::assertTrue( BV::im_antragsfenster( '02-01', '10-01', '02-01' ) );
		self::assertFalse( BV::im_antragsfenster( '02-02', '10-01', '02-01' ) );
		self::assertFalse( BV::im_antragsfenster( '06-11', '10-01', '02-01' ) );
		self::assertFalse( BV::im_antragsfenster( '09-30', '10-01', '02-01' ) );
	}

	public function test_fenster_ohne_jahreswechsel(): void {
		self::assertTrue( BV::im_antragsfenster( '05-15', '05-01', '06-01' ) );
		self::assertFalse( BV::im_antragsfenster( '06-02', '05-01', '06-01' ) );
	}

	public function test_zieljahr(): void {
		self::assertSame( 2027, BV::zieljahr( '2026-11-15', '10-01' ) ); // Herbst → Folgejahr.
		self::assertSame( 2027, BV::zieljahr( '2027-01-15', '10-01' ) ); // Januar → laufendes Jahr.
	}

	// --- Antragsregeln (heute = 15.11.2026 → Zieljahr 2027) ---

	private function pruefen( array $overrides = array() ): array {
		$defaults = array(
			'heute'              => '2026-11-15',
			'fenster_von'        => '10-01',
			'fenster_bis'        => '02-01',
			'datum'              => '2027-05-08',
			'ganzer_tag'         => false,
			'stunde_von'         => 9,
			'stunde_bis'         => 12,
			'ist_tagessee'       => false,
			'bestehende_im_jahr' => 0,
		);
		$werte    = array_merge( $defaults, $overrides );
		return BV::pruefen( ...array_values( $werte ) );
	}

	public function test_gueltiger_antrag(): void {
		self::assertSame( array(), $this->pruefen() );
	}

	public function test_ausserhalb_fenster_sofort_abbruch(): void {
		self::assertSame( array( BV::FEHLER_FENSTER_ZU ), $this->pruefen( array( 'heute' => '2026-06-11' ) ) );
	}

	public function test_datum_muss_im_zieljahr_liegen(): void {
		self::assertContains( BV::FEHLER_FALSCHES_JAHR, $this->pruefen( array( 'datum' => '2026-12-30' ) ) );
		self::assertContains( BV::FEHLER_FALSCHES_JAHR, $this->pruefen( array( 'datum' => '2028-05-08' ) ) );
	}

	public function test_max_zwei_pro_jahr_see_verein(): void {
		self::assertSame( array(), $this->pruefen( array( 'bestehende_im_jahr' => 1 ) ) );
		self::assertContains( BV::FEHLER_MAX_PRO_JAHR, $this->pruefen( array( 'bestehende_im_jahr' => 2 ) ) );
	}

	public function test_max_drei_stunden(): void {
		self::assertSame( array(), $this->pruefen( array( 'stunde_bis' => 12 ) ) ); // 9–12 = 3 Std.
		self::assertContains( BV::FEHLER_ZU_LANG, $this->pruefen( array( 'stunde_bis' => 13 ) ) );
	}

	public function test_unsinniger_zeitraum(): void {
		self::assertContains( BV::FEHLER_ZEITRAUM, $this->pruefen( array( 'stunde_bis' => 9 ) ) );
		self::assertContains(
			BV::FEHLER_ZEITRAUM,
			$this->pruefen(
				array(
					'stunde_von' => null,
					'stunde_bis' => null,
				)
			)
		);
	}

	public function test_tagessee_braucht_keine_stunden(): void {
		$fehler = $this->pruefen(
			array(
				'ist_tagessee' => true,
				'stunde_von'   => null,
				'stunde_bis'   => null,
			)
		);
		self::assertSame( array(), $fehler );
	}

	public function test_ganzer_tag_am_stundensee_ohne_stundenpruefung(): void {
		$fehler = $this->pruefen(
			array(
				'ganzer_tag' => true,
				'stunde_von' => null,
				'stunde_bis' => null,
			)
		);
		self::assertSame( array(), $fehler );
	}
}
