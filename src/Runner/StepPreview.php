<?php
/**
 * Private authoring preview for a single Step.
 *
 * @package SprintEngine
 */

namespace ThePath\SprintEngine\Runner;

use ThePath\SprintEngine\Assets;
use ThePath\SprintEngine\Content\Meta;
use ThePath\SprintEngine\Content\StructureManager;

defined( 'ABSPATH' ) || exit;

/** Serves the built-in Runner presentation independently of member routing. */
final class StepPreview {

	private const STEP_QUERY     = 'sprint_engine_step_preview';
	private const AUTOSAVE_QUERY = 'sprint_engine_preview_autosave';

	/**
	 * Whether this is a preview request on the site root.
	 *
	 * @var bool
	 */
	private $requested = false;

	/** Register editor and frontend hooks. */
	public function register_hooks() {
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor' ) );
		add_filter( 'request', array( $this, 'request' ) );
		add_action( 'template_redirect', array( $this, 'render' ), -1 );
	}

	/** Supply a session-bound preview URL only in the authorized Step editor. */
	public function enqueue_editor() {
		global $post;
		$screen = get_current_screen();
		if ( ! $screen || 'post' !== $screen->base || 'sprint_engine_step' !== $screen->post_type || ! $post || 'sprint_engine_step' !== $post->post_type ||
			! current_user_can( 'manage_options' ) || ! current_user_can( 'edit_post', $post->ID ) ) {
			return;
		}

		wp_enqueue_script(
			'sprint-engine-step-preview',
			plugins_url( 'assets/js/step-preview.js', dirname( __DIR__, 2 ) . '/sprint-engine.php' ),
			array( 'wp-api-fetch', 'wp-compose', 'wp-data', 'wp-edit-post', 'wp-editor', 'wp-element', 'wp-notices', 'wp-plugins' ),
			Assets::version( 'assets/js/step-preview.js' ),
			true
		);
		wp_localize_script(
			'sprint-engine-step-preview',
			'sprintEngineStepPreview',
			array(
				'url'           => add_query_arg(
					array(
						self::STEP_QUERY => $post->ID,
						'_wpnonce'       => wp_create_nonce( self::STEP_QUERY . '_' . $post->ID ),
					),
					home_url( '/' )
				),
				'autosaveParam' => self::AUTOSAVE_QUERY,
				'autosavePath'  => rest_get_route_for_post( $post ) . '/autosaves',
				'label'         => __( 'Preview Step', 'sprint-engine' ),
				'newTab'        => __( 'Preview Step (opens in a new tab)', 'sprint-engine' ),
				'loading'       => __( 'Preparing Step preview…', 'sprint-engine' ),
				'popupBlocked'  => __( 'Allow pop-ups for this site to open the Step preview.', 'sprint-engine' ),
				'saveError'     => __( 'Could not autosave this Step. Save your changes, then try Preview Step again.', 'sprint-engine' ),
			)
		);
	}

