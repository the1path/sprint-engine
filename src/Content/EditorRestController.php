<?php
/**
 * Restricts WordPress's native editor API to administrators.
 *
 * @package SprintEngine
 */

namespace ThePath\SprintEngine\Content;

defined( 'ABSPATH' ) || exit;

/** Protects published content while retaining Gutenberg's native REST support. */
class EditorRestController extends \WP_REST_Posts_Controller {

	/**
	 * Use existing admin capabilities until author roles are explicitly designed.
	 *
	 * @return array
	 */
	public static function capabilities() {
		return array_fill_keys(
			array( 'edit_post', 'read_post', 'delete_post', 'edit_posts', 'edit_others_posts', 'publish_posts', 'read_private_posts', 'delete_posts', 'delete_private_posts', 'delete_published_posts', 'delete_others_posts', 'edit_private_posts', 'edit_published_posts', 'create_posts', 'read' ),
			'manage_options'
		);
	}

	/**
	 * Gate collection reads, including published posts.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return true|\WP_Error
	 */
	public function get_items_permissions_check( $request ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return $this->access_error();
		}
		return parent::get_items_permissions_check( $request );
	}

	/**
	 * Gate individual reads, including published posts.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return true|\WP_Error
	 */
	public function get_item_permissions_check( $request ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return $this->access_error();
		}
		return parent::get_item_permissions_check( $request );
	}

	/**
	 * Return a generic error without exposing content.
	 *
	 * @return \WP_Error
	 */
	private function access_error() {
		return new \WP_Error( 'rest_forbidden', __( 'You cannot access Sprint authoring content.', 'sprint-engine' ), array( 'status' => rest_authorization_required_code() ) );
	}
}
