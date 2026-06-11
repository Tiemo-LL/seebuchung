<?php
/**
 * Buchungs-Mails.
 *
 * @package Seebuchung
 */

namespace Seebuchung\Service;

use Seebuchung\Settings;

/**
 * Versendet die Buchungs-Mails über wp_mail().
 *
 * Alle Texte laufen durch Filter (seebuchung_mail_betreff,
 * seebuchung_mail_text), damit Verbände sie ohne Code-Änderung anpassen
 * können (F7).
 */
final class Mailer {

	/**
	 * Doppel-Opt-in: Bestätigungslink nach der Anfrage.
	 *
	 * @param array<string, mixed> $buchung Buchungszeile.
	 * @param string               $link    Buchungslink mit Klartext-Token.
	 */
	public function doppel_opt_in( array $buchung, string $link ): bool {
		$betreff = __( 'Bitte bestätige deine Tauchgang-Buchung', 'seebuchung' );
		$text    = sprintf(
			/* translators: 1: Vorname, 2: Datum, 3: Link */
			__(
				"Hallo %1\$s,\n\ndeine Buchung für den %2\$s ist eingegangen. Bitte bestätige sie über diesen Link:\n\n%3\$s\n\nOhne Bestätigung verfällt die Anfrage automatisch. Über denselben Link kannst du die Buchung jederzeit stornieren.",
				'seebuchung'
			),
			$buchung['vorname'],
			$buchung['datum'],
			$link
		);
		return $this->senden( $buchung, 'doppel_opt_in', $betreff, $text );
	}

	/**
	 * Buchung ist gültig.
	 *
	 * @param array<string, mixed> $buchung Buchungszeile.
	 * @param string               $link    Buchungslink (Storno, später QR).
	 */
	public function gueltig( array $buchung, string $link ): bool {
		$betreff = __( 'Deine Buchung ist gültig', 'seebuchung' );
		$text    = sprintf(
			/* translators: 1: Vorname, 2: Datum, 3: Link */
			__(
				"Hallo %1\$s,\n\ndeine Buchung für den %2\$s ist bestätigt und gültig. Details und Storno-Möglichkeit:\n\n%3\$s\n\nGut Luft!",
				'seebuchung'
			),
			$buchung['vorname'],
			$buchung['datum'],
			$link
		);
		return $this->senden( $buchung, 'gueltig', $betreff, $text );
	}

	/**
	 * Bestätigt, Zahlung steht aus (PayPal folgt in Phase 2).
	 *
	 * @param array<string, mixed> $buchung Buchungszeile.
	 * @param string               $link    Buchungslink.
	 */
	public function zahlung_offen( array $buchung, string $link ): bool {
		$betreff = __( 'Buchung bestätigt — Zahlung ausstehend', 'seebuchung' );
		$text    = sprintf(
			/* translators: 1: Vorname, 2: Datum, 3: Preis, 4: Link */
			__(
				"Hallo %1\$s,\n\ndeine Buchung für den %2\$s ist bestätigt. Es ist eine Gebühr von %3\$s € fällig; die Buchung wird mit Zahlungseingang gültig.\n\nDetails:\n%4\$s",
				'seebuchung'
			),
			$buchung['vorname'],
			$buchung['datum'],
			number_format_i18n( (float) $buchung['preis_gesamt'], 2 ),
			$link
		);
		return $this->senden( $buchung, 'zahlung_offen', $betreff, $text );
	}

	/**
	 * Stornobestätigung.
	 *
	 * @param array<string, mixed> $buchung Buchungszeile.
	 */
	public function storniert( array $buchung ): bool {
		$betreff = __( 'Deine Buchung wurde storniert', 'seebuchung' );
		$text    = sprintf(
			/* translators: 1: Vorname, 2: Datum */
			__(
				"Hallo %1\$s,\n\ndeine Buchung für den %2\$s wurde storniert. Bereits gezahlte Gebühren werden nicht erstattet.",
				'seebuchung'
			),
			$buchung['vorname'],
			$buchung['datum']
		);
		return $this->senden( $buchung, 'storniert', $betreff, $text );
	}

	/**
	 * Mail mit Filtern und Verbandsabsender verschicken.
	 *
	 * @param array<string, mixed> $buchung Buchungszeile.
	 * @param string               $typ     Mail-Typ für die Filter.
	 * @param string               $betreff Betreff.
	 * @param string               $text    Klartext-Inhalt.
	 */
	private function senden( array $buchung, string $typ, string $betreff, string $text ): bool {
		$verband = (string) Settings::get( 'verbandsname' );
		if ( '' !== $verband ) {
			$betreff = '[' . $verband . '] ' . $betreff;
		}

		/**
		 * Betreff einer Buchungs-Mail filtern.
		 *
		 * @param string               $betreff Vorgeschlagener Betreff.
		 * @param string               $typ     Mail-Typ (doppel_opt_in, gueltig, zahlung_offen, storniert).
		 * @param array<string, mixed> $buchung Buchungszeile.
		 */
		$betreff = (string) apply_filters( 'seebuchung_mail_betreff', $betreff, $typ, $buchung );

		/**
		 * Text einer Buchungs-Mail filtern.
		 *
		 * @param string               $text    Vorgeschlagener Text.
		 * @param string               $typ     Mail-Typ.
		 * @param array<string, mixed> $buchung Buchungszeile.
		 */
		$text = (string) apply_filters( 'seebuchung_mail_text', $text, $typ, $buchung );

		return wp_mail( (string) $buchung['email'], $betreff, $text );
	}
}
