<?php
/**
 * Schritt 3: Stundenwahl am Stundensee.
 *
 * @package Seebuchung
 * @var \Seebuchung\Domain\See $see        Der See.
 * @var string                 $datum      Gewähltes Datum (Y-m-d).
 * @var array<int, int>        $uebersicht Stunde => Rest.
 * @var string                 $basis_url  URL der Buchungsseite.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="seebuchung">
	<p><a href="
	<?php
	echo esc_url(
		add_query_arg(
			array(
				'sb_see'   => $see->id,
				'sb_monat' => substr( $datum, 0, 7 ),
			),
			$basis_url
		)
	);
	?>
	">&larr; <?php esc_html_e( 'Zurück zum Kalender', 'seebuchung' ); ?></a></p>
	<h2><?php echo esc_html( $see->name ); ?> · <?php echo esc_html( wp_date( 'l, j. F Y', strtotime( $datum ) ) ); ?></h2>
	<h3><?php esc_html_e( 'Uhrzeit wählen', 'seebuchung' ); ?></h3>

	<div class="seebuchung-stunden">
		<?php foreach ( $uebersicht as $slot => $rest ) : ?>
			<?php if ( $rest > 0 ) : ?>
				<a class="seebuchung-stunde seebuchung-stunde--frei"
					href="
					<?php
					echo esc_url(
						add_query_arg(
							array(
								'sb_see'    => $see->id,
								'sb_datum'  => $datum,
								'sb_stunde' => $slot,
							),
							$basis_url
						)
					);
					?>
							">
					<?php echo esc_html( sprintf( '%02d:00', (int) $slot ) ); ?>
					<small>
					<?php
					echo esc_html(
						sprintf(
							/* translators: %d: freie Plätze */
							_n( '%d Platz frei', '%d Plätze frei', $rest, 'seebuchung' ),
							$rest
						)
					);
					?>
					</small>
				</a>
			<?php else : ?>
				<span class="seebuchung-stunde seebuchung-stunde--zu">
					<?php echo esc_html( sprintf( '%02d:00', (int) $slot ) ); ?>
					<small><?php esc_html_e( 'belegt', 'seebuchung' ); ?></small>
				</span>
			<?php endif; ?>
		<?php endforeach; ?>
	</div>
</div>
