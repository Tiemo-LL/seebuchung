<?php
/**
 * Token-Seite: Buchungsstatus mit Aktionen.
 *
 * @package Seebuchung
 * @var array<string, mixed>        $buchung   Buchungszeile.
 * @var \Seebuchung\Domain\See|null $see       Der gebuchte See.
 * @var string                      $token     Klartext-Token.
 * @var string                      $basis_url URL der Buchungsseite.
 */

use Seebuchung\Domain\Buchungsstatemachine;
use Seebuchung\Domain\Buchungsstatus;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$status_labels = array(
	Buchungsstatus::ANGEFRAGT    => __( 'Angefragt — bitte bestätigen', 'seebuchung' ),
	Buchungsstatus::BESTAETIGT   => __( 'Bestätigt — Zahlung ausstehend', 'seebuchung' ),
	Buchungsstatus::GUELTIG      => __( 'Gültig', 'seebuchung' ),
	Buchungsstatus::KONTROLLIERT => __( 'Gültig (kontrolliert)', 'seebuchung' ),
	Buchungsstatus::STORNIERT    => __( 'Storniert', 'seebuchung' ),
	Buchungsstatus::VERFALLEN    => __( 'Verfallen', 'seebuchung' ),
);

$buchung_status   = (string) $buchung['status'];
$kann_bestaetigen = Buchungsstatemachine::erlaubt( $buchung_status, Buchungsstatus::BESTAETIGT );
$kann_stornieren  = Buchungsstatemachine::erlaubt( $buchung_status, Buchungsstatus::STORNIERT );
?>
<div class="seebuchung">
	<h2><?php esc_html_e( 'Deine Buchung', 'seebuchung' ); ?></h2>

	<table class="seebuchung-details">
		<tr><th><?php esc_html_e( 'Status', 'seebuchung' ); ?></th><td><span class="seebuchung-status seebuchung-status--<?php echo esc_attr( $buchung_status ); ?>"><?php echo esc_html( $status_labels[ $buchung_status ] ?? $buchung_status ); ?></span></td></tr>
		<tr><th><?php esc_html_e( 'See', 'seebuchung' ); ?></th><td><?php echo esc_html( null !== $see ? $see->name : '—' ); ?></td></tr>
		<tr><th><?php esc_html_e( 'Datum', 'seebuchung' ); ?></th><td>
			<?php
			echo esc_html( wp_date( 'l, j. F Y', strtotime( (string) $buchung['datum'] ) ) );
			if ( null !== $buchung['stunde'] ) {
				echo esc_html( ', ' . sprintf( '%02d:00', (int) $buchung['stunde'] ) . ' ' . __( 'Uhr', 'seebuchung' ) );
			}
			?>
		</td></tr>
		<tr><th><?php esc_html_e( 'Gruppenleitung', 'seebuchung' ); ?></th><td><?php echo esc_html( $buchung['vorname'] . ' ' . $buchung['name'] ); ?></td></tr>
		<tr><th><?php esc_html_e( 'Taucher', 'seebuchung' ); ?></th><td><?php echo esc_html( (string) (int) $buchung['anzahl_taucher'] ); ?></td></tr>
		<?php if ( null !== $buchung['preis_gesamt'] ) : ?>
			<tr><th><?php esc_html_e( 'Gebühr', 'seebuchung' ); ?></th><td><?php echo esc_html( number_format_i18n( (float) $buchung['preis_gesamt'], 2 ) . ' €' ); ?></td></tr>
		<?php endif; ?>
	</table>

	<?php if ( $kann_bestaetigen || $kann_stornieren ) : ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="seebuchung-aktionen">
			<?php wp_nonce_field( 'seebuchung_token_aktion' ); ?>
			<input type="hidden" name="action" value="seebuchung_token_aktion">
			<input type="hidden" name="token" value="<?php echo esc_attr( $token ); ?>">
			<input type="hidden" name="zurueck" value="<?php echo esc_url( $basis_url ); ?>">

			<?php if ( $kann_bestaetigen ) : ?>
				<button type="submit" name="aktion" value="bestaetigen" class="seebuchung-button">
					<?php esc_html_e( 'Buchung bestätigen', 'seebuchung' ); ?>
				</button>
			<?php endif; ?>
			<?php if ( $kann_stornieren ) : ?>
				<button type="submit" name="aktion" value="stornieren" class="seebuchung-button seebuchung-button--storno"
					onclick="return confirm('<?php echo esc_js( __( 'Buchung wirklich stornieren? Gezahlte Gebühren werden nicht erstattet.', 'seebuchung' ) ); ?>');">
					<?php esc_html_e( 'Buchung stornieren', 'seebuchung' ); ?>
				</button>
			<?php endif; ?>
		</form>
	<?php endif; ?>
</div>
