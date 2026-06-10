<?php
/**
 * Kontingent-Modus eines Sees.
 *
 * @package Seebuchung
 */

namespace Seebuchung\Domain;

/**
 * Ein See vergibt sein Kontingent entweder pro Tag oder pro Stunde.
 */
final class Seemodus {

	public const TAG    = 'tag';
	public const STUNDE = 'stunde';

	/**
	 * Alle gültigen Modi.
	 *
	 * @var string[]
	 */
	public const ALLE = array( self::TAG, self::STUNDE );
}
