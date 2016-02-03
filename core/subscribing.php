<?php

class Prompt_Subscribing {
	const SUBSCRIBE_ACTION = 'prompt_subscribe';

	/** @var array Subscribable types - could be extended into a registration system  */
	protected static $subscribables = array(
		'Prompt_Site' => null,
		'Prompt_Post' => 'WP_Post',
		'Prompt_User' => 'WP_User',
	);

	/**
	 * Instantiate a subscribable object.
	 *
	 * @param null|WP_Post|WP_user|int $object Optional object to pass to the constructor.
	 * @return Prompt_Interface_Subscribable
	 */
	public static function make_subscribable( $object = null ) {
		if (
			is_a( $object, 'WP_Post' ) and
			in_array( $object->post_type, Prompt_Core::$options->get( 'site_subscription_post_types' ) )
		) {
			return new Prompt_Post( $object );
		}

		$subscribables = array_diff_key( self::$subscribables, array( 'Prompt_Post' => true ) );
		foreach ( $subscribables as $subscribable_type => $init_object_type ) {
			if ( is_a( $object, $init_object_type ) )
				return new $subscribable_type( $object );
		}

		return apply_filters(
			'prompt/subscribing/make_subscribable',
			new Prompt_Site(),
			$object
		);
	}

	/**
	 * Get registered subscribable types in fixed order.
	 * @return array
	 */
	public static function get_subscribable_classes() {
		return apply_filters( 'prompt/subscribing/get_subscribable_classes', array_keys( self::$subscribables ) );
	}

	/**
	 * Get the lists enabled for new subscribers to choose from.
	 *
	 * @since 2.0.0
	 *
	 * @return Prompt_Interface_Subscribable[]
	 */
	public static function get_signup_lists() {
		$lists = array();

		if ( Prompt_Core::$options->get( 'enable_post_delivery' ) ) {
			$lists[] = new Prompt_Site();
		}

		$lists = apply_filters( 'prompt/subscribing/get_signup_lists', $lists );

		// Always have something to sign up for
		if ( empty( $lists ) ) {
			$lists[] = new Prompt_Site();
		}

		return $lists;
	}

}