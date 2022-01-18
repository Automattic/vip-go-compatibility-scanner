<?php

namespace Vipgocs\Tests\Integration;

require_once( __DIR__ . '/IncludesForTests.php' );

use PHPUnit\Framework\TestCase;

// phpcs:disable PSR1.Files.SideEffects

final class PhpcsCacheDbTest extends TestCase {
	private function _temp_code_files_setup() {
		$tmp_file1 = tempnam(
			sys_get_temp_dir(),
			'file1_'
		);

		file_put_contents(
			$tmp_file1,
			'<?php' . PHP_EOL .
				'echo time() . PHP_EOL;' . PHP_EOL .
				'echo time() . PHP_EOL;'
		);

		$tmp_file2 = tempnam(
			sys_get_temp_dir(),
			'file2_'
		);

		file_put_contents(
			$tmp_file2,
			'<?php' . PHP_EOL .
				'echo time() . PHP_EOL;' . PHP_EOL .
				'echo "test" . PHP_EOL;' . PHP_EOL .
				'echo time() . PHP_EOL;'
		);

		$tmp_file3 = tempnam(
			sys_get_temp_dir(),
			'file3_'
		);

		file_put_contents(
			$tmp_file3,
			'<?php' . PHP_EOL .
				'echo time() . PHP_EOL;' . PHP_EOL .
				'echo "Other Test" . PHP_EOL;' . PHP_EOL .
				'echo time() . PHP_EOL;'
		);

		$this->temp_files = array(
			'tmp_file1' => $tmp_file1,
			'tmp_file2' => $tmp_file2,
			'tmp_file3' => $tmp_file3,
		);
	}

	private function _temp_code_files_remove() {
		unlink( $this->temp_files['tmp_file1'] );
		unset( $this->temp_files['tmp_file1'] );

		unlink( $this->temp_files['tmp_file2'] );
		unset( $this->temp_files['tmp_file2'] );

		unlink( $this->temp_files['tmp_file3'] );
		unset( $this->temp_files['tmp_file3'] );
	}

	protected function setUp(): void {
		$this->options = array();

		$this->options['phpcs-cachedb'] = tempnam(
			sys_get_temp_dir(),
			'phpcs_cachedb_tmp_'
		);

		$this->options['phpcs-severity'] = 5;
		$this->options['phpcs-standard'] = VIPGOCS_PHPCS_SOURCE_INTERNAL;

		$this->_temp_code_files_setup();
	}

	protected function tearDown(): void {
		$this->_temp_code_files_remove();

		unlink( $this->options['phpcs-cachedb'] );

		unset( $this->options );
	}

	/**
	 * @covers ::vipgocs_phpcs_cachedb_hash_calc
	 */
	public function testCacheDbHashCalc() {
		$this->assertSame(
			'96894d44a5395b8621579e5f7cdda4b8f3e4e78e91f9e5b6da8d92ae2a5dfac3aa269d34abf4d623ef06ec61c92c8845b43e2f05932a2ea6a5125890dce3c319',
			vipgocs_phpcs_cachedb_hash_calc(
				json_encode(
					array( 'test1', 'test2' )
				)
			)
		);
	}

