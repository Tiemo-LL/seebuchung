<?php
/**
 * Rate-Limiting für öffentliche Endpoints.
 *
 * @package Seebuchung
 */

namespace Seebuchung\Frontend;

/**
 * Einfaches transient-basiertes Limit je IP und Aktion (Konvention aus
 * CLAUDE.md: öffentliche Formulare gegen Missbrauch drosseln).
 */
final class RateLimiter {

	/**
	 * Aktion erlauben? Zählt den Aufruf mit.
	 *
	 * @param string $aktion          Aktionsname (z. B. 'buchen').
	 * @param int    $max             Erlaubte Aufrufe im Fenster.
	 * @param int    $fenster_sekunden Fensterlänge in Sekunden.
	 */
	public static function erlaubt( string $aktion, int $max = 10, int $fenster_sekunden = 600 ): bool {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		if ( '' === $ip ) {
			return true;
		}

		$schluessel = 'seebuchung_rl_' . $aktion . '_' . md5( $ip );
		$anzahl     = (int) get_transient( $schluessel );
		if ( $anzahl >= $max ) {
			return false;
		}

		set_transient( $schluessel, $anzahl + 1, $fenster_sekunden );
		return true;
	}

	/**
	 * Mit Fehlerseite abbrechen, wenn das Limit erreicht ist.
	 *
	 * @param string $aktion Aktionsname.
	 */
	public static function pruefen_oder_abbrechen( string $aktion ): void {
		if ( ! self::erlaubt( $aktion ) ) {
			wp_die(
				esc_html__( 'Zu viele Anfragen — bitte warte ein paar Minuten und versuche es erneut.', 'seebuchung' ),
				esc_html__( 'Zu viele Anfragen', 'seebuchung' ),
				array( 'response' => 429 )
			);
		}
	}
}
