<?php
/**
 * Wochenbericht an Seeverantwortliche.
 *
 * @package Seebuchung
 */

namespace Seebuchung\Service;

use Seebuchung\Database\BuchungsRepository;
use Seebuchung\Database\SeenRepository;
use Seebuchung\Database\VereinsRepository;
use Seebuchung\Domain\Buchungsstatus;
use Seebuchung\Rollen;
use Seebuchung\Settings;

/**
 * Verschickt am konfigurierten Wochentag je See die Buchungsliste der
 * kommenden 7 Tage an die zugeordneten Seeverantwortlichen (User-Meta
 * seebuchung_see_ids, Pflege im Benutzerprofil).
 *
 * Hinweis: Versand als Tabellen-Mail; PDF-Anhang ist für die Phase-4-Politur
 * vorgemerkt (TASKS.md).
 */
final class Wochenbericht {

	/**
	 * User-Meta-Key für die See-Zuordnung.
	 */
	public const META_SEEN = 'seebuchung_see_ids';

	/**
	 * Täglicher Cron-Einstieg: nur am konfigurierten Wochentag senden.
	 */
	public function vielleicht_senden(): void {
		$wochentag = (int) Settings::get( 'wochenbericht_wochentag' );
		if ( (int) current_time( 'N' ) !== $wochentag ) {
			return;
		}
		$this->senden();
	}

	/**
	 * Berichte für alle Seen mit Verantwortlichen verschicken.
	 *
	 * @return int Anzahl verschickter Mails.
	 */
	public function senden(): int {
		$heute     = current_time( 'Y-m-d' );
		$buchungen = new BuchungsRepository();
		$anzahl    = 0;

		$vereins_namen = array();
		foreach ( ( new VereinsRepository() )->alle() as $verein ) {
			$vereins_namen[ (int) $verein['id'] ] = (string) $verein['name'];
		}

		foreach ( ( new SeenRepository() )->aktive() as $see ) {
			$empfaenger = $this->verantwortliche_fuer( $see->id );
			if ( array() === $empfaenger ) {
				continue;
			}

			$zeilen = $this->buchungen_der_woche( $buchungen, $see->id, $heute );
			$html   = $this->html_bericht( $see->name, $heute, $zeilen, $vereins_namen );

			foreach ( $empfaenger as $email ) {
				$gesendet = wp_mail(
					$email,
					sprintf(
						/* translators: %s: See-Name */
						__( 'Wochenbericht %s', 'seebuchung' ),
						$see->name
					),
					$html,
					array( 'Content-Type: text/html; charset=UTF-8' )
				);
				if ( $gesendet ) {
					++$anzahl;
				}
			}
		}

		return $anzahl;
	}

	/**
	 * E-Mail-Adressen der einem See zugeordneten Verantwortlichen.
	 *
	 * @param int $see_id See-ID.
	 * @return string[]
	 */
	private function verantwortliche_fuer( int $see_id ): array {
		$nutzer = get_users(
			array(
				'capability' => Rollen::CAP_EINSEHEN,
				'fields'     => 'all',
			)
		);

		$emails = array();
		foreach ( $nutzer as $user ) {
			$see_ids = (array) get_user_meta( $user->ID, self::META_SEEN, true );
			if ( in_array( $see_id, array_map( 'intval', $see_ids ), true ) ) {
				$emails[] = (string) $user->user_email;
			}
		}
		return $emails;
	}

	/**
	 * Aktive Buchungen der kommenden 7 Tage.
	 *
	 * @param BuchungsRepository $repo   Repository.
	 * @param int                $see_id See-ID.
	 * @param string             $heute  Startdatum (Y-m-d).
	 * @return array<int, array<string, mixed>>
	 */
	private function buchungen_der_woche( BuchungsRepository $repo, int $see_id, string $heute ): array {
		$zeilen = array();
		for ( $tag = 0; $tag < 7; $tag++ ) {
			$datum = ( new \DateTimeImmutable( $heute ) )->modify( "+{$tag} days" )->format( 'Y-m-d' );
			foreach ( $repo->suchen( $see_id, $datum, '' ) as $buchung ) {
				if ( in_array( $buchung['status'], Buchungsstatus::BELEGT_KONTINGENT, true ) ) {
					$zeilen[] = $buchung;
				}
			}
		}
		return $zeilen;
	}

