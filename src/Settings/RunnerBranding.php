<?php
/**
 * Site-wide Runner branding and safe CSS values.
 *
 * @package SprintEngine
 */

namespace ThePath\SprintEngine\Settings;

defined( 'ABSPATH' ) || exit;

/** Owns the complete, validated presentation configuration. */
final class RunnerBranding {
	const OPTION = 'sprint_engine_runner_branding';
	const RADII  = array(
		'square'  => '0',
		'subtle'  => '0.5rem',
		'soft'    => '1rem',
		'rounded' => '1.5rem',
	);

	/**
	 * Built-in SE-007 appearance.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'logo_id'      => 0,
			'primary'      => '#205b48',
			'primary_text' => '',
			'background'   => '#f3f6f5',
			'surface'      => '#ffffff',
			'text'         => '#203630',
			'muted'        => '#50645d',
			'corner_style' => 'soft',
		);
	}

	/**
	 * Complete valid settings, even with corrupt storage.
	 *
	 * @return array
	 */
	public static function get() {
		return self::normalize( get_option( self::OPTION, array() ), self::defaults() );
	}

	/**
	 * Settings API write boundary. Invalid fields retain saved/default values.
	 *
	 * @param mixed $input Submitted configuration.
	 * @return array
	 */
	public static function sanitize( $input ) {
		return self::normalize( $input, self::get(), true );
	}

