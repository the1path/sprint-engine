<?php
/**
 * Native settings and scoped administrator setup guidance.
 *
 * @package SprintEngine
 */

namespace ThePath\SprintEngine\Settings;

defined( 'ABSPATH' ) || exit;

/** Participant configuration is independent of branding and tutorial decisions. */
final class ParticipantAccountsAdmin {

	/** Register only Settings API and administrator presentation. */
	public function register_hooks() {
		add_action( 'admin_init', array( $this, 'register' ) );
		add_action( 'admin_notices', array( $this, 'notice' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Progressive enhancement only; native disclosures remain usable without JS.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue( $hook ) {
		if ( current_user_can( 'manage_options' ) && str_ends_with( $hook, '_page_sprint-engine-settings' ) ) {
			$asset = 'assets/js/participant-settings.js';
			wp_enqueue_script( 'sprint-engine-participant-settings', plugins_url( $asset, dirname( __DIR__, 2 ) . '/sprint-engine.php' ), array(), \ThePath\SprintEngine\Assets::version( $asset ), true );
		}
	}

	/** Seed only safe unset storage, with no activation or upgrade policy change. */
	public function register() {
		global $pagenow;
		// Route inspection only; native options.php verifies capability and nonce on POST.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reject non-POST requests before any save, including requests with a valid nonce.
		$group = isset( $_REQUEST['option_page'] ) && is_string( $_REQUEST['option_page'] ) ? sanitize_key( wp_unslash( $_REQUEST['option_page'] ) ) : '';
		if ( 'options.php' === $pagenow && 'sprint_engine_participants' === $group && 'POST' !== sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ?? '' ) ) ) {
			wp_die( esc_html__( 'Use the participant settings form to save your choice.', 'sprint-engine' ), '', array( 'response' => 403 ) );
		}
		if ( current_user_can( 'manage_options' ) ) {
			add_option( ParticipantAccounts::OPTION, ParticipantAccounts::defaults(), '', false );
		}
		register_setting(
			'sprint_engine_participants',
			ParticipantAccounts::OPTION,
			array(
				'type'              => 'array',
				'show_in_rest'      => false,
				'sanitize_callback' => array( ParticipantAccounts::class, 'sanitize' ),
			)
		);
	}

	/** Persistent, non-dismissible and restricted to Core admin and WP Dashboard. */
	public function notice() {
		$screen = get_current_screen();
		if ( ! current_user_can( 'manage_options' ) || ! $screen || ( 'dashboard' !== $screen->id && ! in_array( $screen->post_type, array( 'sprint_engine_sprint', 'sprint_engine_step' ), true ) && ! str_contains( $screen->id, '_page_sprint-engine-' ) ) ) {
			return;
		}
		$message = ParticipantAccounts::status();
		if ( '' !== $message ) {
			echo '<div class="notice notice-warning"><p><strong>' . esc_html__( 'Participant Accounts:', 'sprint-engine' ) . '</strong> ' . esc_html( $message ) . ' <a href="' . esc_url( admin_url( 'edit.php?post_type=sprint_engine_sprint&page=sprint-engine-settings#sprint-engine-participants' ) ) . '">' . esc_html__( 'Review participant setup', 'sprint-engine' ) . '</a></p></div>';
		}
	}

	/** Concise mode-specific settings, with native disclosures as the no-JS fallback. */
	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$config = ParticipantAccounts::get();
		$modes  = array(
			'built_in'   => __( 'Sprint Engine registration (recommended)', 'sprint-engine' ),
			'external'   => __( 'Use an existing registration system', 'sprint-engine' ),
			'invitation' => __( 'Invitation only — no public registration', 'sprint-engine' ),
		);
		?>
		<section id="sprint-engine-participants" aria-labelledby="sprint-engine-participants-title">
			<h2 id="sprint-engine-participants-title"><?php esc_html_e( 'Participant Accounts', 'sprint-engine' ); ?></h2>
			<p><?php esc_html_e( 'Choose how new participants get an account. Existing accounts and sign-in stay available.', 'sprint-engine' ); ?></p>
			<form action="options.php" method="post" id="sprint-engine-participants-form">
				<?php settings_fields( 'sprint_engine_participants' ); ?>
				<fieldset><legend class="screen-reader-text"><?php esc_html_e( 'Participant account mode', 'sprint-engine' ); ?></legend>
				<?php foreach ( $modes as $mode => $label ) : ?>
					<p><label><input type="radio" name="<?php echo esc_attr( ParticipantAccounts::OPTION ); ?>[mode]" value="<?php echo esc_attr( $mode ); ?>" <?php checked( $config['mode'], $mode ); ?> required> <?php echo esc_html( $label ); ?></label></p>
				<?php endforeach; ?>
				</fieldset>
				<noscript><p><?php esc_html_e( 'Choose a mode and open its settings below before saving.', 'sprint-engine' ); ?></p></noscript>
				<details data-participant-mode="built_in" <?php echo 'built_in' === $config['mode'] ? 'open' : ''; ?>>
					<summary><?php esc_html_e( 'Sprint Engine registration setup', 'sprint-engine' ); ?></summary>
					<?php $issues = \ThePath\SprintEngine\Registration\Policy::prerequisites(); ?>
					<?php
					if ( $issues ) :
						?>
						<ul>
						<?php
						foreach ( $issues as $issue ) :
							?>
						<li><?php echo esc_html( $issue ); ?></li><?php endforeach; ?></ul>
						<?php
					else :
						?>
						<p><?php esc_html_e( 'WordPress registration, Subscriber, HTTPS and secure storage checks pass.', 'sprint-engine' ); ?></p><?php endif; ?>
					<p><a href="<?php echo esc_url( admin_url( 'options-general.php' ) ); ?>"><?php esc_html_e( 'Review Settings → General', 'sprint-engine' ); ?></a></p>
					<fieldset class="notice notice-warning inline">
						<legend><strong><?php esc_html_e( 'Confirm public account creation', 'sprint-engine' ); ?></strong></legend>
						<input type="hidden" name="<?php echo esc_attr( ParticipantAccounts::OPTION ); ?>[confirmation]" value="0">
						<p><label><input type="checkbox" aria-describedby="sprint-engine-confirmation-help" name="<?php echo esc_attr( ParticipantAccounts::OPTION ); ?>[confirmation]" value="1" <?php checked( \ThePath\SprintEngine\Registration\Policy::confirmed() ); ?>> <?php esc_html_e( 'I have checked the security guide and confirm this site is ready for public participant accounts.', 'sprint-engine' ); ?></label></p>
						<p id="sprint-engine-confirmation-help"><?php esc_html_e( 'Confirm HTTPS, protection for every signup route, compatible native-password policies, mail delivery, cache bypass and login protection. This is your confirmation, not an automatic security certification. If your tools require their own approval, verification or account form, use the existing-system mode.', 'sprint-engine' ); ?> <a href="https://github.com/the1path/sprint-engine/blob/main/docs/participant-registration-security.md" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Security setup guide', 'sprint-engine' ); ?></a></p>
					</fieldset>
					<input type="hidden" name="<?php echo esc_attr( ParticipantAccounts::OPTION ); ?>[disable_native]" value="0">
					<p><label><input type="checkbox" data-disable-native name="<?php echo esc_attr( ParticipantAccounts::OPTION ); ?>[disable_native]" value="1" <?php checked( false !== $config['disable_native'] ); ?>> <?php esc_html_e( 'Disable the standard WordPress registration form (recommended)', 'sprint-engine' ); ?></label></p>
					<p><?php esc_html_e( 'Closes only the standard WordPress form while confirmed built-in registration is operational. Other registration forms remain independent. Invalid setup or deactivation restores native WordPress policy; protect the reopened route. Providers using that native endpoint are also affected.', 'sprint-engine' ); ?></p>
					<p data-native-warning <?php echo false !== $config['disable_native'] ? 'hidden' : ''; ?>><strong><?php esc_html_e( 'Standard WordPress signup remains accessible under site policy and is outside Sprint Engine’s form-specific abuse controls.', 'sprint-engine' ); ?></strong></p>
				</details>
				<details data-participant-mode="external" <?php echo 'external' === $config['mode'] ? 'open' : ''; ?>>
					<summary><?php esc_html_e( 'Existing registration system destinations', 'sprint-engine' ); ?></summary>
					<table class="form-table" role="presentation">
					<?php
					foreach ( array(
						'registration_url' => __( 'Registration destination', 'sprint-engine' ),
						'login_url'        => __( 'Login destination (optional)', 'sprint-engine' ),
					) as $key => $label ) :
						?>
						<tr><th scope="row"><label for="sprint-engine-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th><td><input type="url" class="regular-text" id="sprint-engine-<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( ParticipantAccounts::OPTION ); ?>[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $config[ $key ] ); ?>" maxlength="2048"></td></tr>
					<?php endforeach; ?>
					</table>
					<p><?php esc_html_e( 'Use full same-site HTTPS URLs. Your provider owns registration and security. Configure its redirect_to return, then test signup, sign-in and recovery back to the original Sprint. Native login is used when login is empty or invalid; automatic return across email/devices is not guaranteed.', 'sprint-engine' ); ?></p>
				</details>
				<details data-participant-mode="invitation" <?php echo 'invitation' === $config['mode'] ? 'open' : ''; ?>>
					<summary><?php esc_html_e( 'Invitation-only access', 'sprint-engine' ); ?></summary>
					<p><?php esc_html_e( 'Participants sign in with an existing account or contact you for an invitation. This removes Sprint Engine’s signup offers; it does not close registration elsewhere on the site.', 'sprint-engine' ); ?></p>
				</details>
				<?php submit_button( __( 'Save participant settings', 'sprint-engine' ) ); ?>
			</form>
		</section>
		<?php
	}
}
