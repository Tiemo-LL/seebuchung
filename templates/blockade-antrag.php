<?php
/**
 * Blockade-Antragsformular für Vereine (Token-Link).
 *
 * @package Seebuchung
 * @var array<string, mixed>     $verein    Der Verein.
 * @var \Seebuchung\Domain\See[] $seen      Aktive Seen.
 * @var string                   $token     Vereins-Token (Klartext).
 * @var int                      $zieljahr  Jahr, für das beantragt wird.
 * @var string                   $basis_url URL der Buchungsseite.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="seebuchung">
	<h2><?php esc_html_e( 'See-Blockade beantragen', 'seebuchung' ); ?></h2>
	<p>
		<?php
		echo esc_html(
			sprintf(
				/* translators: 1: Vereinsname, 2: Jahr */
				__( 'Verein: %1$s — Anträge gelten für die Saison %2$d. Pro See und Jahr sind maximal zwei Blockaden möglich, Stundenblockaden maximal drei Stunden.', 'seebuchung' ),
				(string) $verein['name'],
				$zieljahr
			)
		);
		?>
	</p>

	<form class="seebuchung-formular" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'seebuchung_blockade_antrag' ); ?>
		<input type="hidden" name="action" value="seebuchung_blockade_antrag">
		<input type="hidden" name="vereins_token" value="<?php echo esc_attr( $token ); ?>">
		<input type="hidden" name="zurueck" value="<?php echo esc_url( $basis_url ); ?>">

		<div class="seebuchung-feldzeile">
			<label>
				<?php esc_html_e( 'See', 'seebuchung' ); ?>
				<select name="see_id" required>
					<?php foreach ( $seen as $see ) : ?>
						<option value="<?php echo esc_attr( (string) $see->id ); ?>"><?php echo esc_html( $see->name ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<label>
				<?php esc_html_e( 'Datum', 'seebuchung' ); ?>
				<input type="date" name="datum" required
					min="<?php echo esc_attr( $zieljahr . '-01-01' ); ?>"
					max="<?php echo esc_attr( $zieljahr . '-12-31' ); ?>">
			</label>
		</div>

		<div class="seebuchung-feldzeile">
			<label>
				<input type="checkbox" name="ganzer_tag" value="1">
				<?php esc_html_e( 'Ganzer Tag (am Tagessee immer)', 'seebuchung' ); ?>
			</label>
			<label>
				<?php esc_html_e( 'Uhrzeit (von / bis, max. 3 Stunden)', 'seebuchung' ); ?>
				<span style="display:flex;gap:0.5rem">
					<input type="number" min="0" max="23" name="stunde_von" placeholder="9">
					<input type="number" min="1" max="24" name="stunde_bis" placeholder="12">
				</span>
			</label>
		</div>

		<div class="seebuchung-feldzeile">
			<label>
				<?php esc_html_e( 'Veranstaltung', 'seebuchung' ); ?>
				<input type="text" name="veranstaltung" required>
			</label>
			<label>
				<?php esc_html_e( 'Anzahl Taucher (optional)', 'seebuchung' ); ?>
				<input type="number" min="1" name="anzahl_taucher">
			</label>
		</div>

		<div class="seebuchung-feldzeile">
			<label>
				<?php esc_html_e( 'Verantwortliche:r', 'seebuchung' ); ?>
				<input type="text" name="verantwortlicher" required>
			</label>
			<label>
				<?php esc_html_e( 'E-Mail', 'seebuchung' ); ?>
				<input type="email" name="email" required>
			</label>
		</div>
		<div class="seebuchung-feldzeile">
			<label>
				<?php esc_html_e( 'Telefon', 'seebuchung' ); ?>
				<input type="tel" name="telefon">
			</label>
		</div>

		<button type="submit" class="seebuchung-button"><?php esc_html_e( 'Blockade beantragen', 'seebuchung' ); ?></button>
	</form>
</div>
