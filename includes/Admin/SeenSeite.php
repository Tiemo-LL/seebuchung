<?php
/**
 * Admin: Seen-Verwaltung mit Kontingent-Matrix.
 *
 * @package Seebuchung
 */

namespace Seebuchung\Admin;

use Seebuchung\Database\SeenRepository;
use Seebuchung\Domain\See;
use Seebuchung\Domain\Seemodus;
use Seebuchung\Rollen;

/**
 * Liste + Editor für Seen inkl. Saison, Buchungsfenster, Öffnungszeiten
 * und Kontingent-Matrix (Wochentag × Stunde bzw. Tageswert).
 */
final class SeenSeite {

	private const WOCHENTAGE = array(
		1 => 'Mo',
		2 => 'Di',
		3 => 'Mi',
		4 => 'Do',
		5 => 'Fr',
		6 => 'Sa',
		7 => 'So',
	);

	/**
	 * POST-Aktion registrieren.
	 */
	public static function aktionen_registrieren(): void {
		add_action( 'admin_post_seebuchung_see_speichern', array( new self(), 'speichern' ) );
	}

	/**
	 * Seite rendern: Liste oder Editor.
	 */
	public function render(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Lesende Navigation.
		$bearbeiten  = isset( $_GET['see'] ) ? (int) $_GET['see'] : -1;
		$gespeichert = isset( $_GET['sb_gespeichert'] );
		// phpcs:enable

		echo '<div class="wrap"><h1>' . esc_html__( 'Seen', 'seebuchung' ) . '</h1>';
		if ( $gespeichert ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'See gespeichert.', 'seebuchung' ) . '</p></div>';
		}

