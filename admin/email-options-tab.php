<?php

/**
 * Email template options tab
 * @since 1.0.0
 */
class Prompt_Admin_Email_Options_Tab extends Prompt_Admin_Options_Tab {

	/** @var Prompt_Stylify */
	protected $stylify;

	/**
	 * @since 1.0.0
	 * @param bool|string $options
	 * @param null        $overridden_options
	 */
	public function __construct( $options, $overridden_options = null ) {
		parent::__construct( $options, $overridden_options );
		$this->stylify = new Prompt_Stylify( Prompt_Core::$options->get( 'site_styles' ) );
	}

	/**
	 * @since 1.0.0
	 * @return string
	 */
	public function name() {
		return __( 'Your Template', 'Postmatic' );
	}

	/**
	 * @since 1.0.0
	 */
	public function form_handler() {

		if ( !empty( $_POST['stylify_button'] ) ) {
			$status = $this->stylify->refresh();
			$message = is_wp_error( $status ) ? $status->get_error_message() : __( 'Colors updated.', 'Postmatic' );
			$class = is_wp_error( $status ) ? 'error' : 'updated';
			Prompt_Core::$options->set( 'site_styles', $this->stylify->get_styles() );
			$this->add_notice( $message, $class );
			return;
		}

		if ( !empty( $_POST['reset_site_styles_button'] ) ) {
			Prompt_Core::$options->set( 'site_styles', array() );
			$this->stylify = new Prompt_Stylify( array() );
			$this->add_notice( __( 'Colors set to defaults.', 'Postmatic' ) );
			return;
		}

		if ( !empty( $_POST['send_test_email_button'] ) ) {

			$to_address = sanitize_email( $_POST['test_email_address'] );

			if ( !is_email( $to_address ) ) {
				$this->add_notice(
					__( 'Test email was <strong>not sent</strong> to an invalid address.', 'Postmatic' ),
					'error'
				);
				return;
			}

			$html_template = new Prompt_Template( 'test-email.php' );

			$footnote = __(
				'This is a test email sent by Postmatic. It is solely for demonstrating the Postmatic template and is not replyable. Also, that is not latin. <a href="https://en.wikipedia.org/wiki/Lorem_ipsum">It is Lorem ipsum</a>.',
				'Postmatic'
			);

			$batch = new Prompt_Email_Batch( array(
				'subject' => __( 'This is a test email. By Postmatic.', 'Postmatic' ),
				'html_content' => $html_template->render(),
				'message_type' => Prompt_Enum_Message_Types::ADMIN,
				'footnote_html' => $footnote,
				'footnote_text' => $footnote,
			) );
			$batch->add_individual_message_values( array( 'to_address' => $to_address ) );

			if ( !is_wp_error( Prompt_Factory::make_mailer( $batch )->send() ) ) {
				$this->add_notice( __( 'Test email <strong>sent</strong>.', 'Postmatic' ) );
				return;
			}

		}

		parent::form_handler();
	}

