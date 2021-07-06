#!/usr/bin/env php
<?php

require_once( __DIR__ . '/github-api.php' );
require_once( __DIR__ . '/scan-files.php' );
require_once( __DIR__ . '/open-issues.php' );
require_once( __DIR__ . '/zendesk-api.php' );
require_once( __DIR__ . '/csv-data.php' );
require_once( __DIR__ . '/options.php' );
require_once( __DIR__ . '/zendesk-db.php');
require_once( __DIR__ . '/utils.php' );


define( 'VIPGOCI_INCLUDED', true );


/*
 * main() -- prepare to do actual work,
 * invoke functions that do the work.
 */

function vipgocs_zendesk_tickets_create() :int {
	global $argv;

	echo 'Initializing...' . PHP_EOL;

	/*
	 * Do sanity checks on our environment.
	 */
	vipgocs_env_check();

	/*
	 * Log startup time
	 */
	$startup_time = time();

	/*
	 * Report any errors to the user.
	 */
	vipgocs_logging_setup();

	/*
	 * Get option-values
	 */
	$options = getopt(
		null,
		array(
			'help',
			'dry-run:',
			'vipgoci-path:',
			'zendesk-subdomain:',
			'zendesk-access-username:',
			'zendesk-access-token:',
			'zendesk-access-password:',
			'zendesk-ticket-subject:',
			'zendesk-ticket-body:',
			'zendesk-ticket-body-file:',
			'zendesk-ticket-tags:',
			'zendesk-ticket-group-id:',
			'zendesk-ticket-status:',
			'zendesk-csv-data-path:',
			'zendesk-db:'
		)
	);

	/*
	 * Check if any required options are missing.
	 */
	if (
		( ! isset(
			$options['vipgoci-path'],
			$options['zendesk-subdomain'],
			$options['zendesk-access-username'],
			$options['zendesk-ticket-subject'],
			$options['zendesk-csv-data-path'],
			$options['zendesk-db']
		) )
		||
		(
			( ! isset(
				$options['zendesk-ticket-body-file']
			) )
			&&
			( ! isset(
				$options['zendesk-ticket-body']
			) )
		)
		||
		(
			( ! isset(
				$options['zendesk-access-token']
			) )
			&&
			( ! isset(
				$options['zendesk-access-password']
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
			"\t" . 'Options --vipgoci-path, --zendesk-access-username, --zendesk-access-token ' . PHP_EOL .
			"\t" . '        ( or --zendesk-access-password ), --zendesk-ticket-subject, --zendesk-ticket-body, ' . PHP_EOL .
			"\t" . '	--zendesk-csv-data-path and --zendesk-db are mandatory parameters' . PHP_EOL .
			PHP_EOL .
			"\t" . "Note that some parameters have a complementary '-file' parameter (see below)." . PHP_EOL .
			PHP_EOL .
			"\t" . '--vipgoci-path=STRING	            Path to were vip-go-ci lives, should be folder. ' . PHP_EOL .
			PHP_EOL .
			"\t" . '--dry-run=BOOL                      If set to true, will not open Zendesk tickets, ' . PHP_EOL .
			"\t" . '                                    but report which ones would be opened. ' . PHP_EOL .
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
			"\t" . '                                       with link to GitHub issues' . PHP_EOL .
			"\t" . '                                     * %linebreak% will be replaced with \n.' . PHP_EOL .
			"\t" . '--zendesk-ticket-body-file=FILE     A file to read the content of --zendesk-ticket-body from' . PHP_EOL .
			"\t" . '                                    instead of specifying the parameter itself.' . PHP_EOL .
			"\t" . '--zendesk-ticket-tags=STRING        Tags to assign to Zendesk tickets. Comma separated. ' . PHP_EOL .
			"\t" . '--zendesk-ticket-group-id=NUMBER    Zendesk group ID to assign tickets to. ' . PHP_EOL .
			"\t" . '--zendesk-ticket-status=STRING      Status of the Zendesk tickets. Defaults to "New" ' . PHP_EOL .
			"\t" . '--zendesk-csv-data-path=PATH        CSV data to use for Zendesk ticket creation. The ' . PHP_EOL .
			"\t" . '                                    data is used to pair a user\'s email address to repository.' . PHP_EOL .
			"\t" . '                                    The file should have two fields: client_email and source_repo' . PHP_EOL .
			"\t" . '                                    -- first line of the file should be columns.' . PHP_EOL .
			"\t" . '                                    Valid columns are: client_email, source_repo ' . PHP_EOL .
			"\t" . '--zendesk-db=FILE                   File with URLs to GitHub issues for processing ' . PHP_EOL .
			"\t" . '                                    by this utility. ' . PHP_EOL .
			PHP_EOL;

		exit(253);
	}


	/*
	 * Attempt to load vip-go-ci
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
			'zendesk-ticket-body',
		) as $tmp_file_option
	) {
		vipgocs_file_option_process_complementary(
			$options,
			$tmp_file_option
		);
	}

	if ( strpos(
		$options['zendesk-ticket-body'],
		'%github_issues_link%'
	) === false ) {
		vipgoci_sysexit(
			'--zendesk-ticket-body does not contain %github_issues_link%.',
			array(
				'zendesk-ticket-body' => $options['zendesk-ticket-body'],
			)
		);
	}

	/*
	 * Parse rest of options
	 */

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

	if ( ! empty( $options['zendesk-ticket-group-id'] ) ) {
		$options['zendesk-ticket-group-id'] = trim(
			$options['zendesk-ticket-group-id']
		);

		if ( ! is_numeric(
			$options['zendesk-ticket-group-id']
		) ) {
			vipgoci_syexit(
				'Invalid argument provided to option --zendesk-ticket-group-id; should be an integer',
				array(
					'zendesk-ticket-group-id' =>
						$options['zendesk-ticket-group-id']
				)
			);
		}

		$options['zendesk-ticket-group-id'] =
			(int) $options['zendesk-ticket-group-id'];
	}

	else {
		$options['zendesk-ticket-group-id'] = null;
	}

	$valid_ticket_statuses = array(
		'new', 'open', 'pending', 'hold', 'solved', 'closed'
	);

	if ( empty( $options['zendesk-ticket-status'] ) ) {
		$options['zendesk-ticket-status'] = 'new';
	}

	else {
		$options['zendesk-ticket-status'] = strtolower( trim(
			$options['zendesk-ticket-status']
		) );

		if ( ! in_array(
			$options['zendesk-ticket-status'],
			$valid_ticket_statuses
		) ) {
			vipgoci_sysexit(
				'Invalid argument provided to option --zendesk-ticket-status; should be one of: ' .
					join( ', ', $valid_ticket_statuses )
			);
		}
	}

	/*
	 * Check if Zendesk auth is ok
	 */

	if ( null === vipgocs_zendesk_prepare_auth_fields(
		$options
	) ) {
		vipgoci_sysexit(
			'Unable to submit tickets to Zendesk, parameters missing'
		);
	}

	else {
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
	 * Open ZendeskDB
	 */
	$zendesk_db_conn = vipgocs_zendesk_db_open(
		$options['zendesk-db']
	);

	/*
	 * Print cleaned option-values.
	 */

	vipgoci_options_sensitive_clean(
		null,
		array(
			'zendesk-access-username',
			'zendesk-access-password',
			'zendesk-access-token',
		)
	);

	$options_clean = vipgoci_options_sensitive_clean(
		$options
	);


	/*
	 * Main processing starts.
	 */

	vipgoci_log(
		'Starting up...',
		$options_clean
	);

	unset( $options_clean );

	/*
	 * Read in CSV data
	 */

	$zendesk_csv_data = array();
	$zendesk_requestee_email = null;

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

	$zendesk_ticket_urls = array();

	/*
	 * Get GitHub issues from DB.
	 */
	$zendesk_db_issues = vipgocs_zendesk_db_get_github_issues(
		$zendesk_db_conn
	);

	$zendesk_tickets_arr = array();

	/*
	 * Loop through each repo-owner / repo-name combination
	 * and merge GitHub URLs along with other data into
	 * an array.
	 */
	foreach (
		array_keys(
			$zendesk_db_issues
		) as $zendesk_db_key
	) {
		$tmp_repo_owner = $zendesk_db_issues[ $zendesk_db_key ]['repo_owner'];
		$tmp_repo_name = $zendesk_db_issues[ $zendesk_db_key ]['repo_name'];
		$github_issues_links = $zendesk_db_issues[ $zendesk_db_key ]['github_issues_urls'];

		$zendesk_requestee_email = vipgocs_csv_get_email_for_repo(
			$zendesk_csv_data,
			$tmp_repo_owner,
			$tmp_repo_name
		);

		$log_msg = '';

		if ( ! empty( $zendesk_requestee_email ) ) {
			$log_msg = 'Got email for Zendesk ticket creation that matches repository owner and name';
		}

		else {
			$log_msg = 'Got no email for Zendesk ticket creation that matches repository owner and name; will not be created';
			continue;
		}

		vipgoci_log(
			$log_msg,
			array(
				'zendesk_requestee_email'	=> $zendesk_requestee_email,
				'repo-owner'			=> $tmp_repo_owner,
				'repo-name'			=> $tmp_repo_name,
				'zendesk-csv-data-path'		=> $options['zendesk-csv-data-path'],
				'csv-data-count'		=> count( $zendesk_csv_data ),
			)
		);

		if ( ! isset(
			$zendesk_tickets_arr[ $zendesk_requestee_email ]
		) ) {
			$zendesk_tickets_arr[ $zendesk_requestee_email ] = array(
			);
		}

		$zendesk_tickets_arr[ $zendesk_requestee_email ][] = array(
			'github_issues'		=> $github_issues_links,
			'repo_owner'		=> $tmp_repo_owner,
			'repo_name'		=> $tmp_repo_name,
		);
	}

	/*
	 * Open Zendesk tickets; one ticket per email, make
	 * sure all URLs for the user are in the ticket.
	 */
	foreach(
		array_keys(
			$zendesk_tickets_arr
		) as $zendesk_requestee_email
	) {
		$zendesk_requestee_github_links = array();

		foreach(
			$zendesk_tickets_arr[ $zendesk_requestee_email ]
				as $tmp_item
		) {
			$zendesk_requestee_github_links = array_merge(
				$zendesk_requestee_github_links,
				$tmp_item['github_issues']
			);
		}

		unset( $tmp_item );

		if ( true === $options['dry-run'] ) {
			vipgoci_log(
				sprintf(
					'Would open up Zendesk ticket for user %s with links',
						$zendesk_requestee_email
				),
				array(
					'github_links'	=> $zendesk_requestee_github_links,
				)
			);

			continue;
		}

		$zendesk_ticket_url_item = vipgocs_zendesk_open_ticket(
			$options,
			$zendesk_requestee_email,
			$zendesk_requestee_github_links
		);

		if ( ! empty(
			$zendesk_ticket_url_item
		) ) {
			$zendesk_ticket_urls[] =
				$zendesk_ticket_url_item;

			foreach(
				$zendesk_tickets_arr[ $zendesk_requestee_email ]
				as $tmp_item
			) {
				foreach( $tmp_item['github_issues'] as $tmp_github_url ) {
					vipgocs_zendesk_db_delete_github_issue(
						$zendesk_db_conn,
						$tmp_item['repo_owner'],
						$tmp_item['repo_name'],
						$tmp_github_url
					);
				}
			}

			unset( $tmp_item );
		}
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
			'run_time_seconds'	=> time() - $startup_time,
			'run_time_measurements' =>
				vipgoci_runtime_measure(
					VIPGOCI_RUNTIME_DUMP,
					null
				),
			'counters_report'	=> $counter_report,
		)
	);

	if ( empty( $zendesk_ticket_urls ) ) {
		echo 'No zendesk tickets were created' . PHP_EOL;
	}

	else {
		echo 'Find Zendesk ticket(s) here: ' . PHP_EOL;

		foreach( $zendesk_ticket_urls as $zendesk_ticket_url_item ) {
			echo ' * ' . $zendesk_ticket_url_item . PHP_EOL;
		}
	}

	return 0;
}

if ( ( ! defined( 'VIPGOCS_UNIT_TESTING' ) ) || ( false === VIPGOCS_UNIT_TESTING ) ) {
	/*
	 * Main invocation function.
	 */
	$status = vipgocs_zendesk_tickets_create();

	exit( $status );
}
