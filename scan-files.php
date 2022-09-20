<?php

/**
 * Either use cached results or start
 * PHPCS to scan a single file.
 * Do some processing and cache
 * results (if not cached already).
 *
 *
 * Testing of this is performed indirectly by
 * ScanFilesScanFilesTest.php
 *
 * @codeCoverageIgnore
 */
function vipgocs_scan_single_file(
	array $options,
	string $file_relative_path,
	&$phpcscachedb_conn
) :?array {
	
	$file_results_cached = null;

	$cached_zero_results = false;

	if ( null !== $phpcscachedb_conn ) {
		$file_results_cached = vipgocs_phpcs_cachedb_get(
			$phpcscachedb_conn,
			$options['local-git-repo'] . DIRECTORY_SEPARATOR . $file_relative_path,
			array(
				'phpcs-severity'	=> $options['phpcs-severity'],
				'phpcs-standard'	=> $options['phpcs-standard'],
				'phpcs-runtime-set'	=> $options['phpcs-runtime-set'],
				'phpcs-sniffs-exclude'	=> $options['phpcs-sniffs-exclude'],
			),
			$cached_zero_results
		);
	}

	if (
		( ! empty( $file_results_cached ) ) ||
		( $cached_zero_results === true )
	) {
		vipgoci_log(
			'Not PHPCS scanning file, using cached results',
			array(
				'file_relative_path'	=> $file_relative_path,
			)
		);

		$temp_file_name = $file_relative_path;

		$file_results = array();

		$file_results
			['file_issues_arr_master']
			['files']
			[ $temp_file_name ]
			['messages']
		= $file_results_cached;

		$file_results
			['file_issues_arr_master']
			['totals']
		= array(
			'warnings'	=> 0,
			'errors'	=> 0,
		);

		foreach( $file_results_cached as $msg_item ) {
			if ( $msg_item['type'] === 'WARNING' ) {
				$file_results['file_issues_arr_master']['totals']['warnings']++;
			}

			else if ( $msg_item['type'] === 'ERROR' ) {
				$file_results['file_issues_arr_master']['totals']['errors']++;
			}
		}
	}

	else {
		/*
		 * Scan a single PHP file, and process the results
		 * -- i.e., collect the information and save it.
		 */
		$file_results = vipgoci_phpcs_scan_single_file(
			$options,
			$file_relative_path
		);

		$temp_file_name = @$file_results['temp_file_name'];

		/*
		 * Check for errors.
		 */
		if (
			( ! isset( $file_results['file_issues_arr_master'] ) ) ||
			( null === $file_results['file_issues_arr_master'] ) ||
			( ! isset( $file_results['temp_file_name'] ) ) ||
			( null === $file_results['temp_file_name'] ) ||
			( empty( $temp_file_name ) ) ||
			( ! isset( $file_results['file_issues_arr_master']['files'][ $temp_file_name ]['messages'] ) )
		) {
			vipgoci_log(
				'Failed parsing output from PHPCS',
				array(
					'repo_owner'	=> $options['repo-owner'],
					'repo_name'	=> $options['repo-name'],
					'commit_id'	=> $options['commit'],
					'file_results'	=> $file_results,
				),
				0
			);

			return null;
		}

		if ( null !== $phpcscachedb_conn ) {
			vipgocs_phpcs_cachedb_add(
				$phpcscachedb_conn,
				$options['local-git-repo'] . DIRECTORY_SEPARATOR . $file_relative_path,
				vipgoci_options_get_starting_with(
					$options,
					'phpcs-',
					array(
						'phpcs-path',
						'phpcs-cachedb',
					)
				),
				$file_results
					['file_issues_arr_master']
					['files']
					[ $temp_file_name ]
					['messages']
			);
		}
	}

	return array( $file_results, $temp_file_name );
}

/**
 * Submodule configuration for a particular file.
 *
 * Returns with GitHub commit URL and relative
 * path to file without submodule.
 */
function vipgocs_scan_file_submodule_config(
	array $options,
	string $file_relative_path,
	?array $file_is_in_submodule
) :array {
	/*
	 * Get URL to commit of
	 * the file -- make sure to
	 * be compatible with submodules.
	 */
	$file_relative_path_without_submodule = null;

	if ( null === $file_is_in_submodule ) {
		$github_commit_url =
			'https://github.com/' .
			$options['repo-owner'] . '/' .
			$options['repo-name'] . '/' .
			'blob/' .
			$options['commit'];
	}

	else {
		$github_commit_url =
			vipgoci_gitrepo_submodule_get_url(
				$options['local-git-repo'],
				$file_is_in_submodule['submodule_path']
			) . 
			'/blob/' .
			$file_is_in_submodule['commit_id'];

		$file_relative_path_without_submodule = $file_relative_path;

		$file_relative_path_without_submodule = substr(
			$file_relative_path_without_submodule,
			( strlen( $file_is_in_submodule['submodule_path'] ) + 1 )
		);
	}

	return array( $github_commit_url, $file_relative_path_without_submodule ); 
}

/**
 * Add information, such as GitHub commit URL, to
 * issue item. 
 */
function vipgocs_scan_file_add_info_to_item(
	&$file_results,
	$temp_file_name,
	$file_relative_path,
	$github_commit_url,
	$file_is_in_submodule,
	$file_relative_path_without_submodule
) {
	$file_results
		['file_issues_arr_master']
		['files']
		[ $temp_file_name ]
		['messages']
	= array_map(
		function(
			$msg
		) use (
			$file_relative_path,
			$github_commit_url,
			$file_is_in_submodule,
			$file_relative_path_without_submodule
		) {
			$msg['github_commit_url'] = $github_commit_url;
			$msg['file_relative_path'] = $file_relative_path;
			$msg['file_is_in_submodule'] = $file_is_in_submodule !== null;
			$msg['file_path_without_submodule'] = $file_relative_path_without_submodule;

			return $msg;
		},
		$file_results
			['file_issues_arr_master']
			['files']
			[ $temp_file_name ]
			['messages']
	);
}

