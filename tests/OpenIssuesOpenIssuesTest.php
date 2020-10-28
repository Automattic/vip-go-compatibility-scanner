<?php

require_once( __DIR__ . '/IncludesForTests.php' );

use PHPUnit\Framework\TestCase;

final class OpenIssuesOpenIssuesTest extends TestCase {
	var $options_php = array(
		'php-path'              => null,
	);

	protected function setUp() :void {
		if ( ! vipgoci_unittests_pcntl_supported() ) {
			$this->markTestSkipped(
				'PCNTL module is not available'
			);

			return;
		}

		vipgoci_unittests_get_config_values(
			'php',
			$this->options_php
		);

		$this->options = array_merge(
			$this->options_php,
			array(
				'repo-owner'		=> 'test',
				'repo-name'		=> 'test',
				'commit'		=> 'abcd1234',
				'token'			=> '1234abcd',
				'github-issue-group-by' => 'file',
				'github-issue-title'	=> 'my-issue-title1',
				'github-issue-body'	=> 'my-issue-body1, %error_msg%, %branch_name%',
			)
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
			$this->options_php,
			$this->pid_file,
			$this->output_file
		);
	}

	/**
	 * @covers ::vipgocs_open_issues
	 */
	public function testOpenIssue1() {

		$issue_statistics = vipgocs_open_issues(
			$this->options,
			array(
				'files'	=> array(
					'file1.php' => array(
						'messages' => array(
							array(
								'message' => "All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found 'time'.",
								'source' => 'WordPress.Security.EscapeOutput.OutputNotEscaped',
								'severity' => 5,
								'fixable' => false,
								'type' => 'ERROR',
								'line' => 2,
								'column' => 14,
								'file_path' => 'file1.php',
							)
						)
					),

					'file2.php' => array(
						'messages' => array(
						)
					),

					'file3.php' => array(
						'messages' => array(
							array(
								'message' => "All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found 'time'.",
								'source' => 'WordPress.Security.EscapeOutput.OutputNotEscaped',
								'severity' => 5,
								'fixable' => false,
								'type' => 'ERROR',
								'line' => 3,
								'column' => 14,
								'file_path' => 'file3.php',
							)
						)
					)
				),
				'warnings' => 0,
				'errors' => 2,
				'fixable' => 0,
			),
			'main-branch',
			array(
				'my-label1',
				'my-label2',
			),
			array(
				'my-username1',
				'my-username2',
			)
		);

		$this->assertEquals(
			array(
				'issues_found' => 2,
				'issues_opened' => 2,
			),
			$issue_statistics,
		);

		$this->assertEquals(
			'{"_POST":{"{\"title\":\"my-issue-title1file1_php\",\"body\":\"my-issue-body1,_\\\\n*_<b>Error<\\\\\\/b>:_All_output_should_be_run_through_an_escaping_function_(see_the_Security_sections_in_the_WordPress_Developer_Handbooks),_found_\'time\'__https:\\\\\/\\\\\/github_com\\\\\\/test\\\\\\/test\\\\\\/blob\\\\\\/abcd1234\\\\\\/file1_php#L2\\\\n\\\\n\\\\n\\\\n,_main-branch\\\\n\",\"assignees\":":{"\"my-username1\",\"my-username2\"":""}},"_GET":[]}' .
				'{"_POST":{"{\"title\":\"my-issue-title1file3_php\",\"body\":\"my-issue-body1,_\\\\n*_<b>Error<\\\\\\/b>:_All_output_should_be_run_through_an_escaping_function_(see_the_Security_sections_in_the_WordPress_Developer_Handbooks),_found_\'time\'__https:\\\\\\/\\\\\\/github_com\\\\\\/test\\\\\\/test\\\\\\/blob\\\\\\/abcd1234\\\\\\/file3_php#L3\\\\n\\\\n\\\\n\\\\n,_main-branch\\\\n\",\"assignees\":":{"\"my-username1\",\"my-username2\"":""}},"_GET":[]}',
			file_get_contents(
				$this->output_file
			)
		);
	}
}

