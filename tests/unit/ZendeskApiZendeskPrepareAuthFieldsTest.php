<?php

namespace Vipgocs\Tests\Unit;

require_once( __DIR__ . '/../../zendesk-api.php' );

use PHPUnit\Framework\TestCase;

// phpcs:disable PSR1.Files.SideEffects

final class ZendeskApiZendeskPrepareAuthFieldsTest extends TestCase {
	/**
	 * @covers ::vipgocs_zendesk_prepare_auth_fields
	 */
	public function testZendeskPrepareAuthFieldsAllEmpty() {
		$options = array(
		);

		/*
		 * No options, should return null.
		 */
		$this->assertNull(
			vipgocs_zendesk_prepare_auth_fields(
				$options
			)
		);
	}

	/**
	 * @covers ::vipgocs_zendesk_prepare_auth_fields
	 */
	public function testZendeskPrepareAuthFieldsSomeEmpty() {
		/*
		 * Subdomain set, but others missing, should return
		 * null.
		 */

		$options = array(
			'zendesk-subdomain' => 'mydomain'
		);

		$this->assertNull(
			vipgocs_zendesk_prepare_auth_fields(
				$options
			)
		);

		/*
		 * Username and subdomain set, should return null.
		 */
		$options['zendesk-access-username'] = 'username1';

		$this->assertNull(
			vipgocs_zendesk_prepare_auth_fields(
				$options
			)
		);
	}

	/**
	 * @covers ::vipgocs_zendesk_prepare_auth_fields
	 */
	public function testZendeskPrepareAuthFieldsPasswordAuth() {
		$options = array(
			'zendesk-subdomain' 		=> 'mydomain',
			'zendesk-access-username'	=> 'testuser1',
			'zendesk-access-password'	=> 'testpass1',
		);

		/*
		 * All set, should not return null.
		 */

		$this->assertSame(
			array(
				'zendesk-access-username'	=> 'testuser1',
				'zendesk-access-password'	=> 'testpass1',
			),
			vipgocs_zendesk_prepare_auth_fields(
				$options
			)
		);
	}

	/**
	 * @covers ::vipgocs_zendesk_prepare_auth_fields
	 */
	public function testZendeskPrepareAuthFieldsTokenAuth() {
		$options = array(
			'zendesk-subdomain' 		=> 'mydomain',
			'zendesk-access-username'	=> 'testuser1',
			'zendesk-access-token'		=> 'testtoken1',
		);

		/*
		 * All set, should not return null.
		 */

		$this->assertSame(
			array(
				'zendesk-access-username'	=> 'testuser1',
				'zendesk-access-token'		=> 'testtoken1',
			),
			vipgocs_zendesk_prepare_auth_fields(
				$options
			)
		);
	}
}
