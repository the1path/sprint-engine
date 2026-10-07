<?php
/**
 * Native Step list columns and parent Sprint filtering.
 *
 * @package SprintEngine
 */

namespace ThePath\SprintEngine\Content;

defined( 'ABSPATH' ) || exit;

/** Keeps the native list, search and pagination. */
final class StepListAdmin {

	/** Register post-type-scoped list hooks. */
	public function register_hooks() {
		add_filter( 'manage_sprint_engine_step_posts_columns', array( $this, 'columns' ) );
		add_action( 'manage_sprint_engine_step_posts_custom_column', array( $this, 'column' ), 10, 2 );
		add_action( 'restrict_manage_posts', array( $this, 'dropdown' ) );
		add_action( 'pre_get_posts', array( $this, 'filter' ) );
		add_filter( 'disable_months_dropdown', array( $this, 'disable_months' ), 10, 2 );
	}

	/**
	 * Insert useful columns before the retained Date column.
	 *
	 * @param array $columns Native columns.
	 * @return array
	 */
	public function columns( $columns ) {
		$date = $columns['date'] ?? __( 'Date', 'sprint-engine' );
		unset( $columns['date'] );
		$columns['sprint_engine_parent'] = __( 'Parent Sprint', 'sprint-engine' );
		$columns['sprint_engine_stage']  = __( 'Stage', 'sprint-engine' );
		$columns['date']                 = $date;
		return $columns;
	}

	/**
	 * Render escaped relationship and Stage values.
	 *
	 * @param string $column Column name.
	 * @param int    $post_id Step ID.
	 */
	public function column( $column, $post_id ) {
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$meta = Meta::read( $post_id, 'sprint_engine_step' );
		if ( 'sprint_engine_stage' === $column ) {
			echo esc_html( '' !== $meta['_sprint_engine_stage_label'] ? $meta['_sprint_engine_stage_label'] : '—' );
		} elseif ( 'sprint_engine_parent' === $column ) {
			$parent = $meta['_sprint_engine_sprint_id'];
			if ( ! Meta::valid_post( $parent, 'sprint_engine_sprint' ) ) {
				echo '—';
				return;
			}
			$link  = get_edit_post_link( $parent );
			$title = get_the_title( $parent );
			$title = '' !== $title ? $title : __( '(no title)', 'sprint-engine' );
			if ( $link ) {
				echo '<a href="' . esc_url( $link ) . '">' . esc_html( $title ) . '</a>';
			} else {
				echo esc_html( $title );
			}
		}
	}

	/**
	 * Validate the read-only list selection; invalid values mean All Sprints.
	 *
	 * @return int
	 */
	private function selected_sprint() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Read-only value strictly validated below before query use.
		$raw = isset( $_GET['sprint_engine_parent_sprint'] ) && is_string( $_GET['sprint_engine_parent_sprint'] ) ? wp_unslash( $_GET['sprint_engine_parent_sprint'] ) : '';
		$id  = preg_match( '/^[0-9]+$/D', $raw ) ? filter_var( $raw, FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 1 ) ) ) : false;
		return $id && Meta::valid_post( $id, 'sprint_engine_sprint' ) && current_user_can( 'edit_post', $id ) ? $id : 0;
	}

	/**
	 * Native dropdown above the Step list only.
	 *
	 * @param string $post_type Current list type.
	 */
	public function dropdown( $post_type ) {
		if ( ! is_admin() || 'sprint_engine_step' !== $post_type || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$sprints  = get_posts(
			array(
				'post_type'   => 'sprint_engine_sprint',
				'post_status' => array( 'publish', 'draft', 'pending', 'private', 'future' ),
				'numberposts' => -1,
				'orderby'     => 'title',
				'order'       => 'ASC',
			)
		);
		$selected = $this->selected_sprint();
		echo '<label for="se-parent-sprint" class="screen-reader-text">' . esc_html__( 'Filter by Sprint', 'sprint-engine' ) . '</label><select id="se-parent-sprint" name="sprint_engine_parent_sprint"><option value="0">' . esc_html__( 'All Sprints', 'sprint-engine' ) . '</option>';
		foreach ( $sprints as $sprint ) {
			if ( current_user_can( 'edit_post', $sprint->ID ) ) {
				echo '<option value="' . esc_attr( $sprint->ID ) . '" ' . selected( $selected, $sprint->ID, false ) . '>' . esc_html( '' !== $sprint->post_title ? $sprint->post_title : __( '(no title)', 'sprint-engine' ) ) . '</option>';
			}
		}
		echo '</select>';
	}

	/**
	 * Constrain only the main native Step admin query; retain other conditions.
	 *
	 * @param \WP_Query $query Current query.
	 */
	public function filter( $query ) {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! is_admin() || ! $screen || 'edit-sprint_engine_step' !== $screen->id || ! $query->is_main_query() || 'sprint_engine_step' !== $query->get( 'post_type' ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$sprint = $this->selected_sprint();
		if ( $sprint ) {
			$meta   = $query->get( 'meta_query' );
			$clause = array(
				'key'     => '_sprint_engine_sprint_id',
				'value'   => $sprint,
				'compare' => '=',
			);
			$query->set(
				'meta_query',
				$meta ? array(
					'relation' => 'AND',
					$meta,
					$clause,
				) : array( $clause )
			);
		}
	}

	/**
	 * Leave every other post type's month filter unchanged.
	 *
	 * @param bool   $disabled Previous decision.
	 * @param string $post_type List type.
	 * @return bool
	 */
	public function disable_months( $disabled, $post_type ) {
		return 'sprint_engine_step' === $post_type ? true : $disabled;
	}
}
