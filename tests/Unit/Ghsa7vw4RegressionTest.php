<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * Regression test for GHSA-7vw4-2r73-89g2.
 *
 * Fix: replace system(\"test -f\") with @file_exists() in file_exists_2gb
 *
 * Source-scan invariants that would fail if the fix were reverted. Each
 * assertion targets a pattern introduced by the fix commit.
 */

test('lib/poller.php contains the 7vw4 fix', function () {
	$src = file_get_contents(__DIR__ . '/../../lib/poller.php');
	expect($src)->not->toBeFalse();
	// Fix-specific assertion anchors below:
	expect($src)->not->toContain('system("test -f');
	expect($src)->toContain('@file_exists');
});
