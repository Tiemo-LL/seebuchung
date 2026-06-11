<?php
/**
 * Buchungs-Workflow.
 *
 * @package Seebuchung
 */

namespace Seebuchung\Service;

use Seebuchung\Database\BuchungsRepository;
use Seebuchung\Database\VereinsRepository;
use Seebuchung\Database\VerfuegbarkeitsRepository;
use Seebuchung\Domain\Buchungsstatemachine;
use Seebuchung\Domain\Buchungsstatus;
use Seebuchung\Domain\Buchungsvalidierung;
use Seebuchung\Domain\Seemodus;
use Seebuchung\Domain\Token;
use Seebuchung\Settings;
use WP_Error;

/**
 * Orchestriert Anlegen, Doppel-Opt-in, Storno und Verfall von Buchungen.
 *
 * Phase 2 hängt hier PayPal ein: zahlungspflichtige Buchungen bleiben nach
 * der Bestätigung auf "bestaetigt", bis der Capture-Webhook sie auf
 * "gueltig" hebt.
 */
final class Buchungsservice {

	/**
	 * Konstruktor (Abhängigkeiten injizierbar für Tests).
	 *
	 * @param BuchungsRepository|null        $buchungen   Buchungs-Repository.
	 * @param VereinsRepository|null         $vereine     Vereins-Repository.
	 * @param VerfuegbarkeitsRepository|null $verfuegbar  Verfügbarkeits-Repository.
	 * @param Mailer|null                    $mailer      Mailer.
	 */
	public function __construct(
		private ?BuchungsRepository $buchungen = null,
		private ?VereinsRepository $vereine = null,
		private ?VerfuegbarkeitsRepository $verfuegbar = null,
		private ?Mailer $mailer = null
	) {
		$this->buchungen  = $buchungen ?? new BuchungsRepository();
		$this->vereine    = $vereine ?? new VereinsRepository();
		$this->verfuegbar = $verfuegbar ?? new VerfuegbarkeitsRepository();
		$this->mailer     = $mailer ?? new Mailer();
	}

	/**
	 * Buchung anlegen (Status angefragt) und Doppel-Opt-in-Mail senden.
	 *
	 * @param array<string, mixed> $eingabe Bereinigte Formulareingaben:
	 *        see_id, datum, stunde (int|null), name, vorname, email, telefon,
	 *        vereins_nummer (string, leer = kein Verein), brevet_id (int|null),
	 *        anzahl_taucher, anzahl_zahler.
	 * @return array<string, mixed>|WP_Error Buchungszeile oder Fehler (Code-Liste in data).
	 */
	public function anlegen( array $eingabe ) {
		$see_id = (int) $eingabe['see_id'];
		$see    = $this->verfuegbar->see_laden( $see_id );
		$engine = $this->verfuegbar->engine_fuer_see( $see_id );
		if ( null === $see || null === $engine ) {
			return new WP_Error( 'seebuchung_see', __( 'Dieser See existiert nicht.', 'seebuchung' ) );
		}

		if ( ! is_email( (string) $eingabe['email'] ) ) {
			return new WP_Error( 'seebuchung_email', __( 'Bitte gib eine gültige E-Mail-Adresse an.', 'seebuchung' ) );
		}

		$vereins_nummer   = trim( (string) ( $eingabe['vereins_nummer'] ?? '' ) );
		$verein           = '' === $vereins_nummer ? null : $this->vereine->per_nummer( $vereins_nummer );
		$verein_unbekannt = '' !== $vereins_nummer && null === $verein;

		$datum  = (string) $eingabe['datum'];
		$stunde = Seemodus::TAG === $see->modus ? null : ( isset( $eingabe['stunde'] ) ? (int) $eingabe['stunde'] : null );

		$validierung = new Buchungsvalidierung( $see, $engine );
		$fehler      = $validierung->pruefen(
			$datum,
			$stunde,
			(int) $eingabe['anzahl_taucher'],
			(int) $eingabe['anzahl_zahler'],
			null !== $verein,
			$verein_unbekannt,
			$this->buchungen->aktive_buchung_existiert( $see_id, $datum, (string) $eingabe['email'] )
		);
		if ( array() !== $fehler ) {
			return new WP_Error( 'seebuchung_validierung', __( 'Die Buchung ist so nicht möglich.', 'seebuchung' ), $fehler );
		}

		// Zahler zahlen immer — das See-Flag "kostenpflichtig" erzwingt nur, dass alle zahlen.
		$preis_gesamt = round( (int) $eingabe['anzahl_zahler'] * $see->preis_pro_person, 2 );

		$token = Token::generieren();

		$buchung = array(
			'see_id'         => $see_id,
			'datum'          => $datum,
			'stunde'         => $stunde,
			'status'         => Buchungsstatus::ANGEFRAGT,
			'name'           => sanitize_text_field( (string) $eingabe['name'] ),
			'vorname'        => sanitize_text_field( (string) $eingabe['vorname'] ),
			'email'          => sanitize_email( (string) $eingabe['email'] ),
			'telefon'        => sanitize_text_field( (string) ( $eingabe['telefon'] ?? '' ) ),
			'verein_id'      => null === $verein ? null : (int) $verein['id'],
			'brevet_id'      => isset( $eingabe['brevet_id'] ) && (int) $eingabe['brevet_id'] > 0 ? (int) $eingabe['brevet_id'] : null,
			'anzahl_taucher' => (int) $eingabe['anzahl_taucher'],
			'anzahl_zahler'  => (int) $eingabe['anzahl_zahler'],
			'preis_gesamt'   => $preis_gesamt > 0 ? $preis_gesamt : null,
			'token_hash'     => Token::hash( $token ),
		);

		$buchung['id'] = $this->buchungen->anlegen( $buchung );

		$this->mailer->doppel_opt_in( $buchung, self::buchungslink( $token ) );

		return $buchung;
	}

