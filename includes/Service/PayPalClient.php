<?php
/**
 * PayPal-REST-Client (Orders v2).
 *
 * @package Seebuchung
 */

namespace Seebuchung\Service;

use Seebuchung\Settings;
use WP_Error;

/**
 * Schlanker Client für Orders v2 und Webhook-Verifikation über die
 * WP-HTTP-API — kein SDK nötig (IONOS-tauglich).
 */
class PayPalClient {

	/**
	 * Sind Zugangsdaten hinterlegt?
	 */
	public static function konfiguriert(): bool {
		return '' !== (string) Settings::get( 'paypal_client_id' ) && '' !== (string) Settings::get( 'paypal_secret' );
	}

	/**
	 * API-Basis je nach Sandbox-Modus.
	 */
	protected function basis_url(): string {
		return Settings::get( 'paypal_sandbox' )
			? 'https://api-m.sandbox.paypal.com'
			: 'https://api-m.paypal.com';
	}

	/**
	 * Order anlegen.
	 *
	 * @param float  $betrag     Betrag in EUR.
	 * @param string $referenz   Eigene Referenz (Buchungs-ID), landet in custom_id.
	 * @param string $return_url Rücksprung nach Freigabe.
	 * @param string $cancel_url Rücksprung bei Abbruch.
	 * @return array{id: string, approve_url: string}|WP_Error
	 */
	public function order_erstellen( float $betrag, string $referenz, string $return_url, string $cancel_url ) {
		$antwort = $this->request(
			'POST',
			'/v2/checkout/orders',
			array(
				'intent'              => 'CAPTURE',
				'purchase_units'      => array(
					array(
						'custom_id' => $referenz,
						'amount'    => array(
							'currency_code' => 'EUR',
							'value'         => number_format( $betrag, 2, '.', '' ),
						),
					),
				),
				'application_context' => array(
					'return_url'          => $return_url,
					'cancel_url'          => $cancel_url,
					'shipping_preference' => 'NO_SHIPPING',
					'user_action'         => 'PAY_NOW',
				),
			)
		);
		if ( $antwort instanceof WP_Error ) {
			return $antwort;
		}

		$approve = '';
		foreach ( (array) ( $antwort['links'] ?? array() ) as $link ) {
			if ( 'approve' === ( $link['rel'] ?? '' ) ) {
				$approve = (string) $link['href'];
			}
		}
		if ( '' === ( $antwort['id'] ?? '' ) || '' === $approve ) {
			return new WP_Error( 'seebuchung_paypal', __( 'PayPal-Order konnte nicht angelegt werden.', 'seebuchung' ) );
		}

		return array(
			'id'          => (string) $antwort['id'],
			'approve_url' => $approve,
		);
	}

	/**
	 * Order einziehen (Capture).
	 *
	 * @param string $order_id PayPal-Order-ID.
	 * @return array{capture_id: string, custom_id: string, status: string}|WP_Error
	 */
	public function order_capture( string $order_id ) {
		$antwort = $this->request( 'POST', '/v2/checkout/orders/' . rawurlencode( $order_id ) . '/capture', new \stdClass() );
		if ( $antwort instanceof WP_Error ) {
			return $antwort;
		}

		$unit    = $antwort['purchase_units'][0] ?? array();
		$capture = $unit['payments']['captures'][0] ?? array();

		return array(
			'capture_id' => (string) ( $capture['id'] ?? '' ),
			'custom_id'  => (string) ( $capture['custom_id'] ?? ( $unit['custom_id'] ?? '' ) ),
			'status'     => (string) ( $antwort['status'] ?? '' ),
		);
	}

	/**
	 * Webhook-Signatur verifizieren.
	 *
	 * @param array<string, string> $headers Request-Header (lowercase Keys).
	 * @param string                $body    Roher Request-Body.
	 * @return bool|WP_Error True bei gültiger Signatur.
	 */
	public function webhook_signatur_pruefen( array $headers, string $body ) {
		$webhook_id = (string) Settings::get( 'paypal_webhook_id' );
		if ( '' === $webhook_id ) {
			return new WP_Error( 'seebuchung_paypal', 'Keine Webhook-ID konfiguriert.' );
		}

		$antwort = $this->request(
			'POST',
			'/v1/notifications/verify-webhook-signature',
			array(
				'auth_algo'         => (string) ( $headers['paypal-auth-algo'] ?? '' ),
				'cert_url'          => (string) ( $headers['paypal-cert-url'] ?? '' ),
				'transmission_id'   => (string) ( $headers['paypal-transmission-id'] ?? '' ),
				'transmission_sig'  => (string) ( $headers['paypal-transmission-sig'] ?? '' ),
				'transmission_time' => (string) ( $headers['paypal-transmission-time'] ?? '' ),
				'webhook_id'        => $webhook_id,
				'webhook_event'     => json_decode( $body, true ),
			)
		);
		if ( $antwort instanceof WP_Error ) {
			return $antwort;
		}

		return 'SUCCESS' === ( $antwort['verification_status'] ?? '' );
	}

	/**
	 * Authentifizierter API-Request.
	 *
	 * @param string $methode HTTP-Methode.
	 * @param string $pfad    API-Pfad.
	 * @param mixed  $body    JSON-Body.
	 * @return array<string, mixed>|WP_Error
	 */
	protected function request( string $methode, string $pfad, $body ) {
		$token = $this->access_token();
		if ( $token instanceof WP_Error ) {
			return $token;
		}

		$antwort = wp_remote_request(
			$this->basis_url() . $pfad,
			array(
				'method'  => $methode,
				'timeout' => 20,
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $body ),
			)
		);
		if ( is_wp_error( $antwort ) ) {
			return $antwort;
		}

		$code  = (int) wp_remote_retrieve_response_code( $antwort );
		$daten = json_decode( wp_remote_retrieve_body( $antwort ), true );
		if ( $code >= 400 || ! is_array( $daten ) ) {
			return new WP_Error(
				'seebuchung_paypal',
				/* translators: %d: HTTP-Statuscode */
				sprintf( __( 'PayPal-Fehler (HTTP %d).', 'seebuchung' ), $code ),
				$daten
			);
		}

		return $daten;
	}

	/**
	 * OAuth-Token holen (transient-gecacht).
	 *
	 * @return string|WP_Error
	 */
	protected function access_token() {
		$cache = get_transient( 'seebuchung_paypal_token' );
		if ( is_string( $cache ) && '' !== $cache ) {
			return $cache;
		}

		$antwort = wp_remote_post(
			$this->basis_url() . '/v1/oauth2/token',
			array(
				'timeout' => 20,
				'headers' => array(
					// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- HTTP-Basic-Auth lt. PayPal-API.
					'Authorization' => 'Basic ' . base64_encode( Settings::get( 'paypal_client_id' ) . ':' . Settings::get( 'paypal_secret' ) ),
				),
				'body'    => array( 'grant_type' => 'client_credentials' ),
			)
		);
		if ( is_wp_error( $antwort ) ) {
			return $antwort;
		}

		$daten = json_decode( wp_remote_retrieve_body( $antwort ), true );
		$token = (string) ( $daten['access_token'] ?? '' );
		if ( '' === $token ) {
			return new WP_Error( 'seebuchung_paypal', __( 'PayPal-Anmeldung fehlgeschlagen — Zugangsdaten prüfen.', 'seebuchung' ) );
		}

		set_transient( 'seebuchung_paypal_token', $token, max( 60, (int) ( $daten['expires_in'] ?? 300 ) - 60 ) );
		return $token;
	}
}
