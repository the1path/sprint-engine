<?php
/**
 * Bounded temporary signup state and connection-owned serialization.
 *
 * @package SprintEngine
 */

namespace ThePath\SprintEngine\Registration;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.RestrictedFunctions -- Uncached authoritative state on the native mysqli connection; no automatic reconnection inside the mutex.

/** No account/progress tables or per-visitor options. Native wpdb/mysqli only. */
final class StateStore {

	const OPTION      = 'sprint_engine_registration_state';
	const MAX_ENTRIES = 2048;
	const MAX_BYTES   = 262144;

	/**
	 * Locked native connection.
	 *
	 * @var \mysqli|null
	 */
	private $connection;
	/**
	 * Connection ID owning the lock.
	 *
	 * @var int
	 */
	private $owner = 0;
	/**
	 * Prevent recursive operations on this request.
	 *
	 * @var bool
	 */
	private static $active = false;

	/**
	 * A stable per-site name, short enough for MySQL's 64-byte limit.
	 *
	 * @return string
	 */
	public static function lock_name() {
		global $wpdb;
		return 'sprint-register-' . substr( hash( 'sha256', DB_NAME . '|' . $wpdb->options ), 0, 32 );
	}

	/**
	 * Probe a separate unique lock so ordinary contention never reopens native signup.
	 * Capacity and rate exhaustion are not structural unavailability.
	 *
	 * @return string ready, unsupported, invalid or unavailable.
	 */
	public function health() {
		global $wpdb;
		if ( 'wpdb' !== get_class( $wpdb ) || ! ( $wpdb->dbh instanceof \mysqli ) || str_starts_with( DB_HOST, 'p:' ) ) {
			return 'unsupported';
		}
		$probes = array();
		try {
			$db     = $wpdb->dbh;
			$probe  = self::lock_name() . '-' . bin2hex( random_bytes( 4 ) );
			$probes = array( $probe, $probe . 'b' );
			$r      = mysqli_query( $db, $wpdb->prepare( 'SELECT GET_LOCK(%s, 0) AS acquired, CONNECTION_ID() AS owner, @@autocommit AS automatic, @@in_transaction AS transaction_open', $probe ) );
			if ( false === $r ) {
				return in_array( mysqli_errno( $db ), array( 1193, 1235, 1305 ), true ) ? 'unsupported' : 'unavailable';
			}
			$row = mysqli_fetch_assoc( $r );
			mysqli_free_result( $r );
			if ( '1' !== $row['acquired'] ) {
				return 'unavailable';
			}
			// Old single-named-lock engines release the first lock on acquiring another.
			$r      = mysqli_query( $db, $wpdb->prepare( 'SELECT GET_LOCK(%s, 0)', $probes[1] ) );
			$second = $r ? (int) mysqli_fetch_row( $r )[0] : 0;
			if ( $r ) {
				mysqli_free_result( $r );
			}
			$r     = mysqli_query( $db, $wpdb->prepare( 'SELECT IS_USED_LOCK(%s)', $probe ) );
			$owned = $r ? (int) mysqli_fetch_row( $r )[0] === (int) $row['owner'] : false;
			if ( $r ) {
				mysqli_free_result( $r );
			}
			if ( 1 !== $second || ! $owned || '1' !== (string) $row['automatic'] || '0' !== (string) $row['transaction_open'] ) {
				return 'unsupported';
			}
			$r = mysqli_query( $db, $wpdb->prepare( "SELECT LEFT(option_value, %d) FROM $wpdb->options WHERE option_name = %s", self::MAX_BYTES + 1, self::OPTION ) );
			if ( ! $r ) {
				return 'unavailable';
			}
			$value = mysqli_fetch_row( $r );
			mysqli_free_result( $r );
			if ( null === $value ) {
				return '' === \ThePath\SprintEngine\Settings\ParticipantAccounts::get()['confirmation'] ? 'ready' : 'invalid';
			}
			return self::decode( $value[0] ) ? 'ready' : 'invalid';
		} catch ( \Throwable $exception ) {
			if ( $exception instanceof \mysqli_sql_exception && in_array( $exception->getCode(), array( 1193, 1235, 1305 ), true ) ) {
				return 'unsupported';
			}
			return 'unavailable';
		} finally {
			foreach ( $probes as $probe ) {
				try {
					$result = mysqli_query( $db, $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $probe ) );
					if ( $result ) {
						mysqli_free_result( $result );
					}
				} catch ( \Throwable $exception ) { // Disconnection releases server-owned locks.
					continue;
				}
			}
		}
	}

	/**
	 * Prove initial bounded persistence before an operator can enable replacement.
	 *
	 * @return bool
	 */
	public function initialize() {
		return true === $this->run(
			static function ( $store, $state ) {
				$store->write( Budget::purge( $state, time() ) );
				return true;
			}
		);
	}

	/**
	 * Serialize state reservation and native insertion, without an external transaction.
	 * wpdb's public backwards-compatible property access disables retry/reconnect only
	 * for this critical section. Restore it before notification/authentication.
	 *
	 * @param callable $operation Operation accepting this store and decoded state.
	 * @throws \RuntimeException Internally contained and converted to a safe error.
	 * @return mixed
	 */
	public function run( $operation ) {
		global $wpdb;
		if ( self::$active || 'ready' !== $this->health() ) {
			return self::error();
		}
		self::$active                = true;
		$database                    = $wpdb;
		$retries                     = $database->reconnect_retries;
		$previous                    = $database->suppress_errors( true );
		$database->reconnect_retries = 0;
		$this->connection            = $database->dbh;
		try {
			$r   = $this->query( $database->prepare( 'SELECT GET_LOCK(%s, 1), CONNECTION_ID()', self::lock_name() ), false );
			$row = mysqli_fetch_row( $r );
			mysqli_free_result( $r );
			if ( '1' !== (string) $row[0] ) {
				return self::error();
			}
			$this->owner = (int) $row[1];
			$this->assert_owned();
			$this->query( $database->prepare( "INSERT IGNORE INTO $database->options (option_name, option_value, autoload) VALUES (%s, %s, 'no')", self::OPTION, '{"version":1,"entries":[]}' ) );
			$r     = $this->query( $database->prepare( "SELECT LEFT(option_value, %d) FROM $database->options WHERE option_name = %s", self::MAX_BYTES + 1, self::OPTION ) );
			$value = mysqli_fetch_row( $r );
			mysqli_free_result( $r );
			$state = $value ? self::decode( $value[0] ) : false;
			if ( false === $state ) {
				throw new \RuntimeException( 'Invalid temporary state.' );
			}
			return $operation( $this, $state );
		} catch ( \Throwable $exception ) {
			return self::error();
		} finally {
			try {
				if ( $this->owner ) {
					$result = $this->query( $database->prepare( 'SELECT RELEASE_LOCK(%s)', self::lock_name() ) );
					mysqli_free_result( $result );
				}
			} catch ( \Throwable $exception ) { // Connection loss already releases its server-owned lock.
				$this->owner = 0;
			}
			$database->reconnect_retries = $retries;
			$database->suppress_errors( $previous );
			$this->owner      = 0;
			$this->connection = null;
			self::$active     = false;
		}
	}

	/**
	 * Throw before any owned write if a callback/reconnect lost the original mutex.
	 *
	 * @throws \RuntimeException When original connection ownership is lost.
	 */
	public function assert_owned() {
		global $wpdb;
		if ( ! $this->connection || $wpdb->dbh !== $this->connection || 0 !== $wpdb->reconnect_retries ) {
			throw new \RuntimeException( 'Registration connection changed.' );
		}
		$r   = mysqli_query( $this->connection, $wpdb->prepare( 'SELECT CONNECTION_ID(), IS_USED_LOCK(%s), @@autocommit, @@in_transaction', self::lock_name() ) );
		$row = $r ? mysqli_fetch_row( $r ) : null;
		if ( $r ) {
			mysqli_free_result( $r );
		}
		if ( ! $row || $this->owner !== (int) $row[0] || $this->owner !== (int) $row[1] || '1' !== (string) $row[2] || '0' !== (string) $row[3] ) {
			throw new \RuntimeException( 'Registration lock lost.' );
		}
	}

	/**
	 * Run prepared SQL on exactly the original handle, without wpdb's retry path.
	 *
	 * @param string $sql Prepared SQL.
	 * @param bool   $guard Require ownership (false only for acquiring the lock).
	 * @throws \RuntimeException On connection loss or query failure.
	 * @return \mysqli_result|bool
	 */
	public function query( $sql, $guard = true ) {
		if ( $guard ) {
			$this->assert_owned();
		}
		$result = mysqli_query( $this->connection, $sql );
		if ( false === $result ) {
			throw new \RuntimeException( 'Registration database unavailable.' );
		}
		return $result;
	}

	/**
	 * Persist only bounded hashes/counters/expiry, never identities or credentials.
	 *
	 * @param array $state Complete temporary state.
	 * @throws \RuntimeException When state exceeds its bounded shape.
	 */
	public function write( $state ) {
		global $wpdb;
		$json = wp_json_encode( $state );
		if ( ! $json || ! self::decode( $json ) ) {
			throw new \RuntimeException( 'Registration capacity unavailable.' );
		}
		$this->query( $wpdb->prepare( "UPDATE $wpdb->options SET option_value = %s, autoload = 'no' WHERE option_name = %s", $json, self::OPTION ) );
		$result = $this->query( $wpdb->prepare( "SELECT LEFT(option_value, %d) FROM $wpdb->options WHERE option_name = %s", self::MAX_BYTES + 1, self::OPTION ) );
		$stored = mysqli_fetch_row( $result );
		mysqli_free_result( $result );
		if ( ! $stored || $json !== $stored[0] ) {
			throw new \RuntimeException( 'Registration state was not retained.' );
		}
		wp_cache_delete( self::OPTION, 'options' );
		wp_cache_delete( 'notoptions', 'options' );
		wp_cache_delete( 'alloptions', 'options' );
	}

	/**
	 * Reject corrupt/oversized rows before iterating; expiry is UTC Unix seconds.
	 *
	 * @param string $json Stored JSON.
	 * @return array|false
	 */
	private static function decode( $json ) {
		if ( strlen( $json ) > self::MAX_BYTES ) {
			return false;
		}
		$state = json_decode( $json, true, 8 );
		if ( ! is_array( $state ) || array( 'version', 'entries' ) !== array_keys( $state ) || 1 !== $state['version'] || ! is_array( $state['entries'] ) || count( $state['entries'] ) > self::MAX_ENTRIES ) {
			return false;
		}
		foreach ( $state['entries'] as $key => $row ) {
			if ( ! is_string( $key ) || ! preg_match( '/^[a-f0-9]{64}$/D', $key ) || ! is_array( $row ) || array( 'expires', 'count' ) !== array_keys( $row ) || ! is_int( $row['expires'] ) || ! is_int( $row['count'] ) || $row['expires'] < 0 || $row['count'] < 1 || $row['count'] > 1000 ) {
				return false;
			}
		}
		return $state;
	}

	/**
	 * Safe persistence failure.
	 *
	 * @return \WP_Error
	 */
	public static function error() {
		return new \WP_Error( 'registration_store', __( 'Account creation is temporarily unavailable. Try again later or contact the site administrator.', 'sprint-engine' ), array( 'status' => 503 ) );
	}
}
