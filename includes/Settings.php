<?php
/**
 * Plugin-Einstellungen über die Options-API.
 *
 * @package Seebuchung
 */

namespace Seebuchung;

/**
 * Zentraler Zugriff auf alle konfigurierbaren Werte (F7: nichts hartcodiert).
 *
 * Alle verbandsspezifischen Inhalte — Name, Kontakt, Gebührenlogik, Fristen,
 * PayPal-Zugangsdaten — liegen in einer einzigen Option als Array.
 */
final class Settings {

	/**
	 * Options-Schlüssel.
	 */
	private const OPTION = 'seebuchung_settings';

	/**
	 * Standardwerte für eine frische Installation.
	 *
	 * Antragsfenster-Werte im Format MM-TT (jahresunabhängig).
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults(): array {
		return array(
			'verbandsname'               => '',
			'verband_kurzname'           => '',
			'kontakt_email'              => '',
			'logo_attachment_id'         => 0,
			'bestaetigungsfrist_stunden' => 48,
			'anonymisierung_tage'        => 28,
			'antragsfenster_von'         => '10-01',
			'antragsfenster_bis'         => '02-01',
			'paypal_sandbox'             => true,
			'paypal_client_id'           => '',
			'paypal_secret'              => '',
		);
	}

	/**
	 * Alle Einstellungen (gespeicherte Werte über Defaults gemerged).
	 *
	 * @return array<string, mixed>
	 */
	public static function all(): array {
		$gespeichert = get_option( self::OPTION, array() );
		if ( ! is_array( $gespeichert ) ) {
			$gespeichert = array();
		}
		return array_merge( self::defaults(), $gespeichert );
	}

	/**
	 * Einzelne Einstellung lesen.
	 *
	 * @param string $key Schlüssel aus defaults().
	 * @return mixed Null, wenn der Schlüssel unbekannt ist.
	 */
	public static function get( string $key ) {
		$alle = self::all();
		return $alle[ $key ] ?? null;
	}

	/**
	 * Einstellungen (teilweise) aktualisieren.
	 *
	 * @param array<string, mixed> $werte Zu setzende Schlüssel/Werte; unbekannte Schlüssel werden verworfen.
	 */
	public static function update( array $werte ): void {
		$erlaubt = array_intersect_key( $werte, self::defaults() );
		update_option( self::OPTION, array_merge( self::all(), $erlaubt ) );
	}
}