	/**
	 * @covers ::vipgocs_phpcs_cachedb_db_open, ::vipgocs_phpcs_cachedb_db_close, ::vipgocs_phpcs_cachedb_add, ::vipgocs_phpcs_cachedb_get
	 */
	public function testCacheDbNormalUsage() {
		$phpcs_options = array(
			'phpcs-severity' => $this->options['phpcs-severity'],
			'phpcs-standard' => $this->options['phpcs-standard']
		);
		
		$db_conn = vipgocs_phpcs_cachedb_db_open(
			$this->options['phpcs-cachedb']
		);

		/*
		 * Handle file1
		 */
		$results_tmp_file1_original = array(
			array(
				'message'	=> 'Msg 1',
				'source'	=> VIPGOCS_PHPCS_SOURCE_INTERNAL,
				'severity'	=> 5,
				'fixable'	=> false,
				'type'		=> 'WARNING',
				'line'		=> 10,
				'column'	=> 50,
			),
			array(
				'message'	=> 'Msg 2',
				'source'	=> VIPGOCS_PHPCS_SOURCE_INTERNAL,
				'severity'	=> 7,
				'fixable'	=> true,
				'type'		=> 'ERROR',
				'line'		=> 20,
				'column'	=> 90,
			)
		);

		vipgocs_phpcs_cachedb_add(
			$db_conn,
			$this->temp_files['tmp_file1'],
			$phpcs_options,
			$results_tmp_file1_original
		);

		/*
		 * Handle file2
		 */
		$results_tmp_file2_original = array(
			array(
				'message'	=> 'Msg 5',
				'source'	=> VIPGOCS_PHPCS_SOURCE_INTERNAL,
				'severity'	=> 10,
				'fixable'	=> true,
				'type'		=> 'ERROR',
				'line'		=> 200,
				'column'	=> 900,
			)
		);

		vipgocs_phpcs_cachedb_add(
			$db_conn,
			$this->temp_files['tmp_file2'],
			$phpcs_options,
			$results_tmp_file2_original
		);

		/*
		 * Handle file3
		 */
		$results_tmp_file3_original = array(
			// No results
		);

		vipgocs_phpcs_cachedb_add(
			$db_conn,
			$this->temp_files['tmp_file3'],
			$phpcs_options,
			$results_tmp_file3_original
		);

		/*
		 * Get cached results for all
		 * files, check if they match
		 * the original results.
		 */

		$cached_zero_results = false;

		$results_tmp_file1_cached = vipgocs_phpcs_cachedb_get(
			$db_conn,
			$this->temp_files['tmp_file1'],
			$phpcs_options,
			$cached_zero_results
		);		

		$this->assertSame(
			$results_tmp_file1_original,
			$results_tmp_file1_cached
		);

		$this->assertFalse(
			$cached_zero_results
		);

		$cached_zero_results = false;
		$results_tmp_file2_cached = vipgocs_phpcs_cachedb_get(
			$db_conn,
			$this->temp_files['tmp_file2'],
			$phpcs_options,
			$cached_zero_results
		);

		$this->assertSame(
			$results_tmp_file2_original,
			$results_tmp_file2_cached
		);

		$this->assertFalse(
			$cached_zero_results
		);

		$cached_zero_results = true;
		$results_tmp_file3_cached = vipgocs_phpcs_cachedb_get(
			$db_conn,
			$this->temp_files['tmp_file3'],
			$phpcs_options,
			$cached_zero_results
		);

		$this->assertSame(
			$results_tmp_file3_original,
			$results_tmp_file3_cached
		);

		$this->assertTrue(
			$cached_zero_results
		);


		/*
		 * Close DB connection.
		 */
		vipgocs_phpcs_cachedb_db_close(
			$db_conn
		);
	}

	/**
	 * Handle cases where results
	 * is added for a file, but then overwritten.
	 *
	 * @covers ::vipgocs_phpcs_cachedb_add, ::vipgocs_phpcs_cachedb_get,
	 */
	public function testCacheDbOverwrite1() {
		$phpcs_options = array(
			'phpcs-severity' => $this->options['phpcs-severity'],
			'phpcs-standard' => $this->options['phpcs-standard']
		);
		
		$db_conn = vipgocs_phpcs_cachedb_db_open(
			$this->options['phpcs-cachedb']
		);

		/*
		 * Handle file1
		 */
		$results_tmp_file1_original = array(
			array(
				'message'	=> 'Msg 1',
				'source'	=> VIPGOCS_PHPCS_SOURCE_INTERNAL,
				'severity'	=> 5,
				'fixable'	=> false,
				'type'		=> 'WARNING',
				'line'		=> 10,
				'column'	=> 50,
			),
			array(
				'message'	=> 'Msg 2',
				'source'	=> VIPGOCS_PHPCS_SOURCE_INTERNAL,
				'severity'	=> 7,
				'fixable'	=> true,
				'type'		=> 'ERROR',
				'line'		=> 20,
				'column'	=> 90,
			)
		);

		vipgocs_phpcs_cachedb_add(
			$db_conn,
			$this->temp_files['tmp_file1'],
			$phpcs_options,
			$results_tmp_file1_original
		);


		/*
		 * Handle file2
		 */
		$results_tmp_file2_original = array(
			array(
				'message'	=> 'Msg 9',
				'source'	=> VIPGOCS_PHPCS_SOURCE_INTERNAL,
				'severity'	=> 9,
				'fixable'	=> false,
				'type'		=> 'WARNING',
				'line'		=> 200,
				'column'	=> 30,
			),
		);

		vipgocs_phpcs_cachedb_add(
			$db_conn,
			$this->temp_files['tmp_file2'],
			$phpcs_options,
			$results_tmp_file2_original
		);

		/*
		 * Overwrite file1 with empty results
		 */
		$results_tmp_file1_original = array(
		);

		vipgocs_phpcs_cachedb_add(
			$db_conn,
			$this->temp_files['tmp_file1'],
			$phpcs_options,
			$results_tmp_file1_original
		);

		/*
		 * Now file1 should have zero results
		 * while file2 should have results.
		 * Check if this is the case.
		 */

		$cached_zero_results = false;

		$results_tmp_file1_cached = vipgocs_phpcs_cachedb_get(
			$db_conn,
			$this->temp_files['tmp_file1'],
			$phpcs_options,
			$cached_zero_results
		);

		$this->assertSame(
			$results_tmp_file1_original,
			$results_tmp_file1_cached
		);

		$this->assertTrue(
			$cached_zero_results
		);

		$results_tmp_file2_cached = vipgocs_phpcs_cachedb_get(
			$db_conn,
			$this->temp_files['tmp_file2'],
			$phpcs_options,
			$cached_zero_results
		);

		$this->assertSame(
			$results_tmp_file2_original,
			$results_tmp_file2_cached
		);

		$this->assertFalse(
			$cached_zero_results
		);

		/*
		 * Close DB connection.
		 */
		vipgocs_phpcs_cachedb_db_close(
			$db_conn
		);
	}

