<?php

namespace Seebuchung\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Smoke-Test: Plugin-Header konsistent zum Projekt (Slug, Textdomain, Versionen).
 */
final class PluginHeaderTest extends TestCase {

	private static string $header;

	public static function setUpBeforeClass(): void {
		$bootstrap_file = dirname( __DIR__, 2 ) . '/seebuchung.php';
		self::assertFileExists( $bootstrap_file );
		self::$header = (string) file_get_contents( $bootstrap_file );
	}

	public function test_plugin_name_ist_seebuchung(): void {
		self::assertMatchesRegularExpression( '/^ \* Plugin Name:\s+Seebuchung$/m', self::$header );
	}

	public function test_textdomain_ist_seebuchung(): void {
		self::assertMatchesRegularExpression( '/^ \* Text Domain:\s+seebuchung$/m', self::$header );
	}

	public function test_php_mindestversion_8_1(): void {
		self::assertMatchesRegularExpression( '/^ \* Requires PHP:\s+8\.1$/m', self::$header );
	}

	public function test_version_konsistent_mit_konstante(): void {
		preg_match( '/^ \* Version:\s+(?<version>[\d.]+)$/m', self::$header, $header_match );
		preg_match( "/define\( 'SEEBUCHUNG_VERSION', '(?<version>[\d.]+)' \)/", self::$header, $constant_match );

		self::assertNotEmpty( $header_match['version'] ?? '' );
		self::assertSame( $header_match['version'], $constant_match['version'] ?? null );
	}
}
