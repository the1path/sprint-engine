<?php
/**
 * Native registration at the canonical Runner boundary, never a new endpoint.
 *
 * @package SprintEngine
 */

namespace ThePath\SprintEngine\Registration;

use ThePath\SprintEngine\Access\AccessManager;
use ThePath\SprintEngine\Runner\Availability;
use ThePath\SprintEngine\Runner\Routes;
use ThePath\SprintEngine\Settings\ParticipantAccounts;
use ThePath\SprintEngine\Tutorial\Marker;

defined( 'ABSPATH' ) || exit;

/** Coordinates request validation, native account service and normal sign-on. */
final class Controller {

	/**
	 * Existing authority/presentation boundary.
	 *
	 * @var AccessManager
	 */
	private $access;

	/**
	 * Use the existing access authority.
	 *
	 * @param AccessManager $access Existing policy boundary.
	 */
	public function __construct( AccessManager $access ) {
		$this->access = $access;
	}

	/**
	 * Resolve an auth receipt before Runner can resume/read another member's work.
	 *
	 * @param string $slug Canonical route slug.
	 * @return array|null Safe blocked context or null for ordinary Runner resolution.
	 */
	public function before_resolve( $slug ) {
		$sprint = get_page_by_path( $slug, OBJECT, array( 'sprint_engine_sprint' ) );
		if ( ! $sprint || '' !== Availability::reason( $sprint ) ) {
			return null;
		}
		$result = BrowserState::result( $sprint->ID );
		if ( 'mismatch' === $result || ( is_user_logged_in() && 'POST' === self::method() ) ) {
			return array(
				'state'   => 'error',
				'status'  => 409,
				'message' => __( 'You are already signed in. Use your original Sprint link to continue with your current account, or use the normal sign-in flow.', 'sprint-engine' ),
			);
		}
		return null;
	}

