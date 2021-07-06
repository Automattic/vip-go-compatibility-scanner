<?php

/*
 * Open or create ZendeskDB file.
 * Uses SQLite.
 */

function vipgocs_zendesk_db_open(
	string $path
) :object {
	vipgoci_counter_report(
		VIPGOCI_COUNTERS_DO,
		'zendesk_db_open',
		1
	);

	try {
		$db_conn = new SQLite3(
			$path,
			SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE
		);
	}

	catch ( Exception $e ) {
		$error_msg = $e->getMessage();
		$db_conn = null;
	}

	if ( null === $db_conn ) {
		vipgoci_sysexit(
			'Unable to open ZendeskDB',
			array(
				'file'	=> $path,
				'error'	=> $error_msg,
			)
		);
	}

	$res = $db_conn->query(
		'CREATE TABLE IF NOT EXISTS vipgocs_github_issues ( ' .
			'id integer PRIMARY KEY, ' .
			'repo_name TEXT NOT NULL, ' .
			'repo_owner TEXT NOT NULL, ' .
			'github_url TEXT NOT NULL ) '
	);

	if ( false === $res ) {
		vipgoci_sysexit(
			'Unable to write to ZendeskDB',
			array(
				'file'  => $path,
			)
		);
	}


	return $db_conn;
}

/*
 * Close database handle.
 */
function vipgocs_zendesk_db_close(
	object $db_conn
) :bool {
	vipgoci_counter_report(
		VIPGOCI_COUNTERS_DO,
		'zendesk_db_close',
		1
	);

	return $db_conn->close();
}

/*
 * Write GitHub issue URL to DB along
 * with repo-owner and repo-name.
 */
function vipgocs_zendesk_db_write_github_issue(
	object $db_conn,
	string $repo_owner,
	string $repo_name,
	string $github_url
) :?object {
	vipgoci_counter_report(
		VIPGOCI_COUNTERS_DO,
		'zendesk_db_write_github_issue',
		1
	);

	$db_stmt = $db_conn->prepare(
		'INSERT INTO vipgocs_github_issues ' .
			'(repo_name, repo_owner, github_url) ' .
			'VALUES '  .
			'( :repo_name, :repo_owner, :github_url )'
	);

	$db_stmt->bindValue(':repo_name', $repo_name );
	$db_stmt->bindValue(':repo_owner', $repo_owner );
	$db_stmt->bindValue(':github_url', $github_url );

	return $db_stmt->execute();
}

/*
 * Fetch all GitHub issues from DB,
 * merge all that belong to the same
 * repo-owner/repo-name combination into
 * one group.
 */
function vipgocs_zendesk_db_get_github_issues(
	object $db_conn
) :array {
	vipgoci_counter_report(
		VIPGOCI_COUNTERS_DO,
		'zendesk_db_get_github_issues',
		1
	);

	$res = $db_conn->query(
		'SELECT id, repo_name, repo_owner, github_url FROM vipgocs_github_issues ORDER BY id'
	);

	$gh_issues_groups = array();

	while ( $row = $res->fetchArray() ) {
		$key = $row['repo_owner'] . '/' . $row['repo_name'];

		if ( ! isset(
			$gh_issues_groups[ $key ]
		) ) {
			$gh_issues_groups[ $key ] = array(
				'repo_owner'		=> $row['repo_owner'],
				'repo_name'		=> $row['repo_name'],
				'github_issues_urls'	=> array(),
			);
		}

		$gh_issues_groups[ $key ]['github_issues_urls'][] =
			$row['github_url'];
	}

	return $gh_issues_groups;
}

/*
 * Delete a particular URL from
 * Zendesk DB, matched also by repo-owner
 * and repo-name.
 */
function vipgocs_zendesk_db_delete_github_issue(
	object $db_conn,
	string $repo_owner,
	string $repo_name,
	string $github_url
) :object {
	vipgoci_counter_report(
		VIPGOCI_COUNTERS_DO,
		'zendesk_db_delete_github_issue',
		1
	);

	$db_stmt = $db_conn->prepare(
		'DELETE FROM vipgocs_github_issues ' .
			'WHERE ' .
			'repo_owner = :repo_owner AND ' .
			'repo_name = :repo_name AND ' .
			'github_url = :github_url'
	);

	$db_stmt->bindValue(':repo_name', $repo_name );
	$db_stmt->bindValue(':repo_owner', $repo_owner );
	$db_stmt->bindValue(':github_url', $github_url );

	return $db_stmt->execute();
}

