<?php

define( 'VIPGOCS_PHPCS_CACHEDB_TABLE_NAME', 'vipgocs_phpcs_cachedb' );
define( 'VIPGOCS_PHPCS_CACHEDB_INDEX_NAME', 'vipgocs_phpcs_cachedb_index' );

/**
 * Shorthand function to calculate SHA hash.
 */
function vipgocs_phpcs_cachedb_hash_calc(
	string $data
) :string {
	return hash(
		'sha3-512',
		$data,
		false
	);
}

/*
 * Open or create file for cached PHPCS results,
 * PHPCSCacheDB. Uses SQLite.
 */
function vipgocs_phpcs_cachedb_db_open(
	string $path
) :object {
	vipgoci_log(
		'Opening connection to PHPCS caching database (PHPCSCacheDB)',
		array(
			'path'	=> $path,
		)
	);

	vipgoci_counter_report(
		VIPGOCI_COUNTERS_DO,
		'vipgocs_phpcs_cachedb_db_open',
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
			'Unable to open PHPCSCacheDB',
			array(
				'path'	=> $path,
				'error'	=> $error_msg,
			)
		);
	}

	$res = $db_conn->query(
		'CREATE TABLE IF NOT EXISTS ' .
			VIPGOCS_PHPCS_CACHEDB_TABLE_NAME . ' ( ' .
			'id integer PRIMARY KEY, ' .
			'file_shasum TEXT NOT NULL, ' .
			'phpcs_shasum TEXT NOT NULL, ' .
			'message TEXT NOT NULL, ' .
			'source TEXT NOT NULL, ' .
			'severity integer NOT NULL, ' .
			'fixable boolean NOT NULL, ' .
			'type TEXT NOT NULL, ' .
			'line integer NOT NULL, ' .
			'column integer NOT NULL' .
			')'
	);

	if ( false === $res ) {
		vipgoci_sysexit(
			'Unable to write to PHPCSCacheDB',
			array(
				'path'	=> $path,
			)
		);
	}

	$res = $db_conn->query(
		'CREATE INDEX IF NOT EXISTS ' .
			VIPGOCS_PHPCS_CACHEDB_INDEX_NAME . ' ' .
			'ON ' . VIPGOCS_PHPCS_CACHEDB_TABLE_NAME . ' ' .
			'(file_shasum, phpcs_shasum)'
	);

	if ( false === $res ) {
		vipgoci_sysexit(
			'Unable to write to PHPCSCacheDB',
			array(
				'path'	=> $path,
			)
		);
	}

	return $db_conn;
}

/**
 * Close CacheDB database handle.
 */
function vipgocs_phpcs_cachedb_db_close(
	object &$db_conn
) :bool {
	vipgoci_log(
		'Closing connection to PHPCS caching database (PHPCSCacheDB)'
	);

	vipgoci_counter_report(
		VIPGOCI_COUNTERS_DO,
		'vipgocs_phpcs_cachedb_db_close',
		1
	);

	return $db_conn->close();
}

/**
 * Clean the PHPCS CacheDB using the
 * VACUUM query.
 */
function vipgocs_phpcs_cachedb_db_vacuum(
	object &$db_conn
) :bool {
	vipgoci_log(
		'Vacuuming PHPCS caching database (PHPCSCacheDB)'
	);

	$res = $db_conn->query(
		'VACUUM'
	);

	if ( false === $res ) {
		return false;
	}

	return true;
}

/**
 * Add PHPCS results to CacheDB.
 *
 * Will remove any entries relating
 * to the same file and PHPCS settings,
 * if existing. This is to avoid database
 * corruption.
 */
