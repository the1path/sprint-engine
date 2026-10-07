<?php
/**
 * Stable versions for plugin-owned runtime assets.
 *
 * @package SprintEngine
 */

namespace ThePath\SprintEngine;

defined( 'ABSPATH' ) || exit;

/** Fingerprint bytes, including deployments that preserve file timestamps. */
final class Assets {

	/**
	 * Build a cacheable version from a trusted plugin-relative asset path.
	 *
	 * @param string $asset Runtime asset path supplied by plugin code.
	 * @return string Plugin version with content fingerprint when readable.
	 */
	public static function version( $asset ) {
		$path = __DIR__ . '/../' . $asset;
		$hash = false;
		try {
			if ( is_file( $path ) && is_readable( $path ) ) {
				// A deployment can replace a file between the check and the read.
				$hash = @hash_file( 'sha256', $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Safe fallback on concurrent replacement or unreadable files.
			}
		} catch ( \Throwable $exception ) {
			$hash = false;
		}
		return SPRINT_ENGINE_VERSION . ( $hash ? '-' . $hash : '' );
	}
}
