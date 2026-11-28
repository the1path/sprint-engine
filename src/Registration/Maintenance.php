<?php
/**
 * Temporary signup-state lifecycle, never member-data cleanup.
 *
 * @package SprintEngine
 */

namespace ThePath\SprintEngine\Registration;

use ThePath\SprintEngine\Settings\ParticipantAccounts;

defined( 'ABSPATH' ) || exit;

/** Bounded hourly purge plus synchronous expiry on every reserved submission. */
final class Maintenance {

	const HOOK = 'sprint_engine_registration_purge';

	/** Schedule only for an explicitly confirmed built-in configuration. */
	public static function schedule() {
		if ( ( '' !== ParticipantAccounts::get()['confirmation'] || false !== get_option( StateStore::OPTION, false ) ) && ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time() + 600, 'hourly', self::HOOK );
		}
	}

	/** Expired hashes remain physically bounded even when WP-Cron is delayed. */
	public static function purge() {
		( new StateStore() )->run(
			static function ( $store, $state ) {
				$state = Budget::purge( $state, time() );
				$store->write( $state );
				if ( ! $state['entries'] && '' === ParticipantAccounts::get()['confirmation'] ) {
					wp_clear_scheduled_hook( self::HOOK );
					delete_option( StateStore::OPTION );
				}
				return true;
			}
		);
	}

	/** Explicit deactivation clears only temporary abuse state and its schedule. */
	public static function deactivate() {
		Policy::revoke();
		wp_clear_scheduled_hook( self::HOOK );
		delete_option( StateStore::OPTION );
	}
}
