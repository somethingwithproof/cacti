<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * Regression test for GHSA-4494-26m2-mvhm.
 *
 * Fix: lockout check before returning user ID in check_auth_cookie
 *
 * Source-scan invariants that would fail if the fix were reverted. Each
 * assertion targets a pattern introduced by the fix commit.
 */

test('lib/auth.php contains the 4494 fix', function () {
	$src = file_get_contents(__DIR__ . '/../../lib/auth.php');
	expect($src)->not->toBeFalse();
	// Fix-specific assertion anchors below:
	expect($src)->toContain('auth_process_lockout_check');
});
