<?php

class Authress_Sso_Login_Admin_Generic {

	const ERROR_FIELD_STYLE = 'border: 1px solid red;';

	protected $options;

	protected $configurationDatabaseName;

	/**
	 * Authress_Sso_Login_Admin_Generic constructor.
	 *
	 * @param Authress_Sso_Login_Options $options
	 */
	public function __construct( Authress_Sso_Login_Options $options ) {
		$this->options      = $options;
		$this->configurationDatabaseName = $options->getConfigurationDatabaseName();
	}

	/**
	 * Add settings section and fields for each of the settings screen
	 *
	 * @param string $section_name - name used for the settings section (usually empty).
	 * @param string $id - settings screen id.
	 * @param array  $options - array of settings fields.
	 */
	protected function init_option_section( $section_name, $id, $options ) {
		$options_name = $this->configurationDatabaseName . '_' . strtolower( $id );
		$section_id   = "authress_sso_login_{$id}_settings_section";

		add_settings_section($section_id, $section_name, null, $options_name);

		foreach ( $options as $setting ) {
			$callback = function_exists( $setting['function'] ) ? $setting['function'] : [ $this, $setting['function'] ];

			add_settings_field(
				$setting['id'],
				$setting['name'],
				$callback,
				$options_name,
				$section_id,
				[
					'label_for' => $setting['id'],
					'opt_name'  => isset( $setting['opt'] ) ? $setting['opt'] : null,
				]
			);
		}
	}

	public function input_validator( $input ) {
		return $this->basic_validation( $input );
	}

	/**
	 * Wrapper for add_settings_error to output error message on settings change failure.
	 *
	 * @param string $error - Translated error message.
	 * @param string $type - Notice type, "error" by default or "updated".
	 */
	protected function add_validation_error( $error, $type = 'error' ) {
		add_settings_error(
			$this->configurationDatabaseName,
			$this->configurationDatabaseName,
			$error,
			$type
		);
	}

	/**
	 * Output a stylized switch on the options page
	 *
	 * @param string $id - input id attribute
	 * @param string $input_name - input name attribute
	 * @param string $expand_id - id of a field that should be hidden until this switch is active
	 */
	protected function render_switch( $id, $input_name, $expand_id = '' ) {
		$value = $this->options->get( $input_name );
		$field_is_const = $this->options->has_constant_val( $input_name );
		if ($field_is_const) {
			$this->render_const_notice( $input_name );
		}
		printf(
			'<div class="a0-switch"><input type="checkbox" name="%s[%s]" id="%s" data-expand="%s" value="1" %s %s>
			<label for="%s"></label></div>',
			esc_attr( $this->configurationDatabaseName ),
			esc_attr( $input_name ),
			esc_attr( $id ),
			! empty( $expand_id ) ? esc_attr( $expand_id ) : '',
			checked( empty( $value ), false, false ),
			esc_attr($field_is_const ? 'disabled' : ''),
			esc_attr( $id )
		);
	}

	/**
	 * Output a stylized text field on the options page
	 *
	 * @param string $id - input id attribute
	 * @param string $input_name - input name attribute
	 * @param string $type - input type attribute
	 * @param string $placeholder - input placeholder
	 * @param string $style - inline CSS
	 */
	protected function render_text_field( $id, $input_name, $type = 'text', $placeholder = '', $style = '' ) {
		$value = $this->options->get( $input_name );

		// Secure fields are not output by default; validation keeps last value if a new one is not entered
		if ( 'password' === $type ) {
			$value = empty( $value ) ? '' : __( '[REDACTED]', 'wp-authress' );
			$type  = 'text';
		}
		if ( $field_is_const = $this->options->has_constant_val( $input_name ) ) {
			$this->render_const_notice( $input_name );
		}
		printf(
			'<input type="%s" name="%s[%s]" id="%s" value="%s" placeholder="%s" style="%s" %s>',
			esc_attr( $type ),
			esc_attr( $this->configurationDatabaseName ),
			esc_attr( $input_name ),
			esc_attr( $id ),
			esc_attr( $value ),
			$placeholder ? esc_attr( $placeholder ) : '',
			esc_attr($style ? $style : ''),
			esc_attr($field_is_const ? 'disabled' : '')
		);
	}

