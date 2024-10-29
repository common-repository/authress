<?php

class Authress_Sso_Login_Options {

	/**
	 * Name used in options table option_name column.
	 *
	 * @var string
	 */
	protected $configurationDatabaseName = 'authress_settings';

	/**
	 * Current array of options stored in memory.
	 *
	 * @var null|array
	 */
	private $cached_opts = null;

	/**
	 * Array of options overridden by constants.
	 *
	 * @var array
	 */
	protected $constant_opts = [];

	/**
	 * @var Authress_Sso_Login_Options
	 */
	protected static $static_singleton_instance = null;

	/**
	 * Authress_Sso_Login_Options constructor.
	 * Finds and stores all constant-defined settings values.
	 */
	public function __construct() {
		$option_keys = $this->get_defaults( true );
		foreach ( $option_keys as $key ) {
			$setting_const = $this->get_constant_name( $key );
			if ( defined( $setting_const ) ) {
				$this->constant_opts[ $key ] = constant( $setting_const );
			}
		}
	}

	/**
	 * @return Authress_Sso_Login_Options
	 */
	public static function Instance() {
		if ( null === self::$static_singleton_instance ) {
			self::$static_singleton_instance = new self();
		}
		return self::$static_singleton_instance;
	}

	/**
	 * Takes an option key and creates the constant name to look for.
	 *
	 * @param string $key - Option key to transform.
	 *
	 * @return string
	 */
	public function get_constant_name( $key ) {
		$constant_prefix = 'AUTHRESS_ENV_';
		return $constant_prefix . strtoupper( $key );
	}

	/**
	 * Does a certain option pull from a constant?
	 *
	 * @param string $key - Option key to check.
	 *
	 * @return boolean
	 */
	public function has_constant_val( $key ) {
		return isset( $this->constant_opts[ $key ] );
	}

	/**
	 * Get the value of an overriding constant if one is set, return null if not.
	 *
	 * @param string $key - Option key to look for.
	 *
	 * @return string|null
	 */
	public function get_constant_val( $key ) {
		return $this->has_constant_val( $key ) ? constant( $this->get_constant_name( $key ) ) : null;
	}

	/**
	 * Get all the keys for constant-overridden settings.
	 *
	 * @return array
	 */
	public function get_all_constant_keys() {
		return array_keys( $this->constant_opts );
	}

	/**
	 * Get the option_name for the settings array.
	 *
	 * @return string
	 */
	public function getConfigurationDatabaseName() {
		return $this->configurationDatabaseName;
	}

	/**
	 * Return options from memory, database, defaults, or constants.
	 *
	 * @return array
	 */
	public function get_options() {
		if ( empty( $this->cached_opt ) ) {
			$options = get_option( $this->configurationDatabaseName, [] );
			// Brand new install, no saved options so get all defaults.
			if ( empty( $options ) || ! is_array( $options ) ) {
				$options = $this->defaults();
			}

			// Check for constant overrides and replace.
			if ( ! empty( $this->constant_opts ) ) {
				$options = array_replace_recursive( $options, $this->constant_opts );
			}
			$this->cached_opt = $options;
		}
		return $this->cached_opt;
	}

	/**
	 * Return a filtered settings value or default.
	 *
	 * @param string $key - Settings key to get.
	 * @param mixed  $default - Default value to return if not found.
	 *
	 * @return mixed
	 *
	 */
	public function get( $key, $default = null ) {
		$options = $this->get_options();
		$value   = isset( $options[ $key ] ) ? $options[ $key ] : $default;
		return $value;
	}

	/**
	 * Update a setting if not already stored in a constant.
	 * This method will fail silently if the option is already set in a constant.
	 *
	 * @param string $key - Option key name to update.
	 * @param mixed  $value - Value to update with.
	 * @param bool   $should_update - Flag to update DB options array with value stored in memory.
	 *
	 * @return bool
	 */
	public function set( $key, $value, $should_update = true ) {

		// Cannot set a setting that is being overridden by a constant.
		if ( $this->has_constant_val( $key ) ) {
			return false;
		}

		$options         = $this->get_options();
		$options[ $key ] = $value;
		$this->cached_opt     = $options;

		// No database update so process completed successfully.
		if ( ! $should_update ) {
			return true;
		}

		return $this->update_all();
	}

	/**
	 * Remove a setting from the options array in memory.
	 *
	 * @param string $key - Option key name to remove.
	 */
	public function remove( $key ) {

		// Cannot remove a setting that is being overridden by a constant.
		if ( $this->has_constant_val( $key ) ) {
			return;
		}

		$options = $this->get_options();
		unset( $options[ $key ] );
		$this->cached_opt = $options;
	}

	/**
	 * Save the options array as it exists in memory.
	 *
	 * @return bool
	 */
	public function update_all() {
		$options = $this->get_options();

		foreach ( $this->get_all_constant_keys() as $key ) {
			unset( $options[ $key ] );
		}
		return update_option( $this->configurationDatabaseName, $options );
	}

	/**
	 * Save the options array for the first time.
	 */
	public function save() {
		$this->get_options();
		$this->update_all();
	}

