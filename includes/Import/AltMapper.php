<?php
/**
 * Mapping Altsystem-Zeilen → neues Datenmodell.
 *
 * @package Seebuchung
 */

namespace Seebuchung\Import;

use Seebuchung\Domain\Seemodus;

/**
 * Übersetzt geparste Alt-Zeilen in Insert-Arrays für die neuen Tabellen.
 *
 * Pure Klasse (per Unit-Test abgedeckt). Erkenntnisse aus dem Alt-Schema:
 * - see.buchbarProTag = '1' → Tagessee, sonst Stundensee.
 * - maxfrei.wochentag ist bereits ISO (1 = Mo … 7 = So; Wochenend-Werte
 *   liegen auf 6/7).
 * - maxfrei-Spalte "HH:00" = Slot ab HH Uhr; sumTag = Tageskontingent.
 * - vereine.verein_nr beginnt mit der zweistelligen verband_nr und ist
 *   damit global eindeutig.
 *
 * Nicht übernommen: Bankverbindung (ersetzt PayPal), Kontakt-IDs am See
 * (folgen mit dem Rollen-Task), Befugnisse (vorerst außer Scope, siehe
 * TASKS.md).
 */
final class AltMapper {

	/**
	 * Spaltenreihenfolgen im Alt-Dump (v8.4).
	 */
	public const SPALTEN = array(
		'see'       => array( 'see_id', 'isActive', 'name', 'vorOrtKontakt_id', 'ansprechpartner_id', 'kontaktKopie_id', 'wochenImVorraus', 'minAnmelder', 'uhrzeitBeginnWoche', 'uhrzeitEndeWoche', 'buchbarProTag', 'uhrzeitBeginnWochenende', 'uhrzeitEndeWochenende', 'saisonStart', 'saisonEnde', 'maxPerOrder', 'info', 'prizePerPerson', 'isKostenpflichtig', 'bankaccount' ),
		'maxfrei'   => array( 'see_id', 'wochentag', 'sumTag', '01:00', '02:00', '03:00', '04:00', '05:00', '06:00', '07:00', '08:00', '09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00', '17:00', '18:00', '19:00', '20:00', '21:00', '22:00', '23:00', '24:00' ),
		'vereine'   => array( 'verband_nr', 'verein_nr', 'name', 'stadt' ),
		'verbaende' => array( 'verband_nr', 'name' ),
		'brevets'   => array( 'brevet_id', 'name' ),
	);

	/**
	 * Alt-See → Zeile für seebuchung_seen (ohne created_at/updated_at).
	 *
	 * @param array<string, string|null> $alt Geparste see-Zeile.
	 * @return array<string, mixed>
	 */
	public static function see( array $alt ): array {
		return array(
			'id'                     => (int) $alt['see_id'],
			'name'                   => (string) $alt['name'],
			'slug'                   => self::slug( (string) $alt['name'] ),
			'aktiv'                  => (int) ( '1' === $alt['isActive'] ),
			'modus'                  => '1' === $alt['buchbarProTag'] ? Seemodus::TAG : Seemodus::STUNDE,
			'saison_von'             => $alt['saisonStart'],
			'saison_bis'             => $alt['saisonEnde'],
			'buchungsfenster_wochen' => (int) $alt['wochenImVorraus'],
			'stunde_von_woche'       => self::stunde( $alt['uhrzeitBeginnWoche'] ),
			'stunde_bis_woche'       => self::stunde( $alt['uhrzeitEndeWoche'] ),
			'stunde_von_wochenende'  => self::stunde( $alt['uhrzeitBeginnWochenende'] ),
			'stunde_bis_wochenende'  => self::stunde( $alt['uhrzeitEndeWochenende'] ),
			'min_anmelder'           => max( 1, (int) $alt['minAnmelder'] ),
			'max_pro_buchung'        => (int) $alt['maxPerOrder'],
			'kostenpflichtig'        => (int) ( '1' === $alt['isKostenpflichtig'] ),
			'preis_pro_person'       => (float) $alt['prizePerPerson'],
			'info_text'              => (string) $alt['info'],
		);
	}

