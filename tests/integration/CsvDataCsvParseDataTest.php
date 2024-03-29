<?php

namespace Vipgocs\Tests\Integration;

require_once( __DIR__ . '/IncludesForTests.php' );

use PHPUnit\Framework\TestCase;

// phpcs:disable PSR1.Files.SideEffects

final class CsvDataCsvParseDataTest extends TestCase {
	/**
	 * @covers ::vipgocs_csv_parse_data
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

		$this->assertSame(
			array(
				array(
					'client_email'	=> 'myemail1@myemail.com',
					'source_repo'	=> 'myorg1/myrepo1',
				),
				array(
					'client_email'	=> 'myemail2@myemailb.com',
					'source_repo'	=> 'myorg2/myrepo2',
				),
				array(
					'client_email'	=> 'myemail3@myemailc.com',
					'source_repo'	=> 'myorg3/myrepo3',
				),
			),
			$csv_data
		);

		unlink(
			$temp_file_name
		);
	}
}
