<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Source contract for the import_package() write sink.
 *
 * GHSA-vp35-4h28-r883 / GHSA-j696-m433-87qq: the resolved-path check must run,
 * reject-and-continue must precede the fopen() write, and the raw
 * $config['base_path'] . "/$name" concatenation must be gone.
 */

$importSource = file_get_contents(__DIR__ . '/../../lib/import.php');

function ghsa_vp35_import_package_body($src) {
	$start = strpos($src, 'function import_package(');
	expect($start)->not->toBeFalse();

	$end = strpos($src, "\n}\n", $start);
	expect($end)->not->toBeFalse();

	return substr($src, $start, $end - $start);
}

test('GHSA-vp35-4h28-r883 / GHSA-j696-m433-87qq: import_package resolves the entry through the safe helper', function () use ($importSource) {
	$body = ghsa_vp35_import_package_body($importSource);

	expect($body)->toContain('import_package_resolve_file(');
});

test('GHSA-vp35-4h28-r883 / GHSA-j696-m433-87qq: rejection with continue precedes the fopen write', function () use ($importSource) {
	$body = ghsa_vp35_import_package_body($importSource);

	$rejectPos = strpos($body, '$filename === false');
	$fopenPos  = strpos($body, 'fopen(');

	expect($rejectPos)->not->toBeFalse();
	expect($fopenPos)->not->toBeFalse();
	expect($rejectPos)->toBeLessThan($fopenPos);

	$rejectBlock = substr($body, $rejectPos, strpos($body, 'fopen(') - $rejectPos);
	expect($rejectBlock)->toContain('continue;');
});

test('GHSA-vp35-4h28-r883 / GHSA-j696-m433-87qq: the raw base_path concatenation sink is gone', function () use ($importSource) {
	$body = ghsa_vp35_import_package_body($importSource);

	expect($body)->not->toContain('$config[\'base_path\'] . "/$name"');
});
