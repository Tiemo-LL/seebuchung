<?php
/**
 * Parser für den MySQL-Dump des Altsystems.
 *
 * @package Seebuchung
 */

namespace Seebuchung\Import;

/**
 * Extrahiert die Datenzeilen einzelner Tabellen aus einem mysqldump
 * (Format "INSERT INTO `tab` VALUES (…),(…);").
 *
 * Pure Klasse ohne WP-/DB-Zugriff — per Unit-Test abgedeckt. Die
 * Spaltenreihenfolge liefert der Aufrufer (Altsystem v8.4 ist eingefroren,
 * READ-ONLY-Referenz lt. CLAUDE.md).
 */
final class AltDumpParser {

	/**
	 * Konstruktor.
	 *
	 * @param string $dump Roher Dump-Inhalt.
	 */
	public function __construct( private readonly string $dump ) {
	}

	/**
	 * Alle Datenzeilen einer Tabelle als assoziative Arrays.
	 *
	 * @param string   $tabelle  Tabellenname im Altsystem (z. B. 'see').
	 * @param string[] $spalten  Spaltennamen in Dump-Reihenfolge.
	 * @return array<int, array<string, string|null>>
	 * @throws \RuntimeException Wenn die Spaltenzahl einer Zeile nicht passt.
	 */
	public function zeilen( string $tabelle, array $spalten ): array {
		$zeilen   = array();
		$offset   = 0;
		$suche    = "INSERT INTO `{$tabelle}` VALUES ";
		$position = strpos( $this->dump, $suche, $offset );

		while ( false !== $position ) {
			$start = $position + strlen( $suche );
			$ende  = $this->statement_ende( $start );
			foreach ( $this->tupel_parsen( substr( $this->dump, $start, $ende - $start ) ) as $werte ) {
				if ( count( $werte ) !== count( $spalten ) ) {
					// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Pure Klasse ohne WP; Meldung enthält nur Zähler.
					throw new \RuntimeException( "Tabelle {$tabelle}: " . count( $werte ) . ' Werte, aber ' . count( $spalten ) . ' Spalten erwartet.' );
				}
				$zeilen[] = array_combine( $spalten, $werte );
			}
			$offset   = $ende;
			$position = strpos( $this->dump, $suche, $offset );
		}

		return $zeilen;
	}

	/**
	 * Position des abschließenden Semikolons (außerhalb von Strings).
	 *
	 * @param int $start Startposition hinter "VALUES ".
	 */
	private function statement_ende( int $start ): int {
		$laenge    = strlen( $this->dump );
		$in_string = false;

		for ( $i = $start; $i < $laenge; $i++ ) {
			$zeichen = $this->dump[ $i ];
			if ( $in_string ) {
				if ( '\\' === $zeichen ) {
					++$i;
				} elseif ( "'" === $zeichen ) {
					$in_string = false;
				}
			} elseif ( "'" === $zeichen ) {
				$in_string = true;
			} elseif ( ';' === $zeichen ) {
				return $i;
			}
		}

		return $laenge;
	}

	/**
	 * Zerlegt "(a,b),(c,d)" in Wertelisten.
	 *
	 * Strings werden entquotet (\'-Escapes aufgelöst), NULL wird zu null,
	 * Zahlen bleiben Strings (Typkonvertierung macht der Mapper).
	 *
	 * @param string $werteliste Inhalt zwischen VALUES und Semikolon.
	 * @return array<int, array<int, string|null>>
	 */
	private function tupel_parsen( string $werteliste ): array {
		$tupel     = array();
		$aktuell   = array();
		$wert      = '';
		$in_string = false;
		$in_tupel  = false;
		$ist_str   = false;
		$laenge    = strlen( $werteliste );

		for ( $i = 0; $i < $laenge; $i++ ) {
			$zeichen = $werteliste[ $i ];

			if ( $in_string ) {
				if ( '\\' === $zeichen && $i + 1 < $laenge ) {
					$wert .= stripcslashes( '\\' . $werteliste[ $i + 1 ] );
					++$i;
				} elseif ( "'" === $zeichen ) {
					$in_string = false;
				} else {
					$wert .= $zeichen;
				}
				continue;
			}

			if ( ! $in_tupel ) {
				if ( '(' === $zeichen ) {
					$in_tupel = true;
					$aktuell  = array();
					$wert     = '';
					$ist_str  = false;
				}
				continue;
			}

			switch ( $zeichen ) {
				case "'":
					$in_string = true;
					$ist_str   = true;
					break;
				case ',':
					$aktuell[] = $this->wert_abschliessen( $wert, $ist_str );
					$wert      = '';
					$ist_str   = false;
					break;
				case ')':
					$aktuell[] = $this->wert_abschliessen( $wert, $ist_str );
					$tupel[]   = $aktuell;
					$in_tupel  = false;
					break;
				default:
					$wert .= $zeichen;
			}
		}

		return $tupel;
	}

	/**
	 * Rohwert finalisieren: NULL-Literal zu null, sonst getrimmter String.
	 *
	 * @param string $wert    Gesammelte Zeichen.
	 * @param bool   $ist_str Ob der Wert in Quotes stand.
	 */
	private function wert_abschliessen( string $wert, bool $ist_str ): ?string {
		if ( $ist_str ) {
			return $wert;
		}
		$wert = trim( $wert );
		return ( 'NULL' === strtoupper( $wert ) ) ? null : $wert;
	}
}
