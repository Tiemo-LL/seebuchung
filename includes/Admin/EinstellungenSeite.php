<?php
/**
 * Admin: Plugin-Einstellungen.
 *
 * @package Seebuchung
 */

namespace Seebuchung\Admin;

use Seebuchung\Rollen;
use Seebuchung\Settings;

/**
 * Verbandsdaten, Fristen, Buchungsseite und PayPal-Zugangsdaten (F7:
 * alles konfigurierbar, nichts hartcodiert).
 */
final class EinstellungenSeite {

	/**
	 * POST-Aktion registrieren.
	 */
	public static function aktionen_registrieren(): void {
		add_action( 'admin_post_seebuchung_einstellungen', array( new self(), 'speichern' ) );
	}

	/**
	 * Seite rendern.
	 */
	public function render(): void {
		$werte = Settings::all();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nur Erfolgsmeldung.
		$gespeichert = isset( $_GET['sb_gespeichert'] );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Seebuchung-Einstellungen', 'seebuchung' ); ?></h1>
			<?php if ( $gespeichert ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Einstellungen gespeichert.', 'seebuchung' ); ?></p></div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'seebuchung_einstellungen' ); ?>
				<input type="hidden" name="action" value="seebuchung_einstellungen">

				<h2><?php esc_html_e( 'Verband', 'seebuchung' ); ?></h2>
				<table class="form-table">
					<tr><th><label for="sb-verband"><?php esc_html_e( 'Verbandsname', 'seebuchung' ); ?></label></th>
						<td><input id="sb-verband" class="regular-text" name="verbandsname" value="<?php echo esc_attr( (string) $werte['verbandsname'] ); ?>"></td></tr>
					<tr><th><label for="sb-kurz"><?php esc_html_e( 'Kurzname', 'seebuchung' ); ?></label></th>
						<td><input id="sb-kurz" class="regular-text" name="verband_kurzname" value="<?php echo esc_attr( (string) $werte['verband_kurzname'] ); ?>"></td></tr>
					<tr><th><label for="sb-mail"><?php esc_html_e( 'Kontakt-E-Mail', 'seebuchung' ); ?></label></th>
						<td><input id="sb-mail" type="email" class="regular-text" name="kontakt_email" value="<?php echo esc_attr( (string) $werte['kontakt_email'] ); ?>"></td></tr>
					<tr><th><label for="sb-absender"><?php esc_html_e( 'Mail-Absenderadresse', 'seebuchung' ); ?></label></th>
						<td><input id="sb-absender" type="email" class="regular-text" name="mail_absenderadresse" value="<?php echo esc_attr( (string) $werte['mail_absenderadresse'] ); ?>" placeholder="seebuchung@lvst.de">
						<p class="description"><?php esc_html_e( 'Leer = WordPress-Standard (wordpress@…). Die Adresse sollte zur Domain der Website gehören, sonst landen Mails im Spam (SPF/DMARC).', 'seebuchung' ); ?></p></td></tr>
				</table>

				<h2><?php esc_html_e( 'Buchung', 'seebuchung' ); ?></h2>
				<table class="form-table">
					<tr><th><label for="sb-seite"><?php esc_html_e( 'Buchungsseite (enthält [seebuchung])', 'seebuchung' ); ?></label></th>
						<td>
							<?php
							wp_dropdown_pages(
								array(
									'id'                => 'sb-seite',
									'name'              => 'buchungsseite_id',
									'selected'          => (int) $werte['buchungsseite_id'],
									'show_option_none'  => esc_html__( '— wählen —', 'seebuchung' ),
									'option_none_value' => '0',
								)
							);
							?>
						</td></tr>
					<tr><th><label for="sb-frist"><?php esc_html_e( 'Bestätigungsfrist (Stunden)', 'seebuchung' ); ?></label></th>
						<td><input id="sb-frist" type="number" min="1" name="bestaetigungsfrist_stunden" value="<?php echo esc_attr( (string) $werte['bestaetigungsfrist_stunden'] ); ?>">
						<p class="description"><?php esc_html_e( 'Unbestätigte Anfragen verfallen nach dieser Frist automatisch.', 'seebuchung' ); ?></p></td></tr>
					<tr><th><label for="sb-anon"><?php esc_html_e( 'Anonymisierung (Tage nach Tauchtag)', 'seebuchung' ); ?></label></th>
						<td><input id="sb-anon" type="number" min="1" name="anonymisierung_tage" value="<?php echo esc_attr( (string) $werte['anonymisierung_tage'] ); ?>"></td></tr>
				</table>

				<h2><?php esc_html_e( 'Blockaden-Anträge (Vereine)', 'seebuchung' ); ?></h2>
				<table class="form-table">
					<tr><th><?php esc_html_e( 'Antragsfenster (MM-TT bis MM-TT)', 'seebuchung' ); ?></th>
						<td><input name="antragsfenster_von" size="6" value="<?php echo esc_attr( (string) $werte['antragsfenster_von'] ); ?>"> –
							<input name="antragsfenster_bis" size="6" value="<?php echo esc_attr( (string) $werte['antragsfenster_bis'] ); ?>"></td></tr>
				</table>

				<h2><?php esc_html_e( 'PayPal (Phase 2)', 'seebuchung' ); ?></h2>
				<table class="form-table">
					<tr><th><?php esc_html_e( 'Sandbox-Modus', 'seebuchung' ); ?></th>
						<td><label><input type="checkbox" name="paypal_sandbox" value="1" <?php checked( (bool) $werte['paypal_sandbox'] ); ?>> <?php esc_html_e( 'Testumgebung verwenden', 'seebuchung' ); ?></label></td></tr>
					<tr><th><label for="sb-ppid"><?php esc_html_e( 'Client-ID', 'seebuchung' ); ?></label></th>
						<td><input id="sb-ppid" class="regular-text" name="paypal_client_id" value="<?php echo esc_attr( (string) $werte['paypal_client_id'] ); ?>" autocomplete="off"></td></tr>
					<tr><th><label for="sb-ppsec"><?php esc_html_e( 'Secret', 'seebuchung' ); ?></label></th>
						<td><input id="sb-ppsec" type="password" class="regular-text" name="paypal_secret" value="<?php echo esc_attr( (string) $werte['paypal_secret'] ); ?>" autocomplete="new-password"></td></tr>
					<tr><th><label for="sb-ppwh"><?php esc_html_e( 'Webhook-ID', 'seebuchung' ); ?></label></th>
						<td><input id="sb-ppwh" class="regular-text" name="paypal_webhook_id" value="<?php echo esc_attr( (string) $werte['paypal_webhook_id'] ); ?>">
						<p class="description"><?php echo esc_html( sprintf( /* translators: %s: Webhook-URL */ __( 'Im PayPal-Developer-Portal einen Webhook auf %s anlegen (Event: PAYMENT.CAPTURE.COMPLETED) und die ID hier eintragen.', 'seebuchung' ), rest_url( 'seebuchung/v1/paypal-webhook' ) ) ); ?></p></td></tr>
				</table>

				<p><button class="button button-primary"><?php esc_html_e( 'Speichern', 'seebuchung' ); ?></button></p>
			</form>
		</div>
		<?php
	}

	/**
	 * POST: Einstellungen speichern.
	 */
	public function speichern(): void {
		if ( ! current_user_can( Rollen::CAP_VERWALTEN ) ) {
			wp_die( esc_html__( 'Keine Berechtigung.', 'seebuchung' ) );
		}
		check_admin_referer( 'seebuchung_einstellungen' );

		$text = static function ( string $feld ): string {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce oben geprüft.
			return isset( $_POST[ $feld ] ) ? sanitize_text_field( wp_unslash( $_POST[ $feld ] ) ) : '';
		};

		Settings::update(
			array(
				'verbandsname'               => $text( 'verbandsname' ),
				'verband_kurzname'           => $text( 'verband_kurzname' ),
				'kontakt_email'              => sanitize_email( $text( 'kontakt_email' ) ),
				'mail_absenderadresse'       => sanitize_email( $text( 'mail_absenderadresse' ) ),
				'buchungsseite_id'           => (int) $text( 'buchungsseite_id' ),
				'bestaetigungsfrist_stunden' => max( 1, (int) $text( 'bestaetigungsfrist_stunden' ) ),
				'anonymisierung_tage'        => max( 1, (int) $text( 'anonymisierung_tage' ) ),
				'antragsfenster_von'         => $text( 'antragsfenster_von' ),
				'antragsfenster_bis'         => $text( 'antragsfenster_bis' ),
				'paypal_sandbox'             => isset( $_POST['paypal_sandbox'] ),
				'paypal_client_id'           => $text( 'paypal_client_id' ),
				'paypal_secret'              => $text( 'paypal_secret' ),
				'paypal_webhook_id'          => $text( 'paypal_webhook_id' ),
			)
		);

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'           => 'seebuchung-einstellungen',
					'sb_gespeichert' => 1,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
