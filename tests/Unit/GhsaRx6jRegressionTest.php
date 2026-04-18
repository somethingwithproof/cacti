<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * Regression test for GHSA-rx6j-2pxr-p6gj.
 *
 * Fix: cacti_session_start(true) on guest→auth to regenerate session ID
 *
 * Source-scan invariants that would fail if the fix were reverted. Each
 * assertion targets a pattern introduced by the fix commit.
 */

test('include/auth.php contains the rx6j fix', function () {
	$src = file_get_contents(__DIR__ . '/../../include/auth.php');
	expect($src)->not->toBeFalse();
	// Fix-specific assertion anchors below:
	expect($src)->toContain('cacti_session_start(true)');
});
