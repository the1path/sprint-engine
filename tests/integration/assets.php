<?php
/** SE-007 deterministic asset versions; no WordPress database required. */
define( 'ABSPATH', __DIR__ );
define( 'SPRINT_ENGINE_VERSION', '0.2.1' );
require dirname( __DIR__, 2 ) . '/src/Assets.php';

use ThePath\SprintEngine\Assets;

function check_asset( $ok, $label ) {
    if ( ! $ok ) { throw new RuntimeException( $label ); }
    echo "PASS: $label\n";
}
$file = tempnam( __DIR__, 'asset-' );
$relative = 'tests/integration/' . basename( $file );
try {
    file_put_contents( $file, 'first' );
    $time = filemtime( $file );
    $first = Assets::version( $relative );
    check_asset( $first === Assets::version( $relative ), 'Unchanged bytes have a stable version' );
    file_put_contents( $file, 'other' );
    touch( $file, $time );
    check_asset( $first !== Assets::version( $relative ), 'Changed bytes with identical size and timestamp change version' );
    touch( $file, $time + 10 );
    check_asset( Assets::version( $relative ) === '0.2.1-' . hash( 'sha256', 'other' ), 'Timestamp-only changes preserve cacheability' );
    check_asset( Assets::version( 'missing-asset.css' ) === '0.2.1', 'Missing file falls back safely' );
    check_asset( Assets::version( 'assets' ) === '0.2.1', 'Directory falls back safely' );
    foreach ( array( 'css/runner.css', 'js/runner.js', 'css/dashboard.css', 'js/dashboard.js', 'css/structure-admin.css', 'js/structure-admin.js', 'js/runner-admin.js' ) as $asset ) {
        check_asset( Assets::version( 'assets/' . $asset ) === '0.2.1-' . hash_file( 'sha256', dirname( __DIR__, 2 ) . '/assets/' . $asset ), 'Runtime fingerprint: ' . $asset );
    }
} finally { unlink( $file ); }
