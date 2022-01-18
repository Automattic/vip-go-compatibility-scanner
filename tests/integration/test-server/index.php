<?php

header('Status: 200 OK');

file_put_contents(
	getenv("VIPGOCI_UNITTESTS_PID_FILE"),
	getmypid()
);

file_put_contents(
	getenv("VIPGOCI_UNITTESTS_OUTPUT_FILE"),
	json_encode(
		array(
			'_POST'	=> $_POST,
			'_GET'	=> $_GET,
		)
	),
	FILE_APPEND
);

