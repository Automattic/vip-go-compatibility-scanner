<?php

require_once( __DIR__ . '/IncludesForTests.php' );

use PHPUnit\Framework\TestCase;

final class OptionsFileOptionProcessComplementaryTest extends TestCase {
	var $options = array(
	);

	var $text_file_path = null;

	protected function setUp() :void {
		$this->text_file_path = vipgoci_save_temp_file(
			'tmp-complementary-file',
			'txt',
			'My Text File Contents'
		);
	}

	protected function tearDown() :void {
		if ( ! empty(
			$this->text_file_path
		) {
			unlink(
				$this->text_file_path
			);
		}
	}
	
	/**
	 * @covers ::vipgocs_file_option_process_complementary
	 */
	public function testComplementaryOption1() {
		$this->options['my-text-file'] =
			$this->text_file_path;

		vipgocs_file_option_process_complementary(
			$this->options,
			'my-text'
		);

		$this->assertEquals(
			array(
				'my-text-file'	=> $this->text_file_path,
				'my-text'	=> 'My Text File Contents',
			),
			$this->options
		);
	}

	/**
	 * @covers ::vipgocs_file_option_process_complementary
	 */
	public function testComplementaryOption2() {
		$this->options['my-text'] =
			'MyText';

		vipgocs_file_option_process_complementary(
			$this->options,
			'my-text'
		);

		$this->assertEquals(
			array(
				'my-text'	=> 'MyText',
			),
			$this->options
		);
	}
}
