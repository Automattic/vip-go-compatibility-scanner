<?php

/*
 * Check if we are running on PHP 7.3 or later.
 */
function vipgocs_env_check() {
	if ( version_compare(
		phpversion(),
		'7.3.0'
	) < 0 ) {
		echo 'Error: PHP 7.3 is required as a minimum.' . PHP_EOL;
		exit( 251 ); /* System problem */
	}
}

/*
 * Report any errors to the user.
 */
function vipgocs_logging_setup() {
	ini_set( 'error_log', '' );

	error_reporting( E_ALL );

	ini_set( 'display_errors', 'on' );
}

/*
 * Attempt to load vip-go-ci
 */
function vipgocs_vipgoci_load(
	$options,
	$option_name
) {
	if ( ! is_dir( $options[ $option_name ] ) ) {
		echo 'Path specified in --' . $option_name . ' is invalid, is not a directory' . PHP_EOL;
		exit(253);
	}

	if ( ! is_file( $options[ $option_name ] . '/requires.php' ) ) {
		echo 'No requires.php found in --' . $option_name . ' ; is it a valid vip-go-ci installation?' . PHP_EOL;
		exit(253);
	}

	/*
	 * Include vip-go-ci as a library.
	 */
	echo 'Attempting to include vip-go-ci...' . PHP_EOL;

	require_once(
		$options[ $option_name ] . '/requires.php'
	);

	vipgoci_log(
		'Successfully included vip-go-ci'
	);
}

/*
 * Check if file only has
 * whitespacing.
 */
function vipgocs_file_empty_or_whitespace_only(
	string $file_path,
	string $whitespacing_chars = " \n\r\t\v\0"
) {
	$file_contents = file_get_contents(
		$file_path
	);

	/*
	 * In case of error, return null.
	 */
	if ( false === $file_contents ) {
		return null;
	}

	/*
	 * Remove all whitespacing from the
	 * file contents; if there is no content
	 * left after that the file is only whitespacing.
	 */
	$file_contents = trim( $file_contents, $whitespacing_chars );

	if ( '' === $file_contents ) {
		return true;
	}

	/*
	 * There is content, so return false.
	 */
	return false;
}
