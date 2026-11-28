<?php
/**
 * Bounded registration input; no silent password or international-name changes.
 *
 * @package SprintEngine
 */

namespace ThePath\SprintEngine\Registration;

defined( 'ABSPATH' ) || exit;

/** Validate native storage/authentication semantics before handing fields to WP. */
final class Input {

	const FIELDS = array( 'sprint_engine_action', 'sprint_engine_registration_token', 'first_name', 'last_name', 'email', 'password' );

	/**
	 * Validate an unslashed fixed-field request. Never forward the raw request array.
	 *
	 * @param array $input Submitted scalar values.
	 * @return array Normalised values plus safe field errors.
	 */
	public static function validate( $input ) {
		$values = array(
			'first_name' => '',
			'last_name'  => '',
			'email'      => '',
			'password'   => '',
		);
		$errors = array();
		foreach ( array_keys( $values ) as $field ) {
			if ( ! isset( $input[ $field ] ) || ! is_string( $input[ $field ] ) ) {
				$errors[ $field ] = __( 'Enter a valid value for this field.', 'sprint-engine' );
				continue;
			}
			$value = $input[ $field ];
			if ( 'password' === $field ) {
				if ( strlen( $value ) > 4096 || trim( $value ) !== $value || preg_match( '/[\x00-\x1f\x7f]/', $value ) || 1 !== preg_match( '//u', $value ) || preg_match_all( '/./us', $value ) < 12 ) {
					$errors[ $field ] = __( 'Use at least 12 characters, without leading/trailing spaces or control characters. The maximum is 4,096 bytes.', 'sprint-engine' );
				} else {
					$values[ $field ] = $value;
				}
				continue;
			}
			if ( strlen( $value ) > 400 || 1 !== preg_match( '//u', $value ) || preg_match( '/\p{Cc}/u', $value ) ) {
				$errors[ $field ] = __( 'Use a shorter value without control characters.', 'sprint-engine' );
				continue;
			}
			$value = preg_replace( '/\A[\s\p{Z}]+|[\s\p{Z}]+\z/u', '', $value );
			if ( 'email' === $field ) {
				$values[ $field ] = sanitize_email( $value );
				if ( $values[ $field ] !== $value || strlen( $value ) > 100 || ! is_email( $value ) ) {
					$errors[ $field ] = __( 'Enter a valid email address of at most 100 characters.', 'sprint-engine' );
				}
			} else {
				$values[ $field ] = sanitize_text_field( $value );
				if ( '' === $value || $values[ $field ] !== $value || preg_match_all( '/./us', $value ) > 100 ) {
					$errors[ $field ] = __( 'Enter your name, up to 100 characters, without markup or control characters.', 'sprint-engine' );
				}
			}
		}
		return array(
			'values' => $values,
			'errors' => $errors,
		);
	}
}
