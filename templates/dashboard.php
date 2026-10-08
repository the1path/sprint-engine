<?php
/**
 * Standalone My Sprints; context is resolved after authentication and access.
 *
 * @package SprintEngine
 */

defined( 'ABSPATH' ) || exit;

$sprint_engine_context = get_query_var( 'sprint_engine_dashboard_context', array() );
if ( ! isset( $sprint_engine_context['state'] ) ) {
	return;
}
$sprint_engine_sections = array(
	'in_progress' => __( 'In Progress', 'sprint-engine' ),
	'not_started' => __( 'Available', 'sprint-engine' ),
	'completed'   => __( 'Completed', 'sprint-engine' ),
);
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex, nofollow">
	<title><?php echo esc_html( wp_get_document_title() ); ?></title>
	<?php wp_head(); ?>
</head>
<body class="se-dashboard">
<?php wp_body_open(); ?>
<a class="se-dashboard__skip" href="#se-dashboard-main"><?php esc_html_e( 'Skip to My Sprints', 'sprint-engine' ); ?></a>
<div class="se-dashboard__shell">
	<header class="se-dashboard__header">
		<?php echo \ThePath\SprintEngine\Settings\RunnerBranding::logo(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core attachment helper escapes image markup. ?>
		<h1><?php esc_html_e( 'My Sprints', 'sprint-engine' ); ?></h1>
		<p><?php esc_html_e( 'Choose a Sprint to start, continue where you left off, or revisit something you have completed.', 'sprint-engine' ); ?></p>
		<?php do_action( 'sprint_engine/dashboard_header_actions', $sprint_engine_context ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- Intentional public extension seam; callbacks escape their own output. ?>
	</header>
	<main id="se-dashboard-main" tabindex="-1">
		<?php if ( 'error' === $sprint_engine_context['state'] ) : ?>
			<p class="se-dashboard__empty" role="alert"><?php echo esc_html( $sprint_engine_context['message'] ); ?></p>
		<?php elseif ( ! array_filter( $sprint_engine_context['sections'] ) ) : ?>
			<p class="se-dashboard__empty"><?php esc_html_e( 'No Sprints are available to you yet.', 'sprint-engine' ); ?></p>
		<?php else : ?>
			<?php foreach ( $sprint_engine_sections as $sprint_engine_state => $sprint_engine_label ) : ?>
				<?php
				if ( empty( $sprint_engine_context['sections'][ $sprint_engine_state ] ) ) {
					continue; }
				?>
				<section class="se-dashboard__section" aria-labelledby="se-dashboard-<?php echo esc_attr( $sprint_engine_state ); ?>">
					<h2 id="se-dashboard-<?php echo esc_attr( $sprint_engine_state ); ?>"><?php echo esc_html( $sprint_engine_label ); ?></h2>
					<div class="se-dashboard__grid">
						<?php foreach ( $sprint_engine_context['sections'][ $sprint_engine_state ] as $sprint_engine_item ) : ?>
							<article class="se-dashboard__card" aria-labelledby="se-dashboard-title-<?php echo esc_attr( $sprint_engine_item['sprint_id'] ); ?>">
								<?php echo wp_kses_post( $sprint_engine_item['featured_image'] ); ?>
								<div class="se-dashboard__card-content">
									<p class="se-dashboard__label"><?php echo esc_html( $sprint_engine_label ); ?></p>
									<h3 id="se-dashboard-title-<?php echo esc_attr( $sprint_engine_item['sprint_id'] ); ?>"><?php echo esc_html( $sprint_engine_item['title'] ); ?></h3>
									<?php if ( '' !== $sprint_engine_item['excerpt'] ) : ?>
										<p><?php echo esc_html( $sprint_engine_item['excerpt'] ); ?></p>
									<?php endif; ?>
									<?php if ( ! empty( $sprint_engine_item['duration'] ) ) : ?>
										<p class="se-dashboard__muted"><?php /* translators: %s: authored estimated duration. */ echo esc_html( sprintf( __( 'Estimated time: %s', 'sprint-engine' ), $sprint_engine_item['duration']['label'] ) ); ?></p>
									<?php endif; ?>
									<?php if ( 'in_progress' === $sprint_engine_state ) : ?>
										<p class="se-dashboard__muted" id="se-dashboard-progress-<?php echo esc_attr( $sprint_engine_item['sprint_id'] ); ?>"><?php /* translators: 1: completed Steps, 2: total Steps, 3: percentage. */ echo esc_html( sprintf( __( '%1$d of %2$d complete — %3$s%%', 'sprint-engine' ), $sprint_engine_item['completed_steps'], $sprint_engine_item['total_steps'], number_format_i18n( $sprint_engine_item['percentage'], 0 ) ) ); ?></p>
										<progress max="100" value="<?php echo esc_attr( $sprint_engine_item['percentage'] ); ?>" aria-labelledby="se-dashboard-progress-<?php echo esc_attr( $sprint_engine_item['sprint_id'] ); ?>"><?php echo esc_html( number_format_i18n( $sprint_engine_item['percentage'], 0 ) ); ?>%</progress>
									<?php elseif ( 'completed' === $sprint_engine_state ) : ?>
										<p class="se-dashboard__muted"><?php esc_html_e( 'All Steps complete — 100%', 'sprint-engine' ); ?></p>
									<?php endif; ?>
									<div class="se-dashboard__actions">
										<?php if ( 'completed' === $sprint_engine_state ) : ?>
											<button class="se-dashboard__button" type="button" data-sprint-engine-restart data-endpoint="<?php echo esc_url( rest_url( 'sprint-engine/v1/sprints/' . $sprint_engine_item['sprint_id'] . '/restart' ) ); ?>" data-runner-url="<?php echo esc_url( $sprint_engine_item['runner_url'] ); ?>" aria-describedby="se-dashboard-status-<?php echo esc_attr( $sprint_engine_item['sprint_id'] ); ?>"><?php esc_html_e( 'Restart Sprint', 'sprint-engine' ); ?></button>
											<a href="<?php echo esc_url( $sprint_engine_item['runner_url'] ); ?>"><?php esc_html_e( 'View Completed Sprint', 'sprint-engine' ); ?></a>
											<p id="se-dashboard-status-<?php echo esc_attr( $sprint_engine_item['sprint_id'] ); ?>" role="status" aria-live="polite" aria-atomic="true"></p>
											<noscript><p><?php esc_html_e( 'JavaScript is required to restart this Sprint.', 'sprint-engine' ); ?></p></noscript>
										<?php else : ?>
											<a class="se-dashboard__button" href="<?php echo esc_url( $sprint_engine_item['runner_url'] ); ?>"><?php echo esc_html( 'in_progress' === $sprint_engine_state ? __( 'Continue Sprint', 'sprint-engine' ) : __( 'Start Sprint', 'sprint-engine' ) ); ?></a>
										<?php endif; ?>
									</div>
								</div>
							</article>
						<?php endforeach; ?>
					</div>
				</section>
			<?php endforeach; ?>
		<?php endif; ?>
	</main>
</div>
<?php wp_footer(); ?>
</body>
</html>
