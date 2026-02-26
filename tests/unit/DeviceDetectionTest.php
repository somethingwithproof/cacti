<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

use PHPUnit\Framework\TestCase;

/**
 * Tests for SNMP disk device detection regex patterns used in
 * ss_net_snmp_disk_io.php and ss_net_snmp_disk_bytes.php
 */
class DeviceDetectionTest extends TestCase {
	/**
	 * Device prefix regex from ss_net_snmp_disk_io.php line 158.
	 * Must match: sd, nvme, vm, xvd, vd, hd, md, dm-
	 */
	private const DEVICE_PREFIX_PATTERN = '/^(sd|nvme|vm|xvd|vd|hd|md|dm-)/';

	/**
	 * Partition detection: skip entries ending in digits unless they
	 * are nvme base devices (nvme0n1) or md RAID devices (md0).
	 */
	private const PARTITION_PATTERN = '/(?:p\d+|\d+)$/';
	private const BASE_DEVICE_EXCEPTION = '/^(?:nvme\d+n\d+|md\d+|dm-\d+)$/';

	private function isBaseDevice(string $name) : bool {
		if (!preg_match(self::DEVICE_PREFIX_PATTERN, $name)) {
			return false;
		}

		if (preg_match(self::PARTITION_PATTERN, $name) && !preg_match(self::BASE_DEVICE_EXCEPTION, $name)) {
			return false;
		}

		return true;
	}

	// --- Prefix matching ---

	public function testSdaMatches() : void {
		$this->assertTrue($this->isBaseDevice('sda'));
	}

	public function testNvme0n1Matches() : void {
		$this->assertTrue($this->isBaseDevice('nvme0n1'));
	}

	public function testVmdkMatches() : void {
		$this->assertTrue($this->isBaseDevice('vmsomething'));
	}

	public function testXvdaMatches() : void {
		$this->assertTrue($this->isBaseDevice('xvda'));
	}

	public function testVdaMatches() : void {
		$this->assertTrue($this->isBaseDevice('vda'));
	}

	public function testHdaMatches() : void {
		$this->assertTrue($this->isBaseDevice('hda'));
	}

	public function testMd0Matches() : void {
		$this->assertTrue($this->isBaseDevice('md0'));
	}

	public function testDmDashMatches() : void {
		$this->assertTrue($this->isBaseDevice('dm-0'));
	}

	// --- Unknown prefixes rejected ---

	public function testLoopbackRejected() : void {
		$this->assertFalse($this->isBaseDevice('loop0'));
	}

	public function testSrRejected() : void {
		$this->assertFalse($this->isBaseDevice('sr0'));
	}

	// --- Partition detection ---

	public function testSda1IsPartition() : void {
		$this->assertFalse($this->isBaseDevice('sda1'));
	}

	public function testNvme0n1p1IsPartition() : void {
		$this->assertFalse($this->isBaseDevice('nvme0n1p1'));
	}

	public function testNvme0n1IsNotPartition() : void {
		$this->assertTrue($this->isBaseDevice('nvme0n1'));
	}

	public function testMd0IsNotPartition() : void {
		$this->assertTrue($this->isBaseDevice('md0'));
	}

	public function testMd127IsNotPartition() : void {
		$this->assertTrue($this->isBaseDevice('md127'));
	}

	public function testXvda1IsPartition() : void {
		$this->assertFalse($this->isBaseDevice('xvda1'));
	}

	public function testVda2IsPartition() : void {
		$this->assertFalse($this->isBaseDevice('vda2'));
	}

	public function testDmDash1IsBaseDevice() : void {
		// dm-N are device-mapper (LVM) base devices, not partitions
		$this->assertTrue($this->isBaseDevice('dm-1'));
	}
}
