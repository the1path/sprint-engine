<?php
/**
 * Linear authoring operations over the existing explicit content metadata.
 *
 * @package SprintEngine
 */

namespace ThePath\SprintEngine\Content;

defined( 'ABSPATH' ) || exit;

/** Validates complete orders, derives links, and commits verified structures. */
final class StructureManager {

	/**
	 * Objects whose caches must be cleared after transaction completion.
	 *
	 * @var int[]
	 */
	private $touched = array();

	/**
	 * Detach and trash a Step, retaining authored content and historical progress.
	 *
	 * @param int    $sprint Sprint ID.
	 * @param int    $step Step ID.
	 * @param string $revision Required browser snapshot.
	 * @return true|\WP_Error
	 */
	public function trash_step( $sprint, $step, $revision ) {
		if ( ! is_int( $step ) || $step <= 0 || ! is_string( $revision ) || '' === $revision ) {
			return self::error( __( 'Choose a valid Step and reload the saved structure.', 'sprint-engine' ) );
		}
		if ( ! EMPTY_TRASH_DAYS ) {
			return self::error( __( 'WordPress Trash must be enabled before removing Steps here.', 'sprint-engine' ) );
		}
		return $this->transaction(
			array( $sprint ),
			function () use ( $sprint, $step, $revision ) {
				clean_post_cache( $step );
				$order = wp_list_pluck( Meta::steps( $sprint ), 'ID' );
				$valid = $this->validate_order( $sprint, $order, $revision );
				if ( is_wp_error( $valid ) ) {
					return $valid;
				}
				if ( ! in_array( $step, $order, true ) || ! Meta::belongs_to( $step, $sprint ) || ! current_user_can( 'edit_post', $step ) || ! current_user_can( 'delete_post', $step ) ) {
					return self::error( __( 'You cannot remove this Step from this Sprint.', 'sprint-engine' ), 403 );
				}
				$this->touched[] = $step;
				foreach ( array( '_sprint_engine_sprint_id', '_sprint_engine_position', '_sprint_engine_next_step_id' ) as $key ) {
					delete_post_meta( $step, $key );
					wp_cache_delete( $step, 'post_meta' );
					if ( metadata_exists( 'post', $step, $key ) ) {
						return $this->write_error();
					}
				}
				$result = $this->persist_order( $sprint, array_values( array_diff( $order, array( $step ) ) ) );
				if ( is_wp_error( $result ) ) {
					return $result;
				}
				// Core trash hooks run inside this transaction; external side effects cannot roll back.
				if ( ! wp_trash_post( $step ) || 'trash' !== get_post_status( $step ) ) {
					return $this->write_error();
				}
				foreach ( array( '_sprint_engine_sprint_id', '_sprint_engine_position', '_sprint_engine_next_step_id' ) as $key ) {
					if ( metadata_exists( 'post', $step, $key ) ) {
						return $this->write_error();
					}
				}
				$remaining = array_values( array_diff( $order, array( $step ) ) );
				if ( wp_list_pluck( Meta::steps( $sprint ), 'ID' ) !== $remaining || ( $remaining && ! self::is_linear( $sprint ) ) || ( ! $remaining && ( get_post_meta( $sprint, '_sprint_engine_start_step_id', true ) || get_post_meta( $sprint, '_sprint_engine_launchable', true ) ) ) ) {
					return $this->write_error();
				}
				return true;
			}
		);
	}

	/**
	 * Apply an exact permutation of all active associated Steps.
	 *
	 * @param int         $sprint Sprint ID.
	 * @param array       $order Ordered Step IDs.
	 * @param string|null $revision Optional browser snapshot for stale edit detection.
	 * @return true|\WP_Error
	 */
	public function apply_linear_order( $sprint, $order, $revision = null ) {
		return $this->transaction(
			array( $sprint ),
			function () use ( $sprint, $order, $revision ) {
				$valid = $this->validate_order( $sprint, $order, $revision );
				return is_wp_error( $valid ) ? $valid : $this->persist_order( $sprint, $valid );
			}
		);
	}