	/**
	 * Buchung über den Mail-Link bestätigen (Doppel-Opt-in).
	 *
	 * Kostenlose Buchungen werden sofort gültig; zahlungspflichtige bleiben
	 * bestätigt, bis die Zahlung eingeht (Phase 2).
	 *
	 * @param string $token Klartext-Token aus dem Link.
	 * @return array<string, mixed>|WP_Error Aktualisierte Buchung oder Fehler.
	 */
	public function bestaetigen( string $token ) {
		$buchung = $this->per_token( $token );
		if ( $buchung instanceof WP_Error ) {
			return $buchung;
		}

		if ( ! Buchungsstatemachine::erlaubt( (string) $buchung['status'], Buchungsstatus::BESTAETIGT ) ) {
			return new WP_Error( 'seebuchung_status', __( 'Diese Buchung kann nicht mehr bestätigt werden.', 'seebuchung' ) );
		}

		$jetzt = current_time( 'mysql' );
		$this->buchungen->status_setzen( (int) $buchung['id'], Buchungsstatus::BESTAETIGT, array( 'bestaetigt_am' => $jetzt ) );
		$buchung['status']        = Buchungsstatus::BESTAETIGT;
		$buchung['bestaetigt_am'] = $jetzt;

		$zahlungspflichtig = (float) ( $buchung['preis_gesamt'] ?? 0 ) > 0;
		if ( ! $zahlungspflichtig ) {
			$this->buchungen->status_setzen( (int) $buchung['id'], Buchungsstatus::GUELTIG );
			$buchung['status'] = Buchungsstatus::GUELTIG;
			$this->mailer->gueltig( $buchung, self::buchungslink( $token ) );
		} else {
			$this->mailer->zahlung_offen( $buchung, self::buchungslink( $token ) );
		}

		return $buchung;
	}

	/**
	 * Buchung über den Mail-Link stornieren (keine Erstattung).
	 *
	 * @param string $token Klartext-Token aus dem Link.
	 * @return array<string, mixed>|WP_Error Aktualisierte Buchung oder Fehler.
	 */
	public function stornieren( string $token ) {
		$buchung = $this->per_token( $token );
		if ( $buchung instanceof WP_Error ) {
			return $buchung;
		}

		if ( ! Buchungsstatemachine::erlaubt( (string) $buchung['status'], Buchungsstatus::STORNIERT ) ) {
			return new WP_Error( 'seebuchung_status', __( 'Diese Buchung kann nicht mehr storniert werden.', 'seebuchung' ) );
		}

		$jetzt = current_time( 'mysql' );
		$this->buchungen->status_setzen( (int) $buchung['id'], Buchungsstatus::STORNIERT, array( 'storniert_am' => $jetzt ) );
		$buchung['status'] = Buchungsstatus::STORNIERT;

		$this->mailer->storniert( $buchung );

		return $buchung;
	}

