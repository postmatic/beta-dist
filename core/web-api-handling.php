<?php

class Prompt_Web_Api_Handling {

	/**
	 * Receive an ajax API pull updates request.
	 */
	public static function receive_pull_updates() {

		self::validate_or_die();

		$result = Prompt_Inbound_Handling::pull_updates();

		self::set_return_code_and_die( $result );
	}

	public static function receive_pull_configuration() {

		self::validate_or_die();

		$configurator = Prompt_Factory::make_configurator();

		self::set_return_code_and_die( $configurator->pull_configuration() );
	}

	public static function receive_callback() {

		self::validate_or_die();

		$metadata = self::get_callback_metadata_or_die();

		do_action_ref_array( $metadata[0], $metadata[1] );

		self::set_return_code_and_die( 200 );
	}

	protected static function validate_or_die() {
		if ( !self::validate_request() ) {
			status_header( 401 );
			wp_die();
		}
	}

	protected static function set_return_code_and_die( $status ) {
		if ( is_wp_error( $status ) )
			status_header( 500 );

		wp_die();
	}

	/**
	 * @return array metadata
	 */
	protected static function get_callback_metadata_or_die() {
		if ( !isset( $_GET['metadata'] ) ) {
			status_header( 400 );
			wp_die();
		}

		// There's an extra level of slashes to remove here originating in json_encode()
		return wp_unslash( json_decode( wp_unslash( $_GET['metadata'] ), $assoc = true ) );
	}

	/**
	 * @return bool Whether request is valid.
	 */
	protected static function validate_request() {

		$timestamp = intval( $_GET['timestamp'] );
		if (
			!isset( $_GET['timestamp'] ) or
			!is_numeric( $_GET['timestamp'] ) or
			abs( time() - $timestamp ) > 60*60*6
		) {
			Prompt_Logging::add_error(
				'inbound_invalid_timestamp',
				__( 'Rejected an inbound request with an invalid timestamp. Could be bot activity.', 'Postmatic' ),
				$_GET
			);
			return false;
		}

		if ( empty( $_GET['token'] ) ) {
			Prompt_Logging::add_error(
				'inbound_invalid_token',
				__( 'Rejected an inbound request with an invalid token. Could be bot activity.', 'Postmatic' ),
				$_GET
			);
			return false;
		}
		$token = sanitize_key( $_GET['token'] );

		if ( !isset( $_GET['signature'] ) or strlen( $_GET['signature'] ) != 64 ) {
			Prompt_Logging::add_error(
				'inbound_invalid_signature',
				__( 'Rejected an inbound request with an invalid signature. Could be bot activity.', 'Postmatic' ),
				$_GET
			);
			return false;
		}

		$signature = $_GET['signature'];
		if ( hash_hmac( 'sha256', $timestamp . $token, Prompt_Core::$options->get( 'prompt_key' ) ) != $signature ) {
			Prompt_Logging::add_error(
				'inbound_invalid_signature',
				__( 'Rejected an inbound request with an invalid signature. Could be bot activity.', 'Postmatic' ),
				$_GET
			);
			return false;
		}

		if ( ! Prompt_Core::$options->get( 'connected' ) ) {
			Prompt_Core::$options->set( 'connected', true );
		}

		return true;
	}

}