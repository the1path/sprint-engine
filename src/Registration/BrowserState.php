<?php
/**
 * Host-only guest CSRF state and non-authenticating sign-on receipts.
 *
 * @package SprintEngine
 */

namespace ThePath\SprintEngine\Registration;

defined( 'ABSPATH' ) || exit;

/** Never a login/session replacement. No secret or identity goes into a URL. */
final class BrowserState {

	const COOKIE  = '__Host-sprint_engine_registration';
	const RECEIPT = '__Host-sprint_engine_registration_result';
	const TTL     = 1200;

	/**
	 * Start/reuse one signed browser secret; tabs get independent one-use form IDs.
	 *
	 * @return string
	 */
	public static function browser() {
		$value = self::cookie( self::COOKIE );
		if ( self::valid_browser( $value ) ) {
			return $value;
		}
		$value  = time() . '.' . bin2hex( random_bytes( 32 ) );
		$value .= '.' . self::mac( 'browser|' . $value );
		self::set( self::COOKIE, $value, time() + 1800 );
		return $value;
	}

	/**
	 * Signed action/Sprint/browser/expiry form token; no guest WP nonce reliance.
	 *
	 * @param int $sprint Canonical Sprint ID.
	 * @return string
	 */
	public static function token( $sprint ) {
		$browser = self::browser();
		$token   = time() . '.' . bin2hex( random_bytes( 16 ) );
		return $token . '.' . self::mac( 'register|' . $sprint . '|' . $browser . '|' . $token . '|' . \ThePath\SprintEngine\Settings\ParticipantAccounts::get()['confirmation'] );
	}

	/**
	 * Accept only the browser's valid secret, token and compatible supplied provenance.
	 * Null/missing provenance is unknown: token validation is still mandatory.
	 *
	 * @param int    $sprint Resolved Sprint ID.
	 * @param string $token Submitted token.
	 * @return string|false Verified browser value.
	 */
	public static function verify( $sprint, $token ) {
		$browser = self::cookie( self::COOKIE );
		if ( ! Policy::https() || ! self::valid_browser( $browser ) || ! is_string( $token ) || ! preg_match( '/^(\d{10})\.([a-f0-9]{32})\.([a-f0-9]{64})$/D', $token, $parts ) || ! self::fresh( (int) $parts[1], self::TTL ) ) {
			return false;
		}
		if ( ! hash_equals( self::mac( 'register|' . $sprint . '|' . $browser . '|' . $parts[1] . '.' . $parts[2] . '|' . \ThePath\SprintEngine\Settings\ParticipantAccounts::get()['confirmation'] ), $parts[3] ) ) {
			return false;
		}
		// phpcs:disable WordPress.Security.ValidatedSanitizedInput -- Strict scalar/header comparisons are validation; none of these values are rendered or persisted.
		$origin  = $_SERVER['HTTP_ORIGIN'] ?? '';
		$referer = $_SERVER['HTTP_REFERER'] ?? '';
		$fetch   = $_SERVER['HTTP_SEC_FETCH_SITE'] ?? '';
		foreach ( array( $origin, $referer, $fetch ) as $header ) {
			if ( ! is_string( $header ) || strlen( $header ) > 4096 || preg_match( '/[\x00-\x20\x7f]/', $header ) ) {
				return false;
			}
		}
		if ( '' !== $fetch && ! in_array( $fetch, array( 'same-origin', 'none' ), true ) ) {
			return false;
		}
		if ( '' !== $origin && 'null' !== $origin && ! self::same_origin( $origin ) ) {
			return false;
		}
		if ( '' !== $referer && ! self::same_origin( $referer ) ) {
			return false;
		}
		return $browser;
		// phpcs:enable WordPress.Security.ValidatedSanitizedInput
	}

	/**
	 * Record only an opaque expected-identity digest before normal wp_signon/MFA.
	 * It grants nothing; the next request must have the matching native WP identity.
	 *
	 * @param int $sprint Canonical Sprint ID.
	 * @param int $user Newly created user ID.
	 */
	public static function receipt( $sprint, $user ) {
		$browser = self::browser();
		$value   = time() . '.' . self::mac( 'identity|' . $sprint . '|' . $user . '|' . $browser );
		self::set( self::RECEIPT, $value . '.' . self::mac( 'result|' . $sprint . '|' . $browser . '|' . $value ), time() + self::TTL );
	}

