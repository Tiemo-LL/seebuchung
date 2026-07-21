<?php
/**
 * Shortcode [seebuchung] — die komplette Buchungsstrecke.
 *
 * @package Seebuchung
 */

namespace Seebuchung\Frontend;

use Seebuchung\Database\BrevetsRepository;
use Seebuchung\Database\SeenRepository;
use Seebuchung\Database\VerfuegbarkeitsRepository;
use Seebuchung\Domain\See;
use Seebuchung\Domain\Seemodus;
use Seebuchung\Service\Buchungsservice;
use WP_Error;

/**
 * Rendert je nach Query-Parametern: Seeauswahl → Kalender → Stundenwahl →
 * Formular, sowie die Token-Seite (Bestätigen/Stornieren) und Hinweise.
 *
 * Alle Schritte sind serverseitig gerendert (mobile-first, ohne JS-Zwang);
 * Schreibaktionen laufen als POST über admin-post.php (PRG-Pattern).
 */
final class Shortcode {

	/**
	 * Shortcode und Assets registrieren.
	 */
	public static function registrieren(): void {
		add_shortcode( 'seebuchung', array( new self(), 'render' ) );
		add_action( 'template_redirect', array( self::class, 'cache_verhindern' ) );
		add_action(
			'wp_enqueue_scripts',
			static function () {
				wp_register_style(
					'seebuchung',
					SEEBUCHUNG_PLUGIN_URL . 'public/css/seebuchung.css',
					array(),
					SEEBUCHUNG_VERSION
				);
			}
		);
	}

