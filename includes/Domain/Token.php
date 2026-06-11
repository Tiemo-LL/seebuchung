<?php
/**
 * Buchungs- und Vereins-Tokens.
 *
 * @package Seebuchung
 */

namespace Seebuchung\Domain;

/**
 * Tokens werden nur als SHA-256-Hash gespeichert; der Klartext existiert
 * ausschließlich im Mail-Link (Doppel-Opt-in, Storno, Vereins-Blockaden).
 */
final class Token {

	/**
	 * Neuen Token erzeugen (64 Hex-Zeichen, 256 Bit Entropie).
	 */
	public static function generieren(): string {
		return bin2hex( random_bytes( 32 ) );
	}

	/**
	 * Hash für die Speicherung.
	 *
	 * @param string $token Klartext-Token.
	 */
	public static function hash( string $token ): string {
		return hash( 'sha256', $token );
	}

	/**
	 * Klartext gegen gespeicherten Hash prüfen (timing-sicher).
	 *
	 * @param string $token Klartext-Token aus dem Link.
	 * @param string $hash  Gespeicherter Hash.
	 */
	public static function passt( string $token, string $hash ): bool {
		return hash_equals( $hash, self::hash( $token ) );
	}
}
