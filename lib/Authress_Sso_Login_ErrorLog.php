<?php
/**
 * Contains the Authress_Sso_Login_ErrorLog class.
 *
 * @package WP-Authress
 * @since 2.0.0
 */

/**
 * Class Authress_Sso_Login_ErrorLog.
 * Handles error log CRUD actions and hooks.
 */
class Authress_Sso_Login_ErrorLog {

	/**
	 * Option name used to store the error log.
	 */
	const OPTION_NAME = 'authress_error_log';

	/**
	 * Option name used to store the error log.
	 */
	const CLEAR_LOG_NONCE = 'authress_sso_login_clear_error_log';

	/**
	 * Limit of the error logs that can be stored
	 */
	const ERROR_LOG_ENTRY_LIMIT = 30;

	/**
	 * Render the settings page.
	 *
	 * @see Authress_Sso_Login_Settings_Section::init_menu()
	 */
	public function render_settings_page() {
		include AUTHRESS_SSO_LOGIN_PLUGIN_DIR . 'templates/a0-error-log.php';
	}

	/**
	 * Get the error log.
	 *
	 * @return array
	 */
	public function get() {
		$log = get_option( self::OPTION_NAME );

		if ( empty( $log ) ) {
			$log = [];
		}

		return $log;
	}

	/**
	 * Add a new log entry, checking for previous duplicates and limit.
	 *
	 * @param array $new_entry - New log entry to add.
	 *
	 * @return bool
	 */
	public function add( array $new_entry ) {
		$log = $this->get();

		// Prepare the last error log entry to compare with the new one.
		$last_entry = null;
		if ( ! empty( $log ) ) {
			// Get the last error logged.
			$last_entry = $log[0];

			// Remove date and count fields so it can be compared with the new error.
			$last_entry = array_diff_key( $last_entry, array_flip( [ 'date', 'count' ] ) );
		}

		if ( wp_json_encode( $last_entry ) === wp_json_encode( $new_entry ) ) {
			// New error and last error are the same so set the current time and increment the counter.
			$log[0]['date']  = time();
			$log[0]['count'] = isset( $log[0]['count'] ) ? intval( $log[0]['count'] ) + 1 : 2;
		} else {
			// New error is not a repeat to set required fields.
			$new_entry['date']  = time();
			$new_entry['count'] = 1;
			array_unshift( $log, $new_entry );
		}

		return $this->update( $log );
	}

	/**
	 * Clear out the error log.
	 *
	 * @return bool
	 */
	public function clear() {
		return update_option( self::OPTION_NAME, [] );
	}

	/**
	 * Delete the error log option.
	 *
	 * @return bool
	 */
	public function delete() {
		return delete_option( self::OPTION_NAME );
	}

	/**
	 * Update the error log with an array and enforcing the length limit.
	 *
	 * @param array $log - Log array to update.
	 *
	 * @return bool
	 */
	private function update( array $log ) {
		if ( count( $log ) > self::ERROR_LOG_ENTRY_LIMIT ) {
			array_pop( $log );
		}
		return update_option( self::OPTION_NAME, $log );
	}

	/**
	 * Create a row in the error log.
	 *
	 * @param string $section - Portion of the codebase that generated the error.
	 * @param mixed  $error - Error message string or discoverable error type.
	 *
	 * @return bool
	 */
	public static function insert_error( $section, $error ) {

		$new_entry = [
			'section' => $section,
			'code'    => 'unknown_code',
			'message' => __( 'Unknown error message', 'wp-authress' ),
		];

		if ( $error instanceof WP_Error ) {
			$new_entry['code']    = $error->get_error_code();
			$new_entry['message'] = $error->get_error_message();
		} elseif ( $error instanceof Exception ) {
			$new_entry['code']    = $error->getCode();
			$new_entry['message'] = $error->getMessage();
		} elseif ( is_array( $error ) && ! empty( $error['response'] ) ) {
			if ( ! empty( $error['response']['code'] ) ) {
				$new_entry['code'] = sanitize_text_field( $error['response']['code'] );
			}
			if ( ! empty( $error['response']['message'] ) ) {
				$new_entry['message'] = sanitize_text_field( $error['response']['message'] );
			}
		} else {
			$new_entry['message'] = is_object( $error ) || is_array( $error ) ? wp_json_encode( $error ) : $error;
		}

		return ( new self() )->add( $new_entry );
	}
}
