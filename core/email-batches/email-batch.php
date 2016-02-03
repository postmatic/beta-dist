<?php

/**
 * Represents an email template and the values needed to render it for individual recipients.
 *
 * @since 2.0.0
 */
class Prompt_Email_Batch {

	/**
	 * @var array
	 */
	protected static $template_fields = array(
		'to_address',
		'to_name',
		'from_address',
		'from_name',
		'subject',
		'html_content',
		'text_content',
		'message_type',
	);

	/**
	 * @var array
	 */
	protected $batch_message_template;
	/**
	 * @var array
	 */
	protected $default_values;
	/**
	 * @var array
	 */
	protected $individual_message_values;

	/**
	 *
	 * @since 2.0.0
	 *
	 * @param string $key
	 * @param mixed $value
	 * @return bool
	 */
	protected static function is_individual_message_value( $key, $value ) {

		if ( is_array( $value ) ) {
			// It's a macro
			return true;
		}

		if ( in_array( $key, array( 'to_name', 'to_address' ) ) ) {
			return true;
		}

		return false;
	}

	/**
	 *
	 * @since 2.0.0
	 *
	 * @param $values
	 * @return array
	 */
	protected static function select_individual_message_values( $values ) {

		$individual_message_values = array();

		foreach ( $values as $key => $value ) {
			if ( self::is_individual_message_value( $key, $value ) ) {
				$individual_message_values[$key] = $value;
			}
		}

		return $individual_message_values;
	}

	/**
	 * Shortcut to make a batch for a single message with all the required values.
	 *
	 * Everything but to_address, to_name, and macro arrays is taken as a template field.
	 *
	 * @since 2.0.0
	 *
	 * @param array $values Key/value pairs for a single email.
	 * @return Prompt_Email_Batch
	 */
	static public function make_for_single_recipient( $values ) {

		$individual_message_values = self::select_individual_message_values( $values );

		$template = array_diff_key( $values, $individual_message_values );

		if ( isset( $individual_message_values['reply_to'] ) ) {
			$template['reply_to'] = '{{{reply_to}}}';
		}

		return new Prompt_Email_Batch( $template, array( $individual_message_values ) );
	}

	/**
	 * Return a full name-address made from the two parts.
	 *
	 * A name "Foo Bar" and address "foo@bar.com" result in "Foo Bar <foo@bar.com>".
	 *
	 * If no address is included, the default site address is returned.
	 *
	 * If no name is included, the plain email address is returned.
	 *
	 * @param string $address
	 * @param string $name
	 * @return string name address
	 */
	static public function name_address( $address = null, $name = '' ) {
		if ( !$address )
			return self::default_from_email();

		if ( empty( $name ) )
			return $address;

		return $name . ' <' . $address . '>';
	}

	/**
	 * Get the address part of a name-address string.
	 *
	 * "Foo Bar <foo@bar.com>" will return "foo@bar.com".
	 *
	 * If there is no angle-bracketed address, the passed in address is returned unchanged.
	 *
	 * @param string $name_address
	 * @return string
	 */
	static public function address( $name_address ) {
		$address = $name_address;

		if ( preg_match( '/([^<]+) <([^>]+)>/', $name_address, $matches ) )
			$address = $matches[2];

		return $address;
	}

	/**
	 * Get the name part of a name-address string.
	 *
	 * "Foo Bar <foo@bar.com>" will return "Foo Bar".
	 *
	 * If there is no angle-bracketed address, an empty string is returned.
	 *
	 * @param string $name_address
	 * @return string
	 */
	static public function name( $name_address ) {
		$name = '';

		if ( preg_match( '/([^<]+) <([^>]+)>/', $name_address, $matches ) )
			$name = $matches[1];

		return $name;
	}

	/**
	 * Package data into a trackable address structure.
	 *
	 * @since 2.0.0
	 *
	 * @param array|object $data
	 * @return array
	 */
	static public function trackable_address( $data ) {
		return array( 'trackable-address' => $data );
	}

	/**
	 * @return string the default from address used for Prompt emails.
	 */
	static public function default_from_email() {
		/**
		 * Filter default from email.
		 *
		 * @param string $email
		 */
		return apply_filters( 'prompt/default_from_email', 'hello@email.gopostmatic.com' );
	}