	/**
	 * Alt-maxfrei-Zeile → Kontingent-Zeilen (Stunden als Zeilen).
	 *
	 * Tagessee: eine Zeile mit stunde = null und max = sumTag.
	 * Stundensee: je Stunde mit Wert > 0 eine Zeile; "24:00" wäre Slot 24
	 * und ist im neuen Modell (0–23) nicht abbildbar — im Bestand überall 0,
	 * sonst schlägt der Import fehl.
	 *
	 * @param array<string, string|null> $alt        Geparste maxfrei-Zeile.
	 * @param bool                       $ist_tagessee Ob der zugehörige See Tagessee ist.
	 * @return array<int, array<string, int|null>>
	 * @throws \RuntimeException Wenn der Slot 24:00 belegt ist.
	 */
	public static function kontingente( array $alt, bool $ist_tagessee ): array {
		$see_id    = (int) $alt['see_id'];
		$wochentag = (int) $alt['wochentag'];

		if ( $ist_tagessee ) {
			return array(
				array(
					'see_id'      => $see_id,
					'wochentag'   => $wochentag,
					'stunde'      => null,
					'max_taucher' => (int) $alt['sumTag'],
				),
			);
		}

		if ( (int) $alt['24:00'] > 0 ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Pure Klasse ohne WP; see_id ist int.
			throw new \RuntimeException( "See {$see_id}: Kontingent im Slot 24:00 nicht abbildbar." );
		}

		$zeilen = array();
		foreach ( range( 1, 23 ) as $stunde ) {
			$max = (int) $alt[ sprintf( '%02d:00', $stunde ) ];
			if ( $max > 0 ) {
				$zeilen[] = array(
					'see_id'      => $see_id,
					'wochentag'   => $wochentag,
					'stunde'      => $stunde,
					'max_taucher' => $max,
				);
			}
		}
		return $zeilen;
	}

	/**
	 * Soll die Alt-Vereinszeile importiert werden?
	 *
	 * Der Platzhalter "Kein Verein" (verband_nr '00') wird im Neusystem
	 * über verein_id NULL abgebildet und daher übersprungen.
	 *
	 * @param array<string, string|null> $alt Geparste vereine-Zeile.
	 */
	public static function verein_importierbar( array $alt ): bool {
		return '00' !== $alt['verband_nr'];
	}

	/**
	 * Alt-Verein → Zeile für seebuchung_vereine.
	 *
	 * @param array<string, string|null> $alt       Geparste vereine-Zeile.
	 * @param array<string, string>      $verbaende verband_nr => Verbandsname.
	 * @return array<string, mixed>
	 */
	public static function verein( array $alt, array $verbaende ): array {
		return array(
			'nummer'  => (string) $alt['verein_nr'],
			'name'    => (string) $alt['name'],
			'stadt'   => (string) $alt['stadt'],
			'verband' => $verbaende[ $alt['verband_nr'] ] ?? (string) $alt['verband_nr'],
			'aktiv'   => 1,
		);
	}

	/**
	 * Alt-Brevet → Zeile für seebuchung_brevets.
	 *
	 * @param array<string, string|null> $alt Geparste brevets-Zeile.
	 * @return array<string, mixed>
	 */
	public static function brevet( array $alt ): array {
		return array(
			'bezeichnung' => (string) $alt['name'],
			'sortierung'  => (int) $alt['brevet_id'],
			'aktiv'       => 1,
		);
	}

	/**
	 * "HH:MM:SS" → volle Stunde, "00:00:00" → null (nicht gepflegt).
	 *
	 * @param string|null $zeit Alt-Zeitwert.
	 */
	private static function stunde( ?string $zeit ): ?int {
		if ( null === $zeit || '' === $zeit || str_starts_with( $zeit, '00:00' ) ) {
			return null;
		}
		return (int) substr( $zeit, 0, 2 );
	}

	/**
	 * Einfacher Slug (ohne WP-Abhängigkeit, damit pure testbar).
	 *
	 * @param string $name See-Name.
	 */
	private static function slug( string $name ): string {
		$slug = strtolower( str_replace( array( 'ä', 'ö', 'ü', 'ß' ), array( 'ae', 'oe', 'ue', 'ss' ), $name ) );
		$slug = (string) preg_replace( '/[^a-z0-9]+/', '-', $slug );
		return trim( $slug, '-' );
	}
}
