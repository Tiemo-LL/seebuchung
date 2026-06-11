<?php
/**
 * Admin: Vereine und Blockaden-Token.
 *
 * @package Seebuchung
 */

namespace Seebuchung\Admin;

use Seebuchung\Database\VereinsRepository;
use Seebuchung\Domain\Token;
use Seebuchung\Rollen;
use Seebuchung\Service\Buchungsservice;
use Seebuchung\Settings;

/**
 * Vereinsliste mit Token-Generierung/-Widerruf für den Blockaden-Self-Service.
 *
 * Der Klartext-Token existiert nur unmittelbar nach der Generierung (einmalige
 * Anzeige über ein kurzlebiges Transient) — gespeichert wird nur der Hash.
 */
final class VereineSeite {

	/**
	 * POST-Aktion registrieren.
	 */
	public static function aktionen_registrieren(): void {
		add_action(
			'admin_post_seebuchung_verein_token',
			static function () {
				if ( ! current_user_can( Rollen::CAP_VERWALTEN ) ) {
					wp_die( esc_html__( 'Keine Berechtigung.', 'seebuchung' ) );
				}
				check_admin_referer( 'seebuchung_verein_token' );

				$verein_id = isset( $_POST['verein_id'] ) ? (int) $_POST['verein_id'] : 0;
				$aktion    = isset( $_POST['aktion'] ) ? sanitize_key( wp_unslash( $_POST['aktion'] ) ) : '';
				$repo      = new VereinsRepository();

				if ( 'generieren' === $aktion ) {
					$token = Token::generieren();
					$repo->token_setzen( $verein_id, Token::hash( $token ) );
					$link = add_query_arg(
						'sb_verein',
						rawurlencode( $token ),
						( (int) Settings::get( 'buchungsseite_id' ) > 0 )
							? (string) get_permalink( (int) Settings::get( 'buchungsseite_id' ) )
							: home_url( '/' )
					);
					set_transient(
						'seebuchung_vereinslink_' . get_current_user_id(),
						array(
							'verein_id' => $verein_id,
							'link'      => $link,
						),
						300
					);
				} elseif ( 'widerrufen' === $aktion ) {
					$repo->token_setzen( $verein_id, null );
				}

				wp_safe_redirect( add_query_arg( 'page', 'seebuchung-vereine', admin_url( 'admin.php' ) ) );
				exit;
			}
		);
	}

	/**
	 * Seite rendern.
	 */
	public function render(): void {
		$vereine = ( new VereinsRepository() )->alle();
		$frisch  = get_transient( 'seebuchung_vereinslink_' . get_current_user_id() );
		delete_transient( 'seebuchung_vereinslink_' . get_current_user_id() );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Vereine', 'seebuchung' ); ?></h1>

			<?php if ( is_array( $frisch ) ) : ?>
				<div class="notice notice-success">
					<p>
						<strong><?php esc_html_e( 'Neuer Blockaden-Link (nur jetzt sichtbar — bitte kopieren und an den Verein geben):', 'seebuchung' ); ?></strong><br>
						<code style="user-select:all"><?php echo esc_html( (string) $frisch['link'] ); ?></code>
					</p>
				</div>
			<?php endif; ?>

			<table class="widefat striped">
				<thead><tr>
					<th><?php esc_html_e( 'Nr.', 'seebuchung' ); ?></th>
					<th><?php esc_html_e( 'Name', 'seebuchung' ); ?></th>
					<th><?php esc_html_e( 'Stadt', 'seebuchung' ); ?></th>
					<th><?php esc_html_e( 'Verband', 'seebuchung' ); ?></th>
					<th><?php esc_html_e( 'Blockaden-Token', 'seebuchung' ); ?></th>
					<th></th>
				</tr></thead>
				<tbody>
				<?php foreach ( $vereine as $verein ) : ?>
					<tr>
						<td><?php echo esc_html( (string) $verein['nummer'] ); ?></td>
						<td><strong><?php echo esc_html( (string) $verein['name'] ); ?></strong></td>
						<td><?php echo esc_html( (string) $verein['stadt'] ); ?></td>
						<td><?php echo esc_html( (string) $verein['verband'] ); ?></td>
						<td>
							<?php
							echo null === $verein['token_hash']
								? '—'
								: esc_html(
									sprintf(
										/* translators: %s: Datum */
										__( 'aktiv seit %s', 'seebuchung' ),
										(string) $verein['token_erstellt_am']
									)
								);
							?>
						</td>
						<td>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
								<?php wp_nonce_field( 'seebuchung_verein_token' ); ?>
								<input type="hidden" name="action" value="seebuchung_verein_token">
								<input type="hidden" name="verein_id" value="<?php echo esc_attr( (string) $verein['id'] ); ?>">
								<button class="button button-small" name="aktion" value="generieren"><?php echo esc_html( null === $verein['token_hash'] ? __( 'Token generieren', 'seebuchung' ) : __( 'Neu generieren', 'seebuchung' ) ); ?></button>
								<?php if ( null !== $verein['token_hash'] ) : ?>
									<button class="button button-small" name="aktion" value="widerrufen"><?php esc_html_e( 'Widerrufen', 'seebuchung' ); ?></button>
								<?php endif; ?>
							</form>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}
}
