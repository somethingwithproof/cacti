<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Integration tests verifying that PHP modernization changes are consistent.
 *
 * These tests scan key files to ensure:
 *   - strpos(...) !== false has been replaced with str_contains()
 *   - cacti_strtoupper(substr(PHP_OS, 0, 3)) has been replaced with
 *     str_starts_with(PHP_OS, ...) or PHP_OS_FAMILY
 *   - isset($x) ? $x : $default ternaries have been converted to ?? where appropriate
 *   - Null coalescing operator ?? is present in modernized files
 */

$basePath = dirname(__DIR__, 2);

// --- No strpos(...) !== false in modernized files ---

test('lib/functions.php has no strpos not-equal-false pattern', function () use ($basePath) {
	$contents = file_get_contents($basePath . '/lib/functions.php');

	expect($contents)->not->toMatch('/strpos\s*\([^)]+\)\s*!==\s*false/');
});

test('lib/rrd.php has no strpos not-equal-false pattern', function () use ($basePath) {
	$contents = file_get_contents($basePath . '/lib/rrd.php');

	expect($contents)->not->toMatch('/strpos\s*\([^)]+\)\s*!==\s*false/');
});

test('lib/poller.php has no strpos not-equal-false pattern', function () use ($basePath) {
	$contents = file_get_contents($basePath . '/lib/poller.php');

	expect($contents)->not->toMatch('/strpos\s*\([^)]+\)\s*!==\s*false/');
});

test('graph_templates.php has no strpos not-equal-false pattern', function () use ($basePath) {
	$contents = file_get_contents($basePath . '/graph_templates.php');

	expect($contents)->not->toMatch('/strpos\s*\([^)]+\)\s*!==\s*false/');
});

test('aggregate_graphs.php has no strpos not-equal-false pattern', function () use ($basePath) {
	$contents = file_get_contents($basePath . '/aggregate_graphs.php');

	expect($contents)->not->toMatch('/strpos\s*\([^)]+\)\s*!==\s*false/');
});

test('data_sources.php has no strpos not-equal-false pattern', function () use ($basePath) {
	$contents = file_get_contents($basePath . '/data_sources.php');

	expect($contents)->not->toMatch('/strpos\s*\([^)]+\)\s*!==\s*false/');
});

test('lib/functions.php has no strpos equal-false pattern', function () use ($basePath) {
	$contents = file_get_contents($basePath . '/lib/functions.php');

	expect($contents)->not->toMatch('/strpos\s*\([^)]+\)\s*===\s*false/');
});

test('lib/rrd.php has no strpos equal-false pattern', function () use ($basePath) {
	$contents = file_get_contents($basePath . '/lib/rrd.php');

	expect($contents)->not->toMatch('/strpos\s*\([^)]+\)\s*===\s*false/');
});

// --- No cacti_strtoupper(substr(PHP_OS, 0, 3)) in modernized files ---

test('include/global.php does not use cacti_strtoupper(substr(PHP_OS)) pattern', function () use ($basePath) {
	$contents = file_get_contents($basePath . '/include/global.php');

	expect($contents)->not->toContain('cacti_strtoupper(substr(PHP_OS');
	expect($contents)->not->toContain('strtoupper(substr(PHP_OS');
});

test('include/global.php uses str_starts_with for PHP_OS detection', function () use ($basePath) {
	$contents = file_get_contents($basePath . '/include/global.php');

	expect($contents)->toContain("str_starts_with(PHP_OS, 'WIN')");
});

test('lib/functions.php does not use strtoupper(substr(PHP_OS, 0, 3)) pattern', function () use ($basePath) {
	$contents = file_get_contents($basePath . '/lib/functions.php');

	// The old pattern: cacti_strtoupper(substr(PHP_OS, 0, 3)) === 'WIN'
	expect($contents)->not->toMatch('/cacti_strtoupper\s*\(\s*substr\s*\(\s*PHP_OS\s*,\s*0\s*,\s*3\s*\)/');
	expect($contents)->not->toMatch('/strtoupper\s*\(\s*substr\s*\(\s*PHP_OS\s*,\s*0\s*,\s*3\s*\)/');
});

// --- Modernized files use str_contains ---

