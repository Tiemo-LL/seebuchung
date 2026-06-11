<?php
/**
 * Admin: Buchungsübersicht.
 *
 * @package Seebuchung
 */

namespace Seebuchung\Admin;

use Seebuchung\Database\BuchungsRepository;
use Seebuchung\Database\SeenRepository;
use Seebuchung\Domain\Buchungsstatemachine;
use Seebuchung\Domain\Buchungsstatus;
use Seebuchung\Rollen;
use Seebuchung\Service\Buchungsservice;

/**
 * Filterbare Buchungsliste (See/Datum/Status) mit manueller Stornierung.
 */
final class BuchungenSeite {

	/**
	 * POST-Aktionen registrieren.
	 */
	public static function aktionen_registrieren(): void {
		add_action(
			'admin_post_seebuchung_csv_export',
			static function () {
				if ( ! current_user_can( Rollen::CAP_VERWALTEN ) ) {
					wp_die( esc_html__( 'Keine Berechtigung.', 'seebuchung' ) );
				}
				check_admin_referer( 'seebuchung_csv_export' );
				self::csv_export();
			}
		);
		add_action(
			'admin_post_seebuchung_admin_storno',
			static function () {
				if ( ! current_user_can( Rollen::CAP_VERWALTEN ) ) {
					wp_die( esc_html__( 'Keine Berechtigung.', 'seebuchung' ) );
				}
				check_admin_referer( 'seebuchung_admin_storno' );

				$id       = isset( $_POST['buchung_id'] ) ? (int) $_POST['buchung_id'] : 0;
				$ergebnis = ( new Buchungsservice() )->stornieren_admin( $id );

				$ziel = add_query_arg(
					array(
						'page'       => 'seebuchung',
						'sb_meldung' => is_wp_error( $ergebnis ) ? rawurlencode( $ergebnis->get_error_message() ) : 'storniert',
					),
					admin_url( 'admin.php' )
				);
				wp_safe_redirect( $ziel );
				exit;
			}
		);
	}

	/**
	 * CSV aller Zahlungen streamen (Schatzmeister-Export).
	 */
	private static function csv_export(): void {
		global $wpdb;
		$tabelle = \Seebuchung\Database\Schema::table( 'buchungen' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL -- Eigene Tabelle, keine Parameter.
		$zeilen = (array) $wpdb->get_results( "SELECT id, datum, stunde, see_id, name, vorname, email, anzahl_taucher, anzahl_zahler, preis_gesamt, paypal_transaktion, status, updated_at FROM {$tabelle} WHERE paypal_transaktion IS NOT NULL ORDER BY updated_at", ARRAY_A );

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=seebuchung-zahlungen.csv' );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- CSV-Stream an den Browser.
		$ausgabe = fopen( 'php://output', 'w' );
		fputcsv( $ausgabe, array( 'Buchung', 'Datum', 'Stunde', 'See-ID', 'Name', 'Vorname', 'E-Mail', 'Taucher', 'Zahler', 'Betrag EUR', 'PayPal-Transaktion', 'Status', 'Aktualisiert' ), ';' );
		foreach ( $zeilen as $zeile ) {
			fputcsv( $ausgabe, array_values( $zeile ), ';' );
		}
		exit;
	}

	/**
	 * Seite rendern.
	 */
	public function render(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Lesende Filter.
		$see_id  = isset( $_GET['f_see'] ) ? (int) $_GET['f_see'] : 0;
		$datum   = isset( $_GET['f_datum'] ) ? sanitize_text_field( wp_unslash( $_GET['f_datum'] ) ) : '';
		$status  = isset( $_GET['f_status'] ) ? sanitize_key( wp_unslash( $_GET['f_status'] ) ) : '';
		$meldung = isset( $_GET['sb_meldung'] ) ? sanitize_text_field( wp_unslash( $_GET['sb_meldung'] ) ) : '';
		// phpcs:enable
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $datum ) ) {
			$datum = '';
		}
		if ( ! in_array( $status, Buchungsstatus::ALLE, true ) ) {
			$status = '';
		}

		$seen      = ( new SeenRepository() )->alle();
		$see_namen = array();
		foreach ( $seen as $see ) {
			$see_namen[ $see->id ] = $see->name;
		}

		$buchungen   = ( new BuchungsRepository() )->suchen( $see_id, $datum, $status );
		$darf_storno = current_user_can( Rollen::CAP_VERWALTEN );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Buchungen', 'seebuchung' ); ?></h1>

