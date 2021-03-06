<?php

/**
 * An email batch that knows how to render a comment for emails.
 *
 * @since 2.0.0
 *
 */
class Prompt_Comment_Email_Batch extends Prompt_Email_Batch {

	protected static $recipient_ids_meta_key = 'prompt_recipient_ids';
	protected static $sent_meta_key = 'prompt_sent_ids';

	/** @var  object */
	protected $comment;
	/** @var  Prompt_Post */
	protected $prompt_post;
	/** @var  string */
	protected $subscribed_post_title_link;
	/** @var  WP_User */
	protected $parent_author;
	/** @var  string */
	protected $parent_author_name;
	/** @var  object */
	protected $parent_comment;
	/** @var  string */
	protected $commenter_name;
	/** @var  Prompt_User */
	protected $recipient;
	/** @var bool */
	protected $replyable;
	/** @var  Prompt_Comment_Flood_Controller */
	protected $flood_controller;
	/** @var  array */
	protected $previous_comments;

	/**
	 * Builds an email batch with content and recipients based on a comment.
	 *
	 * @since 2.0.0
	 *
	 * @param object $comment Target comment
	 * @param Prompt_Comment_Flood_Controller
	 */
	public function __construct( $comment, Prompt_Comment_Flood_Controller $flood_controller = null ) {

		$this->comment = $comment;
		$this->prompt_post = $prompt_post = new Prompt_Post( $this->comment->comment_post_ID );
		$this->flood_controller = $flood_controller ?
			$flood_controller :
			new Prompt_Comment_Flood_Controller( $comment );

		$this->subscribed_post_title_link = html( 'a',
			array( 'href' => get_permalink( $this->prompt_post->id() ) ),
			get_the_title( $this->prompt_post->id() )
		);

		$comment_author = $this->comment_author_user();

		$is_api_delivery = ( Prompt_Enum_Email_Transports::API == Prompt_Core::$options->get( 'email_transport' ) );

		$parent_comment = $parent_author = null;
		$parent_author_name = '';
		$template_file = 'new-comment-email.php';

		if ( $this->comment->comment_parent ) {
			$parent_comment = get_comment( $this->comment->comment_parent );
			$parent_author = get_userdata( $parent_comment->user_id );

			$parent_author_name = $parent_author ? $parent_author->display_name : $parent_comment->comment_author;
			$parent_author_name = $parent_author_name ? $parent_author_name : __( 'Anonymous', 'Postmatic' );

			$template_file = $is_api_delivery ? 'comment-reply-email.php' : $template_file;
		}
		$this->parent_comment = $parent_comment;
		$this->parent_author = $parent_author;
		$this->parent_author_name = $parent_author_name;

		$commenter_name = $comment_author ? $comment_author->display_name : $this->comment->comment_author;
		$commenter_name = $commenter_name ? $commenter_name : __( 'Anonymous', 'Postmatic' );
		$this->commenter_name = $commenter_name;

		$post_author = get_userdata( $prompt_post->get_wp_post()->post_author );
		$post_author_name = $post_author ? $post_author->display_name : __( 'Anonymous', 'Postmatic' );

		$this->set_previous_comments();

		$template_data = array(
			'comment_author' => $comment_author,
			'comment' => $this->comment,
			'commenter_name' => $commenter_name,
			'subscribed_post' => $prompt_post,
			'subscribed_post_author_name' => $post_author_name,
			'subscribed_post_title_link' => $this->subscribed_post_title_link,
			'previous_comments' => $this->previous_comments,
			'parent_author' => $parent_author,
			'parent_author_name' => $parent_author_name,
			'parent_comment' => $parent_comment,
			'comment_header' => true,
			'is_api_delivery' => $is_api_delivery,
		);

		/**
		 * Filter comment email template data.
		 *
		 * @param array $template_data {
		 * @type WP_User $comment_author
		 * @type WP_User $subscriber
		 * @type object $comment
		 * @type Prompt_post $subscribed_post
		 * @type string $subscribed_post_author_name
		 * @type array $previous_comments
		 * @type WP_User $parent_author
		 * @type string $parent_author_name
		 * @type object $parent_comment
		 * @type bool $comment_header
		 * @type bool $is_api_delivery
		 * }
		 */
		$template_data = apply_filters( 'prompt/comment_email/template_data', $template_data );

		$html_template = new Prompt_Template( $template_file );
		$text_template = new Prompt_Text_Template( str_replace( '.php', '-text.php', $template_file ) );

		/* translators: %1$s is a subscription list title, %2$s the unsubscribe command */
		$footnote_format = __(
			'You received this email because you\'re subscribed to %1$s. To no longer receive other comments or replies in this discussion reply with the word \'%2$s\'.',
			'Postmatic'
		);
		$message_template = array(
			'from_name' => $commenter_name,
			'text_content' => $text_template->render( $template_data ),
			'html_content' => $html_template->render( $template_data ),
			'message_type' => Prompt_Enum_Message_Types::COMMENT,
			'subject' => '{{{subject}}}',
			'reply_to' => '{{{reply_to}}}',
			'footnote_html' => sprintf(
				$footnote_format,
				$this->prompt_post->subscription_object_label(),
				"<a href=\"{$this->unsubscribe_mailto()}\">" . Prompt_Unsubscribe_Matcher::target() . "</a>"
			),
			/* translators: %1$s is a subscription list title, %2$s is the unsubscribe command word */
			'footnote_text' => sprintf(
				$footnote_format,
				$this->prompt_post->subscription_object_label( Prompt_Enum_Content_Types::TEXT ),
				Prompt_Unsubscribe_Matcher::target()
			),
		);

		parent::__construct( $message_template );

		$recipient_ids = array_diff( $this->flood_controlled_recipient_ids(), $this->sent_recipient_ids() );

		/**
		 * Filter whether to send new comment notifications.
		 *
		 * @param boolean $send Default true.
		 * @param object $comment
		 * @param array $recipient_ids
		 */
		if ( !apply_filters( 'prompt/send_comment_notifications', true, $this->comment, $recipient_ids ) )
			return null;

		$this->add_recipients( $recipient_ids );

	}

