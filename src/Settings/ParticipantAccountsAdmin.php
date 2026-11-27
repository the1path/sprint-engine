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

	/** One no-JS Settings API form; all conditional guidance stays readable. */
	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$config = ParticipantAccounts::get();
		$modes  = array(
			'built_in'   => __( 'Sprint Engine registration (recommended; pending implementation)', 'sprint-engine' ),
			'external'   => __( 'Use an existing registration system', 'sprint-engine' ),
			'invitation' => __( 'Invitation only — no public registration', 'sprint-engine' ),
		);
		?>
		<section id="sprint-engine-participants" aria-labelledby="sprint-engine-participants-title">
			<h2 id="sprint-engine-participants-title"><?php esc_html_e( 'Participant Accounts', 'sprint-engine' ); ?></h2>
			<p><?php esc_html_e( 'Choose how new participants get an account. Existing participants keep their accounts and sign in as usual.', 'sprint-engine' ); ?></p>
			<?php if ( ParticipantAccounts::status() ) : ?>
				<p><strong><?php echo esc_html( ParticipantAccounts::status() ); ?></strong></p>
			<?php endif; ?>
			<form action="options.php" method="post" id="sprint-engine-participants-form">
				<?php settings_fields( 'sprint_engine_participants' ); ?>
				<fieldset>
					<legend class="screen-reader-text"><?php esc_html_e( 'Participant account mode', 'sprint-engine' ); ?></legend>
					<?php foreach ( $modes as $mode => $label ) : ?>
						<p><label><input type="radio" name="<?php echo esc_attr( ParticipantAccounts::OPTION ); ?>[mode]" value="<?php echo esc_attr( $mode ); ?>" <?php checked( $config['mode'], $mode ); ?> required> <?php echo esc_html( $label ); ?></label></p>
					<?php endforeach; ?>
				</fieldset>
				<p><?php esc_html_e( 'Invitation only removes Sprint Engine’s account creation offers. It does not close registration elsewhere on your website.', 'sprint-engine' ); ?></p>
				<h3><?php esc_html_e( 'Existing registration system', 'sprint-engine' ); ?></h3>
				<p><?php esc_html_e( 'Enter full same-site HTTPS URLs. A registration destination is required for this mode; login is optional. Published, passwordless WordPress pages and account paths with query actions are supported.', 'sprint-engine' ); ?></p>
				<table class="form-table" role="presentation">
					<?php
					foreach ( array(
						'registration_url' => __( 'Registration destination', 'sprint-engine' ),
						'login_url'        => __( 'Login destination (optional)', 'sprint-engine' ),
					) as $key => $label ) :
						?>
						<tr><th scope="row"><label for="sprint-engine-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th><td><input type="url" class="regular-text" id="sprint-engine-<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( ParticipantAccounts::OPTION ); ?>[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $config[ $key ] ); ?>" maxlength="2048" aria-describedby="sprint-engine-provider-help"></td></tr>
					<?php endforeach; ?>
				</table>
				<p id="sprint-engine-provider-help"><?php esc_html_e( 'Your provider owns registration, sign-in and security policies. Configure it to honour the redirect_to hint after authentication, then test signup, sign-in and recovery back to the original Sprint. Providers may ignore this hint; participants can return using their original Sprint link. No automatic return across email or devices is guaranteed.', 'sprint-engine' ); ?></p>
				<details <?php echo 'built_in' === $config['mode'] ? 'open' : ''; ?>>
				<summary><strong><?php esc_html_e( 'Sprint Engine registration preferences and security requirements (pending)', 'sprint-engine' ); ?></strong></summary>
				<p><strong><?php esc_html_e( 'Pending implementation — no built-in account form or native-registration protection is active in this version.', 'sprint-engine' ); ?></strong></p>
				<input type="hidden" name="<?php echo esc_attr( ParticipantAccounts::OPTION ); ?>[disable_native]" value="0">
				<p><label><input type="checkbox" name="<?php echo esc_attr( ParticipantAccounts::OPTION ); ?>[disable_native]" value="1" <?php checked( false !== $config['disable_native'] ); ?>> <?php esc_html_e( 'Disable the standard WordPress registration form (recommended)', 'sprint-engine' ); ?></label></p>
				<p><?php esc_html_e( 'This preference takes effect only after built-in registration is operational and its saved policy is valid. It is dormant now. Other modes, invalid setup or deactivation leave native registration available under WordPress policy. External forms using the native registration endpoint would also be affected when it is closed.', 'sprint-engine' ); ?></p>
				<p><?php esc_html_e( 'Built-in signup will require single-site WordPress registration permission, the standard Subscriber default role, a supported native-password security stack and environment acceptance. Sprint Engine never changes these global settings or creates a role.', 'sprint-engine' ); ?></p>
				<ul>
					<?php foreach ( ParticipantAccounts::native_policy_problems() as $problem ) : ?>
						<li><?php echo esc_html( $problem ); ?></li>
					<?php endforeach; ?>
				</ul>
				<p><a href="<?php echo esc_url( admin_url( 'options-general.php' ) ); ?>"><?php esc_html_e( 'Review Settings → General', 'sprint-engine' ); ?></a></p>
				<p><?php esc_html_e( 'Before enabling built-in signup, protect all remaining public registration endpoints, including native and independent provider forms, and confirm HTTPS, login abuse protection, cache bypass, mail delivery and security-provider policies. Disabling one form does not close every account creation route or eliminate native account races. UI-dependent approval, verification, CAPTCHA, passwordless or SSO policies should use their existing provider. Plugin-name detection cannot establish compatibility.', 'sprint-engine' ); ?></p>
				<p><?php esc_html_e( 'The planned form will require first name, last name, email and a chosen password, with a generated hidden username. Names will use standard WordPress profile fields and are not marketing consent. Existing-account email sign-in and password recovery remain native/provider journeys; the initial native signup notification is administrator-only. No email verification or delayed checkpoint is planned.', 'sprint-engine' ); ?></p>
				</details>
				<?php if ( false === $config['disable_native'] ) : ?>
					<p class="notice notice-warning inline"><strong><?php esc_html_e( 'Native registration remains accessible and is not protected by Sprint Engine’s form-specific abuse controls.', 'sprint-engine' ); ?></strong></p>
				<?php endif; ?>
				<?php submit_button( __( 'Save participant settings', 'sprint-engine' ) ); ?>
			</form>
		</section>
		<?php
	}
}
