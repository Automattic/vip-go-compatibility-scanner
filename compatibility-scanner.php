#!/usr/bin/env php
<?php

require_once( __DIR__ . '/github-api.php' );
require_once( __DIR__ . '/scan-files.php' );
require_once( __DIR__ . '/open-issues.php' );
require_once( __DIR__ . '/zendesk-api.php' );
require_once( __DIR__ . '/csv-data.php' );
require_once( __DIR__ . '/options.php' );
require_once( __DIR__ . '/zendesk-db.php' );
require_once( __DIR__ . '/utils.php' );


define( 'VIPGOCI_INCLUDED', true );

/*
 * main() -- prepare to do actual work,
 * invoke functions that do the work.
 */

function vipgocs_compatibility_scanner() {
	global $argv;

	$zendesk_db_conn = null;

	echo 'Initializing...' . PHP_EOL;

	/*
	 * Do some sanity checks on our
	 * environment.
	 */
	vipgocs_env_check();

	/*
	 * Log startup time
	 */
	$startup_time = time();

	/*
	 * Set up logging, etc.
	 */
	vipgocs_logging_setup();

	/*
	 * Get option-values
	 */
	$options = getopt(
		null,
		array(
			'help',
			'vipgoci-path:',
			'dry-run:',
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
			'github-issue-body-file:',
			'github-issue-assign:',
			'github-issue-group-by:',
			'zendesk-db:',
			'zendesk-access-username:', // For sanity-checking
		)
	);

	if ( isset(
		$options['zendesk-access-username'],
	) ) {
		echo 'The --zendesk-access-username, --zendesk-access-password, --zendesk-access-token, --zendesk-ticket-subject, --zendesk-ticket-body, etc. parameters are not supported by this utility anymore. You may want to start using the --zendesk-db parameter instead. See README for more information' . PHP_EOL;
		exit( 253 );
	}


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
			$options['local-git-repo'],
			$options['phpcs-path'],
			$options['phpcs-standard']
		) )
		||
		(
			( ! isset(
				$options['github-issue-body-file']
			) )
			&&
			( ! isset(
				$options['github-issue-body']
			) )
		)
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
			"\t" . '	--github-issue-title, --github-issue-body, --local-git-repo' . PHP_EOL .
			"\t" . '	--phpcs-path, --phpcs-standard are mandatory parameters' . PHP_EOL .
			PHP_EOL .
			"\t" . "Note that some parameters have a complementary '-file' parameter (see below)." . PHP_EOL .
			PHP_EOL .
			"\t" . '--vipgoci-path=STRING	            Path to were vip-go-ci lives, should be folder. ' . PHP_EOL .
			PHP_EOL .
			"\t" . '--dry-run=BOOL                      If set to true, will do scanning of code and then ' . PHP_EOL .
			"\t" . '                                    exit without submitting GitHub issues.' . PHP_EOL .
			PHP_EOL .
			"\t" . '--repo-owner=STRING	            Specify repository owner, can be an organization' . PHP_EOL .
			"\t" . '--repo-name=STRING                  Specify name of the repository' . PHP_EOL .
			"\t" . '--token=STRING		            The access-token to use to communicate with GitHub' . PHP_EOL .
			"\t" . '--github-labels=STRING	            Comma separated list of labels to attach to GitHub issues opened.' . PHP_EOL .
			"\t" . '--github-issue-title=STRING         Title to use for GitHub issues created.' . PHP_EOL .
			"\t" . '--github-issue-body=STRING          Body for each created GitHub issue. ' . PHP_EOL .
			"\t" . '                                    The option supports tokens that will be replaced with values: ' . PHP_EOL .
			"\t" . '				      * %error_msg%: Will be replaced with problems noted. ' . PHP_EOL .
			"\t" . '				      * %branch_name%: Will be replaced with name of current branch. ' . PHP_EOL .

			"\t" . '--github-issue-body-file=FILE       A file to read the content of --github-issue-body parameter' . PHP_EOL .
			"\t" . '                                    instead of specifying the parameter itself.' . PHP_EOL .
			"\t" . '--github-issue-assign=STRING        Assign specified admins as collaborators for each created issue' . PHP_EOL .
			"\t" . '				    -- outside, direct, or all.' . PHP_EOL .
			"\t" . '--github-issue-group-by=STRING      How to group the issues found; either by "file" or "folder".' . PHP_EOL .
			"\t" . '                                    "folder" is default.' . PHP_EOL .
			PHP_EOL .
			"\t" . '--local-git-repo=FILE	            The local git repository to use for direct access to code' . PHP_EOL .
			PHP_EOL .
			"\t" . '--phpcs-path=FILE                   Full path to PHPCS script' . PHP_EOL .
			"\t" . '--phpcs-standard=STRING             Specify which PHPCS standard to use' . PHP_EOL .
			"\t" . '--phpcs-runtime-set=STRING          Specify --runtime-set values passed on to PHPCS' . PHP_EOL .
			"\t" . '				    -- expected to be a comma-separated value string of ' . PHP_EOL .
			"\t" . '				    key-value pairs.' . PHP_EOL .
			"\t" . '				    For example: --phpcs-runtime-set="foo1 bar1, foo2,bar2"' . PHP_EOL .
			"\t" . '--phpcs-sniffs-exclude=ARRAY        Specify which sniffs to exclude from PHPCS scanning, ' . PHP_EOL .
			"\t" . '				    should be an array with items separated by commas. ' . PHP_EOL .
			PHP_EOL .
			"\t" . '--zendesk-db=FILE             File to store URLs to GitHub issues for later processing ' . PHP_EOL .
			"\t" . '                                    by the zendesk-tickets-create.php utility. ' . PHP_EOL .
			PHP_EOL;

		exit(253);
	}

	/*
	 * Load vip-go-ci
	 */

	vipgocs_vipgoci_load(
		$options,
		'vipgoci-path'
	);

	/*
	 * Parse --dry-run parameter
	 */
	vipgoci_option_bool_handle(
		$options,
		'dry-run',
		'false'
	);

	/*
	 * Read the parameter's '-file' counterpart from file if
	 * specified.
	 */
	foreach(
		array(
			'github-issue-body',
		) as $tmp_file_option
	) {
		vipgocs_file_option_process_complementary(
			$options,
			$tmp_file_option
		);
	}

	/*
	 * Parse rest of options
	 */
	vipgoci_option_file_handle(
		$options,
		'phpcs-path',
		null
	);

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

	if ( ! isset( $options['github-issue-group-by'] ) ) {
		$options['github-issue-group-by'] = 'folder';
	}

	else {
		$options['github-issue-group-by'] = trim(
			$options['github-issue-group-by']
		);

		if (
			( 'file' !== $options['github-issue-group-by'] ) &&
			( 'folder' !== $options['github-issue-group-by'] )
		) {
			vipgoci_sysexit(
				'Invalid argument provided to option --github-issue-group-by; should be "file" or "folder".'
			);
		}
	}

	if ( ! empty(
		$options['zendesk-db']
	) ) {
		// Create/open Zendesk DB 
		$zendesk_db_conn = vipgocs_zendesk_db_open(
			$options['zendesk-db']
		);
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

	/*
	 * Check if GitHub token is okay.
	 */
	$current_user_info = vipgoci_github_authenticated_user_get(
		$options['token']
	);

	if (
		( false === $current_user_info ) ||
		( ! isset( $current_user_info->login ) ) ||
		( empty( $current_user_info->login ) )
	) {
		vipgoci_sysexit(
			'Unable to get information about token-holder user from GitHub',
			array(
			),
			VIPGOCI_EXIT_GITHUB_PROBLEM
		);
	}

	else {
		vipgoci_log(
			'Got information about token-holder user from GitHub',
			array(
				'login' => $current_user_info->login,
				'html_url' => $current_user_info->html_url,
			)
		);
	}


	/*
	 * Check if Zendesk auth is ok
	 */

	vipgoci_log(
		'Starting up...',
		$options_clean
	);

	unset( $options_clean );

	sleep( 5 );

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

	$options['commit'] = $vipgoci_git_repo_head;

	/*
	 * Verify if we can find this commit
	 * in GitHub, and also verify that
	 * repo exists.
	 */
	$commit_info = vipgoci_github_fetch_commit_info(
		$options['repo-owner'],
		$options['repo-name'],
		$options['commit'],
		$options['token']
	);

	if ( ! isset( $commit_info->sha ) ) {
		vipgoci_sysexit(
			'Unable to fetch information about commit from GitHub; wrong parameter specified?',
			array(
				'repo-owner'	=> $options['repo-owner'],
				'repo-name'	=> $options['repo-name'],
				'commit'	=> $options['commit'],
			)
		);
	}

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

	$issue_statistics = vipgocs_open_issues(
		$options,
		$all_results,
		$git_branch,
		$options['github-labels'],
		$assignees,
		$options['dry-run']
	);

	/*
	 * If set to dry-run, exit here,
	 * closing Zendesk DB connection.
	 */

	if ( true === $options['dry-run'] ) {
		if ( ! empty(
			$zendesk_db_conn
		) ) {
			vipgocs_zendesk_db_close(
				$zendesk_db_conn
			);
		}

		vipgoci_log(
			sprintf(
				'Dry-run complete. Would open GitHub %d issues, found PHPCS %d issues',
				$issue_statistics[
					'github_issues_opened'
				],
				$issue_statistics[
					'phpcs_issues_found'
				]
			),
			$issue_statistics
		);

		exit ( 0 );
	}
	/*
	 * Construct links to issues,
	 * use labels to search.
	 */
	$github_issues_links = array();

	foreach(
		$options['github-labels'] as $label_name
	) {
		$github_issues_links[] =
			'https://github.com/' .
				rawurlencode( $options['repo-owner'] ) . '/' .
				rawurlencode( $options['repo-name'] ) . '/' .
				'labels/' .
				rawurlencode( $label_name );
	}


	if (
		( ! empty( $zendesk_db_conn ) )
		&&
		( $issue_statistics['github_issues_opened'] > 0 )
	) {
		vipgoci_log(
			'Logging created GitHub issues into Zendesk DB....',
			array(
				'zendesk-db' => $options['zendesk-db'],
			)
		);

		/*
		 * Write GitHub issues URL to DB
		 */
		vipgocs_zendesk_db_write_github_issue(
			$zendesk_db_conn,
			$options['repo-owner'],
			$options['repo-name'],
			$github_issues_links[0]
		);
	}

	else {
		vipgoci_log(
			'Note: Not logging GitHub ticket to Zendesk DB as not all requirements fulfilled'
		);
	}
	
	/*
	 * Get API rate limit usage.
	 */
	$github_api_rate_limit_usage =
		vipgoci_github_rate_limit_usage(
			$options['token']
		);

	/*
	 * Close connection to DB.
	 */
	if ( ! empty(
		$zendesk_db_conn
	) ) {
		vipgocs_zendesk_db_close(
			$zendesk_db_conn
		);
	}

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
			'run_time_seconds'	=> time() - $startup_time,
			'run_time_measurements' =>
				vipgoci_runtime_measure(
					VIPGOCI_RUNTIME_DUMP,
					null
				),
			'counters_report'	=> $counter_report,

			'github_api_rate_limit' =>
				$github_api_rate_limit_usage->resources->core,
		)
	);


	/*
	 * Provide a URL to newly created issues.
	 */
	echo PHP_EOL;

	echo 'Find newly created issues here: ' . PHP_EOL;

	foreach( $github_issues_links as $github_issue_link ) {
		echo "* " . $github_issue_link . PHP_EOL;
		
	}

	return 0;
}

if ( ( ! defined( 'VIPGOCS_UNIT_TESTING' ) ) || ( false === VIPGOCS_UNIT_TESTING ) ) {
	/*
	 * Main invocation function.
	 */
	$status = vipgocs_compatibility_scanner();

	exit( $status );
}
