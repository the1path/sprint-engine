<?php
/**
 * Participant preferences and validated same-site handoffs; no account writes.
 *
 * @package SprintEngine
 */

namespace ThePath\SprintEngine\Settings;

defined( 'ABSPATH' ) || exit;

/** One independent, non-autoloaded option; old pending preferences remain disabled. */
final class ParticipantAccounts {

	const OPTION = 'sprint_engine_participant_accounts';

	/**
	 * Safe unset preferences.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'mode'             => '',
			'registration_url' => '',
			'login_url'        => '',
			'disable_native'   => null,
			'confirmation'     => '',
		);
	}

	/**
	 * Strict stored shape, without trusting destinations indefinitely.
	 *
	 * @return array
	 */
	public static function get() {
		$value = get_option( self::OPTION, self::defaults() );
		if ( is_array( $value ) && ! array_key_exists( 'confirmation', $value ) ) {
			$value['confirmation'] = ''; // SE-031.2 never confirmed live account creation.
		}
		return self::valid_shape( $value ) ? $value : self::defaults();
	}

	/**
	 * Validate the entire submission; never partially save an invalid replacement.
	 *
	 * @param mixed $input Settings API value.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$previous = self::get();
		if ( is_array( $input ) && ! array_key_exists( 'confirmation', $input ) ) {
			$input['confirmation'] = '';
		}
		if ( is_array( $input ) && isset( $input['confirmation'] ) && in_array( $input['confirmation'], array( '0', '1' ), true ) ) {
			$requested             = '1' === $input['confirmation'];
			$input['confirmation'] = '';
			if ( $requested && 'built_in' === ( $input['mode'] ?? '' ) ) {
				if ( \ThePath\SprintEngine\Registration\Policy::prerequisites() ) {
					add_settings_error( self::OPTION, 'sprint_engine_confirmation', __( 'Complete the built-in setup checks before confirming live account creation. Previous settings were kept.', 'sprint-engine' ) );
					return self::get(); // Structural checks may have revoked a previously live confirmation.
				}
				if ( ! ( new \ThePath\SprintEngine\Registration\StateStore() )->initialize() ) {
					add_settings_error( self::OPTION, 'sprint_engine_confirmation_store', __( 'Secure account storage could not be prepared. Previous participant settings were kept; ask the host to check database access.', 'sprint-engine' ) );
					return self::get();
				}
				$input['confirmation'] = \ThePath\SprintEngine\Registration\Policy::confirmation();
				if ( '' === $input['confirmation'] ) {
					add_settings_error( self::OPTION, 'sprint_engine_confirmation_entropy', __( 'Secure confirmation is unavailable. Previous participant settings were kept; ask the host to check secure randomness.', 'sprint-engine' ) );
					return $previous;
				}
			}
		}
		if ( is_array( $input ) && 'built_in' !== ( $input['mode'] ?? '' ) ) {
			$input['confirmation'] = '';
		}
		$choice_valid = is_array( $input ) && array_key_exists( 'disable_native', $input ) && in_array( $input['disable_native'], array( '0', '1', false, true, null ), true );
		if ( is_array( $input ) && array_key_exists( 'disable_native', $input ) ) {
			if ( in_array( $input['disable_native'], array( '0', '1' ), true ) ) {
				$input['disable_native'] = '1' === $input['disable_native'];
			}
			// Suggest checked until a built-in save; retain an explicit opt-out in any mode.
			if ( null === $previous['disable_native'] && 'built_in' !== ( $input['mode'] ?? '' ) && true === $input['disable_native'] ) {
				$input['disable_native'] = null;
			}
		}
		$valid = $choice_valid && self::valid_shape( $input ) && '' !== $input['mode'];
		if ( $valid && 'external' === $input['mode'] ) {
			$valid = '' !== self::destination( $input['registration_url'] ) && ( '' === $input['login_url'] || '' !== self::destination( $input['login_url'] ) );
		}
		if ( ! $valid ) {
			add_settings_error( self::OPTION, 'sprint_engine_participant_invalid', __( 'Choose a participant account mode and valid same-site destinations. Previous participant settings were kept.', 'sprint-engine' ) );
			return $previous;
		}
		return $input;
	}

	/**
	 * Shape validation is intentionally separate from changing site/page policy.
	 *
	 * @param mixed $value Candidate configuration.
	 * @return bool
	 */
	private static function valid_shape( $value ) {
		if ( ! is_array( $value ) || array_diff_key( self::defaults(), $value ) || array_diff_key( $value, self::defaults() ) ) {
			return false;
		}
		return in_array( $value['mode'], array( '', 'built_in', 'external', 'invitation' ), true ) &&
			is_string( $value['registration_url'] ) && strlen( $value['registration_url'] ) <= 2048 &&
			is_string( $value['login_url'] ) && strlen( $value['login_url'] ) <= 2048 &&
			is_string( $value['confirmation'] ) && ( '' === $value['confirmation'] || 1 === preg_match( '/^[a-f0-9]{128}$/D', $value['confirmation'] ) ) &&
			( is_bool( $value['disable_native'] ) || ( null === $value['disable_native'] && 'built_in' !== $value['mode'] ) );
	}

