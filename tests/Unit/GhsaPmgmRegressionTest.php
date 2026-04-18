<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * Regression test for GHSA-pmgm-67h9-59hw.
 *
 * Fix: ldap_escape($dn, \"\", LDAP_ESCAPE_FILTER) before filter construction
 *
 * Source-scan invariants that would fail if the fix were reverted. Each
 * assertion targets a pattern introduced by the fix commit.
 */

test('lib/ldap.php contains the pmgm fix', function () {
	$src = file_get_contents(__DIR__ . '/../../lib/ldap.php');
	expect($src)->not->toBeFalse();
	// Fix-specific assertion anchors below:
	expect($src)->toContain('ldap_escape(');
	expect($src)->toContain('LDAP_ESCAPE_FILTER');
});
