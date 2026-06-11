<?php
/**
 * Admin: geführter Saisonwechsel (F3).
 *
 * @package Seebuchung
 */

namespace Seebuchung\Admin;

use Seebuchung\Database\BuchungsRepository;
use Seebuchung\Database\Schema;
use Seebuchung\Database\SeenRepository;
use Seebuchung\Rollen;

/**
 * Statt des alten "Restart": neue Saisondaten je See setzen und alle
 * vergangenen Buchungen anonymisieren. Buchungs- und Statistikdaten
 * bleiben dauerhaft erhalten (read-only Archiv, da Endzustände).
 */
final class SaisonwechselSeite {

	/**
	 * POST-Aktion registrieren.
	 */
	public static function aktionen_registrieren(): void {
		add_action( 'admin_post_seebuchung_saisonwechsel', array( new self(), 'durchfuehren' ) );
	}

	/**
	 * Seite rendern.
	 */
	public function render(): void {
		$seen = ( new SeenRepository() )->alle();
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Nur Erfolgsmeldung.
		$anonymisiert = isset( $_GET['sb_anonymisiert'] ) ? (int) $_GET['sb_anonymisiert'] : -1;
		// phpcs:enable
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Saisonwechsel', 'seebuchung' ); ?></h1>

			<?php if ( $anonymisiert >= 0 ) : ?>
				<div class="notice notice-success is-dismissible"><p>
					<?php
					echo esc_html(
						sprintf(
							/* translators: %d: Anzahl */
							__( 'Saisonwechsel abgeschlossen. %d Buchungen wurden anonymisiert; die Statistik bleibt erhalten.', 'seebuchung' ),
							$anonymisiert
						)
					);
					?>
				</p></div>
			<?php endif; ?>

			<p><?php esc_html_e( 'Setzt die neuen Saisondaten je See und anonymisiert alle Buchungen vergangener Tauchtage (DSGVO). Buchungs- und Statistikdaten bleiben dauerhaft — nichts wird gelöscht.', 'seebuchung' ); ?></p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'seebuchung_saisonwechsel' ); ?>
				<input type="hidden" name="action" value="seebuchung_saisonwechsel">

				<table class="widefat striped" style="max-width:48em">
					<thead><tr>
						<th><?php esc_html_e( 'See', 'seebuchung' ); ?></th>
						<th><?php esc_html_e( 'Bisherige Saison', 'seebuchung' ); ?></th>
						<th><?php esc_html_e( 'Neue Saison (von / bis)', 'seebuchung' ); ?></th>
					</tr></thead>
					<tbody>
					<?php foreach ( $seen as $see ) : ?>
						<?php
						$vorschlag_von = null === $see->saison_von ? '' : ( (int) substr( $see->saison_von, 0, 4 ) + 1 ) . substr( $see->saison_von, 4 );
						$vorschlag_bis = null === $see->saison_bis ? '' : ( (int) substr( $see->saison_bis, 0, 4 ) + 1 ) . substr( $see->saison_bis, 4 );
						?>
						<tr>
							<td><strong><?php echo esc_html( $see->name ); ?></strong></td>
							<td><?php echo esc_html( ( $see->saison_von ?? '—' ) . ' – ' . ( $see->saison_bis ?? '—' ) ); ?></td>
							<td>
								<input type="date" name="saison_von[<?php echo esc_attr( (string) $see->id ); ?>]" value="<?php echo esc_attr( $vorschlag_von ); ?>">
								<input type="date" name="saison_bis[<?php echo esc_attr( (string) $see->id ); ?>]" value="<?php echo esc_attr( $vorschlag_bis ); ?>">
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>

				<p>
					<button class="button button-primary" onclick="return confirm('<?php echo esc_js( __( 'Saisonwechsel jetzt durchführen? Vergangene Buchungen werden anonymisiert (nicht rückgängig zu machen).', 'seebuchung' ) ); ?>');">
						<?php esc_html_e( 'Saisonwechsel durchführen', 'seebuchung' ); ?>
					</button>
				</p>
			</form>
		</div>
		<?php
	}

	/**
	 * POST: Saisondaten setzen + Vergangenes anonymisieren.
	 */
	public function durchfuehren(): void {
		if ( ! current_user_can( Rollen::CAP_VERWALTEN ) ) {
			wp_die( esc_html__( 'Keine Berechtigung.', 'seebuchung' ) );
		}
		check_admin_referer( 'seebuchung_saisonwechsel' );

		global $wpdb;
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Oben geprüft.
		$von_alle = isset( $_POST['saison_von'] ) ? array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['saison_von'] ) ) : array();
		$bis_alle = isset( $_POST['saison_bis'] ) ? array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['saison_bis'] ) ) : array();
		// phpcs:enable

		foreach ( $von_alle as $see_id => $von ) {
			$bis = $bis_alle[ $see_id ] ?? '';
			if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $von ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $bis ) ) {
				continue;
			}
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Eigene Tabelle.
			$wpdb->update(
				Schema::table( 'seen' ),
				array(
					'saison_von' => $von,
					'saison_bis' => $bis,
					'updated_at' => current_time( 'mysql' ),
				),
				array( 'id' => (int) $see_id )
			);
		}

		// Alles vor heute anonymisieren (Saisonende-Regel).
		$anzahl = ( new BuchungsRepository() )->anonymisieren( current_time( 'Y-m-d' ) );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'            => 'seebuchung-saisonwechsel',
					'sb_anonymisiert' => $anzahl,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
