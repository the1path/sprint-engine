<?php
/**
 * Structural content metadata and shared write validation.
 *
 * @package SprintEngine
 */

namespace ThePath\SprintEngine\Content;

defined( 'ABSPATH' ) || exit;

/** Owns authoring metadata; does not implement member state or navigation. */
final class Meta {

	/** Register native editor integration. */
	public function register_hooks() {
		add_action( 'init', array( $this, 'register' ), 11 );
		add_action( 'rest_after_insert_sprint_engine_sprint', array( $this, 'clear_legacy_duration' ), 10, 2 );
		foreach ( array( 'sprint_engine_sprint', 'sprint_engine_step' ) as $type ) {
			add_filter( 'rest_pre_insert_' . $type, array( $this, 'validate_rest' ), 10, 2 );
		}
	}

	/**
	 * An intentional native REST duration save retires Sprint-only legacy minutes.
	 *
	 * @param \WP_Post         $post Saved Sprint.
	 * @param \WP_REST_Request $request Authorized editor request.
	 */
	public function clear_legacy_duration( $post, $request ) {
		$input = $request->get_param( 'meta' );
		if ( is_array( $input ) && array_key_exists( '_sprint_engine_estimated_duration_value', $input ) ) {
			delete_post_meta( $post->ID, '_sprint_engine_estimated_minutes' );
		}
	}

	/**
	 * Defaults use zero for unset integer references in WordPress's meta API.
	 *
	 * @param string $type Post type.
	 * @return array
	 */
	public static function defaults( $type ) {
		if ( 'sprint_engine_sprint' === $type ) {
			return array(
				'_sprint_engine_estimated_duration_value' => '',
				'_sprint_engine_estimated_duration_unit'  => 'minutes',
				'_sprint_engine_estimated_minutes'        => 0,
				'_sprint_engine_start_step_id'            => 0,
				'_sprint_engine_launchable'               => false,
				'_sprint_engine_completion_message'       => '',
				'_sprint_engine_completion_cta_label'     => '',
				'_sprint_engine_completion_cta_url'       => '',
			);
		}
		if ( 'sprint_engine_step' === $type ) {
			return array(
				'_sprint_engine_sprint_id'         => 0,
				'_sprint_engine_position'          => 0,
				'_sprint_engine_stage_label'       => '',
				'_sprint_engine_estimated_minutes' => 0,
				'_sprint_engine_mode'              => 'content',
				'_sprint_engine_next_step_id'      => 0,
			);
		}
		return array();
	}

	/** Register protected, single-value fields for Gutenberg's native meta API. */
	public function register() {
		foreach ( array( 'sprint_engine_sprint', 'sprint_engine_step' ) as $post_type ) {
			foreach ( self::defaults( $post_type ) as $key => $default ) {
				$type   = is_int( $default ) ? 'integer' : ( is_bool( $default ) ? 'boolean' : 'string' );
				$schema = array(
					'type'    => $type,
					'context' => array( 'edit' ),
				);
				if ( 'integer' === $type ) {
					$schema['minimum'] = 0;
				}
				if ( '_sprint_engine_mode' === $key ) {
					$schema['enum'] = array( 'content', 'task' );
				}
				if ( '_sprint_engine_estimated_duration_unit' === $key ) {
					$schema['enum'] = array( 'minutes', 'hours', 'days' );
				}
				register_post_meta(
					$post_type,
					$key,
					array(
						'type'              => $type,
						'single'            => true,
						'default'           => $default,
						'show_in_rest'      => array( 'schema' => $schema ),
						'auth_callback'     => array( $this, 'authorize' ),
						'sanitize_callback' => array( $this, 'sanitize' ),
					)
				);
			}
		}
	}

	/**
	 * Match the existing administrator-only editor capabilities.
	 *
	 * @param bool   $allowed Previous decision.
	 * @param string $key Meta key.
	 * @param int    $post_id Object ID.
	 * @return bool
	 */
	public function authorize( $allowed, $key, $post_id ) {
		return current_user_can( 'manage_options' ) && current_user_can( 'edit_post', $post_id );
	}

