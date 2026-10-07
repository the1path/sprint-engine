<?php
/**
 * Sprint content registration.
 *
 * @package SprintEngine
 */

namespace ThePath\SprintEngine\Content;

defined( 'ABSPATH' ) || exit;

/** Registers the admin-only Sprint authoring surface. */
final class SprintPostType {

	/** Register the post type; routing belongs to the later Runner ticket. */
	public static function register() {
		register_post_type(
			'sprint_engine_sprint',
			array(
				'labels'                => array(
					'name'                     => __( 'Sprints', 'sprint-engine' ),
					'singular_name'            => __( 'Sprint', 'sprint-engine' ),
					'add_new'                  => __( 'Add Sprint', 'sprint-engine' ),
					'add_new_item'             => __( 'Add New Sprint', 'sprint-engine' ),
					'edit_item'                => __( 'Edit Sprint', 'sprint-engine' ),
					'new_item'                 => __( 'New Sprint', 'sprint-engine' ),
					'view_item'                => __( 'View Sprint', 'sprint-engine' ),
					'view_items'               => __( 'View Sprints', 'sprint-engine' ),
					'search_items'             => __( 'Search Sprints', 'sprint-engine' ),
					'not_found'                => __( 'No Sprints found', 'sprint-engine' ),
					'not_found_in_trash'       => __( 'No Sprints found in Trash', 'sprint-engine' ),
					'all_items'                => __( 'All Sprints', 'sprint-engine' ),
					'archives'                 => __( 'Sprint archives', 'sprint-engine' ),
					'attributes'               => __( 'Sprint attributes', 'sprint-engine' ),
					'insert_into_item'         => __( 'Insert into Sprint', 'sprint-engine' ),
					'uploaded_to_this_item'    => __( 'Uploaded to this Sprint', 'sprint-engine' ),
					'filter_items_list'        => __( 'Filter Sprints', 'sprint-engine' ),
					'items_list_navigation'    => __( 'Sprints navigation', 'sprint-engine' ),
					'items_list'               => __( 'Sprints list', 'sprint-engine' ),
					'item_published'           => __( 'Sprint published.', 'sprint-engine' ),
					'item_published_privately' => __( 'Sprint published privately.', 'sprint-engine' ),
					'item_reverted_to_draft'   => __( 'Sprint reverted to draft.', 'sprint-engine' ),
					'item_scheduled'           => __( 'Sprint scheduled.', 'sprint-engine' ),
					'item_updated'             => __( 'Sprint updated.', 'sprint-engine' ),
					'item_link'                => __( 'Sprint link', 'sprint-engine' ),
					'item_link_description'    => __( 'A link to a Sprint.', 'sprint-engine' ),
				),
				'public'                => false,
				'publicly_queryable'    => false,
				'show_ui'               => true,
				'show_in_rest'          => true,
				'rest_controller_class' => EditorRestController::class,
				'capabilities'          => EditorRestController::capabilities(),
				'map_meta_cap'          => false,
				'supports'              => array( 'title', 'editor', 'excerpt', 'thumbnail', 'custom-fields' ),
				'has_archive'           => false,
				'rewrite'               => false,
				'query_var'             => false,
			)
		);
	}
}
