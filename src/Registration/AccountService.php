<?php
/**
 * Native account creation within the owned, reserved registration boundary.
 *
 * @package SprintEngine
 */

namespace ThePath\SprintEngine\Registration;

defined( 'ABSPATH' ) || exit;

/** No custom identity store, direct auth cookies, account updates or enrolment. */
final class AccountService {

	/**
	 * Request-local guard for the native insertion filter.
	 *
	 * @var array|null
	 */
	private static $inserting;

	/** Register a permanent, deny-only native insertion guard for our active call. */
	public static function register_guard() {
		add_filter( 'wp_pre_insert_user_data', array( __CLASS__, 'guard_data' ), PHP_INT_MAX, 3 );
	}

	/**
	 * Native hooks remain authoritative; changed identities/lock ownership abort.
	 * No temporary password/role/policy override is installed.
	 *
	 * @param array $data Native final users-table data.
	 * @param bool  $update Updating an existing account.
	 * @param int   $id Existing account ID or null.
	 * @throws \RuntimeException When native data or ownership changes.
	 * @return array
	 */
	public static function guard_data( $data, $update, $id ) {
		if ( null === self::$inserting ) {
			return $data;
		}
		$guard = self::$inserting;
		$guard['store']->assert_owned();
		if ( $update || $id || ! is_array( $data ) || ( $data['user_login'] ?? '' ) !== $guard['login'] || ( $data['user_email'] ?? '' ) !== $guard['email'] || ! wp_check_password( $guard['password'], $data['user_pass'] ?? '' ) || ! $guard['eligible']() ) {
			throw new \RuntimeException( 'Native registration data or policy changed.' );
		}
		if ( self::identity_exists( $guard['store'], $guard['login'], $guard['email'] ) ) {
			throw new \RuntimeException( 'Native registration identity is no longer available.' );
		}
		return $data;
	}

	/**
	 * Reserve once, honour native validation, insert a fixed new-user structure.
	 *
	 * @param array    $values Validated fields (password remains request-local).
	 * @param string   $token Verified submission.
	 * @param string   $browser Verified browser secret.
	 * @param string   $peer Actual peer address.
	 * @param array    $errors Existing safe input errors.
	 * @param callable $eligible Fresh server-side eligibility check.
	 * @return int|\WP_Error New verified user ID or safe failure.
	 */
	public function create( $values, $token, $browser, $peer, $errors, $eligible ) {
		return ( new StateStore() )->run(
			function ( $store, $state ) use ( $values, $token, $browser, $peer, $errors, $eligible ) {
				$reserved = Budget::reserve( $store, $state, $token, $browser, $values['email'], $peer );
				if ( is_wp_error( $reserved ) ) {
					return $reserved;
				}
				if ( $errors ) {
					return new \WP_Error(
						'registration_fields',
						__( 'Check the highlighted fields.', 'sprint-engine' ),
						array(
							'status' => 400,
							'fields' => $errors,
						)
					);
				}
				if ( ! $eligible() ) {
					return self::denied();
				}
				$email = apply_filters( 'user_registration_email', $values['email'] );
				$login = Username::generate( $store, $values['first_name'], $values['last_name'] );
				if ( is_wp_error( $login ) ) {
					return $login;
				}
				$native_errors = new \WP_Error();
				do_action( 'register_post', $login, $email, $native_errors );
				$native_errors = apply_filters( 'registration_errors', $native_errors, $login, $email );
				if ( ! ( $native_errors instanceof \WP_Error ) || $native_errors->has_errors() || ! is_string( $email ) || sanitize_email( $email ) !== $email || strlen( $email ) > 100 || ! is_email( $email ) || ! validate_username( $login ) || ! $eligible() ) {
					return self::denied();
				}
				if ( self::identity_exists( $store, $login, $email ) ) {
					return new \WP_Error( 'registration_existing', __( 'Use Sign in or password recovery if you already have an account. Otherwise, check your email address or contact the site administrator.', 'sprint-engine' ), array( 'status' => 409 ) );
				}
				self::$inserting = array(
					'store'    => $store,
					'eligible' => $eligible,
					'login'    => $login,
					'email'    => $email,
					'password' => $values['password'],
				);
				try {
					$id = wp_insert_user(
						array(
							'user_login'   => $login,
							'user_email'   => $email,
							'user_pass'    => $values['password'],
							'first_name'   => wp_slash( $values['first_name'] ),
							'last_name'    => wp_slash( $values['last_name'] ),
							'display_name' => wp_slash( $values['first_name'] . ' ' . $values['last_name'] ),
							'nickname'     => wp_slash( $values['first_name'] ),
							'role'         => 'subscriber',
						)
					);
				} finally {
					self::$inserting = null;
				}
				$store->assert_owned();
				if ( is_wp_error( $id ) || ! is_int( $id ) || $id < 1 ) {
					return self::denied();
				}
				clean_user_cache( $id );
				$user    = get_userdata( $id );
				$granted = $user ? array_keys( array_filter( $user->allcaps ) ) : array();
				if ( ! $user || $user->user_login !== $login || $user->user_email !== $email || array( 'subscriber' ) !== $user->roles || ! $user->has_cap( 'read' ) || array_diff( $granted, array( 'read', 'level_0', 'subscriber' ) ) || ! wp_check_password( $values['password'], $user->user_pass, $id ) || get_user_meta( $id, 'first_name', true ) !== $values['first_name'] || get_user_meta( $id, 'last_name', true ) !== $values['last_name'] ) {
					return self::denied(); // Never delete/recreate a persisted account or force sign-on.
				}
				return $id;
			}
		);
	}

	/**
	 * Recheck the exact identity after native callbacks, on the owned connection.
	 *
	 * @param StateStore $store Owned store.
	 * @param string     $login Expected login.
	 * @param string     $email Expected email.
	 * @return bool
	 */
	private static function identity_exists( $store, $login, $email ) {
		global $wpdb;
		// phpcs:disable WordPress.DB.RestrictedFunctions -- Prepared SQL/results use the original locked native connection without wpdb retry or object caches.
		$result = $store->query( $wpdb->prepare( "SELECT ID FROM $wpdb->users WHERE user_email = %s OR user_login = %s LIMIT 2", $email, $login ) );
		$exists = mysqli_num_rows( $result ) > 0;
		mysqli_free_result( $result );
		// phpcs:enable WordPress.DB.RestrictedFunctions
		return $exists;
	}

	/**
	 * Safe native-policy failure; never expose provider/debug data.
	 *
	 * @return \WP_Error
	 */
	private static function denied() {
		return new \WP_Error( 'registration_policy', __( 'Account creation could not be completed here. Use the site’s existing sign-in or contact the administrator for help.', 'sprint-engine' ), array( 'status' => 403 ) );
	}
}
