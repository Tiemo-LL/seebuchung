<?php
/**
 * Schritt 2: Monatskalender mit Restplätzen.
 *
 * @package Seebuchung
 * @var \Seebuchung\Domain\See $see          Der See.
 * @var \DateTimeImmutable     $monat        Erster Tag des Monats.
 * @var array<string, int>     $tage         'Y-m-d' => maximaler Rest des Tages.
 * @var string                 $basis_url    URL der Buchungsseite.
 * @var string                 $vor_monat    Y-m des Vormonats.
 * @var string                 $naech_monat  Y-m des Folgemonats.
 * @var int                    $start_offset Leere Zellen vor dem 1. (Mo-basiert).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="seebuchung">
	<p><a href="<?php echo esc_url( $basis_url ); ?>">&larr; <?php esc_html_e( 'Alle Seen', 'seebuchung' ); ?></a></p>
	<h2><?php echo esc_html( $see->name ); ?></h2>
	<?php if ( '' !== (string) $see->info_text ) : ?>
		<p class="seebuchung-info"><?php echo esc_html( (string) $see->info_text ); ?></p>
	<?php endif; ?>

	<div class="seebuchung-monatsnav">
		<a href="
		<?php
		echo esc_url(
			add_query_arg(
				array(
					'sb_see'   => $see->id,
					'sb_monat' => $vor_monat,
				),
				$basis_url
			)
		);
		?>
		">&larr;</a>
		<strong><?php echo esc_html( wp_date( 'F Y', $monat->getTimestamp() ) ); ?></strong>
		<a href="
		<?php
		echo esc_url(
			add_query_arg(
				array(
					'sb_see'   => $see->id,
					'sb_monat' => $naech_monat,
				),
				$basis_url
			)
		);
		?>
		">&rarr;</a>
	</div>

	<div class="seebuchung-kalender" role="grid">
		<?php foreach ( array( __( 'Mo', 'seebuchung' ), __( 'Di', 'seebuchung' ), __( 'Mi', 'seebuchung' ), __( 'Do', 'seebuchung' ), __( 'Fr', 'seebuchung' ), __( 'Sa', 'seebuchung' ), __( 'So', 'seebuchung' ) ) as $wt ) : ?>
			<div class="seebuchung-kalender-kopf"><?php echo esc_html( $wt ); ?></div>
		<?php endforeach; ?>

		<?php for ( $i = 0; $i < $start_offset; $i++ ) : ?>
			<div class="seebuchung-tag seebuchung-tag--leer"></div>
		<?php endfor; ?>

		<?php foreach ( $tage as $datum => $rest ) : ?>
			<?php if ( $rest > 0 ) : ?>
				<a class="seebuchung-tag seebuchung-tag--frei"
					href="
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
							">
					<span class="seebuchung-tag-nr"><?php echo esc_html( (string) (int) substr( $datum, 8, 2 ) ); ?></span>
					<span class="seebuchung-tag-rest"><?php echo esc_html( (string) $rest ); ?></span>
				</a>
			<?php else : ?>
				<div class="seebuchung-tag seebuchung-tag--zu">
					<span class="seebuchung-tag-nr"><?php echo esc_html( (string) (int) substr( $datum, 8, 2 ) ); ?></span>
				</div>
			<?php endif; ?>
		<?php endforeach; ?>
	</div>
	<p class="seebuchung-legende">
		<?php esc_html_e( 'Zahl = freie Plätze. Graue Tage sind nicht buchbar.', 'seebuchung' ); ?>
	</p>
</div>
