<?php
/**
 * Member Runner coordination over the existing ProgressService.
 *
 * @package SprintEngine
 */

namespace ThePath\SprintEngine\Runner;

use ThePath\SprintEngine\Access\AccessManager;
use ThePath\SprintEngine\Assets;
use ThePath\SprintEngine\Content\Meta;
use ThePath\SprintEngine\Content\StructureManager;
use ThePath\SprintEngine\Progress\ProgressService;

defined( 'ABSPATH' ) || exit;

/** Resolves access/state and selects the standalone plugin template. */
final class Runner {

	/**
	 * Single access boundary.
	 *
	 * @var AccessManager
	 */
	private $access;
	/**
	 * Existing lifecycle service.
	 *
	 * @var ProgressService
	 */
	private $progress;
	/**
	 * Authorized presentation context for this request.
	 *
	 * @var array|null
	 */
	private $context;

	/**
	 * Compose existing services for authorized Runner rendering.
	 *
	 * @param AccessManager|null   $access Access policy boundary.
	 * @param ProgressService|null $progress Existing lifecycle service.
	 */
	public function __construct( ?AccessManager $access = null, ?ProgressService $progress = null ) {
		$this->access   = $access ?? new AccessManager();
		$this->progress = $progress ?? new ProgressService();
	}

