<?php
/**
 * PayPal-Checkout-Flow und Webhook.
 *
 * @package Seebuchung
 */

namespace Seebuchung\Frontend;

use Seebuchung\Domain\Buchungsstatus;
use Seebuchung\Service\Buchungsservice;
use Seebuchung\Service\PayPalClient;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Start (Order anlegen + Redirect zu PayPal), Return (Capture) und der
 * Webhook als Fallback, falls der Käufer den Rücksprung abbricht.
 */
final class PayPalHandler {

	/**
	 * Hooks registrieren.
	 */
	public static function registrieren(): void {
		$handler = new self();
		add_action( 'admin_post_seebuchung_paypal_start', array( $handler, 'start' ) );
		add_action( 'admin_post_nopriv_seebuchung_paypal_start', array( $handler, 'start' ) );
		add_action( 'admin_post_seebuchung_paypal_return', array( $handler, 'zurueck' ) );
		add_action( 'admin_post_nopriv_seebuchung_paypal_return', array( $handler, 'zurueck' ) );

		add_action(
			'rest_api_init',
			static function () use ( $handler ) {
				register_rest_route(
					'seebuchung/v1',
					'/paypal-webhook',
					array(
						'methods'             => 'POST',
						'callback'            => array( $handler, 'webhook' ),
						'permission_callback' => '__return_true', // Auth = PayPal-Signaturprüfung im Callback.
					)
				);
			}
		);
	}

	/**
	 * POST: Zahlung starten — Order anlegen, zu PayPal weiterleiten.
	 */
	public function start(): void {
		check_admin_referer( 'seebuchung_paypal_start' );

		$token   = isset( $_POST['token'] ) ? sanitize_text_field( wp_unslash( $_POST['token'] ) ) : '';
		$service = new Buchungsservice();
		$buchung = $service->per_token( $token );

		if ( $buchung instanceof WP_Error
			|| Buchungsstatus::BESTAETIGT !== $buchung['status']
			|| (float) ( $buchung['preis_gesamt'] ?? 0 ) <= 0 ) {
			wp_safe_redirect( Buchungsservice::buchungslink( $token ) );
			exit;
		}

		$return_url = add_query_arg(
			array(
				'action'   => 'seebuchung_paypal_return',
				'sb_token' => rawurlencode( $token ),
			),
			admin_url( 'admin-post.php' )
		);

		$order = ( new PayPalClient() )->order_erstellen(
			(float) $buchung['preis_gesamt'],
			(string) $buchung['id'],
			$return_url,
			Buchungsservice::buchungslink( $token )
		);

		if ( $order instanceof WP_Error ) {
			wp_safe_redirect( add_query_arg( 'sb_ergebnis', 'paypal_fehler', Buchungsservice::buchungslink( $token ) ) );
			exit;
		}

		// Externe Weiterleitung zu PayPal (kein wp_safe_redirect: fremde, aber bekannte Domain).
		wp_redirect( $order['approve_url'] ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
		exit;
	}

	/**
	 * GET: Rücksprung von PayPal — Capture ausführen.
	 */
	public function zurueck(): void {
		// PayPal hängt token=<order_id> an; sb_token ist unser Buchungslink-Token.
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Rücksprung von PayPal; Sicherheit über Order-Capture + custom_id-Abgleich.
		$sb_token = isset( $_GET['sb_token'] ) ? sanitize_text_field( wp_unslash( $_GET['sb_token'] ) ) : '';
		$order_id = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '';
		// phpcs:enable

		$service = new Buchungsservice();
		$buchung = $service->per_token( $sb_token );
		$ziel    = Buchungsservice::buchungslink( $sb_token );

		if ( $buchung instanceof WP_Error || '' === $order_id ) {
			wp_safe_redirect( $ziel );
			exit;
		}

		$capture = ( new PayPalClient() )->order_capture( $order_id );
		if ( $capture instanceof WP_Error
			|| 'COMPLETED' !== $capture['status']
			|| (string) $buchung['id'] !== $capture['custom_id'] ) {
			wp_safe_redirect( add_query_arg( 'sb_ergebnis', 'paypal_fehler', $ziel ) );
			exit;
		}

		$service->bezahlt_markieren( (int) $buchung['id'], $capture['capture_id'] );

		wp_safe_redirect( add_query_arg( 'sb_ergebnis', 'gueltig', $ziel ) );
		exit;
	}

	/**
	 * Webhook: PAYMENT.CAPTURE.COMPLETED als Fallback verarbeiten.
	 *
	 * @param WP_REST_Request $request Eingehender Webhook.
	 */
	public function webhook( WP_REST_Request $request ): WP_REST_Response {
		$client  = new PayPalClient();
		$headers = array_change_key_case( array_map( static fn ( $w ) => is_array( $w ) ? (string) $w[0] : (string) $w, $request->get_headers() ) );
		$gueltig = $client->webhook_signatur_pruefen( $headers, (string) $request->get_body() );

		if ( true !== $gueltig ) {
			return new WP_REST_Response( array( 'error' => 'signature' ), 403 );
		}

		$event = $request->get_json_params();
		if ( 'PAYMENT.CAPTURE.COMPLETED' === ( $event['event_type'] ?? '' ) ) {
			$buchungs_id = (int) ( $event['resource']['custom_id'] ?? 0 );
			$capture_id  = (string) ( $event['resource']['id'] ?? '' );
			if ( $buchungs_id > 0 && '' !== $capture_id ) {
				( new Buchungsservice() )->bezahlt_markieren( $buchungs_id, $capture_id );
			}
		}

		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}
}
