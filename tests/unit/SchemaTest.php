<?php

namespace Seebuchung\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Seebuchung\Database\Schema;

/**
 * Prüft die CREATE-TABLE-Definitionen (pure Funktion, ohne WP/DB).
 */
final class SchemaTest extends TestCase {

	private const ERWARTETE_TABELLEN = array(
		'seen',
		'kontingente',
		'buchungen',
		'blockaden',
		'vereine',
		'brevets',
		'nachttermine',
	);

	private static array $statements;

	public static function setUpBeforeClass(): void {
		self::$statements = Schema::create_statements( 'wp_', 'DEFAULT CHARACTER SET utf8mb4' );
	}

	public function test_alle_tabellen_definiert(): void {
		self::assertSame( self::ERWARTETE_TABELLEN, array_keys( self::$statements ) );
	}

	public function test_prefix_und_dbdelta_format(): void {
		foreach ( self::$statements as $name => $sql ) {
			self::assertStringContainsString( "CREATE TABLE wp_seebuchung_{$name} (", $sql, $name );
			// dbDelta verlangt exakt zwei Leerzeichen nach PRIMARY KEY.
			self::assertStringContainsString( 'PRIMARY KEY  (id)', $sql, $name );
			self::assertStringContainsString( 'DEFAULT CHARACTER SET utf8mb4', $sql, $name );
		}
	}

	public function test_kontingente_als_zeilen_je_see_wochentag_stunde(): void {
		$sql = self::$statements['kontingente'];
		self::assertStringContainsString( 'wochentag tinyint', $sql );
		self::assertStringContainsString( 'stunde tinyint(3) unsigned DEFAULT NULL', $sql );
		self::assertStringContainsString( 'UNIQUE KEY see_tag_stunde (see_id,wochentag,stunde)', $sql );
	}

	public function test_buchungen_token_nur_als_hash(): void {
		$sql = self::$statements['buchungen'];
		self::assertStringContainsString( 'token_hash char(64) NOT NULL', $sql );
		self::assertStringContainsString( 'UNIQUE KEY token_hash (token_hash)', $sql );
		self::assertStringNotContainsString( ' token varchar', $sql );
	}

	public function test_buchungen_statemachine_spalten(): void {
		$sql = self::$statements['buchungen'];
		self::assertStringContainsString( "status varchar(20) NOT NULL DEFAULT 'angefragt'", $sql );
		foreach ( array( 'bestaetigt_am', 'storniert_am', 'kontrolliert_am', 'anonymisiert_am' ) as $spalte ) {
			self::assertStringContainsString( $spalte, $sql );
		}
	}

	public function test_vereine_token_gehasht_und_nummer_eindeutig(): void {
		$sql = self::$statements['vereine'];
		self::assertStringContainsString( 'token_hash char(64) DEFAULT NULL', $sql );
		self::assertStringContainsString( 'UNIQUE KEY nummer (nummer)', $sql );
	}
}