	/**
	 * Output a stylized textarea field on the options page
	 *
	 * @param string $id - input id attribute
	 * @param string $input_name - input name attribute
	 */
	protected function render_textarea_field( $id, $input_name ) {
		$value = $this->options->get( $input_name );
		if ( $field_is_const = $this->options->has_constant_val( $input_name ) ) {
			$this->render_const_notice( $input_name );
		}
		printf(
			'<textarea name="%s[%s]" id="%s" rows="%d" class="code" %s>%s</textarea>',
			esc_attr( $this->configurationDatabaseName ),
			esc_attr( $input_name ),
			esc_attr( $id ),
			4,
			$field_is_const ? 'disabled' : '',
			esc_textarea( $value )
		);
	}

	/**
	 * Output one or many radio buttons associated to the same option key.
	 *
	 * @param array            $buttons - Array of buttons to output; items can be strings or arrays with "label" and "value" keys.
	 * @param string           $id - Input ID attribute.
	 * @param string           $input_name - Option name saved to the options array.
	 * @param int|float|string $curr_value - Current option value.
	 * @param bool             $vert - True to use vertical orientation for buttons.
	 */
	protected function render_radio_buttons( array $buttons, $id, $input_name, $curr_value, $vert = false ) {
		if ( $field_is_const = $this->options->has_constant_val( $input_name ) ) {
			$this->render_const_notice( $input_name );
		}
		foreach ( $buttons as $index => $button ) {
			$id_attr = $id . '_' . $index;
			$label   = is_array( $button ) ? $button['label'] : ucfirst( $button );
			$value   = is_array( $button ) ? $button['value'] : $button;
			$desc    = isset( $button['desc'] ) ? '<p class="description">' . $button['desc'] . '</p>' : '';
			printf(
				'%s<label for="%s"><input type="radio" name="%s[%s]" id="%s" value="%s" %s %s>%s</label> %s',
				esc_attr($vert ? '<div class="a0-vert-radio">' : ''),
				esc_attr( $id_attr ),
				esc_attr( $this->configurationDatabaseName ),
				esc_attr( $input_name ),
				esc_attr( $id_attr ),
				esc_attr( $value ),
				checked( $value === $curr_value, true, false ),
				esc_attr($field_is_const ? 'disabled' : ''),
				esc_attr( $label ),
				esc_attr($vert ? $desc . '</div>' : '')
			);
		}
	}

	/**
	 * Output a field description
	 *
	 * @param string $text - description text to display
	 */
	protected function render_field_description( $text ) {
		$period = ! in_array( $text[ strlen( $text ) - 1 ], [ '.', ':' ], true ) ? '.' : '';
		// The field descriptions are already sanitized no reason to force this to happen again, and often are being passed in html as well, which should not be sanitized.
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		printf( '<div class="subelement"><span class="description">%s%s</span></div>', $text, esc_attr($period) );
	}

	/**
	 * Check if the setting is provided by a constant and indicate.
	 *
	 * @param string $input_name - Input name for the field, used as option key.
	 */
	protected function render_const_notice( $input_name ) {
		printf(
			'<p class="const-setting-notice"><span class="description">%s <code>%s</code></span></p>',
			esc_attr_e( 'Value is set in the constant ', 'wp-authress' ),
			esc_attr($this->options->get_constant_name( $input_name ))
		);
	}

	/**
	 * Output translated dashboard HTML link
	 *
	 * @param string $path - dashboard sub-section, if any
	 * @param string $name - name of location in Authress management portal
	 *
	 * @return string
	 */
	protected function get_dashboard_link( $path = '', $name = 'management portal') {
		return sprintf('<a href="https://authress.io/app/#/setup?focus=%s" target="_blank">%s</a>', $path, __( 'Authress ', 'wp-authress' ) . $name);
	}

	/**
	 * Output a docs HTML link
	 *
	 * @param string $path - docs sub-page, if any
	 * @param string $text - link text, should be translated before passing
	 *
	 * @return string
	 */
	protected function get_docs_link( $path, $text = '' ) {
		$path = '/' === $path[0] ? substr( $path, 1 ) : $path;
		$text = empty( $text ) ? __( 'here', 'wp-authress' ) : sanitize_text_field( $text );
		return sprintf( '<a href="https://authress.io/knowledge-base" target="_blank">%s</a>', $text );
	}

	/**
	 * Strict-check passed values against possible truth-y ones.
	 *
	 * @param mixed $val Value to check.
	 *
	 * @return bool
	 */
	protected function sanitize_switch_val( $val ) {
		return in_array( $val, [ 1, '1', true ], true ) ? true : false;
	}

	protected function sanitize_text_val( $val ) {
		return sanitize_text_field( trim( strval( $val ) ) );
	}
}