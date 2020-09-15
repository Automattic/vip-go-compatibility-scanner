#!/usr/bin/env php
<?php

require_once( __DIR__ . '/github-api.php' );
require_once( __DIR__ . '/scan-files.php' );
require_once( __DIR__ . '/open-issues.php' );
require_once( __DIR__ . '/zendesk-api.php' );
require_once( __DIR__ . '/csv-data.php' );


define( 'VIPGOCI_INCLUDED', true );

/*
 * main() -- prepare to do actual work,
 * invoke functions that do the work.
 */

function vipgocs_compatibility_scanner() {
	global $argv;

	echo 'Initializing...' . PHP_EOL;

	/*
	 * Check if we are running on PHP 7.3 or later.
	 */
	if ( version_compare(
		phpversion(),
		'7.3.0'
	) < 0 ) {
		echo 'Error: PHP 7.3 is required as a minimum.';
		exit( 251 ); /* System problem */
	}

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
			'github-issue-group-by:',
			'zendesk-subdomain:',
			'zendesk-access-username:',
			'zendesk-access-token:',
			'zendesk-access-password:',
			'zendesk-ticket-subject:',
			'zendesk-ticket-body:',
			'zendesk-ticket-tags:',
			'zendesk-csv-data-path:',
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
			$options['phpcs-standard']
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
			"\t" . '	--github-issue-title, --github-issue-body, --local-git-repo' . PHP_EOL .
			"\t" . '	--phpcs-path, --phpcs-standard are mandatory parameters' . PHP_EOL .
			PHP_EOL .
			"\t" . '--vipgoci-path=STRING	            Path to were vip-go-ci lives, should be folder. ' . PHP_EOL .
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
			"\t" . '--zendesk-subdomain=STRING          Subdomain to use when communicating with Zendesk. ' . PHP_EOL .
			"\t" . '--zendesk-access-username=STRING    Username of the Zendesk user to use.' . PHP_EOL .
			"\t" . '--zendesk-access-token=STRING       Access token to use when communicating with Zendesk REST API. ' . PHP_EOL .
			"\t" . '--zendesk-access-password=STRING    Password to use when communicating with Zendesk REST API. ' . PHP_EOL .
			"\t" . '                                    Use if token is not an option.' . PHP_EOL .
			"\t" . '--zendesk-ticket-subject=STRING     Subject to use for Zendesk tickets.' . PHP_EOL .
			"\t" . '--zendesk-ticket-body=STRING        Body of Zendesk ticket. Markdown is supported. ' . PHP_EOL .
			"\t" . '                                    Also certain strings are replaced with other values: ' . PHP_EOL .
			"\t" . '                                     * String %github_issues_link% will be replaced' . PHP_EOL .
			"\t" . '                                       with link to GitHub issues created; the link' . PHP_EOL .
			"\t" . '                                       will be the first label specified in --github--label' . PHP_EOL .
			"\t" . '                                     * %linebreak% will be replaced with \n.' . PHP_EOL .
			"\t" . '--zendesk-ticket-tags=STRING        Tags to assign to Zendesk ticket. Comma separated. ' . PHP_EOL .
			"\t" . '--zendesk-csv-data-path=PATH        CSV data to use for Zendesk ticket creation. The ' . PHP_EOL .
			"\t" . '                                    data is used to pair a user\'s email address to repository.' . PHP_EOL .
			"\t" . '                                    The file should have two fields: client_email and source_repo' . PHP_EOL .
			"\t" . '                                    -- first line of the file should be columns.' . PHP_EOL .
			"\t" . '                                    Valid columns are: client_email, source_repo ' . PHP_EOL .
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

	if ( ! is_file( $options['vipgoci-path'] . '/main.php' ) ) {
		echo 'No main.php found in --vipgoci-path; is it a valid vip-go-ci installation?' . PHP_EOL;
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

	if ( empty( $options['zendesk-ticket-tags'] ) ) {
		$options['zendesk-ticket-tags'] = array();
	}

	else {
		vipgoci_option_array_handle(
			$options,
			'zendesk-ticket-tags',
			array(),
			null,
			',',
			false
		);
	}

	/*
	 * Print cleaned option-values.
	 */

	$options_clean = vipgoci_options_sensitive_clean(
		null,
		array(
			'token',
			'zendesk-access-username',
			'zendesk-access-password',
			'zendesk-access-token',
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

	if ( null !== vipgocs_zendesk_prepare_auth_fields(
		$options
	) ) {
		if ( true !== vipgocs_zendesk_check_auth(
			$options
		) ) {
			vipgoci_sysexit(
				'Authentication with Zendesk failed',
				array(
					'zendesk-access-username' =>
						@$options['zendesk-access-username'],

					'zendesk-subdomain' =>
						@$options['zendesk-subdomain'],
				)
			);
		}

		else {
			vipgoci_log(
				'Authentication with Zendesk successful'
			);
		}
	}

	/*
	 * Read in CSV data, if --zendesk-csv-data is specified
	 */

	$zendesk_csv_data = array();
	$zendesk_requestee_email = null;

	if ( isset( $options['zendesk-csv-data-path'] ) ) {
		$zendesk_csv_data = vipgocs_csv_parse_data(
			$options['zendesk-csv-data-path']
		);

		if ( empty( $zendesk_csv_data ) ) {
			vipgoci_sysexit(
				'Read CSV file, but no data seems to have been available, or data is invalid',
				array(
					'zendesk-csv-data-path'		=> $options['zendesk-csv-data-path'],
					'csv-data-count'		=> count( $zendesk_csv_data ),
				)
			);
		}

		$zendesk_requestee_email = vipgocs_csv_get_email_for_repo(
			$zendesk_csv_data,
			$options['repo-owner'],
			$options['repo-name']
		);

		$log_msg = '';

		if ( ! empty( $zendesk_requestee_email ) ) {
			$log_msg = 'Got email for Zendesk ticket creation that matches repository owner and name';
		}

		else {
			$log_msg = 'Got no email for Zendesk ticket creation that matches repository owner and name; will not be created';
		}

		vipgoci_log(
			$log_msg,
			array(
				'zendesk_requestee_email'	=> $zendesk_requestee_email,
				'repo-owner'			=> $options['repo-owner'],
				'repo-name'			=> $options['repo-name'],
				'zendesk-csv-data-path'		=> $options['zendesk-csv-data-path'],
				'csv-data-count'		=> count( $zendesk_csv_data ),
			)
		);
	}


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

	$vipgoci_git_repo_head =
		str_replace( '\'', '', $vipgoci_git_repo_head );

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
		$assignees
	);

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

	/*
	 * If configured to create Zendesk
	 * tickets and we did open issues,
	 * do create tickets.
	 */

	$zendesk_ticket_url = null;

	if (
		( null !==
			vipgocs_zendesk_prepare_auth_fields( $options )
		)
		&&
		( ! empty(
			$options['zendesk-subdomain']
		) )
		&&
		( ! empty(
			$options['zendesk-ticket-subject']
		) )
		&&
		( ! empty(
			$options['zendesk-ticket-body']
		) )
		&&
		( ! empty(
			$zendesk_requestee_email
		) )
		&&
		( $issue_statistics['issues_opened'] > 0 )
	) {
		$zendesk_ticket_url = vipgocs_zendesk_open_ticket(
			$options,
			$zendesk_requestee_email,
			$github_issues_links
		);
	}

	else {
		vipgoci_log(
			'Note: Not opening Zendesk ticket as not all requirements fulfilled'
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

	if ( ! empty( $zendesk_ticket_url ) ) {
		echo 'Find Zendesk ticket here: ' . $zendesk_ticket_url . PHP_EOL;
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
