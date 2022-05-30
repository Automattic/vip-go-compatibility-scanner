<?php

declare( strict_types=1 );

/*
 * Define unit-testing for vip-go-compatibility-scanner.
 */
if ( ! defined( 'VIPGOCS_UNIT_TESTING' ) ) {
	define( 'VIPGOCS_UNIT_TESTING', true );
}

/*
 * Get include path for vip-go-ci.
 */
$vipgoci_include_path = getenv( 'VIPGOCI_PATH' );

if (
	( empty( $vipgoci_include_path ) ) ||
	( is_dir( $vipgoci_include_path ) === false )
) {
	echo 'Error: Path to vip-go-ci is not set or invalid. Specify path using the VIPGOCI_PATH environmental variable.' . PHP_EOL;
	exit( 255 );
}

/*
 * Specify INI path for unit-tests.
 */
define( 'VIPGOCI_UNIT_TESTS_INI_DIR_PATH', dirname( __DIR__ ) );

/*
 * Define constants for test HTTP server for unit-testing.
 */

define( 'VIPGOCI_GITHUB_SERVER_ADDR', '127.0.0.1:15701' );
define( 'VIPGOCI_GITHUB_BASE_URL', 'http://' . VIPGOCI_GITHUB_SERVER_ADDR );
define( 'VIPGOCI_TEST_HTTP_SERVER_FILES_PATH', dirname( __DIR__ ) . '/integration/test-server' );

/*
 * Function to start test HTTP server.
 */

function vipgoci_unittests_http_server_start( $php_path, $listen_address, $exec_folder, $pid_file, $output_file ) {
	/*
	 * Fork, let child continue processing.
	 */
	$pid = pcntl_fork();

	if ( $pid === 0 ) {
		/* Give child time to run shell_exec() */
		sleep(5);
		return;
	}

	$cmd = sprintf(
		'cd %s && VIPGOCI_UNITTESTS_PID_FILE=%s VIPGOCI_UNITTESTS_OUTPUT_FILE=%s %s -S %s 2>&1 >/dev/null',
		escapeshellcmd( $exec_folder ),
		escapeshellarg( $pid_file ),
		escapeshellarg( $output_file ),
		escapeshellcmd( $php_path ),
		escapeshellarg( $listen_address )
	);

	$result = shell_exec( $cmd );
	
	exit( 0 );
}

/*
 * Stop test HTTP server.
 */
function vipgoci_unittests_http_server_stop( $pid_file ) {
	$server_pid = trim( file_get_contents(
		$pid_file
	) );

	if ( empty( $server_pid ) ) {
		return;
	}

	if ( $server_pid <= 0 ) {
		return;
	}

	return posix_kill( (int) $server_pid, SIGKILL );
}

/*
 * Check if there is support for pcntl functions.
 */
function vipgoci_unittests_pcntl_supported() {
	if ( function_exists( 'pcntl_fork' ) ) {
		return true;
	}

	return false;
}

/*
 * Safely remove temporary folder
 */
function vipgoci_unittests_remove_temporary_folder_safely( $path ) {
	if (
		( ! empty(
			$path
		) )
		&&
		( strlen(
			$path
		) > 0 )
		&&
		( strpos(
			$path,
			sys_get_temp_dir()
		) !== false )
	) {
		$cmd = sprintf(
			'rm -rf %s',
			escapeshellarg( $path )
		);

		shell_exec( $cmd );
	}
}

/*
 * Require files needed for testing.
 */
require_once( __DIR__ . '/../../compatibility-scanner.php' );


require_once( $vipgoci_include_path . '/tests/integration/IncludesForTests.php' );


