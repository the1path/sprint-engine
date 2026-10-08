<?php
/** Release metadata and directory readme consistency checks; no WordPress needed. */
$root = dirname( __DIR__ );
$plugin = file_get_contents( $root . '/sprint-engine.php' );
$readme = file_get_contents( $root . '/readme.txt' );
function release_check( $condition, $message ) {
    if ( ! $condition ) { throw new RuntimeException( $message ); }
    echo 'PASS: ' . $message . PHP_EOL;
}
$headers = array(
    'Plugin Name' => 'Sprint Engine', 'Version' => '0.2.1',
    'Requires at least' => '6.0', 'Requires PHP' => '8.0', 'Author' => 'ThePath',
    'License' => 'GPLv2 or later', 'License URI' => 'https://www.gnu.org/licenses/gpl-2.0.html',
    'Text Domain' => 'sprint-engine',
);
foreach ( $headers as $key => $value ) {
    release_check( preg_match( '/^ \* ' . preg_quote( $key, '/' ) . ': (.+)$/m', $plugin, $match ) && trim( $match[1] ) === $value, 'Plugin header: ' . $key );
}
foreach ( array( 'VERSION' => '0.2.1', 'SCHEMA_VERSION' => '2', 'MIN_PHP' => '8.0', 'MIN_WP' => '6.0' ) as $key => $value ) {
    release_check( str_contains( $plugin, "define( 'SPRINT_ENGINE_" . $key . "', '" . $value . "' );" ), 'Runtime constant: ' . $key );
}
release_check( str_starts_with( $readme, "=== Sprint Engine ===\n" ), 'Directory readme title.' );
foreach ( array( 'Contributors' => 'thepath', 'Stable tag' => '0.2.1', 'Tested up to' => '7.1', 'Requires at least' => '6.0', 'Requires PHP' => '8.0', 'License' => $headers['License'], 'License URI' => $headers['License URI'] ) as $key => $value ) {
    release_check( preg_match( '/^' . preg_quote( $key, '/' ) . ': (.+)$/m', $readme, $match ) && trim( $match[1] ) === $value, 'Readme metadata: ' . $key );
}
preg_match( '/^Tags: (.+)$/m', $readme, $tags );
release_check( array_map( 'trim', explode( ',', $tags[1] ) ) === array( 'workflow', 'onboarding', 'training', 'learning', 'progress' ), 'Five approved tags.' );
$paragraphs = explode( "\n\n", $readme );
release_check( strlen( $paragraphs[1] ) <= 150, 'Short description fits 150 characters.' );
foreach ( array( 'Description', 'Installation', 'Frequently Asked Questions', 'Screenshots', 'Privacy and Data', 'Changelog' ) as $section ) {
    release_check( str_contains( $readme, '== ' . $section . ' ==' ), 'Readme section: ' . $section );
}
preg_match( '/== Screenshots ==\n(.*?)\n== Privacy and Data ==/s', $readme, $screenshots );
preg_match_all( '/^([1-5])\. /m', $screenshots[1], $numbers );
release_check( $numbers[1] === array( '1', '2', '3', '4', '5' ), 'Five sequential screenshot captions.' );
release_check( preg_match( '/^= 0\.2\.0 =\s*\n(.*?)(?=^= [^=]+ =|\z)/ms', $readme, $changelog ), '0.2.0 release changelog.' );
release_check( preg_match( '/\binitial public release\b/i', $changelog[1] ), '0.2.0 is the initial public release.' );
release_check( preg_match( '/^= 0\.2\.1 =\s*\n(.*?)(?=^= [^=]+ =|\z)/ms', $readme, $patch_changelog ), '0.2.1 release changelog.' );
release_check( str_contains( $patch_changelog[1], 'attempt-history' ) && str_contains( $patch_changelog[1], 'Dashboard header' ) && str_contains( $patch_changelog[1], 'no database migration' ), '0.2.1 documents the API, hook and unchanged storage.' );
release_check( false === stripos( $readme, 'unreleased' ), 'Public readme has no unreleased framing.' );
release_check( ! preg_match( '/^= 0\.1\.0 =\s*$/m', $readme ), 'Private 0.1.0 changelog is absent from public readme.' );
release_check( ! str_contains( $readme, '0.1.0' ), 'Public readme has no previous-public-release or private-upgrade implication.' );
$license = file_get_contents( $root . '/LICENSE' );
$composer = json_decode( file_get_contents( $root . '/composer.json' ), true, 512, JSON_THROW_ON_ERROR );
release_check( str_contains( $license, 'Version 2, June 1991' ) && str_contains( $license, 'END OF TERMS AND CONDITIONS' ) && 'GPL-2.0-or-later' === $composer['license'], 'Canonical GPL v2 text and later-version package metadata.' );
$attributes = file_get_contents( $root . '/.gitattributes' );
release_check( str_contains( $attributes, '* text=auto eol=lf' ) && str_contains( $attributes, '*.png binary' ) && str_contains( $attributes, '*.zip binary' ), 'LF text policy with binary exclusions.' );

release_check(str_contains($readme,'My Sprints member Dashboard') && str_contains($readme,'/sprint-engine/dashboard/'), 'Member Dashboard and canonical route documented.');
release_check(str_contains($readme,'Publish every Step') && str_contains($readme,'Every associated Step is Published'), 'Step publication requirement documented.');
