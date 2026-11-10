<?php
// Exercises the plugin's real ai1wm_copy() the way Ai1wm_Import_Upload now uses it.
// Run: php -d zend.assertions=1 -d assert.exception=1 tests/chunk-upload-test.php src/mindsoft-wp-migration "$(mktemp -d)"
define( 'ABSPATH', '/' );
function __( $s ) { return $s; }
function wp_kses( $s ) { return $s; }
foreach ( array( 'exceptions.php', 'functions.php' ) as $f ) { require $argv[1] . '/' . $f; }

$dir  = $argv[2];
$src  = "$dir/src.bin";
$dst  = "$dir/dst.wpress";
@unlink( $dst );
file_put_contents( $src, random_bytes( 12 * 1024 * 1024 + 123 ) );

$size  = filesize( $src );
$chunk = (int) floor( 5 * 1024 * 1024 * 0.9 );
$parts = array();
for ( $off = 0; $off < $size; $off += $chunk ) {
	$tmp = "$dir/part-$off";
	file_put_contents( $tmp, file_get_contents( $src, false, null, $off, $chunk ) );
	$parts[ $off ] = $tmp;
}

// Write chunks out of order and repeat one (a retry after a lost response)
$order = array_keys( $parts );
shuffle( $order );
$order[] = $order[0];
foreach ( $order as $off ) { ai1wm_copy( $parts[ $off ], $dst, $off ); }

assert( filesize( $dst ) === $size, 'size mismatch' );
assert( md5_file( $dst ) === md5_file( $src ), 'content mismatch' );
echo 'OK: ' . count( $parts ) . " chunks, $size bytes, retry did not duplicate data\n";
