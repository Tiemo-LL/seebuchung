<?php
/**
 * Statemachine für Buchungen.
 *
 * @package Seebuchung
 */

namespace Seebuchung\Domain;

/**
 * Erlaubte Statusübergänge (siehe CLAUDE.md):
 *
 * Ablauf: angefragt → bestaetigt (Mail-Link) → gueltig (kostenlos sofort, sonst
 * nach PayPal-Capture) → kontrolliert. Storno aus jedem aktiven Status
 * (keine Erstattung), Verfall per Cron aus angefragt/bestaetigt.
 */
final class Buchungsstatemachine {

	/**
	 * Erlaubte Zielstati je Ausgangsstatus.
	 *
	 * @var array<string, string[]>
	 */
	private const UEBERGAENGE = array(
		Buchungsstatus::ANGEFRAGT    => array( Buchungsstatus::BESTAETIGT, Buchungsstatus::STORNIERT, Buchungsstatus::VERFALLEN ),
		Buchungsstatus::BESTAETIGT   => array( Buchungsstatus::GUELTIG, Buchungsstatus::STORNIERT, Buchungsstatus::VERFALLEN ),
		Buchungsstatus::GUELTIG      => array( Buchungsstatus::KONTROLLIERT, Buchungsstatus::STORNIERT ),
		Buchungsstatus::KONTROLLIERT => array(),
		Buchungsstatus::STORNIERT    => array(),
		Buchungsstatus::VERFALLEN    => array(),
	);

	/**
	 * Ist der Übergang erlaubt?
	 *
	 * @param string $von  Ausgangsstatus.
	 * @param string $nach Zielstatus.
	 */
	public static function erlaubt( string $von, string $nach ): bool {
		return in_array( $nach, self::UEBERGAENGE[ $von ] ?? array(), true );
	}

	/**
	 * Ist der Status ein Endzustand?
	 *
	 * @param string $status Status.
	 */
	public static function ist_endzustand( string $status ): bool {
		return array() === ( self::UEBERGAENGE[ $status ] ?? array() );
	}
}