	/**
	 * Create a draft and append it to the current saved display order.
	 *
	 * @param int         $sprint Sprint ID.
	 * @param string      $title New title.
	 * @param string|null $revision Optional browser snapshot.
	 * @return int|\WP_Error Created Step ID.
	 */
	public function quick_add( $sprint, $title, $revision = null ) {
		if ( ! is_string( $title ) || '' === trim( sanitize_text_field( $title ) ) ) {
			return self::error( __( 'Enter a Step title.', 'sprint-engine' ) );
		}
		$title = trim( sanitize_text_field( $title ) );
		return $this->transaction(
			array( $sprint ),
			function () use ( $sprint, $title, $revision ) {
				$order = wp_list_pluck( Meta::steps( $sprint ), 'ID' );
				$valid = $this->validate_order( $sprint, $order, $revision );
				if ( is_wp_error( $valid ) ) {
					return $valid;
				}
				$step = wp_insert_post(
					wp_slash(
						array(
							'post_type'   => 'sprint_engine_step',
							'post_status' => 'draft',
							'post_title'  => $title,
						)
					),
					true
				);
				if ( is_wp_error( $step ) || ! $step ) {
					return self::error( __( 'The Step could not be created. Reload and retry.', 'sprint-engine' ) );
				}
				$this->touched[] = $step;
				if ( ! $this->write( $step, '_sprint_engine_sprint_id', $sprint ) || ! $this->write( $step, '_sprint_engine_mode', 'content' ) ) {
					return $this->write_error();
				}
				$order[] = $step;
				$result  = $this->persist_order( $sprint, $order );
				return is_wp_error( $result ) ? $result : $step;
			}
		);
	}

	/**
	 * Save individual Step fields; a changed parent repairs both linear lists.
	 * Position/next inputs are deliberately excluded from the normal metabox.
	 *
	 * @param int   $step Step ID.
	 * @param array $input Editable Step fields.
	 * @return true|\WP_Error
	 */
	public function save_step( $step, $input ) {
		if ( ! current_user_can( 'manage_options' ) || ! Meta::valid_post( $step, 'sprint_engine_step' ) || ! current_user_can( 'edit_post', $step ) ) {
			return self::error( __( 'You cannot edit this Step.', 'sprint-engine' ), 403 );
		}
		$input = array_intersect_key( $input, array_flip( array( '_sprint_engine_sprint_id', '_sprint_engine_stage_label', '_sprint_engine_estimated_minutes', '_sprint_engine_mode' ) ) );
		$state = Meta::read( $step, 'sprint_engine_step' );
		$old   = $state['_sprint_engine_sprint_id'];
		// An internal unset value is not an explicitly submitted zero estimate.
		if ( 0 === $state['_sprint_engine_estimated_minutes'] && ! array_key_exists( '_sprint_engine_estimated_minutes', $input ) ) {
			$state['_sprint_engine_estimated_minutes'] = '';
		}
		// Validate scalar fields and the destination independently of old links.
		$values = Meta::validate(
			0,
			'sprint_engine_step',
			array_merge(
				$state,
				$input,
				array(
					'_sprint_engine_position'     => 0,
					'_sprint_engine_next_step_id' => 0,
				)
			)
		);
		if ( is_wp_error( $values ) ) {
			return $values;
		}
		$new = $values['_sprint_engine_sprint_id'];
		if ( $old === $new ) {
			return Meta::save( $step, $input );
		}
		return $this->transaction(
			array_values( array_filter( array_unique( array( $old, $new ) ) ) ),
			function () use ( $step, $old, $new, $values ) {
				clean_post_cache( $step );
				if ( (int) get_post_meta( $step, '_sprint_engine_sprint_id', true ) !== $old ) {
					return self::error( __( 'This Step changed elsewhere. Reload before moving it.', 'sprint-engine' ), 409 );
				}
				$old_order = $old ? wp_list_pluck( Meta::steps( $old ), 'ID' ) : array();
				$new_order = $new ? wp_list_pluck( Meta::steps( $new ), 'ID' ) : array();
				foreach ( array(
					$old => $old_order,
					$new => $new_order,
				) as $sprint => $order ) {
					if ( $sprint ) {
						$valid = $this->validate_order( $sprint, $order );
						if ( is_wp_error( $valid ) ) {
							return $valid;
						}
					}
				}
				foreach ( $values as $key => $value ) {
					if ( ! $this->write( $step, $key, $value ) ) {
						return $this->write_error();
					}
				}
				if ( $old ) {
					$result = $this->persist_order( $old, array_values( array_diff( $old_order, array( $step ) ) ) );
					if ( is_wp_error( $result ) ) {
						return $result;
					}
				}
				if ( $new ) {
					$new_order[] = $step;
					return $this->persist_order( $new, $new_order );
				}
				return true;
			}
		);
	}

