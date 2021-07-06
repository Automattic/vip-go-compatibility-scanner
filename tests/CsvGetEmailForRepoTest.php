<?php

namespace Vipgocs\tests;

require_once( __DIR__ . '/IncludesForTests.php' );

use PHPUnit\Framework\TestCase;

// phpcs:disable PSR1.Files.SideEffects

final class CsvGetEmailForRepoTest extends TestCase {
	/**
	 * @covers ::vipgocs_csv_get_email_for_repo
	 */
	public function testCsvParseData1() {
		// Determine name for temporary-file
		$temp_file_name = tempnam(
			sys_get_temp_dir(),
			'csv'
		);

		file_put_contents(
			$temp_file_name,
			'client_email,source_repo' . "\n\r" .
				'myemail1@myemail.com,myorg1/myrepo1' . "\n\r" .
				'myemail2@myemailb.com,myorg2/myrepo2 ' . "\n\r" .
				'myemail3@myemailc.com ,myorg3/myrepo3' . "\n\r"
		);

		vipgoci_unittests_output_suppress();
		
		$csv_data = vipgocs_csv_parse_data(
			$temp_file_name
		);

		vipgoci_unittests_output_unsuppress();

		unlink(
			$temp_file_name
		);

		/*
		 * Look for myorg0/myrepo0, should be no match
		 */
		$email = vipgocs_csv_get_email_for_repo(
			$csv_data,
			'myorg0',
			'myrepo0'
		);

		$this->assertSame(
			null,
			$email
		);

		/*
		 * Look for myorg0/myrepo1, should be no match
		 */
		$email = vipgocs_csv_get_email_for_repo(
			$csv_data,
			'myorg0',
			'myrepo1'
		);

		$this->assertSame(
			null,
			$email
		);

		/*
		 * Look for myorg1/myrepo1, should be match
		 */
		$email = vipgocs_csv_get_email_for_repo(
			$csv_data,
			'myorg1',
			'myrepo1'
		);

		$this->assertSame(
			'myemail1@myemail.com',
			$email
		);

		/*
		 * Look for myorg3/myrepo3, should be match
		 */
		$email = vipgocs_csv_get_email_for_repo(
			$csv_data,
			'myorg3',
			'myrepo3'
		);

		$this->assertSame(
			'myemail3@myemailc.com',
			$email
		);
	}
}