			<?php if ( 'storniert' === $meldung ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Buchung storniert, Mail an die buchende Person ist raus.', 'seebuchung' ); ?></p></div>
			<?php elseif ( '' !== $meldung ) : ?>
				<div class="notice notice-error is-dismissible"><p><?php echo esc_html( $meldung ); ?></p></div>
			<?php endif; ?>

			<form method="get">
				<input type="hidden" name="page" value="seebuchung">
				<select name="f_see">
					<option value="0"><?php esc_html_e( 'Alle Seen', 'seebuchung' ); ?></option>
					<?php foreach ( $see_namen as $id => $name ) : ?>
						<option value="<?php echo esc_attr( (string) $id ); ?>" <?php selected( $see_id, $id ); ?>><?php echo esc_html( $name ); ?></option>
					<?php endforeach; ?>
				</select>
				<input type="date" name="f_datum" value="<?php echo esc_attr( $datum ); ?>">
				<select name="f_status">
					<option value=""><?php esc_html_e( 'Alle Stati', 'seebuchung' ); ?></option>
					<?php foreach ( Buchungsstatus::ALLE as $s ) : ?>
						<option value="<?php echo esc_attr( $s ); ?>" <?php selected( $status, $s ); ?>><?php echo esc_html( $s ); ?></option>
					<?php endforeach; ?>
				</select>
				<button class="button"><?php esc_html_e( 'Filtern', 'seebuchung' ); ?></button>
			</form>

			<?php if ( $darf_storno ) : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:0.5em">
					<?php wp_nonce_field( 'seebuchung_csv_export' ); ?>
					<input type="hidden" name="action" value="seebuchung_csv_export">
					<button class="button"><?php esc_html_e( 'Zahlungen als CSV exportieren', 'seebuchung' ); ?></button>
				</form>
			<?php endif; ?>

			<table class="widefat striped" style="margin-top:1em">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Datum', 'seebuchung' ); ?></th>
						<th><?php esc_html_e( 'See', 'seebuchung' ); ?></th>
						<th><?php esc_html_e( 'Name', 'seebuchung' ); ?></th>
						<th><?php esc_html_e( 'E-Mail / Telefon', 'seebuchung' ); ?></th>
						<th><?php esc_html_e( 'Taucher', 'seebuchung' ); ?></th>
						<th><?php esc_html_e( 'Gebühr', 'seebuchung' ); ?></th>
						<th><?php esc_html_e( 'Status', 'seebuchung' ); ?></th>
						<?php if ( $darf_storno ) : ?>
							<th></th>
						<?php endif; ?>
					</tr>
				</thead>
				<tbody>
					<?php if ( array() === $buchungen ) : ?>
						<tr><td colspan="8"><?php esc_html_e( 'Keine Buchungen gefunden.', 'seebuchung' ); ?></td></tr>
					<?php endif; ?>
					<?php foreach ( $buchungen as $buchung ) : ?>
						<tr>
							<td>
								<?php
								echo esc_html( $buchung['datum'] );
								if ( null !== $buchung['stunde'] ) {
									echo esc_html( ' ' . sprintf( '%02d:00', (int) $buchung['stunde'] ) );
								}
								?>
							</td>
							<td><?php echo esc_html( $see_namen[ (int) $buchung['see_id'] ] ?? (string) $buchung['see_id'] ); ?></td>
							<td><?php echo esc_html( $buchung['vorname'] . ' ' . $buchung['name'] ); ?></td>
							<td><?php echo esc_html( $buchung['email'] . ( '' !== $buchung['telefon'] ? ' / ' . $buchung['telefon'] : '' ) ); ?></td>
							<td><?php echo esc_html( (string) (int) $buchung['anzahl_taucher'] ); ?></td>
							<td><?php echo null === $buchung['preis_gesamt'] ? '—' : esc_html( number_format_i18n( (float) $buchung['preis_gesamt'], 2 ) . ' €' ); ?></td>
							<td><?php echo esc_html( (string) $buchung['status'] ); ?></td>
							<?php if ( $darf_storno ) : ?>
								<td>
									<?php if ( Buchungsstatemachine::erlaubt( (string) $buchung['status'], Buchungsstatus::STORNIERT ) ) : ?>
										<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Buchung wirklich stornieren?', 'seebuchung' ) ); ?>');">
											<?php wp_nonce_field( 'seebuchung_admin_storno' ); ?>
											<input type="hidden" name="action" value="seebuchung_admin_storno">
											<input type="hidden" name="buchung_id" value="<?php echo esc_attr( (string) $buchung['id'] ); ?>">
											<button class="button button-small"><?php esc_html_e( 'Stornieren', 'seebuchung' ); ?></button>
										</form>
									<?php endif; ?>
								</td>
							<?php endif; ?>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}
}
