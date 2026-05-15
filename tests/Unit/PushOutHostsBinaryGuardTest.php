<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Source-pattern coverage for the push_out_hosts.php dash-prefix guard.
 * Verifies the guard inspects the resolved $php_binary value (not the
 * undefined $binary from the original code) and exits non-zero on reject.
 */

$repoRoot = __DIR__ . '/../..';
$source = file_get_contents("$repoRoot/cli/push_out_hosts.php");

test('dash-prefix guard reads $php_binary and references it widely', function () use ($source) {
	expect($source)->toContain("strpos(trim(\$php_binary), '-')");
	$matches = [];
	preg_match_all('/\$php_binary\b/', $source, $matches);
	expect(count($matches[0]))->toBeGreaterThanOrEqual(4);
});

test('dash-prefix guard exits non-zero and does not use the buggy return 1', function () use ($source) {
	expect($source)->toContain('exit(1);');
	$marker = 'Rejected PHP binary starting with dash';
	$pos = strpos($source, $marker);
	expect($pos)->not->toBeFalse();
	$window = substr($source, $pos, 200);
	expect($window)->toContain('exit(1);');
	expect(strpos($window, 'return 1;'))->toBeFalse();
});
