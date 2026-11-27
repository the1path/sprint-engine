<?php
/**
 * Generic guest presentation over administrator-owned account destinations.
 *
 * @package SprintEngine
 */

namespace ThePath\SprintEngine\Runner;

use ThePath\SprintEngine\Settings\ParticipantAccounts;

defined( 'ABSPATH' ) || exit;

/** Presentation only: no user, entitlement, progress or registration operations. */
final class GuestAccess {

	/**
	 * Build links using only the resolved Sprint, never a request return parameter.
	 *
	 * @param \WP_Post $sprint Ready Sprint (its private fields are not returned).
	 * @param string   $kind AccessManager presentation classification.
	 * @return array
	 */
	public static function context( $sprint, $kind ) {
		$config    = ParticipantAccounts::get();
		$canonical = Routes::url( $sprint->post_name );
		$login     = wp_login_url( $canonical );
		$signup    = '';
		$delegated = false;
		if ( 'external' === $config['mode'] ) {
			$url = ParticipantAccounts::destination( $config['login_url'] );
			if ( '' !== $url && '' !== self::hint( $url, $canonical ) ) {
				$login     = self::hint( $url, $canonical );
				$delegated = true;
			}
			$url = ParticipantAccounts::destination( $config['registration_url'] );
			if ( 'open' === $kind && '' !== $url && '' !== self::hint( $url, $canonical ) ) {
				$signup    = self::hint( $url, $canonical );
				$delegated = true;
			}
		}
		return array(
			'kind'         => $kind,
			'invitation'   => 'invitation' === $config['mode'],
			'login_url'    => $login,
			'signup_url'   => $signup,
			'recovery_url' => wp_lostpassword_url( $canonical ),
			'original_url' => $delegated ? $canonical : '',
			'privacy_url'  => get_privacy_policy_url(),
		);
	}

	/**
	 * Bound delegated hints without accepting a request-supplied return address.
	 *
	 * @param string $url Validated destination.
	 * @param string $canonical Server-resolved Runner.
	 * @return string
	 */
	private static function hint( $url, $canonical ) {
		if ( strlen( $canonical ) > 2048 ) {
			return '';
		}
		// As in wp_login_url(), new query values need encoding before add_query_arg().
		$result = add_query_arg( 'redirect_to', urlencode( $canonical ), $url ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.urlencode_urlencode -- Match native wp_login_url() query-value encoding.
		return strlen( $result ) <= 4096 ? $result : '';
	}
}
