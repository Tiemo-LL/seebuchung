<?php
/**
 * QR-Code-Erzeugung für Tauchbestätigungen.
 *
 * @package Seebuchung
 */

namespace Seebuchung\Service;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\SvgWriter;
use Seebuchung\Domain\Buchungsstatus;
use Seebuchung\Domain\QrPayload;

/**
 * Erzeugt das QR-SVG für gültige Buchungen (F4: Handy reicht).
 *
 * SVG-Writer bewusst gewählt: keine gd/imagick-Abhängigkeit — läuft auf
 * jedem Shared Hosting.
 */
final class QrService {

	/**
	 * Stati, für die eine Bestätigung gezeigt wird.
	 *
	 * @var string[]
	 */
	private const QR_STATI = array( Buchungsstatus::GUELTIG, Buchungsstatus::KONTROLLIERT );

	/**
	 * QR-SVG für eine Buchung (oder null, wenn der Status keinen QR erlaubt).
	 *
	 * @param array<string, mixed> $buchung Buchungszeile.
	 * @return string|null SVG-Markup.
	 */
	public function svg_fuer_buchung( array $buchung ): ?string {
		if ( ! in_array( (string) $buchung['status'], self::QR_STATI, true ) ) {
			return null;
		}

		$payload = QrPayload::erzeugen( (int) $buchung['id'], self::secret() );

		return ( new Builder() )
			->writer( new SvgWriter() )
			->data( $payload )
			->encoding( new Encoding( 'UTF-8' ) )
			->errorCorrectionLevel( ErrorCorrectionLevel::Medium )
			->size( 240 )
			->margin( 8 )
			->build()
			->getString();
	}

	/**
	 * Signatur-Secret (eigene Option, einmalig erzeugt — unabhängig von
	 * WP-Salts, damit ein Salt-Wechsel ausgegebene QR-Codes nicht entwertet).
	 */
	public static function secret(): string {
		$secret = (string) get_option( 'seebuchung_qr_secret', '' );
		if ( '' === $secret ) {
			$secret = bin2hex( random_bytes( 32 ) );
			add_option( 'seebuchung_qr_secret', $secret, '', false );
		}
		return $secret;
	}
}