	/**
	 * Handle cases where results
	 * is added for a file, but then removed.
	 *
	 * @covers ::vipgocs_phpcs_cachedb_remove
	 */
	public function testCacheAddThenRemove() {
		$phpcs_options = array(
			'phpcs-severity' => $this->options['phpcs-severity'],
			'phpcs-standard' => $this->options['phpcs-standard']
		);

		$db_conn = vipgocs_phpcs_cachedb_db_open(
			$this->options['phpcs-cachedb']
		);

		/*
		 * Handle file1 - add and remove results.
		 */
		$results_tmp_file1_original = array(
			array(
				'message'	=> 'Msg 1',
				'source'	=> VIPGOCS_PHPCS_SOURCE_INTERNAL,
				'severity'	=> 1,
				'fixable'	=> false,
				'type'		=> 'WARNING',
				'line'		=> 1,
				'column'	=> 2,
			),
		);

		/*
		 * Add results for file.
		 */
		vipgocs_phpcs_cachedb_add(
			$db_conn,
			$this->temp_files['tmp_file1'],
			$phpcs_options,
			$results_tmp_file1_original
		);

		/*
		 * Check if successful.
		 */
		$cached_zero_results = false;

		$results_tmp_file1_cached = vipgocs_phpcs_cachedb_get(
			$db_conn,
			$this->temp_files['tmp_file1'],
			$phpcs_options,
			$cached_zero_results
		);

		$this->assertSame(
			$results_tmp_file1_original,
			$results_tmp_file1_cached
		);

		$this->assertFalse(
			$cached_zero_results
		);

		/*
		 * Remove results for the file.
		 */
		vipgocs_phpcs_cachedb_remove(
			$db_conn,
			$this->temp_files['tmp_file1'],
			$phpcs_options
		);

		/*
		 * Check if successful.
		 */
		$results_tmp_file1_cached = vipgocs_phpcs_cachedb_get(
			$db_conn,
			$this->temp_files['tmp_file1'],
			$phpcs_options,
			$cached_zero_results
		);

		$this->assertEmpty(
			$results_tmp_file1_cached
		);

		$this->assertFalse(
			$cached_zero_results
		);
	}

	/**
	 * Test cleaning of database.
	 *
	 * @covers ::vipgocs_phpcs_cachedb_db_vacuum
	 */
	public function testCacheAddThenClean() {
		$phpcs_options = array(
			'phpcs-severity' => $this->options['phpcs-severity'],
			'phpcs-standard' => $this->options['phpcs-standard']
		);

		$db_conn = vipgocs_phpcs_cachedb_db_open(
			$this->options['phpcs-cachedb']
		);

		/*
		 * Handle file1 - add results.
		 */
		$results_tmp_file1_original = array(
			array(
				'message'	=> 'Msg 1',
				'source'	=> VIPGOCS_PHPCS_SOURCE_INTERNAL,
				'severity'	=> 1,
				'fixable'	=> false,
				'type'		=> 'WARNING',
				'line'		=> 1,
				'column'	=> 2,
			),
		);

		/*
		 * Add results for file.
		 */
		vipgocs_phpcs_cachedb_add(
			$db_conn,
			$this->temp_files['tmp_file1'],
			$phpcs_options,
			$results_tmp_file1_original
		);

		/*
		 * Clean DB
		 */
		vipgocs_phpcs_cachedb_db_vacuum(
			$db_conn
		);

		/*
		 * Check if successful.
		 */
		$cached_zero_results = false;

		$results_tmp_file1_cached = vipgocs_phpcs_cachedb_get(
			$db_conn,
			$this->temp_files['tmp_file1'],
			$phpcs_options,
			$cached_zero_results
		);

		$this->assertSame(
			$results_tmp_file1_original,
			$results_tmp_file1_cached
		);

		$this->assertFalse(
			$cached_zero_results
		);
	}
}
