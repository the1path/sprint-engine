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
    'Plugin Name' => 'Sprint Engine', 'Version' => '0.2.3',
    'Requires at least' => '6.0', 'Requires PHP' => '8.0', 'Author' => 'ThePath',
    'License' => 'GPLv2 or later', 'License URI' => 'https://www.gnu.org/licenses/gpl-2.0.html',
    'Text Domain' => 'sprint-engine',
);
foreach ( $headers as $key => $value ) {
    release_check( preg_match( '/^ \* ' . preg_quote( $key, '/' ) . ': (.+)$/m', $plugin, $match ) && trim( $match[1] ) === $value, 'Plugin header: ' . $key );
}
foreach ( array( 'VERSION' => '0.2.3', 'SCHEMA_VERSION' => '2', 'MIN_PHP' => '8.0', 'MIN_WP' => '6.0' ) as $key => $value ) {
    release_check( str_contains( $plugin, "define( 'SPRINT_ENGINE_" . $key . "', '" . $value . "' );" ), 'Runtime constant: ' . $key );
}
release_check( str_starts_with( $readme, "=== Sprint Engine ===\n" ), 'Directory readme title.' );
foreach ( array( 'Contributors' => 'thepath', 'Stable tag' => '0.2.3', 'Tested up to' => '7.1', 'Requires at least' => '6.0', 'Requires PHP' => '8.0', 'License' => $headers['License'], 'License URI' => $headers['License URI'] ) as $key => $value ) {
    release_check( preg_match( '/^' . preg_quote( $key, '/' ) . ': (.+)$/m', $readme, $match ) && trim( $match[1] ) === $value, 'Readme metadata: ' . $key );
}
preg_match( '/^Tags: (.+)$/m', $readme, $tags );
preg_match( '/^ \* Version: (.+)$/m', $plugin, $header_version );
preg_match( "/define\( 'SPRINT_ENGINE_VERSION', '([^']+)' \);/", $plugin, $constant_version );
preg_match( '/^Stable tag: (.+)$/m', $readme, $stable_version );
release_check( trim( $header_version[1] ) === $constant_version[1] && $constant_version[1] === trim( $stable_version[1] ), 'Plugin header, runtime version and readme stable tag align.' );
$tag_list = array_map( 'trim', explode( ',', $tags[1] ?? '' ) );
release_check( count( $tag_list ) <= 5 && count( array_unique( array_map( 'strtolower', $tag_list ) ) ) === count( $tag_list ) && ! in_array( '', $tag_list, true ), 'One to five unique, nonempty directory tags.' );
preg_match( '/\n\n([^\n]+)\n\n== Description ==\n/', $readme, $short_description );
$short = $short_description[1] ?? '';
release_check( '' !== $short && preg_match( '/^.{1,150}$/uD', $short ) && ! preg_match( '/[<>`*\[\]]/', $short ), 'Plain-text short description fits 150 characters.' );
preg_match_all( '/^== ([^=]+) ==$/m', $readme, $sections );
foreach ( array( 'Description', 'Installation', 'Frequently Asked Questions', 'Screenshots', 'Privacy and Data', 'Changelog' ) as $section ) {
    release_check( 1 === count( array_keys( $sections[1], $section, true ) ), 'Unique readme section: ' . $section );
}
preg_match( '/^== Screenshots ==\n(.*?)(?=^== [^=]+ ==|\z)/ms', $readme, $screenshots );
preg_match_all( '/^(\d+)\. \S.+$/m', $screenshots[1] ?? '', $numbers );
release_check( $numbers[1] === array( '1', '2', '3', '4', '5' ), 'Five sequential screenshot captions.' );
preg_match( '/^== Changelog ==\n(.*?)(?=^== [^=]+ ==|\z)/ms', $readme, $changelog_section );
preg_match_all( '/^= (\d+\.\d+\.\d+) =$/m', $changelog_section[1] ?? '', $changelog_versions );
release_check( $changelog_versions[1] === array( $constant_version[1], '0.2.2', '0.2.1', '0.2.0' ), 'Changelog starts with the candidate and preserves the public release history in order.' );
release_check( preg_match( '/^= 0\.2\.3 =\n\n\* \S/m', $changelog_section[1] ?? '' ), '0.2.3 contains a bulleted release changelog.' );
release_check( preg_match( '/^= 0\.2\.0 =\s*\n(.*?)(?=^= [^=]+ =|\z)/ms', $readme, $changelog ), '0.2.0 release changelog.' );
release_check( preg_match( '/\binitial public release\b/i', $changelog[1] ), '0.2.0 is the initial public release.' );
release_check( preg_match( '/^= 0\.2\.1 =\s*\n(.*?)(?=^= [^=]+ =|\z)/ms', $readme, $patch_changelog ), '0.2.1 release changelog.' );
release_check( str_contains( $patch_changelog[1], 'attempt-history' ) && str_contains( $patch_changelog[1], 'Dashboard header' ) && str_contains( $patch_changelog[1], 'no database migration' ), '0.2.1 documents the API, hook and unchanged storage.' );
release_check( preg_match( '/^= 0\.2\.2 =\s*\n(.*?)(?=^= [^=]+ =|\z)/ms', $readme, $release_changelog ), '0.2.2 release changelog.' );
release_check( str_contains( $release_changelog[1], 'completed Sprint actions' ) && str_contains( $release_changelog[1], 'completed Dashboard cards' ) && str_contains( $release_changelog[1], 'access-denied Runner' ) && str_contains( $release_changelog[1], 'extensions to opt into a public access screen' ) && str_contains( $release_changelog[1], 'by default' ) && str_contains( $release_changelog[1], 'No database changes or migrations' ), '0.2.2 documents optional presentation hooks, preserved defaults and unchanged storage.' );
release_check( false === stripos( $readme, 'unreleased' ), 'Public readme has no unreleased framing.' );
release_check( ! preg_match( '/^= 0\.1\.0 =\s*$/m', $readme ), 'Private 0.1.0 changelog is absent from public readme.' );
release_check( ! str_contains( $readme, '0.1.0' ), 'Public readme has no previous-public-release or private-upgrade implication.' );
$license = file_get_contents( $root . '/LICENSE' );
$composer = json_decode( file_get_contents( $root . '/composer.json' ), true, 512, JSON_THROW_ON_ERROR );
release_check( str_contains( $license, 'Version 2, June 1991' ) && str_contains( $license, 'END OF TERMS AND CONDITIONS' ) && 'GPL-2.0-or-later' === $composer['license'], 'Canonical GPL v2 text and later-version package metadata.' );
$attributes = file_get_contents( $root . '/.gitattributes' );
release_check( str_contains( $attributes, '* text=auto eol=lf' ) && str_contains( $attributes, '*.png binary' ) && str_contains( $attributes, '*.zip binary' ), 'LF text policy with binary exclusions.' );

release_check( str_contains( $readme, '/sprint-engine/dashboard/' ), 'Canonical member Dashboard route documented.' );
release_check( preg_match( '/^\d+\. .*\bpublish\b.*\bStep\b.*\bLaunchable\b/im', $readme ), 'Installation explains Step publication and Launchable status.' );
