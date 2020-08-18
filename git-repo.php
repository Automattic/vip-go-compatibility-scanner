<?php

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

