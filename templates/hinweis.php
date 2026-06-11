<?php
/**
 * Hinweis-Box.
 *
 * @package Seebuchung
 * @var string $text   Meldungstext.
 * @var bool   $fehler Ob es eine Fehlermeldung ist.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$ist_fehler = isset( $fehler ) && $fehler;
?>
<div class="seebuchung-hinweis <?php echo $ist_fehler ? 'seebuchung-hinweis--fehler' : 'seebuchung-hinweis--ok'; ?>" role="alert">
	<?php echo esc_html( $text ); ?>
</div>