	/**
	 * Delete the options array.
	 *
	 * @return bool
	 */
	public function delete() {
		return delete_option( $this->configurationDatabaseName );
	}

	/**
	 * Reset options to defaults.
	 */
	public function reset() {
		$this->cached_opt = null;
		$this->delete();
		$this->save();
	}

	/**
	 * Return default options as key => value or just keys.
	 *
	 * @param bool $keys_only - Only return the array keys for the default options.
	 *
	 * @return array
	 */
	public function get_defaults( $keys_only = false ) {
		$default_opts = $this->defaults();
		return $keys_only ? array_keys( $default_opts ) : $default_opts;
	}

	public function get_default( $key ) {
		$defaults = $this->defaults();
		return $defaults[ $key ];
	}

	/**
	 * Get web_origin settings for new Clients
	 *
	 * @return array
	 */
	public function get_web_origins() {
		$home_url_parsed = wp_parse_url( home_url() );
		$home_url_origin = ! empty( $home_url_parsed['path'] )
			? str_replace( $home_url_parsed['path'], '', home_url() )
			: home_url();

		$site_url_parsed = wp_parse_url( site_url() );
		$site_url_origin = ! empty( $site_url_parsed['path'] )
			? str_replace( $site_url_parsed['path'], '', site_url() )
			: site_url();

		return $home_url_origin === $site_url_origin
			? [ $home_url_origin ]
			: [ $home_url_origin, $site_url_origin ];
	}

	/**
	 * Get the main site URL for Authress processing
	 *
	 * @param string|null $protocol - forced URL protocol, use default if empty
	 *
	 * @return string
	 */
	public function get_authress_sso_login_url( $protocol = null ) {
		if ( is_null( $protocol ) && $this->get( 'force_https_callback' ) ) {
			$protocol = 'https';
		}
		$site_url = site_url( 'index.php', $protocol );
		return add_query_arg( 'authress', 1, $site_url );
	}

	/**
	 * Get the authentication organization.
	 *
	 * @return string
	 */
	public function get_auth_organization() {
		return $this->get( 'organization', '' );
	}

	/**
	 * Get lock_connections as an array of strings
	 *
	 * @return array
	 */
	public function get_lock_connections() {
		$connections = $this->get( 'lock_connections' );
		$connections = empty( $connections ) ? [] : explode( ',', $connections );
		return array_map( 'trim', $connections );
	}

	/**
	 * Add a new connection to the lock_connections setting
	 *
	 * @param string $connection - connection name to add
	 */
	public function add_lock_connection( $connection ) {
		$connections = $this->get_lock_connections();

		// Add if it doesn't exist already
		if ( ! array_key_exists( $connection, $connections ) ) {
			$connections[] = $connection;
			$connections   = implode( ',', $connections );
			$this->set( 'lock_connections', $connections );
		}
	}

	/**
	 * Check if provided strategy is allowed to skip email verification.
	 * Useful for Enterprise strategies that do not provide a email_verified profile value.
	 *
	 * @param string $strategy - Strategy to check against saved setting.
	 *
	 * @return bool
	 *
	 * @since 3.8.0
	 */
	public function strategy_skips_verified_email( $strategy ) {
		$skip_strategies = trim( $this->get( 'skip_strategies' ) );

		// No strategies to skip.
		if ( empty( $skip_strategies ) ) {
			return false;
		}

		$skip_strategies = explode( ',', $skip_strategies );
		$skip_strategies = array_map( 'trim', $skip_strategies );
		return in_array( $strategy, $skip_strategies, true);
	}

	/**
	 * Default settings when plugin is installed or reset
	 *
	 * @return array
	 */
	protected function defaults() {
		return [
			// System
			'version' => 1,
			'applicationId' => '',
			'customDomain' => '',
			'accessKey' => '',
			'default_login_redirection' => home_url(),
			'authress_server_domain' => 'authress.io',

            'last_step'                 => 1,
            'db_connection_name'        => '',

            // Basic
            'domain'                    => '',
            'client_secret'             => '',
            'organization'              => '',
            'cache_expiration'          => 1440,
            'wordpress_login_enabled'   => 'link',
            'wle_code'                  => '',

            // Features
            // AutoLogin means automatically redirect the user to a login location, but we actually don't want that, we want to check if the user logged
			'auto_login'                => true,
            'auto_login_method'         => '',
            'singlelogout'              => true,
            'override_wp_avatars'       => true,

            // Embedded
            'passwordless_enabled'      => false,
            'icon_url'                  => '',
            'form_title'                => 'SSO Login',
            'gravatar'                  => true,
            'username_style'            => '',
            'primary_color'             => '',
            'extra_conf'                => '',
            'custom_cdn_url'            => false,
            'lock_connections'          => '',

            // Advanced
            'requires_verified_email'   => true,
            'skip_strategies'           => '',
            'remember_users_session'    => true,
            'default_login_redirection' => home_url(),
			'auto_provisioning'         => true,
            'valid_proxy_ip'            => ''

		];
	}
}
