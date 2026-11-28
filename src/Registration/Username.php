<?php
/**
 * Bounded native username selection, only on the owned registration connection.
 *
 * @package SprintEngine
 */

namespace ThePath\SprintEngine\Registration;

defined( 'ABSPATH' ) || exit;

/** Names are public profile data; these logins are identifiers, never secrets. */
final class Username {

	const NAME_ATTEMPTS   = 20;
	const RANDOM_ATTEMPTS = 3;

	/**
	 * Prefer readable names; resolve only collisions, never retry a policy denial.
	 *
	 * @param StateStore $store Currently owned store.
	 * @param string     $first Validated first name.
	 * @param string     $last Validated last name.
	 * @return string|\WP_Error
	 */
	public static function generate( $store, $first, $last ) {
		$parts = array();
		foreach ( array( $first, $last ) as $name ) {
			$part = sanitize_user( remove_accents( $name, 'en_US' ), true );
			if ( ! is_string( $part ) ) {
				return self::denied();
			}
			$part = trim( preg_replace( '/[^a-z0-9]+/', '.', strtolower( $part ) ), '.' );
			if ( '' !== $part ) {
				$parts[] = $part;
			}
		}
		$base = implode( '.', $parts );
		for ( $attempt = 1; '' !== $base && $attempt <= self::NAME_ATTEMPTS; ++$attempt ) {
			$suffix    = 1 === $attempt ? '' : '.' . $attempt;
			$candidate = rtrim( substr( $base, 0, 60 - strlen( $suffix ) ), '.' ) . $suffix;
			$available = self::available( $store, $candidate );
			if ( is_wp_error( $available ) || $available ) {
				return is_wp_error( $available ) ? $available : $candidate;
			}
		}
		// 48 random bits are for collision avoidance, not authentication. Every
		// candidate is checked on the database collation, with at most three tries.
		for ( $attempt = 0; $attempt < self::RANDOM_ATTEMPTS; ++$attempt ) {
			$candidate = 'participant_' . bin2hex( random_bytes( 6 ) );
			$available = self::available( $store, $candidate );
			if ( is_wp_error( $available ) || $available ) {
				return is_wp_error( $available ) ? $available : $candidate;
			}
		}
		return self::denied();
	}

	/**
	 * Native policy must accept every candidate; only a collision permits another.
	 *
	 * @param StateStore $store Owned connection (no cached username_exists read).
	 * @param string     $candidate Bounded ASCII login.
	 * @return bool|\WP_Error
	 */
	private static function available( $store, $candidate ) {
		if ( strlen( $candidate ) > 60 || ! preg_match( '/^[a-z0-9][a-z0-9._]*$/D', $candidate ) || ! validate_username( $candidate ) ) {
			return self::denied();
		}
		foreach ( (array) apply_filters( 'illegal_user_logins', array() ) as $illegal ) {
			if ( ! is_string( $illegal ) || strtolower( $illegal ) === $candidate ) {
				return self::denied();
			}
		}
		global $wpdb;
		// phpcs:disable WordPress.DB.RestrictedFunctions -- Prepared SQL/results use only the original owned connection, bypassing wpdb retry and object caches.
		$result = $store->query( $wpdb->prepare( "SELECT ID FROM $wpdb->users WHERE user_login = %s LIMIT 1", $candidate ) );
		$exists = mysqli_num_rows( $result ) > 0;
		mysqli_free_result( $result );
		// phpcs:enable WordPress.DB.RestrictedFunctions
		return ! $exists;
	}

	/**
	 * Never disclose a rejected username, hook error or collision history.
	 *
	 * @return \WP_Error
	 */
	private static function denied() {
		return new \WP_Error( 'registration_username', __( 'Account creation could not be completed here. Use the site’s existing sign-in or contact the administrator for help.', 'sprint-engine' ), array( 'status' => 403 ) );
	}
}
