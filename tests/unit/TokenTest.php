<?php

namespace Seebuchung\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Seebuchung\Domain\Token;

final class TokenTest extends TestCase {

	public function test_token_format_und_eindeutigkeit(): void {
		$token = Token::generieren();
		self::assertSame( 64, strlen( $token ) );
		self::assertTrue( ctype_xdigit( $token ) );
		self::assertNotSame( $token, Token::generieren() );
	}

	public function test_hash_passt_zum_token(): void {
		$token = Token::generieren();
		$hash  = Token::hash( $token );

		self::assertSame( 64, strlen( $hash ) );
		self::assertNotSame( $token, $hash ); // Niemals Klartext speichern.
		self::assertTrue( Token::passt( $token, $hash ) );
		self::assertFalse( Token::passt( Token::generieren(), $hash ) );
	}
}