	/** Register scoped frontend hooks. */
	public function register_hooks() {
		add_action( 'template_redirect', array( $this, 'prepare' ), 0 );
		add_filter( 'template_include', array( $this, 'template' ), 99 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ), 20 );
		add_filter( 'pre_get_document_title', array( $this, 'document_title' ) );
	}

	/**
	 * Resolve only the current authenticated user's read/resume state.
	 * No request-supplied user or Step IDs are accepted.
	 *
	 * @param string $slug Captured route slug.
	 * @return array Presentation context, login instruction or safe error.
	 */
	public function resolve( $slug ) {
		if ( ! is_string( $slug ) || '' === $slug || preg_match( '~[/\\\\\x00-\x20]~', rawurldecode( $slug ) ) ) {
			return $this->unavailable();
		}
		$sprint = get_page_by_path( $slug, OBJECT, array( 'sprint_engine_sprint' ) );
		if ( '' !== Availability::reason( $sprint ) ) {
			return $this->unavailable();
		}
		if ( ! is_user_logged_in() ) {
			return array(
				'state'     => 'login',
				'status'    => 302,
				'login_url' => wp_login_url( Routes::url( $sprint->post_name ) ),
			);
		}
		$user = get_current_user_id();
		if ( ! $this->access->can_access( $user, $sprint->ID ) ) {
			return $this->unavailable( 403 );
		}
		$state = $this->progress->get_state( $user, $sprint->ID );
		if ( ! is_wp_error( $state ) && 'in_progress' === $state['status'] ) {
			$state = $this->progress->resume_sprint( $user, $sprint->ID );
		}
		if ( is_wp_error( $state ) ) {
			return $this->unavailable( 503 );
		}
		$context = array(
			'state'         => $state['status'],
			'status'        => 200,
			'sprint_id'     => $sprint->ID,
			'title'         => $sprint->post_title,
			'canonical_url' => Routes::url( $sprint->post_name ),
			'progress'      => $state,
		);
		if ( 'not_started' === $state['status'] ) {
			$context['duration'] = Meta::sprint_duration( $sprint->ID );
			$context['overview'] = '' !== $sprint->post_excerpt ? wpautop( esc_html( $sprint->post_excerpt ) ) : $this->content( $sprint );
		} elseif ( 'in_progress' === $state['status'] ) {
			$step = $state['current_step_id'];
			if ( ! is_int( $step ) || ! Meta::belongs_to( $step, $sprint->ID ) ) {
				return $this->unavailable( 503 );
			}
			$post = get_post( $step );
			if ( ! $post || '' !== $post->post_password ) {
				return $this->unavailable();
			}
			$meta  = Meta::read( $step, 'sprint_engine_step' );
			$order = wp_list_pluck( Meta::steps( $sprint->ID ), 'ID' );
			$index = array_search( $step, $order, true );
			// Presentation only: never use position to select or advance the current Step.
			$valid           = false !== $index && count( $order ) === $state['total_steps'] && StructureManager::is_linear( $sprint->ID );
			$context['step'] = array(
				'id'                => $step,
				'title'             => $post->post_title,
				'stage'             => $meta['_sprint_engine_stage_label'],
				'mode'              => 'task' === $meta['_sprint_engine_mode'] ? 'task' : 'content',
				'estimated_minutes' => $meta['_sprint_engine_estimated_minutes'],
				'position'          => $valid ? $index + 1 : null,
				'is_terminal'       => $valid && count( $order ) - 1 === $index && 0 === $meta['_sprint_engine_next_step_id'],
				'content'           => $this->content( $post ),
			);
		} elseif ( 'completed' === $state['status'] ) {
			$meta                  = Meta::read( $sprint->ID, 'sprint_engine_sprint' );
			$context['completion'] = array(
				'message' => ( new Meta() )->sanitize( $meta['_sprint_engine_completion_message'], '_sprint_engine_completion_message' ),
				'label'   => ( new Meta() )->sanitize( $meta['_sprint_engine_completion_cta_label'], '_sprint_engine_completion_cta_label' ),
				'url'     => Meta::completion_url( $meta['_sprint_engine_completion_cta_url'] ),
			);
		} else {
			return $this->unavailable( 503 );
		}
		$image_post                = 'in_progress' === $state['status'] ? $context['step']['id'] : $sprint->ID;
		$image_id                  = get_post_thumbnail_id( $image_post );
		$context['featured_image'] = $image_id && 'attachment' === get_post_type( $image_id ) && 'trash' !== get_post_status( $image_id ) && wp_attachment_is_image( $image_id ) ? wp_get_attachment_image( $image_id, 'large', false, array( 'class' => 'se-runner__featured-image' ) ) : '';
		return $context;
	}

	/** Set response semantics before WordPress's normal canonical redirects. */
	public function prepare() {
		$slug = Routes::slug();
		if ( null === $slug ) {
			return;
		}
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		nocache_headers();
		header( 'Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0' );
		header( 'X-Robots-Tag: noindex, nofollow', true );
		remove_action( 'template_redirect', 'redirect_canonical' );
		remove_action( 'template_redirect', 'wp_old_slug_redirect' );
		remove_action( 'wp_head', '_wp_render_title_tag', 1 );
		show_admin_bar( false );
		$this->context = $this->resolve( $slug );
		if ( 'login' === $this->context['state'] ) {
			wp_safe_redirect( $this->context['login_url'], 302, 'Sprint Engine' );
			exit;
		}
		global $wp_query;
		// This application route must not carry an unrelated home/feed/post query.
		$wp_query->init();
		$wp_query->is_404 = 404 === $this->context['status'];
		status_header( $this->context['status'] );
	}

	/**
	 * Select a controlled template and supply only the authorized context.
	 *
	 * @param string $template Normal WordPress template.
	 * @return string
	 */
	public function template( $template ) {
		if ( null === Routes::slug() || null === $this->context ) {
			return $template;
		}
		set_query_var( 'sprint_engine_runner_context', $this->context );
		return $this->template_path( $this->context );
	}

	/**
	 * Allow trusted PHP templates inside installed plugin/theme code directories.
	 * Resolve symlinks; reject uploads, wrappers, non-PHP and unreadable paths.
	 *
	 * @param array $context Authorized context.
	 * @return string Valid template path, falling back to the shipped template.
	 */
	public function template_path( $context ) {
		$default  = dirname( __DIR__, 2 ) . '/templates/runner.php';
		$filtered = apply_filters( 'sprint_engine/runner_template', $default, $context ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- Public hook spelling required by the specification.
		if ( ! is_string( $filtered ) || false !== strpos( $filtered, "\0" ) || false !== strpos( $filtered, '://' ) ) {
			return $default;
		}
		$path = realpath( $filtered );
		if ( ! $path || ! is_file( $path ) || ! is_readable( $path ) || 'php' !== strtolower( pathinfo( $path, PATHINFO_EXTENSION ) ) ) {
			return $default;
		}
		foreach ( array( WP_PLUGIN_DIR, WPMU_PLUGIN_DIR, get_theme_root() ) as $root ) {
			$root = realpath( $root );
			if ( $root ) {
				$prefix    = trailingslashit( wp_normalize_path( $root ) );
				$candidate = wp_normalize_path( $path );
				if ( '\\' === DIRECTORY_SEPARATOR ) {
					$prefix    = strtolower( $prefix );
					$candidate = strtolower( $candidate );
				}
				if ( 0 === strpos( $candidate, $prefix ) ) {
					return $path;
				}
			}
		}
		return $default;
	}

	/** Enqueue the simple shell only for a matched frontend Runner route. */
	public function enqueue() {
		if ( is_admin() ) {
			return;
		}
		if ( null !== Routes::slug() ) {
			wp_enqueue_style( 'sprint-engine-runner', plugins_url( 'assets/css/runner.css', dirname( __DIR__, 2 ) . '/sprint-engine.php' ), array(), Assets::version( 'assets/css/runner.css' ) );
			wp_add_inline_style( 'sprint-engine-runner', 'body.se-runner{' . \ThePath\SprintEngine\Settings\RunnerBranding::css() . '}' );
			if ( is_user_logged_in() && null !== $this->context && in_array( $this->context['state'], array( 'not_started', 'in_progress', 'completed' ), true ) ) {
				$start   = 'not_started' === $this->context['state'];
				$restart = 'completed' === $this->context['state'];
				$route   = $restart ? 'sprints/' . $this->context['sprint_id'] . '/restart' : ( $start ? 'sprints/' . $this->context['sprint_id'] . '/start' : 'steps/' . $this->context['step']['id'] . '/complete' );
				wp_enqueue_script( 'sprint-engine-runner', plugins_url( 'assets/js/runner.js', dirname( __DIR__, 2 ) . '/sprint-engine.php' ), array(), Assets::version( 'assets/js/runner.js' ), true );
				wp_localize_script(
					'sprint-engine-runner',
					'sprintEngineRunner',
					array(
						'endpoint'  => rest_url( 'sprint-engine/v1/' . $route ),
						'nonce'     => wp_create_nonce( 'wp_rest' ),
						'runnerUrl' => $this->context['canonical_url'],
						'busy'      => $restart ? __( 'Restarting Sprint…', 'sprint-engine' ) : ( $start ? __( 'Starting Sprint…', 'sprint-engine' ) : __( 'Saving progress…', 'sprint-engine' ) ),
						'confirm'   => $restart ? __( 'Restart this Sprint? Your previous completed attempt will be kept and you will start again from the first Step.', 'sprint-engine' ) : '',
						'error'     => __( 'The request could not be confirmed. Try again, or reload the Sprint to check your progress.', 'sprint-engine' ),
					)
				);
			}
		}
	}

	/**
	 * Keep document titles useful without exposing unavailable content.
	 *
	 * @param string $title Existing document title.
	 * @return string
	 */
	public function document_title( $title ) {
		return null !== Routes::slug() && null !== $this->context ? ( $this->context['title'] ?? __( 'Sprint unavailable', 'sprint-engine' ) ) . ' — ' . get_bloginfo( 'name' ) : $title;
	}

	/**
	 * Run normal Gutenberg/content filters with the correct temporary post context.
	 *
	 * @param \WP_Post $post Authorized content object.
	 * @return string Rendered HTML.
	 */
	private function content( $post ) {
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
	 * Generic unavailable state with no private titles, content or service errors.
	 *
	 * @param int $status HTTP status.
	 * @return array
	 */
	private function unavailable( $status = 404 ) {
		return array(
			'state'   => 'error',
			'status'  => $status,
			'message' => 503 === $status ? __( 'Your Sprint could not be loaded safely. Try again, or contact the site administrator.', 'sprint-engine' ) : __( 'This Sprint is not available. Return to the site or contact the site administrator.', 'sprint-engine' ),
		);
	}
}
