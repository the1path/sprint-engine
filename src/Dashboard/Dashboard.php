<?php
/**
 * Authenticated, read-only member Dashboard coordination.
 *
 * @package SprintEngine
 */

namespace ThePath\SprintEngine\Dashboard;

use ThePath\SprintEngine\Access\AccessManager;
use ThePath\SprintEngine\Assets;
use ThePath\SprintEngine\Content\Meta;
use ThePath\SprintEngine\Progress\ProgressService;
use ThePath\SprintEngine\Runner\Availability;
use ThePath\SprintEngine\Runner\Routes as RunnerRoutes;
use ThePath\SprintEngine\Settings\RunnerBranding;

defined( 'ABSPATH' ) || exit;

/** Composes existing access and canonical state without writing member progress. */
final class Dashboard {

	/**
	 * Access boundary.
	 *
	 * @var AccessManager
	 */
	private $access;
	/**
	 * Canonical progress service.
	 *
	 * @var ProgressService
	 */
	private $progress;
	/**
	 * Authorized presentation context.
	 *
	 * @var array|null
	 */
	private $context;

	/**
	 * Compose the existing member boundaries.
	 *
	 * @param AccessManager|null   $access Access policy.
	 * @param ProgressService|null $progress Canonical lifecycle service.
	 */
	public function __construct( ?AccessManager $access = null, ?ProgressService $progress = null ) {
		$this->access   = $access ?? new AccessManager();
		$this->progress = $progress ?? new ProgressService();
	}

