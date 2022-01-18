<?php

namespace Vipgocs\Tests\Unit;

require_once( __DIR__ . './../../open-issues.php' );

use PHPUnit\Framework\TestCase;

// phpcs:disable PSR1.Files.SideEffects

final class OpenIssuesFilterIgnorableIssuesTest extends TestCase {
	/**
	 * @covers ::vipgocs_filter_ignorable_issues
	 */
	public function testFilterIgnorableIssues1() {
		$results = array(
			'files'	=> array(
				'file1.php'	=> array(
					'messages'	=> array(
						array(
							'message'	=> 'Having more than 100 posts returned per page may lead to severe performance problems. ',
						),

						array(
							'message'	=> 'Having more than 100 posts returned per page may lead to severe performance problems',
						),

						array(
							'message'	=> '. Having more than 100 posts returned per page may lead to severe performance problems',
						),

						array(
							'message'	=> 'Test entry 1.',
						),

						array(
							'message'	=> ' Test entry 4',
						),
					)
				),

				'file2.php'	=> array(
					'messages' => array(
						array(
							'message'	=> 'Test entry 30',
						),

						array(
							'message'	=> 'Test entry 40',
						),
					),
				),

				'file3.php'	=> array(
					'messages'	=> array(
						array(
							'message'	=> 'Having more than 100 posts returned per page may lead to severe performance problems',
						)
					)
				),
			),
		);

		$results_filtered = vipgocs_filter_ignorable_issues(
			array(
				'Having more than 100 posts returned per page may lead to severe performance problems',
			),
			$results
		);

		$this->assertSame(
			array(
				'files'	=> array(
					'file1.php'	=> array(
						'messages'	=> array(
							array(
								'message'	=> 'Test entry 1.',
							),
							array(
								'message'	=> ' Test entry 4',
							),
						)
					),

					'file2.php'	=> array(
						'messages' => array(
							array(
								'message'	=> 'Test entry 30',
							),
							array(
								'message'	=> 'Test entry 40',
							),
						),
					),

					'file3.php'	=> array(
						'messages'	=> array(
						)
					),
				),
			),
			$results_filtered
		);
	}
}
