<?php

require_once( __DIR__ . '/IncludesForTests.php' );

use PHPUnit\Framework\TestCase;

final class FileWithWhiteSpacingOnlyTest extends TestCase {
	/**
	 * @covers ::vipgocs_file_with_whitespacing_only
	 */
	public function testFileWithWhiteSpacingOnlyTrue() {
		$tmp_file_name = tempnam(
			sys_get_temp_dir(),
			'vipgocs_fileiswithwhitespacingonly_true'
		);

		if ( false === $tmp_file_name ) {
			$this->markTestSkipped(
				'Unable to create temporary file, skipping test'
			);

			return;
		}

		/*
		 * Only whitespacing, should assert
		 * as true.
		 */
		$tmp_bytes_written = file_put_contents(
			$tmp_file_name,
			"\n\r\n\r\n\r\n\t    \n\r\n\t     "
		);

		if ( false === $tmp_bytes_written ) {
			$this->markTestSkipped(
				'Unable to write to temporary file, skipping test'
			);

			unlink( $tmp_file_name );

			return;
		}

		$this->assertTrue(
			vipgocs_file_with_whitespacing_only( $tmp_file_name, " \n\r\t\v\0" )
		);

		unlink( $tmp_file_name );
	}

	/**
	 * @covers ::vipgocs_file_with_whitespacing_only
	 */
	public function testFileWithWhiteSpacingOnlyFalse() {
		$tmp_file_name = tempnam(
			sys_get_temp_dir(),
			'vipgocs_fileiswithwhitespacingonly_false'
		);

		if ( false === $tmp_file_name ) {
			$this->markTestSkipped(
				'Unable to create temporary file, skipping test'
			);

			return;
		}

		/*
		 * Write valid PHP code to file plus
		 * whitespacing -- should assert as false.
		 */
		$tmp_bytes_written = file_put_contents(
			$tmp_file_name,
			"\n\r\n\r\n\r\n\t         " . PHP_EOL .
			'<?php if (isset( $test ) ) { }' . PHP_EOL
			"\n\r\n\r  " . PHP_EOL
		);

		if ( false === $tmp_bytes_written ) {
			$this->markTestSkipped(
				'Unable to write to temporary file, skipping test'
			);

			unlink( $tmp_file_name );

			return;
		}

		$this->assertFalse(
			vipgocs_file_with_whitespacing_only( $tmp_file_name, " \n\r\t\v\0" )
		);

		unlink( $tmp_file_name );
	}
}
