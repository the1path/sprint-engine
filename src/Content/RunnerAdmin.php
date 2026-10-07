<?php
/**
 * Native slug and canonical Runner links in Sprint administration.
 *
 * @package SprintEngine
 */

namespace ThePath\SprintEngine\Content;

use ThePath\SprintEngine\Runner\Availability;
use ThePath\SprintEngine\Assets;
use ThePath\SprintEngine\Runner\Routes;

defined( 'ABSPATH' ) || exit;

/** Adds conveniences without introducing another permalink or save endpoint. */
final class RunnerAdmin {

	/** Register hooks scoped by post type and capability at their boundaries. */
	public function register_hooks() {
		add_action( 'add_meta_boxes_sprint_engine_sprint', array( $this, 'add_box' ) );
		add_filter( 'post_row_actions', array( $this, 'row_actions' ), 10, 2 );
		add_filter( 'manage_sprint_engine_sprint_posts_columns', array( $this, 'columns' ) );
		add_action( 'manage_sprint_engine_sprint_posts_custom_column', array( $this, 'column' ), 10, 2 );
		add_action( 'quick_edit_custom_box', array( $this, 'quick_edit' ), 10, 2 );
		add_action( 'admin_init', array( $this, 'guard_slug' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/** Replace the hidden legacy slug box with one block-editor-compatible panel. */
	public function add_box() {
		remove_meta_box( 'slugdiv', 'sprint_engine_sprint', 'normal' );
		add_meta_box( 'sprint_engine_runner_url', __( 'Runner URL', 'sprint-engine' ), array( $this, 'render' ), 'sprint_engine_sprint', 'normal', 'default', array( '__block_editor_compatible_meta_box' => true ) );
	}

	/**
	 * Render saved canonical values; native post.php/metabox saving owns post_name.
	 *
	 * @param \WP_Post $post Sprint being edited.
	 */
	public function render( $post ) {
		if ( 'sprint_engine_sprint' !== $post->post_type || ! current_user_can( 'edit_post', $post->ID ) ) {
			return;
		}
		echo '<p><label for="post_name">' . esc_html__( 'Slug', 'sprint-engine' ) . '</label><input class="large-text" type="text" name="post_name" id="post_name" value="' . esc_attr( $post->post_name ) . '" autocomplete="off" spellcheck="false"></p>';
		if ( 'auto-draft' === $post->post_status || '' === $post->post_name ) {
			echo '<p>' . esc_html__( 'Save this Sprint first to generate its Runner URL.', 'sprint-engine' ) . '</p>';
			return;
		}
		echo '<p><label for="se-runner-url">' . esc_html__( 'Canonical Runner URL', 'sprint-engine' ) . '</label><input class="large-text" type="text" readonly id="se-runner-url" value="' . esc_url( Routes::url( $post->post_name ) ) . '"></p>';
		$reason = Availability::reason( $post );
		echo '<p>' . esc_html( $reason ? $reason : __( 'Runner available', 'sprint-engine' ) ) . '</p>';
		echo '<p>';
		if ( '' === $reason ) {
			echo '<a class="button" href="' . esc_url( Routes::url( $post->post_name ) ) . '">' . esc_html__( 'View Runner', 'sprint-engine' ) . '</a> ';
		}
		echo '<button type="button" class="button" id="se-copy-runner-url">' . esc_html__( 'Copy URL', 'sprint-engine' ) . '</button> <span id="se-copy-runner-status" role="status"></span></p>';
		echo '<p class="description">' . esc_html__( 'Save and reload after changing the slug or availability to refresh this panel. Members must log in and pass Sprint access checks.', 'sprint-engine' ) . '</p>';
	}

	/**
	 * Add only usable links for Sprints the current administrator can edit.
	 *
	 * @param array    $actions Existing actions.
	 * @param \WP_Post $post List row.
	 * @return array
	 */
	public function row_actions( $actions, $post ) {
		if ( 'sprint_engine_sprint' === $post->post_type && current_user_can( 'edit_post', $post->ID ) && '' !== $post->post_name && '' === Availability::reason( $post ) ) {
			$actions['sprint_engine_runner'] = '<a href="' . esc_url( Routes::url( $post->post_name ) ) . '">' . esc_html__( 'View Runner', 'sprint-engine' ) . '</a>';
		}
		return $actions;
	}

	/**
	 * Preserve native columns, sorting and filtering.
	 *
	 * @param array $columns Existing columns.
	 * @return array
	 */
	public function columns( $columns ) {
		$columns['sprint_engine_runner'] = __( 'Runner', 'sprint-engine' );
		return $columns;
	}

	/**
	 * Show a concise link or unavailable status.
	 *
	 * @param string $column Column key.
	 * @param int    $post_id Sprint ID.
	 */
	public function column( $column, $post_id ) {
		if ( 'sprint_engine_runner' !== $column || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$actions = $this->row_actions( array(), get_post( $post_id ) );
		if ( isset( $actions['sprint_engine_runner'] ) ) {
			echo $actions['sprint_engine_runner']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fully escaped by row_actions().
		} else {
			echo esc_html__( 'Unavailable', 'sprint-engine' );
		}
	}

	/**
	 * Core inline-edit-post populates post_name from its native hidden row data.
	 * Core inline-save owns capability, nonce, sanitisation and uniqueness checks.
	 *
	 * @param string $column Column key.
	 * @param string $post_type List post type.
	 */
	public function quick_edit( $column, $post_type ) {
		if ( 'sprint_engine_runner' !== $column || 'sprint_engine_sprint' !== $post_type || ! current_user_can( get_post_type_object( 'sprint_engine_sprint' )->cap->edit_posts ) ) {
			return;
		}
		echo '<fieldset class="inline-edit-col-left"><div class="inline-edit-col"><label><span class="title">' . esc_html__( 'Slug', 'sprint-engine' ) . '</span><span class="input-text-wrap"><input type="text" name="post_name" value="" autocomplete="off" spellcheck="false"></span></label></div></fieldset>';
	}

	/** Discard malformed native form input before core string sanitisation. */
	public function guard_slug() {
		// No write occurs here. Core verifies the nonce/capability before saving.
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		if ( isset( $_POST['post_type'] ) && 'sprint_engine_sprint' === $_POST['post_type'] && isset( $_POST['post_name'] ) && ! is_string( $_POST['post_name'] ) ) {
			unset( $_POST['post_name'] );
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing
	}

	/** Enqueue only the Sprint editor convenience script. */
	public function enqueue() {
		$screen = get_current_screen();
		if ( ! $screen || 'post' !== $screen->base || 'sprint_engine_sprint' !== $screen->post_type || ! current_user_can( get_post_type_object( 'sprint_engine_sprint' )->cap->edit_posts ) ) {
			return;
		}
		wp_enqueue_script( 'sprint-engine-runner-admin', plugins_url( 'assets/js/runner-admin.js', dirname( __DIR__, 2 ) . '/sprint-engine.php' ), $screen->is_block_editor() ? array( 'wp-data', 'wp-editor' ) : array(), Assets::version( 'assets/js/runner-admin.js' ), true );
		wp_localize_script(
			'sprint-engine-runner-admin',
			'sprintEngineRunnerAdmin',
			array(
				'copied'   => __( 'URL copied.', 'sprint-engine' ),
				'fallback' => __( 'Select and copy the URL field manually.', 'sprint-engine' ),
			)
		);
	}
}
