<?php
/**
 * Native browser form with optional password visibility; no password reflection.
 *
 * @package SprintEngine
 */

defined( 'ABSPATH' ) || exit;
$sprint_engine_form   = $sprint_engine_guest['form'];
$sprint_engine_fields = array(
	'first_name' => array( __( 'First name', 'sprint-engine' ), 'text', 'given-name' ),
	'last_name'  => array( __( 'Last name', 'sprint-engine' ), 'text', 'family-name' ),
	'email'      => array( __( 'Email', 'sprint-engine' ), 'email', 'email' ),
	'password'   => array( __( 'Password', 'sprint-engine' ), 'password', 'new-password' ),
);
?>
<?php if ( $sprint_engine_form['errors'] ) : ?>
	<div class="se-runner__error se-runner__registration-errors" tabindex="-1" autofocus aria-label="<?php esc_attr_e( 'Check your details', 'sprint-engine' ); ?>">
		<ul><?php foreach ( $sprint_engine_form['errors'] as $sprint_engine_field => $sprint_engine_error ) : ?>
			<li><a href="#se-registration-<?php echo esc_attr( $sprint_engine_field ); ?>"><?php echo esc_html( $sprint_engine_error ); ?></a></li>
		<?php endforeach; ?></ul>
	</div>
<?php endif; ?>
<form class="se-runner__registration" method="post" action="<?php echo esc_url( $sprint_engine_form['url'] ); ?>">
	<input type="hidden" name="sprint_engine_action" value="sprint_engine_register">
	<input type="hidden" name="sprint_engine_registration_token" value="<?php echo esc_attr( $sprint_engine_form['token'] ); ?>">
	<?php foreach ( $sprint_engine_fields as $sprint_engine_field => $sprint_engine_properties ) : ?>
		<?php
		if ( 'first_name' === $sprint_engine_field ) :
			?>
			<div class="se-runner__registration-names"><?php endif; ?>
		<p>
			<label for="se-registration-<?php echo esc_attr( $sprint_engine_field ); ?>"><?php echo esc_html( $sprint_engine_properties[0] ); ?></label>
			<?php
			if ( 'password' === $sprint_engine_field ) :
				?>
				<span class="se-runner__password"><?php endif; ?>
			<input id="se-registration-<?php echo esc_attr( $sprint_engine_field ); ?>" name="<?php echo esc_attr( $sprint_engine_field ); ?>" type="<?php echo esc_attr( $sprint_engine_properties[1] ); ?>" autocomplete="<?php echo esc_attr( $sprint_engine_properties[2] ); ?>" required
			<?php
			if ( 'password' !== $sprint_engine_field ) :
				?>
				value="<?php echo esc_attr( $sprint_engine_form['values'][ $sprint_engine_field ] ); ?>" maxlength="<?php echo 'email' === $sprint_engine_field ? '100' : '400'; ?>"<?php endif; ?>
			<?php
			if ( isset( $sprint_engine_form['errors'][ $sprint_engine_field ] ) ) :
				?>
				aria-invalid="true" aria-describedby="se-registration-<?php echo esc_attr( $sprint_engine_field ); ?>-error"
				<?php
elseif ( 'password' === $sprint_engine_field ) :
	?>
				aria-describedby="se-registration-password-help"<?php endif; ?>>
			<?php if ( 'password' === $sprint_engine_field ) : ?>
				<button type="button" class="se-runner__button se-runner__button--secondary se-runner__password-toggle" data-password-toggle data-show-label="<?php esc_attr_e( 'Show password', 'sprint-engine' ); ?>" data-hide-label="<?php esc_attr_e( 'Hide password', 'sprint-engine' ); ?>" aria-controls="se-registration-password" hidden><?php esc_html_e( 'Show password', 'sprint-engine' ); ?></button>
				</span>
			<?php endif; ?>
			<?php
			if ( 'password' === $sprint_engine_field ) :
				?>
				<small id="se-registration-password-help"><?php esc_html_e( 'Use at least 12 characters, without spaces at the beginning or end.', 'sprint-engine' ); ?></small><?php endif; ?>
			<?php
			if ( isset( $sprint_engine_form['errors'][ $sprint_engine_field ] ) ) :
				?>
				<span id="se-registration-<?php echo esc_attr( $sprint_engine_field ); ?>-error"><?php echo esc_html( $sprint_engine_form['errors'][ $sprint_engine_field ] ); ?></span><?php endif; ?>
		</p>
		<?php
		if ( 'last_name' === $sprint_engine_field ) :
			?>
			</div><?php endif; ?>
	<?php endforeach; ?>
	<button type="submit" class="se-runner__button se-runner__button--primary"><?php esc_html_e( 'Create account', 'sprint-engine' ); ?></button>
</form>
