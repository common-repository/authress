<?php

class Authress_Sso_Login_Lock {

	const LOCK_GLOBAL_JS_VAR_NAME = 'wpAuthressLockGlobal';

	protected $wp_options;

	/**
	 * Authress_Sso_Login_Lock_Options constructor.
	 *
	 * @param array                 $extended_settings Argument in renderAuthressForm(), used by shortcode and widget.
	 * @param null|Authress_Sso_Login_Options $opts Authress_Sso_Login_Options instance.
	 */
	public function __construct( $extended_settings = [], $opts = null ) {
		$this->wp_options = ! empty( $opts ) ? $opts : Authress_Sso_Login_Options::Instance();
	}

	/**
	 * Render the Lock form with saved and passed options.
	 *
	 * @param bool  $canShowLegacyLogin - Is the legacy login form allowed? Only on wp-login.php.
	 * @param array $specialSettings - Additional settings from widget or shortcode.
	 */
	public static function render( $canShowLegacyLogin = true, $specialSettings = [] ) {
		if (is_user_logged_in() && ! isset($_REQUEST['force'])) {
			return;
		}

		// if ( $canShowLegacyLogin && authress_show_user_wordpress_login_form() ) {
		// 	add_action( 'login_footer', [ 'Authress_Sso_Login_Lock', 'render_back_to_lock' ] );
		// 	return;
		// }

		wp_enqueue_script('authress_sso_login_login_sdk', AUTHRESS_SSO_LOGIN_PLUGIN_JS_URL . 'authress-login-sdk.min.js', [], AUTHRESS_SSO_LOGIN_VERSION, false);
		// wp_enqueue_script('authress_sso_login_login_auto_load', AUTHRESS_SSO_LOGIN_PLUGIN_JS_URL . 'login.js', [ 'authress_sso_login_login_sdk' ], AUTHRESS_SSO_LOGIN_VERSION);
		$login_tpl = AUTHRESS_SSO_LOGIN_PLUGIN_DIR . 'templates/authress-login-form.php';

		$authress_options = Authress_Sso_Login_Options::Instance();
		$options = [
			'custom_domain' => $authress_options->get('customDomain'),
			'application_id' => $authress_options->get('applicationId')
		];
		$login_tpl = apply_filters( 'authress::user_login_template::html::formatter', $login_tpl, $options);
		require $login_tpl;
	}
}
