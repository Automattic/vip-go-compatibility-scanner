<?php

/*
 * Scan all PHP files in the
 * repository using the options
 * given, return the issues found
 * in one array.
 */
function vipgocs_scan_files(
	$options
) {
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
	foreach( $tree as $file_path ) {
		vipgoci_log(
			'Looking at file ' . $file_path,
			array(
				'repo-owner'	=> $options['repo-owner'],
				'repo-name'	=> $options['repo-name'],
				'file_path'	=> $file_path,
				'commit'	=> $options['commit']
			)
		);

		/*
		 * Determine file-extension, and if it is not PHP, skip the file.
		 */
		$file_extension = vipgoci_file_extension_get(
			$file_path
		);

		if ( 'php' !== $file_extension ) {
			vipgoci_log(
				'Skipping file, as it is not a PHP file',
				array(
					'file_path' => $file_path,
				)
			);

			continue;
		}

		/*
		 * Get path to file relative
		 * to repository. 
		 *
		 * Replace '.'/ with '/'.
		 */
		$file_path_dir = dirname(
			$file_path
		);

		if ( '.' === $file_path_dir ) {
			$file_path_dir = '/';
		}


		/*
		 * Scan a single PHP file, and process the results
		 * -- i.e., collect the information and save it.
		 */
		$file_results = vipgoci_phpcs_scan_single_file(
			$options,
			$file_path
		);

		$temp_file_name = $file_results['temp_file_name'];

		/*
		 * Loop through each PHPCS message, add 'file_path'
		 * attribute with actual path to file (relative
		 * to repository).
		 */
		$file_results
			['file_issues_arr_master']
			['files']
			[ $temp_file_name ]
			['messages']
		= array_map(
			function( $msg ) use ( $file_path ) {
				$msg['file_path'] = $file_path;

				return $msg;
			},
			$file_results
				['file_issues_arr_master']
				['files']
				[ $temp_file_name ]
				['messages']
		);

		$file_results_master = $file_results['file_issues_arr_master'];


		if ( ! isset( $all_results['files'][ $file_path_dir ]['messages'] ) ) {
			$all_results['files'][ $file_path_dir ]['messages'] = array();
		}

		$all_results['files'][ $file_path_dir ]['messages'] = array_merge(
			$all_results['files'][ $file_path_dir ]['messages'],
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

