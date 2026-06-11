<?php
/**
 * Admin: mobile Kontrolleurs-Ansicht (F6).
 *
 * @package Seebuchung
 */

namespace Seebuchung\Admin;

use Seebuchung\Database\BuchungsRepository;
use Seebuchung\Database\SeenRepository;
use Seebuchung\Database\VereinsRepository;
use Seebuchung\Domain\Buchungsstatus;
use Seebuchung\Domain\QrPayload;
use Seebuchung\Rollen;
use Seebuchung\Service\Buchungsservice;
use Seebuchung\Service\QrService;

/**
 * See wählen → Tagesliste der Buchungen, QR-Payload prüfen,
 * "kontrolliert"-Haken setzen. Read-only bis auf den Haken.
 */
final class KontrolleSeite {

	/**
	 * POST-Aktionen registrieren.
	 */
	public static function aktionen_registrieren(): void {
		add_action(
			'admin_post_seebuchung_kontrolliert',
			static function () {
				if ( ! current_user_can( Rollen::CAP_KONTROLLE ) ) {
					wp_die( esc_html__( 'Keine Berechtigung.', 'seebuchung' ) );
				}
				check_admin_referer( 'seebuchung_kontrolliert' );

				$id  = isset( $_POST['buchung_id'] ) ? (int) $_POST['buchung_id'] : 0;
				$see = isset( $_POST['see_id'] ) ? (int) $_POST['see_id'] : 0;
				( new Buchungsservice() )->kontrollieren( $id );

				wp_safe_redirect(
					add_query_arg(
						array(
							'page'  => 'seebuchung-kontrolle',
							'k_see' => $see,
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
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Lesende Navigation + QR-Prüfung (read-only).
		$see_id  = isset( $_GET['k_see'] ) ? (int) $_GET['k_see'] : 0;
		$payload = isset( $_GET['k_qr'] ) ? sanitize_text_field( wp_unslash( $_GET['k_qr'] ) ) : '';
		// phpcs:enable

		$heute = current_time( 'Y-m-d' );
		$seen  = ( new SeenRepository() )->aktive();

		echo '<div class="wrap seebuchung-kontrolle"><h1>' . esc_html__( 'See-Kontrolle', 'seebuchung' ) . '</h1>';

		if ( '' !== $payload ) {
			$this->qr_ergebnis( $payload );
		}

		if ( $see_id <= 0 ) {
			$this->see_wahl( $seen );
			echo '</div>';
			return;
		}

		$this->qr_formular( $see_id );
		$this->tagesliste( $see_id, $heute );
		echo '</div>';
	}

	/**
	 * See-Auswahl (große Touch-Ziele).
	 *
	 * @param \Seebuchung\Domain\See[] $seen Aktive Seen.
	 */
	private function see_wahl( array $seen ): void {
		echo '<p>' . esc_html__( 'See wählen:', 'seebuchung' ) . '</p><p>';
		foreach ( $seen as $see ) {
			printf(
				'<a class="button button-hero" style="margin:0 0.5em 0.5em 0" href="%s">%s</a>',
				esc_url(
					add_query_arg(
						array(
							'page'  => 'seebuchung-kontrolle',
							'k_see' => $see->id,
						),
						admin_url( 'admin.php' )
					)
				),
				esc_html( $see->name )
			);
		}
		echo '</p>';
	}

	/**
	 * QR-Prüfformular (Payload aus einer Scanner-App einfügen).
	 *
	 * @param int $see_id Gewählter See.
	 */
	private function qr_formular( int $see_id ): void {
		?>
		<form method="get" style="margin:1em 0">
			<input type="hidden" name="page" value="seebuchung-kontrolle">
			<input type="hidden" name="k_see" value="<?php echo esc_attr( (string) $see_id ); ?>">
			<input type="text" name="k_qr" placeholder="<?php esc_attr_e( 'QR-Inhalt (SB1:…) einfügen', 'seebuchung' ); ?>" style="min-width:16em">
			<button class="button button-primary"><?php esc_html_e( 'Prüfen', 'seebuchung' ); ?></button>
		</form>
		<?php
	}

	/**
	 * Ergebnis einer QR-Prüfung.
	 *
	 * @param string $payload Gescannter Inhalt.
	 */
	private function qr_ergebnis( string $payload ): void {
		$buchungs_id = QrPayload::pruefen( $payload, QrService::secret() );
		$buchung     = null === $buchungs_id ? null : ( new BuchungsRepository() )->per_id( $buchungs_id );
		$ist_gueltig = null !== $buchung && in_array( $buchung['status'], array( Buchungsstatus::GUELTIG, Buchungsstatus::KONTROLLIERT ), true );

		if ( $ist_gueltig ) {
			printf(
				'<div class="notice notice-success"><p><strong>✓ %s</strong> — %s, %s, %s</p></div>',
				esc_html__( 'GÜLTIG', 'seebuchung' ),
				esc_html( $buchung['vorname'] . ' ' . $buchung['name'] ),
				esc_html( (string) $buchung['datum'] ),
				esc_html(
					sprintf(
						/* translators: %d: Anzahl Taucher */
						_n( '%d Taucher', '%d Taucher', (int) $buchung['anzahl_taucher'], 'seebuchung' ),
						(int) $buchung['anzahl_taucher']
					)
				)
			);
		} else {
			printf(
				'<div class="notice notice-error"><p><strong>✗ %s</strong></p></div>',
				esc_html__( 'UNGÜLTIG — keine gültige Buchung zu diesem Code', 'seebuchung' )
			);
		}
	}

	/**
	 * Heutige Buchungen eines Sees.
	 *
	 * @param int    $see_id See-ID.
	 * @param string $heute  Heutiges Datum (Y-m-d).
	 */
	private function tagesliste( int $see_id, string $heute ): void {
		$buchungen     = ( new BuchungsRepository() )->suchen( $see_id, $heute, '' );
		$vereins_namen = array();
		foreach ( ( new VereinsRepository() )->alle() as $verein ) {
			$vereins_namen[ (int) $verein['id'] ] = (string) $verein['name'];
		}
		?>
		<h2><?php echo esc_html( sprintf( /* translators: %s: Datum */ __( 'Buchungen heute (%s)', 'seebuchung' ), $heute ) ); ?></h2>
		<table class="widefat striped">
			<thead><tr>
				<th><?php esc_html_e( 'Zeit', 'seebuchung' ); ?></th>
				<th><?php esc_html_e( 'Gruppenleitung', 'seebuchung' ); ?></th>
				<th><?php esc_html_e( 'Verein', 'seebuchung' ); ?></th>
				<th><?php esc_html_e( 'Anzahl', 'seebuchung' ); ?></th>
				<th><?php esc_html_e( 'Status', 'seebuchung' ); ?></th>
				<th></th>
			</tr></thead>
			<tbody>
			<?php if ( array() === $buchungen ) : ?>
				<tr><td colspan="6"><?php esc_html_e( 'Heute keine Buchungen an diesem See.', 'seebuchung' ); ?></td></tr>
			<?php endif; ?>
			<?php foreach ( $buchungen as $buchung ) : ?>
				<?php
				if ( in_array( $buchung['status'], array( Buchungsstatus::STORNIERT, Buchungsstatus::VERFALLEN ), true ) ) {
					continue;
				}
				?>
				<tr>
					<td><?php echo esc_html( null === $buchung['stunde'] ? __( 'Tag', 'seebuchung' ) : sprintf( '%02d:00', (int) $buchung['stunde'] ) ); ?></td>
					<td><strong><?php echo esc_html( $buchung['vorname'] . ' ' . $buchung['name'] ); ?></strong></td>
					<td><?php echo esc_html( null === $buchung['verein_id'] ? __( 'Gast', 'seebuchung' ) : ( $vereins_namen[ (int) $buchung['verein_id'] ] ?? '—' ) ); ?></td>
					<td><?php echo esc_html( (string) (int) $buchung['anzahl_taucher'] ); ?></td>
					<td><?php echo esc_html( (string) $buchung['status'] ); ?></td>
					<td>
						<?php if ( Buchungsstatus::GUELTIG === $buchung['status'] ) : ?>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
								<?php wp_nonce_field( 'seebuchung_kontrolliert' ); ?>
								<input type="hidden" name="action" value="seebuchung_kontrolliert">
								<input type="hidden" name="buchung_id" value="<?php echo esc_attr( (string) $buchung['id'] ); ?>">
								<input type="hidden" name="see_id" value="<?php echo esc_attr( (string) $see_id ); ?>">
								<button class="button button-small button-primary"><?php esc_html_e( '✓ kontrolliert', 'seebuchung' ); ?></button>
							</form>
						<?php elseif ( Buchungsstatus::KONTROLLIERT === $buchung['status'] ) : ?>
							✓
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}
}
