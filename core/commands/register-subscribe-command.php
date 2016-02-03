<?php

class Prompt_Register_Subscribe_Command implements Prompt_Interface_Command {
	protected static $user_data_meta_key = 'prompt_user_data';
	protected static $resend_count_meta_key = 'prompt_resend_count';
	protected static $comment_type = 'prompt_pre_reg';

	protected $keys = array( 0 );
	protected $subscribable_object;
	protected $message;

	public function set_keys( $keys ) {
		$this->keys = $keys;
	}

	public function get_keys() {
		return $this->keys;
	}

	public function set_message( $message ) {
		$this->message = $message;
	}

	public function get_message() {
		return $this->message;
	}

	public function execute() {

		if ( !$this->validate() ) {
			return;
		}

		$comment_id = $this->keys[0];
		$comment = get_comment( $comment_id );

		if ( !$comment ) {
			Prompt_Logging::add_error(
				'register_subscribe_comment_invalid',
				__( 'Couldn\'t find the original registration information for a new user.', 'Postmatic' ),
				array( 'keys' => $this->keys, 'message' => $this->message )
			);
			return;
		}

		$lists = $this->resolve_lists( $comment );

		$user_data = get_comment_meta( $comment_id, self::$user_data_meta_key, true );

		$email = $comment->comment_author_email;

		$subscriber = get_user_by( 'email', $email );

		$opted_in_list = $this->opted_in_list( $lists );

		if ( !$subscriber and !$opted_in_list ) {

			if ( self::stop_resending( $comment ) ) {
				return;
			}

			Prompt_Subscription_Mailing::send_agreement( $lists, $email, $user_data, $resend_command = $this );

			return;
		}

		if ( !$opted_in_list ) {
			// The user has already been created, probably via a different reply. Just ignore this nonsense reply.
			return;
		}

		$subscriber_id = $subscriber ? $subscriber->ID : Prompt_User_Handling::create_from_email( $email );

		if ( is_wp_error( $subscriber_id ) ) {
			Prompt_Logging::add_error(
				'register_subscribe_user_creation_failure',
				__( 'Failed to create a new user from an agreement reply email.', 'Postmatic' ),
				array(
					'keys' => $this->keys,
					'user_data' => $user_data,
					'message' => $this->message,
					'error' => $subscriber_id
				)
			);
			return;
		}

		if ( !$subscriber and $user_data ) {

			$user_data['ID'] = $subscriber_id;

			wp_update_user( $user_data );

			$origin = new Prompt_Subscriber_Origin( array(
				'source_label' => $opted_in_list->subscription_object_label(),
				'source_url' => $opted_in_list->subscription_url(),
				'agreement' => $this->message,
			) );

			$prompt_user = new Prompt_User( $subscriber_id );

			$prompt_user->set_subscriber_origin( $origin );

			do_action( 'prompt/register_subscribe_command/created_user', $prompt_user->get_wp_user() );
		}

		if ( !$opted_in_list->is_subscribed( $subscriber_id ) ) {

			$opted_in_list->subscribe( $subscriber_id );
			Prompt_Subscription_Mailing::send_subscription_notification( $subscriber_id, $opted_in_list );

		}

		// TODO: remove our pre registration comment?
	}

	/**
	 * Create a data comment with data for a potential new subscriber.
	 *
	 * @since 1.0.0
	 *
	 * @param Prompt_Interface_Subscribable[]|Prompt_Interface_Subscribable $lists
	 * @param string $email
	 * @param array $user_data
	 */
	public function save_subscription_data( $lists, $email, $user_data = array() ) {
		$remote_address = isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : '';
		$comment_id = wp_insert_comment( array(
			'comment_author_email' => $email,
			'comment_author_IP' => preg_replace( '/[^0-9a-fA-F:., ]/', '', $remote_address ),
			'comment_agent' => 'Postmatic/' . Prompt_Core::version(),
			'comment_content' => serialize( $lists ),
			'comment_type' => self::$comment_type,
			'comment_approved' => 'Postmatic',
		) );

		if ( !empty( $user_data ) ) {
			add_comment_meta( $comment_id, self::$user_data_meta_key, $user_data );
		}

		$this->keys = array( $comment_id );
	}

