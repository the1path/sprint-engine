<?php
/**
 * Generic participant handoffs inside the existing branded Runner shell.
 *
 * @package SprintEngine
 */

defined( 'ABSPATH' ) || exit;
$sprint_engine_guest = $sprint_engine_context['guest'];
?>
<?php if ( ! empty( $sprint_engine_guest['notice'] ) ) : ?>
	<p class="se-runner__error" role="alert"><?php echo esc_html( $sprint_engine_guest['notice'] ); ?></p>
<?php endif; ?>
<?php if ( isset( $sprint_engine_guest['form'] ) ) : ?>
	<p class="se-runner__intro"><?php esc_html_e( 'A few details, then you can take it one Step at a time.', 'sprint-engine' ); ?></p>
	<?php require __DIR__ . '/registration-form.php'; ?>
	<p><?php esc_html_e( 'Already have an account?', 'sprint-engine' ); ?> <a href="<?php echo esc_url( $sprint_engine_guest['login_url'] ); ?>"><?php esc_html_e( 'Sign in', 'sprint-engine' ); ?></a></p>
<?php else : ?>
	<p class="se-runner__intro"><?php echo esc_html( 'open' === $sprint_engine_guest['kind'] ? __( 'Welcome back. Continue with your account.', 'sprint-engine' ) : __( 'Sign in to check your access to this Sprint.', 'sprint-engine' ) ); ?></p>
	<p><a class="se-runner__button se-runner__button--primary" href="<?php echo esc_url( $sprint_engine_guest['login_url'] ); ?>"><?php esc_html_e( 'Sign in', 'sprint-engine' ); ?></a></p>
	<?php if ( '' !== $sprint_engine_guest['signup_url'] ) : ?>
	<p><?php esc_html_e( 'New here? Create an account, then return to this Sprint.', 'sprint-engine' ); ?></p>
	<p><a class="se-runner__button se-runner__button--secondary" href="<?php echo esc_url( $sprint_engine_guest['signup_url'] ); ?>"><?php esc_html_e( 'Create account', 'sprint-engine' ); ?></a></p>
<?php elseif ( $sprint_engine_guest['invitation'] ) : ?>
	<p><?php esc_html_e( 'Accounts are by invitation. Contact the site administrator if you need an account.', 'sprint-engine' ); ?></p>
<?php else : ?>
	<p><?php esc_html_e( 'Need an account or help with access? Contact the site administrator.', 'sprint-engine' ); ?></p>
<?php endif; ?>
<?php endif; ?>
<p><a href="<?php echo esc_url( $sprint_engine_guest['recovery_url'] ); ?>"><?php esc_html_e( 'Forgotten your password?', 'sprint-engine' ); ?></a></p>
<?php if ( '' !== $sprint_engine_guest['original_url'] ) : ?>
	<p class="se-runner__intro"><?php esc_html_e( 'After signing in or creating an account, use your original Sprint link if you are not returned automatically.', 'sprint-engine' ); ?> <a href="<?php echo esc_url( $sprint_engine_guest['original_url'] ); ?>"><?php esc_html_e( 'Return to this Sprint', 'sprint-engine' ); ?></a></p>
<?php endif; ?>
<?php if ( '' !== $sprint_engine_guest['privacy_url'] ) : ?>
	<p><a href="<?php echo esc_url( $sprint_engine_guest['privacy_url'] ); ?>"><?php esc_html_e( 'Privacy policy', 'sprint-engine' ); ?></a></p>
<?php endif; ?>
