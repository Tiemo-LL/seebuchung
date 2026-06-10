<?php
/**
 * Datenbankschema und Migrationen.
 *
 * @package Seebuchung
 */

namespace Seebuchung\Database;

/**
 * Definiert alle Plugin-Tabellen und führt Migrationen über dbDelta aus.
 *
 * Hinweis: dbDelta unterstützt keine FOREIGN-KEY-Constraints — referenzielle Integrität
 * wird in der Domänenschicht sichergestellt, alle *_id-Spalten sind indiziert.
 *
 * Konventionen:
 * - Stunden als Zeilen (nicht als Spalten wie im Altsystem), Wertebereich 0–23.
 * - `stunde IS NULL` bedeutet "ganzer Tag" (Tagesseen) — Eindeutigkeit solcher
 *   Zeilen prüft die Schreiblogik, da UNIQUE-Indizes mehrere NULLs zulassen.
 * - Tokens (Buchungs-/Vereins-Token) werden ausschließlich als SHA-256-Hash
 *   gespeichert.
 */
final class Schema {

	/**
	 * Bei jeder Schemaänderung erhöhen — löst beim Plugin-Update die Migration aus.
	 */
	public const DB_VERSION = '1';

	/**
	 * Option mit der installierten Schemaversion.
	 */
	private const OPTION_DB_VERSION = 'seebuchung_db_version';

	/**
	 * Vollqualifizierter Tabellenname.
	 *
	 * @param string $name Kurzname, z. B. 'seen'.
	 */
	public static function table( string $name ): string {
		global $wpdb;
		return $wpdb->prefix . 'seebuchung_' . $name;
	}

	/**
	 * Legt alle Tabellen an bzw. migriert sie auf den aktuellen Stand.
	 */
	public static function install(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		foreach ( self::create_statements( $wpdb->prefix, $wpdb->get_charset_collate() ) as $sql ) {
			dbDelta( $sql );
		}

		update_option( self::OPTION_DB_VERSION, self::DB_VERSION );
	}

	/**
	 * Migration nach Plugin-Update ohne erneute Aktivierung.
	 */
	public static function maybe_upgrade(): void {
		if ( self::DB_VERSION !== get_option( self::OPTION_DB_VERSION ) ) {
			self::install();
		}
	}

	/**
	 * CREATE-TABLE-Statements im dbDelta-Format.
	 *
	 * Pure Funktion ohne WP-Zugriff — dadurch per Unit-Test prüfbar.
	 *
	 * @param string $prefix          WP-Tabellen-Prefix (z. B. 'wp_').
	 * @param string $charset_collate Charset-/Collation-Klausel von $wpdb.
	 * @return array<string, string> Kurzname => CREATE-TABLE-Statement.
	 */
	public static function create_statements( string $prefix, string $charset_collate ): array {
		$p = $prefix . 'seebuchung_';

		$seen = "CREATE TABLE {$p}seen (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(190) NOT NULL,
			slug varchar(190) NOT NULL,
			aktiv tinyint(1) NOT NULL DEFAULT 0,
			modus varchar(10) NOT NULL DEFAULT 'tag',
			saison_von date DEFAULT NULL,
			saison_bis date DEFAULT NULL,
			buchungsfenster_wochen smallint(5) unsigned NOT NULL DEFAULT 4,
			stunde_von_woche tinyint(3) unsigned DEFAULT NULL,
			stunde_bis_woche tinyint(3) unsigned DEFAULT NULL,
			stunde_von_wochenende tinyint(3) unsigned DEFAULT NULL,
			stunde_bis_wochenende tinyint(3) unsigned DEFAULT NULL,
			min_anmelder smallint(5) unsigned NOT NULL DEFAULT 1,
			max_pro_buchung smallint(5) unsigned NOT NULL DEFAULT 10,
			kostenpflichtig tinyint(1) NOT NULL DEFAULT 0,
			preis_pro_person decimal(8,2) NOT NULL DEFAULT 0.00,
			info_text text,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY slug (slug),
			KEY aktiv (aktiv)
		) {$charset_collate};";

