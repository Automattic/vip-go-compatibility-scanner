<?php

/*
 * Get CSV data. First line of file
 * should be columns.
 */
function vipgocs_csv_parse_data(
	string $zendesk_csv_file_path
): array {
	vipgoci_log(
		'Parsing CSV data file',
		array(
			'zendesk_csv_file_path'	=> $zendesk_csv_file_path,
		)
	);

	ini_set( 'auto_detect_line_endings', false );

	$res = array();

	$f = fopen(
		$zendesk_csv_file_path,
		'r'
	);

	if ( ! $f ) {
		vipgoci_sysexit(
			'Unable to parse CSV file',
			array(
				'zendesk_csv_file_path' => $zendesk_csv_file_path,
			)
		);
	}

	$first = fgetcsv(
		$f,
		0,
		','
	);

	if ( empty( $first ) ) {
		vipgoci_sysexit(
			'CSV file seems empty? First line should be columns.'
		);
	}

	while ( $item = fgetcsv(
		$f,
		0,
		','
	) ) {
		if ( count( $item ) <= 0 ) {
			continue;
		}

		$i = 0;

		$item_arr = array();

		foreach( array_values( $first ) as $key ) {
			if ( ! isset( $item[ $i ] ) ) {
				continue;
			}

			if ( empty( $item[ $i ] ) ) {
				continue;
			}

			/*
			 * Assign item, trim any whitespace characters.
			 */
			$item_arr[ $key ] = trim( $item[ $i++ ] );
		}

		if ( ! empty( $item_arr ) ) {
			$res[] = $item_arr;
		}
	}

	fclose(
		$f
	);

	ini_set( 'auto_detect_line_endings', false );

	return $res;
}

/*
 * Try to match email address with repository.
 */
function vipgocs_csv_get_email_for_repo(
	array $csv_data_arr,
	string $repo_owner,
	string $repo_name
): ?string {
	$repo_owner = strtolower(
		$repo_owner
	);

	$repo_name = strtolower(
		$repo_name
	);

	foreach(
		$csv_data_arr as $csv_item
	) {
		if (
			( ! isset( $csv_item['source_repo'] ) ) ||
			( ! isset( $csv_item['client_email'] ) )
		) {
			continue;
		}

		if (
			$csv_item['source_repo'] !==
			$repo_owner .'/' . $repo_name
		) {
			continue;
		}

		return $csv_item['client_email'];
	}

	return null;
}
