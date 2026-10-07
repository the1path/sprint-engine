<?php
/**
 * Authenticated member write adapters.
 *
 * @package SprintEngine
 */

namespace ThePath\SprintEngine\Rest;

use ThePath\SprintEngine\Access\AccessManager;
use ThePath\SprintEngine\Content\Meta;
use ThePath\SprintEngine\Progress\ProgressService;
use ThePath\SprintEngine\Runner\Availability;
use ThePath\SprintEngine\Runner\Routes;

defined( 'ABSPATH' ) || exit;

/** Delegates every progress transition to ProgressService. */
final class Controller {

	/** Register routes at the WordPress REST lifecycle boundary. */
	public function register_hooks() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_filter( 'rest_post_dispatch', array( $this, 'no_store' ), 10, 3 );
	}

	/**
	 * Protect successes and errors, including core authentication failures.
	 *
	 * @param \WP_REST_Response $response Prepared REST response.
	 * @param \WP_REST_Server   $server REST server.
	 * @param \WP_REST_Request  $request Request being served.
	 * @return \WP_REST_Response
	 */
	public function no_store( $response, $server, $request ) {
		if ( 0 === strpos( $request->get_route(), '/sprint-engine/v1/' ) ) {
			$response->header( 'Cache-Control', 'private, no-store, no-cache, must-revalidate, max-age=0' );
		}
		return $response;
	}

	/** Register the three member POST operations. */
	public function register_routes() {
		$operations = array(
			'start'    => 'sprints',
			'restart'  => 'sprints',
			'complete' => 'steps',
		);
		foreach ( $operations as $operation => $resource ) {
			register_rest_route(
				'sprint-engine/v1',
				'/' . $resource . '/(?P<id>[^/]+)/' . $operation,
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, $operation ),
					'permission_callback' => array( $this, 'permissions' ),
				)
			);
		}
	}

	/**
	 * Require the current identity and a standard REST nonce, including dispatches.
	 * WordPress also performs its normal cookie authentication before this check.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return true|\WP_Error
	 */
	public function permissions( $request ) {
		if ( ! get_current_user_id() ) {
			return $this->error( 401 );
		}
		if ( ! wp_verify_nonce( $request->get_header( 'X-WP-Nonce' ), 'wp_rest' ) ) {
			return $this->error( 403 );
		}
		return true;
	}

	/**
	 * Start or return the existing canonical state.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function start( $request ) {
		return $this->write( $request, 'start' );
	}

	/**
	 * Restart the authenticated member's completed Sprint.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function restart( $request ) {
		return $this->write( $request, 'restart' );
	}

	/**
	 * Complete a Step using its persisted parent Sprint.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function complete( $request ) {
		return $this->write( $request, 'complete' );
	}

	/**
	 * Validate transport/content/access boundaries and project the service result.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @param string           $operation Validated route operation.
	 * @return \WP_REST_Response|\WP_Error
	 */
	private function write( $request, $operation ) {
		$complete   = 'complete' === $operation;
		$permission = $this->permissions( $request );
		if ( is_wp_error( $permission ) ) {
			return $permission;
		}
		// URL parameters alone select the resource; JSON/query IDs cannot override it.
		$id = $request->get_url_params()['id'] ?? null;
		if ( ! is_string( $id ) || ! preg_match( '/^[1-9][0-9]*$/D', $id ) || false === filter_var( $id, FILTER_VALIDATE_INT ) ) {
			return $this->error( 400 );
		}
		$id = (int) $id;
		if ( $complete ) {
			$step   = get_post( $id );
			$parent = get_post_meta( $id, '_sprint_engine_sprint_id', true );
			if ( ! $step || 'sprint_engine_step' !== $step->post_type || '' !== $step->post_password || ! Meta::belongs_to( $id, (int) $parent ) ) {
				return $this->error( 404 );
			}
			$sprint = get_post( (int) $parent );
		} else {
			$sprint = get_post( $id );
		}
		if ( '' !== Availability::reason( $sprint ) ) {
			return $this->error( 404 );
		}
		$user = get_current_user_id();
		if ( ! ( new AccessManager() )->can_access( $user, $sprint->ID ) ) {
			return $this->error( 403 );
		}
		$service = new ProgressService();
		$state   = $complete ? $service->complete_step( $user, $sprint->ID, $id ) : ( 'restart' === $operation ? $service->restart_sprint( $user, $sprint->ID ) : $service->start_sprint( $user, $sprint->ID ) );
		if ( is_wp_error( $state ) ) {
			$statuses = array(
				'sprint_engine_progress_user'           => 401,
				'sprint_engine_progress_sprint'         => 404,
				'sprint_engine_progress_step'           => 404,
				'sprint_engine_progress_not_launchable' => 404,
				'sprint_engine_progress_structure'      => 404,
				'sprint_engine_progress_state'          => 409,
				'sprint_engine_progress_restart_unavailable' => 409,
				'sprint_engine_progress_out_of_order'   => 409,
				'sprint_engine_progress_persistence'    => 503,
			);
			return $this->error( $statuses[ $state->get_error_code() ] ?? 503 );
		}
		$data = array(
			'success'         => true,
			'sprint_id'       => $sprint->ID,
			'state'           => $state['status'],
			'runner_url'      => Routes::url( $sprint->post_name ),
			'progress'        => array_intersect_key( $state, array_flip( array( 'completed_steps', 'total_steps', 'percentage' ) ) ),
			'current_step_id' => $state['current_step_id'],
			'attempt_id'      => $state['attempt_id'],
			'attempt_number'  => $state['attempt_number'],
		);
		if ( $complete ) {
			$data['completed_step_id'] = $id;
			$data['sprint_completed']  = 'completed' === $state['status'];
		}
		return new \WP_REST_Response( $data, 200, array( 'Cache-Control' => 'private, no-store' ) );
	}

	/**
	 * Allowlisted public errors never forward internal service details.
	 *
	 * @param int $status HTTP status.
	 * @return \WP_Error
	 */
	private function error( $status ) {
		$messages = array(
			400 => __( 'Supply a valid positive identifier.', 'sprint-engine' ),
			401 => __( 'Sign in and reload the Sprint to continue.', 'sprint-engine' ),
			403 => __( 'This action is not permitted. Reload the Sprint and try again.', 'sprint-engine' ),
			404 => __( 'This Sprint or Step is unavailable.', 'sprint-engine' ),
			409 => __( 'Progress has changed. Reload the Sprint and try again.', 'sprint-engine' ),
			503 => __( 'Progress could not be saved safely. Try again or reload the Sprint.', 'sprint-engine' ),
		);
		return new \WP_Error( 'sprint_engine_write_' . $status, $messages[ $status ], array( 'status' => $status ) );
	}
}