	/**
	 * Fingerprint structural state, not unrelated title/content edits.
	 *
	 * @param int $sprint Sprint ID.
	 * @return string
	 */
	public static function revision( $sprint ) {
		$state = array( (int) get_post_meta( $sprint, '_sprint_engine_start_step_id', true ) );
		foreach ( Meta::steps( $sprint ) as $step ) {
			$state[] = array( $step->ID, (int) get_post_meta( $step->ID, '_sprint_engine_position', true ), (int) get_post_meta( $step->ID, '_sprint_engine_next_step_id', true ) );
		}
		return hash( 'sha256', wp_json_encode( $state ) );
	}

	/**
	 * Check the stored single chain against the complete position-ordered list.
	 *
	 * @param int      $sprint Sprint ID.
	 * @param int|null $start Candidate start, or stored start when omitted.
	 * @return bool
	 */
	public static function is_linear( $sprint, $start = null ) {
		$steps = wp_list_pluck( Meta::steps( $sprint ), 'ID' );
		$start = null === $start ? (int) get_post_meta( $sprint, '_sprint_engine_start_step_id', true ) : $start;
		if ( ! $steps || $steps[0] !== $start ) {
			return false;
		}
		foreach ( $steps as $index => $step ) {
			if ( (int) get_post_meta( $step, '_sprint_engine_position', true ) !== $index + 1 || (int) get_post_meta( $step, '_sprint_engine_next_step_id', true ) !== ( $steps[ $index + 1 ] ?? 0 ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Reject malformed IDs, duplicates, omissions, foreign and stale membership.
	 *
	 * @param int         $sprint Sprint ID.
	 * @param array       $order Requested order.
	 * @param string|null $revision Optional snapshot.
	 * @return array|\WP_Error Normalized order.
	 */
	private function validate_order( $sprint, $order, $revision = null ) {
		if ( ! is_array( $order ) || array_values( $order ) !== $order ) {
			return self::error( __( 'Submit the complete ordered list of Steps.', 'sprint-engine' ) );
		}
		$ids = array();
		foreach ( $order as $id ) {
			if ( ( ! is_int( $id ) && ! is_string( $id ) ) || false === filter_var( $id, FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 1 ) ) ) ) {
				return self::error( __( 'Step IDs must be positive integers.', 'sprint-engine' ) );
			}
			$id = (int) $id;
			if ( isset( $ids[ $id ] ) || ! Meta::belongs_to( $id, $sprint ) || ! current_user_can( 'edit_post', $id ) ) {
				return self::error( __( 'Every Step must be editable, unique and belong to this Sprint.', 'sprint-engine' ) );
			}
			$ids[ $id ] = $id;
		}
		$members = wp_list_pluck( Meta::steps( $sprint ), 'ID' );
		if ( count( $members ) !== count( $ids ) || array_diff( $members, $ids ) || ( null !== $revision && ( ! is_string( $revision ) || ! hash_equals( self::revision( $sprint ), $revision ) ) ) ) {
			return self::error( __( 'The Sprint structure changed. Reload the editor and try again with all associated Steps.', 'sprint-engine' ), 409 );
		}
		return array_values( $ids );
	}

	/**
	 * Persist only derived metadata and verify the complete result before commit.
	 *
	 * @param int   $sprint Sprint ID.
	 * @param array $order Validated order.
	 * @return true|\WP_Error
	 */
	private function persist_order( $sprint, $order ) {
		foreach ( $order as $index => $step ) {
			if ( ! $this->write( $step, '_sprint_engine_position', $index + 1 ) || ! $this->write( $step, '_sprint_engine_next_step_id', $order[ $index + 1 ] ?? 0 ) ) {
				return $this->write_error();
			}
		}
		if ( ! $this->write( $sprint, '_sprint_engine_start_step_id', $order[0] ?? 0 ) || ( ! $order && ! $this->write( $sprint, '_sprint_engine_launchable', false ) ) ) {
			return $this->write_error();
		}
		if ( $order && ! self::is_linear( $sprint ) ) {
			return $this->write_error();
		}
		return true;
	}

	/**
	 * Write through WordPress and verify fresh metadata, including no-op writes.
	 *
	 * @param int    $id Post ID.
	 * @param string $key Meta key.
	 * @param mixed  $value Desired value.
	 * @return bool
	 */
	private function write( $id, $key, $value ) {
		$this->touched[] = $id;
		if ( 0 === $value && '_sprint_engine_position' !== $key ) {
			delete_post_meta( $id, $key );
		} else {
			update_post_meta( $id, $key, wp_slash( $value ) );
		}
		wp_cache_delete( $id, 'post_meta' );
		$stored = Meta::read( $id, get_post_type( $id ) );
		return isset( $stored[ $key ] ) && $stored[ $key ] === $value;
	}

	/**
	 * Serialize manager operations on Sprint rows and roll back failures.
	 * Requires transactional WordPress content tables; never changes their schema.
	 *
	 * @param array    $sprints Sprint IDs to lock in ascending order.
	 * @param callable $operation Operation returning a value or WP_Error.
	 * @return mixed
	 * @throws \RuntimeException Internally caught and converted to a safe error.
	 */
	private function transaction( $sprints, $operation ) {
		global $wpdb;
		foreach ( $sprints as $sprint ) {
			if ( ! is_int( $sprint ) || ! Meta::valid_post( $sprint, 'sprint_engine_sprint' ) || ! current_user_can( 'manage_options' ) || ! current_user_can( 'edit_post', $sprint ) ) {
				return self::error( __( 'You cannot manage this Sprint. Save it as a draft first.', 'sprint-engine' ), 403 );
			}
		}
		$tables = $wpdb->get_results( $wpdb->prepare( 'SHOW TABLE STATUS WHERE Name IN (%s, %s)', $wpdb->posts, $wpdb->postmeta ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Verify rollback support before structural writes.
		if ( ! is_array( $tables ) || 2 !== count( $tables ) || array_filter(
			$tables,
			static function ( $table ) {
				return 'innodb' !== strtolower( $table['Engine'] ?? '' );
			}
		) ) {
			return self::error( __( 'Structure changes require transactional WordPress content tables. Ask your site administrator to check the database storage engine.', 'sprint-engine' ), 500 );
		}
		if ( false === $wpdb->query( 'START TRANSACTION' ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			return $this->write_error();
		}
		$this->touched = $sprints;
		sort( $sprints, SORT_NUMERIC );
		try {
			foreach ( $sprints as $sprint ) {
				// A no-op write acquires a row lock on MySQL and a write lock on SQLite.
				if ( false === $wpdb->query( $wpdb->prepare( "UPDATE $wpdb->posts SET ID = ID WHERE ID = %d", $sprint ) ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
					throw new \RuntimeException();
				}
				clean_post_cache( $sprint );
				if ( ! Meta::valid_post( $sprint, 'sprint_engine_sprint' ) ) {
					throw new \RuntimeException();
				}
				foreach ( Meta::steps( $sprint ) as $step ) {
					clean_post_cache( $step->ID );
				}
			}
			$result = $operation();
			if ( ! is_wp_error( $result ) && false === $wpdb->query( 'COMMIT' ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$result = $this->write_error();
			}
		} catch ( \Throwable $exception ) {
			$result = $this->write_error();
		}
		if ( is_wp_error( $result ) ) {
			$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		foreach ( array_unique( $this->touched ) as $id ) {
			clean_post_cache( $id );
			wp_cache_delete( $id, 'post_meta' );
		}
		wp_cache_set_posts_last_changed();
		return $result;
	}

	/**
	 * Generic persistence error without internal details.
	 *
	 * @return \WP_Error
	 */
	private function write_error() {
		return self::error( __( 'The complete structure could not be saved. Reload to check the saved state before retrying.', 'sprint-engine' ), 500 );
	}

	/**
	 * Build a safe operation error.
	 *
	 * @param string $message Localized message.
	 * @param int    $status HTTP status.
	 * @return \WP_Error
	 */
	private static function error( $message, $status = 400 ) {
		return new \WP_Error( 'sprint_engine_structure_error', $message, array( 'status' => $status ) );
	}
}
