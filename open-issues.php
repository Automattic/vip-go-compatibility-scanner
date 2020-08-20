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
			$error_msg .=
				'* <b>' .
					ucfirst ( strtolower(
						$file_issue['type']
					) );

			if ( 'folder' === $options['github-issue-group-by'] ) {
				$error_msg .= ' in ' .
				 $file_issue['file_path'];
			}

			$error_msg .= '</b>: ';		

			$error_msg .= $file_issue['message'] . ' ';

			$error_msg .= 'https://github.com/' .
				$options['repo-owner'] . '/' .
				$options['repo-name'] . '/' .
				'blob/' .
				$options['commit'] . '/' .
				$file_issue['file_path'] .
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
		unset( $file_path );
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

	return $issue_statistics;
}

