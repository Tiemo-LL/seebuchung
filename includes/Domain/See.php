<?php
/**
 * See-Datenobjekt.
 *
 * @package Seebuchung
 */

namespace Seebuchung\Domain;

/**
 * Unveränderliche Konfiguration eines Sees, wie die Engine sie braucht.
 *
 * Stunden sind volle Stunden 0–23; ein Slot "9" steht für 09:00–10:00 Uhr.
 * Saison-Daten im Format Y-m-d; null bei saison_von/saison_bis bedeutet
 * ganzjährig buchbar.
 */
final class See {

	/**
	 * Konstruktor.
	 *
	 * @param int         $id                     Datensatz-ID.
	 * @param string      $name                   Anzeigename.
	 * @param bool        $aktiv                  Nur aktive Seen sind buchbar.
	 * @param string      $modus                  Seemodus::TAG oder Seemodus::STUNDE.
	 * @param string|null $saison_von             Saisonbeginn (Y-m-d) oder null.
	 * @param string|null $saison_bis             Saisonende (Y-m-d) oder null.
	 * @param int         $buchungsfenster_wochen Buchbar bis X Wochen im Voraus.
	 * @param int|null    $stunde_von_woche       Erste buchbare Stunde Mo–Fr.
	 * @param int|null    $stunde_bis_woche       Ende der Buchbarkeit Mo–Fr (exklusiv).
	 * @param int|null    $stunde_von_wochenende  Erste buchbare Stunde Sa/So.
	 * @param int|null    $stunde_bis_wochenende  Ende der Buchbarkeit Sa/So (exklusiv).
	 * @param int         $min_anmelder           Mindestanzahl Taucher je Buchung.
	 * @param int         $max_pro_buchung        Maximale Taucher je Buchung.
	 * @param bool        $kostenpflichtig        Ob ALLE Taucher zahlungspflichtig sind (nicht nur Gäste ohne Verein).
	 * @param float       $preis_pro_person       Preis je zahlungspflichtiger Person.
	 * @param string      $info_text              Hinweistext fürs Frontend.
	 */
	public function __construct(
		public readonly int $id,
		public readonly string $name,
		public readonly bool $aktiv,
		public readonly string $modus,
		public readonly ?string $saison_von,
		public readonly ?string $saison_bis,
		public readonly int $buchungsfenster_wochen,
		public readonly ?int $stunde_von_woche,
		public readonly ?int $stunde_bis_woche,
		public readonly ?int $stunde_von_wochenende,
		public readonly ?int $stunde_bis_wochenende,
		public readonly int $min_anmelder,
		public readonly int $max_pro_buchung,
		public readonly bool $kostenpflichtig,
		public readonly float $preis_pro_person,
		public readonly string $info_text = ''
	) {
	}

	/**
	 * Baut das Objekt aus einer DB-Zeile (Array aus $wpdb->get_row(..., ARRAY_A)).
	 *
	 * @param array<string, mixed> $row Zeile aus seebuchung_seen.
	 */
	public static function from_row( array $row ): See {
		return new self(
			(int) $row['id'],
			(string) $row['name'],
			(bool) $row['aktiv'],
			(string) $row['modus'],
			$row['saison_von'] ?? null,
			$row['saison_bis'] ?? null,
			(int) $row['buchungsfenster_wochen'],
			isset( $row['stunde_von_woche'] ) ? (int) $row['stunde_von_woche'] : null,
			isset( $row['stunde_bis_woche'] ) ? (int) $row['stunde_bis_woche'] : null,
			isset( $row['stunde_von_wochenende'] ) ? (int) $row['stunde_von_wochenende'] : null,
			isset( $row['stunde_bis_wochenende'] ) ? (int) $row['stunde_bis_wochenende'] : null,
			(int) $row['min_anmelder'],
			(int) $row['max_pro_buchung'],
			(bool) $row['kostenpflichtig'],
			(float) $row['preis_pro_person'],
			(string) ( $row['info_text'] ?? '' )
		);
	}
}
