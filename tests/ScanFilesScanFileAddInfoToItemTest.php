<?php

namespace Vipgocs\tests;

require_once( __DIR__ . '/IncludesForTests.php' );

use PHPUnit\Framework\TestCase;

// phpcs:disable PSR1.Files.SideEffects

final class ScanFilesScanFileAddInfoToItemTest extends TestCase {
	/**
	 * @covers ::vipgocs_scan_file_add_info_to_item
	 */
	public function testAddInfoToItem() {
		/*
		 * Construct results
		 */
		$file1_messages = array(
			array(
				'message'	=> 'Message 1',
				'source'	=> VIPGOCS_PHPCS_SOURCE_INTERNAL,
				'severity'	=> 1,
				'fixable'	=> false,
				'type'		=> 'ERROR',
				'line'		=> 2,
				'column'	=> 3,
			),

			array(
				'message'	=> 'Message 2',
				'source'	=> VIPGOCS_PHPCS_SOURCE_INTERNAL,
				'severity'	=> 1,
				'fixable'	=> false,
				'type'		=> 'ERROR',
				'line'		=> 4,
				'column'	=> 5,
			),
		);

		$file2_messages = array(
			array(
				'message'	=> 'Message 2',
				'source'	=> VIPGOCS_PHPCS_SOURCE_INTERNAL,
				'severity'	=> 3,
				'fixable'	=> true,
				'type'		=> 'WARNING',
				'line'		=> 9,
				'column'	=> 8,
			)
		);

		$file_results = array(
			'file_issues_arr_master' => array(
				'files' => array(
					'file1.php' => array(
						'messages' => $file1_messages,
					),

					'file2.php' => array(
						'messages' => $file2_messages,
					)
				)
			)
		);

		/*
		 * Call function.
		 */
		vipgocs_scan_file_add_info_to_item(
			$file_results,
			'file1.php',
			'folder1/file1.php',
			'https://github.com/test1/test2/commit/00000/folder1/file1.php',
			array( 'in_submodule' ),
			'folder1/file1.php'
		);

		/*
		 * Check if $file_results is as expected.
		 */
		$file1_results_expected = array(
			array(
				'message'			=> 'Message 1',
				'source'			=> VIPGOCS_PHPCS_SOURCE_INTERNAL,
				'severity'			=> 1,
				'fixable'			=> false,
				'type'				=> 'ERROR',
				'line'				=> 2,
				'column'			=> 3,
				'github_commit_url'		=> 'https://github.com/test1/test2/commit/00000/folder1/file1.php',
				'file_relative_path'		=> 'folder1/file1.php',
				'file_is_in_submodule'		=> true,
				'file_path_without_submodule'	=> 'folder1/file1.php',
			),

			array(
				'message'			=> 'Message 2',
				'source'			=> VIPGOCS_PHPCS_SOURCE_INTERNAL,
				'severity'			=> 1,
				'fixable'			=> false,
				'type'				=> 'ERROR',
				'line'				=> 4,
				'column'			=> 5,
				'github_commit_url'		=> 'https://github.com/test1/test2/commit/00000/folder1/file1.php',
				'file_relative_path'		=> 'folder1/file1.php',
				'file_is_in_submodule'		=> true,
				'file_path_without_submodule'	=> 'folder1/file1.php',
			),
		);

		$file2_results_expected = array(
			array(
				'message'	=> 'Message 2',
				'source'	=> VIPGOCS_PHPCS_SOURCE_INTERNAL,
				'severity'	=> 3,
				'fixable'	=> true,
				'type'		=> 'WARNING',
				'line'		=> 9,
				'column'	=> 8,
			)
		);

		$file_results_expected = array(
			'file_issues_arr_master' => array(
				'files' => array(
					'file1.php' => array(
						'messages' =>
							$file1_results_expected
						),
					'file2.php' => array(
						'messages' =>
							$file2_results_expected
						)
					)
				)
		);
	
		$this->assertSame(
			$file_results,
			$file_results_expected
		);
	}
}
