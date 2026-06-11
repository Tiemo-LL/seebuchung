<?php
/**
 * Blockaden-Workflow (Vereins-Self-Service, F2).
 *
 * @package Seebuchung
 */

namespace Seebuchung\Service;

use Seebuchung\Database\BlockadenRepository;
use Seebuchung\Database\VereinsRepository;
use Seebuchung\Database\VerfuegbarkeitsRepository;
use Seebuchung\Domain\Blockadenvalidierung;
use Seebuchung\Domain\Seemodus;
use Seebuchung\Domain\Token;
use Seebuchung\Settings;
use WP_Error;

/**
 * Antrag über Vereins-Token-Link, Genehmigung/Ablehnung durch den Admin.
 */
final class Blockadenservice {

	/**
	 * Verein zum Token-Link auflösen.
	 *
	 * @param string $token Klartext-Token aus dem Link.
	 * @return array<string, mixed>|null
	 */
	public function verein_zum_token( string $token ): ?array {
		if ( 64 !== strlen( $token ) || ! ctype_xdigit( $token ) ) {
			return null;
		}
		return ( new VereinsRepository() )->per_token_hash( Token::hash( $token ) );
	}

	/**
	 * Ist das Antragsfenster gerade offen?
	 */
	public function fenster_offen(): bool {
		return Blockadenvalidierung::im_antragsfenster(
			current_time( 'm-d' ),
			(string) Settings::get( 'antragsfenster_von' ),
			(string) Settings::get( 'antragsfenster_bis' )
		);
	}

	/**
	 * Antrag einreichen.
	 *
	 * @param array<string, mixed> $verein  Verein (aus verein_zum_token).
	 * @param array<string, mixed> $eingabe Bereinigte Formulareingaben:
	 *        see_id, datum, ganzer_tag (bool), stunde_von, stunde_bis (int|null),
	 *        anzahl_taucher, veranstaltung, verantwortlicher, email, telefon.
	 * @return array<string, mixed>|WP_Error Blockadezeile oder Fehler (Codes in data).
	 */
	public function beantragen( array $verein, array $eingabe ) {
		$see = ( new VerfuegbarkeitsRepository() )->see_laden( (int) $eingabe['see_id'] );
		if ( null === $see ) {
			return new WP_Error( 'seebuchung_see', __( 'Dieser See existiert nicht.', 'seebuchung' ) );
		}

		$ist_tagessee = Seemodus::TAG === $see->modus;
		$ganzer_tag   = $ist_tagessee || ! empty( $eingabe['ganzer_tag'] );
		$heute        = current_time( 'Y-m-d' );
		$repo         = new BlockadenRepository();

		$fehler = Blockadenvalidierung::pruefen(
			$heute,
			(string) Settings::get( 'antragsfenster_von' ),
			(string) Settings::get( 'antragsfenster_bis' ),
			(string) $eingabe['datum'],
			$ganzer_tag,
			$ganzer_tag ? null : ( isset( $eingabe['stunde_von'] ) ? (int) $eingabe['stunde_von'] : null ),
			$ganzer_tag ? null : ( isset( $eingabe['stunde_bis'] ) ? (int) $eingabe['stunde_bis'] : null ),
			$ist_tagessee,
			$repo->anzahl_im_jahr( $see->id, (int) $verein['id'], (int) substr( (string) $eingabe['datum'], 0, 4 ) )
		);
		if ( array() !== $fehler ) {
			return new WP_Error( 'seebuchung_blockade', __( 'Der Antrag ist so nicht möglich.', 'seebuchung' ), $fehler );
		}

		$blockade = array(
			'see_id'           => $see->id,
			'datum'            => (string) $eingabe['datum'],
			'ganzer_tag'       => $ganzer_tag ? 1 : 0,
			'stunde_von'       => $ganzer_tag ? null : (int) $eingabe['stunde_von'],
			'stunde_bis'       => $ganzer_tag ? null : (int) $eingabe['stunde_bis'],
			'anzahl_taucher'   => isset( $eingabe['anzahl_taucher'] ) && (int) $eingabe['anzahl_taucher'] > 0 ? (int) $eingabe['anzahl_taucher'] : null,
			'verein_id'        => (int) $verein['id'],
			'veranstaltung'    => sanitize_text_field( (string) ( $eingabe['veranstaltung'] ?? '' ) ),
			'verantwortlicher' => sanitize_text_field( (string) ( $eingabe['verantwortlicher'] ?? '' ) ),
			'email'            => sanitize_email( (string) ( $eingabe['email'] ?? '' ) ),
			'telefon'          => sanitize_text_field( (string) ( $eingabe['telefon'] ?? '' ) ),
			'status'           => 'beantragt',
		);

		$blockade['id'] = ( new BlockadenRepository() )->anlegen( $blockade );

		$this->mail_an_admin( $blockade, $verein );

		return $blockade;
	}

	/**
	 * Antrag entscheiden (Admin) und Verein benachrichtigen.
	 *
	 * @param int  $id        Blockade-ID.
	 * @param bool $genehmigt True = genehmigen, false = ablehnen.
	 * @return array<string, mixed>|WP_Error
	 */
	public function entscheiden( int $id, bool $genehmigt ) {
		$repo     = new BlockadenRepository();
		$blockade = $repo->per_id( $id );
		if ( null === $blockade ) {
			return new WP_Error( 'seebuchung_id', __( 'Antrag nicht gefunden.', 'seebuchung' ) );
		}
		if ( 'beantragt' !== $blockade['status'] ) {
			return new WP_Error( 'seebuchung_status', __( 'Dieser Antrag ist bereits entschieden.', 'seebuchung' ) );
		}

		$status = $genehmigt ? 'genehmigt' : 'abgelehnt';
		$repo->status_setzen( $id, $status );
		$blockade['status'] = $status;

		if ( '' !== (string) $blockade['email'] ) {
			$betreff = $genehmigt
				? __( 'Eure See-Blockade ist genehmigt', 'seebuchung' )
				: __( 'Eure See-Blockade wurde abgelehnt', 'seebuchung' );
			$text    = sprintf(
				/* translators: 1: Datum, 2: Status */
				__( "Hallo,\n\neuer Blockade-Antrag für den %1\$s wurde %2\$s.", 'seebuchung' ),
				(string) $blockade['datum'],
				$genehmigt ? __( 'genehmigt — der Termin ist für euch reserviert', 'seebuchung' ) : __( 'leider abgelehnt', 'seebuchung' )
			);
			wp_mail( (string) $blockade['email'], $betreff, $text );
		}

		return $blockade;
	}

	/**
	 * Eingangsmeldung an die Verbands-Kontaktadresse.
	 *
	 * @param array<string, mixed> $blockade Blockadezeile.
	 * @param array<string, mixed> $verein   Verein.
	 */
	private function mail_an_admin( array $blockade, array $verein ): void {
		$an = (string) Settings::get( 'kontakt_email' );
		if ( '' === $an ) {
			return;
		}
		wp_mail(
			$an,
			__( 'Neuer Blockade-Antrag', 'seebuchung' ),
			sprintf(
				/* translators: 1: Verein, 2: Datum, 3: Admin-URL */
				__( "Verein %1\$s hat eine Blockade für den %2\$s beantragt.\n\nEntscheiden: %3\$s", 'seebuchung' ),
				(string) $verein['name'],
				(string) $blockade['datum'],
				admin_url( 'admin.php?page=seebuchung-blockaden' )
			)
		);
	}
}