	/**
	 * Allow exact-home-origin GET destinations, never arbitrary redirect payloads.
	 * Does not fetch a URL or replace native login/recovery URL filters.
	 *
	 * @param mixed $url Candidate full URL.
	 * @return string Validated original URL or empty string.
	 */
	public static function destination( $url ) {
		if ( ! is_string( $url ) || '' === $url || strlen( $url ) > 2048 || preg_match( '~[\x00-\x20\x7f\\\\]|%(?![a-f0-9]{2})~i', $url ) ) {
			return '';
		}
		$decoded = rawurldecode( $url );
		if ( preg_match( '~[\x00-\x20\x7f\\\\]|%[a-f0-9]{2}~i', $decoded ) ) {
			return '';
		}
		$parts = wp_parse_url( $url );
		$home  = wp_parse_url( home_url( '/' ) );
		if ( ! is_array( $parts ) || ! is_array( $home ) || empty( $home['scheme'] ) || empty( $home['host'] ) || isset( $parts['user'] ) || isset( $parts['pass'] ) || isset( $parts['fragment'] ) || empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
			return '';
		}
		$local = 'local' === wp_get_environment_type() && in_array( strtolower( $parts['host'] ), array( 'localhost', '127.0.0.1', '[::1]' ), true );
		if ( ( 'https' !== $parts['scheme'] && ! ( $local && 'http' === $parts['scheme'] ) ) || $parts['scheme'] !== $home['scheme'] || strtolower( $parts['host'] ) !== strtolower( $home['host'] ) || self::port( $parts ) !== self::port( $home ) ) {
			return '';
		}
		$path = rawurldecode( $parts['path'] ?? '/' );
		$base = trailingslashit( rawurldecode( $home['path'] ?? '/' ) );
		if ( preg_match( '~(?:^|/)\.{1,2}(?:/|$)|//|[?#]~', $path ) || ( untrailingslashit( $base ) !== $path && 0 !== strpos( $path, $base ) ) ) {
			return '';
		}
		$relative = ltrim( substr( $path, strlen( untrailingslashit( $base ) ) ), '/' );
		if ( preg_match( '~^sprint(?:/|$)|^sprint-engine/dashboard(?:/|$)|(?:^|/)(?:wp-admin|wp-json|xmlrpc\.php)(?:/|$)~i', $relative ) ) {
			return '';
		}
		if ( preg_match( '~(?:^|/)(?:logout|customer-logout|reset-password|lost-password|delete-account)(?:/|$)~i', $relative ) ) {
			return '';
		}
		$query = array();
		wp_parse_str( $parts['query'] ?? '', $query );
		foreach ( $query as $key => $value ) {
			if ( ! is_string( $value ) || preg_match( '/redirect|return|^next$|^url$|^rest_route$|^wc-ajax$|^key$|^checkemail$|password|token|nonce|%/i', $key ) || preg_match( '~[\x00-\x20\x7f\\\\?#&=]|%[a-f0-9]{2}|://|^//~i', $value ) ) {
				return '';
			}
		}
		if ( isset( $query['action'] ) && in_array( strtolower( $query['action'] ), array( 'logout', 'postpass', 'rp', 'resetpass', 'lostpassword', 'retrievepassword', 'confirmaction', 'enter_recovery_mode', 'entered_recovery_mode', 'delete', 'remove' ), true ) ) {
			return '';
		}
		if ( isset( $query['action'] ) && preg_match( '/(?:^|[_-])(?:logout|delete|remove|reset)(?:$|[_-])/i', $query['action'] ) ) {
			return '';
		}
		if ( 'wp-login.php' === strtolower( basename( $relative ) ) && isset( $query['action'] ) && ! in_array( $query['action'], array( 'login', 'register' ), true ) ) {
			return '';
		}
		$page = get_page_by_path( $relative, OBJECT, 'page' );
		if ( isset( $query['page_id'] ) || isset( $query['p'] ) ) {
			$id   = $query['page_id'] ?? $query['p'];
			$page = ctype_digit( $id ) ? get_post( (int) $id ) : null;
			if ( ! $page || 'page' !== $page->post_type ) {
				return '';
			}
		}
		if ( $page && ( 'publish' !== $page->post_status || '' !== $page->post_password ) ) {
			return '';
		}
		return $url;
	}

