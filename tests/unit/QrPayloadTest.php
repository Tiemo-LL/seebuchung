<?php

namespace Seebuchung\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Seebuchung\Domain\QrPayload;

final class QrPayloadTest extends TestCase {

	private const SECRET = 'test-secret-1234';

	public function test_roundtrip(): void {
		$payload = QrPayload::erzeugen( 4711, self::SECRET );
		self::assertStringStartsWith( 'SB1:4711:', $payload );
		self::assertSame( 4711, QrPayload::pruefen( $payload, self::SECRET ) );
	}

	public function test_manipulierte_id_faellt_durch(): void {
		$payload     = QrPayload::erzeugen( 4711, self::SECRET );
		$manipuliert = str_replace( ':4711:', ':4712:', $payload );
		self::assertNull( QrPayload::pruefen( $manipuliert, self::SECRET ) );
	}

	public function test_falsches_secret_faellt_durch(): void {
		$payload = QrPayload::erzeugen( 4711, 'anderes-secret' );
		self::assertNull( QrPayload::pruefen( $payload, self::SECRET ) );
	}

	public function test_muell_faellt_durch(): void {
		self::assertNull( QrPayload::pruefen( '', self::SECRET ) );
		self::assertNull( QrPayload::pruefen( 'SB1:abc:def', self::SECRET ) );
		self::assertNull( QrPayload::pruefen( 'SB9:1:xyz', self::SECRET ) );
		self::assertNull( QrPayload::pruefen( 'https://example.org/boese', self::SECRET ) );
	}
}
