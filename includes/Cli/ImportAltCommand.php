<?php
/**
 * WP-CLI-Kommando für den Altsystem-Import.
 *
 * @package Seebuchung
 */

namespace Seebuchung\Cli;

use Seebuchung\Import\AltImporter;

/**
 * Importiert die Stammdaten (Seen, Kontingente, Vereine, Brevets) aus
 * einem mysqldump des Altsystems "Seeanmeldung".
 *
 * ## OPTIONS
 *
 * <pfad>
 * : Pfad zur Dump-Datei (.sql oder .sql.gz).
 *
 * ## EXAMPLES
 *
 *     wp seebuchung import-alt backup/20260610.sql.gz
 */
final class ImportAltCommand {

	/**
	 * Kommando ausführen.
	 *
	 * @param array<int, string> $args Positionsargumente (Pfad).
	 */
	public function __invoke( array $args ): void {
		list( $pfad ) = $args;

		if ( ! file_exists( $pfad ) ) {
			\WP_CLI::error( "Datei nicht gefunden: {$pfad}" );
		}

		// phpcs:disable WordPress.WP.AlternativeFunctions -- Lokale Datei im CLI-Kontext.
		$dump = str_ends_with( $pfad, '.gz' )
			? implode( '', (array) gzfile( $pfad ) )
			: (string) file_get_contents( $pfad );
		// phpcs:enable

		if ( '' === $dump ) {
			\WP_CLI::error( 'Dump ist leer oder konnte nicht gelesen werden.' );
		}

		try {
			$ergebnis = ( new AltImporter() )->importieren( $dump );
		} catch ( \RuntimeException $e ) {
			\WP_CLI::error( $e->getMessage() );
		}

		foreach ( $ergebnis as $tabelle => $anzahl ) {
			\WP_CLI::log( sprintf( '%-12s %d Zeilen', $tabelle . ':', $anzahl ) );
		}
		\WP_CLI::success( 'Stammdaten-Import abgeschlossen.' );
	}
}
