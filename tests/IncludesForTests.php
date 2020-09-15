<?php

declare( strict_types=1 );


if ( ! defined( 'VIPGOCS_UNIT_TESTING' ) ) {
	define( 'VIPGOCS_UNIT_TESTING', true );
}

$vipgoci_include_path = getenv( 'VIPGOCI_PATH' );

if (
	( empty( $vipgoci_include_path ) ) ||
	( is_dir( $vipgoci_include_path ) === false )
) {
	echo 'Error: Path to vip-go-ci is not set or invalid. Specify path using the VIPGOCI_PATH environmental variable.' . PHP_EOL;
	exit( 255 );
}

require_once( __DIR__ . '/../compatibility-scanner.php' );

require_once( $vipgoci_include_path . '/main.php' );


