<?php

namespace Vipgocs\tests;

require_once( __DIR__ . '/IncludesForTests.php' );

use PHPUnit\Framework\TestCase;

// phpcs:disable PSR1.Files.SideEffects

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
		$this->options['review-comments-ignore'] = array(
			'Test message 40',
			'Test message 50',
		);

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
								'github_commit_url' => 'https://github.com/mytestuseraccount0x900/testrepo18736/blob/8171',
								'file_is_in_submodule' => false,
								'file_path_without_submodule' => 'file1.php',
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
								'github_commit_url' => 'https://github.com/mytestuseraccount1x900/testrepo19936/blob/9300',
								'file_is_in_submodule' => false,
								'file_path_without_submodule' => 'file3.php',
							)
						)
					),

					'file4.php' => array(
						'messages' => array(
							array(
								'message' => "All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found 'time'.",
								'source' => 'WordPress.Security.EscapeOutput.OutputNotEscaped',
								'severity' => 5,
								'fixable' => false,
								'type' => 'ERROR',
								'line' => 30,
								'column' => 14,
								'file_path' => 'sub-module-folder/file4.php',
								'github_commit_url' => 'https://github.com/yetanothertestaccount1x900/testrepo39999/blob/18900',
								'file_is_in_submodule' => true,
								'file_path_without_submodule' => 'file4.php',
							),
							array(
								'message' => "Test message 40", // Should be ignored, see review-comments-ignore config above
								'source' => 'TestSource.Source1',
								'severity' => 5,
								'fixable' => false,
								'type' => 'WARNING',
								'line' => 40,
								'column' => 20,
								'file_path' => 'sub-module-folder/file4.php',
								'github_commit_url' => 'https://github.com/yetanothertestaccount1x900/testrepo39999/blob/18900',
								'file_is_in_submodule' => true,
								'file_path_without_submodule' => 'file4.php',
							),
						)
					),
				),
				'warnings' => 1,
				'errors' => 3,
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
				'phpcs_issues_found' => 3,
				'github_issues_opened' => 3,
			),
			$issue_statistics,
		);

		$this->assertEquals(
			'{"_POST":{"{\"title\":\"my-issue-title1file1_php\",\"body\":\"my-issue-body1,_\\\\n*_<b>Error<\\\\\\/b>:_All_output_should_be_run_through_an_escaping_function_(see_the_Security_sections_in_the_WordPress_Developer_Handbooks),_found_\'time\'__https:\\\\\/\\\\\/github_com\\\\\\/mytestuseraccount0x900\\\\\\/testrepo18736\\\\\\/blob\\\\\\/8171\\\\\\/file1_php#L2\\\\n\\\\n\\\\n\\\\n,_main-branch\\\\n\",\"assignees\":":{"\"my-username1\",\"my-username2\"":""}},"_GET":[]}' .
				'{"_POST":{"{\"title\":\"my-issue-title1file3_php\",\"body\":\"my-issue-body1,_\\\\n*_<b>Error<\\\\\\/b>:_All_output_should_be_run_through_an_escaping_function_(see_the_Security_sections_in_the_WordPress_Developer_Handbooks),_found_\'time\'__https:\\\\\\/\\\\\\/github_com\\\\\\/mytestuseraccount1x900\\\\\\/testrepo19936\\\\\\/blob\\\\\\/9300\\\\\\/file3_php#L3\\\\n\\\\n\\\\n\\\\n,_main-branch\\\\n\",\"assignees\":":{"\"my-username1\",\"my-username2\"":""}},"_GET":[]}' .
				'{"_POST":{"{\"title\":\"my-issue-title1file4_php\",\"body\":\"my-issue-body1,_\\\\n*_<b>Error<\\\\\\/b>:_All_output_should_be_run_through_an_escaping_function_(see_the_Security_sections_in_the_WordPress_Developer_Handbooks),_found_\'time\'___In_submodule,_<a_href":"\\\\\\"https:\\\\\\/\\\\\\/github.com\\\\\\/yetanothertestaccount1x900\\\\\\/testrepo39999\\\\\\/blob\\\\\\/18900\\\\\\/file4.php#L30\\\\\\">here<\\\\\\/a>.\\\\n\\\\n\\\\n\\\\n, main-branch\\\\n\",\"assignees\":[\"my-username1\",\"my-username2\"]}"},"_GET":[]}',

			file_get_contents(
				$this->output_file
			)
		);
	}
}

