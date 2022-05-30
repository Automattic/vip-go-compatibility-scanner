<?php

namespace Vipgocs\Tests\Integration;

require_once( __DIR__ . '/IncludesForTests.php' );

use PHPUnit\Framework\TestCase;

// phpcs:disable PSR1.Files.SideEffects

final class ScanFilesScanFilesTest extends TestCase {
	var $options_git = array(
		'git-path'		=> null,
	);

	var $options_phpcs_scan = array(
		'phpcs-path'     => null,
		'phpcs-php-path' => null,
		'phpcs-standard' => null,
		'phpcs-severity' => null,
	);

	/*
	 * Set up a temporary git repository with
	 * some data.
	 */
	private function _local_git_repo_setup() {
		$this->local_git_repo = null;

		/*
		 * Create temporary directory for
		 * git repository.
		 */
		$cmd = sprintf(
			'%s -d %s',
			escapeshellcmd( 'mktemp' ),
			escapeshellarg( sys_get_temp_dir() . '/vipgocs-XXXXXX' )
		);

		$result = shell_exec( $cmd );

		$this->local_git_repo = trim( $result );

		/*
		 * Initialize the repository.
		 */
		$cmd = sprintf(
			'%s -C %s init .',
			escapeshellcmd( $this->options['git-path'] ),
			escapeshellarg( $this->local_git_repo )
		);

		$result = shell_exec( $cmd );

		/*
		 * Set identity, only for this repository.
		 */

		$cmd = sprintf(
			'%s -C %s config user.email "test@mydomain.com" && %s -C %s config user.name "test name"',
			escapeshellcmd( $this->options['git-path'] ),
			escapeshellarg( $this->local_git_repo ),
			escapeshellcmd( $this->options['git-path'] ),
			escapeshellarg( $this->local_git_repo )
		);

		$result = shell_exec( $cmd );

		/*
		 * Add data to the repository.
		 */

		file_put_contents(
			$this->local_git_repo . '/file1.php',
			'<?php' . PHP_EOL .
			'echo "foo" . time() . PHP_EOL;' . PHP_EOL .
			'echo esc_html( "foo" . time() . PHP_EOL );' . PHP_EOL .
			PHP_EOL
		);

		file_put_contents(
			$this->local_git_repo . '/file2.php',
			'<?php' . PHP_EOL .
			PHP_EOL .
			PHP_EOL .
			'echo esc_html( "foo" . time() . PHP_EOL );'  . PHP_EOL
		);

		file_put_contents(
			$this->local_git_repo . '/file3.php',
			'<?php' . PHP_EOL .
			PHP_EOL .
			'echo "foo" . time() . PHP_EOL;'  . PHP_EOL
		);

		file_put_contents(
			$this->local_git_repo . '/file4.php',
			PHP_EOL .
			"\t\n\t\n" .
			PHP_EOL
		);

		/*
		 * Commit the data.
		 */
		$cmd = sprintf(
			'%s -C %s add file1.php file2.php file3.php file4.php && %s -C %s commit -m test .',
			escapeshellcmd( $this->options['git-path'] ),
			escapeshellarg( $this->local_git_repo ),
			escapeshellcmd( $this->options['git-path'] ),
			escapeshellarg( $this->local_git_repo )
		);

		$result = shell_exec( $cmd );
	}

	private function _local_git_commit_url_get() {
		return 'https://github.com/test/test/blob/' .
			str_replace("'","", vipgoci_gitrepo_get_head(
				$this->options['local-git-repo']
			) );
	}

	/*
	 * Remove temporary git repository.
	 */
	private function _local_git_repo_teardown() {
		vipgoci_unittests_remove_temporary_folder_safely(
			$this->local_git_repo
		);

		unset( $this->local_git_repo );
	}

	/*
	 * Set up temporary PHPCSCacheDB
	 */
	private function _local_setup_phpcs_cachedb_conn() {
		$this->options['phpcs-cachedb-path'] = tempnam(
			sys_get_temp_dir(),
			'vipgocs_phpcs_cachedb_'
		);

		$this->options['phpcs-cachedb'] = vipgocs_phpcs_cachedb_db_open(
			$this->options['phpcs-cachedb-path']
		);
	}

