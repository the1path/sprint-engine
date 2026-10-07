<?php
/**
 * Internal linear Sprint lifecycle, independent of transport and presentation.
 * Future member callers MUST authorize through AccessManager before calling.
 *
 * @package SprintEngine
 */

namespace ThePath\SprintEngine\Progress;

use ThePath\SprintEngine\Content\Meta;
use ThePath\SprintEngine\Content\StructureManager;

defined( 'ABSPATH' ) || exit;

/** Owns progress transitions; never checks commerce or membership entitlements. */
final class ProgressService {

	/**
	 * Canonical enrolment persistence.
	 *
	 * @var EnrolmentRepository
	 */
	private $enrolments;
	/**
	 * Step persistence.
	 *
	 * @var StepProgressRepository
	 */
	private $steps;
	/**
	 * UTC clock returning a database datetime string.
	 *
	 * @var callable
	 */
	private $clock;

	/**
	 * Create the internal service with an optional deterministic UTC clock.
	 *
	 * @param callable|null $clock UTC clock; defaults to WordPress UTC time.
	 */
	public function __construct( $clock = null ) {
		$this->enrolments = new EnrolmentRepository();
		$this->steps      = new StepProgressRepository();
		$this->clock      = $clock ?? static function () {
			return current_time( 'mysql', true );
		};
	}

	/**
	 * Start once, or return existing state without resetting timestamps/progress.
	 *
	 * @param int $user User ID.
	 * @param int $sprint Sprint ID.
	 * @return array|\WP_Error
	 */
	public function start_sprint( $user, $sprint ) {
		return $this->operate( 'start', $user, $sprint );
	}

	/**
	 * Restart a completed Sprint as a new attempt; retries return an active attempt.
	 *
	 * @param int $user User ID.
	 * @param int $sprint Sprint ID.
	 * @return array|\WP_Error
	 */
	public function restart_sprint( $user, $sprint ) {
		return $this->operate( 'restart', $user, $sprint );
	}

	/**
	 * Read canonical state without creating or repairing progress.
	 *
	 * @param int $user User ID.
	 * @param int $sprint Sprint ID.
	 * @return array|\WP_Error
	 */
	public function get_state( $user, $sprint ) {
		return $this->operate( 'read', $user, $sprint );
	}

	/**
	 * Read the same canonical state and deterministic progress calculation.
	 *
	 * @param int $user User ID.
	 * @param int $sprint Sprint ID.
	 * @return array|\WP_Error
	 */
	public function get_progress( $user, $sprint ) {
		return $this->get_state( $user, $sprint );
	}

	/**
	 * Complete only the current Step; completed-Step retries return current state.
	 *
	 * @param int $user User ID.
	 * @param int $sprint Sprint ID.
	 * @param int $step Step ID.
	 * @return array|\WP_Error
	 */
	public function complete_step( $user, $sprint, $step ) {
		return $this->operate( 'complete', $user, $sprint, $step );
	}

	/**
	 * Resume or repair a stale pointer, preserving completed history.
	 *
	 * @param int $user User ID.
	 * @param int $sprint Sprint ID.
	 * @return array|\WP_Error
	 */
	public function resume_sprint( $user, $sprint ) {
		return $this->operate( 'resume', $user, $sprint );
	}