	/** Register scoped application hooks. */
	public function register_hooks() {
		add_action( 'template_redirect', array( $this, 'prepare' ), 0 );
		add_filter( 'template_include', array( $this, 'template' ), 99 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ), 20 );
		add_filter( 'pre_get_document_title', array( $this, 'document_title' ) );
	}

	/**
	 * Read only the logged-in member's eligible Sprints and latest attempts.
	 * One get_state call per authorized Sprint deliberately preserves service rules.
	 *
	 * @return array Login, safe error, or authorized items.
	 */
	public function resolve() {
		if ( ! is_user_logged_in() ) {
			return array(
				'state'     => 'login',
				'status'    => 302,
				'login_url' => wp_login_url( Routes::url() ),
			);
		}
		$user  = get_current_user_id();
		$items = array();
		foreach ( get_posts(
			array(
				'post_type'    => 'sprint_engine_sprint',
				'post_status'  => 'publish',
				'numberposts'  => -1,
				'has_password' => false,
			)
		) as $sprint ) {
			if ( '' !== Availability::reason( $sprint ) || ! $this->access->can_access( $user, $sprint->ID ) ) {
				continue;
			}
			$state = $this->progress->get_state( $user, $sprint->ID );
			if ( is_wp_error( $state ) || ! in_array( $state['status'], array( 'not_started', 'in_progress', 'completed' ), true ) ) {
				return array(
					'state'   => 'error',
					'status'  => 503,
					'message' => __( 'Your Sprints could not be loaded safely. Try again, or contact the site administrator.', 'sprint-engine' ),
				);
			}
			$image_id = get_post_thumbnail_id( $sprint->ID );
			$items[]  = array(
				'sprint_id'        => $sprint->ID,
				'title'            => $sprint->post_title,
				'excerpt'          => $sprint->post_excerpt,
				'runner_url'       => RunnerRoutes::url( $sprint->post_name ),
				'featured_image'   => $image_id && 'attachment' === get_post_type( $image_id ) && 'trash' !== get_post_status( $image_id ) && wp_attachment_is_image( $image_id ) ? wp_get_attachment_image( $image_id, 'medium_large', false, array( 'class' => 'se-dashboard__image' ) ) : '',
				'duration'         => Meta::sprint_duration( $sprint->ID ),
				'state'            => $state['status'],
				'completed_steps'  => $state['completed_steps'],
				'total_steps'      => $state['total_steps'],
				'percentage'       => $state['percentage'],
				'started_at'       => $state['started_at'],
				'last_activity_at' => $state['last_activity_at'],
				'completed_at'     => $state['completed_at'],
				'attempt_id'       => $state['attempt_id'],
				'attempt_number'   => $state['attempt_number'],
			);
		}
		$items    = apply_filters( 'sprint_engine/dashboard_items', $items, $user ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- Intentional public extension seam.
		$sections = array(
			'in_progress' => array(),
			'not_started' => array(),
			'completed'   => array(),
		);
		foreach ( $items as $item ) {
			$sections[ $item['state'] ][] = $item;
		}
		foreach ( $sections as $state => &$section ) {
			usort(
				$section,
				static function ( $left, $right ) use ( $state ) {
					$key   = 'completed' === $state ? 'completed_at' : 'last_activity_at';
					$order = 'not_started' === $state ? 0 : self::timestamp( $right[ $key ] ) <=> self::timestamp( $left[ $key ] );
					if ( 0 !== $order ) {
						return $order;
					}
					$title_order = strcasecmp( $left['title'], $right['title'] );
					return 0 !== $title_order ? $title_order : $left['sprint_id'] <=> $right['sprint_id'];
				}
			);
		}
		unset( $section );
		return array(
			'state'    => 'ready',
			'status'   => 200,
			'sections' => $sections,
		);
	}

	/**
	 * Validate UTC database timestamps; null/invalid values sort last.
	 *
	 * @param mixed $value Timestamp.
	 * @return int
	 */
	private static function timestamp( $value ) {
		if ( ! is_string( $value ) || ! preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/D', $value ) ) {
			return PHP_INT_MIN;
		}
		$date = \DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', $value, new \DateTimeZone( 'UTC' ) );
		return $date && $date->format( 'Y-m-d H:i:s' ) === $value ? $date->getTimestamp() : PHP_INT_MIN;
	}

	/** Authenticate and apply private application response semantics. */
	public function prepare() {
		if ( ! Routes::matches() ) {
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
		$this->context = $this->resolve();
		if ( 'login' === $this->context['state'] ) {
			wp_safe_redirect( $this->context['login_url'], 302, 'Sprint Engine' );
			exit;
		}
		global $wp_query;
		$wp_query->init();
		status_header( $this->context['status'] );
	}

	/**
	 * Supply authorized context to the controlled standalone template.
	 *
	 * @param string $template Normal template.
	 * @return string
	 */
	public function template( $template ) {
		if ( ! Routes::matches() || null === $this->context ) {
			return $template;
		}
		set_query_var( 'sprint_engine_dashboard_context', $this->context );
		return $this->template_path( $this->context );
	}

	/**
	 * Accept readable local PHP only inside installed plugin/mu-plugin/theme code.
	 *
	 * @param array $context Authorized context.
	 * @return string
	 */
	public function template_path( $context ) {
		$default  = dirname( __DIR__, 2 ) . '/templates/dashboard.php';
		$filtered = apply_filters( 'sprint_engine/dashboard_template', $default, $context ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- Public hook spelling required by the specification.
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


	/** Enqueue Dashboard assets only on the matched application route. */
	public function enqueue() {
		if ( ! Routes::matches() ) {
			return;
		}
		$base = dirname( __DIR__, 2 ) . '/sprint-engine.php';
		wp_enqueue_style( 'sprint-engine-dashboard', plugins_url( 'assets/css/dashboard.css', $base ), array(), Assets::version( 'assets/css/dashboard.css' ) );
		wp_add_inline_style( 'sprint-engine-dashboard', 'body.se-dashboard{' . RunnerBranding::css() . '}' );
		if ( ! is_user_logged_in() || null === $this->context || 'ready' !== $this->context['state'] || empty( $this->context['sections']['completed'] ) ) {
			return;
		}
		wp_enqueue_script( 'sprint-engine-dashboard', plugins_url( 'assets/js/dashboard.js', $base ), array(), Assets::version( 'assets/js/dashboard.js' ), true );
		wp_localize_script(
			'sprint-engine-dashboard',
			'sprintEngineDashboard',
			array(
				'nonce'   => wp_create_nonce( 'wp_rest' ),
				'confirm' => __( 'Restart this Sprint? Your previous completed attempt will be kept and you will start again from the first Step.', 'sprint-engine' ),
				'busy'    => __( 'Restarting Sprint…', 'sprint-engine' ),
				'error'   => __( 'The restart could not be confirmed. Try again, or reload My Sprints to check your progress.', 'sprint-engine' ),
			)
		);
	}

	/**
	 * Use the member page title only on this route.
	 *
	 * @param string $title Existing title.
	 * @return string
	 */
	public function document_title( $title ) {
		return Routes::matches() && null !== $this->context ? __( 'My Sprints', 'sprint-engine' ) . ' — ' . get_bloginfo( 'name' ) : $title;
	}
}
