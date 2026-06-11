<?php
/**
 * POST-Handler für Buchung, Bestätigung und Storno.
 *
 * @package Seebuchung
 */

namespace Seebuchung\Frontend;

use Seebuchung\Service\Buchungsservice;
use WP_Error;

/**
 * Verarbeitet die Frontend-POSTs über admin-post.php (auch für Gäste)
 * und leitet per PRG-Pattern zurück auf die Buchungsseite.
 */
final class FormHandler {

	/**
	 * Hooks registrieren.
	 */
	public static function registrieren(): void {
		$handler = new self();
		add_action( 'admin_post_seebuchung_buchen', array( $handler, 'buchen' ) );
		add_action( 'admin_post_nopriv_seebuchung_buchen', array( $handler, 'buchen' ) );
		add_action( 'admin_post_seebuchung_token_aktion', array( $handler, 'token_aktion' ) );
		add_action( 'admin_post_nopriv_seebuchung_token_aktion', array( $handler, 'token_aktion' ) );
		add_action( 'admin_post_seebuchung_blockade_antrag', array( $handler, 'blockade_antrag' ) );
		add_action( 'admin_post_nopriv_seebuchung_blockade_antrag', array( $handler, 'blockade_antrag' ) );
	}

	/**
	 * Buchungsformular verarbeiten.
	 */
	public function buchen(): void {
		check_admin_referer( 'seebuchung_buchen' );
		RateLimiter::pruefen_oder_abbrechen( 'buchen' );

		$zurueck = $this->zurueck_url();

		$eingabe = array(
			'see_id'         => isset( $_POST['see_id'] ) ? (int) $_POST['see_id'] : 0,
			'datum'          => isset( $_POST['datum'] ) ? sanitize_text_field( wp_unslash( $_POST['datum'] ) ) : '',
			'stunde'         => isset( $_POST['stunde'] ) && '' !== $_POST['stunde'] ? (int) $_POST['stunde'] : null,
			'name'           => isset( $_POST['nachname'] ) ? sanitize_text_field( wp_unslash( $_POST['nachname'] ) ) : '',
			'vorname'        => isset( $_POST['vorname'] ) ? sanitize_text_field( wp_unslash( $_POST['vorname'] ) ) : '',
			'email'          => isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '',
			'telefon'        => isset( $_POST['telefon'] ) ? sanitize_text_field( wp_unslash( $_POST['telefon'] ) ) : '',
			'vereins_nummer' => isset( $_POST['vereins_nummer'] ) ? sanitize_text_field( wp_unslash( $_POST['vereins_nummer'] ) ) : '',
			'brevet_id'      => isset( $_POST['brevet_id'] ) ? (int) $_POST['brevet_id'] : 0,
			'anzahl_taucher' => isset( $_POST['anzahl_taucher'] ) ? (int) $_POST['anzahl_taucher'] : 0,
			'anzahl_zahler'  => isset( $_POST['anzahl_zahler'] ) ? (int) $_POST['anzahl_zahler'] : 0,
		);

		$ergebnis = ( new Buchungsservice() )->anlegen( $eingabe );

		if ( $ergebnis instanceof WP_Error ) {
			$codes = (array) $ergebnis->get_error_data();
			if ( array() === $codes ) {
				$codes = array( str_replace( 'seebuchung_', '', $ergebnis->get_error_code() ) );
			}
			$ziel = add_query_arg(
				array(
					'sb_ergebnis' => 'fehler',
					'sb_fehler'   => implode( ',', array_map( 'sanitize_key', $codes ) ),
					'sb_see'      => $eingabe['see_id'],
					'sb_datum'    => $eingabe['datum'],
					'sb_stunde'   => $eingabe['stunde'],
				),
				$zurueck
			);
		} else {
			$ziel = add_query_arg( 'sb_ergebnis', 'angefragt', $zurueck );
		}

		wp_safe_redirect( $ziel );
		exit;
	}

