#!/usr/bin/env php
<?php
/**
 * Parse log file from vip-go-ci / vip-go-compatibility scanner
 * and report any entries not found in file containing whitelisted log
 * entries.
 *
 * @package Automattic/vip-go-compatibility-scanner
 */

global $argv;

/**
 * Parse file containing expected log entries.
 *
 * @param string $file_path Path to file.
 *
 * @return array
 */
function parse_expected_log_entries_file( string $file_path ) :array {
	$file_contents = file_get_contents(
		$file_path
	);

	if ( empty( $file_contents ) ) {
		die( 'Unable to get expected log entries from file' . PHP_EOL );
	}

	$file_contents_arr = explode(
		PHP_EOL,
		$file_contents
	);

	unset( $file_contents );

	$file_contents_arr = array_filter(
		$file_contents_arr,
		function( $arr_item ) {
			return ( ! empty( $arr_item ) );
		}
	);

	$file_contents_arr = array_map(
		function( $arr_item ) {
			return trim( $arr_item );
		},
		$file_contents_arr
	);

	return $file_contents_arr;
}

/**
 * Parse file containing log entries.
 *
 * @param string $file_path Path to file.
 *
 * @return array
 */
function parse_log_file( string $file_path ) :array {
	$file_contents = file_get_contents(
		$file_path
	);

	if ( empty( $file_contents ) ) {
		die( 'Unable to get log file contents' . PHP_EOL );
	}

	$file_contents_arr = explode(
		PHP_EOL,
		$file_contents
	);

	unset( $file_contents );

	$log_entries = array();

	$line_no = 0;

	foreach ( $file_contents_arr as $file_line ) {
		$line_no++;

		if ( strpos( $file_line, '[' ) !== 0 ) {
			continue;
		}

		if ( strpos( $file_line, ']' ) === false ) {
			continue;
		}

		$log_msg_begin_pos = strpos( $file_line, '"', 0 );
		$log_msg_end_pos   = strpos( $file_line, '"', $log_msg_begin_pos + 1 );

		if (
			( false === $log_msg_begin_pos ) ||
			( false === $log_msg_end_pos )
		) {
			continue;
		}

		$log_entry = substr(
			$file_line,
			$log_msg_begin_pos + 1,
			$log_msg_end_pos - ( 1 + $log_msg_begin_pos )
		);

		$log_entry = str_replace(
			'(cached)',
			'',
			$log_entry
		);

		$log_entry = trim( $log_entry );

		if ( empty( $log_entry ) ) {
			continue;
		}

		$log_entries[ $line_no ] = $log_entry;
	}

	return $log_entries;
}

/**
 * Return any unexpected log entries found.
 *
 * @param array $expected_log_entries Expected log entries.
 * @param array $file_log_entries     Log entries found.
 *
 * @return array
 */
function find_unexpected_log_entries(
	array $expected_log_entries,
	array $file_log_entries
) :array {
	$unexpected_log_entries = array_diff(
		$file_log_entries,
		$expected_log_entries
	);

	/*
	 * Check if expected log entries with wildcards
	 * match any actual log entries.
	 */
	$unexpected_log_entries = array_filter(
		$unexpected_log_entries,
		function ( $log_entry ) use ( $expected_log_entries ) {
			foreach ( $expected_log_entries as $expected_log_entry ) {
				$wildcard_pos = strpos(
					$expected_log_entry,
					'*'
				);

				if ( false === $wildcard_pos ) {
					continue;
				}

				$expected_log_entry_without_wildcard = substr(
					$expected_log_entry,
					0,
					$wildcard_pos
				);

				if ( empty( $expected_log_entry_without_wildcard ) ) {
					continue;
				}

				$match = str_contains(
					$log_entry,
					$expected_log_entry_without_wildcard
				);

				if ( true === $match ) {
					return false;
				}
			}

			return true;
		}
	);

	return $unexpected_log_entries;
}

/**
 * Main routine.
 *
 * @return int Exit code.
 */
function main() :int {
	global $argv;

	if ( count( $argv ) !== 3 ) {
		die( 'Usage: ' . $argv[0] . ' expected-log-entries-file log-file' . PHP_EOL );
	}

	$expected_log_entres_file_path = $argv[1];

	$expected_log_entries = parse_expected_log_entries_file( $expected_log_entres_file_path );

	$log_file_path = $argv[2];

	$file_log_entries = parse_log_file( $log_file_path );

	$unexpected_log_entries = find_unexpected_log_entries(
		$expected_log_entries,
		$file_log_entries
	);

	if ( empty( $unexpected_log_entries ) ) {
		echo 'No unexpected log entries found in file ' . $log_file_path . PHP_EOL;
		return 0;
	} else {
		echo 'Unexpected log entries were found in file ' . $log_file_path . PHP_EOL;
		print_r( $unexpected_log_entries );
		return 1;
	}
}

exit( main() );

