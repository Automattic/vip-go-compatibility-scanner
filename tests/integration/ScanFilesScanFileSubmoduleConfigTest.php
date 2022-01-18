<?php

namespace Vipgocs\Tests\Integration;

require_once( __DIR__ . '/IncludesForTests.php' );

use PHPUnit\Framework\TestCase;

// phpcs:disable PSR1.Files.SideEffects

final class ScanFilesScanFileSubmoduleConfigTest extends TestCase {
	/*
	 * Set up a folder with partial git structure
	 * so gitmodule info can be fetched.
	 */
	protected function _git_repo_setup() {
		$this->local_git_repo = null;

		/*
		 * Create temporary directory
		 */
		$cmd = sprintf(
			'%s -d %s',
			escapeshellcmd( 'mktemp' ),
			escapeshellarg( sys_get_temp_dir() . '/vipgocs-XXXXXX' )
		);

		$result = shell_exec( $cmd );

		$this->local_git_repo = trim( $result );

		/*
		 * Add submodule.
		 */
		file_put_contents(
			$this->local_git_repo . '/.gitmodules',
			'[submodule "testmodule1"]' . PHP_EOL .
			'path = plugins/submodulefolder-1' . PHP_EOL .
			'url = https://github.com/test-submodule-owner/test-submodule-1.git' . PHP_EOL
		);
	}

	/*
	 * Remove temporary git repository.
	 */
	protected function _git_repo_teardown() {
		vipgoci_unittests_remove_temporary_folder_safely(
			$this->local_git_repo
		);

		unset( $this->local_git_repo );
	}

	protected function setUp() :void {
		$this->options = array(
			'repo-owner'	=> 'test-owner',
			'repo-name'	=> 'test-name',
		);

		$this->_git_repo_setup();

		$this->options['local-git-repo'] = $this->local_git_repo;
		$this->options['commit'] = 'abc' . time();
	}

	protected function tearDown() :void {
		$this->_git_repo_teardown();

		unset(
			$this->local_git_repo,
			$this->options,
		);
	}

	/**
	 * @covers ::vipgocs_scan_file_submodule_config
	 */
	public function testSubmoduleConfigNoSubmodule() {
		list(
			$github_commit_url,
			$file_relative_path_without_submodule
		) =
		vipgocs_scan_file_submodule_config(
			$this->options,
			'plugins/submodulefolder-1/file1.php',
			null
		);

		$this->assertSame(
			'https://github.com/test-owner/test-name/blob/' . $this->options['commit'],
			$github_commit_url
		);

		$this->assertNull(
			$file_relative_path_without_submodule
		);
	}

	/**
	 * @covers ::vipgocs_scan_file_submodule_config
	 */
	public function testSubmoduleConfigWithSubmodule() {
		$submodule_commit_id = 'xyz' . time();

		list(
			$github_commit_url,
			$file_relative_path_without_submodule
		) =
		vipgocs_scan_file_submodule_config(
			$this->options,
			'plugins/submodulefolder-1/file1.php',
			array(
				'submodule_path'	=> 'plugins/submodulefolder-1',
				'commit_id'		=> $submodule_commit_id,
			)
		);

		$this->assertSame(
			'https://github.com/test-submodule-owner/test-submodule-1/blob/' . $submodule_commit_id,
			$github_commit_url
		);

		$this->assertSame(
			'file1.php',
			$file_relative_path_without_submodule
		);
	}
}