	/**
	 *
	 * @since 2.0.0
	 *
	 * @return object
	 */
	public function get_comment() {
		return $this->comment;
	}

	/**
	 * Add the IDs of users who have been sent an email notification for this comment.
	 *
	 * @since 2.0.0
	 */
	public function lock_for_sending() {

		$recipient_ids = wp_list_pluck( $this->individual_message_values, 'id' );

		$sent_ids = array_unique( array_merge( $this->sent_recipient_ids(), $recipient_ids ) );

		update_comment_meta( $this->comment->comment_ID, self::$sent_meta_key, $sent_ids );
	}

	/**
	 * Remove the IDs of users from the sent list so delivery can be retried.
	 *
	 * @since 2.0.0
	 */
	public function clear_for_retry() {

		$recipient_ids = wp_list_pluck( $this->individual_message_values, 'id' );

		$sent_ids = array_diff( $this->sent_recipient_ids(), $recipient_ids );

		update_comment_meta( $this->comment->comment_ID, self::$sent_meta_key, $sent_ids );
	}

	/**
	 * Add recipient-specific values for an email.
	 *
	 * @since 2.0.0
	 *
	 * @param WP_User $recipient
	 * @return $this
	 */
	protected function add_recipient( WP_User $recipient ) {

		$unsubscribe_link = new Prompt_Unsubscribe_Link( $recipient );

		$command = new Prompt_Comment_Command();
		$command->set_post_id( $this->prompt_post->id() );
		$command->set_user_id( $recipient->ID );
		$command->set_parent_comment_id( $this->comment->comment_ID );

		$values = array(
			'id' => $recipient->ID,
			'to_name' => $recipient->display_name,
			'to_address' => $recipient->user_email,
			'subject' => $this->subscriber_subject( $recipient ),
			'unsubscribe_url' => $unsubscribe_link->url(),
			'subscriber_comment_intro_html' => $this->subscriber_comment_intro_html( $recipient ),
			'subscriber_comment_intro_text' => $this->subscriber_comment_intro_text( $recipient ),
			'reply_to' => $this->trackable_address( Prompt_Command_Handling::get_command_metadata( $command ) ),
		);

		$values = array_merge(
			$values,
			Prompt_Command_Handling::get_comment_reply_macros( $this->previous_comments, $recipient->ID )
		);

		return $this->add_individual_message_values( $values );
	}

