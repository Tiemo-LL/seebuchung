<?php

namespace Seebuchung\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Seebuchung\Import\AltMapper;
use Seebuchung\Domain\Seemodus;

final class AltMapperTest extends TestCase {

	/**
	 * Echte Jägerweiher-Zeile aus dem Dump (gekürzt um irrelevante Kontakt-IDs).
	 */
	private function jaegerweiher(): array {
		return array(
			'see_id'                  => '1',
			'isActive'                => '1',
			'name'                    => 'Jaegerweiher',
			'vorOrtKontakt_id'        => '30',
			'ansprechpartner_id'      => '36',
			'kontaktKopie_id'         => '36',
			'wochenImVorraus'         => '2',
			'minAnmelder'             => '1',
			'uhrzeitBeginnWoche'      => '09:00:00',
			'uhrzeitEndeWoche'        => '20:00:00',
			'buchbarProTag'           => '1',
			'uhrzeitBeginnWochenende' => '09:00:00',
			'uhrzeitEndeWochenende'   => '20:00:00',
			'saisonStart'             => '2026-04-01',
			'saisonEnde'              => '2026-10-31',
			'maxPerOrder'             => '99',
			'info'                    => '',
			'prizePerPerson'          => '4.5',
			'isKostenpflichtig'       => '0',
			'bankaccount'             => 'LVST…',
		);
	}

	public function test_see_mapping_tagessee(): void {
		$neu = AltMapper::see( $this->jaegerweiher() );

		self::assertSame( 1, $neu['id'] );
		self::assertSame( Seemodus::TAG, $neu['modus'] );
		self::assertSame( 1, $neu['aktiv'] );
		self::assertSame( 'jaegerweiher', $neu['slug'] );
		self::assertSame( 9, $neu['stunde_von_woche'] );
		self::assertSame( 20, $neu['stunde_bis_woche'] );
		self::assertSame( 2, $neu['buchungsfenster_wochen'] );
		self::assertSame( 4.5, $neu['preis_pro_person'] );
		self::assertSame( 0, $neu['kostenpflichtig'] );
		self::assertArrayNotHasKey( 'bankaccount', $neu );
	}

	public function test_see_mapping_stundensee_und_umlaut_slug(): void {
		$alt                  = $this->jaegerweiher();
		$alt['name']          = 'Müßiger See';
		$alt['buchbarProTag'] = '0';

		$neu = AltMapper::see( $alt );
		self::assertSame( Seemodus::STUNDE, $neu['modus'] );
		self::assertSame( 'muessiger-see', $neu['slug'] );
	}

	private function maxfrei_zeile( array $stunden_werte, string $sum = '66' ): array {
		$zeile = array(
			'see_id'    => '2',
			'wochentag' => '5',
			'sumTag'    => $sum,
		);
		foreach ( range( 1, 24 ) as $stunde ) {
			$zeile[ sprintf( '%02d:00', $stunde ) ] = (string) ( $stunden_werte[ $stunde ] ?? 0 );
		}
		return $zeile;
	}

	public function test_kontingente_stundensee_nur_belegte_stunden(): void {
		$werte  = array_fill_keys( range( 9, 19 ), 6 );
		$zeilen = AltMapper::kontingente( $this->maxfrei_zeile( $werte ), false );

		self::assertCount( 11, $zeilen );
		self::assertSame(
			array(
				'see_id'      => 2,
				'wochentag'   => 5,
				'stunde'      => 9,
				'max_taucher' => 6,
			),
			$zeilen[0]
		);
		self::assertSame( 19, $zeilen[10]['stunde'] );
	}

	public function test_kontingente_tagessee_eine_zeile_mit_null_stunde(): void {
		$zeilen = AltMapper::kontingente( $this->maxfrei_zeile( array(), '30' ), true );

		self::assertCount( 1, $zeilen );
		self::assertNull( $zeilen[0]['stunde'] );
		self::assertSame( 30, $zeilen[0]['max_taucher'] );
	}

	public function test_kontingent_slot_24_wirft(): void {
		$this->expectException( \RuntimeException::class );
		AltMapper::kontingente( $this->maxfrei_zeile( array( 24 => 5 ) ), false );
	}

	public function test_verein_mit_verbandslookup(): void {
		$neu = AltMapper::verein(
			array(
				'verband_nr' => '09',
				'verein_nr'  => '0900123',
				'name'       => 'TSC Test',
				'stadt'      => 'Mainz',
			),
			array( '09' => 'LVST' )
		);

		self::assertSame( '0900123', $neu['nummer'] );
		self::assertSame( 'LVST', $neu['verband'] );
		self::assertSame( 'Mainz', $neu['stadt'] );
	}

	public function test_kein_verein_platzhalter_wird_uebersprungen(): void {
		self::assertFalse(
			AltMapper::verein_importierbar(
				array(
					'verband_nr' => '00',
					'verein_nr'  => '0000',
					'name'       => 'Kein Verein',
					'stadt'      => 'unbekannt',
				)
			)
		);
		self::assertTrue( AltMapper::verein_importierbar( array( 'verband_nr' => '09' ) ) );
	}

	public function test_brevet_mapping(): void {
		$neu = AltMapper::brevet(
			array(
				'brevet_id' => '7',
				'name'      => 'CMAS **',
			)
		);
		self::assertSame( 'CMAS **', $neu['bezeichnung'] );
		self::assertSame( 7, $neu['sortierung'] );
	}
}