	/**
	 * Defence in depth for trusted WordPress metadata callers; relationships are
	 * validated at the editor/service write boundary, before any metadata changes.
	 *
	 * @param mixed  $value Submitted value.
	 * @param string $key Meta key.
	 * @return mixed
	 */
	public function sanitize( $value, $key ) {
		if ( '_sprint_engine_estimated_duration_value' === $key ) {
			return self::duration_value( $value ) ?? '';
		}
		if ( '_sprint_engine_estimated_duration_unit' === $key ) {
			return in_array( $value, array( 'minutes', 'hours', 'days' ), true ) ? $value : 'minutes';
		}
		if ( '_sprint_engine_completion_message' === $key ) {
			return is_string( $value ) ? sanitize_textarea_field( $value ) : '';
		}
		if ( '_sprint_engine_completion_cta_url' === $key ) {
			return self::completion_url( $value );
		}
		if ( in_array( $key, array( '_sprint_engine_stage_label', '_sprint_engine_completion_cta_label' ), true ) ) {
			return is_string( $value ) ? sanitize_text_field( $value ) : '';
		}
		if ( '_sprint_engine_mode' === $key ) {
			return in_array( $value, array( 'content', 'task' ), true ) ? $value : 'content';
		}
		if ( '_sprint_engine_launchable' === $key ) {
			return in_array( $value, array( true, 1, '1' ), true );
		}
		return false !== filter_var( $value, FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 0 ) ) ) ? (int) $value : 0;
	}

	/**
	 * Accept absolute web destinations without restricting their domain.
	 *
	 * @param mixed $value Submitted URL.
	 * @return string
	 */
	public static function completion_url( $value ) {
		if ( ! is_string( $value ) || ! preg_match( '~^https?://~i', $value ) || preg_match( '/[\s<>]/', $value ) || ! filter_var( $value, FILTER_VALIDATE_URL ) ) {
			return '';
		}
		$url = esc_url_raw( $value, array( 'http', 'https' ) );
		return wp_parse_url( $url, PHP_URL_HOST ) && filter_var( $url, FILTER_VALIDATE_URL ) ? $url : '';
	}

	/**
	 * Validate a positive decimal without float rounding or exponent notation.
	 *
	 * @param mixed $value Submitted value.
	 * @return string|null Normalized decimal, blank, or null for invalid input.
	 */
	public static function duration_value( $value ) {
		if ( '' === $value ) {
			return '';
		}
		if ( ! is_string( $value ) || ! preg_match( '/^[0-9]{1,9}(?:\.[0-9]{1,2})?$/D', $value ) || (float) $value <= 0 ) {
			return null;
		}
		$value = ltrim( $value, '0' );
		$value = str_contains( $value, '.' ) ? rtrim( rtrim( $value, '0' ), '.' ) : $value;
		return str_starts_with( $value, '.' ) ? '0' . $value : $value;
	}

	/**
	 * Validated presentation metadata, including old Sprint minute values.
	 *
	 * @param int $sprint Sprint ID.
	 * @return array Empty when no valid duration exists.
	 */
	public static function sprint_duration( $sprint ) {
		$value = self::duration_value( get_post_meta( $sprint, '_sprint_engine_estimated_duration_value', true ) );
		$unit  = get_post_meta( $sprint, '_sprint_engine_estimated_duration_unit', true );
		if ( ! metadata_exists( 'post', $sprint, '_sprint_engine_estimated_duration_value' ) || ! metadata_exists( 'post', $sprint, '_sprint_engine_estimated_duration_unit' ) || ! $value || ! in_array( $unit, array( 'minutes', 'hours', 'days' ), true ) ) {
			$legacy = get_post_meta( $sprint, '_sprint_engine_estimated_minutes', true );
			$value  = is_scalar( $legacy ) && false !== filter_var( $legacy, FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 1 ) ) ) ? (string) $legacy : '';
			$unit   = 'minutes';
		}
		if ( '' === $value ) {
			return array();
		}
		// WordPress plural selection takes integers; fractional values use plural.
		$count = str_contains( $value, '.' ) ? 2 : (int) $value;
		switch ( $unit ) {
			case 'hours':
				/* translators: %s: estimated duration value. */
				$format = _n( '%s hour', '%s hours', $count, 'sprint-engine' );
				break;
			case 'days':
				/* translators: %s: estimated duration value. */
				$format = _n( '%s day', '%s days', $count, 'sprint-engine' );
				break;
			default:
				/* translators: %s: estimated duration value. */
				$format = _n( '%s minute', '%s minutes', $count, 'sprint-engine' );
		}
		$precision = str_contains( $value, '.' ) ? strlen( explode( '.', $value )[1] ) : 0;
		return array(
			'value' => $value,
			'unit'  => $unit,
			'label' => sprintf( $format, number_format_i18n( (float) $value, $precision ) ),
		);
	}

	/**
	 * Load the complete typed authoring state.
	 *
	 * @param int    $post_id Post ID (zero for a new REST object).
	 * @param string $type Post type.
	 * @return array
	 */
	public static function read( $post_id, $type ) {
		$values = self::defaults( $type );
		foreach ( $values as $key => $default ) {
			if ( $post_id && metadata_exists( 'post', $post_id, $key ) ) {
				$value          = get_post_meta( $post_id, $key, true );
				$values[ $key ] = is_int( $default ) ? (int) $value : ( is_bool( $default ) ? (bool) $value : $value );
			}
		}
		return $values;
	}

	/**
	 * Validate the final candidate state, including partial and multi-field edits.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $type Post type.
	 * @param array  $input Submitted fields.
	 * @return array|\WP_Error
	 */
	public static function validate( $post_id, $type, $input ) {
		$values = self::read( $post_id, $type );
		foreach ( self::defaults( $type ) as $key => $default ) {
			if ( ! array_key_exists( $key, $input ) ) {
				continue;
			}
			$value = $input[ $key ];
			if ( '_sprint_engine_estimated_duration_value' === $key && null === self::duration_value( $value ) ) {
				return self::error( __( 'Estimated duration must be a positive number, for example 4 or 4.5, with up to nine whole digits and two decimal places. Leave blank to remove it.', 'sprint-engine' ), array( $key ) );
			}
			if ( '_sprint_engine_estimated_duration_unit' === $key && ! in_array( $value, array( 'minutes', 'hours', 'days' ), true ) ) {
				return self::error( __( 'Duration unit must be Minutes, Hours or Days.', 'sprint-engine' ), array( $key ) );
			}
			if ( 'sprint_engine_step' === $type && '_sprint_engine_estimated_minutes' === $key && '' !== $value && null !== $value && ( ( ! is_int( $value ) && ! is_string( $value ) ) || ! preg_match( '/^[0-9]+$/D', (string) $value ) || false === filter_var( $value, FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 1 ) ) ) ) ) {
				return self::error( __( 'Estimated minutes must be positive whole minutes, for example 15 or 60. Leave blank to remove the estimate.', 'sprint-engine' ), array( $key ) );
			}
			if ( '_sprint_engine_completion_cta_url' === $key && ( ! is_string( $value ) || ( '' !== $value && '' === self::completion_url( $value ) ) ) ) {
				return self::error( __( 'Completion CTA requires both a button label and a valid full http:// or https:// URL. Clear both fields to remove it.', 'sprint-engine' ), array( $key ) );
			}
			if ( null === $value && ( is_string( $default ) || is_bool( $default ) || '_sprint_engine_sprint_id' === $key ) ) {
				return self::error( __( 'Sprint Engine fields must contain the expected text, selection or checkbox value.', 'sprint-engine' ), array( $key ) );
			}
			if ( null === $value || ( '' === $value && is_int( $default ) ) ) {
				$value = $default;
			}
			if ( is_int( $default ) ) {
				if ( ( ! is_int( $value ) && ! is_string( $value ) ) || false === filter_var( $value, FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 0 ) ) ) ) {
					return self::error( __( 'IDs, position and minutes must be whole non-negative integers. Leave optional values blank.', 'sprint-engine' ), array( $key ) );
				}
				$value = (int) $value;
			} elseif ( is_bool( $default ) ) {
				if ( ! in_array( $value, array( true, false, 0, 1, '0', '1' ), true ) ) {
					return self::error( __( 'Launchable must be a boolean value.', 'sprint-engine' ), array( $key ) );
				}
				$value = (bool) $value;
			} elseif ( ! is_string( $value ) ) {
				return self::error( __( 'Text fields must contain text.', 'sprint-engine' ), array( $key ) );
			}
			$values[ $key ] = is_string( $default ) && '_sprint_engine_mode' !== $key ? ( new self() )->sanitize( $value, $key ) : $value;
		}
		if ( 'sprint_engine_sprint' === $type ) {
			if ( ( array_key_exists( '_sprint_engine_completion_cta_label', $input ) || array_key_exists( '_sprint_engine_completion_cta_url', $input ) ) && ( ( '' === $values['_sprint_engine_completion_cta_label'] ) !== ( '' === $values['_sprint_engine_completion_cta_url'] ) || ( '' !== $values['_sprint_engine_completion_cta_url'] && '' === self::completion_url( $values['_sprint_engine_completion_cta_url'] ) ) ) ) {
				return self::error( __( 'Completion CTA requires both a button label and a valid full http:// or https:// URL. Clear both fields to remove it.', 'sprint-engine' ), array( '_sprint_engine_completion_cta_label', '_sprint_engine_completion_cta_url' ) );
			}
			$start = $values['_sprint_engine_start_step_id'];
			if ( ( $start && ! self::belongs_to( $start, $post_id ) ) || ( $values['_sprint_engine_launchable'] && ! self::belongs_to( $start, $post_id ) ) ) {
				return self::error( __( 'Choose a valid start Step belonging to this Sprint before making it launchable.', 'sprint-engine' ) );
			}
			if ( $values['_sprint_engine_launchable'] && ! StructureManager::is_linear( $post_id, $start ) ) {
				return self::error( __( 'Save a complete linear order in the Sprint Structure Manager before making this Sprint launchable.', 'sprint-engine' ) );
			}
			if ( $values['_sprint_engine_launchable'] && ! self::publication_readiness( $post_id )['ready'] ) {
				return self::error( __( 'Publish every Sprint Step before making this Sprint Launchable.', 'sprint-engine' ), array( '_sprint_engine_launchable' ) );
			}
		} elseif ( 'sprint_engine_step' === $type ) {
			$sprint = $values['_sprint_engine_sprint_id'];
			$next   = $values['_sprint_engine_next_step_id'];
			if ( ! in_array( $values['_sprint_engine_mode'], array( 'content', 'task' ), true ) ) {
				return self::error( __( 'Step mode must be Content or Task.', 'sprint-engine' ), array( '_sprint_engine_mode' ) );
			}
			if ( $sprint && ! self::valid_post( $sprint, 'sprint_engine_sprint' ) ) {
				return self::error( __( 'Choose an existing Sprint that is not in the trash.', 'sprint-engine' ), array( '_sprint_engine_sprint_id' ) );
			}
			if ( $next && ( $next === $post_id || ! self::belongs_to( $next, $sprint ) ) ) {
				return self::error( __( 'The next Step must be a different Step belonging to the same Sprint.', 'sprint-engine' ) );
			}
			$old = (int) get_post_meta( $post_id, '_sprint_engine_sprint_id', true );
			if ( $post_id && $old !== $sprint && self::has_references( $post_id ) ) {
				return self::error( __( 'Unlink this Step from Sprint start fields and other Steps before changing its Sprint.', 'sprint-engine' ) );
			}
		}
		return $values;
	}

	/**
	 * Validate before core saves post content or any REST meta fields.
	 *
	 * @param object|\WP_Error $prepared Prepared post.
	 * @param \WP_REST_Request $request Request.
	 * @return object|\WP_Error
	 */
	public function validate_rest( $prepared, $request ) {
		if ( is_wp_error( $prepared ) ) {
			return $prepared;
		}
		$input = $request->get_param( 'meta' );
		if ( ! is_array( $input ) || ! array_intersect_key( $input, self::defaults( $prepared->post_type ) ) ) {
			// Gutenberg saves legacy metaboxes separately. Keep content editable so
			// the following metabox submission can repair a deleted structural link.
			return $prepared;
		}
		$result = self::validate( (int) $request['id'], $prepared->post_type, $input );
		return is_wp_error( $result ) ? $result : $prepared;
	}

	/**
	 * Save native metabox input only after the complete submission is valid.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $input Input.
	 * @return true|\WP_Error
	 */
	public static function save( $post_id, $input ) {
		if ( ! current_user_can( 'manage_options' ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return self::error( __( 'You cannot edit Sprint authoring metadata.', 'sprint-engine' ) );
		}
		$values = self::validate( $post_id, get_post_type( $post_id ), $input );
		if ( is_wp_error( $values ) ) {
			return $values;
		}
		if ( 'sprint_engine_sprint' === get_post_type( $post_id ) && array_key_exists( '_sprint_engine_estimated_duration_value', $input ) ) {
			$values['_sprint_engine_estimated_minutes'] = 0;
		}
		foreach ( $values as $key => $value ) {
			if ( 0 === $value && '_sprint_engine_position' !== $key ) {
				delete_post_meta( $post_id, $key );
				if ( metadata_exists( 'post', $post_id, $key ) ) {
					return self::error( __( 'Could not clear structural metadata. Reload and retry.', 'sprint-engine' ) );
				}
			} else {
				update_post_meta( $post_id, $key, wp_slash( $value ) );
				$stored = self::read( $post_id, get_post_type( $post_id ) );
				if ( $stored[ $key ] !== $value ) {
					return self::error( __( 'Could not save all structural metadata. Reload and retry.', 'sprint-engine' ) );
				}
			}
		}
		return true;
	}

	/**
	 * Check object type and availability for structural links.
	 *
	 * @param int    $id Post ID.
	 * @param string $type Required type.
	 * @return bool
	 */
	public static function valid_post( $id, $type ) {
		$post = get_post( $id );
		return $id && $post && $type === $post->post_type && ! in_array( $post->post_status, array( 'trash', 'auto-draft' ), true );
	}

	/**
	 * Validate a Step's membership.
	 *
	 * @param int $step Step ID.
	 * @param int $sprint Sprint ID.
	 * @return bool
	 */
	public static function belongs_to( $step, $sprint ) {
		return $sprint && self::valid_post( $sprint, 'sprint_engine_sprint' ) && self::valid_post( $step, 'sprint_engine_step' ) && (int) get_post_meta( $step, '_sprint_engine_sprint_id', true ) === $sprint;
	}

	/**
	 * Fetch associated Steps, including drafts and those without saved positions.
	 *
	 * @param int $sprint Sprint ID.
	 * @return array
	 */
	public static function steps( $sprint ) {
		if ( ! $sprint ) {
			return array();
		}
		$steps = get_posts(
			array(
				'post_type'   => 'sprint_engine_step',
				'post_status' => array( 'publish', 'draft', 'pending', 'private', 'future' ),
				'numberposts' => -1,
				'meta_key'    => '_sprint_engine_sprint_id', // phpcs:ignore WordPress.DB.SlowDBQuery -- Native authoring list.
				'meta_value'  => $sprint, // phpcs:ignore WordPress.DB.SlowDBQuery -- Native authoring list.
			)
		);
		usort(
			$steps,
			static function ( $left, $right ) {
				return array( (int) get_post_meta( $left->ID, '_sprint_engine_position', true ), $left->ID ) <=> array( (int) get_post_meta( $right->ID, '_sprint_engine_position', true ), $right->ID );
			}
		);
		return $steps;
	}

	/**
	 * Publication is separate from structure: only publish is member-ready.
	 * Empty Sprints are never ready. Uses the same active membership as authoring.
	 *
	 * @param int $sprint Sprint ID.
	 * @return array Counts and readiness, without changing content or progress.
	 */
	public static function publication_readiness( $sprint ) {
		$steps       = self::steps( $sprint );
		$unpublished = count(
			array_filter(
				$steps,
				static function ( $step ) {
					return 'publish' !== $step->post_status;
				}
			)
		);
		return array(
			'total'       => count( $steps ),
			'unpublished' => $unpublished,
			'ready'       => (bool) $steps && 0 === $unpublished,
		);
	}

	/**
	 * Check incoming references, including trashed objects that may be restored.
	 *
	 * @param int $step Step ID.
	 * @return bool
	 */
	private static function has_references( $step ) {
		foreach ( array(
			'sprint_engine_sprint' => '_sprint_engine_start_step_id',
			'sprint_engine_step'   => '_sprint_engine_next_step_id',
		) as $type => $key ) {
			$posts = get_posts(
				array(
					'post_type'   => $type,
					'post_status' => array_values( get_post_stati() ),
					'numberposts' => 1,
					'fields'      => 'ids',
					'meta_key'    => $key, // phpcs:ignore WordPress.DB.SlowDBQuery -- Structural authoring reference check.
					'meta_value'  => $step, // phpcs:ignore WordPress.DB.SlowDBQuery -- Structural authoring reference check.
				)
			);
			if ( $posts ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Safe author-facing validation error.
	 *
	 * @param string $message Localized message.
	 * @param array  $fields Affected authoring field keys.
	 * @return \WP_Error
	 */
	private static function error( $message, $fields = array() ) {
		return new \WP_Error(
			'sprint_engine_invalid_content',
			$message,
			array(
				'status' => 400,
				'fields' => $fields,
			)
		);
	}
}
