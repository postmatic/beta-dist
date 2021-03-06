var prompt_text_metabox_env;

(function( $ ) {

	$( document ).ready( init_text_metabox );

	function init_text_metabox() {
		var $pre = $( 'pre.prompt-custom-text' ),
			$customize_button = $( 'input.prompt-customize-text' ).on( 'click', customize ).hide(),
			$textarea;

		if ( !prompt_text_metabox_env.sent ) {

			$( document ).on( 'heartbeat-tick', update_preview );
			$customize_button.show();

		}

		function update_preview( e, data ) {

			if ( !data.hasOwnProperty( 'wp_autosave' ) || !data.hasOwnProperty( 'prompt_text_version' ) ) {
				return;
			}

			$pre.text( data.prompt_text_version );
		}

		function customize( e ) {
			$textarea = $( '<textarea class="prompt-custom-text"></textarea>' )
				.attr( 'name', prompt_text_metabox_env.custom_text_name )
				.text( $pre.text() );

			$pre.replaceWith( $textarea );

			$textarea.focus();

			$customize_button.hide();
		}
	}

}( jQuery ) );