	/**
	 * HTML-Tabelle des Berichts.
	 *
	 * @param string                           $see_name      See-Name.
	 * @param string                           $heute         Startdatum.
	 * @param array<int, array<string, mixed>> $zeilen        Buchungen.
	 * @param array<int, string>               $vereins_namen Vereins-Lookup.
	 */
	private function html_bericht( string $see_name, string $heute, array $zeilen, array $vereins_namen ): string {
		$titel = sprintf(
			/* translators: 1: See, 2: Datum */
			__( 'Buchungen %1$s ab %2$s (7 Tage)', 'seebuchung' ),
			$see_name,
			$heute
		);

		$html = '<h2>' . esc_html( $titel ) . '</h2>';
		if ( array() === $zeilen ) {
			return $html . '<p>' . esc_html__( 'Keine Buchungen in den kommenden 7 Tagen.', 'seebuchung' ) . '</p>';
		}

		$html .= '<table border="1" cellpadding="6" cellspacing="0"><tr>'
			. '<th>' . esc_html__( 'Datum', 'seebuchung' ) . '</th>'
			. '<th>' . esc_html__( 'Zeit', 'seebuchung' ) . '</th>'
			. '<th>' . esc_html__( 'Gruppenleitung', 'seebuchung' ) . '</th>'
			. '<th>' . esc_html__( 'Verein', 'seebuchung' ) . '</th>'
			. '<th>' . esc_html__( 'Anzahl', 'seebuchung' ) . '</th>'
			. '<th>' . esc_html__( 'Status', 'seebuchung' ) . '</th></tr>';

		foreach ( $zeilen as $buchung ) {
			$html .= '<tr>'
				. '<td>' . esc_html( (string) $buchung['datum'] ) . '</td>'
				. '<td>' . esc_html( null === $buchung['stunde'] ? __( 'Tag', 'seebuchung' ) : sprintf( '%02d:00', (int) $buchung['stunde'] ) ) . '</td>'
				. '<td>' . esc_html( $buchung['vorname'] . ' ' . $buchung['name'] ) . '</td>'
				. '<td>' . esc_html( null === $buchung['verein_id'] ? __( 'Gast', 'seebuchung' ) : ( $vereins_namen[ (int) $buchung['verein_id'] ] ?? '—' ) ) . '</td>'
				. '<td>' . esc_html( (string) (int) $buchung['anzahl_taucher'] ) . '</td>'
				. '<td>' . esc_html( (string) $buchung['status'] ) . '</td>'
				. '</tr>';
		}
		return $html . '</table>';
	}

	/**
	 * Profilfelder für die See-Zuordnung registrieren.
	 */
	public static function profilfelder_registrieren(): void {
		$anzeigen = static function ( \WP_User $user ): void {
			if ( ! current_user_can( Rollen::CAP_VERWALTEN ) ) {
				return;
			}
			$zugeordnet = array_map( 'intval', (array) get_user_meta( $user->ID, self::META_SEEN, true ) );
			wp_nonce_field( 'seebuchung_seen_zuordnung', 'seebuchung_seen_nonce' );
			echo '<h2>' . esc_html__( 'Seebuchung: Seeverantwortung', 'seebuchung' ) . '</h2><table class="form-table"><tr><th>'
				. esc_html__( 'Zugeordnete Seen (Wochenbericht)', 'seebuchung' ) . '</th><td>';
			foreach ( ( new SeenRepository() )->alle() as $see ) {
				printf(
					'<label style="display:block"><input type="checkbox" name="seebuchung_see_ids[]" value="%d" %s> %s</label>',
					(int) $see->id,
					checked( in_array( $see->id, $zugeordnet, true ), true, false ),
					esc_html( $see->name )
				);
			}
			echo '</td></tr></table>';
		};

		$speichern = static function ( int $user_id ): void {
			if ( ! current_user_can( Rollen::CAP_VERWALTEN )
				|| ! isset( $_POST['seebuchung_seen_nonce'] )
				|| ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['seebuchung_seen_nonce'] ) ), 'seebuchung_seen_zuordnung' ) ) {
				return;
			}
			$ids = isset( $_POST['seebuchung_see_ids'] ) ? array_map( 'intval', (array) wp_unslash( $_POST['seebuchung_see_ids'] ) ) : array();
			update_user_meta( $user_id, self::META_SEEN, $ids );
		};

		add_action( 'show_user_profile', $anzeigen );
		add_action( 'edit_user_profile', $anzeigen );
		add_action( 'personal_options_update', $speichern );
		add_action( 'edit_user_profile_update', $speichern );
	}
}
