<?php
/**
 * Rollen und Capabilities.
 *
 * @package Seebuchung
 */

namespace Seebuchung;

/**
 * Registriert die drei Plugin-Rollen und vergibt die Capabilities.
 *
 * Capabilities:
 * - seebuchung_verwalten: Seen/Kontingente/Settings pflegen, Blockaden
 *   genehmigen, Stammdaten — der volle Admin-Bereich.
 * - seebuchung_buchungen_einsehen: Buchungsübersicht und Wochenberichte
 *   (Seeverantwortliche; Zuordnung zu konkreten Seen folgt mit dem
 *   Wochenbericht-Task als User-Meta).
 * - seebuchung_kontrollieren: mobile Kontroll-Ansicht, QR-Prüfung,
 *   "kontrolliert"-Haken — sonst nichts.
 *
 * WordPress-Administratoren erhalten alle drei Capabilities zusätzlich.
 */
final class Rollen {

	public const ADMIN               = 'seebuchung_admin';
	public const SEEVERANTWORTLICHER = 'seebuchung_seeverantwortlicher';
	public const KONTROLLEUR         = 'seebuchung_kontrolleur';

	public const CAP_VERWALTEN = 'seebuchung_verwalten';
	public const CAP_EINSEHEN  = 'seebuchung_buchungen_einsehen';
	public const CAP_KONTROLLE = 'seebuchung_kontrollieren';

	/**
	 * Alle Plugin-Capabilities.
	 *
	 * @var string[]
	 */
	public const ALLE_CAPS = array( self::CAP_VERWALTEN, self::CAP_EINSEHEN, self::CAP_KONTROLLE );

	/**
	 * Rollen anlegen und WP-Admins die Caps geben (Aktivierung).
	 */
	public static function registrieren(): void {
		add_role(
			self::ADMIN,
			__( 'Seebuchung-Admin', 'seebuchung' ),
			array(
				'read'              => true,
				self::CAP_VERWALTEN => true,
				self::CAP_EINSEHEN  => true,
				self::CAP_KONTROLLE => true,
			)
		);
		add_role(
			self::SEEVERANTWORTLICHER,
			__( 'Seeverantwortliche:r', 'seebuchung' ),
			array(
				'read'             => true,
				self::CAP_EINSEHEN => true,
			)
		);
		add_role(
			self::KONTROLLEUR,
			__( 'Seebuchung-Kontrolleur:in', 'seebuchung' ),
			array(
				'read'              => true,
				self::CAP_KONTROLLE => true,
			)
		);

		$administrator = get_role( 'administrator' );
		if ( null !== $administrator ) {
			foreach ( self::ALLE_CAPS as $cap ) {
				$administrator->add_cap( $cap );
			}
		}
	}

	/**
	 * Rollen und Caps entfernen (Deinstallation).
	 */
	public static function entfernen(): void {
		remove_role( self::ADMIN );
		remove_role( self::SEEVERANTWORTLICHER );
		remove_role( self::KONTROLLEUR );

		$administrator = get_role( 'administrator' );
		if ( null !== $administrator ) {
			foreach ( self::ALLE_CAPS as $cap ) {
				$administrator->remove_cap( $cap );
			}
		}
	}
}