	/**
	 * Construct an email batch
	 *
	 * @since 2.0.0
	 *
	 * @param array $batch_message_template {
	 *      Handlebars template fields to use for every email in the batch
	 * @var string $html_content Required. Email message HTML content template
	 * @var string $message_type Required. See Prompt_Enum_Message_Types.
	 * @var string $text_content Optional. Email message text content template
	 * @var string $to_address Optional, but required as an individual message value. Default '{{{to_address}}}'
	 * @var string $to_name Optional, default '{{{to_name}}}'
	 * @var string $subject Optional, default 'This is a test email. By Postmatic.'
	 * @var string $from_name Optional, default blogname.
	 * @var string $from_address Optional, default 'hello@email.gopostmatic.com'
	 * }
	 * @param array $individual_message_values {
	 *      Array of key/value pairs to create an email from the template, may contain your own custom values too.
	 * @var string $to_address Required.
	 * @var string $to_name Optional.
	 * @var string $reply_to Optional, for trackable replies: array( 'trackable-address' => $metadata )
	 * }
	 * @param array $default_values Key/value pairs to use when a key is missing from individual message values.
	 */
	public function __construct(
		$batch_message_template = array(),
		$individual_message_values = array(),
		$default_values = array()
	) {
		$brand_type = Prompt_Core::$options->get( 'email_header_type' );

		$brand_image_id = 0;
		if ( Prompt_Enum_Email_Header_Types::IMAGE === $brand_type ) {
			$brand_image_id = Prompt_Core::$options->get( 'email_header_image' );
			$batch_message_template['is_image_header'] = true;
		}

		$brand_image = new Prompt_Attachment_Image( $brand_image_id );

		$site_icon_url = get_site_icon_url( 64 );
		if ( !$site_icon_url ) {
			$site_icon = new Prompt_Attachment_Image( Prompt_Core::$options->get( 'site_icon' ) );
			$site_icon_url = $site_icon->url();
		}

		$site_styles = new Prompt_Stylify( Prompt_Core::$options->get( 'site_styles' ) );

		ob_start();
		do_action( 'prompt/html_email/print_styles' );
		$integration_css = ob_get_clean();

		$footer_type = Prompt_Core::$options->get( 'email_footer_type' );
		$footer_text = Prompt_Core::$options->get( 'email_footer_text' );

		$is_comment = false;
		if ( isset( $batch_message_template['message_type'] ) ) {
			$batch_message_template['is_' . $batch_message_template['message_type']] = true;
			$is_comment = isset( $batch_message_template['is_' . Prompt_Enum_Message_Types::COMMENT] );
		}

		$default_template_values = array(
			'to_name' => '{{{to_name}}}',
			'to_address' => '{{{to_address}}}',
			'subject' => __( 'This is a test email. By Postmatic.', 'Postmatic' ),
			'from_name' => get_option( 'blogname' ),
			'from_address' => self::default_from_email(),
			'blogname' => get_option( 'blogname' ),
			'brand_type' => $brand_type,

			'brand_text' => Prompt_Core::$options->get( 'email_header_text' ),
			'small_brand_image' => ( $brand_image->width() < 1440 ),
			'brand_image_url' => $brand_image->url(),
			'brand_image_width' => $brand_image->width() / 2,
			'brand_image_height' => $brand_image->height() / 2,
			'site_icon_url' => $site_icon_url,
			'site_css' => $site_styles->get_css() . $integration_css,
			'footer_html' => Prompt_Enum_Email_Footer_Types::WIDGETS == $footer_type ?
				$this->footer_widgets_html( $is_comment ) :
				$footer_text,
			'footer_text' => $footer_text,
			'credit_html' => $this->credit_html(),
			'credit_text' => $this->credit_text(),
			'footnote_html' => '',
			'footnote_text' => '',
		);

		$this->batch_message_template = wp_parse_args( $batch_message_template, $default_template_values );

		$this->individual_message_values = $individual_message_values;

		$this->default_values = $default_values;
	}

	/**
	 * Add a set of message values to the batch.
	 *
	 * @since 2.0.0
	 *
	 * @param array $values
	 * @return $this
	 */
	public function add_individual_message_values( array $values ) {
		$this->individual_message_values[] = $values;
		return $this;
	}

	/**
	 * Get a hash compatible with the API.
	 *
	 * @since 2.0.0
	 *
	 * @return array
	 */
	public function to_array() {
		return array(
			'batch_message_template' => $this->get_batch_message_template(),
			'individual_message_values' => $this->get_individual_message_values(),
			'default_values' => $this->get_default_values(),
		);
	}

	/**
	 *
	 * @since 2.0.0
	 *
	 * @return array
	 */
	public function get_batch_message_template() {
		$this->finish_template();
		return $this->batch_message_template;
	}

