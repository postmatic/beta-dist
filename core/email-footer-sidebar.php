<?php

class Prompt_Email_Footer_Sidebar {

	const SIDEBAR_ID = 'prompt-email-footer-area';

	public static function register() {
		register_sidebar( array(
			'name' => 'Postmatic Posts Footer',
			'id' => self::SIDEBAR_ID,
			'description' => __(
				'These widgets will be included below new posts which are sent via Postmatic. Need inspiration? Try our widgets directory at http://gopostmatic.com/widgets.',
				'Postmatic'
			),
			'before_widget' => "<div class='postmatic-widget'>",
			'after_widget' => '</div>',
			'before_title' => "<h4>",
			'after_title' => '</h4>'
		) );
	}

	public static function render() {
		if ( is_active_sidebar( self::SIDEBAR_ID ) )
			dynamic_sidebar( self::SIDEBAR_ID );
	}
}