	/**
	 * Get the comment author user if there is one.
	 *
	 * @since 2.0.0
	 *
	 * @return bool|WP_User
	 */
	protected function comment_author_user() {

		$comment_author = get_user_by( 'id', $this->comment->user_id );
		if ( !$comment_author )
			$comment_author = get_user_by( 'email', $this->comment->comment_author_email );

		return $comment_author;
	}

	/**
	 * Set the previous approved comments array.
	 *
	 * Always includes the comment being mailed.
	 *
	 * If the comment is a reply, gets ancestor comments.
	 *
	 * If the comment is top level, gets previous top level comments.
	 *
	 * Adds an 'excerpt' property with a 100 word text excerpt.
	 *
	 * @since 2.0.0
	 *
	 * @param int $number
	 * @return $this
	 */
	protected function set_previous_comments( $number = 3 ) {

		if ( $this->comment->comment_parent ) {
			$this->previous_comments = $this->comment_thread();
			return $this;
		}

		$comments = $this->previous_top_level_comments( $number );

		foreach ( $comments as $comment ) {
			$comment->excerpt = $this->excerpt( $comment );
		}

		$this->previous_comments = array_reverse( $comments );

		return $this;
	}

	/**
	 *
	 * @since 2.0.0
	 *
	 * @return array
	 */
	protected function comment_thread() {

		$comment = $this->comment;
		$comments = array( $comment );

		while ( $comment->comment_parent ) {
			$comment = get_comment( $comment->comment_parent );
			$comments[] = $comment;
		}

		return $comments;
	}

	/**
	 *
	 * @since 2.0.0
	 *
	 * @param int $number
	 * @return array
	 */
	protected function previous_top_level_comments( $number = 3 ) {
		$query = array(
			'post_id' => $this->comment->comment_post_ID,
			'parent' => 0,
			'status' => 'approve',
			'number' => $number,
			'date_query' => array(
				array(
					'before' => $this->comment->comment_date,
					'inclusive' => true,
				)
			)
		);
		return get_comments( $query );
	}

	/**
	 * Make a 100 word excerpt of a comment.
	 *
	 * @since 2.0.0
	 *
	 * @param object $comment
	 * @param int $word_count
	 * @return string
	 */
	protected function excerpt( $comment, $word_count = 100 ) {

		$comment_text = strip_tags( $comment->comment_content );

		$words = explode( ' ', $comment_text );

		$elipsis = count( $words ) > $word_count ? ' &hellip;' : '';

		return implode( ' ', array_slice( $words, 0, $word_count ) ) . $elipsis;
	}


	/**
	 *
	 * @since 2.0.0
	 *
	 * @param WP_User $subscriber
	 * @return string
	 */
	protected function subscriber_subject( WP_User $subscriber ) {
		if ( $this->parent_author and $this->parent_author->ID == $subscriber->ID ) {
			return sprintf(
				__( '%s replied to your comment on %s.', 'Postmatic' ),
				$this->commenter_name,
				$this->prompt_post->get_wp_post()->post_title
			);
		}

		if ( $this->parent_comment ) {
			return sprintf(
				__( '%s replied to %s on %s', 'Postmatic' ),
				$this->commenter_name,
				$this->parent_author_name,
				$this->prompt_post->get_wp_post()->post_title
			);
		}

		return sprintf(
			__( '%s commented on %s', 'Postmatic' ),
			$this->commenter_name,
			$this->prompt_post->get_wp_post()->post_title
		);
	}