	/**
	 * Check a receipt only for its original Sprint. Other routes are unaffected.
	 *
	 * @param int $sprint Resolved Sprint ID.
	 * @return string none, pending, matched or mismatch.
	 */
	public static function result( $sprint ) {
		$value   = self::cookie( self::RECEIPT );
		$browser = self::cookie( self::COOKIE );
		if ( ! self::valid_browser( $browser ) || ! preg_match( '/^(\d{10})\.([a-f0-9]{64})\.([a-f0-9]{64})$/D', $value, $parts ) || ! self::fresh( (int) $parts[1], self::TTL ) || ! hash_equals( self::mac( 'result|' . $sprint . '|' . $browser . '|' . $parts[1] . '.' . $parts[2] ), $parts[3] ) ) {
			return 'none';
		}
		if ( ! is_user_logged_in() ) {
			return 'pending';
		}
		$matched = hash_equals( self::mac( 'identity|' . $sprint . '|' . get_current_user_id() . '|' . $browser ), $parts[2] );
		self::set( self::RECEIPT, '', time() - HOUR_IN_SECONDS );
		self::set( self::COOKIE, '', time() - HOUR_IN_SECONDS );
		return $matched ? 'matched' : 'mismatch';
	}

	/**
	 * Compare exact scheme/host/effective port without trusting forwarded headers.
	 *
	 * @param string $url Supplied origin or Referer.
	 * @return bool
	 */
	private static function same_origin( $url ) {
		$a = wp_parse_url( $url );
		$b = wp_parse_url( home_url( '/' ) );
		return is_array( $a ) && ! isset( $a['user'] ) && ! isset( $a['pass'] ) && 'https' === ( $a['scheme'] ?? '' ) && strtolower( $a['host'] ?? '' ) === strtolower( $b['host'] ?? '' ) && ( $a['port'] ?? 443 ) === ( $b['port'] ?? 443 );
	}

	/**
	 * Validate signed browser age as well as cookie syntax.
	 *
	 * @param string $value Cookie.
	 * @return bool
	 */
	private static function valid_browser( $value ) {
		return 1 === preg_match( '/^(\d{10})\.([a-f0-9]{64})\.([a-f0-9]{64})$/D', $value, $parts ) && self::fresh( (int) $parts[1], 1800 ) && hash_equals( self::mac( 'browser|' . $parts[1] . '.' . $parts[2] ), $parts[3] );
	}

	/**
	 * Read only a bounded scalar; never reflect an injected cookie.
	 *
	 * @param string $name Cookie name.
	 * @return string
	 */
	private static function cookie( $name ) {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Syntax/MAC validated by callers; no rendering/persistence.
		return isset( $_COOKIE[ $name ] ) && is_string( $_COOKIE[ $name ] ) && strlen( $_COOKIE[ $name ] ) <= 160 ? $_COOKIE[ $name ] : '';
	}

	/**
	 * Host prefix forbids sibling-domain injection; expire/rotate without JS storage.
	 *
	 * @param string $name Cookie name.
	 * @param string $value Signed value.
	 * @param int    $expires UTC timestamp.
	 * @throws \RuntimeException When secure headers cannot be issued.
	 */
	private static function set( $name, $value, $expires ) {
		if ( ! Policy::https() || headers_sent() ) {
			throw new \RuntimeException( 'Secure browser state unavailable.' );
		}
		setcookie(
			$name,
			$value,
			array(
				'expires'  => $expires,
				'path'     => '/',
				'secure'   => true,
				'httponly' => true,
				'samesite' => 'Strict',
			)
		);
		$_COOKIE[ $name ] = $expires > time() ? $value : '';
	}

	/**
	 * Permit small clock skew but no indefinite browser/form state.
	 *
	 * @param int $issued UTC timestamp.
	 * @param int $ttl Lifetime seconds.
	 * @return bool
	 */
	private static function fresh( $issued, $ttl ) {
		return $issued <= time() + 30 && $issued > time() - $ttl;
	}

	/**
	 * Domain-separated secret; no credentials are signed or stored here.
	 *
	 * @param string $value Message.
	 * @return string
	 */
	private static function mac( $value ) {
		return hash_hmac( 'sha256', 'registration-v1|' . $value, wp_salt( 'nonce' ) );
	}
}
