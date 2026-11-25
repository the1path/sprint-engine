<?php
/**
 * Explicit, atomic tutorial opt-in using Core authoring workflows.
 *
 * @package SprintEngine
 */

namespace ThePath\SprintEngine\Tutorial;

use ThePath\SprintEngine\Content\Meta;
use ThePath\SprintEngine\Content\StructureManager;
use ThePath\SprintEngine\Content\Transaction;
use ThePath\SprintEngine\Runner\Availability;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.DB.DirectDatabaseQuery -- Locked per-site installation identity, never a member-facing query.

/** Installs all posts, metadata and identity in one transaction, or none of them. */
final class Installer {

	const OPTION = 'sprint_engine_tutorial_id';

	/**
	 * Locate the one marked tutorial, including Trash; ambiguity fails closed.
	 * The option is an installer pointer, not the access policy.
	 *
	 * @return int|\WP_Error Zero when deliberately deleted/not installed.
	 */
	public static function find() {
		global $wpdb;
		$previous = $wpdb->suppress_errors( true );
		$ids      = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT p.ID FROM $wpdb->posts p INNER JOIN $wpdb->postmeta m ON m.post_id = p.ID WHERE p.post_type = %s AND m.meta_key = %s", 'sprint_engine_sprint', Marker::KEY ) );
		$failed   = $wpdb->last_error || ! is_array( $ids );
		$wpdb->suppress_errors( $previous );
		if ( $failed || count( $ids ) > 1 ) {
			return self::error();
		}
		$id      = (int) ( $ids[0] ?? 0 );
		$pointer = get_option( self::OPTION, '0' );
		if ( ! is_scalar( $pointer ) || ! preg_match( '/^(0|[1-9][0-9]*)$/D', (string) $pointer ) ) {
			return self::error();
		}
		if ( (int) $pointer && get_post( (int) $pointer ) && (int) $pointer !== $id ) {
			return self::error();
		}
		if ( $id ) {
			clean_post_cache( $id );
			if ( ! Marker::canonical( $id ) ) {
				return self::error();
			}
		}
		return $id;
	}

	/**
	 * Install only on an authorized POST controller call (or trusted admin PHP).
	 * An existing tutorial is reused unchanged; Trash restoration is explicit.
	 *
	 * @return int|\WP_Error Tutorial ID.
	 */
	public function install() {
		global $wpdb;
		if ( ! current_user_can( 'manage_options' ) || SPRINT_ENGINE_SCHEMA_VERSION !== get_option( 'sprint_engine_schema_version' ) || get_option( 'sprint_engine_installation_failed' ) ) {
			return self::error();
		}
		// A permanent, non-autoloaded row is the mutex; it has no expiring lease.
		add_option( self::OPTION, '0', '', false );
		$result = ( new Transaction() )->run(
			function () use ( $wpdb ) {
				if ( false === $wpdb->query( $wpdb->prepare( "UPDATE $wpdb->options SET option_value = option_value WHERE option_name = %s", self::OPTION ) ) ) {
					return self::error();
				}
				// Read the locked row from the database, never another request's option cache.
				$pointer = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM $wpdb->options WHERE option_name = %s", self::OPTION ) );
				if ( null === $pointer || $wpdb->last_error ) {
					return self::error();
				}
				wp_cache_delete( self::OPTION, 'options' );
				wp_cache_delete( 'alloptions', 'options' );
				wp_cache_delete( 'notoptions', 'options' );
				$id = self::find();
				if ( is_wp_error( $id ) ) {
					return $id;
				}
				if ( $id && 'trash' === get_post_status( $id ) ) {
					// Restore the exact authored content, not a new copy. Normal readiness still applies.
					$status = get_post_meta( $id, '_wp_trash_meta_status', true );
					if ( ! wp_untrash_post( $id ) ) {
						return self::error();
					}
					if ( 'publish' === $status && is_wp_error(
						wp_update_post(
							array(
								'ID'          => $id,
								'post_status' => 'publish',
							),
							true
						)
					) ) {
						return self::error();
					}
				} elseif ( ! $id ) {
					$id = $this->create();
					if ( is_wp_error( $id ) ) {
						return $id;
					}
				}
				update_option( self::OPTION, (string) $id, false );
				update_option( Onboarding::OPTION, 'installed', false );
				if ( (string) get_option( self::OPTION ) !== (string) $id || 'installed' !== get_option( Onboarding::OPTION ) ) {
					return self::error();
				}
				return $id;
			},
			true
		);
		foreach ( array( self::OPTION, Onboarding::OPTION, 'alloptions', 'notoptions' ) as $key ) {
			wp_cache_delete( $key, 'options' );
		}
		return $result;
	}

	/**
	 * Build through the same draft/Quick Add/Meta validation as native authoring.
	 * Caller owns the content transaction. No handcrafted linear metadata.
	 *
	 * @return int|\WP_Error
	 */
	private function create() {
		$id = wp_insert_post(
			wp_slash(
				array(
					'post_type'     => 'sprint_engine_sprint',
					'post_status'   => 'draft',
					'post_title'    => __( 'Build your first Sprint — Tutorial', 'sprint-engine' ),
					'post_content'  => Content::overview(),
					'post_password' => '',
				)
			),
			true
		);
		if ( is_wp_error( $id ) || ! $id || ! add_post_meta( $id, Marker::KEY, '1', true ) || ! Marker::canonical( $id ) ) {
			return self::error();
		}
		$manager = new StructureManager();
		foreach ( Content::steps() as $definition ) {
			$step = $manager->quick_add( $id, $definition['title'] );
			if ( is_wp_error( $step ) ) {
				return $step;
			}
			$saved = $manager->save_step(
				$step,
				array(
					'_sprint_engine_stage_label' => $definition['stage'],
					'_sprint_engine_mode'        => 'task',
				)
			);
			if ( is_wp_error( $saved ) || is_wp_error(
				wp_update_post(
					wp_slash(
						array(
							'ID'            => $step,
							'post_content'  => $definition['body'],
							'post_status'   => 'publish',
							'post_password' => '',
						)
					),
					true
				)
			) ) {
				return self::error();
			}
			clean_post_cache( $step );
			$post = get_post( $step );
			if ( ! $post || $definition['body'] !== $post->post_content || 'publish' !== $post->post_status || '' !== $post->post_password ) {
				return self::error();
			}
		}
		$saved = Meta::save(
			$id,
			array(
				'_sprint_engine_launchable'         => true,
				'_sprint_engine_completion_message' => Content::completion(),
			)
		);
		if ( is_wp_error( $saved ) || is_wp_error(
			wp_update_post(
				array(
					'ID'          => $id,
					'post_status' => 'publish',
				),
				true
			)
		) ) {
			return self::error();
		}
		clean_post_cache( $id );
		return '' === Availability::reason( get_post( $id ) ) && Marker::canonical( $id ) && 6 === count( Meta::steps( $id ) ) ? $id : self::error();
	}

	/**
	 * Clear retry outcome without internal database or filesystem details.
	 *
	 * @return \WP_Error
	 */
	private static function error() {
		return new \WP_Error( 'sprint_engine_tutorial_install', __( 'The tutorial could not be installed safely. Retry from Settings. If this continues, ask an administrator to check database transaction support and the tutorial marker and installation identity.', 'sprint-engine' ) );
	}
}
