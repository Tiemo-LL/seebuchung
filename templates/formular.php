<?php
/**
 * Schritt 4: Buchungsformular.
 *
 * @package Seebuchung
 * @var \Seebuchung\Domain\See               $see       Der See.
 * @var string                               $datum     Datum (Y-m-d).
 * @var int|null                             $stunde    Slot (null am Tagessee).
 * @var int                                  $rest      Freie Plätze.
 * @var array<int, array<string, mixed>>     $brevets   Brevets fürs Dropdown.
 * @var string                               $basis_url URL der Buchungsseite.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$max_taucher = min( $rest, $see->max_pro_buchung );
?>
<div class="seebuchung">
	<p>
		<a href="
		<?php
		echo esc_url(
			add_query_arg(
				array(
					'sb_see'   => $see->id,
					'sb_datum' => $datum,
				),
				$basis_url
			)
		);
		?>
		">&larr; <?php esc_html_e( 'Zurück', 'seebuchung' ); ?></a>
	</p>
	<h2><?php esc_html_e( 'Buchung', 'seebuchung' ); ?></h2>
	<p class="seebuchung-zusammenfassung">
		<strong><?php echo esc_html( $see->name ); ?></strong><br>
		<?php
		echo esc_html( wp_date( 'l, j. F Y', strtotime( $datum ) ) );
		if ( null !== $stunde ) {
			echo esc_html( ', ' . sprintf( '%02d:00', $stunde ) . ' ' . __( 'Uhr', 'seebuchung' ) );
		}
		?>
		<br>
		<?php
		echo esc_html(
			sprintf(
				/* translators: %d: freie Plätze */
				_n( 'Noch %d Platz frei', 'Noch %d Plätze frei', $rest, 'seebuchung' ),
				$rest
			)
		);
		?>
	</p>

	<form class="seebuchung-formular" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'seebuchung_buchen' ); ?>
		<input type="hidden" name="action" value="seebuchung_buchen">
		<input type="hidden" name="see_id" value="<?php echo esc_attr( (string) $see->id ); ?>">
		<input type="hidden" name="datum" value="<?php echo esc_attr( $datum ); ?>">
		<input type="hidden" name="stunde" value="<?php echo esc_attr( null === $stunde ? '' : (string) $stunde ); ?>">
		<input type="hidden" name="zurueck" value="<?php echo esc_url( $basis_url ); ?>">

		<div class="seebuchung-feldzeile">
			<label>
				<?php esc_html_e( 'Vorname', 'seebuchung' ); ?>
				<input type="text" name="vorname" required autocomplete="given-name">
			</label>
			<label>
				<?php esc_html_e( 'Nachname', 'seebuchung' ); ?>
				<input type="text" name="nachname" required autocomplete="family-name">
			</label>
		</div>
		<div class="seebuchung-feldzeile">
			<label>
				<?php esc_html_e( 'E-Mail', 'seebuchung' ); ?>
				<input type="email" name="email" required autocomplete="email" inputmode="email">
			</label>
			<label>
				<?php esc_html_e( 'Telefon', 'seebuchung' ); ?>
				<input type="tel" name="telefon" autocomplete="tel" inputmode="tel">
			</label>
		</div>
		<div class="seebuchung-feldzeile">
			<label>
				<?php esc_html_e( 'Vereins-Nr. (leer lassen, wenn kein Verband-Verein)', 'seebuchung' ); ?>
				<input type="text" name="vereins_nummer" inputmode="numeric">
			</label>
			<label>
				<?php esc_html_e( 'Brevet Gruppenleiter:in', 'seebuchung' ); ?>
				<select name="brevet_id">
					<option value="0"><?php esc_html_e( '— bitte wählen —', 'seebuchung' ); ?></option>
					<?php foreach ( $brevets as $brevet ) : ?>
						<option value="<?php echo esc_attr( (string) $brevet['id'] ); ?>"><?php echo esc_html( (string) $brevet['bezeichnung'] ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
		</div>
		<div class="seebuchung-feldzeile">
			<label>
				<?php esc_html_e( 'Anzahl Taucher', 'seebuchung' ); ?>
				<input type="number" name="anzahl_taucher" min="<?php echo esc_attr( (string) $see->min_anmelder ); ?>" max="<?php echo esc_attr( (string) $max_taucher ); ?>" value="<?php echo esc_attr( (string) $see->min_anmelder ); ?>" required inputmode="numeric">
			</label>
			<label>
				<?php esc_html_e( 'davon zahlungspflichtig (ohne Verbands-Verein)', 'seebuchung' ); ?>
				<input type="number" name="anzahl_zahler" min="0" max="<?php echo esc_attr( (string) $max_taucher ); ?>" value="0" required inputmode="numeric">
			</label>
		</div>

		<?php if ( $see->preis_pro_person > 0 ) : ?>
			<p class="seebuchung-preis-hinweis">
				<?php
				echo esc_html(
					sprintf(
						/* translators: %s: Preis pro Person */
						__( 'Gebühr: %s € pro zahlungspflichtiger Person. Bei Storno erfolgt keine Erstattung.', 'seebuchung' ),
						number_format_i18n( $see->preis_pro_person, 2 )
					)
				);
				if ( $see->kostenpflichtig ) {
					echo ' ';
					esc_html_e( 'An diesem See sind alle Taucher zahlungspflichtig.', 'seebuchung' );
				}
				?>
			</p>
		<?php endif; ?>

		<p class="seebuchung-datenschutz">
			<?php esc_html_e( 'Deine Daten werden nur für diese Buchung verwendet und nach Ablauf der gesetzlichen Fristen automatisch anonymisiert.', 'seebuchung' ); ?>
		</p>

		<button type="submit" class="seebuchung-button"><?php esc_html_e( 'Verbindlich anfragen', 'seebuchung' ); ?></button>
	</form>
</div>