	/**
	 * @since 1.0.0
	 * @return string
	 */
	public function render() {

		$introduction = html(
			'div class="intro-text"',
			html( 'h2', __( 'Customize Your Postmatic Template', 'Postmatic' ) ),
			html( 'p',
				__( 'Since we\'ll be sending via email the focus should be on <em>your content</em>. That\'s why we keep things simple. Configure you colors, header and footer. Postmatic will handle what goes in between.',
					'Postmatic'
				)
			)
		);

		$email_header_image = new Prompt_Attachment_Image( $this->options->get( 'email_header_image' ) );

		$rows = array();

		$style_reset_html = '';
		if ( $this->stylify->get_styles() ) {
			$style_reset_html = html(
				'input class="button" type="submit" name="reset_site_styles_button"',
				array( 'value' => __( 'Use defaults', 'Postmatic' ) )
			);
		}

		$rows[] = html(
			'tr class="stylify"',
			html( 'th scope="row"',
				__( 'Color palette detection', 'Postmatic' ),
				'<br/>',
				html( 'small',
					__(
						'Want the Postmatic template to use your typography and colors? Do so with a single click. We\'ll analyze the active theme and make your email template follow suit.',
						'Postmatic'
					)
				)
			),
			html(
				'td',
				html( 'span class="site-color"',
					array( 'style' => 'background-color: ' . $this->stylify->get_value( 'a', 'color', '#000' ) )
				),
				html( 'span class="site-color"',
					array( 'style' => 'background-color: ' . $this->stylify->get_value( 'h1', 'color', '#000' ) )
				),
				html( 'span class="site-color"',
					array( 'style' => 'background-color: ' . $this->stylify->get_value( 'h2', 'color', '#000' ) )
				),
				html( 'span class="site-color"',
					array( 'style' => 'background-color: ' . $this->stylify->get_value( 'h3', 'color', '#000' ) )
				),
				html( 'span class="site-color"',
					array( 'style' => 'background-color: ' . $this->stylify->get_value( 'h4', 'color', '#000' ) )
				),
				html( 'div',
					html(
						'input class="button" type="submit" name="stylify_button"',
						array( 'value' => __( 'Refresh', 'Postmatic' ) )
					),
					$style_reset_html
				)
			)
		);

		if ( Prompt_Enum_Email_Transports::API == Prompt_Core::$options->get( 'email_transport' ) ) {

			$rows[] = $this->row_wrap(
				__( 'Email header type', 'Postmatic' ),
				$this->input(
					array(
						'type' => 'radio',
						'name' => 'email_header_type',
						'choices' => array(
							Prompt_Enum_Email_Header_Types::IMAGE => __( 'Image', 'Postmatic' ),
							Prompt_Enum_Email_Header_Types::TEXT => __( 'Text', 'Postmatic' ),
						),
					),
					$this->options->get()
				)
			);

			$rows[] = html(
				'tr class="email-header-image"',
				html( 'th scope="row"',
					__( 'Email header image', 'Postmatic' ),
					'<br/>',
					html( 'small',
						__(
							'Choose a header image to be used when sending new posts, digests, letters, invitations, and subscription confirmations. Will be displayed at half the size of your uploaded image to support retina displays. The ideal width to fill the full header area is 1440px wide.',
							'Postmatic'
						)
					)
				),
				html(
					'td',
					html(
						'img',
						array(
							'src' => $email_header_image->url(),
							'width' => $email_header_image->width() / 2,
							'height' => $email_header_image->height() / 2,
							'class' => 'alignleft',
						)
					),
					html(
						'div class="uploader"',
						$this->input(
							array( 'name' => 'email_header_image', 'type' => 'hidden' ),
							$this->options->get()
						),
						html(
							'input class="button" type="button" name="email_header_image_button"',
							array( 'value' => __( 'Change', 'Postmatic' ) )
						)
					)
				)
			);
		}

		$rows[] = html(
			'tr class="email-header-text"',
			html( 'th scope="row"', __( 'Email header text', 'Postmatic' ),
			'<br/>',
					html( 'small',
						__(
							'This text will show next to your site icon in simpler transactional emails such as comment notifications.',
							'Postmatic'
						)
				)
			),
			html(
				'td',
				$this->input(
					array( 'name' => 'email_header_text', 'type' => 'text', 'extra' => 'class=last-submit' ),
					$this->options->get()
				)
			)
		);

		if ( Prompt_Enum_Email_Transports::API == Prompt_Core::$options->get( 'email_transport' ) ) {

			$rows[] = html(
				'tr class="site-icon"',
				html( 'th scope="row"',
					__( 'Site icon', 'Postmatic' ),
					'<br/>',
					html( 'small',
						__(
							'This is based on your site\'s favicon, and used in comment notifications in place of the header image.',
							'Postmatic'
						)
					)
				),
				html(
					'td',
					html(
						'img',
						array(
							'src' => Prompt_Site_Icon::url(),
							'width' => 32,
							'height' => 32,
							'class' => 'alignleft',
						)
					),
					html(
						'div',
						html(
							'a',
							array( 'href' => admin_url( 'customize.php?autofocus[control]=site_icon' ) ),
							__( 'Change in the customizer', 'Postmatic' )
						)
					)
				)
			);

			$rows[] = $this->row_wrap(
				__( 'Email footer type', 'Postmatic' ),
				$this->input(
					array(
						'type' => 'radio',
						'name' => 'email_footer_type',
						'choices' => array(
							Prompt_Enum_Email_Footer_Types::WIDGETS => __( 'Widgets', 'Postmatic' ),
							Prompt_Enum_Email_Header_Types::TEXT => __( 'Text', 'Postmatic' )
						),
					),
					$this->options->get()
				)
			);

			$rows[] = html(
				'tr class="email-footer-widgets"',
				html( 'th scope="row"', __( 'Footer Widgets', 'Postmatic' ) ),
				html(
					'td',
					__( 'You can define widgets for your footer at ', 'Postmatic' ),
					html(
						'a',
						array( 'href' => admin_url( 'widgets.php' ) ),
						__( 'Appearance > Widgets', 'Postmatic' )
					)
				)
			);

			$rows[] = html(
				'tr class="email-footer-credit"',
				html( 'th scope="row"', __( 'Share the love?', 'Postmatic' ) ),
				html(
					'td',
					$this->input(
						array(
							'name' => 'email_footer_credit',
							'type' => 'checkbox',
							'desc' => __( 'Include "Delivered by Postmatic" in the footer area. We appreciate it!', 'Postmatic' ),
							'extra' => 'class=last-submit',
						),
						$this->options->get()
					)
				)
			);
		}

		$rows[] = html(
			'tr class="email-footer-text"',
			html( 'th scope="row"', __( 'Email footer text', 'Postmatic' ) ),
			html(
				'td',
				$this->input(
					array( 'name' => 'email_footer_text', 'type' => 'text', 'extra' => 'class=last-submit' ),
					$this->options->get()
				)
			)
		);

		$rows[] = html(
			'tr',
			html( 'th scope="row"', __( 'Send a test email to', 'Postmatic' ) ),
			html(
				'td',
				$this->input(
					array(
						'type' => 'text',
						'name' => 'test_email_address',
						'value' => wp_get_current_user()->user_email,
						'extra' => 'class=no-submit',
					),
					$_POST
				),
				html(
					'input class="button" type="submit" name="send_test_email_button"',
					array( 'value' => __( 'Send', 'Postmatic' ) )
				)
			)
		);

		ob_start();
		wp_editor( $this->options->get( 'subscribed_introduction' ), 'subscribed_introduction' );
		$subscriber_welcome_editor = ob_get_clean();

		$subscriber_welcome_content = html( 'div id="subscriber-welcome-message"',
			html( 'h3', __( 'Custom welcome message', 'Postmatic' ) ),
			html( 'p', __( 'When someone sucessfully subscribes to your site we\'ll shoot back a confirmation note. Use this as a place to say thanks, or even offer an incentive.', 'Postmatic' ) ),
			$subscriber_welcome_editor
		);

		$content = $this->table_wrap( implode( '', $rows ) ) . $subscriber_welcome_content;

		return
			$introduction .
			$this->form_wrap( $content ) . $this->footer();
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

		$valid_data = $this->validate_checkbox_fields( $new_data, $old_data, array( 'email_footer_credit' ) );

		$header_type_reflect = new ReflectionClass( 'Prompt_Enum_Email_Header_Types' );
		$header_types = array_values( $header_type_reflect->getConstants() );

		if ( isset( $new_data['email_header_type'] ) and in_array( $new_data['email_header_type'], $header_types ) ) {
			$valid_data['email_header_type'] = $new_data['email_header_type'];
		}

		if ( isset( $new_data['email_header_text'] ) ) {
			$valid_data['email_header_text'] = sanitize_text_field( $new_data['email_header_text'] );
		}

		if ( isset( $new_data['email_header_image'] ) ) {
			$valid_data['email_header_image'] = absint( $new_data['email_header_image'] );
		}

		$footer_type_reflect = new ReflectionClass( 'Prompt_Enum_Email_Footer_Types' );
		$footer_types = array_values( $footer_type_reflect->getConstants() );

		if ( isset( $new_data['email_footer_type'] ) and in_array( $new_data['email_footer_type'], $footer_types ) ) {
			$valid_data['email_footer_type'] = $new_data['email_footer_type'];
		}

		if ( isset( $new_data['email_footer_text'] ) ) {
			$valid_data['email_footer_text'] = sanitize_text_field( $new_data['email_footer_text'] );
		}

		if ( isset( $new_data['subscribed_introduction'] ) ) {
			$valid_data['subscribed_introduction'] = stripslashes(
				wp_kses_post( $new_data['subscribed_introduction'] )
			);
		}

		return $valid_data;
	}

	/**
	 * @since 1.0.0
	 * @return string
	 */
	protected function footer() {

		if ( Prompt_Enum_Email_Transports::LOCAL != $this->options->get( 'email_transport' ) )
			return '';

		$footer_template = new Prompt_Template( 'email-options-tab-footer.php' );

		$data = array(
			'upgrade_url' => Prompt_Enum_Urls::PREMIUM,
			'image_url' => path_join( Prompt_Core::$url_path, 'media/screenshots.jpg' ),
		);

		return $footer_template->render( $data );
	}

}