	/**
	 * Reserve root preview requests before WordPress queries posts or handles feeds.
	 *
	 * @param array $vars Parsed query variables.
	 * @return array
	 */
	public function request( $vars ) {
		global $wp;
		if ( is_admin() || '' !== $wp->request || isset( $vars['rest_route'] ) || ! isset( $_GET[ self::STEP_QUERY ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Authorization happens before rendering.
			return $vars;
		}
		// With plain permalinks WordPress may leave $wp->request empty on other paths.
		$path = wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Used only for exact comparison with the site root path.
		if ( wp_parse_url( home_url( '/' ), PHP_URL_PATH ) !== $path ) {
			return $vars;
		}
		$this->requested = true;
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		return array(
			'post__in'            => array( 0 ),
			'ignore_sticky_posts' => true,
		);
	}

	/** Authorize the requested Step and render without invoking member hooks. */
	public function render() {
		if ( ! $this->requested ) {
			return;
		}

		add_filter(
			'nocache_headers',
			static function ( $headers ) {
				$headers['Cache-Control'] = 'private, no-store, no-cache, must-revalidate, max-age=0';
				return $headers;
			}
		);
		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow', true );
		header( 'Referrer-Policy: no-referrer', true );

		$step_id = self::positive_id( wp_unslash( $_GET[ self::STEP_QUERY ] ?? '' ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- positive_id validates the entire value.
		$nonce   = isset( $_GET['_wpnonce'] ) && is_string( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
		if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) || ! wp_verify_nonce( $nonce, self::STEP_QUERY . '_' . $step_id ) ) {
			$this->unavailable( 403 );
		}
		if ( ! Meta::valid_post( $step_id, 'sprint_engine_step' ) ) {
			$this->unavailable( 404 );
		}
		if ( ! current_user_can( 'edit_post', $step_id ) ) {
			$this->unavailable( 403 );
		}

		global $wp_query;
		$wp_query->init();
		$post     = get_post( $step_id );
		$autosave = null;
		if ( isset( $_GET[ self::AUTOSAVE_QUERY ] ) ) {
			$autosave_id = self::positive_id( wp_unslash( $_GET[ self::AUTOSAVE_QUERY ] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- positive_id validates the entire value.
			$autosave    = wp_get_post_autosave( $step_id, get_current_user_id() );
			if ( ! $autosave || $autosave_id !== $autosave->ID || $step_id !== (int) $autosave->post_parent || get_current_user_id() !== (int) $autosave->post_author ) {
				$this->unavailable( 404 );
			}
			// Keep the canonical Step identity for blocks, attachments and saved metadata.
			$post               = clone $post;
			$post->post_title   = $autosave->post_title;
			$post->post_content = $autosave->post_content;
		}

		$context = $this->context( $post, null !== $autosave );
		status_header( 200 );
		show_admin_bar( false );
		remove_action( 'wp_head', '_wp_render_title_tag', 1 );
		add_filter(
			'pre_get_document_title',
			static function () use ( $post ) {
				return $post->post_title . ' — ' . __( 'Preview Mode', 'sprint-engine' );
			}
		);
		add_action( 'wp_enqueue_scripts', array( Presentation::class, 'enqueue_style' ), 20 );
		set_query_var( 'sprint_engine_runner_context', $context );
		require dirname( __DIR__, 2 ) . '/templates/runner.php';
		exit;
	}

	/**
	 * Derive display metadata from the saved structure, when it is valid.
	 *
	 * @param \WP_Post $post Authorized Step.
	 * @param bool     $autosaved Whether the title and content come from an autosave.
	 * @return array
	 */
	private function context( \WP_Post $post, bool $autosaved ): array {
		$meta     = Meta::read( $post->ID, 'sprint_engine_step' );
		$sprint   = $meta['_sprint_engine_sprint_id'];
		$position = null;
		$context  = array(
			'state'          => 'preview',
			'autosaved'      => $autosaved,
			'featured_image' => Presentation::featured_image( $post->ID ),
		);
		if ( Meta::valid_post( $sprint, 'sprint_engine_sprint' ) && current_user_can( 'edit_post', $sprint ) ) {
			$context['title'] = get_post( $sprint )->post_title;
			if ( StructureManager::is_linear( $sprint ) ) {
				$order = wp_list_pluck( Meta::steps( $sprint ), 'ID' );
				$index = array_search( $post->ID, $order, true );
				if ( false !== $index ) {
					$position              = $index + 1;
					$context['step_total'] = count( $order );
				}
			}
		}
		$context['step'] = Presentation::step( $post, $meta, $position );
		return $context;
	}

	/**
	 * Parse a request ID without accepting malformed, negative or overflowing values.
	 *
	 * @param mixed $value Raw query parameter.
	 * @return int
	 */
	private static function positive_id( $value ): int {
		return is_string( $value ) && preg_match( '/^[1-9][0-9]*$/D', $value ) ? (int) filter_var( $value, FILTER_VALIDATE_INT ) : 0;
	}

	/**
	 * End a preview request without disclosing private titles or content.
	 *
	 * @param int $status HTTP status code.
	 */
	private function unavailable( int $status ) {
		wp_die( esc_html__( 'This Step preview is unavailable. Reopen Preview Step from the Step editor.', 'sprint-engine' ), '', array( 'response' => $status ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The argument selects the HTTP response status.
	}
}