	/*
	 * Remove temporary PHPCSCacheDB
	 */
	private function _local_remove_phpcs_cachedb_conn() {
		if ( ! empty( $this->options['phpcs-cachedb'] ) ) {
			vipgocs_phpcs_cachedb_db_close(
				$this->options['phpcs-cachedb']
			);

			$this->options['phpcs-cachedb'] = null;
		}

		if ( ! empty( $this->options['phpcs-cachedb-path'] ) ) {
			unlink( $this->options['phpcs-cachedb-path']  );

			$this->options['phpcs-cachedb-path']  = null;
		}
	}

	protected function setUp() :void {
		vipgoci_unittests_get_config_values(
			'git',
			$this->options_git
		);

		vipgoci_unittests_get_config_values(
			'phpcs-scan',
			$this->options_phpcs_scan
		);

		$this->options = array_merge(
			$this->options_git,
			$this->options_phpcs_scan,
			array(
				'repo-owner'			=> 'test',
				'repo-name'			=> 'test',
				'token'				=> 'test',
 				'phpcs-sniffs-exclude'		=> array(),
       				'phpcs-sniffs-include'		=> array(),
				'phpcs-runtime-set'		=> array(),
				'phpcs-severity'		=> 1,
				'phpcs-cachedb'			=> null,
				'phpcs-cachedb-path'		=> null,
				'skip-large-files'		=> false,
			)
		);

		$this->_local_git_repo_setup();

		$this->options['local-git-repo'] = $this->local_git_repo;

		$vipgoci_git_repo_head = vipgoci_gitrepo_get_head(
			$this->options['local-git-repo']
		);

		$vipgoci_git_repo_head =
			str_replace( '\'', '', $vipgoci_git_repo_head );

		$this->options['commit'] = $vipgoci_git_repo_head;

		/*
		 * Note: Do not set up PHPCSCacheDB here, is done
		 * in the tests that need it.
		 */
	}

	protected function tearDown() :void {
		$this->_local_git_repo_teardown();

		$this->_local_remove_phpcs_cachedb_conn();

		unset(
			$this->local_git_repo,
			$this->options,
			$this->options_git,
			$this->options_phpcs_scan
		);
	}