	/**
	 * Authoritative allowlist used for reads and writes.
	 *
	 * @param mixed $input Candidate values.
	 * @param array $fallback Valid fallback values.
	 * @param bool  $report Report rejected settings at the write boundary.
	 * @return array
	 */
	private static function normalize( $input, $fallback, $report = false ) {
		if ( ! is_array( $input ) && $report ) {
			add_settings_error( self::OPTION, 'sprint_engine_branding_invalid', __( 'Branding settings must contain valid fields. Previous settings were kept.', 'sprint-engine' ) );
		}
		$input = is_array( $input ) ? $input : array();
		if ( isset( $input['primary_text'] ) && '' === $input['primary_text'] ) {
			$fallback['primary_text'] = '';
		}
		$labels = array(
			'primary'      => __( 'Primary colour', 'sprint-engine' ),
			'primary_text' => __( 'Primary button text colour', 'sprint-engine' ),
			'background'   => __( 'Background colour', 'sprint-engine' ),
			'surface'      => __( 'Surface colour', 'sprint-engine' ),
			'text'         => __( 'Text colour', 'sprint-engine' ),
			'muted'        => __( 'Muted text colour', 'sprint-engine' ),
		);
		foreach ( $labels as $key => $label ) {
			if ( isset( $input[ $key ] ) && is_string( $input[ $key ] ) && preg_match( '/^#[0-9a-fA-F]{6}$/D', $input[ $key ] ) ) {
				$fallback[ $key ] = strtolower( sanitize_hex_color( $input[ $key ] ) );
			} elseif ( $report && array_key_exists( $key, $input ) && ! ( 'primary_text' === $key && '' === $input[ $key ] ) ) {
				/* translators: %s: branding colour field label. */
				add_settings_error( self::OPTION, 'sprint_engine_branding_' . $key, sprintf( __( '%s must be a six-digit hex colour, for example #205b48. The previous value was kept.', 'sprint-engine' ), $label ) );
			}
		}
		if ( isset( $input['corner_style'] ) && is_string( $input['corner_style'] ) && isset( self::RADII[ $input['corner_style'] ] ) ) {
			$fallback['corner_style'] = $input['corner_style'];
		} elseif ( $report && array_key_exists( 'corner_style', $input ) ) {
			add_settings_error( self::OPTION, 'sprint_engine_branding_corner_style', __( 'Corner style must be Square, Subtle, Soft or Rounded. The previous style was kept.', 'sprint-engine' ) );
		}
		if ( array_key_exists( 'logo_id', $input ) ) {
			$raw = $input['logo_id'];
			$id  = ( is_int( $raw ) || ( is_string( $raw ) && preg_match( '/^[0-9]+$/D', $raw ) ) ) ? filter_var( $raw, FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 0 ) ) ) : false;
			if ( 0 === $id || ( $id && 'attachment' === get_post_type( $id ) && 'trash' !== get_post_status( $id ) && wp_attachment_is_image( $id ) ) ) {
				$fallback['logo_id'] = $id;
			} elseif ( $report ) {
				add_settings_error( self::OPTION, 'sprint_engine_branding_logo_id', __( 'Choose an existing image attachment for the logo, or use Remove Logo to clear it. The previous logo was kept.', 'sprint-engine' ) );
			}
		}
		return $fallback;
	}

	/**
	 * Choose black or white using WCAG relative luminance and maximum contrast.
	 *
	 * @param string $hex Valid six-digit hex.
	 * @return string
	 */
	public static function foreground( $hex ) {
		$rgb       = array_map(
			static function ( $channel ) {
				$value = hexdec( $channel ) / 255;
				return $value <= 0.04045 ? $value / 12.92 : pow( ( $value + 0.055 ) / 1.055, 2.4 );
			},
			str_split( substr( $hex, 1 ), 2 )
		);
		$luminance = 0.2126 * $rgb[0] + 0.7152 * $rgb[1] + 0.0722 * $rgb[2];
		return ( $luminance + 0.05 ) / 0.05 > 1.05 / ( $luminance + 0.05 ) ? '#000000' : '#ffffff';
	}

	/**
	 * Mix validated colours without modern browser colour functions.
	 *
	 * @param string $first First hex colour.
	 * @param string $second Second hex colour.
	 * @param float  $weight First colour weight.
	 * @return string
	 */
	private static function mix( $first, $second, $weight ) {
		$result = '#';
		foreach ( array( 1, 3, 5 ) as $offset ) {
			$result .= sprintf( '%02x', (int) round( hexdec( substr( $first, $offset, 2 ) ) * $weight + hexdec( substr( $second, $offset, 2 ) ) * ( 1 - $weight ) ) );
		}
		return $result;
	}

	/**
	 * Safe custom properties shared with the live preview.
	 *
	 * @return array
	 */
	public static function variables() {
		$config = self::get();
		$vars   = array();
		foreach ( array( 'primary', 'background', 'surface', 'text', 'muted' ) as $key ) {
			$vars[ '--se-' . $key ] = $config[ $key ];
		}
		$vars['--se-on-primary']     = '' !== $config['primary_text'] ? $config['primary_text'] : self::foreground( $config['primary'] );
		$vars['--se-radius']         = self::RADII[ $config['corner_style'] ];
		$vars['--se-control-radius'] = 'soft' === $config['corner_style'] ? '0.5rem' : self::RADII[ $config['corner_style'] ];
		$baseline                    = '#205b48' === $config['primary'] && '#ffffff' === $config['surface'];
		$derived                     = array(
			'hover'           => array( '#164333', '#000000', 0.75 ),
			'accent'          => array( '#276a55', $config['surface'], 1 ),
			'link'            => array( '#165b4b', $config['surface'], 1 ),
			'label'           => array( '#386453', $config['surface'], 1 ),
			'secondary-text'  => array( '#245440', $config['surface'], 1 ),
			'secondary'       => array( '#eef4f0', $config['surface'], 0.08 ),
			'secondary-hover' => array( '#dceae1', $config['surface'], 0.16 ),
			'border'          => array( '#d5e0db', $config['surface'], 0.2 ),
			'control-border'  => array( '#c7d8ce', $config['surface'], 0.25 ),
			'track'           => array( '#dce6e0', $config['surface'], 0.18 ),
			'task'            => array( '#f0f6f3', $config['surface'], 0.06 ),
			'quote'           => array( '#83a593', $config['surface'], 0.55 ),
			'notice-border'   => array( '#aabcb2', $config['surface'], 0.35 ),
		);
		foreach ( $derived as $key => $parts ) {
			$vars[ '--se-' . $key ] = $baseline ? $parts[0] : self::mix( $config['primary'], $parts[1], $parts[2] );
		}
		if ( ! $baseline && '#000000' === self::foreground( $config['primary'] ) ) {
			$vars['--se-hover'] = self::mix( $config['primary'], '#ffffff', 0.85 );
		}
		if ( ! $baseline ) {
			$vars['--se-secondary-text'] = self::foreground( $vars['--se-secondary'] );
			$vars['--se-label']          = self::mix( $config['primary'], self::foreground( $config['surface'] ), 0.25 );
			$vars['--se-link']           = $vars['--se-label'];
		}
		return $vars;
	}

	/**
	 * Validated CSS declarations only.
	 *
	 * @return string
	 */
	public static function css() {
		$css = '';
		foreach ( self::variables() as $key => $value ) {
			$css .= $key . ':' . $value . ';';
		}
		return $css;
	}

	/**
	 * WordPress-generated image markup, or empty.
	 *
	 * @return string
	 */
	public static function logo() {
		$id = self::get()['logo_id'];
		return $id ? wp_get_attachment_image(
			$id,
			'medium',
			false,
			array(
				'class' => 'se-runner__logo',
				'alt'   => get_post_meta( $id, '_wp_attachment_image_alt', true ) ? get_post_meta( $id, '_wp_attachment_image_alt', true ) : get_bloginfo( 'name' ),
			)
		) : '';
	}
}