	/**
	 *
	 * @since 2.0.0
	 *
	 * @param array $batch_message_template
	 * @return Prompt_Email_Batch
	 */
	public function set_batch_message_template( $batch_message_template ) {
		$this->batch_message_template = $batch_message_template;
		return $this;
	}

	/**
	 *
	 * @since 2.0.0
	 *
	 * @return array
	 */
	public function get_default_values() {
		return $this->default_values;
	}

	/**
	 *
	 * @since 2.0.0
	 *
	 * @param array $default_values
	 * @return Prompt_Email_Batch
	 */
	public function set_default_values( $default_values ) {
		$this->default_values = $default_values;
		return $this;
	}

	/**
	 *
	 * @since 2.0.0
	 *
	 * @return array
	 */
	public function get_individual_message_values() {
		return $this->individual_message_values;
	}

	/**
	 *
	 * @since 2.0.0
	 *
	 * @param array $individual_message_values
	 * @return $this
	 */
	public function set_individual_message_values( $individual_message_values ) {
		$this->individual_message_values = $individual_message_values;
		return $this;
	}

	/**
	 * Encode text fields and fill in missing values.
	 *
	 * @since 2.0.0
	 *
	 */
	protected function finish_template() {
		$this->fill_in_missing_content_type();
		$this->encode_template_text_fields();
	}

	/**
	 * Encode text fields as UTF-8.
	 *
	 * @since 2.0.0
	 */
	protected function encode_template_text_fields() {
		$text_fields = array_diff( self::$template_fields, array( 'html_content' ) );

		foreach ( $text_fields as $name ) {
			$this->batch_message_template[$name] = $this->to_utf8( $this->batch_message_template[$name] );
		}
	}

	/**
	 * If text is missing, set it to markdown of HTML. If HTML is missing, set it to the text.
	 *
	 * @since 2.0.0
	 *
	 */
	protected function fill_in_missing_content_type() {

		if (
			!isset( $this->batch_message_template['text_content'] ) and
			isset( $this->batch_message_template['html_content'] )
		) {
			$html = preg_replace(
				'@<(head|script|style)[^>]*?>.*?</\\1>@si',
				'',
				$this->batch_message_template['html_content']
			);

			$text = Prompt_Html_To_Markdown::convert( $html );
			$this->batch_message_template['text_content'] = strip_tags( $text );
		}

		if (
			!isset( $this->batch_message_template['html_content'] ) and
			isset( $this->batch_message_template['text_content'] )
		) {
			$this->batch_message_template['html_content'] = $this->batch_message_template['text_content'];
		}
	}

	/**
	 *
	 * @since 2.0.0
	 *
	 * @param string $content
	 * @return string
	 */
	protected function to_utf8( $content ) {
		return wp_strip_all_tags( html_entity_decode( $content, ENT_QUOTES, 'UTF-8' ) );
	}


	/**
	 *
	 * @since 2.0.0
	 *
	 * @param bool $is_comment
	 * @return string
	 */
	protected function footer_widgets_html( $is_comment ) {
		ob_start();

		if ( $is_comment ) {
			Prompt_Comment_Email_Footer_Sidebar::render();
		} else {
			Prompt_Email_Footer_Sidebar::render();
		}

		return ob_get_clean();
	}

	/**
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	protected function credit_html() {
		$html = sprintf(
			__( 'Sent from %s.', 'Postmatic' ),
			'<a href="' . get_bloginfo( 'url' ) . '">' . get_bloginfo( 'name' ) . '</a>'
		);
		if ( Prompt_Core::$options->get( 'email_footer_credit' ) ) {
			$html .= ' ' . sprintf(
				__( 'Delivered by <a href="%s">Postmatic</a>.', 'Postmatic' ),
				path_join( Prompt_Enum_Urls::HOME, '?utm_source=footer&utm_medium=email&utm_campaign=pluginfooter' )
			);
		}
		return $html;
	}

	/**
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	protected function credit_text() {
		$text = sprintf(
			__( 'Sent from %s.', 'Postmatic' ),
			get_bloginfo( 'url' )
		);
		if ( Prompt_Core::$options->get( 'email_footer_credit' ) ) {
			$text .= ' ' . __( 'Delivered by Postmatic.', 'Postmatic' );
		}
		return $text;
	}

	/**
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	protected function unsubscribe_mailto() {
		return sprintf(
			'mailto:{{{reply_to}}}?body=%s&subject=%s',
			rawurlencode( Prompt_Unsubscribe_Matcher::target() ),
			rawurlencode( __( 'Unsubscribe request', 'Postmatic' ) )
		);
	}

}