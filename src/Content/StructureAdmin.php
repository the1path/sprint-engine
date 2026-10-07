<?php
/**
 * Small native admin surface and authenticated AJAX adapter for linear orders.
 *
 * @package SprintEngine
 */

namespace ThePath\SprintEngine\Content;

use ThePath\SprintEngine\Assets;

defined( 'ABSPATH' ) || exit;

/** Presents calculated links; all write decisions belong to StructureManager. */
final class StructureAdmin {

	/** Register admin-only assets and authenticated AJAX. */
	public function register_hooks() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'wp_ajax_sprint_engine_structure', array( $this, 'handle' ) );
	}

	/** Load only on the Sprint editor, using WordPress's bundled Sortable. */
	public function enqueue() {
		$screen = get_current_screen();
		if ( ! $screen || 'post' !== $screen->base || 'sprint_engine_sprint' !== $screen->post_type || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$plugin       = dirname( __DIR__, 2 ) . '/sprint-engine.php';
		$dependencies = array( 'jquery', 'jquery-ui-sortable' );
		if ( $screen->is_block_editor() ) {
			$dependencies = array_merge( $dependencies, array( 'wp-data', 'wp-editor' ) );
		}
		wp_enqueue_script( 'sprint-engine-structure', plugins_url( 'assets/js/structure-admin.js', $plugin ), $dependencies, Assets::version( 'assets/js/structure-admin.js' ), true );
		wp_enqueue_style( 'sprint-engine-structure', plugins_url( 'assets/css/structure-admin.css', $plugin ), array(), Assets::version( 'assets/css/structure-admin.css' ) );
		wp_localize_script(
			'sprint-engine-structure',
			'sprintEngineStructure',
			array(
				'url'          => admin_url( 'admin-ajax.php' ),
				'start'        => __( 'Start', 'sprint-engine' ),
				'final'        => __( 'Final', 'sprint-engine' ),
				'end'          => __( 'End', 'sprint-engine' ),
				'none'         => __( 'None', 'sprint-engine' ),
				'unsaved'      => __( 'Order changed. Save order to apply these positions and links.', 'sprint-engine' ),
				'saving'       => __( 'Saving structure…', 'sprint-engine' ),
				'saved'        => __( 'Structure saved.', 'sprint-engine' ),
				'error'        => __( 'The request did not complete. Reload to check the saved structure before retrying.', 'sprint-engine' ),
				'reload'       => __( 'Reload this editor before retrying.', 'sprint-engine' ),
				'title'        => __( 'Enter a Step title.', 'sprint-engine' ),
				'refreshError' => __( 'Sprint saved, but Sprint Structure could not be loaded. Save again to retry, or reload this editor.', 'sprint-engine' ),
				'removed'      => __( 'Step moved to Trash. Structure saved.', 'sprint-engine' ),
				/* translators: %s: Step title. */
				'confirm'      => __( 'Move "%s" to Trash? This removes the Step from this Sprint and rebuilds the remaining structure. You can recover it from WordPress Trash, but restoring it will leave it unassigned.', 'sprint-engine' ),
			)
		);
	}

	/**
	 * Render a standalone fragment, also returned after a successful operation.
	 *
	 * @param int $sprint Saved Sprint ID.
	 */
	public static function render( $sprint ) {
		if ( ! current_user_can( 'manage_options' ) || ! current_user_can( 'edit_post', $sprint ) ) {
			return;
		}
		if ( ! Meta::valid_post( $sprint, 'sprint_engine_sprint' ) ) {
			$post = get_post( $sprint );
			if ( ! $post || 'sprint_engine_sprint' !== $post->post_type || 'auto-draft' !== $post->post_status ) {
				return;
			}
			echo '<div class="se-structure-locked" data-sprint="' . esc_attr( $sprint ) . '" data-nonce="' . esc_attr( wp_create_nonce( 'sprint_engine_structure_' . $sprint ) ) . '">';
			echo '<h3>' . esc_html__( 'Sprint Structure', 'sprint-engine' ) . '</h3>';
			echo '<p>' . esc_html__( 'Save this Sprint to start adding Steps.', 'sprint-engine' ) . '</p>';
			echo '<p class="description">' . esc_html__( 'Once the Sprint has been saved, you can create, order and manage its Steps here.', 'sprint-engine' ) . '</p>';
			echo '<p><button type="button" class="button" disabled>' . esc_html__( 'Add Step', 'sprint-engine' ) . '</button></p>';
			echo '<p class="se-structure-status" role="status" aria-live="polite"></p></div>';
			return;
		}
		$steps = Meta::steps( $sprint );
		echo '<div class="se-linear-manager" data-sprint="' . esc_attr( $sprint ) . '" data-nonce="' . esc_attr( wp_create_nonce( 'sprint_engine_structure_' . $sprint ) ) . '" data-revision="' . esc_attr( StructureManager::revision( $sprint ) ) . '">';
		echo '<h3>' . esc_html__( 'Sprint Structure', 'sprint-engine' ) . '</h3>';
		$unpublished = Meta::publication_readiness( $sprint )['unpublished'];
		if ( $unpublished ) {
			/* translators: %s: number of unpublished Steps. */
			$warning = _n( '%s Step is not published. Publish it before this Sprint can be launched.', '%s Steps are not published. Publish them before this Sprint can be launched.', $unpublished, 'sprint-engine' );
			echo '<p class="notice notice-warning se-publication-warning">' . esc_html( sprintf( $warning, number_format_i18n( $unpublished ) ) ) . '</p>';
		}
		echo '<p class="description">' . esc_html__( 'These Steps are shown after the member clicks Start Sprint. Put general welcome/introduction content in the main Sprint editor rather than creating a separate Welcome Step.', 'sprint-engine' ) . '</p>';
		echo '<p>' . esc_html__( 'Start Step:', 'sprint-engine' ) . ' <strong class="se-start-title">' . esc_html( $steps ? $steps[0]->post_title : __( 'None', 'sprint-engine' ) ) . '</strong></p>';
		echo '<p>' . esc_html__( 'Drag Steps or use Move up / Move down, then Save order. Positions and links below are calculated from this list. These controls save separately from the Sprint content.', 'sprint-engine' ) . '</p>';
		echo '<ol class="se-linear-list">';
		foreach ( $steps as $index => $step ) {
			$meta = Meta::read( $step->ID, 'sprint_engine_step' );
			echo '<li class="se-linear-row" data-step="' . esc_attr( $step->ID ) . '">';
			echo '<button type="button" class="se-drag" aria-label="' . esc_attr__( 'Drag to reorder Step', 'sprint-engine' ) . '">☰</button>';
			echo '<span class="se-position">' . esc_html( $index + 1 ) . '</span><div class="se-step-details"><strong class="se-step-title">' . esc_html( $step->post_title ) . '</strong>';
			$status = get_post_status_object( $step->post_status );
			echo ' <span class="se-step-status" data-status="' . esc_attr( $step->post_status ) . '">' . esc_html( $status ? $status->label : $step->post_status ) . '</span>';
			echo '<span class="se-stage">' . esc_html( $meta['_sprint_engine_stage_label'] ) . '</span></div><span class="se-boundary">';
			if ( 0 === $index ) {
				echo esc_html__( 'Start', 'sprint-engine' );
			}
			if ( count( $steps ) - 1 === $index ) {
				echo ' ' . esc_html__( 'Final', 'sprint-engine' );
			}
			echo '</span><span>' . esc_html( 'task' === $meta['_sprint_engine_mode'] ? __( 'Task', 'sprint-engine' ) : __( 'Content', 'sprint-engine' ) ) . '</span>';
			echo '<span>→ <span class="se-next-title">' . esc_html( isset( $steps[ $index + 1 ] ) ? $steps[ $index + 1 ]->post_title : __( 'End', 'sprint-engine' ) ) . '</span></span>';
			echo '<span class="se-row-actions"><button type="button" class="button se-move" data-direction="up" aria-label="' . esc_attr__( 'Move Step up', 'sprint-engine' ) . '">↑</button> ';
			echo '<button type="button" class="button se-move" data-direction="down" aria-label="' . esc_attr__( 'Move Step down', 'sprint-engine' ) . '">↓</button> ';
			echo '<a href="' . esc_url( get_edit_post_link( $step->ID ) ) . '">' . esc_html__( 'Edit', 'sprint-engine' ) . '</a> ';
			if ( current_user_can( 'edit_post', $step->ID ) && current_user_can( 'delete_post', $step->ID ) ) {
				echo '<span aria-hidden="true"> · </span><button type="button" class="button-link button-link-delete se-remove-step">' . esc_html__( 'Trash', 'sprint-engine' ) . '</button>';
			}
			echo '</span></li>';
		}
		echo '</ol><p><button type="button" class="button button-secondary se-save-order">' . esc_html__( 'Save order', 'sprint-engine' ) . '</button></p>';
		echo '<p class="se-quick-add"><label for="se-new-step-title">' . esc_html__( 'New Step title', 'sprint-engine' ) . '</label> <input type="text" id="se-new-step-title" class="regular-text se-new-step-title"> <button type="button" class="button se-add-step">' . esc_html__( 'Add Step', 'sprint-engine' ) . '</button></p>';
		echo '<p class="se-structure-status" role="status" aria-live="polite"></p>';
		if ( $steps && ! StructureManager::is_linear( $sprint ) ) {
			echo '<p class="notice notice-warning">' . esc_html__( 'Stored links or positions do not match this list. Review the order and Save order to rebuild the linear structure.', 'sprint-engine' ) . '</p>';
		}
		foreach ( Authoring::warnings( $sprint ) as $warning ) {
			echo '<p class="notice notice-warning">' . esc_html( $warning ) . '</p>';
		}
		echo '<noscript><p>' . esc_html__( 'Enable JavaScript to add and order Steps here.', 'sprint-engine' ) . '</p></noscript></div>';
	}

	/** Verify nonce and dispatch; no unauthenticated AJAX action is registered. */
	public function handle() {
		$sprint = isset( $_POST['sprint'] ) && is_string( $_POST['sprint'] ) ? filter_var( wp_unslash( $_POST['sprint'] ), FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 1 ) ) ) : false;
		if ( ! $sprint || ! check_ajax_referer( 'sprint_engine_structure_' . $sprint, 'nonce', false ) || ! current_user_can( 'manage_options' ) || ! current_user_can( 'edit_post', $sprint ) ) {
			wp_send_json_error( array( 'message' => __( 'Your session cannot change this Sprint. Reload and try again.', 'sprint-engine' ) ), 403 );
		}
		$operation = isset( $_POST['operation'] ) && is_string( $_POST['operation'] ) ? sanitize_key( $_POST['operation'] ) : '';
		$revision  = isset( $_POST['revision'] ) && is_string( $_POST['revision'] ) ? sanitize_text_field( wp_unslash( $_POST['revision'] ) ) : '';
		$manager   = new StructureManager();
		if ( 'refresh' === $operation ) {
			if ( ! Meta::valid_post( $sprint, 'sprint_engine_sprint' ) ) {
				wp_send_json_error( array( 'message' => __( 'Save this Sprint before loading its Structure.', 'sprint-engine' ) ), 400 );
			}
			// Read-only: return the existing escaped fragment without service writes.
			$result = true;
		} elseif ( 'order' === $operation ) {
			// JSON shape and every ID are validated by the service before writes.
			$order  = isset( $_POST['order'] ) && is_string( $_POST['order'] ) ? json_decode( wp_unslash( $_POST['order'] ), true ) : null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$result = $manager->apply_linear_order( $sprint, $order, $revision );
		} elseif ( 'add' === $operation ) {
			$title  = isset( $_POST['title'] ) && is_string( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
			$result = $manager->quick_add( $sprint, $title, $revision );
		} elseif ( 'remove' === $operation ) {
			$step   = isset( $_POST['step'] ) && is_string( $_POST['step'] ) ? filter_var( wp_unslash( $_POST['step'] ), FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 1 ) ) ) : false;
			$result = $manager->trash_step( $sprint, $step, $revision );
		} else {
			wp_send_json_error( array( 'message' => __( 'Unknown structure operation.', 'sprint-engine' ) ), 400 );
		}
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), $result->get_error_data()['status'] );
		}
		ob_start();
		self::render( $sprint );
		wp_send_json_success( array( 'html' => ob_get_clean() ) );
	}
}
