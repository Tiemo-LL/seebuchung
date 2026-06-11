<?php
/**
 * Admin: Mehrjahres-Statistik (F3).
 *
 * @package Seebuchung
 */

namespace Seebuchung\Admin;

use Seebuchung\Database\Schema;
use Seebuchung\Database\SeenRepository;

/**
 * Aggregierte Auslastung und Einnahmen je Jahr und See — überlebt die
 * Anonymisierung, da nur Sachdaten ausgewertet werden.
 */
final class StatistikSeite {

	/**
	 * Seite rendern.
	 */
	public function render(): void {
		global $wpdb;
		$tabelle = Schema::table( 'buchungen' );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL -- Eigene Tabelle, keine Parameter.
		$jahres_zeilen  = (array) $wpdb->get_results(
			"SELECT YEAR(datum) AS jahr, see_id,
				COUNT(*) AS buchungen,
				SUM(anzahl_taucher) AS taucher,
				SUM(CASE WHEN status IN ('gueltig','kontrolliert') THEN anzahl_taucher ELSE 0 END) AS taucher_gueltig,
				SUM(CASE WHEN status IN ('gueltig','kontrolliert') AND paypal_transaktion IS NOT NULL THEN preis_gesamt ELSE 0 END) AS einnahmen,
				SUM(status = 'storniert') AS storniert,
				SUM(status = 'verfallen') AS verfallen
			FROM {$tabelle}
			GROUP BY YEAR(datum), see_id
			ORDER BY jahr DESC, see_id",
			ARRAY_A
		);
		$stunden_zeilen = (array) $wpdb->get_results(
			"SELECT stunde, SUM(anzahl_taucher) AS taucher
			FROM {$tabelle}
			WHERE stunde IS NOT NULL AND status IN ('gueltig','kontrolliert')
			GROUP BY stunde ORDER BY taucher DESC LIMIT 5",
			ARRAY_A
		);
		// phpcs:enable

		$see_namen = array();
		foreach ( ( new SeenRepository() )->alle() as $see ) {
			$see_namen[ $see->id ] = $see->name;
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Statistik', 'seebuchung' ); ?></h1>

			<h2><?php esc_html_e( 'Jahresübersicht je See', 'seebuchung' ); ?></h2>
			<table class="widefat striped" style="max-width:62em">
				<thead><tr>
					<th><?php esc_html_e( 'Jahr', 'seebuchung' ); ?></th>
					<th><?php esc_html_e( 'See', 'seebuchung' ); ?></th>
					<th><?php esc_html_e( 'Buchungen', 'seebuchung' ); ?></th>
					<th><?php esc_html_e( 'Taucher (gesamt)', 'seebuchung' ); ?></th>
					<th><?php esc_html_e( 'Taucher (gültig)', 'seebuchung' ); ?></th>
					<th><?php esc_html_e( 'Einnahmen', 'seebuchung' ); ?></th>
					<th><?php esc_html_e( 'Storniert', 'seebuchung' ); ?></th>
					<th><?php esc_html_e( 'Verfallen', 'seebuchung' ); ?></th>
				</tr></thead>
				<tbody>
				<?php if ( array() === $jahres_zeilen ) : ?>
					<tr><td colspan="8"><?php esc_html_e( 'Noch keine Buchungsdaten.', 'seebuchung' ); ?></td></tr>
				<?php endif; ?>
				<?php foreach ( $jahres_zeilen as $zeile ) : ?>
					<tr>
						<td><?php echo esc_html( (string) $zeile['jahr'] ); ?></td>
						<td><?php echo esc_html( $see_namen[ (int) $zeile['see_id'] ] ?? (string) $zeile['see_id'] ); ?></td>
						<td><?php echo esc_html( (string) $zeile['buchungen'] ); ?></td>
						<td><?php echo esc_html( (string) $zeile['taucher'] ); ?></td>
						<td><?php echo esc_html( (string) $zeile['taucher_gueltig'] ); ?></td>
						<td><?php echo esc_html( number_format_i18n( (float) $zeile['einnahmen'], 2 ) . ' €' ); ?></td>
						<td><?php echo esc_html( (string) $zeile['storniert'] ); ?></td>
						<td><?php echo esc_html( (string) $zeile['verfallen'] ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>

			<h2><?php esc_html_e( 'Beliebteste Stunden (gültige Buchungen)', 'seebuchung' ); ?></h2>
			<table class="widefat striped" style="max-width:24em">
				<thead><tr><th><?php esc_html_e( 'Slot', 'seebuchung' ); ?></th><th><?php esc_html_e( 'Taucher', 'seebuchung' ); ?></th></tr></thead>
				<tbody>
				<?php foreach ( $stunden_zeilen as $zeile ) : ?>
					<tr>
						<td><?php echo esc_html( sprintf( '%02d:00', (int) $zeile['stunde'] ) ); ?></td>
						<td><?php echo esc_html( (string) $zeile['taucher'] ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}
}
