<?php

namespace Seebuchung\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Seebuchung\Domain\Buchungsstatemachine;
use Seebuchung\Domain\Buchungsstatus;

final class BuchungsstatemachineTest extends TestCase {

	public function test_regulaerer_ablauf(): void {
		self::assertTrue( Buchungsstatemachine::erlaubt( Buchungsstatus::ANGEFRAGT, Buchungsstatus::BESTAETIGT ) );
		self::assertTrue( Buchungsstatemachine::erlaubt( Buchungsstatus::BESTAETIGT, Buchungsstatus::GUELTIG ) );
		self::assertTrue( Buchungsstatemachine::erlaubt( Buchungsstatus::GUELTIG, Buchungsstatus::KONTROLLIERT ) );
	}

	public function test_keine_abkuerzungen(): void {
		self::assertFalse( Buchungsstatemachine::erlaubt( Buchungsstatus::ANGEFRAGT, Buchungsstatus::GUELTIG ) );
		self::assertFalse( Buchungsstatemachine::erlaubt( Buchungsstatus::ANGEFRAGT, Buchungsstatus::KONTROLLIERT ) );
	}

	public function test_storno_aus_aktiven_stati(): void {
		foreach ( array( Buchungsstatus::ANGEFRAGT, Buchungsstatus::BESTAETIGT, Buchungsstatus::GUELTIG ) as $von ) {
			self::assertTrue( Buchungsstatemachine::erlaubt( $von, Buchungsstatus::STORNIERT ), $von );
		}
	}

	public function test_verfall_nur_vor_gueltigkeit(): void {
		self::assertTrue( Buchungsstatemachine::erlaubt( Buchungsstatus::ANGEFRAGT, Buchungsstatus::VERFALLEN ) );
		self::assertTrue( Buchungsstatemachine::erlaubt( Buchungsstatus::BESTAETIGT, Buchungsstatus::VERFALLEN ) );
		self::assertFalse( Buchungsstatemachine::erlaubt( Buchungsstatus::GUELTIG, Buchungsstatus::VERFALLEN ) );
	}

	public function test_endzustaende_sind_final(): void {
		foreach ( array( Buchungsstatus::KONTROLLIERT, Buchungsstatus::STORNIERT, Buchungsstatus::VERFALLEN ) as $endzustand ) {
			self::assertTrue( Buchungsstatemachine::ist_endzustand( $endzustand ), $endzustand );
			foreach ( Buchungsstatus::ALLE as $ziel ) {
				self::assertFalse( Buchungsstatemachine::erlaubt( $endzustand, $ziel ), "{$endzustand} → {$ziel}" );
			}
		}
	}

	public function test_unbekannter_status_erlaubt_nichts(): void {
		self::assertFalse( Buchungsstatemachine::erlaubt( 'quatsch', Buchungsstatus::BESTAETIGT ) );
	}
}
