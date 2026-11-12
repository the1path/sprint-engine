<?php
/**
 * Font stacks and locally loadable font faces for Runner branding.
 *
 * @package SprintEngine
 */

namespace ThePath\SprintEngine\Settings;

defined( 'ABSPATH' ) || exit;

/** Resolves the same typography for the application and settings preview. */
final class RunnerTypography {
	const SYSTEM = 'system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif';

	const MANUAL = array(
		'poppins'   => 'Poppins',
		'inter'     => 'Inter',
		'roboto'    => 'Roboto',
		'open-sans' => 'Open Sans',
		'lato'      => 'Lato',
	);

	private const VARIABLE_WEIGHTS = array(
		'inter'     => '100 900',
		'roboto'    => '100 900',
		'open-sans' => '300 800',
	);

	private const WEBSITE_ALIAS = '"Sprint Engine Website"';
	private const GENERIC       = array( 'serif', 'sans-serif', 'monospace', 'cursive', 'fantasy', 'system-ui', 'ui-serif', 'ui-sans-serif', 'ui-monospace', 'ui-rounded' );
	private const SYSTEM_NAMES  = array(
		'-apple-system'      => '-apple-system',
		'blinkmacsystemfont' => 'BlinkMacSystemFont',
		'segoe ui'           => '"Segoe UI"',
		'arial'              => 'Arial',
		'courier new'        => '"Courier New"',
		'georgia'            => 'Georgia',
		'helvetica'          => 'Helvetica',
		'helvetica neue'     => '"Helvetica Neue"',
		'tahoma'             => 'Tahoma',
		'times new roman'    => '"Times New Roman"',
		'trebuchet ms'       => '"Trebuchet MS"',
		'verdana'            => 'Verdana',
	);

	/**
	 * Resolve a validated choice to a CSS stack and any required font faces.
	 *
	 * @param string $choice Saved branding choice.
	 * @return array{family: string, faces: string}
	 */
	public static function resolve( string $choice ): array {
		if ( 'website' === $choice ) {
			return self::website();
		}
		if ( ! isset( self::MANUAL[ $choice ] ) ) {
			return array(
				'family' => self::SYSTEM,
				'faces'  => '',
			);
		}

		$family  = '"Sprint Engine ' . self::MANUAL[ $choice ] . '"';
		$weights = isset( self::VARIABLE_WEIGHTS[ $choice ] ) ? array( 'wght' => self::VARIABLE_WEIGHTS[ $choice ] ) : array(
			400 => '400',
			700 => '700',
		);
		$faces   = '';
		foreach ( $weights as $file_weight => $weight ) {
			foreach ( array( 'normal', 'italic' ) as $style ) {
				$file   = "assets/fonts/{$choice}-latin-{$file_weight}-{$style}.woff2";
				$url    = plugins_url( $file, dirname( __DIR__, 2 ) . '/sprint-engine.php' );
				$faces .= self::face( $family, array( $url ), $weight, $style );
			}
		}
		return array(
			'family' => $family . ', ' . self::SYSTEM,
			'faces'  => $faces,
		);
	}

	/**
	 * Resolve the declared body family in both WordPress preset syntaxes.
	 *
	 * @return array{family: string, faces: string}
	 */
	private static function website(): array {
		$fallback = array(
			'family' => self::SYSTEM,
			'faces'  => '',
		);
		$declared = wp_get_global_styles( array( 'typography', 'fontFamily' ) );
		if ( ! is_string( $declared ) ) {
			return $fallback;
		}

		$families = wp_get_global_settings( array( 'typography', 'fontFamilies' ) );
		$presets  = array();
		foreach ( array( 'custom', 'theme', 'default' ) as $origin ) {
			foreach ( (array) ( $families[ $origin ] ?? array() ) as $preset ) {
				if ( isset( $preset['slug'], $preset['fontFamily'] ) && is_string( $preset['slug'] ) && is_string( $preset['fontFamily'] ) ) {
					$presets[ $preset['slug'] ] ??= $preset;
				}
			}
		}
		$declared = trim( $declared );
		if ( preg_match( '/^var:preset\|font-family\|([a-z0-9_-]+)$|^var\(--wp--preset--font-family--([a-z0-9_-]+)\)$/D', $declared, $match ) ) {
			$declared = $presets[ end( $match ) ]['fontFamily'] ?? '';
		}

		$names = array_map( array( self::class, 'family_name' ), explode( ',', $declared ) );
		if ( in_array( '', $names, true ) ) {
			return $fallback;
		}
		$primary   = $names[0];
		$has_faces = false;
		foreach ( $presets as $preset ) {
			if ( empty( $preset['fontFace'] ) || self::family_name( explode( ',', $preset['fontFamily'] )[0] ) !== $primary ) {
				continue;
			}
			$has_faces = true;
			$faces     = self::website_faces( (array) $preset['fontFace'] );
			if ( '' !== $faces ) {
				return array(
					'family' => self::WEBSITE_ALIAS . ', ' . self::SYSTEM,
					'faces'  => $faces,
				);
			}
		}
		// A matching web font can shadow a system family with the same name.
		if ( $has_faces ) {
			return $fallback;
		}

		$stack = array();
		foreach ( $names as $name ) {
			if ( in_array( $name, self::GENERIC, true ) ) {
				$stack[] = $name;
			} elseif ( isset( self::SYSTEM_NAMES[ $name ] ) ) {
				$stack[] = self::SYSTEM_NAMES[ $name ];
			} elseif ( $primary === $name ) {
				return $fallback;
			}
		}
		return array(
			'family' => implode( ', ', array_unique( array_merge( $stack, explode( ', ', self::SYSTEM ) ) ) ),
			'faces'  => '',
		);
	}

