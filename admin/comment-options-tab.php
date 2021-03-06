<?php

/**
 * Comment delivery options tab
 *
 * @since 2.0.0
 *
 */

class Prompt_Admin_Comment_Options_Tab extends Prompt_Admin_Options_Tab {

	/**
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	public function name() {
		return __( 'Configure Comments', 'Postmatic' );
	}

	/**
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	public function slug() {
		return 'comment-delivery';
	}

	/**
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	public function render() {

		$table_entries = array(
			array(
				'title' => __( 'Comment form opt-in', 'Postmatic' ),
				'type' => 'checkbox',
				'name' => 'comment_opt_in_default',
				'desc' => __( 'Subscribe commenters to the conversation by default.', 'Postmatic' ) .
					html( 'p',
						__(
							'Please note this may place you in violation of European and Canadian spam laws. Be sure to do your homework.',
							'Postmatic'
						)
					),
			),
			array(
				'title' => __( 'Comment form opt-in text', 'Postmatic' ),
				'type' => 'text',
				'name' => 'comment_opt_in_text',
				'desc' => __( 'This text is displayed by the checkbox on the comment form.', 'Postmatic' ),
				'extra' => array( 'class' => 'regular-text last-submit' ),
			),
			array(
				'title' => __( 'Comment flood control', 'Postmatic' ),
				'type' => 'text',
				'name' => 'comment_flood_control_trigger_count',
				'desc' => __( 'How many comments in one hour should it take to trigger the flood control? There is a mimimum of 3.', 'Postmatic' ) .
					html( 'p',
						sprintf(
							__(
								'Postmatic automatically pauses comment notifications on posts that go viral. Setting the trigger to be 6 comments per hour is good for most sites. You can read more about it <a href="%s" target="_blank">on our support site</a>.  ',
								'Postmatic'
							),
							'http://docs.gopostmatic.com/article/143-what-happens-if-a-post-gets-a-gazillion-comments-do-i-get-a-gazillion-emails'
						)
					),
				'extra' => array( 'size' => 3 ),
			),
		);

		$this->override_entries( $table_entries );

		return $this->form_table( $table_entries );
	}

	/**
	 * Disable overridden entry UI table entries.
	 *
	 * @since 2.0.0
	 *
	 * @param array $table_entries
	 */
	protected function override_entries( &$table_entries ) {
		foreach ( $table_entries as $index => $entry ) {
			if ( isset( $this->overridden_options[$entry['name']] ) ) {
				$table_entries[$index]['extra'] = array(
					'class' => 'overridden',
					'disabled' => 'disabled',
				);
			}
		}
	}

	/**
	 *
	 * @since 2.0.0
	 *
	 * @param array $new_data
	 * @param array $old_data
	 * @return array
	 */
	function validate( $new_data, $old_data ) {

		$valid_data = $this->validate_checkbox_fields(
			$new_data,
			$old_data,
			array( 'comment_opt_in_default' )
		);

		if ( isset( $new_data['comment_opt_in_text'] ) ) {
			$valid_data['comment_opt_in_text'] = sanitize_text_field( $new_data['comment_opt_in_text'] );
		}

		$flood_trigger_count = $new_data['comment_flood_control_trigger_count'];
		$flood_trigger_count = is_numeric( $flood_trigger_count ) ? absint( $flood_trigger_count ) : 6;
		$flood_trigger_count = ( $flood_trigger_count < 3 ) ? 3 : $flood_trigger_count;
		$valid_data['comment_flood_control_trigger_count'] = $flood_trigger_count;

		return $valid_data;
	}

}