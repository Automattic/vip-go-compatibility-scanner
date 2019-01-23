#!/usr/bin/env php
<?php

$vipgoci_root = '/home/teamcity-buildagent/vip-go-ci-tools/vip-go-ci';

require_once("$vipgoci_root/defines.php");
require_once("$vipgoci_root/git-repo.php");
require_once("$vipgoci_root/github-api.php");
require_once("$vipgoci_root/phpcs-scan.php");
require_once("$vipgoci_root/statistics.php");
require_once("$vipgoci_root/misc.php");

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
		$file_extension = vipgoci_file_extension(
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
		 * Scan a single PHP file, and process the results
		 * -- i.e., collect the information and save it.
		 */
		$file_results = vipgoci_phpcs_scan_single_file(
			$options,
			$file_path
		);

		$temp_file_name = $file_results['temp_file_name'];
		$file_results_master = $file_results['file_issues_arr_master'];
	

		$all_results['files'][ $file_path ]['messages'] =
				$file_results_master['files'][ $temp_file_name ]['messages'];

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

/*
 * Open up issues on GitHub for each
 * problem we found when scanning.
 */
function vipgocs_open_issues(
	$options,
	$all_results
) {

	/*
	 * Keep some statistics on what
	 * we do.
	 */
	$issue_statistics = array(
		'issues_found'	=> 0,
		'issues_opened'	=> 0,
	);

	vipgoci_log(
		'Opening up issues on GitHub for problems found',
		array(
			'repo-owner'	=> $options['repo-owner'],
			'repo-name'	=> $options['repo-name'],
			'issues'	=> $all_results,
		)
	);

	foreach(
		$all_results['files'] as
			$file_name => $file_issues
	) {

		/*
		 * No issues, nothing to do.
		 */
		if ( empty( $file_issues['messages'] ) ) {
			continue;
		}


		/*
		 * Compose string to post as body of an issue.
		 */
		$error_msg = '';

		foreach( $file_issues['messages'] as $file_issue ) {
			$error_msg .=
				'* ' . ucfirst(
						strtolower(
							$file_issue['type']
						)
					) . ': ';

			$error_msg .= $file_issue['message'] . ' ';

			$error_msg .= 'https://github.com/' .
				$options['repo-owner'] . '/' .
				$options['repo-name'] . '/' .
				'blob/' .
				$options['commit'] . '/' .
				$file_name .
				'#L' . $file_issue['line'];

			$error_msg .= PHP_EOL . PHP_EOL;

			$issue_statistics['issues_found']++;
		}

		$github_url =
			VIPGOCI_GITHUB_BASE_URL . '/' .
			'repos/' .
			rawurlencode( $options['repo-owner'] ) . '/' .
			rawurlencode( $options['repo-name'] ) . '/' .
			'issues';

		/*
		 * Create issue on GitHub.
		 */
		$res = vipgoci_github_post_url(
			$github_url,
			array(
				'title'		=> 'PHP compatibility issues found in ' . $file_name,
				'body'		=> 'The following issues were found when scanning for PHP compatibility issues: ' . PHP_EOL .
							$error_msg,
				'labels'	=> array( 'PHP Compatibility Issues'),
			),
			$options['token']
		);

		$issue_statistics['issues_opened']++;

		/*
		 * Clean up and return.
		 */
		unset( $github_url );
		unset( $res );
		unset( $file_name );
		unset( $file_issues );
		unset( $error_msg );
		
		gc_collect_cycles();

		sleep( 2 + rand(0, 3 ));
	}

	vipgoci_log(
		'Finished opening up issues on GitHub',
		array(
			'issue_statistics' => $issue_statistics,
		)
	);
}

/*
 * main() -- prepare to do actual work,
 * invoke functions that do the work.
 */

function vipgocs_compatibility_scanner() {
	vipgoci_log(
		'Initializing...',
		array()
	);

	$startup_time = time();

	/*
	 * Report any errors to the user.
	 */
	ini_set( 'error_log', '' );

	error_reporting( E_ALL );
	ini_set( 'display_errors', 'on' );


	/*
	 * Get option-values
	 */
	$options = getopt(
		null,
		array(
			'repo-owner:',
			'repo-name:',
			'token:',
			'local-git-repo:',
			'phpcs-path:',
			'phpcs-standard:',
			'phpcs-runtime-set:',
		)
	);

	/* FIXME: These should be options */
	$options['phpcs-runtime-set'] = array(
		array('testVersion', '7.2-')
	);

	$options['phpcs-sniffs-exclude'] = array();
	$options['phpcs-severity'] = 1;

	/*
	 * Check if any options are missing.
	 */
	if ( ! isset(
		$options['repo-owner'],
		$options['repo-name'],
		$options['token'],
		$options['local-git-repo'],
		$options['phpcs-path'],
		$options['phpcs-standard'],
		$options['phpcs-runtime-set']
	) ) {
		vipgoci_sysexit(
			'Essential parameter missing',
			array()
		);
	}

	/*
	 * Print cleaned option-values.
	 */
	$options_clean = $options;
	$options_clean['token'] = '***';

	vipgoci_log(
		'Starting up...',
		$options_clean
	);

	unset( $options_clean );

	/*
	 * A bit a hack; get the HEAD commit
	 * from the local repository -- this
	 * would be the latest commit.
	 */

	$vipgoci_git_repo_head = vipgoci_git_repo_get_head(
		$options['local-git-repo']
	);

	$vipgoci_git_repo_head = 
		str_replace( '\'', '', $vipgoci_git_repo_head );

	$options['commit'] = $vipgoci_git_repo_head;

	/*
	 * Scan all files in the Git repository,
	 * get results.
	 */
	$all_results = vipgocs_scan_files( $options );

	/*
	 * Process each file which as any issues
	 * tagged against it.
	 */

	vipgocs_open_issues(
		$options,
		$all_results
	);

	/*
	 * Get API rate limit usage.
	 */
	$github_api_rate_limit_usage =
		vipgoci_github_rate_limit_usage(
			$options['token']
		);

	/*
	 * Collect counter-information.
	 */
	$counter_report = vipgoci_counter_report(
		VIPGOCI_COUNTERS_DUMP,
		null,
		null
	);

	vipgoci_log(
		'Complete, shutting down...',
		array(
			'repo-owner'	=> $options['repo-owner'],
			'repo-name'	=> $options['repo-name'],
			'run_time_seconds'      => time() - $startup_time,
			'run_time_measurements' =>
				vipgoci_runtime_measure(
					VIPGOCI_RUNTIME_DUMP,
					null
				),
			'counters_report'       => $counter_report,

			'github_api_rate_limit' =>
				$github_api_rate_limit_usage->resources->core,
		)
	);

	return 0;
}

$status = vipgocs_compatibility_scanner();

exit( $status );

