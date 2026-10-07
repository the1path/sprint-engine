<?php
/** Verify the built package against the exact runtime source allowlist. */
$root = dirname( __DIR__ );
$zip = new ZipArchive();
if ( true !== $zip->open( $root . '/dist/sprint-engine.zip' ) ) { throw new RuntimeException( 'Build the package first.' ); }
$expected = array( 'sprint-engine.php', 'uninstall.php', 'readme.txt', 'LICENSE' );
foreach ( array( 'src' => array( 'php' ), 'templates' => array( 'php' ), 'assets' => array( 'css', 'js' ) ) as $dir => $extensions ) {
    foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root . '/' . $dir, FilesystemIterator::SKIP_DOTS ) ) as $file ) {
        if ( $file->isFile() && in_array( $file->getExtension(), $extensions, true ) ) {
            $expected[] = str_replace( '\\', '/', substr( $file->getPathname(), strlen( $root ) + 1 ) );
        }
    }
}
if ( count( $expected ) !== $zip->numFiles ) { throw new RuntimeException( 'Unexpected archive entry count.' ); }
$entries = array();
for ( $index = 0; $index < $zip->numFiles; ++$index ) {
    $entry = $zip->getNameIndex( $index );
    if ( isset( $entries[ $entry ] ) || ! str_starts_with( $entry, 'sprint-engine/' ) || str_contains( $entry, '..' ) || str_contains( $entry, '\\' ) ) { throw new RuntimeException( 'Invalid or duplicate archive path.' ); }
    $entries[ $entry ] = true;
    if ( $zip->getExternalAttributesIndex( $index, $system, $attributes ) && 0120000 === ( ( $attributes >> 16 ) & 0170000 ) ) { throw new RuntimeException( 'Archive contains a symlink.' ); }
}
foreach ( $expected as $name ) {
    $bytes = $zip->getFromName( 'sprint-engine/' . $name );
    if ( $bytes !== file_get_contents( $root . '/' . $name ) ) { throw new RuntimeException( 'Missing or mismatched runtime file: ' . $name ); }
    if ( preg_match( '/(?<![A-Za-z0-9])_?se_[A-Za-z0-9_]+|\bse(?:Runner|RunnerAdmin|Structure|Branding|AuthoringValidation)\b/', $bytes ) ) { throw new RuntimeException( 'Pre-release registration/storage/global identifier: ' . $name ); }
    if ( preg_match( '/(?<!ThePath\\\\)(?<!ThePath\\\\\\\\)\bSprintEngine\\\\/', $bytes ) ) { throw new RuntimeException( 'Pre-release PHP namespace: ' . $name ); }
    if ( str_starts_with( $name, 'src/' ) && ! str_contains( $bytes, 'namespace ThePath\\SprintEngine' ) ) { throw new RuntimeException( 'Missing vendor namespace: ' . $name ); }
    if ( preg_match( '/disposable-test-only|SE_TEST_WP_ROOT|github_pat_|ghp_[a-zA-Z0-9]{20}/', $bytes ) ) { throw new RuntimeException( 'Development or credential marker: ' . $name ); }
    if ( preg_match( '~(?:[A-Z]:[\\\\/]Users[\\\\/]|wp-config\.php.*DB_PASSWORD|-----BEGIN .*PRIVATE KEY)~', $bytes ) ) { throw new RuntimeException( 'Local path or secret marker: ' . $name ); }
}
foreach (array('src/Dashboard/Routes.php','src/Dashboard/Dashboard.php','templates/dashboard.php','assets/css/dashboard.css','assets/js/dashboard.js') as $file) {
    if (!in_array($file,$expected,true)) { throw new RuntimeException('Missing Dashboard runtime file: ' . $file); }
}
$zip->close();
echo 'PASS: ' . count( $expected ) . " exact runtime files; no extra archive entries, local user paths or private-key markers.\n";
