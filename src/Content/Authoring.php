<?php
/**
 * Native WordPress metaboxes for linear Sprint authoring.
 *
 * @package SprintEngine
 */

namespace ThePath\SprintEngine\Content;

use ThePath\SprintEngine\Assets;

defined( 'ABSPATH' ) || exit;

/** Presents metadata and advisory structure checks without a visual builder. */
final class Authoring {

	/**
	 * Consumed notice retained for this response's field rendering.
	 *
	 * @var array
	 */
	private $field_error = array();

	/** Register admin hooks. */
	public function register_hooks() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'init', array( $this, 'thumbnail_support' ), 12 );
		add_action( 'add_meta_boxes', array( $this, 'add_boxes' ) );
		add_action( 'save_post_sprint_engine_sprint', array( $this, 'save' ) );
		add_action( 'save_post_sprint_engine_step', array( $this, 'save' ) );
		add_action( 'admin_notices', array( $this, 'notice' ) );
		add_filter( 'redirect_post_location', array( $this, 'notice_redirect' ), 10, 2 );
	}

	/** Load validation only in our native post editors. */
	public function enqueue() {
		$screen = get_current_screen();
		if ( ! $screen || 'post' !== $screen->base || ! in_array( $screen->post_type, array( 'sprint_engine_sprint', 'sprint_engine_step' ), true ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$dependencies = $screen->is_block_editor() ? array( 'wp-dom-ready', 'wp-data', 'wp-editor', 'wp-notices' ) : array( 'wp-dom-ready' );
		wp_enqueue_script( 'sprint-engine-authoring-validation', plugins_url( 'assets/js/authoring-validation.js', dirname( __DIR__, 2 ) . '/sprint-engine.php' ), $dependencies, Assets::version( 'assets/js/authoring-validation.js' ), true );
		wp_localize_script(
			'sprint-engine-authoring-validation',
			'sprintEngineAuthoringValidation',
			array(
				'blockEditor' => $screen->is_block_editor(),
				'maxInteger'  => (string) PHP_INT_MAX,
				'duration'    => __( 'Estimated duration must be a positive number, for example 4 or 4.5, with up to nine whole digits and two decimal places. Leave blank to remove it.', 'sprint-engine' ),
				'minutes'     => __( 'Estimated minutes must be positive whole minutes, for example 15 or 60. Leave blank to remove the estimate.', 'sprint-engine' ),
				'cta'         => __( 'Completion CTA requires both a button label and a valid full http:// or https:// URL. Clear both fields to remove it.', 'sprint-engine' ),
				'notice'      => 'sprint_engine_sprint' === $screen->post_type ? __( 'Sprint Engine has fields that need attention before this Sprint can be saved.', 'sprint-engine' ) : __( 'Sprint Engine has fields that need attention before this Step can be saved.', 'sprint-engine' ),
			)
		);
	}

	/** Enable native image UI for our CPTs while preserving theme choices elsewhere. */
	public function thumbnail_support() {
		$support = get_theme_support( 'post-thumbnails' );
		if ( true === $support ) {
			return;
		}
		$types = is_array( $support ) && isset( $support[0] ) && is_array( $support[0] ) ? $support[0] : array();
		add_theme_support( 'post-thumbnails', array_unique( array_merge( $types, array( 'sprint_engine_sprint', 'sprint_engine_step' ) ) ) );
	}

	/** Add block-editor-compatible native metaboxes. */
	public function add_boxes() {
		add_meta_box( 'sprint_engine_setup', __( 'Sprint setup', 'sprint-engine' ), array( $this, 'setup' ), 'sprint_engine_sprint', 'normal', 'high', array( '__block_editor_compatible_meta_box' => true ) );
		foreach ( array( 'sprint_engine_sprint', 'sprint_engine_step' ) as $type ) {
			add_meta_box( 'sprint_engine_structure', __( 'Sprint structure', 'sprint-engine' ), array( $this, 'render' ), $type, 'normal', 'default', array( '__block_editor_compatible_meta_box' => true ) );
		}
	}

	/**
	 * Render fields and the associated Step list.
	 *
	 * @param \WP_Post $post Editor object.
	 */
	public function render( $post ) {
		$this->notice( true );
		$values = Meta::read( $post->ID, $post->post_type );
		wp_nonce_field( 'sprint_engine_authoring_' . $post->ID, 'sprint_engine_authoring_nonce' );
		if ( 'sprint_engine_sprint' === $post->post_type ) {
			$duration = Meta::sprint_duration( $post->ID );
			echo '<p><label for="_sprint_engine_estimated_duration_value">' . esc_html__( 'Estimated duration (optional)', 'sprint-engine' ) . '</label><br><input type="text" inputmode="decimal" ';
			$this->error_attributes( '_sprint_engine_estimated_duration_value', 'se-duration-help' );
			echo ' id="_sprint_engine_estimated_duration_value" name="sprint_engine_meta[_sprint_engine_estimated_duration_value]" value="' . esc_attr( $duration['value'] ?? '' ) . '"> ';
			echo '<label class="screen-reader-text" for="_sprint_engine_estimated_duration_unit">' . esc_html__( 'Duration unit', 'sprint-engine' ) . '</label><select id="_sprint_engine_estimated_duration_unit" name="sprint_engine_meta[_sprint_engine_estimated_duration_unit]" ';
			$this->error_attributes( '_sprint_engine_estimated_duration_unit' );
			echo '>';
			foreach ( array(
				'minutes' => __( 'Minutes', 'sprint-engine' ),
				'hours'   => __( 'Hours', 'sprint-engine' ),
				'days'    => __( 'Days', 'sprint-engine' ),
			) as $unit => $label ) {
				echo '<option value="' . esc_attr( $unit ) . '" ' . selected( $duration['unit'] ?? 'minutes', $unit, false ) . '>' . esc_html( $label ) . '</option>';
			}
			echo '</select></p>';
			$this->inline_error( '_sprint_engine_estimated_duration_value' );
			$this->inline_error( '_sprint_engine_estimated_duration_unit' );
			echo '<p id="se-duration-help" class="description">' . esc_html__( 'Enter one estimated number, for example 4 or 4.5.', 'sprint-engine' ) . '</p>';
			echo '<p><label><input type="checkbox" id="_sprint_engine_launchable" name="sprint_engine_meta[_sprint_engine_launchable]" value="1" ' . checked( $values['_sprint_engine_launchable'], true, false );
			$this->error_attributes( '_sprint_engine_launchable' );
			echo '> ' . esc_html__( 'Launchable', 'sprint-engine' ) . '</label></p>';
			$this->inline_error( '_sprint_engine_launchable' );
			StructureAdmin::render( $post->ID );
			echo '<h3>' . esc_html__( 'Completion screen', 'sprint-engine' ) . '</h3>';
			echo '<p><label for="_sprint_engine_completion_message">' . esc_html__( 'Completion message', 'sprint-engine' ) . '</label><br><textarea class="large-text" rows="4" id="_sprint_engine_completion_message" name="sprint_engine_meta[_sprint_engine_completion_message]"';
			$this->error_attributes( '_sprint_engine_completion_message' );
			echo '>' . esc_textarea( $values['_sprint_engine_completion_message'] ) . '</textarea></p>';
			$this->inline_error( '_sprint_engine_completion_message' );
			echo '<p class="description">' . esc_html__( 'Optional plain text. Leave blank to use: You have completed this Sprint. Your progress is saved.', 'sprint-engine' ) . '</p>';
			$this->input( '_sprint_engine_completion_cta_label', __( 'CTA button label', 'sprint-engine' ), $values['_sprint_engine_completion_cta_label'] );
			$this->input( '_sprint_engine_completion_cta_url', __( 'CTA URL', 'sprint-engine' ), $values['_sprint_engine_completion_cta_url'], 'url' );
			$this->inline_error( '_sprint_engine_completion_cta' );
			echo '<p class="description">' . esc_html__( 'Use both a label and a full http:// or https:// URL to send members to a feedback form, another Sprint, a booking page or another next step.', 'sprint-engine' ) . '</p>';
		} else {
			echo '<p class="description">' . esc_html__( 'The Step Featured Image is its optional visual banner in the Runner.', 'sprint-engine' ) . '</p>';
			// This GET value only prefills an unsaved form; save validates it again.
			if ( 'auto-draft' === $post->post_status && isset( $_GET['sprint_engine_sprint'] ) && is_scalar( $_GET['sprint_engine_sprint'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$prefill = absint( $_GET['sprint_engine_sprint'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				if ( Meta::valid_post( $prefill, 'sprint_engine_sprint' ) && current_user_can( 'edit_post', $prefill ) ) {
					$values['_sprint_engine_sprint_id'] = $prefill;
				}
			}
			$sprints = get_posts(
				array(
					'post_type'   => 'sprint_engine_sprint',
					'post_status' => array( 'publish', 'draft', 'pending', 'private', 'future' ),
					'numberposts' => -1,
					'orderby'     => 'title',
					'order'       => 'ASC',
				)
			);
			$this->select( '_sprint_engine_sprint_id', __( 'Sprint', 'sprint-engine' ), $values['_sprint_engine_sprint_id'], $this->choices( $sprints ) );
			if ( Meta::valid_post( $values['_sprint_engine_sprint_id'], 'sprint_engine_sprint' ) ) {
				$parent_link = get_edit_post_link( $values['_sprint_engine_sprint_id'] );
				echo '<p>' . esc_html__( 'Parent Sprint:', 'sprint-engine' ) . ' <strong>' . esc_html( get_the_title( $values['_sprint_engine_sprint_id'] ) ) . '</strong></p>';
				if ( $parent_link ) {
					echo '<p><a class="button" href="' . esc_url( $parent_link ) . '">' . esc_html__( '← Back to Sprint', 'sprint-engine' ) . '</a></p>';
				}
			}
			echo '<p>' . esc_html__( 'Position:', 'sprint-engine' ) . ' <strong>' . esc_html( $values['_sprint_engine_position'] ) . '</strong></p>';
			$this->input( '_sprint_engine_stage_label', __( 'Stage label', 'sprint-engine' ), $values['_sprint_engine_stage_label'] );
			echo '<p id="se-stage-help" class="description">' . esc_html__( 'The phase of the Sprint this Step belongs to. Choose a suggestion or enter any custom value. Examples: Discover, Diagnose, Decide, Design, Build, Implement, Review, Reflect, Measure.', 'sprint-engine' ) . '</p>';
			echo '<datalist id="se-stage-suggestions">';
			foreach ( array(
				__( 'Discover', 'sprint-engine' ),
				__( 'Diagnose', 'sprint-engine' ),
				__( 'Analyse', 'sprint-engine' ),
				__( 'Decide', 'sprint-engine' ),
				__( 'Design', 'sprint-engine' ),
				__( 'Build', 'sprint-engine' ),
				__( 'Implement', 'sprint-engine' ),
				__( 'Review', 'sprint-engine' ),
				__( 'Reflect', 'sprint-engine' ),
				__( 'Measure', 'sprint-engine' ),
			) as $stage ) {
				echo '<option value="' . esc_attr( $stage ) . '"></option>';
			}
			echo '</datalist>';
			$this->input( '_sprint_engine_estimated_minutes', __( 'Estimated minutes (optional)', 'sprint-engine' ), $values['_sprint_engine_estimated_minutes'] );
			echo '<p id="se-minutes-help" class="description">' . esc_html__( 'Enter whole minutes, for example 15 or 60. Leave blank to remove the estimate.', 'sprint-engine' ) . '</p>';
			$this->select(
				'_sprint_engine_mode',
				__( 'Step mode', 'sprint-engine' ),
				$values['_sprint_engine_mode'],
				array(
					'content' => __( 'Content', 'sprint-engine' ),
					'task'    => __( 'Task', 'sprint-engine' ),
				)
			);
			$next_title = $values['_sprint_engine_next_step_id'] ? get_the_title( $values['_sprint_engine_next_step_id'] ) : __( 'End', 'sprint-engine' );
			echo '<p>' . esc_html__( 'Next Step:', 'sprint-engine' ) . ' <strong>' . esc_html( $next_title ) . '</strong></p>';
			echo '<p>' . esc_html__( 'Order Steps in the Sprint Structure Manager. Changing the parent Sprint appends this Step to its new Sprint and rebuilds both lists. Save and reload to see the updated position and next Step.', 'sprint-engine' ) . '</p>';
			$validation = Meta::validate( $post->ID, 'sprint_engine_step', array() );
			if ( is_wp_error( $validation ) ) {
				echo '<p class="notice notice-warning">' . esc_html( $validation->get_error_message() ) . '</p>';
			}
		}
	}

	/**
	 * Save only nonce-verified, authorized editor submissions, never autosaves.
	 *
	 * @param int $post_id Post ID.
	 */
	public function save( $post_id ) {
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		if ( ! isset( $_POST['sprint_engine_authoring_nonce'] ) || ! is_string( $_POST['sprint_engine_authoring_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['sprint_engine_authoring_nonce'] ) ), 'sprint_engine_authoring_' . $post_id ) || ! current_user_can( 'manage_options' ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		if ( ! isset( $_POST['sprint_engine_meta'] ) || ! is_array( $_POST['sprint_engine_meta'] ) ) {
			set_transient( 'sprint_engine_authoring_error_' . get_current_user_id() . '_' . $post_id, __( 'Reload the editor and submit valid Sprint Engine fields. Your previous values were kept.', 'sprint-engine' ), 5 * MINUTE_IN_SECONDS );
			return;
		}
		// The shared validator rejects malformed values before any metadata writes.
		$input = wp_unslash( $_POST['sprint_engine_meta'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( 'sprint_engine_sprint' === get_post_type( $post_id ) && ! array_key_exists( '_sprint_engine_launchable', $input ) ) {
			$input['_sprint_engine_launchable'] = false;
		}
		if ( 'sprint_engine_step' === get_post_type( $post_id ) ) {
			$result = ( new StructureManager() )->save_step( $post_id, $input );
		} else {
			// Never resubmit an old start value after an independent AJAX reorder.
			$input  = array_intersect_key( $input, array_flip( array( '_sprint_engine_estimated_duration_value', '_sprint_engine_estimated_duration_unit', '_sprint_engine_launchable', '_sprint_engine_completion_message', '_sprint_engine_completion_cta_label', '_sprint_engine_completion_cta_url' ) ) );
			$result = Meta::save( $post_id, $input );
		}
		if ( is_wp_error( $result ) ) {
			set_transient(
				'sprint_engine_authoring_error_' . get_current_user_id() . '_' . $post_id,
				array(
					'message' => $result->get_error_message(),
					'fields'  => $result->get_error_data()['fields'] ?? array(),
				),
				5 * MINUTE_IN_SECONDS
			);
		} else {
			delete_transient( 'sprint_engine_authoring_error_' . get_current_user_id() . '_' . $post_id );
		}
	}

	/**
	 * Carry background metabox context through WordPress's save redirect.
	 *
	 * @param string $location Core redirect URL.
	 * @param int    $post_id Saved post ID.
	 * @return string
	 */
	public function notice_redirect( $location, $post_id ) {
		if ( isset( $_GET['meta-box-loader'] ) && in_array( get_post_type( $post_id ), array( 'sprint_engine_sprint', 'sprint_engine_step' ), true ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Preserve request context only.
			return add_query_arg( 'se-metabox-response', '1', $location );
		}
		return $location;
	}

	/**
	 * Show per-user/per-post errors after native editor redirects or reloads.
	 *
	 * @param bool $in_metabox Render inside the block editor's visible custom fields.
	 */
	public function notice( $in_metabox = false ) {
		// Gutenberg reloads legacy metaboxes in a hidden request after saving.
		// Keep the notice for the next visible editor load instead of consuming it there.
		if ( isset( $_GET['meta-box-loader'] ) || isset( $_GET['se-metabox-response'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only request context; no submitted value is used.
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'post' !== $screen->base || ! in_array( $screen->post_type, array( 'sprint_engine_sprint', 'sprint_engine_step' ), true ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		// Gutenberg hides legacy admin-header notices; show ours beside the fields.
		if ( $screen->is_block_editor() && ! $in_metabox ) {
			return;
		}
		global $post;
		if ( ! $post ) {
			return;
		}
		$key     = 'sprint_engine_authoring_error_' . get_current_user_id() . '_' . $post->ID;
		$message = get_transient( $key );
		if ( $message ) {
			$this->field_error = is_array( $message ) ? $message : array(
				'message' => $message,
				'fields'  => array(),
			);
			echo '<div class="notice notice-error inline se-authoring-server-notice" data-field-error="' . esc_attr( empty( $this->field_error['fields'] ) ? 'false' : 'true' ) . '" role="alert"><p>' . esc_html__( 'Sprint Engine fields were not saved: ', 'sprint-engine' ) . esc_html( $this->field_error['message'] ) . '</p></div>';
			delete_transient( $key );
		}
	}

	/**
	 * Stable shared CTA location; other fields each have their own location.
	 *
	 * @param string $key Field key.
	 * @return string
	 */
	private function error_id( $key ) {
		return ( str_starts_with( $key, '_sprint_engine_completion_cta' ) ? '_sprint_engine_completion_cta' : $key ) . '-error';
	}

	/**
	 * Keep normal descriptions alongside server fallback feedback.
	 *
	 * @param string $key Field key.
	 * @param string $help Existing help ID.
	 */
	private function error_attributes( $key, $help = '' ) {
		$invalid = in_array( $key, $this->field_error['fields'] ?? array(), true );
		echo ' data-se-error="' . esc_attr( $this->error_id( $key ) ) . '"';
		if ( $invalid ) {
			echo ' aria-invalid="true"';
			$help = trim( $help . ' ' . $this->error_id( $key ) );
		}
		if ( $help ) {
			echo ' aria-describedby="' . esc_attr( $help ) . '"';
		}
	}

	/**
	 * Native readable error location, including without JavaScript.
	 *
	 * @param string $key Field or shared CTA key.
	 */
	private function inline_error( $key ) {
		$fields  = $this->field_error['fields'] ?? array();
		$invalid = in_array( $key, $fields, true ) || ( '_sprint_engine_completion_cta' === $key && array_intersect( $fields, array( '_sprint_engine_completion_cta_label', '_sprint_engine_completion_cta_url' ) ) );
		echo '<p class="se-field-error" id="' . esc_attr( $this->error_id( $key ) ) . '" aria-live="polite"' . ( $invalid ? '' : ' hidden' ) . '><strong>' . esc_html( $invalid ? $this->field_error['message'] : '' ) . '</strong></p>';
	}

	/**
	 * Bounded traversal of the single next-Step chain for advisory editor warnings.
	 *
	 * @param int $sprint Sprint ID.
	 * @return array
	 */
	public static function warnings( $sprint ) {
		$warnings = array();
		$steps    = Meta::steps( $sprint );
		$values   = Meta::read( $sprint, 'sprint_engine_sprint' );
		$current  = $values['_sprint_engine_start_step_id'];
		$seen     = array();
		if ( ! Meta::belongs_to( $current, $sprint ) ) {
			$warnings[] = __( 'This Sprint has no valid start Step.', 'sprint-engine' );
		}
		foreach ( $steps as $step ) {
			$next = (int) get_post_meta( $step->ID, '_sprint_engine_next_step_id', true );
			if ( $next && ( $next === $step->ID || ! Meta::belongs_to( $next, $sprint ) ) ) {
				$warnings[] = __( 'An associated Step has a self-loop or invalid next-Step relationship.', 'sprint-engine' );
				break;
			}
		}
		while ( Meta::belongs_to( $current, $sprint ) ) {
			if ( isset( $seen[ $current ] ) ) {
				$warnings[] = __( 'The start-to-finish chain contains a loop.', 'sprint-engine' );
				break;
			}
			$seen[ $current ] = true;
			$current          = (int) get_post_meta( $current, '_sprint_engine_next_step_id', true );
		}
		if ( count( $steps ) > count( $seen ) ) {
			$warnings[] = __( 'Some associated Steps are not reachable from the start Step. Review the links before launch.', 'sprint-engine' );
		}
		return $warnings;
	}

	/**
	 * Build labeled native select options.
	 *
	 * @param array $posts Posts.
	 * @return array
	 */
	private function choices( $posts ) {
		$choices = array( 0 => __( '— None —', 'sprint-engine' ) );
		foreach ( $posts as $post ) {
			$choices[ $post->ID ] = $post->post_title . ' (#' . $post->ID . ')';
		}
		return $choices;
	}

	/**
	 * Render an escaped native input.
	 *
	 * @param string $key Meta key.
	 * @param string $label Label.
	 * @param mixed  $value Current value.
	 * @param string $type Input type.
	 * @param int    $minimum Minimum numeric value.
	 */
	private function input( $key, $label, $value, $type = 'text', $minimum = 0 ) {
		if ( '_sprint_engine_estimated_minutes' === $key && ! $value ) {
			$value = '';
		}
		echo '<p><label for="' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label><br><input id="' . esc_attr( $key ) . '" name="sprint_engine_meta[' . esc_attr( $key ) . ']" type="' . esc_attr( $type ) . '" value="' . esc_attr( $value ) . '"';
		if ( 'number' === $type ) {
			echo ' min="' . esc_attr( $minimum ) . '" step="1"';
		}
		if ( '_sprint_engine_stage_label' === $key ) {
			echo ' list="se-stage-suggestions"';
		}
		if ( '_sprint_engine_estimated_minutes' === $key ) {
			echo ' inputmode="numeric"';
		}
		$help = '_sprint_engine_estimated_minutes' === $key ? 'se-minutes-help' : ( '_sprint_engine_stage_label' === $key ? 'se-stage-help' : '' );
		$this->error_attributes( $key, $help );
		echo '></p>';
		if ( ! str_starts_with( $key, '_sprint_engine_completion_cta' ) ) {
			$this->inline_error( $key );
		}
	}

	/**
	 * Saved-state guidance, reusing the canonical structure and availability checks.
	 *
	 * @param \WP_Post $post Sprint being edited.
	 */
	public function setup( $post ) {
		$values = Meta::read( $post->ID, 'sprint_engine_sprint' );
		echo '<h3>' . esc_html__( 'Start screen / Sprint introduction', 'sprint-engine' ) . '</h3>';
		echo '<p class="description">' . esc_html__( 'The Sprint Featured Image is the optional banner on Start/Welcome and Completion.', 'sprint-engine' ) . '</p>';
		echo '<p>' . esc_html__( 'The main Sprint content in the WordPress editor is shown to members before they click Start Sprint. Use it for a welcome, what members will achieve, what to expect, preparation, approximate time and instructions before beginning. If you supply an excerpt, it is shown instead of the main content.', 'sprint-engine' ) . '</p>';
		echo '<p>' . esc_html__( 'Your first Sprint Step should normally be the first piece of work. You usually do not need a separate Welcome Step.', 'sprint-engine' ) . '</p>';
		$introduction = trim( html_entity_decode( wp_strip_all_tags( $post->post_content . $post->post_excerpt ), ENT_QUOTES, 'UTF-8' ), " \n\r\t\v\0\xc2\xa0" );
		$checks       = array(
			'introduction'     => array( '' !== $introduction, __( 'Add a Sprint introduction / Start screen', 'sprint-engine' ) ),
			'steps'            => array( (bool) Meta::steps( $post->ID ), __( 'Add Sprint Steps', 'sprint-engine' ) ),
			'structure'        => array( StructureManager::is_linear( $post->ID ), __( 'Order the Steps', 'sprint-engine' ) ),
			'step_publication' => array( Meta::publication_readiness( $post->ID )['ready'], __( 'Publish all Sprint Steps', 'sprint-engine' ) ),
			'launchable'       => array( $values['_sprint_engine_launchable'], __( 'Make the Sprint Launchable', 'sprint-engine' ) ),
			'published'        => array( 'publish' === $post->post_status, __( 'Publish the Sprint', 'sprint-engine' ) ),
		);
		echo '<ul class="se-setup-checklist">';
		foreach ( $checks as $key => $check ) {
			echo '<li data-check="' . esc_attr( $key ) . '" data-complete="' . esc_attr( $check[0] ? 'true' : 'false' ) . '"><span aria-hidden="true">' . ( $check[0] ? '✓' : '○' ) . '</span> <span class="screen-reader-text">' . esc_html( $check[0] ? __( 'Complete:', 'sprint-engine' ) : __( 'To do:', 'sprint-engine' ) ) . '</span> ' . esc_html( $check[1] ) . '</li>';
		}
		echo '</ul><p class="description">' . esc_html__( 'Based on saved state. Save and reload to refresh this checklist.', 'sprint-engine' ) . '</p>';
		if ( '' !== $post->post_name && '' === \ThePath\SprintEngine\Runner\Availability::reason( $post ) ) {
			echo '<p><a class="button" href="' . esc_url( \ThePath\SprintEngine\Runner\Routes::url( $post->post_name ) ) . '">' . esc_html__( 'View Runner', 'sprint-engine' ) . '</a></p>';
		}
	}

	/**
	 * Render an escaped select without offering stale or cross-Sprint links.
	 *
	 * @param string $key Meta key.
	 * @param string $label Label.
	 * @param mixed  $value Current value.
	 * @param array  $choices Options.
	 */
	private function select( $key, $label, $value, $choices ) {
		echo '<p><label for="' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label><br><select id="' . esc_attr( $key ) . '" name="sprint_engine_meta[' . esc_attr( $key ) . ']"';
		$this->error_attributes( $key );
		echo '>';
		foreach ( $choices as $id => $text ) {
			echo '<option value="' . esc_attr( $id ) . '" ' . selected( $value, $id, false ) . '>' . esc_html( $text ) . '</option>';
		}
		echo '</select></p>';
		$this->inline_error( $key );
	}
}
