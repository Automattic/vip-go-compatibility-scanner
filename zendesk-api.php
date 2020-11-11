<?php

/*
 * Function to test if authentication
 * succeeded when using REST API. Make
 * a simple request and return with true
 * if everything is okay, false otherwise.
 */
function vipgocs_zendesk_check_auth(
	$options
) {
	vipgoci_log(
		'Checking authentication credentials with Zendesk...'
	);

	$sessions = vipgocs_zendesk_send_request(
		'GET',
		$options['zendesk-subdomain'],
		'sessions.json',
		array(),
		vipgocs_zendesk_prepare_auth_fields(
			$options
		)
	);

	if (
		( isset(
			$sessions['sessions']
		) )
		&&
		( count(
			$sessions['sessions']
		) > 0 )
	) {
		vipgoci_log(
			'Zendesk authentication successful'
		);

		return true;
	}

	vipgoci_log(
		'Zendesk authentication failed'
	);

	return false;
}

/*
 * Search for a particular email
 * address, try to link up with user ID.
 */
function vipgocs_zendesk_search_for_user(
	$options,
	$email
) {
	vipgoci_log(
		'Searching for user on Zendesk...',
		array(
			'email'	=> $email,
		)
	);

	$zendesk_api_datafields = array(
		'query' => 'type:user "' . $email . '"'
	);

	$resp_data = vipgocs_zendesk_send_request(
		'GET',
		$options['zendesk-subdomain'],
		'search.json',
		$zendesk_api_datafields,
		vipgocs_zendesk_prepare_auth_fields(
			$options
		)
	);

	if (
		( isset( $resp_data['results'] ) ) &&
		( count( $resp_data['results'] ) > 0 )
	) {
		vipgoci_log(
			'Zendesk user found',
			array(
				'email' => $email,
				'zendesk_user_id' => $resp_data['results'][0]['id'],
			)
		);

		return $resp_data['results'][0];
	}

	vipgoci_log(
		'No user found',
		array(
			'email'	=> $email,
		)
	);

	return null;
}

/*
 * Open a Zendesk ticket via REST API
 *
 * See documentation: https://developer.zendesk.com/rest_api/docs/support/tickets
 */
function vipgocs_zendesk_open_ticket(
	$options,
	$zendesk_requestee_email,
	$github_issues_links
) {
	vipgoci_log(
		'Opening Zendesk ticket via REST API',
		array(
			'zendesk-subdomain'		=> $options['zendesk-subdomain'],
			'zendesk-requestee-email'	=> $zendesk_requestee_email,
			'zendesk-ticket-subject'	=> $options['zendesk-ticket-subject'],
			'zendesk-ticket-body'		=> $options['zendesk-ticket-body'],
			'zendesk-ticket-tags'		=> $options['zendesk-ticket-tags'],
			'zendesk-group-id'  		=> $options['zendesk-group-id'],
			'github_issues_link'		=> $github_issues_links,
		)
	);

	$zendesk_api_postfields = array(
		'ticket'	=> array(
			'subject'	=> $options['zendesk-ticket-subject'],
			'comment'	=> array(
				'body' => str_replace(
					array(
						'%github_issues_link%',
						'%linebreak%',
					),
					array(
						$github_issues_links[0],
						PHP_EOL,
					),
					$options['zendesk-ticket-body']
				)
			)
		)
	);

	if ( ! empty( $options['zendesk-ticket-tags'] ) ) {
		$zendesk_api_postfields['ticket']['tags'] = $options['zendesk-ticket-tags'];
	}

	if ( ! empty( $options['zendesk-ticket-group-id'] ) ) {
		$zendesk_api_postfields['ticket']['group_id'] = $options['zendesk-ticket-group-id'];
	}

	if ( ! empty( $options['zendesk-ticket-status'] ) ) {
		$zendesk_api_postfields['ticket']['status'] = $options['zendesk-ticket-status'];
	}

	/*
	 * Assign requestee to the ticket.
	 */
	$zendesk_req_id = vipgocs_zendesk_search_for_user(
		$options,
		$zendesk_requestee_email
	);

	if ( empty( $zendesk_req_id['id'] ) ) {
		vipgoci_log(
			'Unable to open ticket for repository, as no user was found for email address specified',
			array(
				'zendesk-requestee-email'	=> $zendesk_requestee_email,
			)
		);

		return;
	}

	$zendesk_api_postfields['ticket']['requester_id'] =
		$zendesk_req_id['id'];

	/*
	 * Assign submitter to the ticket --
	 * this is so that it does not appear
	 * that clients open tickets, but whoever
	 * runs the program.
	 */
	$zendesk_submitter_id = vipgocs_zendesk_search_for_user(
		$options,
		$options['zendesk-access-username']
	);

	if ( empty( $zendesk_submitter_id['id'] ) ) {
		vipgoci_log(
			'Unable to open ticket for repository, as no user was found for email address specified',
			array(
				'zendesk-submitter-email'	=> $options['zendesk-access-username'],
			)
		);

		return;
	}
	$zendesk_api_postfields['ticket']['submitter_id'] =
		$zendesk_submitter_id['id'];


	$resp_data = vipgocs_zendesk_send_request(
		'POST',
		$options['zendesk-subdomain'],
		'tickets.json',
		$zendesk_api_postfields,
		vipgocs_zendesk_prepare_auth_fields(
			$options
		)
	);

	if ( isset( $resp_data['ticket']['id'] ) ) {
		/*
		 * Log stuff before returning
		 */

		vipgoci_log(
			'Zendesk API responded',
			array(
				'ticket_id'		=> $resp_data['ticket']['id'],
				'ticket_subject'	=> $resp_data['ticket']['subject'],
			)
		);

		$zendesk_ticket_url =
			'https://' .
				$options['zendesk-subdomain'] . '.zendesk.com/' .
				'agent/' .
				'tickets/' .
				$resp_data['ticket']['id'];
	}

	else {
		vipgoci_log(
			'Zendesk API error response',
			array(
				$resp_data
			)
		);

		$zendesk_ticket_url = null;
	}

	return $zendesk_ticket_url;
}

