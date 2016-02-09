<?php
/**
 * Determine if text is considered an unsubscribe request.
 *
 * @since 2.0.0
 *
 */
class Prompt_Unsubscribe_Matcher extends Prompt_Matcher {

	/**
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	public static function target() {
		/* translators: this is the word used to unsubscribe via email reply */
		return __( 'unsubscribe', 'Postmatic' );
	}

	/**
	 *
	 * @since 2.0.0
	 *
	 * @return boolean  Whether the text matches an unsubscribe request
	 */
	public function matches() {

		$unsubscribe_pattern = '/^[\s\*\_\pZ\pC]*(' . self::target() .
			'|un..[bn]scri?be?|sunsubscribe|unsusbscribe|un..scribe|unsusribe?|unsubcribe)[\s\*\_]*/iu';

		return (bool) preg_match( $unsubscribe_pattern, $this->text );
	}

}