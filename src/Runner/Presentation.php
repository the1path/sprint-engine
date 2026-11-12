<?php
/**
 * Presentation shared by the member Runner and author preview.
 *
 * @package SprintEngine
 */

namespace ThePath\SprintEngine\Runner;

use ThePath\SprintEngine\Assets;
use ThePath\SprintEngine\Settings\RunnerBranding;

defined( 'ABSPATH' ) || exit;

/** Renders already-authorized content without consulting participant state. */
final class Presentation {

	/**
	 * Prepare the Step article data.
	 *
	 * @param \WP_Post $post Authorized Step, with optional autosaved title and content.
	 * @param array    $meta Saved Step metadata.
	 * @param int|null $position Verified position in the Sprint.
	 * @param bool     $is_terminal Whether the Step terminates the chain.
	 * @return array
	 */
	public static function step( \WP_Post $post, array $meta, ?int $position, bool $is_terminal = false ): array {
		return array(
			'id'                => $post->ID,
			'title'             => $post->post_title,
			'stage'             => $meta['_sprint_engine_stage_label'],
			'mode'              => 'task' === $meta['_sprint_engine_mode'] ? 'task' : 'content',
			'estimated_minutes' => $meta['_sprint_engine_estimated_minutes'],
			'position'          => $position,
			'is_terminal'       => $is_terminal,
			'content'           => self::content( $post ),
		);
	}

	/**
	 * Run normal Gutenberg/content filters with temporary post context.
	 *
	 * @param \WP_Post $post Authorized content object.
	 * @return string Rendered HTML.
	 */
	public static function content( \WP_Post $post ) {
		$globals = array( 'post', 'id', 'authordata', 'currentday', 'currentmonth', 'page', 'pages', 'multipage', 'more', 'numpages' );
		$saved   = array();
		foreach ( $globals as $key ) {
			$saved[ $key ] = array( array_key_exists( $key, $GLOBALS ), $GLOBALS[ $key ] ?? null );
		}
		$GLOBALS['post'] = $post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Scoped normal content rendering; restored below.
		try {
			setup_postdata( $post );
			return apply_filters( 'the_content', $post->post_content );
		} finally {
			foreach ( $saved as $key => $value ) {
				if ( $value[0] ) {
					$GLOBALS[ $key ] = $value[1];
				} else {
					unset( $GLOBALS[ $key ] );
				}
			}
		}
	}

	/**
	 * Render the configured banner, when it is a usable image attachment.
	 *
	 * @param int $post_id Authorized content ID.
	 * @return string
	 */
	public static function featured_image( int $post_id ): string {
		$image_id = get_post_thumbnail_id( $post_id );
		return $image_id && 'attachment' === get_post_type( $image_id ) && 'trash' !== get_post_status( $image_id ) && wp_attachment_is_image( $image_id ) ? wp_get_attachment_image( $image_id, 'large', false, array( 'class' => 'se-runner__featured-image' ) ) : '';
	}

	/** Enqueue the branded Runner stylesheet. */
	public static function enqueue_style() {
		wp_enqueue_style( 'sprint-engine-runner', plugins_url( 'assets/css/runner.css', dirname( __DIR__, 2 ) . '/sprint-engine.php' ), array(), Assets::version( 'assets/css/runner.css' ) );
		wp_add_inline_style( 'sprint-engine-runner', 'body.se-runner{' . RunnerBranding::css() . '}' );
	}
}