	/**
	 * Compare implicit and explicit default ports equivalently.
	 *
	 * @param array $parts Parsed URL.
	 * @return int
	 */
	private static function port( $parts ) {
		return $parts['port'] ?? ( 'https' === $parts['scheme'] ? 443 : 80 );
	}

	/**
	 * Current built-in prerequisites; never enables registration.
	 *
	 * @return array
	 */
	public static function native_policy_problems() {
		$problems = array();
		if ( is_multisite() ) {
			$problems[] = __( 'Embedded registration is unsupported on multisite. Use the native network or an existing registration provider.', 'sprint-engine' );
		}
		if ( ! get_option( 'users_can_register' ) ) {
			$problems[] = __( 'WordPress registration is disabled. Review “Anyone can register” in Settings → General.', 'sprint-engine' );
		}
		$role = get_role( 'subscriber' );
		$safe = $role && $role->has_cap( 'read' ) && 'subscriber' === get_option( 'default_role' );
		foreach ( $role ? $role->capabilities : array() as $capability => $allowed ) {
			if ( $allowed && ! in_array( $capability, array( 'read', 'level_0' ), true ) ) {
				$safe = false;
			}
		}
		if ( ! $safe ) {
			$problems[] = __( 'Use the standard Subscriber default role with read-only capabilities. Review Settings → General and any role customisations.', 'sprint-engine' );
		}
		return $problems;
	}

	/**
	 * Persistent setup status; unconfirmed choices never imply operation.
	 *
	 * @return string
	 */
	public static function status() {
		$config = self::get();
		if ( '' === $config['mode'] ) {
			return __( 'Choose how participants get an account. Existing sign-in and access rules remain available.', 'sprint-engine' );
		}
		if ( 'built_in' === $config['mode'] ) {
			if ( ! \ThePath\SprintEngine\Registration\Policy::confirmed() ) {
				return __( 'Built-in account creation is not operational. Review the setup checks and explicitly confirm that the site is ready.', 'sprint-engine' );
			}
			$issues = \ThePath\SprintEngine\Registration\Policy::prerequisites();
			return $issues ? __( 'Built-in account creation is unavailable. Review participant setup; native registration follows the documented failure policy.', 'sprint-engine' ) : '';
		}
		if ( 'external' === $config['mode'] ) {
			if ( '' === self::destination( $config['registration_url'] ) ) {
				return __( 'The registration destination is missing or no longer valid. Account creation links are hidden; update Participant Accounts.', 'sprint-engine' );
			}
			if ( '' !== $config['login_url'] && '' === self::destination( $config['login_url'] ) ) {
				return __( 'The optional login destination is no longer valid. Native sign-in is being used; update Participant Accounts.', 'sprint-engine' );
			}
		}
		return '';
	}
}
