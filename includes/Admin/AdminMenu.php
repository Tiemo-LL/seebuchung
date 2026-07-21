<?php
/**
 * Admin-Menü.
 *
 * @package Seebuchung
 */

namespace Seebuchung\Admin;

use Seebuchung\Rollen;

/**
 * Registriert das Seebuchung-Menü mit Buchungen, Seen und Einstellungen.
 */
final class AdminMenu {

	/**
	 * Hooks registrieren.
	 */
	public static function registrieren(): void {
		add_action(
			'admin_menu',
			static function () {
				// Reine Kontrolleur:innen sehen ein eigenes schlankes Menü —
				// das normale Hauptmenü verlangt die Einsehen-Berechtigung.
				if ( ! current_user_can( Rollen::CAP_EINSEHEN ) && current_user_can( Rollen::CAP_KONTROLLE ) ) {
					add_menu_page(
						__( 'Seebuchung', 'seebuchung' ),
						__( 'Seebuchung', 'seebuchung' ),
						Rollen::CAP_KONTROLLE,
						'seebuchung-kontrolle',
						array( new KontrolleSeite(), 'render' ),
						'dashicons-palmtree',
						26
					);
					add_submenu_page(
						'seebuchung-kontrolle',
						__( 'Hilfe', 'seebuchung' ),
						__( 'Hilfe', 'seebuchung' ),
						Rollen::CAP_KONTROLLE,
						'seebuchung-hilfe',
						array( new HilfeSeite(), 'render' )
					);
					return;
				}

				// Wichtig: dieselbe Callback-Instanz für Haupt- und ersten
				// Untermenüpunkt (gleicher Slug) — sonst rendert WP doppelt.
				$buchungen = array( new BuchungenSeite(), 'render' );

				add_menu_page(
					__( 'Seebuchung', 'seebuchung' ),
					__( 'Seebuchung', 'seebuchung' ),
					Rollen::CAP_EINSEHEN,
					'seebuchung',
					$buchungen,
					'dashicons-palmtree',
					26
				);
				add_submenu_page(
					'seebuchung',
					__( 'Buchungen', 'seebuchung' ),
					__( 'Buchungen', 'seebuchung' ),
					Rollen::CAP_EINSEHEN,
					'seebuchung',
					$buchungen
				);
				add_submenu_page(
					'seebuchung',
					__( 'Seen', 'seebuchung' ),
					__( 'Seen', 'seebuchung' ),
					Rollen::CAP_VERWALTEN,
					'seebuchung-seen',
					array( new SeenSeite(), 'render' )
				);
				add_submenu_page(
					'seebuchung',
					__( 'Blockaden', 'seebuchung' ),
					__( 'Blockaden', 'seebuchung' ),
					Rollen::CAP_VERWALTEN,
					'seebuchung-blockaden',
					array( new BlockadenSeite(), 'render' )
				);
				add_submenu_page(
					'seebuchung',
					__( 'Vereine', 'seebuchung' ),
					__( 'Vereine', 'seebuchung' ),
					Rollen::CAP_VERWALTEN,
					'seebuchung-vereine',
					array( new VereineSeite(), 'render' )
				);
				add_submenu_page(
					'seebuchung',
					__( 'Kontrolle', 'seebuchung' ),
					__( 'Kontrolle', 'seebuchung' ),
					Rollen::CAP_KONTROLLE,
					'seebuchung-kontrolle',
					array( new KontrolleSeite(), 'render' )
				);
				add_submenu_page(
					'seebuchung',
					__( 'Statistik', 'seebuchung' ),
					__( 'Statistik', 'seebuchung' ),
					Rollen::CAP_EINSEHEN,
					'seebuchung-statistik',
					array( new StatistikSeite(), 'render' )
				);
				add_submenu_page(
					'seebuchung',
					__( 'Saisonwechsel', 'seebuchung' ),
					__( 'Saisonwechsel', 'seebuchung' ),
					Rollen::CAP_VERWALTEN,
					'seebuchung-saisonwechsel',
					array( new SaisonwechselSeite(), 'render' )
				);
				add_submenu_page(
					'seebuchung',
					__( 'Einstellungen', 'seebuchung' ),
					__( 'Einstellungen', 'seebuchung' ),
					Rollen::CAP_VERWALTEN,
					'seebuchung-einstellungen',
					array( new EinstellungenSeite(), 'render' )
				);
				add_submenu_page(
					'seebuchung',
					__( 'Import', 'seebuchung' ),
					__( 'Import', 'seebuchung' ),
					Rollen::CAP_VERWALTEN,
					'seebuchung-import',
					array( new ImportSeite(), 'render' )
				);
				add_submenu_page(
					'seebuchung',
					__( 'Hilfe', 'seebuchung' ),
					__( 'Hilfe', 'seebuchung' ),
					Rollen::CAP_EINSEHEN,
					'seebuchung-hilfe',
					array( new HilfeSeite(), 'render' )
				);
			}
		);

		BuchungenSeite::aktionen_registrieren();
		SeenSeite::aktionen_registrieren();
		BlockadenSeite::aktionen_registrieren();
		VereineSeite::aktionen_registrieren();
		KontrolleSeite::aktionen_registrieren();
		SaisonwechselSeite::aktionen_registrieren();
		EinstellungenSeite::aktionen_registrieren();
		ImportSeite::aktionen_registrieren();
	}
}
