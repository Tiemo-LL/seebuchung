<?php
/**
 * Schritt 1: Seeauswahl.
 *
 * @package Seebuchung
 * @var \Seebuchung\Domain\See[] $seen Aktive Seen.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="seebuchung">
	<h2><?php esc_html_e( 'See auswählen', 'seebuchung' ); ?></h2>
	<?php if ( array() === $seen ) : ?>
		<p><?php esc_html_e( 'Derzeit ist kein See buchbar.', 'seebuchung' ); ?></p>
	<?php else : ?>
		<ul class="seebuchung-seen">
			<?php foreach ( $seen as $see ) : ?>
				<li>
					<a class="seebuchung-see-karte" href="<?php echo esc_url( add_query_arg( 'sb_see', $see->id, get_permalink() ) ); ?>">
						<strong><?php echo esc_html( $see->name ); ?></strong>
						<span>
							<?php
							echo esc_html(
								\Seebuchung\Domain\Seemodus::TAG === $see->modus
									? __( 'Tagesbuchung', 'seebuchung' )
									: __( 'Stundenbuchung', 'seebuchung' )
							);
							if ( $see->preis_pro_person > 0 ) {
								echo esc_html(
									' · ' . sprintf(
										/* translators: %s: Preis */
										__( '%s € pro Gast', 'seebuchung' ),
										number_format_i18n( $see->preis_pro_person, 2 )
									)
								);
							}
							?>
						</span>
						<?php if ( '' !== (string) $see->saison_von ) : ?>
							<span class="seebuchung-saison">
								<?php
								echo esc_html(
									sprintf(
										/* translators: 1: Saisonstart, 2: Saisonende */
										__( 'Saison: %1$s – %2$s', 'seebuchung' ),
										wp_date( 'j. F', strtotime( (string) $see->saison_von ) ),
										wp_date( 'j. F', strtotime( (string) $see->saison_bis ) )
									)
								);
								?>
							</span>
						<?php endif; ?>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
</div>
