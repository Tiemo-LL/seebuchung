<?php
/**
 * Admin: Blockaden-Genehmigung.
 *
 * @package Seebuchung
 */

namespace Seebuchung\Admin;

use Seebuchung\Database\BlockadenRepository;
use Seebuchung\Database\SeenRepository;
use Seebuchung\Database\VereinsRepository;
use Seebuchung\Rollen;
use Seebuchung\Service\Blockadenservice;

/**
 * Liste aller Blockaden mit Genehmigen-/Ablehnen-Workflow.
 */
final class BlockadenSeite {

	/**
	 * POST-Aktion registrieren.
	 */
	public static function aktionen_registrieren(): void {
		add_action(
			'admin_post_seebuchung_blockade_entscheiden',
			static function () {
				if ( ! current_user_can( Rollen::CAP_VERWALTEN ) ) {
					wp_die( esc_html__( 'Keine Berechtigung.', 'seebuchung' ) );
				}
				check_admin_referer( 'seebuchung_blockade_entscheiden' );

				$id        = isset( $_POST['blockade_id'] ) ? (int) $_POST['blockade_id'] : 0;
				$genehmigt = isset( $_POST['entscheidung'] ) && 'genehmigen' === $_POST['entscheidung'];
				$ergebnis  = ( new Blockadenservice() )->entscheiden( $id, $genehmigt );

				wp_safe_redirect(
					add_query_arg(
						array(
							'page'       => 'seebuchung-blockaden',
							'sb_meldung' => is_wp_error( $ergebnis ) ? rawurlencode( $ergebnis->get_error_message() ) : ( $genehmigt ? 'genehmigt' : 'abgelehnt' ),
						),
						admin_url( 'admin.php' )
					)
				);
				exit;
			}
		);
	}

	/**
	 * Seite rendern.
	 */
	public function render(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Lesende Filter/Meldungen.
		$status  = isset( $_GET['f_status'] ) ? sanitize_key( wp_unslash( $_GET['f_status'] ) ) : '';
		$meldung = isset( $_GET['sb_meldung'] ) ? sanitize_text_field( wp_unslash( $_GET['sb_meldung'] ) ) : '';
		// phpcs:enable
		if ( ! in_array( $status, array( 'beantragt', 'genehmigt', 'abgelehnt' ), true ) ) {
			$status = '';
		}

		$see_namen = array();
		foreach ( ( new SeenRepository() )->alle() as $see ) {
			$see_namen[ $see->id ] = $see->name;
		}
		$vereins_namen = array();
		foreach ( ( new VereinsRepository() )->alle() as $verein ) {
			$vereins_namen[ (int) $verein['id'] ] = (string) $verein['name'];
		}

		$blockaden = ( new BlockadenRepository() )->alle( $status );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Blockaden', 'seebuchung' ); ?></h1>

			<?php if ( in_array( $meldung, array( 'genehmigt', 'abgelehnt' ), true ) ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php echo esc_html( 'genehmigt' === $meldung ? __( 'Antrag genehmigt, Mail an den Verein ist raus.', 'seebuchung' ) : __( 'Antrag abgelehnt, Mail an den Verein ist raus.', 'seebuchung' ) ); ?></p></div>
			<?php elseif ( '' !== $meldung ) : ?>
				<div class="notice notice-error is-dismissible"><p><?php echo esc_html( $meldung ); ?></p></div>
			<?php endif; ?>

			<form method="get">
				<input type="hidden" name="page" value="seebuchung-blockaden">
				<select name="f_status">
					<option value=""><?php esc_html_e( 'Alle Stati', 'seebuchung' ); ?></option>
					<?php foreach ( array( 'beantragt', 'genehmigt', 'abgelehnt' ) as $s ) : ?>
						<option value="<?php echo esc_attr( $s ); ?>" <?php selected( $status, $s ); ?>><?php echo esc_html( $s ); ?></option>
					<?php endforeach; ?>
				</select>
				<button class="button"><?php esc_html_e( 'Filtern', 'seebuchung' ); ?></button>
			</form>

			<table class="widefat striped" style="margin-top:1em">
				<thead><tr>
					<th><?php esc_html_e( 'Datum', 'seebuchung' ); ?></th>
					<th><?php esc_html_e( 'See', 'seebuchung' ); ?></th>
					<th><?php esc_html_e( 'Zeitraum', 'seebuchung' ); ?></th>
					<th><?php esc_html_e( 'Verein', 'seebuchung' ); ?></th>
					<th><?php esc_html_e( 'Veranstaltung / Kontakt', 'seebuchung' ); ?></th>
					<th><?php esc_html_e( 'Status', 'seebuchung' ); ?></th>
					<th></th>
				</tr></thead>
				<tbody>
				<?php if ( array() === $blockaden ) : ?>
					<tr><td colspan="7"><?php esc_html_e( 'Keine Blockaden gefunden.', 'seebuchung' ); ?></td></tr>
				<?php endif; ?>
				<?php foreach ( $blockaden as $blockade ) : ?>
					<tr>
						<td><?php echo esc_html( (string) $blockade['datum'] ); ?></td>
						<td><?php echo esc_html( $see_namen[ (int) $blockade['see_id'] ] ?? (string) $blockade['see_id'] ); ?></td>
						<td>
							<?php
							if ( ! empty( $blockade['ganzer_tag'] ) ) {
								esc_html_e( 'ganzer Tag', 'seebuchung' );
							} else {
								echo esc_html( sprintf( '%02d:00–%02d:00', (int) $blockade['stunde_von'], (int) $blockade['stunde_bis'] ) );
							}
							?>
						</td>
						<td><?php echo esc_html( null === $blockade['verein_id'] ? __( 'Verband', 'seebuchung' ) : ( $vereins_namen[ (int) $blockade['verein_id'] ] ?? (string) $blockade['verein_id'] ) ); ?></td>
						<td><?php echo esc_html( trim( $blockade['veranstaltung'] . ' — ' . $blockade['verantwortlicher'] . ' ' . $blockade['email'], ' —' ) ); ?></td>
						<td><?php echo esc_html( (string) $blockade['status'] ); ?></td>
						<td>
							<?php if ( 'beantragt' === $blockade['status'] ) : ?>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline">
									<?php wp_nonce_field( 'seebuchung_blockade_entscheiden' ); ?>
									<input type="hidden" name="action" value="seebuchung_blockade_entscheiden">
									<input type="hidden" name="blockade_id" value="<?php echo esc_attr( (string) $blockade['id'] ); ?>">
									<button class="button button-small button-primary" name="entscheidung" value="genehmigen"><?php esc_html_e( 'Genehmigen', 'seebuchung' ); ?></button>
									<button class="button button-small" name="entscheidung" value="ablehnen"><?php esc_html_e( 'Ablehnen', 'seebuchung' ); ?></button>
								</form>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}
}