/**
 * Scan all PHP files in the
 * repository using the options
 * given, return the issues found
 * in one array.
 */
function vipgocs_scan_files(
	array $options,
	&$phpcscachedb_conn
) :array {
	vipgoci_log(
		'Fetching tree of files in the repository',
		array(
			'repo_owner'		=> $options['repo-owner'],
			'repo_name'		=> $options['repo-name'],
			'local_git_repo'	=> $options['local-git-repo'],
		)
	);

	/*
	 * Get tree of files that are
	 * available in the commit specified.
	 */
	$tree = vipgoci_gitrepo_fetch_tree(
		$options,
		$options['commit']
	);

	$all_results = array(
		'files'		=> array(),
		'warnings'	=> 0,
		'errors'	=> 0,
		'fixable'	=> 0,
	);

	/*
	 * Loop through each file, note any issues.
	 */
	foreach( $tree as $file_relative_path ) {
		vipgoci_log(
			'Looking at file ' . $file_relative_path,
			array(
				'repo-owner'	=> $options['repo-owner'],
				'repo-name'	=> $options['repo-name'],
				'file_relative_path'	=> $file_relative_path,
				'commit'	=> $options['commit']
			)
		);

		/*
		 * Determine file-extension, and if it is not PHP, skip the file.
		 */
		$file_extension = vipgoci_file_extension_get(
			$file_relative_path
		);

		if ( 'php' !== $file_extension ) {
			vipgoci_log(
				'Skipping file, as it is not a PHP file',
				array(
					'file_relative_path' => $file_relative_path,
				)
			);

			continue;
		}


		$file_is_in_submodule =
			vipgoci_gitrepo_submodule_file_path_get(
				$options['local-git-repo'],
				$file_relative_path
			);

		/*
		 * Get correct GitHub commit URL
		 * for the file depending on if it is a submodule.
		 *
		 * Get relative file-path without submodule.
		 */
		list(
			$github_commit_url,
			$file_relative_path_without_submodule
		) = vipgocs_scan_file_submodule_config(
			$options,
			$file_relative_path,
			$file_is_in_submodule
		);

		/*
		 * Get path to file relative
		 * to repository.
		 *
		 * Replace '.'/ with '/'.
		 */
		$file_relative_path_dir = dirname(
			$file_relative_path
		);

		if ( '.' === $file_relative_path_dir ) {
			$file_relative_path_dir = '/';
		}

		/*
		 * If file empty or only has whitespacing, skip it.
		 */

		if (
			( true === $options['skip-empty-files'] )
			&&
			( true === vipgocs_file_empty_or_whitespace_only(
				$options['local-git-repo'] . DIRECTORY_SEPARATOR .
				$file_relative_path
			) )
		) {
			vipgoci_log(
				'Skipping file, as it is empty',
				array(
					'file_relative_path'	=> $file_relative_path,
				)
			);

			continue;
		}

		/*
		 * Scan a single file, possibly
		 * use cached results.
		 */
		$scan_single_file_result = vipgocs_scan_single_file(
			$options,
			$file_relative_path,
			$phpcscachedb_conn
		);

		/*
		 * Some problem with file, continue.
		 */
		if ( null === $scan_single_file_result ) {
			vipgoci_log(
				'Skipping file, PHPCS could not scan',
				array(
					'file_relative_path'	=> $file_relative_path,
				)
			);

			continue;
		}

		list(
			$file_results,
			$temp_file_name
		) = $scan_single_file_result;

		if ( ( false === $file_results ) || ( null === $file_results ) ) {
			vipgoci_log(
				'Skipping file, PHPCS could not scan',
				array(
					'file_relative_path'	=> $file_relative_path,
				)
			);

			continue;
		}

		/*
		 * Loop through each PHPCS message, add 'file_relative_path'
		 * and other information to each item.
		 */
		vipgocs_scan_file_add_info_to_item(
			$file_results,
			$temp_file_name,
			$file_relative_path,
			$github_commit_url,
			$file_is_in_submodule,
			$file_relative_path_without_submodule
		);

		$file_results_master = $file_results['file_issues_arr_master'];

		/*
		 * Figure out how to group
		 * issues, either by file or folder.
		 */
		if ( 'file' === $options['github-issue-group-by'] ) {
			$group_issues_by = $file_relative_path;
		}

		else if ( 'folder' === $options['github-issue-group-by'] ) {
			$group_issues_by = $file_relative_path_dir;
		}

		if ( ! isset( $all_results['files'][ $group_issues_by ]['messages'] ) ) {
			$all_results['files'][ $group_issues_by ]['messages'] = array();
		}

		$all_results['files'][ $group_issues_by ]['messages'] = array_merge(
			$all_results['files'][ $group_issues_by ]['messages'],
			$file_results_master['files'][ $temp_file_name ]['messages']
		);


		$all_results['warnings'] += $file_results_master['totals']['warnings'];
		$all_results['errors'] += $file_results_master['totals']['errors'];

		unset( $file_extension );
		unset( $file_results );
		unset( $file_results_master );
		unset( $temp_file_name );

		gc_collect_cycles();
	}

	return $all_results;
}

