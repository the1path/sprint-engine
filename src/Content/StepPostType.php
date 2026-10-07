<?php
/**
 * Step content registration.
 *
 * @package SprintEngine
 */

namespace ThePath\SprintEngine\Content;

defined( 'ABSPATH' ) || exit;

/** Registers Steps without public standalone pages. */
final class StepPostType {

	/** Register native block-editor authoring. */
	public static function register() {
		register_post_type(
			'sprint_engine_step',
			array(
				'labels'                => array(
					'name'                     => __( 'Sprint Steps', 'sprint-engine' ),
					'singular_name'            => __( 'Sprint Step', 'sprint-engine' ),
					'add_new'                  => __( 'Add Step', 'sprint-engine' ),
					'add_new_item'             => __( 'Add New Step', 'sprint-engine' ),
					'edit_item'                => __( 'Edit Step', 'sprint-engine' ),
					'new_item'                 => __( 'New Step', 'sprint-engine' ),
					'view_item'                => __( 'View Step', 'sprint-engine' ),
					'view_items'               => __( 'View Steps', 'sprint-engine' ),
					'search_items'             => __( 'Search Steps', 'sprint-engine' ),
					'not_found'                => __( 'No Steps found', 'sprint-engine' ),
					'not_found_in_trash'       => __( 'No Steps found in Trash', 'sprint-engine' ),
					'all_items'                => __( 'All Steps', 'sprint-engine' ),
					'archives'                 => __( 'Step archives', 'sprint-engine' ),
					'attributes'               => __( 'Step attributes', 'sprint-engine' ),
					'insert_into_item'         => __( 'Insert into Step', 'sprint-engine' ),
					'uploaded_to_this_item'    => __( 'Uploaded to this Step', 'sprint-engine' ),
					'filter_items_list'        => __( 'Filter Steps', 'sprint-engine' ),
					'items_list_navigation'    => __( 'Steps navigation', 'sprint-engine' ),
					'items_list'               => __( 'Steps list', 'sprint-engine' ),
					'item_published'           => __( 'Step published.', 'sprint-engine' ),
					'item_published_privately' => __( 'Step published privately.', 'sprint-engine' ),
					'item_reverted_to_draft'   => __( 'Step reverted to draft.', 'sprint-engine' ),
					'item_scheduled'           => __( 'Step scheduled.', 'sprint-engine' ),
					'item_updated'             => __( 'Step updated.', 'sprint-engine' ),
					'item_link'                => __( 'Step link', 'sprint-engine' ),
					'item_link_description'    => __( 'A link to a Step.', 'sprint-engine' ),
				),
				'public'                => false,
				'publicly_queryable'    => false,
				'show_ui'               => true,
				'show_in_rest'          => true,
				'rest_controller_class' => EditorRestController::class,
				'capabilities'          => EditorRestController::capabilities(),
				'map_meta_cap'          => false,
				'supports'              => array( 'title', 'editor', 'thumbnail', 'custom-fields' ),
				'has_archive'           => false,
				'rewrite'               => false,
				'query_var'             => false,
			)
		);
	}
}
