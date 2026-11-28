<?php
/**
 * Optional closure of only WordPress's resolved single-site register action.
 *
 * @package SprintEngine
 */

namespace ThePath\SprintEngine\Registration;

use ThePath\SprintEngine\Access\AccessManager;
use ThePath\SprintEngine\Runner\Availability;
use ThePath\SprintEngine\Runner\Routes;
use ThePath\SprintEngine\Tutorial\Marker;

defined( 'ABSPATH' ) || exit;

/** No login URL rewrite, global registration option change or Woo interception. */
final class NativeForm {

	/** Intercept the native action after WordPress has resolved body/query overrides. */
	public static function block() {
		if ( is_multisite() || ! Policy::native_closed() ) {
			return;
		}
		nocache_headers();
		header( 'Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0' );
		header( 'X-Robots-Tag: noindex, nofollow' );
		$return = '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput -- Optional navigation only; exact canonical and fresh eligibility validation below.
		$raw = isset( $_REQUEST['redirect_to'] ) && is_string( $_REQUEST['redirect_to'] ) ? wp_unslash( $_REQUEST['redirect_to'] ) : '';
		if ( strlen( $raw ) <= 2048 && str_starts_with( $raw, home_url( '/sprint/' ) ) ) {
			$slug   = substr( untrailingslashit( $raw ), strlen( home_url( '/sprint/' ) ) );
			$sprint = get_page_by_path( $slug, OBJECT, array( 'sprint_engine_sprint' ) );
			if ( $sprint && Routes::url( $sprint->post_name ) === $raw && '' === Availability::reason( $sprint ) && ! Marker::exists( $sprint->ID ) && 'open' === ( new AccessManager() )->participant_access_kind( $sprint->ID ) ) {
				$return = $raw;
			}
		}
		$message = '<p>' . esc_html__( 'Open your Sprint link to create an account. If you already have an account, sign in or recover your password.', 'sprint-engine' ) . '</p><p><a href="' . esc_url( wp_login_url( $return ) ) . '">' . esc_html__( 'Sign in', 'sprint-engine' ) . '</a> · <a href="' . esc_url( wp_lostpassword_url( $return ) ) . '">' . esc_html__( 'Forgot password?', 'sprint-engine' ) . '</a></p>';
		if ( '' !== $return ) {
			$message .= '<p><a href="' . esc_url( $return ) . '">' . esc_html__( 'Return to your Sprint', 'sprint-engine' ) . '</a></p>';
		}
		wp_die( wp_kses_post( $message ), esc_html__( 'Participant accounts', 'sprint-engine' ), array( 'response' => 403 ) );
	}
}
