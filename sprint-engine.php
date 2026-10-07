<?php
/**
 * Plugin Name: Sprint Engine
 * Description: Create guided, step-by-step Sprints with saved progress and a distraction-free Runner.
 * Version: 0.2.0
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Author: ThePath
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: sprint-engine
 *
 * @package SprintEngine
 */

defined( 'ABSPATH' ) || exit;

define( 'SPRINT_ENGINE_VERSION', '0.2.0' );
define( 'SPRINT_ENGINE_SCHEMA_VERSION', '2' );
define( 'SPRINT_ENGINE_MIN_PHP', '8.0' );
define( 'SPRINT_ENGINE_MIN_WP', '6.0' );

spl_autoload_register(
	static function ( $class_name ) {
		$prefix = 'ThePath\\SprintEngine\\';
		if ( 0 !== strpos( $class_name, $prefix ) ) {
			return;
		}
		$relative = substr( $class_name, strlen( $prefix ) );
		if ( ! preg_match( '/^[A-Za-z0-9_\\\\]+$/', $relative ) ) {
			return;
		}
		$file = __DIR__ . '/src/' . str_replace( '\\', '/', $relative ) . '.php';
		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
);

register_activation_hook( __FILE__, array( 'ThePath\\SprintEngine\\Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'ThePath\\SprintEngine\\Plugin', 'deactivate' ) );
( new ThePath\SprintEngine\Plugin() )->register_hooks();
