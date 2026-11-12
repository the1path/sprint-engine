<?php
/**
 * Website links for standalone Sprint Engine templates.
 *
 * @package SprintEngine
 */

namespace ThePath\SprintEngine\Presentation;

use ThePath\SprintEngine\Assets;
use ThePath\SprintEngine\Settings\RunnerBranding;

defined( 'ABSPATH' ) || exit;

/** Optional presentation component; callers own placement and asset scoping. */
final class WebsiteNavigation {

	/** Enqueue the component stylesheet on a page that uses the links. */
	public static function enqueue_style(): void {
		$asset = 'assets/css/website-navigation.css';
		wp_enqueue_style( 'sprint-engine-website-navigation', plugins_url( $asset, dirname( __DIR__, 2 ) . '/sprint-engine.php' ), array(), Assets::version( $asset ) );
	}

	/** Print a homepage link containing the configured organisation image, if any. */
	public static function logo(): void {
		$logo = RunnerBranding::logo();
		if ( '' === $logo ) {
			return;
		}
		?>
		<a class="se-website-navigation__logo" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="<?php esc_attr_e( 'Go to website homepage', 'sprint-engine' ); ?>">
			<?php echo $logo; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core attachment helper escapes image markup. ?>
		</a>
		<?php
	}

	/** Print the website footer after the page's main content. */
	public static function footer(): void {
		?>
		<footer class="se-website-navigation">
			<a class="se-website-navigation__link" href="<?php echo esc_url( home_url( '/' ) ); ?>"><span aria-hidden="true">←</span> <?php esc_html_e( 'Back to website', 'sprint-engine' ); ?></a>
		</footer>
		<?php
	}
}
