<?php
/**
 * Stammdaten-Import aus dem Altsystem.
 *
 * @package Seebuchung
 */

namespace Seebuchung\Import;

use Seebuchung\Database\Schema;
use Seebuchung\Domain\Seemodus;

/**
 * Importiert Seen, Kontingente, Vereine und Brevets aus dem Alt-Dump.
 *
 * Sicherheitsregel: importiert nur in LEERE Zieltabellen — bei vorhandenen
 * Daten bricht der Import ab, damit nichts überschrieben wird.
 */
final class AltImporter {

	/**
	 * Import ausführen.
	 *
	 * @param string $dump Roher SQL-Dump-Inhalt (bereits entpackt).
	 * @return array<string, int> Tabelle => Anzahl importierter Zeilen.
	 * @throws \RuntimeException Wenn Zieltabellen nicht leer sind oder der Dump unbrauchbar ist.
	 */
	public function importieren( string $dump ): array {
		global $wpdb;

		foreach ( array( 'seen', 'kontingente', 'vereine', 'brevets' ) as $tabelle ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- Eigene Tabelle, Name aus Schema::table().
			$anzahl = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . Schema::table( $tabelle ) );
			if ( $anzahl > 0 ) {
				throw new \RuntimeException( esc_html( "Zieltabelle {$tabelle} ist nicht leer ({$anzahl} Zeilen) — Import abgebrochen." ) );
			}
		}

		$parser = new AltDumpParser( $dump );
		$jetzt  = current_time( 'mysql' );

		// Seen.
		$seen_zeilen  = $parser->zeilen( 'see', AltMapper::SPALTEN['see'] );
		$tagessee_ids = array();
		foreach ( $seen_zeilen as $alt ) {
			$neu = AltMapper::see( $alt );
			if ( Seemodus::TAG === $neu['modus'] ) {
				$tagessee_ids[] = $neu['id'];
			}
			$neu['created_at'] = $jetzt;
			$neu['updated_at'] = $jetzt;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Eigene Tabelle.
			$wpdb->insert( Schema::table( 'seen' ), $neu );
		}

		// Kontingente.
		$kontingent_anzahl = 0;
		foreach ( $parser->zeilen( 'maxfrei', AltMapper::SPALTEN['maxfrei'] ) as $alt ) {
			$ist_tagessee = in_array( (int) $alt['see_id'], $tagessee_ids, true );
			foreach ( AltMapper::kontingente( $alt, $ist_tagessee ) as $zeile ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Eigene Tabelle.
				$wpdb->insert( Schema::table( 'kontingente' ), $zeile );
				++$kontingent_anzahl;
			}
		}

		// Vereine (mit Verbandsnamen-Lookup).
		$verbaende = array();
		foreach ( $parser->zeilen( 'verbaende', AltMapper::SPALTEN['verbaende'] ) as $alt ) {
			$verbaende[ $alt['verband_nr'] ] = (string) $alt['name'];
		}
		$vereins_anzahl = 0;
		foreach ( $parser->zeilen( 'vereine', AltMapper::SPALTEN['vereine'] ) as $alt ) {
			if ( ! AltMapper::verein_importierbar( $alt ) ) {
				continue;
			}
			$neu               = AltMapper::verein( $alt, $verbaende );
			$neu['created_at'] = $jetzt;
			$neu['updated_at'] = $jetzt;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Eigene Tabelle.
			$wpdb->insert( Schema::table( 'vereine' ), $neu );
			++$vereins_anzahl;
		}

		// Brevets.
		$brevet_anzahl = 0;
		foreach ( $parser->zeilen( 'brevets', AltMapper::SPALTEN['brevets'] ) as $alt ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Eigene Tabelle.
			$wpdb->insert( Schema::table( 'brevets' ), AltMapper::brevet( $alt ) );
			++$brevet_anzahl;
		}

		return array(
			'seen'        => count( $seen_zeilen ),
			'kontingente' => $kontingent_anzahl,
			'vereine'     => $vereins_anzahl,
			'brevets'     => $brevet_anzahl,
		);
	}
}
