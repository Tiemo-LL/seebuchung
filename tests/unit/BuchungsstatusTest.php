<?php

namespace Seebuchung\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Seebuchung\Domain\Buchungsstatus;

final class BuchungsstatusTest extends TestCase {

	public function test_sechs_stati_ohne_duplikate(): void {
		self::assertCount( 6, Buchungsstatus::ALLE );
		self::assertSame( Buchungsstatus::ALLE, array_unique( Buchungsstatus::ALLE ) );
	}

	public function test_stornierte_und_verfallene_belegen_kein_kontingent(): void {
		self::assertNotContains( Buchungsstatus::STORNIERT, Buchungsstatus::BELEGT_KONTINGENT );
		self::assertNotContains( Buchungsstatus::VERFALLEN, Buchungsstatus::BELEGT_KONTINGENT );
		self::assertContains( Buchungsstatus::ANGEFRAGT, Buchungsstatus::BELEGT_KONTINGENT );
	}
}
