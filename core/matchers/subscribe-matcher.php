<?php
/**
 * Determine if text is considered a subscribe request.
 *
 * @since 2.0.0
 *
 */
class Prompt_Subscribe_Matcher extends Prompt_Matcher {
	/**
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	public static function target() {
		/* translators: this is the word used to request a subscription via email reply */
		return __( 'subscribe', 'Postmatic' );
	}

	/**
	 *
	 * @since 2.0.0
	 *
	 * @return boolean  Whether the text matches a subscribe request
	 */
	public function matches() {

		$subscribe_pattern = '/^[\s\*\_]*(' . self::target() .
			'|usbscribe|s..scribe|suscribe|susribe?|susrib)[\s\*\_]*/i';

		return (bool) preg_match( $subscribe_pattern, $this->text );
	}

}