	/**
	 * Enrich an already resolved guest; never re-run SE-017's public gate filter.
	 *
	 * @param string $slug Resolved route slug.
	 * @param array  $context Existing safe guest/403 context.
	 * @throws \RuntimeException Internally contained; persisted accounts are retained.
	 * @return array
	 */
	public function prepare( $slug, $context ) {
		if ( ! isset( $context['guest'] ) ) {
			return $context;
		}
		$sprint = get_page_by_path( $slug, OBJECT, array( 'sprint_engine_sprint' ) );
		if ( ! $sprint || '' !== Availability::reason( $sprint ) ) {
			return $context;
		}
		$context['title'] = __( 'Sign in to continue', 'sprint-engine' );
		if ( 'pending' === BrowserState::result( $sprint->ID ) ) {
			$context['guest']['notice'] = self::created_message();
			return $context;
		}
		$config = ParticipantAccounts::get();
		if ( 'built_in' !== $config['mode'] || 'open' !== $context['guest']['kind'] || Marker::exists( $sprint->ID ) || ! Policy::operational() ) {
			if ( 'POST' === self::method() ) {
				unset( $_POST['password'], $_REQUEST['password'] );
				$context['guest']['notice'] = __( 'Account creation is unavailable here. Use Sign in or contact the site administrator.', 'sprint-engine' );
				$context['status']          = 403;
			}
			return $context;
		}
		$values = array(
			'first_name' => '',
			'last_name'  => '',
			'email'      => '',
		);
		$errors = array();
		if ( 'POST' === self::method() ) {
			// phpcs:disable WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput -- Browser-bound action token, strict scalar/size/field validation below; no WP guest nonce assumption.
			$input = $_POST;
			$size  = $_SERVER['CONTENT_LENGTH'] ?? '';
			$peer  = $_SERVER['REMOTE_ADDR'] ?? '';
			$valid = is_array( $input ) && count( $input ) === count( Input::FIELDS ) && ! array_diff( array_keys( $input ), Input::FIELDS ) && is_scalar( $size ) && ctype_digit( (string) $size ) && (int) $size <= 16384 && is_string( $peer ) && false !== filter_var( $peer, FILTER_VALIDATE_IP );
			foreach ( $input as $value ) {
				if ( ! is_string( $value ) || strlen( $value ) > 8192 ) {
					$valid = false;
				}
			}
			$input   = $valid ? wp_unslash( $input ) : array();
			$browser = $valid && 'sprint_engine_register' === ( $input['sprint_engine_action'] ?? '' ) ? BrowserState::verify( $sprint->ID, $input['sprint_engine_registration_token'] ?? '' ) : false;
			// phpcs:enable WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput
			if ( ! $browser ) {
				$error = new \WP_Error( 'registration_request', __( 'This form could not be verified. Use the fresh form below and try again.', 'sprint-engine' ), array( 'status' => 403 ) );
			} else {
				$validated = Input::validate( $input );
				$values    = array_diff_key( $validated['values'], array( 'password' => true ) );
				$error     = ( new AccountService() )->create(
					$validated['values'],
					$input['sprint_engine_registration_token'],
					$browser,
					$peer,
					$validated['errors'],
					fn() => $this->eligible( $sprint->ID, $config['confirmation'] )
				);
				if ( is_int( $error ) && $error > 0 ) {
					$id = $error;
					// Native validation hooks already ran; clear request globals before auth/UI.
					unset( $_POST['password'], $_REQUEST['password'] );
					$context['guest']['notice'] = self::created_message();
					try {
						BrowserState::receipt( $sprint->ID, $id );
						wp_send_new_user_notifications( $id, 'admin' );
						Policy::refresh();
						if ( ! Policy::operational() ) {
							throw new \RuntimeException( 'Registration policy changed.' );
						}
						$canonical = Routes::url( get_post_field( 'post_name', $sprint->ID ) );
						// Native MFA reads this request hint; it is never taken from the browser.
						$_REQUEST['redirect_to'] = $canonical;
						$user                    = wp_signon(
							array(
								'user_login'    => get_userdata( $id )->user_login,
								'user_password' => $validated['values']['password'],
								'remember'      => false,
							)
						);
						if ( ! is_wp_error( $user ) && $user instanceof \WP_User && $id === $user->ID ) {
							wp_safe_redirect( $canonical, 303, 'Sprint Engine' );
							exit; // Actual authentication/access is checked on the fresh request.
						}
					} catch ( \Throwable $exception ) {
						// Persisted account is retained; never recreate/delete it or force auth.
						return $context;
					}
					return $context;
				}
			}
			unset( $_POST['password'], $_REQUEST['password'] );
			$data                       = $error->get_error_data();
			$errors                     = is_array( $data ) ? ( $data['fields'] ?? array() ) : array();
			$context['guest']['notice'] = $error->get_error_message();
			if ( ! isset( $context['access_denied_sprint_id'] ) ) {
				$context['status'] = is_array( $data ) ? ( $data['status'] ?? 400 ) : 400;
			}
			if ( ! $this->eligible( $sprint->ID, $config['confirmation'] ) ) {
				return $context;
			}
		} elseif ( 'GET' !== self::method() && 'HEAD' !== self::method() ) {
			$context['guest']['notice'] = __( 'Use the account form to submit your details.', 'sprint-engine' );
			if ( ! isset( $context['access_denied_sprint_id'] ) ) {
				$context['status'] = 405;
			}
			return $context;
		}
		try {
			$context['guest']['form'] = array(
				'url'    => Routes::url( $sprint->post_name ),
				'token'  => BrowserState::token( $sprint->ID ),
				'values' => $values,
				'errors' => $errors,
			);
			$context['title']         = __( 'Create your account', 'sprint-engine' );
		} catch ( \Throwable $exception ) {
			$context['guest']['notice'] = __( 'Secure account creation is unavailable. Use the site’s sign-in or contact the administrator.', 'sprint-engine' );
		}
		return $context;
	}

	/**
	 * Recheck cached content and policy after waiting or invoking native callbacks.
	 *
	 * @param int    $sprint Canonical saved Sprint ID.
	 * @param string $confirmation Verified request's operator generation.
	 * @return bool
	 */
	private function eligible( $sprint, $confirmation ) {
		Policy::refresh();
		clean_post_cache( $sprint );
		foreach ( \ThePath\SprintEngine\Content\Meta::steps( $sprint ) as $step ) {
			clean_post_cache( $step->ID );
		}
		return ! is_user_logged_in() && Policy::operational() && hash_equals( $confirmation, ParticipantAccounts::get()['confirmation'] ) && '' === Availability::reason( get_post( $sprint ) ) && ! Marker::exists( $sprint ) && 'open' === $this->access->participant_access_kind( $sprint );
	}

	/**
	 * Native method, without accepting a method override.
	 *
	 * @return string
	 */
	private static function method() {
		return sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ?? '' ) );
	}

	/**
	 * Generic partial-success guidance with no account identifier.
	 *
	 * @return string
	 */
	private static function created_message() {
		return __( 'Your account was created. Complete the site’s sign-in or password recovery to continue, then return using your original Sprint link.', 'sprint-engine' );
	}
}