	/**
	 * Buchungsseiten vom Page-Cache ausnehmen.
	 *
	 * Restplätze und Nonces müssen live sein — gecachte Seiten zeigen
	 * veraltete Kontingente und liefern irgendwann abgelaufene Nonces.
	 * DONOTCACHEPAGE respektieren alle gängigen Cache-Plugins.
	 */
	public static function cache_verhindern(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Nur Erkennung der Buchungsseite.
		$hat_parameter = isset( $_GET['sb_see'] ) || isset( $_GET['sb_token'] ) || isset( $_GET['sb_verein'] )
			|| isset( $_GET['sb_datum'] ) || isset( $_GET['sb_ergebnis'] ) || isset( $_GET['sb_monat'] );
		// phpcs:enable

		$post = get_post();
		if ( ! $hat_parameter && ( null === $post || ! has_shortcode( (string) $post->post_content, 'seebuchung' ) ) ) {
			return;
		}

		nocache_headers();
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- Standardisierte Konstante, die Cache-Plugins auswerten.
			define( 'DONOTCACHEPAGE', true );
		}
	}

	/**
	 * Einstiegspunkt des Shortcodes.
	 */
	public function render(): string {
		wp_enqueue_style( 'seebuchung' );

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Lesende Navigation; Schreibaktionen laufen über admin-post mit Nonce.
		$token  = isset( $_GET['sb_token'] ) ? sanitize_text_field( wp_unslash( $_GET['sb_token'] ) ) : '';
		$see_id = isset( $_GET['sb_see'] ) ? (int) $_GET['sb_see'] : 0;
		$datum  = isset( $_GET['sb_datum'] ) ? sanitize_text_field( wp_unslash( $_GET['sb_datum'] ) ) : '';
		$stunde = isset( $_GET['sb_stunde'] ) ? (int) $_GET['sb_stunde'] : null;
		$monat  = isset( $_GET['sb_monat'] ) ? sanitize_text_field( wp_unslash( $_GET['sb_monat'] ) ) : '';
		// phpcs:enable

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Lesende Navigation.
		$vereins_token = isset( $_GET['sb_verein'] ) ? sanitize_text_field( wp_unslash( $_GET['sb_verein'] ) ) : '';

		$hinweis = $this->hinweis_html();

		if ( '' !== $vereins_token ) {
			return $hinweis . $this->blockade_antrag( $vereins_token );
		}
		if ( '' !== $token ) {
			return $hinweis . $this->token_seite( $token );
		}
		if ( $see_id > 0 && '' !== $datum && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $datum ) ) {
			return $hinweis . $this->tagesschritt( $see_id, $datum, $stunde );
		}
		if ( $see_id > 0 ) {
			return $hinweis . $this->kalender( $see_id, $monat );
		}
		return $hinweis . $this->seeauswahl();
	}

	/**
	 * Schritt 1: Liste der aktiven Seen.
	 */
	private function seeauswahl(): string {
		$seen = ( new SeenRepository() )->aktive();
		return $this->template( 'seeauswahl', array( 'seen' => $seen ) );
	}

	/**
	 * Schritt 2: Monatskalender mit Restplätzen.
	 *
	 * @param int    $see_id See-ID.
	 * @param string $monat  Monat (Y-m) oder leer für aktuellen Monat.
	 */
	private function kalender( int $see_id, string $monat ): string {
		$repo   = new VerfuegbarkeitsRepository();
		$see    = $repo->see_laden( $see_id );
		$engine = $repo->engine_fuer_see( $see_id );
		if ( null === $see || null === $engine ) {
			return $this->template( 'hinweis', array( 'text' => __( 'Dieser See existiert nicht.', 'seebuchung' ) ) );
		}

		if ( ! preg_match( '/^\d{4}-\d{2}$/', $monat ) ) {
			$monat = current_time( 'Y-m' );
		}

		$erster = new \DateTimeImmutable( $monat . '-01' );
		$tage   = array();
		$anzahl = (int) $erster->format( 't' );
		for ( $tag = 1; $tag <= $anzahl; $tag++ ) {
			$datum = $erster->setDate( (int) $erster->format( 'Y' ), (int) $erster->format( 'n' ), $tag )->format( 'Y-m-d' );
			$rest  = 0;
			if ( $engine->ist_buchbar_am( $datum ) ) {
				$uebersicht = $engine->tagesuebersicht( $datum );
				$rest       = array() === $uebersicht ? 0 : max( $uebersicht );
			}
			$tage[ $datum ] = $rest;
		}

		return $this->template(
			'kalender',
			array(
				'see'          => $see,
				'monat'        => $erster,
				'tage'         => $tage,
				'basis_url'    => $this->basis_url(),
				'vor_monat'    => $erster->modify( '-1 month' )->format( 'Y-m' ),
				'naech_monat'  => $erster->modify( '+1 month' )->format( 'Y-m' ),
				'start_offset' => (int) $erster->format( 'N' ) - 1,
			)
		);
	}

	/**
	 * Schritt 3/4: Stundenwahl (Stundensee) bzw. Formular.
	 *
	 * @param int      $see_id See-ID.
	 * @param string   $datum  Datum (Y-m-d).
	 * @param int|null $stunde Gewählter Slot oder null.
	 */
	private function tagesschritt( int $see_id, string $datum, ?int $stunde ): string {
		$repo   = new VerfuegbarkeitsRepository();
		$see    = $repo->see_laden( $see_id );
		$engine = $repo->engine_fuer_see( $see_id );
		if ( null === $see || null === $engine || ! $engine->ist_buchbar_am( $datum ) ) {
			return $this->template( 'hinweis', array( 'text' => __( 'Dieses Datum ist nicht buchbar.', 'seebuchung' ) ) );
		}

		if ( Seemodus::STUNDE === $see->modus && null === $stunde ) {
			return $this->template(
				'stundenwahl',
				array(
					'see'        => $see,
					'datum'      => $datum,
					'uebersicht' => $engine->tagesuebersicht( $datum ),
					'basis_url'  => $this->basis_url(),
				)
			);
		}

		$rest = Seemodus::TAG === $see->modus
			? $engine->rest_fuer_tag( $datum )
			: $engine->rest_fuer_stunde( $datum, (int) $stunde );
		if ( $rest < 1 ) {
			return $this->template( 'hinweis', array( 'text' => __( 'Für diesen Termin sind keine Plätze mehr frei.', 'seebuchung' ) ) );
		}

		return $this->template(
			'formular',
			array(
				'see'       => $see,
				'datum'     => $datum,
				'stunde'    => Seemodus::TAG === $see->modus ? null : (int) $stunde,
				'rest'      => $rest,
				'brevets'   => ( new BrevetsRepository() )->aktive(),
				'basis_url' => $this->basis_url(),
			)
		);
	}

	/**
	 * Token-Seite: Buchungsdetails mit Bestätigen-/Storno-Aktionen.
	 *
	 * @param string $token Klartext-Token aus dem Link.
	 */
	private function token_seite( string $token ): string {
		$service = new Buchungsservice();
		$buchung = $service->per_token( $token );
		if ( $buchung instanceof WP_Error ) {
			return $this->template( 'hinweis', array( 'text' => $buchung->get_error_message() ) );
		}

		$see = ( new VerfuegbarkeitsRepository() )->see_laden( (int) $buchung['see_id'] );

		return $this->template(
			'status',
			array(
				'buchung'   => $buchung,
				'see'       => $see,
				'token'     => $token,
				'basis_url' => $this->basis_url(),
				'qr_svg'    => ( new \Seebuchung\Service\QrService() )->svg_fuer_buchung( $buchung ),
			)
		);
	}

	/**
	 * Blockade-Antragsseite für Vereine (F2, über Token-Link).
	 *
	 * @param string $vereins_token Klartext-Token aus dem Vereinslink.
	 */
	private function blockade_antrag( string $vereins_token ): string {
		$service = new \Seebuchung\Service\Blockadenservice();
		$verein  = $service->verein_zum_token( $vereins_token );
		if ( null === $verein ) {
			return $this->template( 'hinweis', array( 'text' => __( 'Dieser Vereinslink ist ungültig oder wurde widerrufen.', 'seebuchung' ) ) );
		}

		if ( ! $service->fenster_offen() ) {
			return $this->template(
				'hinweis',
				array(
					'text' => sprintf(
						/* translators: 1: Fensterbeginn, 2: Fensterende */
						__( 'Blockade-Anträge sind nur im Antragsfenster möglich (%1$s bis %2$s).', 'seebuchung' ),
						(string) \Seebuchung\Settings::get( 'antragsfenster_von' ),
						(string) \Seebuchung\Settings::get( 'antragsfenster_bis' )
					),
				)
			);
		}

		return $this->template(
			'blockade-antrag',
			array(
				'verein'    => $verein,
				'seen'      => ( new SeenRepository() )->aktive(),
				'token'     => $vereins_token,
				'zieljahr'  => \Seebuchung\Domain\Blockadenvalidierung::zieljahr( current_time( 'Y-m-d' ), (string) \Seebuchung\Settings::get( 'antragsfenster_von' ) ),
				'basis_url' => $this->basis_url(),
			)
		);
	}

	/**
	 * Hinweis-Block nach Redirects (PRG).
	 */
	private function hinweis_html(): string {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Nur Anzeige einer Meldung.
		$ergebnis = isset( $_GET['sb_ergebnis'] ) ? sanitize_key( wp_unslash( $_GET['sb_ergebnis'] ) ) : '';
		$codes    = isset( $_GET['sb_fehler'] ) ? sanitize_text_field( wp_unslash( $_GET['sb_fehler'] ) ) : '';
		// phpcs:enable
		if ( '' === $ergebnis ) {
			return '';
		}

		$texte = array(
			'angefragt'          => __( 'Deine Anfrage ist eingegangen! Bitte bestätige sie über den Link, den wir dir per E-Mail geschickt haben.', 'seebuchung' ),
			'bestaetigt'         => __( 'Deine Buchung ist bestätigt.', 'seebuchung' ),
			'gueltig'            => __( 'Deine Buchung ist bestätigt und gültig. Gut Luft!', 'seebuchung' ),
			'storniert'          => __( 'Deine Buchung wurde storniert.', 'seebuchung' ),
			'paypal_fehler'      => __( 'Die PayPal-Zahlung konnte nicht abgeschlossen werden. Bitte versuche es erneut.', 'seebuchung' ),
			'blockade_beantragt' => __( 'Euer Blockade-Antrag ist eingegangen. Ihr bekommt eine Mail, sobald er entschieden ist.', 'seebuchung' ),
		);

		if ( 'fehler' === $ergebnis ) {
			$text = implode( ' ', Fehlertexte::zu_codes( explode( ',', $codes ) ) );
			return $this->template(
				'hinweis',
				array(
					'text'   => '' !== $text ? $text : __( 'Das hat leider nicht geklappt.', 'seebuchung' ),
					'fehler' => true,
				)
			);
		}

		return isset( $texte[ $ergebnis ] )
			? $this->template( 'hinweis', array( 'text' => $texte[ $ergebnis ] ) )
			: '';
	}

	/**
	 * URL der Shortcode-Seite (für Navigationslinks).
	 */
	private function basis_url(): string {
		return (string) get_permalink();
	}

	/**
	 * Template aus templates/ rendern (per Filter überschreibbar, F7).
	 *
	 * @param string               $name      Template-Name ohne .php.
	 * @param array<string, mixed> $variablen Variablen fürs Template.
	 */
	private function template( string $name, array $variablen ): string {
		$pfad = SEEBUCHUNG_PLUGIN_DIR . 'templates/' . $name . '.php';

		/**
		 * Template-Pfad filtern, damit Themes/Verbände eigene Templates liefern können.
		 *
		 * @param string $pfad Absoluter Pfad zur Template-Datei.
		 * @param string $name Template-Name.
		 */
		$pfad = (string) apply_filters( 'seebuchung_template', $pfad, $name );
		if ( ! file_exists( $pfad ) ) {
			return '';
		}

		ob_start();
		// phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- Kontrolliertes Template-Muster, Schlüssel stammen aus dem Plugin.
		extract( $variablen );
		include $pfad;
		return (string) ob_get_clean();
	}
}
