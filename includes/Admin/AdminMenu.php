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
				add_menu_page(
					__( 'Seebuchung', 'seebuchung' ),
					__( 'Seebuchung', 'seebuchung' ),
					Rollen::CAP_EINSEHEN,
					'seebuchung',
					array( new BuchungenSeite(), 'render' ),
					'dashicons-palmtree',
					26
				);
				add_submenu_page(
					'seebuchung',
					__( 'Buchungen', 'seebuchung' ),
					__( 'Buchungen', 'seebuchung' ),
					Rollen::CAP_EINSEHEN,
					'seebuchung',
					array( new BuchungenSeite(), 'render' )
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
					__( 'Einstellungen', 'seebuchung' ),
					__( 'Einstellungen', 'seebuchung' ),
					Rollen::CAP_VERWALTEN,
					'seebuchung-einstellungen',
					array( new EinstellungenSeite(), 'render' )
				);
			}
		);

		BuchungenSeite::aktionen_registrieren();
		SeenSeite::aktionen_registrieren();
		BlockadenSeite::aktionen_registrieren();
		VereineSeite::aktionen_registrieren();
		KontrolleSeite::aktionen_registrieren();
		EinstellungenSeite::aktionen_registrieren();
	}
}