		if ( $bearbeiten >= 0 ) {
			$this->editor( $bearbeiten );
		} else {
			$this->liste();
		}
		echo '</div>';
	}

	/**
	 * Übersichtsliste.
	 */
	private function liste(): void {
		$seen = ( new SeenRepository() )->alle();
		?>
		<p><a class="button button-primary" href="
		<?php
		echo esc_url(
			add_query_arg(
				array(
					'page' => 'seebuchung-seen',
					'see'  => 0,
				),
				admin_url( 'admin.php' )
			)
		);
		?>
													"><?php esc_html_e( 'See hinzufügen', 'seebuchung' ); ?></a></p>
		<table class="widefat striped">
			<thead><tr>
				<th><?php esc_html_e( 'Name', 'seebuchung' ); ?></th>
				<th><?php esc_html_e( 'Modus', 'seebuchung' ); ?></th>
				<th><?php esc_html_e( 'Saison', 'seebuchung' ); ?></th>
				<th><?php esc_html_e( 'Aktiv', 'seebuchung' ); ?></th>
				<th></th>
			</tr></thead>
			<tbody>
			<?php foreach ( $seen as $see ) : ?>
				<tr>
					<td><strong><?php echo esc_html( $see->name ); ?></strong></td>
					<td><?php echo esc_html( Seemodus::TAG === $see->modus ? __( 'Tag', 'seebuchung' ) : __( 'Stunde', 'seebuchung' ) ); ?></td>
					<td><?php echo esc_html( ( $see->saison_von ?? '—' ) . ' – ' . ( $see->saison_bis ?? '—' ) ); ?></td>
					<td><?php echo $see->aktiv ? '✓' : '—'; ?></td>
					<td><a class="button button-small" href="
					<?php
					echo esc_url(
						add_query_arg(
							array(
								'page' => 'seebuchung-seen',
								'see'  => $see->id,
							),
							admin_url( 'admin.php' )
						)
					);
					?>
																"><?php esc_html_e( 'Bearbeiten', 'seebuchung' ); ?></a></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Editor-Formular für einen See (0 = neu).
	 *
	 * @param int $see_id See-ID.
	 */
	private function editor( int $see_id ): void {
		$repo = new SeenRepository();
		$see  = $see_id > 0 ? $this->see_finden( $repo, $see_id ) : null;

		$matrix = array();
		if ( null !== $see ) {
			foreach ( $repo->kontingente_fuer( $see->id ) as $zeile ) {
				$schluessel = null === $zeile['stunde'] ? 'tag' : (string) (int) $zeile['stunde'];
				$matrix[ (int) $zeile['wochentag'] ][ $schluessel ] = (int) $zeile['max_taucher'];
			}
		}
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'seebuchung_see_speichern' ); ?>
			<input type="hidden" name="action" value="seebuchung_see_speichern">
			<input type="hidden" name="see_id" value="<?php echo esc_attr( (string) ( $see->id ?? 0 ) ); ?>">

			<table class="form-table">
				<tr><th><label for="sb-name"><?php esc_html_e( 'Name', 'seebuchung' ); ?></label></th>
					<td><input id="sb-name" class="regular-text" name="name" required value="<?php echo esc_attr( $see->name ?? '' ); ?>"></td></tr>
				<tr><th><?php esc_html_e( 'Aktiv', 'seebuchung' ); ?></th>
					<td><label><input type="checkbox" name="aktiv" value="1" <?php checked( $see->aktiv ?? false ); ?>> <?php esc_html_e( 'See ist buchbar', 'seebuchung' ); ?></label></td></tr>
				<tr><th><?php esc_html_e( 'Kontingent-Modus', 'seebuchung' ); ?></th>
					<td>
						<label><input type="radio" name="modus" value="tag" <?php checked( ( $see->modus ?? 'tag' ), 'tag' ); ?>> <?php esc_html_e( 'Tageskontingent', 'seebuchung' ); ?></label><br>
						<label><input type="radio" name="modus" value="stunde" <?php checked( ( $see->modus ?? '' ), 'stunde' ); ?>> <?php esc_html_e( 'Stundenkontingent', 'seebuchung' ); ?></label>
					</td></tr>
				<tr><th><?php esc_html_e( 'Saison', 'seebuchung' ); ?></th>
					<td><input type="date" name="saison_von" value="<?php echo esc_attr( $see->saison_von ?? '' ); ?>"> –
						<input type="date" name="saison_bis" value="<?php echo esc_attr( $see->saison_bis ?? '' ); ?>"></td></tr>
				<tr><th><label for="sb-fenster"><?php esc_html_e( 'Buchungsfenster (Wochen im Voraus)', 'seebuchung' ); ?></label></th>
					<td><input id="sb-fenster" type="number" min="1" max="52" name="buchungsfenster_wochen" value="<?php echo esc_attr( (string) ( $see->buchungsfenster_wochen ?? 4 ) ); ?>"></td></tr>
				<tr><th><?php esc_html_e( 'Uhrzeiten Mo–Fr (von/bis)', 'seebuchung' ); ?></th>
					<td><input type="number" min="0" max="23" name="stunde_von_woche" value="<?php echo esc_attr( (string) ( $see->stunde_von_woche ?? '' ) ); ?>"> –
						<input type="number" min="1" max="24" name="stunde_bis_woche" value="<?php echo esc_attr( (string) ( $see->stunde_bis_woche ?? '' ) ); ?>"></td></tr>
				<tr><th><?php esc_html_e( 'Uhrzeiten Sa/So (von/bis)', 'seebuchung' ); ?></th>
					<td><input type="number" min="0" max="23" name="stunde_von_wochenende" value="<?php echo esc_attr( (string) ( $see->stunde_von_wochenende ?? '' ) ); ?>"> –
						<input type="number" min="1" max="24" name="stunde_bis_wochenende" value="<?php echo esc_attr( (string) ( $see->stunde_bis_wochenende ?? '' ) ); ?>"></td></tr>
				<tr><th><?php esc_html_e( 'Gruppengröße (min/max)', 'seebuchung' ); ?></th>
					<td><input type="number" min="1" name="min_anmelder" value="<?php echo esc_attr( (string) ( $see->min_anmelder ?? 1 ) ); ?>"> –
						<input type="number" min="1" name="max_pro_buchung" value="<?php echo esc_attr( (string) ( $see->max_pro_buchung ?? 10 ) ); ?>"></td></tr>
				<tr><th><label for="sb-preis"><?php esc_html_e( 'Preis pro zahlungspflichtiger Person (€)', 'seebuchung' ); ?></label></th>
					<td><input id="sb-preis" type="number" step="0.01" min="0" name="preis_pro_person" value="<?php echo esc_attr( number_format( $see->preis_pro_person ?? 0, 2, '.', '' ) ); ?>"></td></tr>
				<tr><th><?php esc_html_e( 'Alle zahlungspflichtig', 'seebuchung' ); ?></th>
					<td><label><input type="checkbox" name="kostenpflichtig" value="1" <?php checked( $see->kostenpflichtig ?? false ); ?>> <?php esc_html_e( 'An diesem See zahlen ALLE Taucher (nicht nur Gäste ohne Verein)', 'seebuchung' ); ?></label></td></tr>
				<tr><th><label for="sb-info"><?php esc_html_e( 'Info-Text', 'seebuchung' ); ?></label></th>
					<td><textarea id="sb-info" class="large-text" rows="3" name="info_text"><?php echo esc_textarea( $see->info_text ?? '' ); ?></textarea></td></tr>
			</table>

			<h2><?php esc_html_e( 'Kontingent-Matrix', 'seebuchung' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Tagessee: nur die Spalte "Tag" füllen. Stundensee: Werte je Stunde (leer/0 = nicht buchbar).', 'seebuchung' ); ?></p>
			<div style="overflow-x:auto">
			<table class="widefat" style="width:auto">
				<thead><tr>
					<th></th>
					<th><?php esc_html_e( 'Tag', 'seebuchung' ); ?></th>
					<?php for ( $stunde = 6; $stunde <= 23; $stunde++ ) : ?>
						<th><?php echo esc_html( (string) $stunde ); ?></th>
					<?php endfor; ?>
				</tr></thead>
				<tbody>
				<?php foreach ( self::WOCHENTAGE as $nr => $kuerzel ) : ?>
					<tr>
						<th><?php echo esc_html( $kuerzel ); ?></th>
						<td><input type="number" min="0" style="width:4em" name="kontingent[<?php echo esc_attr( (string) $nr ); ?>][tag]" value="<?php echo esc_attr( (string) ( $matrix[ $nr ]['tag'] ?? '' ) ); ?>"></td>
						<?php for ( $stunde = 6; $stunde <= 23; $stunde++ ) : ?>
							<td><input type="number" min="0" style="width:3.5em" name="kontingent[<?php echo esc_attr( (string) $nr ); ?>][<?php echo esc_attr( (string) $stunde ); ?>]" value="<?php echo esc_attr( (string) ( $matrix[ $nr ][ (string) $stunde ] ?? '' ) ); ?>"></td>
						<?php endfor; ?>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			</div>
			<p class="description"><?php esc_html_e( 'Stunden 0–5 sind über den Import möglich, hier aber ausgeblendet (kein Tauchbetrieb).', 'seebuchung' ); ?></p>

			<p><button class="button button-primary"><?php esc_html_e( 'Speichern', 'seebuchung' ); ?></button>
			<a class="button" href="<?php echo esc_url( add_query_arg( 'page', 'seebuchung-seen', admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'Abbrechen', 'seebuchung' ); ?></a></p>
		</form>
		<?php
	}

	/**
	 * POST: See + Matrix speichern.
	 */
	public function speichern(): void {
		if ( ! current_user_can( Rollen::CAP_VERWALTEN ) ) {
			wp_die( esc_html__( 'Keine Berechtigung.', 'seebuchung' ) );
		}
		check_admin_referer( 'seebuchung_see_speichern' );

		$see_id = isset( $_POST['see_id'] ) ? (int) $_POST['see_id'] : 0;
		$name   = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$modus  = isset( $_POST['modus'] ) && 'stunde' === $_POST['modus'] ? Seemodus::STUNDE : Seemodus::TAG;

		$daten = array(
			'name'                   => $name,
			'slug'                   => sanitize_title( $name ),
			'aktiv'                  => isset( $_POST['aktiv'] ) ? 1 : 0,
			'modus'                  => $modus,
			'saison_von'             => $this->datum_oder_null( 'saison_von' ),
			'saison_bis'             => $this->datum_oder_null( 'saison_bis' ),
			'buchungsfenster_wochen' => isset( $_POST['buchungsfenster_wochen'] ) ? max( 1, (int) $_POST['buchungsfenster_wochen'] ) : 4,
			'stunde_von_woche'       => $this->zahl_oder_null( 'stunde_von_woche' ),
			'stunde_bis_woche'       => $this->zahl_oder_null( 'stunde_bis_woche' ),
			'stunde_von_wochenende'  => $this->zahl_oder_null( 'stunde_von_wochenende' ),
			'stunde_bis_wochenende'  => $this->zahl_oder_null( 'stunde_bis_wochenende' ),
			'min_anmelder'           => isset( $_POST['min_anmelder'] ) ? max( 1, (int) $_POST['min_anmelder'] ) : 1,
			'max_pro_buchung'        => isset( $_POST['max_pro_buchung'] ) ? max( 1, (int) $_POST['max_pro_buchung'] ) : 10,
			'preis_pro_person'       => isset( $_POST['preis_pro_person'] ) ? max( 0, (float) $_POST['preis_pro_person'] ) : 0,
			'kostenpflichtig'        => isset( $_POST['kostenpflichtig'] ) ? 1 : 0,
			'info_text'              => isset( $_POST['info_text'] ) ? sanitize_textarea_field( wp_unslash( $_POST['info_text'] ) ) : '',
		);

		$repo   = new SeenRepository();
		$see_id = $repo->speichern( $daten, $see_id );

		// Matrix übernehmen: Tagessee nutzt die Tag-Spalte, Stundensee die Stunden.
		$zeilen = array();
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Zahlenmatrix, jede Zelle wird unten gecastet.
		$eingabe = isset( $_POST['kontingent'] ) ? (array) wp_unslash( $_POST['kontingent'] ) : array();
		foreach ( $eingabe as $wochentag => $spalten ) {
			$wochentag = (int) $wochentag;
			if ( $wochentag < 1 || $wochentag > 7 || ! is_array( $spalten ) ) {
				continue;
			}
			foreach ( $spalten as $spalte => $wert ) {
				$max = (int) $wert;
				if ( $max < 1 ) {
					continue;
				}
				$ist_tag      = 'tag' === $spalte;
				$ist_tagessee = Seemodus::TAG === $modus;
				if ( $ist_tag !== $ist_tagessee ) {
					continue; // Nur die zum Modus passende Spalte übernehmen.
				}
				$zeilen[] = array(
					'wochentag'   => $wochentag,
					'stunde'      => $ist_tag ? null : (int) $spalte,
					'max_taucher' => $max,
				);
			}
		}
		$repo->kontingente_ersetzen( $see_id, $zeilen );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'           => 'seebuchung-seen',
					'see'            => $see_id,
					'sb_gespeichert' => 1,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * See per ID aus der Liste.
	 *
	 * @param SeenRepository $repo   Repository.
	 * @param int            $see_id See-ID.
	 */
	private function see_finden( SeenRepository $repo, int $see_id ): ?See {
		foreach ( $repo->alle() as $see ) {
			if ( $see->id === $see_id ) {
				return $see;
			}
		}
		return null;
	}

	/**
	 * POST-Datum oder null.
	 *
	 * @param string $feld Feldname.
	 */
	private function datum_oder_null( string $feld ): ?string {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce in speichern() geprüft.
		$wert = isset( $_POST[ $feld ] ) ? sanitize_text_field( wp_unslash( $_POST[ $feld ] ) ) : '';
		return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $wert ) ? $wert : null;
	}

	/**
	 * POST-Zahl oder null (leeres Feld).
	 *
	 * @param string $feld Feldname.
	 */
	private function zahl_oder_null( string $feld ): ?int {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce in speichern() geprüft.
		$wert = isset( $_POST[ $feld ] ) ? trim( sanitize_text_field( wp_unslash( $_POST[ $feld ] ) ) ) : '';
		return '' === $wert ? null : (int) $wert;
	}
}
