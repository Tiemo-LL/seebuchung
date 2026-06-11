<?php
/**
 * Deinstallation: Rollen und Optionen entfernen.
 *
 * Die Datentabellen (seebuchung_*) bleiben bewusst erhalten — Buchungs- und
 * Statistikdaten sollen eine versehentliche Deinstallation überleben. Eine
 * Option "Daten bei Deinstallation löschen" folgt mit der F7-Härtung.
 *
 * @package Seebuchung
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once __DIR__ . '/vendor/autoload.php';

\Seebuchung\Rollen::entfernen();

delete_option( 'seebuchung_settings' );
delete_option( 'seebuchung_db_version' );
