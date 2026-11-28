<?php
/**
 * Objective native-registration prerequisites and explicit operator confirmation.
 *
 * @package SprintEngine
 */

namespace ThePath\SprintEngine\Registration;

use ThePath\SprintEngine\Settings\ParticipantAccounts;

defined( 'ABSPATH' ) || exit;

/** Confirmation is intent, never a substitute for HTTPS, roles or atomic storage. */
final class Policy {

	/**
	 * No header/constant/filter bypass: TLS must be correctly configured in WordPress.
	 *
	 * @return bool
	 */
	public static function https() {
		return is_ssl() && self::https_urls();
	}

	/**
	 * Configured TLS origin; insecure transport must not reopen a confirmed gate.
	 *
	 * @return bool
	 */
	private static function https_urls() {
		$home = get_option( 'home' );
		$site = get_option( 'siteurl' );
		return is_string( $home ) && is_string( $site ) && 'https' === wp_parse_url( $home, PHP_URL_SCHEME ) && 'https' === wp_parse_url( $site, PHP_URL_SCHEME );
	}

	/**
	 * Current objective prerequisites; does not require a saved confirmation itself.
	 *
	 * @return array Safe administrator guidance.
	 */
	public static function prerequisites() {
		$issues = ParticipantAccounts::native_policy_problems();
		if ( ! self::https() ) {
			$issues[] = __( 'Configure HTTPS for the website and WordPress login before enabling account creation.', 'sprint-engine' );
		}
		$health = ( new StateStore() )->health();
		if ( in_array( $health, array( 'unsupported', 'invalid' ), true ) ) {
			self::revoke();
		}
		if ( 'ready' !== $health ) {
			$issues[] = __( 'Account storage is unavailable or unsupported. Ask the host to check native mysqli, database named locks and temporary registration state.', 'sprint-engine' );
		}
		return $issues;
	}

	/**
	 * Server-only confirmation bound to objective configuration and policy revision.
	 *
	 * @return string
	 */
	public static function stamp() {
		$role = get_role( 'subscriber' );
		$caps = $role ? $role->capabilities : array();
		ksort( $caps );
		// site_url() changes scheme with the current request; fingerprint configured options.
		return hash_hmac( 'sha256', wp_json_encode( array( 'se0313-v1', get_option( 'home' ), get_option( 'siteurl' ), is_multisite(), (bool) get_option( 'users_can_register' ), get_option( 'default_role' ), $caps ) ), wp_salt( 'auth' ) );
	}

	/**
	 * A fresh confirmation generation invalidates forms across revoke/reconfirm.
	 *
	 * @return string Signed random generation, or empty on entropy failure.
	 */
	public static function confirmation() {
		try {
			$random = bin2hex( random_bytes( 32 ) );
			return $random . hash_hmac( 'sha256', self::stamp() . '|' . $random, wp_salt( 'auth' ) );
		} catch ( \Throwable $exception ) {
			return '';
		}
	}

	/**
	 * The saved flag alone never silently activates an SE-031.2 pending preference.
	 *
	 * @return bool
	 */
	public static function confirmed() {
		$config = ParticipantAccounts::get();
		if ( 'built_in' !== $config['mode'] || '' === $config['confirmation'] ) {
			return false;
		}
		$expected = hash_hmac( 'sha256', self::stamp() . '|' . substr( $config['confirmation'], 0, 64 ), wp_salt( 'auth' ) );
		if ( 128 !== strlen( $config['confirmation'] ) || ! hash_equals( $expected, substr( $config['confirmation'], 64 ) ) ) {
			self::revoke();
			return false;
		}
		return true;
	}

	/**
	 * Whether the form may be offered/processed now.
	 *
	 * @return bool
	 */
	public static function operational() {
		return self::confirmed() && array() === self::prerequisites();
	}

	/**
	 * Transient contention/rates/capacity never reopen a native abuse bypass.
	 * Structural unsupported/corrupt storage invalidates operation; DB outages hold
	 * the already-confirmed native gate closed with safe temporary guidance.
	 *
	 * @return bool
	 */
	public static function native_closed() {
		$config = ParticipantAccounts::get();
		if ( true !== $config['disable_native'] || ! self::confirmed() || ! self::https_urls() || ParticipantAccounts::native_policy_problems() ) {
			return false;
		}
		$health = ( new StateStore() )->health();
		if ( in_array( $health, array( 'unsupported', 'invalid' ), true ) ) {
			self::revoke();
		}
		return true === $config['disable_native'] && self::confirmed() && self::https_urls() && array() === ParticipantAccounts::native_policy_problems() && ! in_array( $health, array( 'unsupported', 'invalid' ), true );
	}

	/** Refresh cached settings/roles after waiting for the mutex or native callbacks. */
	public static function refresh() {
		global $wpdb;
		foreach ( array( ParticipantAccounts::OPTION, 'users_can_register', 'default_role', 'home', 'siteurl', $wpdb->prefix . 'user_roles' ) as $option ) {
			wp_cache_delete( $option, 'options' );
		}
		wp_cache_delete( 'alloptions', 'options' );
		wp_roles()->for_site();
	}

	/**
	 * Objective WP setting changes revoke confirmation; returning to old values
	 * requires a deliberate new save rather than silently re-enabling signup.
	 *
	 * @param string $option Changed option name.
	 */
	public static function invalidate( $option ) {
		global $wpdb;
		if ( ! in_array( $option, array( 'users_can_register', 'default_role', 'home', 'siteurl', $wpdb->prefix . 'user_roles' ), true ) ) {
			return;
		}
		self::revoke();
	}

	/** Repaired structural prerequisites still require a fresh operator save. */
	public static function revoke() {
		$config = ParticipantAccounts::get();
		if ( '' !== $config['confirmation'] ) {
			$config['confirmation'] = '';
			update_option( ParticipantAccounts::OPTION, $config, false );
		}
	}
}
