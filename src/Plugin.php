<?php
/**
 * Plugin lifecycle and hook registration.
 *
 * @package SprintEngine
 */

namespace ThePath\SprintEngine;

use ThePath\SprintEngine\Content\SprintPostType;
use ThePath\SprintEngine\Content\StepPostType;
use ThePath\SprintEngine\Content\Meta;
use ThePath\SprintEngine\Content\Authoring;
use ThePath\SprintEngine\Content\StructureAdmin;
use ThePath\SprintEngine\Content\RunnerAdmin;
use ThePath\SprintEngine\Database\Installer;
use ThePath\SprintEngine\Runner\Routes;
use ThePath\SprintEngine\Runner\Runner;

defined( 'ABSPATH' ) || exit;

/** Coordinates plugin lifecycle and dedicated content/Runner services. */
final class Plugin {

	/** Register hooks without running database work at file inclusion time. */
	public function register_hooks() {
		( new Meta() )->register_hooks();
		( new Authoring() )->register_hooks();
		( new StructureAdmin() )->register_hooks();
		( new RunnerAdmin() )->register_hooks();
		( new \ThePath\SprintEngine\Content\StepListAdmin() )->register_hooks();
		( new \ThePath\SprintEngine\Settings\SettingsPage() )->register_hooks();
		( new Routes() )->register_hooks();
		( new \ThePath\SprintEngine\Dashboard\Routes() )->register_hooks();
		( new \ThePath\SprintEngine\Dashboard\Dashboard() )->register_hooks();
		( new Runner() )->register_hooks();
		( new \ThePath\SprintEngine\Rest\Controller() )->register_hooks();
		add_action( 'init', array( $this, 'maybe_upgrade' ), 5 );
		add_action( 'init', array( __CLASS__, 'register_post_types' ) );
		add_action( 'admin_notices', array( $this, 'installation_notice' ) );
	}

	/** Register content on init and before the activation rewrite flush. */
	public static function register_post_types() {
		SprintPostType::register();
		StepPostType::register();
	}

	/**
	 * Install the current site; network-wide provisioning is not part of SE-001.
	 *
	 * @param bool $network_wide Whether network activation was requested.
	 */
	public static function activate( $network_wide = false ) {
		if ( $network_wide ) {
			wp_die( esc_html__( 'Activate Sprint Engine on individual sites. Network activation is not supported yet.', 'sprint-engine' ) );
		}
		if ( ! self::requirements_met() ) {
			wp_die( esc_html__( 'Sprint Engine requires PHP 8.0 or later and WordPress 6.0 or later. Upgrade the site before activating.', 'sprint-engine' ) );
		}
		if ( ! ( new Installer() )->install() ) {
			wp_die( esc_html__( 'Sprint Engine could not install its tables. Ask your administrator to check database permissions, then reactivate the plugin.', 'sprint-engine' ) );
		}
		self::register_post_types();
		Routes::register();
		\ThePath\SprintEngine\Dashboard\Routes::register();
		flush_rewrite_rules( false );
	}

	/** Remove in-memory registrations only. All stored content/progress is retained. */
	public static function deactivate() {
		unregister_post_type( 'sprint_engine_sprint' );
		unregister_post_type( 'sprint_engine_step' );
		Routes::unregister();
		\ThePath\SprintEngine\Dashboard\Routes::unregister();
		flush_rewrite_rules( false );
	}

	/** Install before dependent init handlers; refresh routes once per version change. */
	public function maybe_upgrade() {
		if ( ! self::requirements_met() ) {
			return;
		}
		$stored_version = get_option( 'sprint_engine_version' );
		if ( SPRINT_ENGINE_SCHEMA_VERSION !== get_option( 'sprint_engine_schema_version' ) ||
			SPRINT_ENGINE_VERSION !== $stored_version ) {
			if ( ( new Installer() )->install() && SPRINT_ENGINE_VERSION !== $stored_version ) {
				self::register_post_types();
				Routes::register();
				\ThePath\SprintEngine\Dashboard\Routes::register();
				flush_rewrite_rules( false );
			}
		}
	}

	/** Show a safe, administrator-only installation error. */
	public function installation_notice() {
		$screen = get_current_screen();
		if ( ! $screen || ( 'plugins' !== $screen->id && ! str_ends_with( $screen->id, '_page_sprint-engine-settings' ) && ! in_array( $screen->post_type, array( 'sprint_engine_sprint', 'sprint_engine_step' ), true ) ) ) {
			return;
		}
		if ( current_user_can( 'manage_options' ) && get_option( 'sprint_engine_installation_failed' ) ) {
			echo '<div class="notice notice-error"><p>';
			echo esc_html__( 'Sprint Engine database installation failed. Check database permissions and reactivate the plugin to retry.', 'sprint-engine' );
			echo '</p></div>';
		}
	}

	/**
	 * Check the compatibility baseline before installing.
	 *
	 * @return bool
	 */
	private static function requirements_met() {
		global $wp_version;
		return version_compare( PHP_VERSION, SPRINT_ENGINE_MIN_PHP, '>=' ) &&
			version_compare( $wp_version, SPRINT_ENGINE_MIN_WP, '>=' );
	}
}
