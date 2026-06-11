<?php
/**
 * Datenzugriff für Vereine.
 *
 * @package Seebuchung
 */

namespace Seebuchung\Database;

/**
 * Lookup auf seebuchung_vereine.
 */
final class VereinsRepository {

	/**
	 * Aktiven Verein über die Vereins-Nr. finden.
	 *
	 * @param string $nummer Vereins-Nr. (wie vom Taucher eingegeben).
	 * @return array<string, mixed>|null
	 */
	public function per_nummer( string $nummer ): ?array {
		global $wpdb;
		$tabelle = Schema::table( 'vereine' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Eigene Tabelle.
		$zeile = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$tabelle} WHERE nummer = %s AND aktiv = 1", $nummer ), ARRAY_A );
		return null === $zeile ? null : $zeile;
	}

	/**
	 * Verein über den Blockaden-Token finden.
	 *
	 * @param string $token_hash SHA-256-Hash des Tokens.
	 * @return array<string, mixed>|null
	 */
	public function per_token_hash( string $token_hash ): ?array {
		global $wpdb;
		$tabelle = Schema::table( 'vereine' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Eigene Tabelle.
		$zeile = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$tabelle} WHERE token_hash = %s AND aktiv = 1", $token_hash ), ARRAY_A );
		return null === $zeile ? null : $zeile;
	}

	/**
	 * Alle Vereine, alphabetisch.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function alle(): array {
		global $wpdb;
		$tabelle = Schema::table( 'vereine' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL -- Eigene Tabelle, keine Parameter.
		return (array) $wpdb->get_results( "SELECT * FROM {$tabelle} ORDER BY name", ARRAY_A );
	}

	/**
	 * Blockaden-Token setzen oder widerrufen.
	 *
	 * @param int         $id   Vereins-ID.
	 * @param string|null $hash Token-Hash oder null zum Widerruf.
	 */
	public function token_setzen( int $id, ?string $hash ): void {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Eigene Tabelle.
		$wpdb->update(
			Schema::table( 'vereine' ),
			array(
				'token_hash'        => $hash,
				'token_erstellt_am' => null === $hash ? null : current_time( 'mysql' ),
				'updated_at'        => current_time( 'mysql' ),
			),
			array( 'id' => $id )
		);
	}
}