test('lib/functions.php uses str_contains for string searching', function () use ($basePath) {
	$contents = file_get_contents($basePath . '/lib/functions.php');

	expect($contents)->toContain('str_contains(');
});

test('lib/rrd.php uses str_contains for string searching', function () use ($basePath) {
	$contents = file_get_contents($basePath . '/lib/rrd.php');

	expect($contents)->toContain('str_contains(');
});

test('lib/poller.php uses str_contains for string searching', function () use ($basePath) {
	$contents = file_get_contents($basePath . '/lib/poller.php');

	expect($contents)->toContain('str_contains(');
});

// --- No isset($x) ? $x : $default in files we converted (check specific patterns) ---

test('graph_templates.php isset ternaries use object/array context not simple variable coalescing', function () use ($basePath) {
	$contents = file_get_contents($basePath . '/graph_templates.php');

	// The remaining isset ternaries in graph_templates.php fall into two categories:
	// 1. Object/array member access: isset($template_item) ? $template_item['id'] : '0'
	//    These check existence of the variable itself, then access a member -- not a ?? candidate.
	// 2. inject_form_variables() arguments: (isset($template) ? $template : [])
	//    These pass entire arrays as function arguments and are a recognized pattern
	//    in the Cacti form system that has not been converted.
	//
	// We verify that NO simple coalescing patterns exist OUTSIDE of inject_form_variables calls.
	$lines = explode("\n", $contents);
	$violations = [];

	foreach ($lines as $lineNum => $line) {
		// Skip lines that are part of inject_form_variables() calls -- recognized exception
		if (str_contains($line, 'inject_form_variables')) {
			continue;
		}

		// Match the simple `isset($var) ? $var : default` pattern that should use ??
		if (preg_match('/isset\s*\(\s*(\$\w+)\s*\)\s*\?\s*\1\s*:/', $line)) {
			$violations[] = 'Line ' . ($lineNum + 1) . ': ' . trim($line);
		}
	}

	expect($violations)->toBeEmpty();
});

test('aggregate_graphs.php has no simple isset-coalescing ternaries', function () use ($basePath) {
	$contents = file_get_contents($basePath . '/aggregate_graphs.php');
	$lines = explode("\n", $contents);

	foreach ($lines as $lineNum => $line) {
		if (preg_match('/isset\s*\(\s*(\$\w+)\s*\)\s*\?\s*\1\s*:/', $line)) {
			expect(true)->toBeFalse("Line " . ($lineNum + 1) . " has a simple isset ternary that should use ??: " . trim($line));
		}
	}

	expect(true)->toBeTrue();
});

test('data_sources.php has no simple isset-coalescing ternaries outside form helpers', function () use ($basePath) {
	$contents = file_get_contents($basePath . '/data_sources.php');
	$lines = explode("\n", $contents);
	$violations = [];

	foreach ($lines as $lineNum => $line) {
		// Skip inject_form_variables() calls -- recognized pattern in Cacti form system
		if (str_contains($line, 'inject_form_variables')) {
			continue;
		}

		if (preg_match('/isset\s*\(\s*(\$\w+)\s*\)\s*\?\s*\1\s*:/', $line)) {
			$violations[] = 'Line ' . ($lineNum + 1) . ': ' . trim($line);
		}
	}

	expect($violations)->toBeEmpty();
});

// --- Null coalescing operator is present in modernized files ---

test('lib/rrd.php uses null coalescing operator', function () use ($basePath) {
	$contents = file_get_contents($basePath . '/lib/rrd.php');

	// The rrd.php file uses ?? (e.g. $cf_ds_cache[$cf_ds_key] ?? 0)
	expect($contents)->toContain('??');
});

test('lib/functions.php uses modern PHP string functions', function () use ($basePath) {
	$contents = file_get_contents($basePath . '/lib/functions.php');

	// Should use modern string functions
	expect($contents)->toContain('str_contains(');
	expect($contents)->toContain('str_starts_with(');
});

test('include/global.php uses modern PHP string functions for OS detection', function () use ($basePath) {
	$contents = file_get_contents($basePath . '/include/global.php');

	// Modern pattern: str_starts_with(PHP_OS, 'WIN')
	expect($contents)->toContain('str_starts_with(PHP_OS');
	// Old pattern should be gone
	expect($contents)->not->toContain("substr(PHP_OS, 0, 3)");
});
