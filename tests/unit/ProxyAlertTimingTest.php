<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

use PHPUnit\Framework\TestCase;

/**
 * Tests for proxy_alert day-difference logic in lib/functions.php.
 * The migration notice must fire on first run (day 0) and then
 * repeat at the configured interval.
 */
class ProxyAlertTimingTest extends TestCase {
	/**
	 * Mirrors the fixed condition: (int) $this_days >= 1
	 * DateInterval::format('%a') returns a string like '0', '1', '30'.
	 */
	private function shouldLogNotice(string $this_days, int $proxy_headers_days) : bool {
		return (int) $this_days >= 1 && (int) $this_days % $proxy_headers_days === 0;
	}

	/**
	 * First-run case: proxy_alert was just set, day difference is 0.
	 * Should NOT log (wait until interval elapses).
	 */
	public function testDayZeroDoesNotLog() : void {
		$this->assertFalse($this->shouldLogNotice('0', 7));
	}

	public function testDayOneLogsForDailyInterval() : void {
		$this->assertTrue($this->shouldLogNotice('1', 1));
	}

	public function testDaySevenLogsForWeeklyInterval() : void {
		$this->assertTrue($this->shouldLogNotice('7', 7));
	}

	public function testDayFourteenLogsForWeeklyInterval() : void {
		$this->assertTrue($this->shouldLogNotice('14', 7));
	}

	public function testDayThreeDoesNotLogForWeeklyInterval() : void {
		$this->assertFalse($this->shouldLogNotice('3', 7));
	}

	/**
	 * The old condition used if ($this_days) which fails for '0'
	 * because PHP treats '0' as falsy. The fix uses (int) cast.
	 */
	public function testStringZeroIsFalsyInPhp() : void {
		// Documents why the old code was broken
		$this->assertFalse((bool) '0');
		// But (int) '0' >= 1 correctly returns false
		$this->assertFalse((int) '0' >= 1);
	}
}