/*
 * Generic function to send REST request
 * to Zendesk API.
 */
function vipgocs_zendesk_send_request(
	$type,
	$zendesk_api_subdomain,
	$zendesk_api_endpoint,
	$zendesk_api_datafields,
	$zendesk_api_auth
) {
	$type = strtoupper(
		$type
	);

	/*
	 * Prepare URL
	 */

	if (
		( defined( 'VIPGOCS_UNIT_TESTING' ) ) &&
		( true === VIPGOCS_UNIT_TESTING )
	) {
		$zendesk_api_url = 
			VIPGOCI_GITHUB_BASE_URL;
	}

	else {
		$zendesk_api_url =
			'https://' .
				$zendesk_api_subdomain . '.zendesk.com/' .
				'api/' .
				'v2/' .
				$zendesk_api_endpoint;
	}

	/*
	 * Prepare cURL for request.
	 */
	$ch = curl_init();

	curl_setopt(
		$ch, CURLOPT_RETURNTRANSFER, 1
	);

	curl_setopt(
		$ch, CURLOPT_CONNECTTIMEOUT, 20
	);

	curl_setopt(
		$ch, CURLOPT_USERAGENT, VIPGOCI_CLIENT_ID
	);

	if ( 'POST' === $type ) {
		curl_setopt(
			$ch, CURLOPT_POST, 1
		);

		curl_setopt(
			$ch,
			CURLOPT_POSTFIELDS,
			json_encode( $zendesk_api_datafields )
		);
	}

	else if ( 'GET' === $type ) {
		curl_setopt(
			$ch, CURLOPT_HTTPGET, 1
		);

		$query_val = '';
		$i = 0;

		foreach(
			$zendesk_api_datafields as
				$query_key => $query_value
		) {
			if ( $i == 0 ) {
				$query_val .= '?';
			}

			else if ( $i > 0 ) {
				$query_val .= '&';
			}

			$query_val .=
				rawurlencode( $query_key ) .
				'=' .
				rawurlencode( $query_value );

			$i++;
		}

		$zendesk_api_url .=
			$query_val;
	}

	curl_setopt(
		$ch, CURLOPT_URL, $zendesk_api_url
	);


	$zendesk_api_headers = array(
		'Content-Type: application/json',
	);

	if (
		( isset( $zendesk_api_auth['zendesk-access-username'] ) ) &&
		( isset( $zendesk_api_auth['zendesk-access-token'] ) )
	) {
		curl_setopt(
			$ch,
			CURLOPT_USERPWD,
			$zendesk_api_auth['zendesk-access-username'] . '/token:' .
				$zendesk_api_auth['zendesk-access-token']
		);
	}

	else if (
		( isset( $zendesk_api_auth['zendesk-access-username'] ) ) &&
		( isset( $zendesk_api_auth['zendesk-access-password'] ) )
	) {
		curl_setopt(
			$ch,
			CURLOPT_USERPWD,
			$zendesk_api_auth['zendesk-access-username'] . ':' .
				$zendesk_api_auth['zendesk-access-password']
		);
	}

	curl_setopt(
		$ch,
		CURLOPT_HTTPHEADER,
		$zendesk_api_headers
	);

	/*
	 * Update statistics.
	 */

	vipgoci_counter_report(
		VIPGOCI_COUNTERS_DO,
		'zendesk_api_request_' . strtolower( $type ),
		1
	);

	vipgoci_runtime_measure( VIPGOCI_RUNTIME_START, 'zendesk_api_' . strtolower( $type ) );

	/* Execute */
	$resp_data = curl_exec( $ch );

	vipgoci_runtime_measure( VIPGOCI_RUNTIME_STOP, 'zendesk_api_' . strtolower( $type ) );

	/*
	 * Parse results.
	 */
	$resp_data = json_decode(
		$resp_data,
		true // Array result
	);

	return $resp_data;
}


/*
 * Prepare fields for authentication
 * with Zendesk.
 *
 * This tool supports both username and
 * password authentication, and username and token
 * authentication. Here we check if required
 * fields are present for either of these,
 * and if not, we return null. 

 * There is also a check for existance of a subdomain 
 * field, resulting in returning null when not existing.
 */

function vipgocs_zendesk_prepare_auth_fields(
	$options
) {
	$auth_fields = array();

	if ( empty(
		$options['zendesk-subdomain']
	) ) {
		return null;
	}

	if (
		( isset( $options['zendesk-access-username'] ) ) &&
		( isset( $options['zendesk-access-token'] ) )
	) {
		$auth_fields['zendesk-access-username'] =
			$options['zendesk-access-username'];

		$auth_fields['zendesk-access-token'] =
			$options['zendesk-access-token'];
	}

	else if (
		( isset( $options['zendesk-access-username'] ) ) &&
		( isset( $options['zendesk-access-password'] ) )
	) {
		$auth_fields['zendesk-access-username'] =
			$options['zendesk-access-username'];

		$auth_fields['zendesk-access-password'] =
			$options['zendesk-access-password'];
	}

	else {
		return null;
	}

	return $auth_fields;
}
