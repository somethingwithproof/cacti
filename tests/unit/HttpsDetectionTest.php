<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

use PHPUnit\Framework\TestCase;

/**
 * Tests for HTTPS detection logic used in include/global.php force_https redirect.
 * Covers edge cases where $_SERVER['HTTPS'] is 'off' or empty.
 */
class HttpsDetectionTest extends TestCase {
	/**
	 * Mirrors the detection logic from include/global.php
	 */
	private function isHttps(array $server) : bool {
		return (
			isset($server['HTTPS']) &&
			$server['HTTPS'] !== '' &&
			strtolower($server['HTTPS']) !== 'off'
		);
	}

	public function testHttpsOnIsSecure() : void {
		$this->assertTrue($this->isHttps(['HTTPS' => 'on']));
	}

	public function testHttps1IsSecure() : void {
		$this->assertTrue($this->isHttps(['HTTPS' => '1']));
	}

	public function testHttpsAnyNonEmptyIsSecure() : void {
		$this->assertTrue($this->isHttps(['HTTPS' => 'yes']));
	}

	public function testHttpsOffIsNotSecure() : void {
		$this->assertFalse($this->isHttps(['HTTPS' => 'off']));
	}

	public function testHttpsOFFUppercaseIsNotSecure() : void {
		$this->assertFalse($this->isHttps(['HTTPS' => 'OFF']));
	}

	public function testHttpsOffMixedCaseIsNotSecure() : void {
		$this->assertFalse($this->isHttps(['HTTPS' => 'Off']));
	}

	public function testHttpsEmptyStringIsNotSecure() : void {
		$this->assertFalse($this->isHttps(['HTTPS' => '']));
	}

	public function testHttpsUnsetIsNotSecure() : void {
		$this->assertFalse($this->isHttps([]));
	}
}
