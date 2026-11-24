<?php
/**
 * Per-site first-install decision and native administrator screens.
 *
 * @package SprintEngine
 */

namespace ThePath\SprintEngine\Tutorial;

use ThePath\SprintEngine\Runner\Availability;
use ThePath\SprintEngine\Runner\Routes;

defined( 'ABSPATH' ) || exit;

/** Activation records intent only; content creation always requires explicit opt-in. */
final class Onboarding {

	const OPTION = 'sprint_engine_onboarding';

	/** Register capability-gated presentation and authenticated POST entry points. */
	public function register_hooks() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_notices', array( $this, 'notice' ) );
		add_action( 'admin_post_sprint_engine_tutorial', array( $this, 'handle' ) );
		add_action( 'edit_form_after_title', array( $this, 'editor_notice' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_filter( 'display_post_states', array( $this, 'post_states' ), 10, 2 );
	}

	/**
	 * Capture pre-existing evidence BEFORE the schema installer writes its markers.
	 * An interrupted first installation retains installing until a successful retry.
	 */
	public static function prepare_install() {
		global $wpdb;
		if ( false !== get_option( self::OPTION, false ) ) {
			return;
		}
		$existing = false !== get_option( 'sprint_engine_version', false ) || false !== get_option( 'sprint_engine_schema_version', false );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Detect legacy content independently of plugin versions.
		$content  = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_type IN (%s, %s) LIMIT 1", 'sprint_engine_sprint', 'sprint_engine_step' ) );
		$existing = $existing || $content || $wpdb->last_error;
		foreach ( array( 'sprint_engine_enrolments', 'sprint_engine_step_progress' ) as $suffix ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Detect a previous installation even if its version options are absent.
			$table    = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $wpdb->prefix . $suffix ) ) );
			$existing = $existing || $table || $wpdb->last_error;
		}
		add_option( self::OPTION, $existing ? 'existing' : 'installing', '', false );
	}

	/** Offer a choice only after successful installation; never redirect activation. */
	public static function finish_install() {
		global $wpdb;
		$previous = $wpdb->suppress_errors( true );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- An activation retry with stale option cache must never replace a saved choice.
		$wpdb->query( $wpdb->prepare( "UPDATE $wpdb->options SET option_value = %s WHERE option_name = %s AND option_value = %s", 'pending', self::OPTION, 'installing' ) );
		$wpdb->suppress_errors( $previous );
		wp_cache_delete( self::OPTION, 'options' );
	}

	/** Register a small welcome screen in the native Sprints menu. */
	public function menu() {
		add_submenu_page( 'edit.php?post_type=sprint_engine_sprint', __( 'Welcome to Sprint Engine', 'sprint-engine' ), __( 'Welcome', 'sprint-engine' ), 'manage_options', 'sprint-engine-welcome', array( $this, 'render' ) );
	}

	/** Post-activation navigation remains safe during bulk/CLI activation. */
	public function notice() {
		$screen = get_current_screen();
		if ( ! current_user_can( 'manage_options' ) || 'pending' !== get_option( self::OPTION ) || ! $screen || ( 'plugins' !== $screen->id && 'sprint_engine_sprint' !== $screen->post_type ) || str_contains( $screen->id, 'sprint-engine-welcome' ) ) {
			return;
		}
		echo '<div class="notice notice-info"><p>' . esc_html__( 'New to Sprint Engine? Choose whether to install a guided Tutorial Sprint.', 'sprint-engine' ) . ' <a href="' . esc_url( admin_url( 'edit.php?post_type=sprint_engine_sprint&page=sprint-engine-welcome' ) ) . '">' . esc_html__( 'Choose how to get started', 'sprint-engine' ) . '</a></p></div>';
	}

	/** Render the dedicated welcome page. */
	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You cannot manage these settings.', 'sprint-engine' ), '', array( 'response' => 403 ) );
		}
		echo '<div class="wrap"><h1>' . esc_html__( 'Welcome to Sprint Engine', 'sprint-engine' ) . '</h1>';
		self::panel();
		echo '</div>';
	}

	/** Shared Settings and welcome controls, outside the branding settings form. */
	public static function panel() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$id = Installer::find();
		?>
		<section aria-labelledby="sprint-engine-tutorial-title">
			<h2 id="sprint-engine-tutorial-title"><?php esc_html_e( 'Tutorial Sprint', 'sprint-engine' ); ?></h2>
			<p><?php esc_html_e( 'Learn by following a real Sprint while you build a different Sprint in another tab. Installing the tutorial is optional.', 'sprint-engine' ); ?></p>
			<p><?php esc_html_e( 'Your tutorial is published and ready to run, but only WordPress administrators can access it. You can edit or delete it at any time.', 'sprint-engine' ); ?></p>
			<?php if ( is_wp_error( $id ) ) : ?>
				<p role="alert"><?php echo esc_html( $id->get_error_message() ); ?></p>
			<?php elseif ( $id && 'trash' !== get_post_status( $id ) ) : ?>
				<?php if ( '' === Availability::reason( get_post( $id ) ) ) : ?>
					<p><a class="button button-primary" href="<?php echo esc_url( Routes::url( get_post_field( 'post_name', $id ) ) ); ?>"><?php esc_html_e( 'Open Tutorial Sprint', 'sprint-engine' ); ?></a></p>
				<?php else : ?>
					<p><?php echo esc_html( Availability::reason( get_post( $id ) ) ); ?></p>
				<?php endif; ?>
				<p><a href="<?php echo esc_url( get_edit_post_link( $id, 'raw' ) ); ?>"><?php esc_html_e( 'Edit Tutorial Sprint', 'sprint-engine' ); ?></a></p>
			<?php else : ?>
				<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
					<input type="hidden" name="action" value="sprint_engine_tutorial">
					<?php wp_nonce_field( 'sprint_engine_tutorial' ); ?>
					<p><button class="button button-primary" type="submit" name="choice" value="install"><?php echo esc_html( $id ? __( 'Restore Tutorial Sprint', 'sprint-engine' ) : __( 'Install Tutorial Sprint', 'sprint-engine' ) ); ?></button>
					<?php if ( 'pending' === get_option( self::OPTION ) ) : ?>
						<button class="button" type="submit" name="choice" value="skip"><?php esc_html_e( 'I know how to use Sprint Engine', 'sprint-engine' ); ?></button>
					<?php endif; ?></p>
				</form>
			<?php endif; ?>
		</section>
		<?php
	}

	/** Apply the explicit choice after POST, capability and nonce checks. */
	public function handle() {
		if ( ! current_user_can( 'manage_options' ) || 'POST' !== sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ?? '' ) ) ) {
			wp_die( esc_html__( 'This action is not permitted.', 'sprint-engine' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'sprint_engine_tutorial' );
		$choice = isset( $_POST['choice'] ) && is_string( $_POST['choice'] ) ? sanitize_key( wp_unslash( $_POST['choice'] ) ) : '';
		if ( 'skip' === $choice ) {
			// A stale Skip tab never erases an already installed decision.
			global $wpdb;
			$previous = $wpdb->suppress_errors( true );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Atomic compare-and-set prevents a concurrent Skip from replacing Installed.
			$saved = $wpdb->query( $wpdb->prepare( "UPDATE $wpdb->options SET option_value = %s WHERE option_name = %s AND option_value = %s", 'skipped', self::OPTION, 'pending' ) );
			$wpdb->suppress_errors( $previous );
			wp_cache_delete( self::OPTION, 'options' );
			if ( false === $saved ) {
				wp_die(
					esc_html__( 'Your choice could not be saved. Return to Settings and retry.', 'sprint-engine' ),
					'',
					array(
						'response'  => 500,
						'back_link' => true,
					)
				);
			}
		} elseif ( 'install' === $choice ) {
			$result = ( new Installer() )->install();
			if ( is_wp_error( $result ) ) {
				wp_die(
					esc_html( $result->get_error_message() ),
					esc_html__( 'Tutorial installation incomplete', 'sprint-engine' ),
					array(
						'response'  => 500,
						'back_link' => true,
					)
				);
			}
		} else {
			wp_die( esc_html__( 'Choose a valid tutorial action.', 'sprint-engine' ), '', array( 'response' => 400 ) );
		}
		wp_safe_redirect( admin_url( 'edit.php?post_type=sprint_engine_sprint&page=sprint-engine-settings' ) );
		exit;
	}

	/**
	 * Non-dismissible identity warning inside the Sprint editor.
	 *
	 * @param \WP_Post $post Edited post.
	 */
	public function editor_notice( $post ) {
		if ( current_user_can( 'manage_options' ) && 'sprint_engine_sprint' === $post->post_type && Marker::exists( $post->ID ) ) {
			echo '<div class="notice notice-info inline"><p>' . esc_html__( 'This is the Sprint Engine Tutorial Sprint — administrators only', 'sprint-engine' ) . '</p></div>';
		}
	}

	/** Show the same persistent notice in Gutenberg's native notice area. */
	public function enqueue() {
		global $post;
		$screen = get_current_screen();
		if ( ! $screen || ! $screen->is_block_editor() || ! $post || 'sprint_engine_sprint' !== $post->post_type || ! current_user_can( 'manage_options' ) || ! Marker::exists( $post->ID ) ) {
			return;
		}
		wp_enqueue_script( 'sprint-engine-tutorial-editor', plugins_url( 'assets/js/tutorial-editor.js', dirname( __DIR__, 2 ) . '/sprint-engine.php' ), array( 'wp-dom-ready', 'wp-data', 'wp-notices' ), \ThePath\SprintEngine\Assets::version( 'assets/js/tutorial-editor.js' ), true );
		wp_localize_script( 'sprint-engine-tutorial-editor', 'sprintEngineTutorial', array( 'notice' => __( 'This is the Sprint Engine Tutorial Sprint — administrators only', 'sprint-engine' ) ) );
	}

	/**
	 * Identify only marked Sprint rows in native admin lists.
	 *
	 * @param array    $states Existing labels.
	 * @param \WP_Post $post Listed post.
	 * @return array
	 */
	public function post_states( $states, $post ) {
		if ( current_user_can( 'manage_options' ) && 'sprint_engine_sprint' === $post->post_type && Marker::exists( $post->ID ) ) {
			$states['sprint_engine_tutorial'] = __( 'Admin-only tutorial', 'sprint-engine' );
		}
		return $states;
	}
}
