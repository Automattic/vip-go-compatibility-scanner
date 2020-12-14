<?php

/*
 * Open up issues on GitHub for each
 * problem we found when scanning.
 */
function vipgocs_open_issues(
	$options,
	$all_results,
	$git_branch,
	$labels,
	$assignees = array(),
	$emulate_only = false
) {

	/*
	 * Keep some statistics on what
	 * we do.
	 */
	$issue_statistics = array(
		'phpcs_issues_found'	=> 0,
		'github_issues_opened'	=> 0,
	);

	vipgoci_log(
		( $emulate_only ? 'Emulating o' : 'O' ) . 'pening up issues on GitHub for problems found',
		array(
			'repo-owner'			=> $options['repo-owner'],
			'repo-name'			=> $options['repo-name'],
			'github_issue_title'		=> $options['github-issue-title'],
			'github_issue_body'		=> $options['github-issue-body'],
			'issues'			=> $all_results,
			'assignees'			=> $assignees,
			'emulate_only'			=> $emulate_only,
		)
	);

	foreach(
		$all_results['files'] as
			$file_path => $file_issues
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
			/*
			 * Construct link to file
			 */
			$error_msg_link =
				$file_issue['github_commit_url'] . '/';

			if ( false === $file_issue['file_is_in_submodule'] ) {
				$error_msg_link .= $file_issue['file_path'];
			}

			else {
				$error_msg_link .= $file_issue['file_path_without_submodule'];
			}

			$error_msg_link .=
				'#L' . $file_issue['line'];

			/*
			 * Construct error message
			 */
			$error_msg .=
				'* <b>' .
					ucfirst ( strtolower(
						$file_issue['type']
					) );

			if ( 'folder' === $options['github-issue-group-by'] ) {
				$error_msg .= ' in ' .
					$file_issue['file_path'];
			}

			$error_msg .= '</b>: ' .
				$file_issue['message'];

			
			if ( false === $file_issue['file_is_in_submodule'] ) {
				$error_msg .=
					' ' .
					$error_msg_link;
			}

			else {
				$error_msg .=
					'. In submodule, <a href="' . $error_msg_link . '">here</a>.';
			}

			$error_msg .=
				PHP_EOL . PHP_EOL;

			$issue_statistics['phpcs_issues_found']++;
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
					$options['github-issue-title'] . $file_path,
				'body'		=>
					str_replace(
						array(
							'%error_msg%',
							'%branch_name%',
						),
						array(
							PHP_EOL . $error_msg . PHP_EOL . PHP_EOL,
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

		if ( false === $emulate_only ) {
			$res = vipgoci_github_post_url(
				$github_url,
				$github_req_body,
				$options['token']
			);
		}

		$issue_statistics['github_issues_opened']++;

		/*
		 * Clean up and return.
		 */
		unset( $github_url );
		unset( $res );
		unset( $file_path );
		unset( $file_issues );
		unset( $error_msg );
		
		gc_collect_cycles();

		sleep( 2 + rand( 0, 3 ));
	}

	vipgoci_log(
		( $emulate_only ? 'Emulated' : 'Finished' ) . ' opening up issues on GitHub',
		array(
			'issue_statistics' => $issue_statistics,
		)
	);

	return $issue_statistics;
}

