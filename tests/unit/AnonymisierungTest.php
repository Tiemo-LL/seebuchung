<?php

namespace Seebuchung\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Seebuchung\Domain\Anonymisierung;

final class AnonymisierungTest extends TestCase {

	public function test_stichtag_28_tage(): void {
		// Tauchtag 14.05. ist am 11.06. genau 28 Tage her → Stichtag 14.05.:
		// anonymisiert wird alles VOR dem 14.05., der 14.05. selbst noch nicht.
		self::assertSame( '2026-05-14', Anonymisierung::stichtag( '2026-06-11', 28 ) );
	}

	public function test_stichtag_ueber_jahreswechsel(): void {
		self::assertSame( '2026-12-19', Anonymisierung::stichtag( '2027-01-16', 28 ) );
	}

	public function test_negative_frist_klemmt_bei_null(): void {
		self::assertSame( '2026-06-11', Anonymisierung::stichtag( '2026-06-11', -5 ) );
	}
}
