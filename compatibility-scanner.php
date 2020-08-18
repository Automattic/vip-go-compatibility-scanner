#!/usr/bin/env php
<?php

define( 'VIPGOCI_INCLUDED', true );

/*
 * Get outside collaborators who are admins for
 * the repository.
 */
function vipgocs_get_repo_collaborators_admins(
	$options
) {
	$repo_outside_collaborators_admins =
		vipgoci_github_repo_collaborators_get(
			$options['repo-owner'],
			$options['repo-name'],
			$options['token'],
			$options['github-issue-assign'],
			array(
				'admin' => true
			)
		);

	$repo_outside_collaborators_admins = array_map(
		function( $user_item ) {
			return $user_item->login;
		},
		$repo_outside_collaborators_admins
	);

	vipgoci_log(
		'Found ' . $options['github-issue-assign'] . ' collaborators (admin) for repository',
		array(
			'collaborators' => $repo_outside_collaborators_admins,
		)
	);

	return $repo_outside_collaborators_admins;
}

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
	$all_results,
	$git_branch,
	$labels,
	$assignees = array()
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
			'repo-owner'			=> $options['repo-owner'],
			'repo-name'			=> $options['repo-name'],
			'github_issue_title'		=> $options['github-issue-title'],
			'github_issue_body'		=> $options['github-issue-body'],
			'issues'			=> $all_results,
			'assignees'			=> $assignees,
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
				'* <b>' . ucfirst(
						strtolower(
							$file_issue['type']
						)
					) . '</b>: ';

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
		$github_req_body =
			array(
				'title'		=>
					$options['github-issue-title'] . $file_name,
				'body'		=>
					str_replace(
						array(
							'%error_msg%',
							'%branch_name%',
						),
						array(
							PHP_EOL . $error_msg . PHP_EOL,
							$git_branch,
						),
						$options['github-issue-body'] . PHP_EOL
					)
			);

		if ( ! empty( $options['github-labels'] ) ) {
			$github_req_body['labels'] = $labels;
		}

		if ( ! empty( $assignees ) ) {
			$github_req_body['assignees'] = $assignees;
		}


		$res = vipgoci_github_post_url(
			$github_url,
			$github_req_body,
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

		sleep( 2 + rand( 0, 3 ));
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
	global $argv;

	echo 'Initializing...' . PHP_EOL;

	/*
	 * Log startup time
	 */
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
			'help',
			'vipgoci-path:',
			'repo-owner:',
			'repo-name:',
			'token:',
			'local-git-repo:',
			'phpcs-path:',
			'phpcs-standard:',
			'phpcs-runtime-set:',
			'github-labels:',
			'github-issue-title:',
			'github-issue-body:',
			'github-issue-assign:',
		)
	);

	/*
	 * Check if any required options are missing.
	 */
	if (
		( ! isset(
			$options['vipgoci-path'],
			$options['repo-owner'],
			$options['repo-name'],
			$options['token'],
			$options['github-issue-title'],
			$options['github-issue-body'],
			$options['local-git-repo'],
			$options['phpcs-path'],
			$options['phpcs-standard'],
		) )
		||
		(
			( isset( $options['help'] ) )
		)
	) {
		if ( ! isset( $options['help'] ) ) {
			echo 'Error: Essential parameter missing.' . PHP_EOL;
		}

		print 'Usage: ' . $argv[0] . PHP_EOL .
			"\t" . 'Options --vipgoci-path, --repo-owner, --repo-name, --token, ' . PHP_EOL .
			"\t" . '        --github-issue-title, --github-issue-body, --local-git-repo' . PHP_EOL .
			"\t" . '        --phpcs-path, --phpcs-standard are mandatory parameters' . PHP_EOL .
			PHP_EOL .
			"\t" . '--vipgoci-path=STRING             Path to were vip-go-ci lives, should be folder. ' . PHP_EOL .
			PHP_EOL .
			"\t" . '--repo-owner=STRING               Specify repository owner, can be an organization' . PHP_EOL .
			"\t" . '--repo-name=STRING                Specify name of the repository' . PHP_EOL .
			"\t" . '--token=STRING                    The access-token to use to communicate with GitHub' . PHP_EOL .
			"\t" . '--github-labels=STRING            Comma separated list of labels to attach to GitHub issues opened.' . PHP_EOL .
			"\t" . '--github-issue-title=STRING       Title to use for GitHub issues created.' . PHP_EOL .
			"\t" . '--github-issue-body=STRING        Body for each created GitHub issue. ' . PHP_EOL .
			"\t" . '                                  The option supports tokens that will be replaced with values: ' . PHP_EOL .
			"\t" . '                                      * %error_msg%: Will be replaced with problems noted. ' . PHP_EOL .
			"\t" . '                                      * %branch_name%: Will be replaced with name of current branch. ' . PHP_EOL .
			"\t" . '                                  %error_msg% in the body for list of problems.' . PHP_EOL .
			"\t" . '--github-issue-assign=STRING      Assign specified admins as collaborators for each created issue' . PHP_EOL .
			"\t" . '                                  -- outside, direct, or all.' . PHP_EOL .
			PHP_EOL .
			"\t" . '--local-git-repo=FILE             The local git repository to use for direct access to code' . PHP_EOL .
			PHP_EOL .
			"\t" . '--phpcs-path=FILE                 Full path to PHPCS script' . PHP_EOL .
			"\t" . '--phpcs-standard=STRING           Specify which PHPCS standard to use' . PHP_EOL .
			"\t" . '--phpcs-runtime-set=STRING        Specify --runtime-set values passed on to PHPCS' . PHP_EOL .
			"\t" . '                                  -- expected to be a comma-separated value string of ' . PHP_EOL .
			"\t" . '                                  key-value pairs.' . PHP_EOL .
			"\t" . '                                  For example: --phpcs-runtime-set="foo1 bar1, foo2,bar2"' . PHP_EOL .
			"\t" . '--phpcs-sniffs-exclude=ARRAY      Specify which sniffs to exclude from PHPCS scanning, ' . PHP_EOL .
			"\t" . '                                  should be an array with items separated by commas. ' . PHP_EOL .
			PHP_EOL;

		exit(253);
	}

	/*
	 * Check if the --vipgoci-path parameter is 
	 * invalid, should be a folder.
	 */
	if ( ! is_dir( $options['vipgoci-path'] ) ) {
		echo 'Path specified in --vipgoci-path is invalid, is not a directory' . PHP_EOL;
		exit(253);
	}

	/*
	 * Include vip-go-ci as a library.
	 */
	echo 'Attempting to include vip-go-ci...' . PHP_EOL;

	require_once(
		$options['vipgoci-path'] . '/main.php'
	);

	vipgoci_log(
		'Successfully included vip-go-ci'
	);

	/*
	 * Parse rest of options
	 */
	
	vipgoci_option_array_handle(
		$options,
		'phpcs-standard',
		array(),
		array(),
		',',
		false
	);

	vipgoci_option_phpcs_runtime_set(
		$options,
		'phpcs-runtime-set'
	);

	if ( empty( $options['phpcs-sniffs-exclude'] ) ) {
		$options['phpcs-sniffs-exclude'] = array();
	}

	else {
		vipgoci_option_array_handle(
			$options,
			'phpcs-sniffs-exclude',
			array(),
			array(),
			',',
			false
		);
	}

	$options['phpcs-severity'] = 1;

	if ( strpos(
		$options['github-issue-body'],
		'%error_msg%'
	) === false ) {
		vipgoci_sysexit(
			'--github-issue-body is missing %error_msg% string',
			array(
				'github-issue-body'	=> $options['github-issue-body'],
			)
		);
	}

	if ( empty( $options['github-labels'] ) ) {
		$options['github-labels'] = array();
	}

	else {
		vipgoci_option_array_handle(
			$options,
			'github-labels',
			array(),
			null,
			',',
			false
		);
	}

	if ( isset( $options['github-issue-assign'] ) ) {
		$options['github-issue-assign'] = trim(
			$options['github-issue-assign']
		);

		if (
			( 'all' !== $options['github-issue-assign'] ) &&
			( 'direct' !== $options['github-issue-assign'] ) &&
			( 'outside' !== $options['github-issue-assign'] )
		) {
			vipgoci_sysexit(
				'--github-issue-assign is assigned invalid parameter',
				array(
					'github-issue-assign' => $options['github-issue-assign']
				)
			);
		}
	}

	/*
	 * Print cleaned option-values.
	 */
	
	$options_clean = vipgoci_options_sensitive_clean(
		null,
		array(
			'token',
		)
	);

	$options_clean = vipgoci_options_sensitive_clean(
		$options
	);

	vipgoci_log(
		'Starting up...',
		$options_clean
	);

	unset( $options_clean );

	/*
	 * Get the HEAD commit
	 * from the local repository -- this
	 * would be the latest commit. Used
	 * by the PHPCS scanning function
	 * and elsewhere.
	 */

	$vipgoci_git_repo_head = vipgoci_gitrepo_get_head(
		$options['local-git-repo']
	);

	$vipgoci_git_repo_head = 
		str_replace( '\'', '', $vipgoci_git_repo_head );

	$options['commit'] = $vipgoci_git_repo_head;

	/*
	 * Main processing starts.
	 */

	vipgoci_log(
		'Starting processing...'
	);

	/*
	 * Scan all files in the Git repository,
	 * get results.
	 */
	$all_results = vipgocs_scan_files(
		$options
	);

	/*
	 * Get outside collaborators for the
	 * the repository who are admin, assign
	 * them to the issues created.
	 */

	if ( ! empty( $options['github-issue-assign'] ) ) {
		$assignees = vipgocs_get_repo_collaborators_admins(
			$options
		);
	}

	else {
		$assignees = array();
	}

	/*
	 * Get git branch being used.
	 */
	$git_branch = vipgoci_gitrepo_branch_current_get(
		$options['local-git-repo']
	);

	if ( empty( $git_branch ) ) {
		$git_branch = '[UNKNOWN]';
	}

	/*
	 * Process each file which as any issues
	 * tagged against it.
	 */

	vipgocs_open_issues(
		$options,
		$all_results,
		$git_branch,
		$options['github-labels'],
		$assignees
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


	/*
	 * Log information and return.
	 */
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

	/*
	 * Provide a URL to newly created issues.
	 */
	echo PHP_EOL;

	foreach(
		$options['github-labels'] as $label_name
	) {
		echo 'Find newly created issues here: ' . 
				'https://github.com/' .
					rawurlencode( $options['repo-owner'] ) . '/' .
					rawurlencode( $options['repo-name'] ) . '/' .
					'labels/' .
					rawurlencode( $label_name ) .
		PHP_EOL;
	}

	return 0;
}

/*
 * Main invocation function.
 */
$status = vipgocs_compatibility_scanner();

exit( $status );

