<?php
/**
 * Status-Konstanten der Buchungs-Statemachine.
 *
 * @package Seebuchung
 */

namespace Seebuchung\Domain;

/**
 * Stati lt. CLAUDE.md:
 *
 * Ablauf: angefragt → (Mail-Link) → bestaetigt → ggf. (PayPal Capture) → gueltig
 * → kontrolliert | storniert (jederzeit via Link) | verfallen (Frist, Cron).
 *
 * Kostenlose Buchungen springen von bestaetigt direkt auf gueltig.
 * Die Übergangslogik selbst folgt im Statemachine-Task (Phase 1).
 */
final class Buchungsstatus {

	public const ANGEFRAGT    = 'angefragt';
	public const BESTAETIGT   = 'bestaetigt';
	public const GUELTIG      = 'gueltig';
	public const KONTROLLIERT = 'kontrolliert';
	public const STORNIERT    = 'storniert';
	public const VERFALLEN    = 'verfallen';

	/**
	 * Alle gültigen Stati.
	 *
	 * @var string[]
	 */
	public const ALLE = array(
		self::ANGEFRAGT,
		self::BESTAETIGT,
		self::GUELTIG,
		self::KONTROLLIERT,
		self::STORNIERT,
		self::VERFALLEN,
	);

	/**
	 * Stati, deren Buchungen Kontingent belegen.
	 *
	 * @var string[]
	 */
	public const BELEGT_KONTINGENT = array(
		self::ANGEFRAGT,
		self::BESTAETIGT,
		self::GUELTIG,
		self::KONTROLLIERT,
	);
}
