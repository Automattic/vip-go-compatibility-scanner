<?php

require_once( __DIR__ . '/IncludesForTests.php' );

use PHPUnit\Framework\TestCase;

final class ZendeskSendRequestTest extends TestCase {
	var $options = array(
		'php-path'              => null,
	);

	protected function setUp() :void {
		vipgoci_unittests_get_config_values(
			'php',
			$this->options
		);

		$this->pid_file = tempnam(
			sys_get_temp_dir(),
			'test_http_server_pid_'
		);

		$this->output_file = tempnam(
			sys_get_temp_dir(),
			'test_http_server_output_'
		);

		vipgoci_unittests_http_server_start(
			$this->options['php-path'],
			VIPGOCI_GITHUB_SERVER_ADDR,
			VIPGOCI_TEST_HTTP_SERVER_FILES_PATH,
			$this->pid_file,
			$this->output_file
		);	
	}

	protected function tearDown() :void {
		vipgoci_unittests_http_server_stop(
			$this->pid_file
		);
		
		unlink(
			$this->pid_file
		);

		unlink(
			$this->output_file
		);

		unset(
			$this->options,
			$this->pid_file,
			$this->output_file
		);
	}

	/**
	 * @covers ::vipgocs_zendesk_send_request
	 */
	public function testZendeskSendRequest() {
		if ( ! vipgoci_unittests_pcntl_supported() ) {
			$this->markTestSkipped(
				'PCNTL module is not available'
			);

			return;
		}

		vipgocs_zendesk_send_request(
			'GET',
			'mytest-domain',
			'/v1/endpoint',
			array(
				'data' => 12345678,
			),
			'user:pass'
		);

		$this->assertEquals(
			'{"_POST":[],"_GET":{"data":"12345678"}}',
			file_get_contents(
				$this->output_file
			)
		);
	}
}

