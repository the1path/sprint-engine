<?php
/**
 * Native WordPress Runner branding settings page.
 *
 * @package SprintEngine
 */

namespace ThePath\SprintEngine\Settings;

use ThePath\SprintEngine\Assets;

defined( 'ABSPATH' ) || exit;

/** Settings API controller; no progress or content writes. */
final class SettingsPage {
	/**
	 * Page-specific asset hook.
	 *
	 * @var string
	 */
	private $hook = '';

	/** Register admin-only entry points. */
	public function register_hooks() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_init', array( $this, 'register' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'admin_post_sprint_engine_reset_branding', array( $this, 'reset' ) );
	}

	/** Add a submenu to the existing Sprints menu. */
	public function menu() {
		$this->hook = add_submenu_page( 'edit.php?post_type=sprint_engine_sprint', __( 'Sprint Engine Settings', 'sprint-engine' ), __( 'Settings', 'sprint-engine' ), 'manage_options', 'sprint-engine-settings', array( $this, 'render' ) );
	}

	/** Register the single option with the standard Settings API. */
	public function register() {
		register_setting(
			'sprint_engine_branding',
			RunnerBranding::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( RunnerBranding::class, 'sanitize' ),
				'show_in_rest'      => false,
			)
		);
	}

	/**
	 * Load media/colour picker and preview assets only on our settings page.
	 *
	 * @param string $hook Current admin page.
	 */
	public function enqueue( $hook ) {
		if ( ! $this->hook || $this->hook !== $hook || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		wp_enqueue_media();
		wp_enqueue_style( 'wp-color-picker' );
		$base = dirname( __DIR__, 2 ) . '/sprint-engine.php';
		wp_enqueue_style( 'sprint-engine-branding-runner', plugins_url( 'assets/css/runner.css', $base ), array(), Assets::version( 'assets/css/runner.css' ) );
		wp_enqueue_style( 'sprint-engine-runner-settings', plugins_url( 'assets/css/runner-settings.css', $base ), array( 'sprint-engine-branding-runner' ), Assets::version( 'assets/css/runner-settings.css' ) );
		wp_add_inline_style( 'sprint-engine-runner-settings', '#se-branding-preview{' . RunnerBranding::css() . '}' );
		wp_enqueue_script( 'sprint-engine-runner-settings', plugins_url( 'assets/js/runner-settings.js', $base ), array( 'wp-color-picker', 'media-views' ), Assets::version( 'assets/js/runner-settings.js' ), true );
		wp_localize_script(
			'sprint-engine-runner-settings',
			'sprintEngineBranding',
			array(
				'settings' => RunnerBranding::get(),
				'radii'    => RunnerBranding::RADII,
				'choose'   => __( 'Choose logo', 'sprint-engine' ),
				'use'      => __( 'Use logo', 'sprint-engine' ),
				'alt'      => get_bloginfo( 'name' ),
			)
		);
	}

	/** Delete only the branding option after capability and nonce verification. */
	public function reset() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You cannot manage these settings.', 'sprint-engine' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'sprint_engine_reset_branding' );
		delete_option( RunnerBranding::OPTION );
		wp_safe_redirect( admin_url( 'edit.php?post_type=sprint_engine_sprint&page=sprint-engine-settings&settings-updated=true' ) );
		exit;
	}

	/** Render accessible native fields and an illustrative, inert preview. */
	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You cannot manage these settings.', 'sprint-engine' ), '', array( 'response' => 403 ) );
		}
		$config  = RunnerBranding::get();
		$colors  = array(
			'primary'      => __( 'Primary colour', 'sprint-engine' ),
			'primary_text' => __( 'Primary button text colour', 'sprint-engine' ),
			'background'   => __( 'Runner background', 'sprint-engine' ),
			'surface'      => __( 'Surface colour', 'sprint-engine' ),
			'text'         => __( 'Text colour', 'sprint-engine' ),
			'muted'        => __( 'Muted text colour', 'sprint-engine' ),
		);
		$corners = array(
			'square'  => __( 'Square', 'sprint-engine' ),
			'subtle'  => __( 'Subtle', 'sprint-engine' ),
			'soft'    => __( 'Soft', 'sprint-engine' ),
			'rounded' => __( 'Rounded', 'sprint-engine' ),
		);
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Sprint Engine Settings', 'sprint-engine' ); ?></h1>
			<?php settings_errors(); ?>
			<section aria-labelledby="se-dashboard-settings-title">
				<h2 id="se-dashboard-settings-title"><?php esc_html_e( 'Member Dashboard', 'sprint-engine' ); ?></h2>
				<p><?php esc_html_e( 'Your member Dashboard is available at:', 'sprint-engine' ); ?> <a href="<?php echo esc_url( \ThePath\SprintEngine\Dashboard\Routes::url() ); ?>"><?php echo esc_html( \ThePath\SprintEngine\Dashboard\Routes::url() ); ?></a></p>
				<p><a class="button" href="<?php echo esc_url( \ThePath\SprintEngine\Dashboard\Routes::url() ); ?>"><?php esc_html_e( 'View Dashboard', 'sprint-engine' ); ?></a></p>
			</section>
			<h2><?php esc_html_e( 'Runner Branding', 'sprint-engine' ); ?></h2>
			<p><?php esc_html_e( 'These settings apply to every Sprint on this site. Preview changes below, then save to apply them.', 'sprint-engine' ); ?></p>
			<div class="se-branding-layout">
				<div>
				<form action="options.php" method="post" id="se-branding-form">
					<?php settings_fields( 'sprint_engine_branding' ); ?>
					<table class="form-table" role="presentation">
						<tr><th scope="row"><?php esc_html_e( 'Organisation logo', 'sprint-engine' ); ?></th><td>
							<input type="hidden" id="se-logo-id" name="<?php echo esc_attr( RunnerBranding::OPTION ); ?>[logo_id]" value="<?php echo esc_attr( $config['logo_id'] ); ?>">
							<div id="se-logo-thumbnail"><?php echo RunnerBranding::logo(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core attachment helper escapes image markup. ?></div>
							<button type="button" class="button" id="se-choose-logo"><?php esc_html_e( 'Choose logo', 'sprint-engine' ); ?></button>
							<button type="button" class="button" id="se-remove-logo"><?php esc_html_e( 'Remove logo', 'sprint-engine' ); ?></button>
						</td></tr>
						<?php foreach ( $colors as $key => $label ) : ?>
						<tr><th scope="row"><label for="se-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th><td><input type="text" class="se-color" id="se-<?php echo esc_attr( $key ); ?>" data-color="<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( RunnerBranding::OPTION ); ?>[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $config[ $key ] ); ?>" maxlength="7" placeholder="<?php echo esc_attr( 'primary_text' === $key ? __( 'Automatic', 'sprint-engine' ) : '' ); ?>" aria-describedby="se-color-help">
							<?php
							if ( 'primary_text' === $key ) :
								?>
							<p class="description"><?php esc_html_e( 'Automatic when empty. Use Clear to restore automatic contrast.', 'sprint-engine' ); ?></p><?php endif; ?></td></tr>
						<?php endforeach; ?>
						<tr><th scope="row"><label for="se-corner-style"><?php esc_html_e( 'Corner style', 'sprint-engine' ); ?></label></th><td><select id="se-corner-style" name="<?php echo esc_attr( RunnerBranding::OPTION ); ?>[corner_style]">
						<?php foreach ( $corners as $key => $label ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $config['corner_style'], $key ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
						</select></td></tr>
					</table>
					<p id="se-color-help" class="description"><?php esc_html_e( 'Use six-digit hex colours, for example #205b48. Primary button text: Automatic when empty. Clear a custom value to restore automatic contrast.', 'sprint-engine' ); ?></p>
					<?php submit_button(); ?>
				</form>
				<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
					<input type="hidden" name="action" value="sprint_engine_reset_branding">
					<?php wp_nonce_field( 'sprint_engine_reset_branding' ); ?>
					<?php submit_button( __( 'Reset to defaults', 'sprint-engine' ), 'secondary', 'submit', false ); ?>
				</form>
				</div>
				<section aria-labelledby="se-preview-title">
					<h2 id="se-preview-title"><?php esc_html_e( 'Live preview', 'sprint-engine' ); ?></h2>
					<p><?php esc_html_e( 'Illustration only. These controls do not change Sprint progress.', 'sprint-engine' ); ?></p>
					<p id="se-contrast-warning" role="status" hidden><?php esc_html_e( 'Some text may be difficult to read against your chosen backgrounds. Consider increasing contrast.', 'sprint-engine' ); ?></p>
					<div class="se-runner" id="se-branding-preview">
						<div class="se-runner__utility"><span id="se-preview-logo"><?php echo RunnerBranding::logo(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core attachment helper. ?></span><button type="button" class="se-runner__button se-runner__button--secondary"><?php esc_html_e( 'Save & Exit', 'sprint-engine' ); ?></button></div>
						<h3><?php esc_html_e( 'Example Sprint', 'sprint-engine' ); ?></h3>
						<div class="se-runner__progress"><p id="se-preview-progress"><?php esc_html_e( '1 of 4 complete — 25%', 'sprint-engine' ); ?></p><progress value="25" max="100" aria-labelledby="se-preview-progress">25%</progress></div>
						<div class="se-runner__main">
							<p class="se-runner__metadata"><?php esc_html_e( 'Step 2 of 4 · About 5 minutes', 'sprint-engine' ); ?></p>
							<p class="se-runner__label"><?php esc_html_e( 'Put it into practice', 'sprint-engine' ); ?></p>
							<h3><?php esc_html_e( 'Take your next step', 'sprint-engine' ); ?></h3>
							<p><?php esc_html_e( 'Focus on one useful action today.', 'sprint-engine' ); ?></p>
							<p class="se-runner__task"><?php esc_html_e( 'Try this before completing the Step.', 'sprint-engine' ); ?></p>
							<button type="button" class="se-runner__button se-runner__button--primary"><?php esc_html_e( 'Complete & Continue', 'sprint-engine' ); ?></button>
						</div>
					</div>
				</section>
			</div>
		</div>
		<?php
	}
}
