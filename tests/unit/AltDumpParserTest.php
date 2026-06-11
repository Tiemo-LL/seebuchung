<?php

namespace Seebuchung\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Seebuchung\Import\AltDumpParser;

final class AltDumpParserTest extends TestCase {

	public function test_mehrere_tupel_und_typen(): void {
		$dump   = "INSERT INTO `brevets` VALUES (1,'CMAS*'),(2,'CMAS**'),(3,NULL);";
		$zeilen = ( new AltDumpParser( $dump ) )->zeilen( 'brevets', array( 'brevet_id', 'name' ) );

		self::assertCount( 3, $zeilen );
		self::assertSame(
			array(
				'brevet_id' => '1',
				'name'      => 'CMAS*',
			),
			$zeilen[0]
		);
		self::assertNull( $zeilen[2]['name'] );
	}

	public function test_escapes_kommas_und_klammern_in_strings(): void {
		$dump   = "INSERT INTO `see` VALUES (1,'See \\'Schlicht\\', am Wald (RLP)\\nZeile2');";
		$zeilen = ( new AltDumpParser( $dump ) )->zeilen( 'see', array( 'id', 'name' ) );

		self::assertSame( "See 'Schlicht', am Wald (RLP)\nZeile2", $zeilen[0]['name'] );
	}

	public function test_mehrere_insert_statements_derselben_tabelle(): void {
		$dump   = "INSERT INTO `v` VALUES (1,'a');\nLOCK TABLES;\nINSERT INTO `v` VALUES (2,'b');";
		$zeilen = ( new AltDumpParser( $dump ) )->zeilen( 'v', array( 'id', 'x' ) );

		self::assertCount( 2, $zeilen );
		self::assertSame( '2', $zeilen[1]['id'] );
	}

	public function test_andere_tabellen_werden_ignoriert(): void {
		$dump   = "INSERT INTO `andere` VALUES (9,'x');\nINSERT INTO `v` VALUES (1,'a');";
		$zeilen = ( new AltDumpParser( $dump ) )->zeilen( 'v', array( 'id', 'x' ) );

		self::assertCount( 1, $zeilen );
	}

	public function test_spaltenzahl_mismatch_wirft(): void {
		$this->expectException( \RuntimeException::class );
		( new AltDumpParser( "INSERT INTO `v` VALUES (1,'a','zuviel');" ) )->zeilen( 'v', array( 'id', 'x' ) );
	}

	public function test_semikolon_in_string_beendet_statement_nicht(): void {
		$dump   = "INSERT INTO `v` VALUES (1,'a;b'),(2,'c');";
		$zeilen = ( new AltDumpParser( $dump ) )->zeilen( 'v', array( 'id', 'x' ) );

		self::assertCount( 2, $zeilen );
		self::assertSame( 'a;b', $zeilen[0]['x'] );
	}
}
