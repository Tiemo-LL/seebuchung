<?php
/**
 * Admin: Stammdaten-Import aus dem Altsystem.
 *
 * @package Seebuchung
 */

namespace Seebuchung\Admin;

use Seebuchung\Import\AltImporter;
use Seebuchung\Rollen;

/**
 * Upload-Variante des WP-CLI-Imports — für Hosting ohne Shell-Zugang.
 * Importiert nur in leere Tabellen (gleiche Sicherheitsregel wie die CLI).
 */
final class ImportSeite {

	/**
	 * POST-Aktion registrieren.
	 */
	public static function aktionen_registrieren(): void {
		add_action( 'admin_post_seebuchung_import', array( new self(), 'verarbeiten' ) );
	}

	/**
	 * Seite rendern.
	 */
	public function render(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Nur Meldungsanzeige.
		$meldung = isset( $_GET['sb_meldung'] ) ? sanitize_text_field( wp_unslash( $_GET['sb_meldung'] ) ) : '';
		$fehler  = isset( $_GET['sb_fehler'] ) ? sanitize_text_field( wp_unslash( $_GET['sb_fehler'] ) ) : '';
		// phpcs:enable
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Stammdaten-Import (Altsystem)', 'seebuchung' ); ?></h1>

			<?php if ( '' !== $meldung ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php echo esc_html( $meldung ); ?></p></div>
			<?php elseif ( '' !== $fehler ) : ?>
				<div class="notice notice-error is-dismissible"><p><?php echo esc_html( $fehler ); ?></p></div>
			<?php endif; ?>

			<p><?php esc_html_e( 'Importiert Seen, Kontingente, Vereine und Brevets aus einem mysqldump des Altsystems "Seeanmeldung" (.sql oder .sql.gz). Der Import läuft nur, wenn die Zieltabellen leer sind — vorhandene Daten werden nie überschrieben.', 'seebuchung' ); ?></p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
				<?php wp_nonce_field( 'seebuchung_import' ); ?>
				<input type="hidden" name="action" value="seebuchung_import">
				<p><input type="file" name="dump" accept=".sql,.gz" required></p>
				<p><button class="button button-primary"><?php esc_html_e( 'Import starten', 'seebuchung' ); ?></button></p>
			</form>
		</div>
		<?php
	}

	/**
	 * POST: Upload entgegennehmen und importieren.
	 */
	public function verarbeiten(): void {
		if ( ! current_user_can( Rollen::CAP_VERWALTEN ) ) {
			wp_die( esc_html__( 'Keine Berechtigung.', 'seebuchung' ) );
		}
		check_admin_referer( 'seebuchung_import' );

		$ziel = add_query_arg( 'page', 'seebuchung-import', admin_url( 'admin.php' ) );

		// phpcs:disable WordPress.Security.ValidatedSanitizedInput -- Datei-Upload; Pfad stammt von PHP, Inhalt geht nur durch den Parser.
		$pfad   = isset( $_FILES['dump']['tmp_name'] ) ? (string) $_FILES['dump']['tmp_name'] : '';
		$name   = isset( $_FILES['dump']['name'] ) ? (string) $_FILES['dump']['name'] : '';
		$status = isset( $_FILES['dump']['error'] ) ? (int) $_FILES['dump']['error'] : UPLOAD_ERR_NO_FILE;
		// phpcs:enable

		if ( UPLOAD_ERR_OK !== $status || '' === $pfad || ! is_uploaded_file( $pfad ) ) {
			wp_safe_redirect( add_query_arg( 'sb_fehler', rawurlencode( __( 'Upload fehlgeschlagen.', 'seebuchung' ) ), $ziel ) );
			exit;
		}

		// phpcs:disable WordPress.WP.AlternativeFunctions -- Lokale Upload-Datei.
		$dump = str_ends_with( strtolower( $name ), '.gz' )
			? implode( '', (array) gzfile( $pfad ) )
			: (string) file_get_contents( $pfad );
		// phpcs:enable

		if ( '' === $dump ) {
			wp_safe_redirect( add_query_arg( 'sb_fehler', rawurlencode( __( 'Die Datei ist leer oder konnte nicht gelesen werden.', 'seebuchung' ) ), $ziel ) );
			exit;
		}

		try {
			$ergebnis = ( new AltImporter() )->importieren( $dump );
		} catch ( \RuntimeException $e ) {
			wp_safe_redirect( add_query_arg( 'sb_fehler', rawurlencode( $e->getMessage() ), $ziel ) );
			exit;
		}

		$teile = array();
		foreach ( $ergebnis as $tabelle => $anzahl ) {
			$teile[] = "{$tabelle}: {$anzahl}";
		}
		wp_safe_redirect(
			add_query_arg(
				'sb_meldung',
				rawurlencode( __( 'Import abgeschlossen — ', 'seebuchung' ) . implode( ', ', $teile ) ),
				$ziel
			)
		);
		exit;
	}
}