		$kontingente = "CREATE TABLE {$p}kontingente (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			see_id bigint(20) unsigned NOT NULL,
			wochentag tinyint(3) unsigned NOT NULL,
			stunde tinyint(3) unsigned DEFAULT NULL,
			max_taucher smallint(5) unsigned NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			UNIQUE KEY see_tag_stunde (see_id,wochentag,stunde),
			KEY see_id (see_id)
		) {$charset_collate};";

		$buchungen = "CREATE TABLE {$p}buchungen (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			see_id bigint(20) unsigned NOT NULL,
			datum date NOT NULL,
			stunde tinyint(3) unsigned DEFAULT NULL,
			status varchar(20) NOT NULL DEFAULT 'angefragt',
			name varchar(100) NOT NULL,
			vorname varchar(100) NOT NULL,
			email varchar(190) NOT NULL,
			telefon varchar(50) NOT NULL DEFAULT '',
			verein_id bigint(20) unsigned DEFAULT NULL,
			brevet_id bigint(20) unsigned DEFAULT NULL,
			anzahl_taucher smallint(5) unsigned NOT NULL DEFAULT 1,
			anzahl_zahler smallint(5) unsigned NOT NULL DEFAULT 0,
			preis_gesamt decimal(8,2) DEFAULT NULL,
			paypal_transaktion varchar(64) DEFAULT NULL,
			token_hash char(64) NOT NULL,
			blockade_id bigint(20) unsigned DEFAULT NULL,
			bestaetigt_am datetime DEFAULT NULL,
			storniert_am datetime DEFAULT NULL,
			kontrolliert_am datetime DEFAULT NULL,
			anonymisiert_am datetime DEFAULT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY token_hash (token_hash),
			KEY see_datum (see_id,datum),
			KEY status (status),
			KEY datum (datum)
		) {$charset_collate};";

		$blockaden = "CREATE TABLE {$p}blockaden (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			see_id bigint(20) unsigned NOT NULL,
			datum date NOT NULL,
			ganzer_tag tinyint(1) NOT NULL DEFAULT 0,
			stunde_von tinyint(3) unsigned DEFAULT NULL,
			stunde_bis tinyint(3) unsigned DEFAULT NULL,
			anzahl_taucher smallint(5) unsigned DEFAULT NULL,
			verein_id bigint(20) unsigned DEFAULT NULL,
			veranstaltung varchar(190) NOT NULL DEFAULT '',
			verantwortlicher varchar(190) NOT NULL DEFAULT '',
			email varchar(190) NOT NULL DEFAULT '',
			telefon varchar(50) NOT NULL DEFAULT '',
			status varchar(20) NOT NULL DEFAULT 'beantragt',
			info text,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY see_datum (see_id,datum),
			KEY verein_id (verein_id),
			KEY status (status)
		) {$charset_collate};";

		$vereine = "CREATE TABLE {$p}vereine (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			nummer varchar(20) NOT NULL,
			name varchar(190) NOT NULL,
			verband varchar(190) NOT NULL DEFAULT '',
			aktiv tinyint(1) NOT NULL DEFAULT 1,
			token_hash char(64) DEFAULT NULL,
			token_erstellt_am datetime DEFAULT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY nummer (nummer),
			KEY aktiv (aktiv)
		) {$charset_collate};";

		$brevets = "CREATE TABLE {$p}brevets (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			bezeichnung varchar(190) NOT NULL,
			sortierung smallint(5) unsigned NOT NULL DEFAULT 0,
			aktiv tinyint(1) NOT NULL DEFAULT 1,
			PRIMARY KEY  (id),
			KEY aktiv_sortierung (aktiv,sortierung)
		) {$charset_collate};";

		$nachttermine = "CREATE TABLE {$p}nachttermine (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			see_id bigint(20) unsigned NOT NULL,
			datum date NOT NULL,
			stunde_von tinyint(3) unsigned NOT NULL DEFAULT 21,
			stunde_bis tinyint(3) unsigned NOT NULL DEFAULT 24,
			info text,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY see_datum (see_id,datum)
		) {$charset_collate};";

		return array(
			'seen'         => $seen,
			'kontingente'  => $kontingente,
			'buchungen'    => $buchungen,
			'blockaden'    => $blockaden,
			'vereine'      => $vereine,
			'brevets'      => $brevets,
			'nachttermine' => $nachttermine,
		);
	}
}