	/**
	 *
	 * @since 2.0.0
	 *
	 * @param WP_User $subscriber
	 * @return string
	 */
	protected function subscriber_comment_intro_html( WP_User $subscriber ) {
		return $this->subscriber_comment_intro( $subscriber, Prompt_Enum_Content_Types::HTML );
	}

	/**
	 *
	 * @since 2.0.0
	 *
	 * @param WP_User $subscriber
	 * @return string
	 */
	protected function subscriber_comment_intro_text( WP_User $subscriber ) {
		return $this->subscriber_comment_intro( $subscriber, Prompt_Enum_Content_Types::TEXT );
	}

	/**
	 *
	 * @since 2.0.0
	 *
	 * @param WP_User $subscriber
	 * @param string $type Default HTML, alternately Prompt_Enum_Content_Types::TEXT.
	 * @return string
	 */
	protected function subscriber_comment_intro( WP_User $subscriber, $type = Prompt_Enum_Content_Types::HTML ) {

		$name = $this->commenter_name;
		$parent_author_name = $this->parent_author_name;
		$title = $this->prompt_post->get_wp_post()->post_title;

		if ( Prompt_Enum_Content_Types::HTML === $type ) {
			$name = html( 'span class="capitalize"', $name );
			$parent_author_name = html( 'span class="capitalize"', $parent_author_name );
			$title = $this->subscribed_post_title_link;
		}

		if ( $this->parent_author and $this->parent_author->ID == $subscriber->ID ) {
			return sprintf( __( '%s replied to your comment on %s:', 'Postmatic' ), $name, $title );
		}

		if ( $this->parent_author ) {
			return sprintf(
				__( '%s left a reply to a comment by %s on %s:', 'Postmatic' ),
				$name,
				$parent_author_name,
				$this->subscribed_post_title_link
			);
		}

		return '';
	}

	/**
	 *
	 * @since 2.0.0
	 *
	 * @param $comment_id
	 * @return array
	 */
	protected function comment_children( $comment_id ) {
		$children = get_comments( array(
			'parent' => $comment_id,
			'status' => 'approve',
		) );

		if ( ! $children )
			return array();

		foreach ( $children as $child ) {
			$children = array_merge( $children, $this->comment_children( $child->comment_ID ) );
		}

		return $children;
	}

	/**
	 * Find recipients after flood control.
	 *
	 * @since 2.0.0
	 *
	 * @return array IDs of users who should receive a comment notification
	 */
	protected function flood_controlled_recipient_ids() {

		// We currently only mail standard WP comments
		if ( !empty( $this->comment->comment_type ) )
			return array();

		$recipient_ids = get_comment_meta( $this->comment->comment_ID, self::$recipient_ids_meta_key, true );

		if ( ! $recipient_ids ) {

			$recipient_ids = $this->flood_controller->control_recipient_ids();
			/**
			 * Filter the recipient ids of notifications for a comment.
			 *
			 * @param array $recipient_ids
			 * @param WP_Post $post
			 */
			$recipient_ids = apply_filters( 'prompt/recipient_ids/comment', $recipient_ids, $this->comment );

			update_comment_meta( $this->comment->comment_ID, self::$recipient_ids_meta_key, $recipient_ids );

		}

		return $recipient_ids;
	}

	/**
	 *
	 * @since 2.0.0
	 *
	 * @param array $subscriber_ids
	 */
	protected function add_recipients( array $subscriber_ids ) {

		$this->set_individual_message_values( array() );

		foreach ( $subscriber_ids as $subscriber_id ) {

			$subscriber = get_userdata( $subscriber_id );

			if ( !$subscriber or !$subscriber->user_email )
				continue;

			$this->add_recipient( $subscriber );

		}
	}

	/**
	 *
	 * @since 2.0.0
	 *
	 * @return array
	 */
	protected function sent_recipient_ids() {
		$sent_ids = get_comment_meta( $this->comment->comment_ID, self::$sent_meta_key, true );
		if ( !$sent_ids )
			$sent_ids = array();

		return $sent_ids;
	}

}