function vipgocs_phpcs_cachedb_add(
	object &$db_conn,
	string $file_path,
	array $phpcs_options,
	array $results
) :bool {
	vipgoci_log(
		'Caching PHPCS results to database',
		array(
			'file_path'	=> $file_path,
			'results_cnt'	=> count( $results ),
		)
	);

	vipgoci_counter_report(
		VIPGOCI_COUNTERS_DO,
		'vipgocs_phpcs_cachedb_add',
		1
	);

	/*
	 * Get file contents and
	 * calculate SHA sum for the
	 * file.
	 */
	$file_contents = file_get_contents(
		$file_path
	);

	if ( false === $file_contents ) {
		vipgoci_log(
			'Unable to get file contents',
			array(
				'file_path'	=> $file_path,
			)
		);

		return false;
	}

	$file_contents_shasum = vipgocs_phpcs_cachedb_hash_calc(
		$file_contents
	);

	/*
	 * Calculate SHA sum for PHPCS options.
	 */
	$phpcs_options_shasum = vipgocs_phpcs_cachedb_hash_calc(
		json_encode( $phpcs_options )
	);

	/*
	 * In case of data existing, remove it first
	 * just in case.
	 */
	vipgocs_phpcs_cachedb_remove(
		$db_conn,
		$file_path,
		$phpcs_options
	);

	/*
	 * Prepare to insert data into the DB.
	 */
	$db_stmt = $db_conn->prepare(
		'INSERT INTO ' . VIPGOCS_PHPCS_CACHEDB_TABLE_NAME .
			'(file_shasum, phpcs_shasum, message, source, severity, fixable, type, line, column)' .
			'VALUES ' .
			'( :file_shasum, :phpcs_shasum, :message, :source, :severity, :fixable, :type, :line, :column )'
	);

	/*
	 * No results, so insert a DB entry to cache this
	 * for this file. This is handled specifically by
	 * the get function.
	 */
	if ( count( $results ) === 0 ) {
		$res_item = array(
			'message'	=> '--CACHE-ENTRY--',
			'source'	=> 'Vipgocs.Internal',
			'severity'	=> 0,
			'fixable'	=> false,
			'type'		=> 'info',
			'line'		=> 0,
			'column'	=> 0,
		);

		$db_stmt->bindValue(':file_shasum', $file_contents_shasum );
		$db_stmt->bindValue(':phpcs_shasum', $phpcs_options_shasum );
		$db_stmt->bindValue(':message', $res_item['message'] );
		$db_stmt->bindValue(':source', $res_item['source'] );
		$db_stmt->bindValue(':severity', $res_item['severity'] );
		$db_stmt->bindValue(':fixable', $res_item['fixable'] );
		$db_stmt->bindValue(':type', $res_item['type'] );
		$db_stmt->bindValue(':line', $res_item['line'] );
		$db_stmt->bindValue(':column', $res_item['column'] );
	
		$db_stmt->execute();

		return true;	
	}

	else {
		foreach( $results as $res_item ) {
			$db_stmt->bindValue(':file_shasum', $file_contents_shasum );
			$db_stmt->bindValue(':phpcs_shasum', $phpcs_options_shasum );
			$db_stmt->bindValue(':message', $res_item['message'] );
			$db_stmt->bindValue(':source', $res_item['source'] );
			$db_stmt->bindValue(':severity', $res_item['severity'] );
			$db_stmt->bindValue(':fixable', $res_item['fixable'] );
			$db_stmt->bindValue(':type', $res_item['type'] );
			$db_stmt->bindValue(':line', $res_item['line'] );
			$db_stmt->bindValue(':column', $res_item['column'] );
	
			$db_stmt->execute();
		}
	}

	return true;
}

/**
 * Remove PHPCS results from CacheDB.
 *
 * Will remove any entries relating
 * to the file specified and PHPCS settings,
 * if existing.
 */