	/**
	 * Buchung nach Zahlungseingang gültig setzen (PayPal-Capture/Webhook).
	 *
	 * Idempotent: ist die Buchung schon gültig, passiert nichts.
	 *
	 * @param int    $id          Buchungs-ID.
	 * @param string $transaktion PayPal-Capture-ID.
	 * @return array<string, mixed>|WP_Error Aktualisierte Buchung oder Fehler.
	 */
	public function bezahlt_markieren( int $id, string $transaktion ) {
		$buchung = $this->buchungen->per_id( $id );
		if ( null === $buchung ) {
			return new WP_Error( 'seebuchung_id', __( 'Buchung nicht gefunden.', 'seebuchung' ) );
		}

		if ( Buchungsstatus::GUELTIG === $buchung['status'] || Buchungsstatus::KONTROLLIERT === $buchung['status'] ) {
			return $buchung; // Bereits verarbeitet (z. B. Webhook nach Return-Flow).
		}
		if ( ! Buchungsstatemachine::erlaubt( (string) $buchung['status'], Buchungsstatus::GUELTIG ) ) {
			return new WP_Error( 'seebuchung_status', __( 'Diese Buchung kann nicht mehr gültig gesetzt werden.', 'seebuchung' ) );
		}

		$this->buchungen->status_setzen( $id, Buchungsstatus::GUELTIG, array( 'paypal_transaktion' => $transaktion ) );
		$buchung['status']             = Buchungsstatus::GUELTIG;
		$buchung['paypal_transaktion'] = $transaktion;

		$this->mailer->gueltig( $buchung, home_url( '/' ) );

		return $buchung;
	}

	/**
	 * Buchung als kontrolliert markieren (Kontrolleurs-Ansicht).
	 *
	 * @param int $id Buchungs-ID.
	 * @return array<string, mixed>|WP_Error Aktualisierte Buchung oder Fehler.
	 */
	public function kontrollieren( int $id ) {
		$buchung = $this->buchungen->per_id( $id );
		if ( null === $buchung ) {
			return new WP_Error( 'seebuchung_id', __( 'Buchung nicht gefunden.', 'seebuchung' ) );
		}

		if ( ! Buchungsstatemachine::erlaubt( (string) $buchung['status'], Buchungsstatus::KONTROLLIERT ) ) {
			return new WP_Error( 'seebuchung_status', __( 'Nur gültige Buchungen können kontrolliert werden.', 'seebuchung' ) );
		}

		$this->buchungen->status_setzen( $id, Buchungsstatus::KONTROLLIERT, array( 'kontrolliert_am' => current_time( 'mysql' ) ) );
		$buchung['status'] = Buchungsstatus::KONTROLLIERT;

		return $buchung;
	}

	/**
	 * Buchung durch den Admin stornieren (Buchungsübersicht).
	 *
	 * @param int $id Buchungs-ID.
	 * @return array<string, mixed>|WP_Error Aktualisierte Buchung oder Fehler.
	 */
	public function stornieren_admin( int $id ) {
		$buchung = $this->buchungen->per_id( $id );
		if ( null === $buchung ) {
			return new WP_Error( 'seebuchung_id', __( 'Buchung nicht gefunden.', 'seebuchung' ) );
		}

		if ( ! Buchungsstatemachine::erlaubt( (string) $buchung['status'], Buchungsstatus::STORNIERT ) ) {
			return new WP_Error( 'seebuchung_status', __( 'Diese Buchung kann nicht mehr storniert werden.', 'seebuchung' ) );
		}

		$this->buchungen->status_setzen( $id, Buchungsstatus::STORNIERT, array( 'storniert_am' => current_time( 'mysql' ) ) );
		$buchung['status'] = Buchungsstatus::STORNIERT;

		$this->mailer->storniert( $buchung );

		return $buchung;
	}

	/**
	 * Buchung zum Token laden (für die Link-Seite).
	 *
	 * @param string $token Klartext-Token.
	 * @return array<string, mixed>|WP_Error
	 */
	public function per_token( string $token ) {
		if ( 64 !== strlen( $token ) || ! ctype_xdigit( $token ) ) {
			return new WP_Error( 'seebuchung_token', __( 'Ungültiger Buchungslink.', 'seebuchung' ) );
		}
		$buchung = $this->buchungen->per_token_hash( Token::hash( $token ) );
		if ( null === $buchung ) {
			return new WP_Error( 'seebuchung_token', __( 'Ungültiger Buchungslink.', 'seebuchung' ) );
		}
		return $buchung;
	}

	/**
	 * Unbestätigte Anfragen verfallen lassen (Cron).
	 *
	 * @return int Anzahl verfallener Buchungen.
	 */
	public function verfall_bereinigen(): int {
		$frist_stunden = (int) Settings::get( 'bestaetigungsfrist_stunden' );
		$stichtag      = gmdate( 'Y-m-d H:i:s', strtotime( current_time( 'mysql' ) ) - $frist_stunden * HOUR_IN_SECONDS );
		return $this->buchungen->verfall_markieren( $stichtag );
	}

	/**
	 * Öffentlicher Buchungslink für einen Token.
	 *
	 * @param string $token Klartext-Token.
	 */
	public static function buchungslink( string $token ): string {
		$seiten_id = (int) Settings::get( 'buchungsseite_id' );
		$basis     = $seiten_id > 0 ? (string) get_permalink( $seiten_id ) : home_url( '/' );
		return add_query_arg( 'sb_token', rawurlencode( $token ), $basis );
	}
}
