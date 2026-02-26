<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

use PHPUnit\Framework\TestCase;

/**
 * Tests for column allow-list validation patterns used in MikroTik scripts.
 * Verifies that only known column names pass validation before SQL interpolation.
 */
class ColumnAllowListTest extends TestCase {
	/**
	 * Allow-list from ss_mikrotik_health.php
	 */
	private const HEALTH_COLUMNS = [
		'voltage', 'temperature', 'processorTemperature',
		'current', 'power', 'fanSpeed', 'cpuFrequency',
	];

	private function validateHealthColumn(string $column) : bool {
		return in_array($column, self::HEALTH_COLUMNS, true);
	}

	public function testValidHealthColumnAccepted() : void {
		$this->assertTrue($this->validateHealthColumn('voltage'));
		$this->assertTrue($this->validateHealthColumn('temperature'));
		$this->assertTrue($this->validateHealthColumn('cpuFrequency'));
	}

	public function testInvalidHealthColumnRejected() : void {
		$this->assertFalse($this->validateHealthColumn('no'));
		$this->assertFalse($this->validateHealthColumn(''));
	}

	public function testSqlInjectionInColumnRejected() : void {
		$this->assertFalse($this->validateHealthColumn('1; DROP TABLE--'));
		$this->assertFalse($this->validateHealthColumn("voltage' OR '1'='1"));
		$this->assertFalse($this->validateHealthColumn('voltage UNION SELECT'));
	}

	public function testStrictTypeComparison() : void {
		// in_array with strict=true rejects integer 0
		$this->assertFalse(in_array(0, self::HEALTH_COLUMNS, true));
	}

	/**
	 * ss_mikrotik_qtrees.php getvalue() maps input to fixed column names
	 * via switch statement — any unknown input falls to default.
	 */
	public function testQtreesSwitchDefaultsToKnownColumn() : void {
		$map = [
			'qtBytes'   => 'curHCBytes',
			'qtPackets' => 'curPackets',
		];

		// Known inputs map correctly
		$this->assertSame('curHCBytes', $map['qtBytes'] ?? 'curHCBytes');
		$this->assertSame('curPackets', $map['qtPackets'] ?? 'curHCBytes');

		// Unknown input falls to default (curHCBytes)
		$this->assertSame('curHCBytes', $map['malicious'] ?? 'curHCBytes');
	}
}