function vipgocs_phpcs_cachedb_remove(
	object &$db_conn,
	string $file_path,
	array $phpcs_options
) :object {
	
	vipgoci_counter_report(
		VIPGOCI_COUNTERS_DO,
		'vipgocs_phpcs_cachedb_remove',
		1
	);

	$file_contents = file_get_contents(
		$file_path
	);

	if ( false === $file_contents ) {
		vipgoci_log(
			'Unable to get file contents',
			array(
				'file_path'	=> $file_path,
			)
		);

		return false;
	}


	$file_contents_shasum = vipgocs_phpcs_cachedb_hash_calc(
		$file_contents
	);

	$phpcs_options_shasum = vipgocs_phpcs_cachedb_hash_calc(
		json_encode( $phpcs_options )
	);

	$db_stmt = $db_conn->prepare(
		'DELETE FROM ' . VIPGOCS_PHPCS_CACHEDB_TABLE_NAME . ' ' .
			'WHERE file_shasum = :file_shasum AND phpcs_shasum = :phpcs_shasum'
	);

	$db_stmt->bindValue(':file_shasum', $file_contents_shasum );
	$db_stmt->bindValue(':phpcs_shasum', $phpcs_options_shasum );

	unset( $file_contents );

	return $db_stmt->execute();
}

/**
 * Get PHPCS results, if any, from CacheDB.
 */
function vipgocs_phpcs_cachedb_get(
	object &$db_conn,
	string $file_path,
	array $phpcs_options,
	bool &$cached_zero_results
) :array {
	vipgoci_log(
		'Getting information from PHPCS caching database (PHPCSCacheDD)',
		array(
			'file_path'	=> $file_path,
		)
	);

	vipgoci_counter_report(
		VIPGOCI_COUNTERS_DO,
		'vipgocs_phpcs_cachedb_get',
		1
	);

	$file_contents = file_get_contents(
		$file_path
	);

	if ( false === $file_contents ) {
		vipgoci_log(
			'Unable to get file contents',
			array(
				'file_path'	=> $file_path,
			)
		);

		return array();
	}


	$file_contents_shasum = vipgocs_phpcs_cachedb_hash_calc(
		$file_contents
	);

	$phpcs_options_shasum = vipgocs_phpcs_cachedb_hash_calc(
		json_encode( $phpcs_options )
	);

	$db_stmt = $db_conn->prepare(
		'SELECT ' .
			'message, source, severity, fixable, type, line, column ' .
			'FROM ' . VIPGOCS_PHPCS_CACHEDB_TABLE_NAME . ' ' .
			'WHERE file_shasum = :file_shasum AND phpcs_shasum = :phpcs_shasum',
	);

	$db_stmt->bindValue(':file_shasum', $file_contents_shasum );
	$db_stmt->bindValue(':phpcs_shasum', $phpcs_options_shasum );

	$db_res = $db_stmt->execute();

	/*
	 * Get the results.
	 */
	$results = array();

	$cached_zero_results = false;

	do {
		$db_row = $db_res->fetchArray( SQLITE3_ASSOC );

		/*
		 * In case of a results, but 'fixable' is integer
		 * convert to boolean.
		 */
		if ( ( false !== $db_row ) && ( is_numeric( $db_row['fixable'] ) ) ) {
			$db_row['fixable'] = (bool) $db_row['fixable'];
		}

		/*
		 * Found a row indicating zero
		 * results found, handle this
		 * specifically.
		 */
		if (
			( false !== $db_row ) &&
			( $db_row['message'] === '--CACHE-ENTRY--' ) &&
			( $db_row['source'] === 'Vipgocs.Internal' ) &&
			( $db_row['severity'] === 0 ) &&
			( $db_row['fixable'] === false ) &&
			( $db_row['type'] === 'info' ) &&
			( $db_row['line'] === 0 ) &&
			( $db_row['column'] === 0 )
		) {
			$cached_zero_results = true;
			continue;
		}

		if ( false !== $db_row ) {
			$results[] = $db_row;
		}
	} while ( $db_row !== false );

	/*
	 * In case of some kind of database corruption,
	 * whereby we have results indicating zero rows
	 * but then there are some results, fix this variable.
	 */
	if (
		( count( $results ) > 0 ) &&
		( true === $cached_zero_results )
	) {
		$cached_zero_results = false;
	}

	return $results;	
}