	/**
	 * Validate identifiers, serialize transitions, then publish committed events.
	 *
	 * @param string $operation Operation name.
	 * @param int    $user User ID.
	 * @param int    $sprint Sprint ID.
	 * @param int    $step Optional completion Step.
	 * @return array|\WP_Error
	 */
	private function operate( $operation, $user, $sprint, $step = 0 ) {
		if ( ! $this->valid_id( $user ) || ! get_userdata( (int) $user ) ) {
			return new \WP_Error( 'sprint_engine_progress_user', __( 'The user does not exist.', 'sprint-engine' ) );
		}
		if ( ! $this->valid_id( $sprint ) || ! Meta::valid_post( (int) $sprint, 'sprint_engine_sprint' ) ) {
			return new \WP_Error( 'sprint_engine_progress_sprint', __( 'The Sprint is unavailable.', 'sprint-engine' ) );
		}
		if ( 'complete' === $operation && ! $this->valid_id( $step ) ) {
			return new \WP_Error( 'sprint_engine_progress_step', __( 'Supply a valid Step ID.', 'sprint-engine' ) );
		}
		$user   = (int) $user;
		$sprint = (int) $sprint;
		$step   = (int) $step;
		$events = array();
		$repair = false;
		$result = ( new Transaction() )->run(
			$sprint,
			function () use ( $operation, $user, $sprint, $step, &$events, &$repair ) {
				$chain = $this->chain( $sprint );
				if ( is_wp_error( $chain ) ) {
					return $chain;
				}
				$row = $this->enrolments->find( $user, $sprint );
				if ( $row && ! in_array( $row['status'], array( 'not_started', 'in_progress', 'completed' ), true ) ) {
					return $this->state_error();
				}
				$now = call_user_func( $this->clock );
				if ( 'restart' === $operation ) {
					if ( ! Meta::read( $sprint, 'sprint_engine_sprint' )['_sprint_engine_launchable'] ) {
						return new \WP_Error( 'sprint_engine_progress_not_launchable', __( 'This Sprint is not launchable.', 'sprint-engine' ) );
					}
					if ( ! $row || 'not_started' === $row['status'] ) {
						return new \WP_Error( 'sprint_engine_progress_restart_unavailable', __( 'Complete this Sprint before restarting it.', 'sprint-engine' ) );
					}
					if ( 'in_progress' === $row['status'] ) {
						return $this->state( $user, $sprint, $chain );
					}
				}
				$attempt = $row['attempt_number'] ?? 1;
				if ( ( 'start' === $operation && ( ! $row || 'not_started' === $row['status'] ) ) || 'restart' === $operation ) {
					if ( ! Meta::read( $sprint, 'sprint_engine_sprint' )['_sprint_engine_launchable'] ) {
						return new \WP_Error( 'sprint_engine_progress_not_launchable', __( 'This Sprint is not launchable.', 'sprint-engine' ) );
					}
					// Never reset historical progress in a corrupt not-started enrolment.
					if ( 'start' === $operation && ( $this->steps->completed_ids( $user, $sprint, $attempt ) || ( $row && ( $row['started_at'] || $row['completed_at'] ) ) ) ) {
						return $this->state_error();
					}
					$fields = array(
						'status'           => 'in_progress',
						'current_step_id'  => $chain[0],
						'started_at'       => $now,
						'last_activity_at' => $now,
						'completed_at'     => null,
						'updated_at'       => $now,
					);
					if ( $row && 'start' === $operation ) {
						$this->enrolments->update( $row['id'], $fields );
					} else {
						$this->enrolments->create(
							array_merge(
								$fields,
								array(
									'attempt_number' => 'restart' === $operation ? $attempt + 1 : 1,
									'user_id'        => $user,
									'sprint_id'      => $sprint,
									'created_at'     => $now,
								)
							)
						);
					}
					$row      = $this->enrolments->find( $user, $sprint );
					$events[] = array( 'sprint_engine/sprint_started', $user, $sprint, $row['id'], $row['attempt_number'] );
					$this->enter_step( $row, $user, $sprint, $chain[0], $now, $events );
				} elseif ( 'complete' === $operation ) {
					$result = $this->complete( $row, $user, $sprint, $step, $chain, $now, $events );
					if ( is_wp_error( $result ) ) {
						return $result;
					}
				} elseif ( 'resume' === $operation && $row && 'in_progress' === $row['status'] ) {
					$repair = $this->resume( $row, $user, $sprint, $chain, $now, $events );
				}
				return $this->state( $user, $sprint, $chain );
			}
		);
		if ( ! is_wp_error( $result ) ) {
			if ( $repair ) {
				error_log( 'Sprint Engine repaired a stale enrolment resume state.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Required generic anomaly record, no personal data.
			}
			foreach ( $events as $event ) {
				// Events intentionally run outside the transaction and its error handler.
				do_action( ...$event );
			}
		}
		return $result;
	}

	/**
	 * Validate using SE-002.1, then traverse explicit links for runtime navigation.
	 *
	 * @param int $sprint Sprint ID.
	 * @return int[]|\WP_Error
	 */
	private function chain( $sprint ) {
		if ( ! Meta::valid_post( $sprint, 'sprint_engine_sprint' ) || ! StructureManager::is_linear( $sprint ) ) {
			return new \WP_Error( 'sprint_engine_progress_structure', __( 'The Sprint structure is invalid. Ask an administrator to review and save its Step order.', 'sprint-engine' ) );
		}
		$chain = array();
		$start = get_post_meta( $sprint, '_sprint_engine_start_step_id', true );
		if ( ! $this->valid_id( $start ) ) {
			return $this->state_error();
		}
		$step = (int) $start;
		while ( $step ) {
			if ( isset( $chain[ $step ] ) || ! Meta::belongs_to( $step, $sprint ) || ! in_array( Meta::read( $step, 'sprint_engine_step' )['_sprint_engine_mode'], array( 'content', 'task' ), true ) ) {
				return $this->state_error();
			}
			$parent   = get_post_meta( $step, '_sprint_engine_sprint_id', true );
			$position = get_post_meta( $step, '_sprint_engine_position', true );
			$next     = get_post_meta( $step, '_sprint_engine_next_step_id', true );
			if ( ! $this->valid_id( $parent ) || ! $this->valid_id( $position ) || ( ! in_array( $next, array( '', 0, '0', null ), true ) && ! $this->valid_id( $next ) ) ) {
				return $this->state_error();
			}
			$chain[ $step ] = $step;
			$step           = (int) $next;
		}
		return array_values( $chain );
	}

	/**
	 * Persist one explicit completion and its next-Step or terminal transition.
	 *
	 * @param array|null $row Enrolment.
	 * @param int        $user User ID.
	 * @param int        $sprint Sprint ID.
	 * @param int        $step Submitted Step.
	 * @param array      $chain Valid chain.
	 * @param string     $now UTC time.
	 * @param array      $events Post-commit events.
	 * @return true|\WP_Error
	 */
	private function complete( $row, $user, $sprint, $step, $chain, $now, &$events ) {
		if ( ! in_array( $step, $chain, true ) || ! Meta::belongs_to( $step, $sprint ) ) {
			return new \WP_Error( 'sprint_engine_progress_step', __( 'This Step is not in the Sprint.', 'sprint-engine' ) );
		}
		if ( ! $row || ! in_array( $row['status'], array( 'in_progress', 'completed' ), true ) ) {
			return $this->state_error();
		}
		$completed = $this->steps->completed_ids( $user, $sprint, $row['attempt_number'] );
		if ( in_array( $step, $completed, true ) ) {
			return true;
		}
		if ( 'in_progress' !== $row['status'] || $row['current_step_id'] !== $step ) {
			return new \WP_Error( 'sprint_engine_progress_out_of_order', __( 'Only the current Step can be completed. Resume the Sprint and try again.', 'sprint-engine' ) );
		}
		$this->enter_step( $row, $user, $sprint, $step, $now, $events );
		EnrolmentRepository::check( $this->steps->complete( $user, $sprint, $step, $now, $row['attempt_number'] ) );
		$events[] = array( 'sprint_engine/step_completed', $user, $sprint, $step, $row['id'], $row['attempt_number'] );
		$next     = (int) get_post_meta( $step, '_sprint_engine_next_step_id', true );
		if ( $next ) {
			if ( ! in_array( $next, $chain, true ) || in_array( $next, $completed, true ) ) {
				return $this->state_error();
			}
			$this->enrolments->update(
				$row['id'],
				array(
					'current_step_id'  => $next,
					'last_activity_at' => $now,
					'updated_at'       => $now,
				)
			);
			$this->enter_step( $row, $user, $sprint, $next, $now, $events );
		} else {
			if ( end( $chain ) !== $step || array_diff( $chain, array_merge( $completed, array( $step ) ) ) ) {
				return $this->state_error();
			}
			$this->finish( $row, $user, $sprint, $now, $events );
		}
		return true;
	}

	/**
	 * Recover against a valid chain; never delete historical progress.
	 *
	 * @param array  $row Enrolment.
	 * @param int    $user User ID.
	 * @param int    $sprint Sprint ID.
	 * @param array  $chain Valid chain.
	 * @param string $now UTC time.
	 * @param array  $events Post-commit events.
	 * @return bool Whether an anomaly was repaired.
	 */
	private function resume( $row, $user, $sprint, $chain, $now, &$events ) {
		$incomplete = array_values( array_diff( $chain, $this->steps->completed_ids( $user, $sprint, $row['attempt_number'] ) ) );
		if ( ! $incomplete ) {
			$this->finish( $row, $user, $sprint, $now, $events );
			return true;
		}
		$repair  = ! in_array( $row['current_step_id'], $incomplete, true );
		$current = $repair ? $incomplete[0] : $row['current_step_id'];
		$this->enrolments->update(
			$row['id'],
			array(
				'current_step_id'  => $current,
				'last_activity_at' => $now,
				'updated_at'       => $now,
			)
		);
		$this->enter_step( $row, $user, $sprint, $current, $now, $events );
		return $repair;
	}

	/**
	 * Start a Step only if its progress row does not already exist.
	 *
	 * @param array  $attempt Current attempt.
	 * @param int    $user User ID.
	 * @param int    $sprint Sprint ID.
	 * @param int    $step Step ID.
	 * @param string $now UTC time.
	 * @param array  $events Post-commit events.
	 * @return void
	 */
	private function enter_step( $attempt, $user, $sprint, $step, $now, &$events ) {
		$row = $this->steps->find( $user, $sprint, $step, $attempt['attempt_number'] );
		if ( ! $row ) {
			$this->steps->create( $user, $sprint, $step, $now, $attempt['attempt_number'] );
			$events[] = array( 'sprint_engine/step_started', $user, $sprint, $step, $attempt['id'], $attempt['attempt_number'] );
		} else {
			EnrolmentRepository::check( 'started' === $row['status'] );
		}
	}

	/**
	 * Finish once, preserving any existing completion timestamp during repair.
	 *
	 * @param array  $row Enrolment.
	 * @param int    $user User ID.
	 * @param int    $sprint Sprint ID.
	 * @param string $now UTC time.
	 * @param array  $events Post-commit events.
	 * @return void
	 */
	private function finish( $row, $user, $sprint, $now, &$events ) {
		$this->enrolments->update(
			$row['id'],
			array(
				'status'           => 'completed',
				'current_step_id'  => null,
				'completed_at'     => $row['completed_at'] ?? $now,
				'last_activity_at' => $now,
				'updated_at'       => $now,
			)
		);
		if ( ! $row['completed_at'] ) {
			$events[] = array( 'sprint_engine/sprint_completed', $user, $sprint, $row['id'], $row['attempt_number'] );
		}
	}

	/**
	 * Project persisted state, counting only currently valid completed Steps.
	 *
	 * @param int   $user User ID.
	 * @param int   $sprint Sprint ID.
	 * @param array $chain Valid chain.
	 * @return array
	 */
	private function state( $user, $sprint, $chain ) {
		$row       = $this->enrolments->find( $user, $sprint );
		$status    = $row['status'] ?? 'not_started';
		$completed = $row ? count( array_intersect( $chain, $this->steps->completed_ids( $user, $sprint, $row['attempt_number'] ) ) ) : 0;
		return array(
			'status'           => $status,
			'sprint_id'        => $sprint,
			'attempt_id'       => $row['id'] ?? null,
			'attempt_number'   => $row['attempt_number'] ?? null,
			'current_step_id'  => 'completed' === $status ? null : ( $row['current_step_id'] ?? null ),
			'completed_steps'  => $completed,
			'total_steps'      => count( $chain ),
			'percentage'       => 'completed' === $status ? 100.0 : round( 100 * $completed / count( $chain ), 2 ),
			'started_at'       => $row['started_at'] ?? null,
			'last_activity_at' => $row['last_activity_at'] ?? null,
			'completed_at'     => $row['completed_at'] ?? null,
		);
	}

	/**
	 * Strictly accept positive integer IDs and their decimal string form.
	 *
	 * @param mixed $id Submitted identifier.
	 * @return bool
	 */
	private function valid_id( $id ) {
		return ( is_int( $id ) || is_string( $id ) ) && false !== filter_var( $id, FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 1 ) ) );
	}

	/**
	 * Recoverable error without internal details.
	 *
	 * @return \WP_Error
	 */
	private function state_error() {
		return new \WP_Error( 'sprint_engine_progress_state', __( 'Progress is inconsistent. Resume the Sprint, or ask an administrator to review its structure.', 'sprint-engine' ) );
	}
}