	/**
	 * @since 1.0.0
	 * @return bool
	 */
	protected function validate() {

		if ( !is_array( $this->keys ) or count( $this->keys ) != 1 ) {
			Prompt_Logging::add_error(
				'register_subscribe_keys_invalid',
				__( 'Received invalid metadata with a subscription agreement.', 'Postmatic' ),
				array( 'keys' => $this->keys, 'message' => $this->message )
			);
			return false;
		}

		$int_keys = array_filter( $this->keys, 'is_int' );

		if ( $int_keys != $this->keys ) {
			Prompt_Logging::add_error(
				'register_subscribe_keys_invalid',
				__( 'Received invalid metadata with a subscription agreement.', 'Postmatic' ),
				array( 'keys' => $this->keys, 'message' => $this->message )
			);
			return false;
		}

		if ( empty( $this->message ) ) {
			Prompt_Logging::add_error(
				'register_subscribe_message_invalid',
				__( 'Received no message with a subscription agreement.', 'Postmatic' ),
				array( 'keys' => $this->keys, 'message' => $this->message )
			);
			return false;
		}

		return true;
	}

	/**
	 * @since 1.0.0
	 * @return string
	 */
	protected function get_message_text() {
		return $this->message->message;
	}

	/**
	 * Whether to stop resending the agreement associated with a data comment.
	 *
	 * @since 1.0.0
	 * @param object $comment
	 * @return bool
	 */
	protected function stop_resending( $comment ) {

		$resend_count = get_comment_meta( $comment->comment_ID, self::$resend_count_meta_key, true );

		$resend_count += 1;

		update_comment_meta( $comment->comment_ID, self::$resend_count_meta_key, $resend_count );

		return ( $resend_count > 2 );
	}

	/**
	 * Get lists from comment data.
	 *
	 * Handles data created by earlier versions.
	 *
	 * @since 2.0.0
	 *
	 * @param WP_Comment $comment
	 * @return Prompt_Interface_Subscribable|Prompt_Interface_Subscribable[]
	 */
	protected function resolve_lists( $comment ) {

		if ( 'Postmatic/' == substr( $comment->comment_agent, 0, 10 ) ) {
			return unserialize( $comment->comment_content );
		}

		// Comment was created by an earlier version of Postmatic
		return array( new $comment->comment_agent( $comment->comment_parent ) );
	}

	/**
	 *
	 * @since 2.0.0
	 *
	 * @param Prompt_Interface_Subscribable[] $lists
	 * @return null|Prompt_Interface_Subscribable
	 */
	protected function opted_in_list( $lists ) {

		$opted_in_list = null;

		$stripped_text = $this->get_message_text();

		if ( 1 == count( $lists ) ) {
			$agree_matcher = new Prompt_Agree_Matcher( $stripped_text );
			$opted_in_list = $agree_matcher->matches() ? $lists[0] : null;
		}

		if ( $opted_in_list ) {
			return $opted_in_list;
		}

		foreach ( $lists as $list ) {
			$opted_in_list = $this->is_opt_in( $stripped_text, $list ) ? $list : $opted_in_list;
		}

		return $opted_in_list;
	}

	/**
	 *
	 * @since 2.0.0
	 *
	 * @param string $text
	 * @param Prompt_Interface_Subscribable $list
	 * @return bool
	 */
	protected function is_opt_in( $text, Prompt_Interface_Subscribable $list ) {

		$class = 'Prompt_' . ucfirst( $list->subscribe_phrase() . '_Matcher' );

		if ( !class_exists( $class ) ) {
			return false;
		}

		$matcher = new $class( $text );
		return $matcher->matches();
	}
}