<?php

/**
 * Manage sending notifications for a comment.
 *
 * @since 2.0.0
 *
 */
class Prompt_Comment_Mailer extends Prompt_Mailer {
	protected static $outbound_message_batch_ids_meta_key = 'prompt_outbound_message_batch_ids';

	/** @var Prompt_Comment_Email_Batch */
	protected $batch;
	/** @var  object */
	protected $comment;
	/** @var  array */
	protected $unsent_ids;

	/**
	 * @since 2.0.0
	 *
	 * @param Prompt_Comment_Email_Batch $batch
	 * @param Prompt_Interface_Http_Client $client
	 */
	public function __construct(
		Prompt_Comment_Email_Batch $batch,
		Prompt_Interface_Http_Client $client = null
	) {
		parent::__construct( $batch, $client );
		$this->comment = $batch->get_comment();
	}

	/**
	 * Add idempotent checks, flood control and batch recording to the parent send method.
	 *
	 * @since 2.0.0
	 */
	public function send() {

		$this->batch->lock_for_sending();

		$result = parent::send();

		if ( $result and ! is_wp_error( $result ) ) {
			$this->record_successful_outbound_message_batch( $result );
		}

		return $result;
	}

	/**
	 * Schedule a retry if a temporary failure has occurred.
	 *
	 * @since 2.0.0
	 *
	 * @param array $response
	 * @return bool Whether a retry has been rescheduled.
	 */
	protected function reschedule( $response ) {

		$rescheduler = Prompt_Factory::make_rescheduler( $response, $this->retry_wait_seconds );

		if ( $rescheduler->found_temporary_error() ) {

			$this->batch->clear_for_retry();

			$rescheduler->reschedule(
				'prompt/comment_mailing/send_notifications',
				array( $this->comment->comment_ID, 'rescheduled', null )
			);
			return true;
		}

		return false;
	}

	/**
	 *
	 * @since 2.0.0
	 *
	 * @param object $data
	 * @return $this
	 */
	protected function record_successful_outbound_message_batch( $data ) {

		if ( empty( $data->id ) ) {
			Prompt_Logging::add_error(
				Prompt_Enum_Error_Codes::OUTBOUND,
				__( 'Got an unrecognized outbound message batch response.', 'Postmatic' ),
				array( 'result' => $data, 'comment_id' => $this->comment->comment_ID )
			);
			return $this;
		}

		$sent_ids = get_comment_meta( $this->comment->comment_ID, self::$outbound_message_batch_ids_meta_key, true );
		$sent_ids = $sent_ids ? $sent_ids : array();
		$sent_ids[] = $data->id;
		update_comment_meta( $this->comment->comment_ID, self::$outbound_message_batch_ids_meta_key, $sent_ids );

		return $this;
	}
}