	/**
	 * Accept ordinary family names only, with optional matching quotes.
	 *
	 * @param string $name CSS family token.
	 * @return string
	 */
	private static function family_name( string $name ): string {
		$name = trim( $name );
		if ( ! preg_match( '/^(["\']?)[a-z0-9 _-]+\1$/iD', $name ) ) {
			return '';
		}
		return strtolower( trim( $name, "\"' " ) );
	}

	/**
	 * Register only the selected website family's supported local font faces.
	 *
	 * @param array $definitions WordPress font-face definitions.
	 * @return string
	 */
	private static function website_faces( array $definitions ): string {
		$css = '';
		foreach ( $definitions as $definition ) {
			$style  = $definition['fontStyle'] ?? 'normal';
			$weight = $definition['fontWeight'] ?? '400';
			$weight = is_int( $weight ) || is_string( $weight ) ? (string) $weight : '';
			$weight = array(
				'normal' => '400',
				'bold'   => '700',
			)[ $weight ] ?? $weight;
			if ( ! in_array( $style, array( 'normal', 'italic', 'oblique' ), true ) ||
				! preg_match( '/^(?:[1-9][0-9]{0,2}|1000)(?: (?:[1-9][0-9]{0,2}|1000))?$/D', $weight ) ) {
				continue;
			}
			$urls = array_filter( array_map( array( self::class, 'website_source' ), (array) ( $definition['src'] ?? array() ) ) );
			$css .= self::face( self::WEBSITE_ALIAS, $urls, $weight, $style );
		}
		return $css;
	}

	/**
	 * Resolve theme files and same-origin font URLs, including Font Library URLs.
	 *
	 * @param mixed $source Registered font source.
	 * @return string
	 */
	private static function website_source( $source ): string {
		if ( ! is_string( $source ) ) {
			return '';
		}
		if ( str_starts_with( $source, 'file:./' ) ) {
			$file = substr( $source, 7 );
			if ( ! preg_match( '~^[a-z0-9_./-]+$~iD', $file ) || preg_match( '~(?:^|/)\.\.(?:/|$)~', $file ) || ! is_file( get_theme_file_path( $file ) ) ) {
				return '';
			}
			$source = get_theme_file_uri( $file );
		}
		$url  = wp_parse_url( $source );
		$home = wp_parse_url( home_url() );
		if ( ! isset( $url['scheme'], $url['host'], $url['path'], $home['scheme'], $home['host'] ) ||
			isset( $url['user'] ) || isset( $url['pass'] ) ||
			0 !== strcasecmp( $url['scheme'], $home['scheme'] ) || 0 !== strcasecmp( $url['host'], $home['host'] ) ||
			( $url['port'] ?? null ) !== ( $home['port'] ?? null ) ||
			! preg_match( '/\.(?:woff2?|ttf|otf)$/iD', $url['path'] ) ) {
			return '';
		}
		return $source;
	}

	/**
	 * Build a face from a controlled alias, descriptors and URL list.
	 *
	 * @param string $family CSS family alias.
	 * @param array  $urls Font source URLs.
	 * @param string $weight CSS weight or weight range.
	 * @param string $style CSS font style.
	 * @return string
	 */
	private static function face( string $family, array $urls, string $weight, string $style ): string {
		$sources = array();
		foreach ( $urls as $url ) {
			$url = esc_url_raw( $url, array( 'http', 'https' ) );
			if ( '' !== $url ) {
				$sources[] = 'url("' . str_replace( array( '"', '\\', '<', '>' ), array( '%22', '%5C', '%3C', '%3E' ), $url ) . '")';
			}
		}
		return $sources ? '@font-face{font-family:' . $family . ';src:' . implode( ',', $sources ) . ';font-weight:' . $weight . ';font-style:' . $style . ';font-display:swap;}' : '';
	}
}
