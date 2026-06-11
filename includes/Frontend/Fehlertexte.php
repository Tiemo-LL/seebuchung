<?php
/**
 * Fehlercodes → Nutzertexte.
 *
 * @package Seebuchung
 */

namespace Seebuchung\Frontend;

use Seebuchung\Domain\Buchungsvalidierung;

/**
 * Übersetzt die Fehlercodes der Buchungsvalidierung in verständliche Sätze.
 */
final class Fehlertexte {

	/**
	 * Texte zu einer Liste von Fehlercodes.
	 *
	 * @param string[] $codes Fehlercodes.
	 * @return string[] Übersetzte Texte (unbekannte Codes werden übersprungen).
	 */
	public static function zu_codes( array $codes ): array {
		$texte = array(
			Buchungsvalidierung::FEHLER_NICHT_BUCHBAR    => __( 'Dieses Datum ist nicht buchbar (Saison oder Buchungsfenster).', 'seebuchung' ),
			Buchungsvalidierung::FEHLER_STUNDE_FEHLT     => __( 'Bitte wähle eine Uhrzeit.', 'seebuchung' ),
			Buchungsvalidierung::FEHLER_KEIN_KONTINGENT  => __( 'Für diesen Termin sind nicht mehr genug Plätze frei.', 'seebuchung' ),
			Buchungsvalidierung::FEHLER_ANZAHL_MIN       => __( 'Die Gruppe ist zu klein für diesen See.', 'seebuchung' ),
			Buchungsvalidierung::FEHLER_ANZAHL_MAX       => __( 'Die Gruppe ist zu groß für eine Buchung.', 'seebuchung' ),
			Buchungsvalidierung::FEHLER_ZAHLER_ANZAHL    => __( 'Die Zahl der zahlungspflichtigen Taucher passt nicht zur Gruppengröße.', 'seebuchung' ),
			Buchungsvalidierung::FEHLER_ZAHLER_ERFORDERLICH => __( 'Ohne Vereins-Nr. ist mindestens ein zahlungspflichtiger Taucher anzugeben.', 'seebuchung' ),
			Buchungsvalidierung::FEHLER_VEREIN_UNBEKANNT => __( 'Diese Vereins-Nr. ist nicht bekannt.', 'seebuchung' ),
			Buchungsvalidierung::FEHLER_DOPPELBUCHUNG    => __( 'Für diese E-Mail-Adresse existiert an dem Tag bereits eine Buchung.', 'seebuchung' ),
			Buchungsvalidierung::FEHLER_ALLE_ZAHLER      => __( 'An diesem See sind alle Taucher zahlungspflichtig.', 'seebuchung' ),
			'email'                                      => __( 'Bitte gib eine gültige E-Mail-Adresse an.', 'seebuchung' ),
			'see'                                        => __( 'Dieser See existiert nicht.', 'seebuchung' ),
		);

		$ergebnis = array();
		foreach ( $codes as $code ) {
			if ( isset( $texte[ $code ] ) ) {
				$ergebnis[] = $texte[ $code ];
			}
		}
		return $ergebnis;
	}
}
