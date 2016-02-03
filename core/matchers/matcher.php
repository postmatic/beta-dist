<?php
/**
 * Base class for simple fuzzy text matching.
 *
 * @since 2.0.0
 *
 */
abstract class Prompt_Matcher {

	/** @var  string */
	protected $text;

	/**
	 * Text that will be considered an exact match.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	public static function target() {
		return '';
	}

	/**
	 *
	 * @since 2.0.0
	 *
	 * @param string $text
	 */
	public function __construct( $text = '' ) {
		$this->text = $text;
	}

	/**
	 *
	 * @since 2.0.0
	 *
	 * @return boolean  Whether the text matches an expectation
	 */
	public function matches() {
		return false;
	}
}