	/**
	 * Blockade-Antrag eines Vereins verarbeiten.
	 */
	public function blockade_antrag(): void {
		check_admin_referer( 'seebuchung_blockade_antrag' );
		RateLimiter::pruefen_oder_abbrechen( 'blockade' );

		$token   = isset( $_POST['vereins_token'] ) ? sanitize_text_field( wp_unslash( $_POST['vereins_token'] ) ) : '';
		$zurueck = add_query_arg( 'sb_verein', rawurlencode( $token ), $this->zurueck_url() );
		$service = new \Seebuchung\Service\Blockadenservice();
		$verein  = $service->verein_zum_token( $token );

		if ( null === $verein ) {
			wp_safe_redirect( $this->zurueck_url() );
			exit;
		}

		$eingabe = array(
			'see_id'           => isset( $_POST['see_id'] ) ? (int) $_POST['see_id'] : 0,
			'datum'            => isset( $_POST['datum'] ) ? sanitize_text_field( wp_unslash( $_POST['datum'] ) ) : '',
			'ganzer_tag'       => isset( $_POST['ganzer_tag'] ),
			'stunde_von'       => isset( $_POST['stunde_von'] ) && '' !== $_POST['stunde_von'] ? (int) $_POST['stunde_von'] : null,
			'stunde_bis'       => isset( $_POST['stunde_bis'] ) && '' !== $_POST['stunde_bis'] ? (int) $_POST['stunde_bis'] : null,
			'anzahl_taucher'   => isset( $_POST['anzahl_taucher'] ) ? (int) $_POST['anzahl_taucher'] : 0,
			'veranstaltung'    => isset( $_POST['veranstaltung'] ) ? sanitize_text_field( wp_unslash( $_POST['veranstaltung'] ) ) : '',
			'verantwortlicher' => isset( $_POST['verantwortlicher'] ) ? sanitize_text_field( wp_unslash( $_POST['verantwortlicher'] ) ) : '',
			'email'            => isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '',
			'telefon'          => isset( $_POST['telefon'] ) ? sanitize_text_field( wp_unslash( $_POST['telefon'] ) ) : '',
		);

		$ergebnis = $service->beantragen( $verein, $eingabe );

		if ( $ergebnis instanceof WP_Error ) {
			$codes = (array) $ergebnis->get_error_data();
			$ziel  = add_query_arg(
				array(
					'sb_ergebnis' => 'fehler',
					'sb_fehler'   => implode( ',', array_map( 'sanitize_key', $codes ) ),
				),
				$zurueck
			);
		} else {
			$ziel = add_query_arg( 'sb_ergebnis', 'blockade_beantragt', $zurueck );
		}

		wp_safe_redirect( $ziel );
		exit;
	}

	/**
	 * Bestätigen/Stornieren über die Token-Seite.
	 */
	public function token_aktion(): void {
		check_admin_referer( 'seebuchung_token_aktion' );
		RateLimiter::pruefen_oder_abbrechen( 'token_aktion' );

		$token   = isset( $_POST['token'] ) ? sanitize_text_field( wp_unslash( $_POST['token'] ) ) : '';
		$aktion  = isset( $_POST['aktion'] ) ? sanitize_key( wp_unslash( $_POST['aktion'] ) ) : '';
		$zurueck = $this->zurueck_url();
		$service = new Buchungsservice();

		$ergebnis = 'bestaetigen' === $aktion ? $service->bestaetigen( $token ) : $service->stornieren( $token );

		if ( $ergebnis instanceof WP_Error ) {
			$ziel = add_query_arg(
				array(
					'sb_token'    => rawurlencode( $token ),
					'sb_ergebnis' => 'fehler',
				),
				$zurueck
			);
		} else {
			$ziel = add_query_arg(
				array(
					'sb_token'    => rawurlencode( $token ),
					'sb_ergebnis' => (string) $ergebnis['status'],
				),
				$zurueck
			);
		}

		wp_safe_redirect( $ziel );
		exit;
	}

	/**
	 * Rücksprung-URL (Formularfeld, sonst Buchungsseite/Home).
	 */
	private function zurueck_url(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce wurde vom Aufrufer (buchen/token_aktion) geprüft.
		$kandidat = isset( $_POST['zurueck'] ) ? esc_url_raw( wp_unslash( $_POST['zurueck'] ) ) : '';
		if ( '' !== $kandidat && str_starts_with( $kandidat, home_url() ) ) {
			return $kandidat;
		}
		return home_url( '/' );
	}
}