	private function getScanFilesGroupByFileWithEmptyFilesExpectedResults() {
		return array(
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
							'github_commit_url' => $this->_local_git_commit_url_get(),
							'file_relative_path' => 'file1.php',
							'file_is_in_submodule' => false,
							'file_path_without_submodule' => null,
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
							'github_commit_url' => $this->_local_git_commit_url_get(),
							'file_relative_path' => 'file3.php',
							'file_is_in_submodule' => false,
							'file_path_without_submodule' => null,
						)
					)
				),

				'file4.php' => array(
					'messages' => array(
						array(
							'message' => "No PHP code was found in this file and short open tags are not allowed by this install of PHP. This file may be using short open tags but PHP does not allow them.",
							'source' => 'Internal.NoCodeFound',
							'severity' => 5,
							'fixable' => false,
							'type' => 'WARNING',
							'line' => 1,
							'column' => 1,
							'github_commit_url' => $this->_local_git_commit_url_get(),
							'file_relative_path' => 'file4.php',
							'file_is_in_submodule' => false,
							'file_path_without_submodule' => null,
						)
					)
				),
			),
			'warnings' => 1,
			'errors' => 2,
			'fixable' => 0,
		);
	}

	/**
	 * @covers ::vipgocs_scan_files
	 */
	public function testScanFilesGroupByFileWithEmptyFilesSkipPhpcsCache() {
		$this->options['github-issue-group-by'] = 'file';
		$this->options['skip-empty-files'] = false;

		$scan_results = vipgocs_scan_files(
			$this->options,
			$this->options['phpcs-cachedb']
		);

		$this->assertSame(
			$this->getScanFilesGroupByFileWithEmptyFilesExpectedResults(),
			$scan_results
		);
	}

	/**
	 * @covers ::vipgocs_scan_files
	 */
	public function testScanFilesGroupByFileWithEmptyFilesUsePhpcsCache() {
		$this->options['github-issue-group-by'] = 'file';
		$this->options['skip-empty-files'] = false;

		$this->_local_setup_phpcs_cachedb_conn();

		$scan_results1 = vipgocs_scan_files(
			$this->options,
			$this->options['phpcs-cachedb']
		);

		$this->assertSame(
			$this->getScanFilesGroupByFileWithEmptyFilesExpectedResults(),
			$scan_results1
		);

		$scan_results2 = vipgocs_scan_files(
			$this->options,
			$this->options['phpcs-cachedb']
		);

		$this->assertSame(
			$scan_results1,
			$scan_results2
		);
	}

	private function getScanFilesGroupByFileWithoutEmptyFilesExpectedResults() {
		return array(
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
							'github_commit_url' => $this->_local_git_commit_url_get(),
							'file_relative_path' => 'file1.php',
							'file_is_in_submodule' => false,
							'file_path_without_submodule' => null,
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
							'github_commit_url' => $this->_local_git_commit_url_get(),
							'file_relative_path' => 'file3.php',
							'file_is_in_submodule' => false,
							'file_path_without_submodule' => null,
						)
					)
				)
				// No file4.php
			),
			'warnings' => 0,
			'errors' => 2,
			'fixable' => 0,
		);
	}

	/**
	 * @covers ::vipgocs_scan_files
	 */
	public function testScanFilesGroupByFileWithoutEmptyFilesSkipPhpcsCache() {
		$this->options['github-issue-group-by'] = 'file';
		$this->options['skip-empty-files'] = true;

		$scan_results = vipgocs_scan_files(
			$this->options,
			$this->options['phpcs-cachedb']
		);

		$this->assertSame(
			$this->getScanFilesGroupByFileWithoutEmptyFilesExpectedResults(),
			$scan_results
		);
	}

	/**
	 * @covers ::vipgocs_scan_files
	 */
	public function testScanFilesGroupByFileWithoutEmptyFilesUsePhpcsCache() {
		$this->options['github-issue-group-by'] = 'file';
		$this->options['skip-empty-files'] = true;

		$this->_local_setup_phpcs_cachedb_conn();

		$scan_results1 = vipgocs_scan_files(
			$this->options,
			$this->options['phpcs-cachedb']
		);

		$this->assertSame(
			$this->getScanFilesGroupByFileWithoutEmptyFilesExpectedResults(),
			$scan_results1
		);
	
		$scan_results2 = vipgocs_scan_files(
			$this->options,
			$this->options['phpcs-cachedb']
		);

		$this->assertSame(
			$scan_results1,
			$scan_results2
		);
	}

	private function getScanFilesGroupByFolderWithEmptyFilesExpectedResults() {
		return array(
			'files'	=> array(
				'/' => array(
					'messages' => array(
						array(
							'message' => "All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found 'time'.",
							'source' => 'WordPress.Security.EscapeOutput.OutputNotEscaped',
							'severity' => 5,
							'fixable' => false,
							'type' => 'ERROR',
							'line' => 2,
							'column' => 14,
							'github_commit_url' => $this->_local_git_commit_url_get(),
							'file_relative_path' => 'file1.php',
							'file_is_in_submodule' => false,
							'file_path_without_submodule' => null,
						),
						array(
							'message' => "All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found 'time'.",
							'source' => 'WordPress.Security.EscapeOutput.OutputNotEscaped',
							'severity' => 5,
							'fixable' => false,
							'type' => 'ERROR',
							'line' => 3,
							'column' => 14,
							'github_commit_url' => $this->_local_git_commit_url_get(),
							'file_relative_path' => 'file3.php',
							'file_is_in_submodule' => false,
							'file_path_without_submodule' => null,
						),
						array(
							'message' => "No PHP code was found in this file and short open tags are not allowed by this install of PHP. This file may be using short open tags but PHP does not allow them.",
							'source' => 'Internal.NoCodeFound',
							'severity' => 5,
							'fixable' => false,
							'type' => 'WARNING',
							'line' => 1,
							'column' => 1,
							'github_commit_url' => $this->_local_git_commit_url_get(),
							'file_relative_path' => 'file4.php',
							'file_is_in_submodule' => false,
							'file_path_without_submodule' => null,
						)
					)
				)
			),
			'warnings' => 1,
			'errors' => 2,
			'fixable' => 0,
		);
	}

	/**
	 * @covers ::vipgocs_scan_files
	 */
	public function testScanFilesGroupByFolderWithEmptyFilesSkipPhpcsCache() {
		$this->options['github-issue-group-by'] = 'folder';
		$this->options['skip-empty-files'] = false;

		$scan_results = vipgocs_scan_files(
			$this->options,
			$this->options['phpcs-cachedb']
		);

		$this->assertSame(
			$this->getScanFilesGroupByFolderWithEmptyFilesExpectedResults(),
			$scan_results
		);
	}

	/**
	 * @covers ::vipgocs_scan_files
	 */
	public function testScanFilesGroupByFolderWithEmptyFilesUsePhpcsCache() {
		$this->options['github-issue-group-by'] = 'folder';
		$this->options['skip-empty-files'] = false;

		$this->_local_setup_phpcs_cachedb_conn();

		$scan_results1 = vipgocs_scan_files(
			$this->options,
			$this->options['phpcs-cachedb']
		);

		$this->assertSame(
			$this->getScanFilesGroupByFolderWithEmptyFilesExpectedResults(),
			$scan_results1
		);

		$scan_results2 = vipgocs_scan_files(
			$this->options,
			$this->options['phpcs-cachedb']
		);


		$this->assertSame(
			$scan_results1,
			$scan_results2
		);
	}

	private function getScanFilesGroupByFolderWithoutEmptyFilesExpectedResults() {
		return array(
			'files'	=> array(
				'/' => array(
					'messages' => array(
						array(
							'message' => "All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found 'time'.",
							'source' => 'WordPress.Security.EscapeOutput.OutputNotEscaped',
							'severity' => 5,
							'fixable' => false,
							'type' => 'ERROR',
							'line' => 2,
							'column' => 14,
							'github_commit_url' => $this->_local_git_commit_url_get(),
							'file_relative_path' => 'file1.php',
							'file_is_in_submodule' => false,
							'file_path_without_submodule' => null,
						),
						array(
							'message' => "All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found 'time'.",
							'source' => 'WordPress.Security.EscapeOutput.OutputNotEscaped',
							'severity' => 5,
							'fixable' => false,
							'type' => 'ERROR',
							'line' => 3,
							'column' => 14,
							'github_commit_url' => $this->_local_git_commit_url_get(),
							'file_relative_path' => 'file3.php',
							'file_is_in_submodule' => false,
							'file_path_without_submodule' => null,
						)
						// No file4.php
					)
				)
			),
			'warnings' => 0,
			'errors' => 2,
			'fixable' => 0,
		);
	}

	/**
	 * @covers ::vipgocs_scan_files
	 */
	public function testScanFilesGroupByFolderWithoutEmptyFilesSkipPhpcsCache() {
		$this->options['github-issue-group-by'] = 'folder';
		$this->options['skip-empty-files'] = true;

		$scan_results = vipgocs_scan_files(
			$this->options,
			$this->options['phpcs-cachedb']
		);

		$this->assertSame(
			$this->getScanFilesGroupByFolderWithoutEmptyFilesExpectedResults(),
			$scan_results
		);
	}


	/**
	 * @covers ::vipgocs_scan_files
	 */
	public function testScanFilesGroupByFolderWithoutEmptyFilesUsePhpcsCache() {
		$this->options['github-issue-group-by'] = 'folder';
		$this->options['skip-empty-files'] = true;

		$this->_local_setup_phpcs_cachedb_conn();

		$scan_results1 = vipgocs_scan_files(
			$this->options,
			$this->options['phpcs-cachedb']
		);

		$this->assertSame(
			$this->getScanFilesGroupByFolderWithoutEmptyFilesExpectedResults(),
			$scan_results1
		);
	
		$scan_results2 = vipgocs_scan_files(
			$this->options,
			$this->options['phpcs-cachedb']
		);

		$this->assertSame(
			$scan_results1,
			$scan_results2
		);
	}
}
