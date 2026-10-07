<?php
/** Build an installable ZIP containing runtime files only. */

$root = dirname( __DIR__ );
if ( ! class_exists( 'ZipArchive' ) ) {
	fwrite( STDERR, "The PHP zip extension is required.\n" );
	exit( 1 );
}
if ( ! is_dir( $root . '/dist' ) ) {
	mkdir( $root . '/dist', 0755, true );
}
$zip = new ZipArchive();
if ( true !== $zip->open( $root . '/dist/sprint-engine.zip', ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
	throw new RuntimeException( 'Could not create plugin archive.' );
}
$files = array( 'sprint-engine.php', 'uninstall.php', 'readme.txt', 'LICENSE' );

foreach ( array( 'src' => array( 'php' ), 'templates' => array( 'php' ), 'assets' => array( 'js', 'css' ) ) as $directory => $extensions ) {
	foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root . '/' . $directory, FilesystemIterator::SKIP_DOTS ) ) as $file ) {
		if ( $file->isLink() ) {
			throw new RuntimeException( 'Runtime symlinks cannot be packaged.' );
		}
		if ( $file->isFile() && in_array( $file->getExtension(), $extensions, true ) ) {
			$files[] = str_replace( '\\', '/', substr( $file->getPathname(), strlen( $root ) + 1 ) );
		}
	}
}
sort( $files );
foreach ( $files as $file ) {
	if ( ! $zip->addFile( $root . '/' . $file, 'sprint-engine/' . $file ) ) {
		throw new RuntimeException( 'Could not add ' . $file );
	}
}
if ( ! $zip->close() ) {
	throw new RuntimeException( 'Could not finish plugin archive.' );
}
echo "Created dist/sprint-engine.zip\n";
