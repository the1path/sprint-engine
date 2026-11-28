<?php
/**
 * Fixed-capacity atomic rate/replay policy, separate from persistence.
 *
 * @package SprintEngine
 */

namespace ThePath\SprintEngine\Registration;

defined( 'ABSPATH' ) || exit;

/** Launch budgets bound all processed submissions, including validation failures. */
final class Budget {

	/**
	 * Reserve a one-use token and counters while the caller holds StateStore's mutex.
	 *
	 * @param StateStore $store Locked store.
	 * @param array      $state Fresh state.
	 * @param string     $token Verified form identity.
	 * @param string     $browser Verified browser secret.
	 * @param string     $email Candidate normalised email (never stored).
	 * @param string     $peer Actual validated REMOTE_ADDR, never forwarded headers.
	 * @return true|\WP_Error
	 */
	public static function reserve( $store, $state, $token, $browser, $email, $peer ) {
		$now    = time();
		$state  = self::purge( $state, $now );
		$replay = self::key( 'replay|' . $browser . '|' . $token );
		if ( isset( $state['entries'][ $replay ] ) ) {
			return new \WP_Error( 'registration_replay', __( 'This form has already been submitted. Sign in if you have an account, or use the new form below.', 'sprint-engine' ), array( 'status' => 409 ) );
		}
		$budgets = array(
			array( 'site', 100, 600 ),
			array( 'peer|' . $peer, 40, 600 ),
			array( 'browser|' . $browser, 6, 600 ),
			array( 'email|' . strtolower( $email ), 3, HOUR_IN_SECONDS ),
		);
		foreach ( $budgets as $budget ) {
			[$identity, $limit, $window] = $budget;
			$key                         = self::key( $identity . '|' . (int) floor( $now / $window ) );
			if ( ( $state['entries'][ $key ]['count'] ?? 0 ) >= $limit ) {
				return new \WP_Error( 'registration_rate', __( 'Too many account requests. Please try again later. Existing accounts can still sign in.', 'sprint-engine' ), array( 'status' => 429 ) );
			}
		}
		foreach ( $budgets as $budget ) {
			[$identity, $limit, $window] = $budget;
			$key                         = self::key( $identity . '|' . (int) floor( $now / $window ) );
			$state['entries'][ $key ]    = array(
				'expires' => ( (int) floor( $now / $window ) + 1 ) * $window,
				'count'   => ( $state['entries'][ $key ]['count'] ?? 0 ) + 1,
			);
		}
		$state['entries'][ $replay ] = array(
			'expires' => $now + 1200,
			'count'   => 1,
		);
		if ( count( $state['entries'] ) > StateStore::MAX_ENTRIES ) {
			return StateStore::error();
		}
		$store->write( $state );
		return true;
	}

	/**
	 * Bounded logical/cron expiry; no operational member data is read or deleted.
	 *
	 * @param array $state Validated state.
	 * @param int   $now UTC Unix seconds.
	 * @return array
	 */
	public static function purge( $state, $now ) {
		foreach ( $state['entries'] as $key => $row ) {
			if ( $row['expires'] <= $now ) {
				unset( $state['entries'][ $key ] );
			}
		}
		return $state;
	}

	/**
	 * Domain-separated keyed identities do not store raw email/IP/browser values.
	 *
	 * @param string $value Ephemeral identity.
	 * @return string
	 */
	private static function key( $value ) {
		return hash_hmac( 'sha256', 'registration-budget|' . $value, wp_salt( 'nonce' ) );
	}
}
