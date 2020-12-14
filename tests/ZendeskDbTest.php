<?php

require_once( __DIR__ . '/IncludesForTests.php' );

use PHPUnit\Framework\TestCase;

final class ZendeskDbTest extends TestCase {
	protected function setUp() :void {
		$this->db_file = vipgoci_save_temp_file(
			'tmp-db-file',
			'sqlite',
			''
		);
	}

	protected function tearDown() :void {
		if ( ! empty(
			$this->db_file
		) ) {
			unlink(
				$this->db_file
			);
		}
	}
	
	public function testZendeskDb1() {
		$db_conn = vipgocs_zendesk_db_open(
			$this->db_file
		);


		$this->assertNotEmpty(
			$db_conn
		);

		$gh_issues = vipgocs_zendesk_db_get_github_issues(
			$db_conn
		);

		$this->assertEquals(
			array(),
			$gh_issues
		);

		vipgocs_zendesk_db_write_github_issue(
			$db_conn,
			'r-owner1',
			'r-name1',
			'http://github.com/test1'
		);

		vipgocs_zendesk_db_write_github_issue(
			$db_conn,
			'r-owner1',
			'r-name1',
			'http://github.com/test2'
		);

		vipgocs_zendesk_db_write_github_issue(
			$db_conn,
			'r-owner1',
			'r-name1',
			'http://github.com/test3'
		);

		vipgocs_zendesk_db_write_github_issue(
			$db_conn,
			'r-owner1',
			'r-name2',
			'http://github.com/test4'
		);

		vipgocs_zendesk_db_write_github_issue(
			$db_conn,
			'r-owner2',
			'r-name1',
			'http://github.com/test5'
		);

		vipgocs_zendesk_db_write_github_issue(
			$db_conn,
			'r-owner2',
			'r-name1',
			'http://github.com/test6'
		);


		$gh_issues = vipgocs_zendesk_db_get_github_issues(
			$db_conn
		);

		$this->assertEquals(
			array(
				'r-owner1/r-name1' => array(
					'repo_owner' => 'r-owner1',
					'repo_name' => 'r-name1',
					'github_issues_urls' => array(
						'http://github.com/test1',
						'http://github.com/test2',
						'http://github.com/test3',
					)
				),

				'r-owner1/r-name2' => array(
					'repo_owner' => 'r-owner1',
					'repo_name' => 'r-name2',
					'github_issues_urls' => array(
						'http://github.com/test4'
					)
				),

				'r-owner2/r-name1' => array(
					'repo_owner' => 'r-owner2',
					'repo_name' => 'r-name1',
					'github_issues_urls' => array(
						'http://github.com/test5',
						'http://github.com/test6',
					)
				),
			),
			$gh_issues
		);

		vipgocs_zendesk_db_delete_github_issue(
			$db_conn,
			'r-owner2',
			'r-name1',
			'http://github.com/test5'
		);

		$gh_issues = vipgocs_zendesk_db_get_github_issues(
			$db_conn
		);

		$this->assertEquals(
			array(
				'r-owner1/r-name1' => array(
					'repo_owner' => 'r-owner1',
					'repo_name' => 'r-name1',
					'github_issues_urls' => array(
						'http://github.com/test1',
						'http://github.com/test2',
						'http://github.com/test3',
					)
				),

				'r-owner1/r-name2' => array(
					'repo_owner' => 'r-owner1',
					'repo_name' => 'r-name2',
					'github_issues_urls' => array(
						'http://github.com/test4'
					)
				),

				'r-owner2/r-name1' => array(
					'repo_owner' => 'r-owner2',
					'repo_name' => 'r-name1',
					'github_issues_urls' => array(
						'http://github.com/test6',
					)
				),
			),
			$gh_issues
		);

		vipgocs_zendesk_db_delete_github_issue(
			$db_conn,
			'r-owner1',
			'r-name2',
			'http://github.com/test4'
		);

		$gh_issues = vipgocs_zendesk_db_get_github_issues(
			$db_conn
		);

		$this->assertEquals(
			array(
				'r-owner1/r-name1' => array(
					'repo_owner' => 'r-owner1',
					'repo_name' => 'r-name1',
					'github_issues_urls' => array(
						'http://github.com/test1',
						'http://github.com/test2',
						'http://github.com/test3',
					)
				),
				'r-owner2/r-name1' => array(
					'repo_owner' => 'r-owner2',
					'repo_name' => 'r-name1',
					'github_issues_urls' => array(
						'http://github.com/test6',
					)
				),
			),
			$gh_issues
		);

		$this->assertTrue(
			vipgocs_zendesk_db_close(
				$db_conn
			)
		);
	}
}

