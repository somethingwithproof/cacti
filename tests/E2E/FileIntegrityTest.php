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

require_once dirname(__DIR__) . '/Helpers/CactiStubs.php';

// =====================================================================
// E2E File Integrity Tests
//
// Verify the entire changed codebase is syntactically valid and
// consistent. These tests scan actual source files to ensure no
// regressions have been introduced.
// =====================================================================

// Verify all modified PHP files parse without syntax errors
test('all modified PHP files have valid syntax', function () {
	$files = [
		'lib/rrd.php', 'lib/ping.php', 'lib/auth.php', 'lib/poller.php',
		'lib/data_query.php', 'lib/api_graph.php', 'lib/api_device.php',
		'lib/api_automation.php', 'lib/api_automation_tools.php',
		'lib/functions.php', 'lib/time.php', 'lib/utility.php',
		'graphs.php', 'graphs_new.php', 'host.php', 'host_templates.php',
		'user_log.php', 'user_admin.php', 'user_group_admin.php',
		'aggregate_graphs.php', 'aggregate_templates.php',
		'graph_templates.php',
		'data_sources.php', 'data_queries.php', 'rrdcleaner.php',
		'script_server.php', 'auth_changepassword.php',
		'automation_graph_rules.php', 'automation_tree_rules.php',
		'automation_snmp.php', 'cdef.php', 'color_templates.php',
		'cli/add_site.php', 'cli/repair_database.php',
		'cli/remove_broken_graphs.php', 'cli/batchgapfix.php',
		'cli/upgrade_database.php', 'cmd_realtime.php',
		'snmpagent_mibcache.php', 'snmpagent_persist.php',
	];

	$base = dirname(__DIR__, 2);
	foreach ($files as $file) {
		$path = $base . '/' . $file;
		if (file_exists($path)) {
			$output = [];
			$exitCode = 0;
			exec("php -l " . escapeshellarg($path) . " 2>&1", $output, $exitCode);
			expect($exitCode)->toBe(0, "Syntax error in {$file}: " . implode("\n", $output));
		}
	}
});

// Verify no strpos(...) !== false remains in files we converted to str_contains
test('converted files do not contain strpos !== false pattern', function () {
	$files = [
		'lib/rrd.php', 'lib/ping.php', 'lib/functions.php',
		'lib/poller.php', 'lib/utility.php', 'lib/time.php',
	];

	$base = dirname(__DIR__, 2);
	foreach ($files as $file) {
		$path = $base . '/' . $file;
		if (file_exists($path)) {
			$content = file_get_contents($path);
			// Check for the old pattern: strpos(...) !== false
			$has_old_pattern = preg_match('/strpos\s*\([^)]+\)\s*!==\s*false/', $content);
			expect($has_old_pattern)->toBe(0, "File {$file} still contains strpos() !== false pattern that should have been converted to str_contains()");
		}
	}
});

// Verify SQL patterns in auth.php use NOT EXISTS structure correctly
test('auth.php SQL patterns use NOT EXISTS with correlated subqueries', function () {
	$path = dirname(__DIR__, 2) . '/lib/auth.php';
	if (!file_exists($path)) {
		$this->markTestSkipped('lib/auth.php not found');
	}

	$content = file_get_contents($path);

	// Every NOT EXISTS should have a correlated predicate referencing the outer table
	preg_match_all('/NOT\s+EXISTS\s*\(\s*SELECT\s+1\s+FROM[^)]+\)/si', $content, $matches);

	foreach ($matches[0] as $match) {
		// Each NOT EXISTS subquery should reference the outer table (e.g., gl.id, h.id, gt.id)
		expect($match)->toMatch(
			'/\b\w+\.\w+\b/',
			"NOT EXISTS subquery missing correlated predicate: " . substr($match, 0, 100)
		);
	}
});

// Verify all implode near IN( in security-fixed files have intval
test('implode near IN clauses in auth.php use intval sanitization', function () {
	$path = dirname(__DIR__, 2) . '/lib/auth.php';
	if (!file_exists($path)) {
		$this->markTestSkipped('lib/auth.php not found');
	}

	$content = file_get_contents($path);

	// Find all IN( patterns that use implode — verify our security-hardened ones have intval
	preg_match_all('/IN\s*\(\s*[\'"]?\s*\.\s*implode\s*\([^)]*\)/i', $content, $matches);

	$intval_count = 0;

	foreach ($matches[0] as $match) {
		if (str_contains($match, 'intval')) {
			$intval_count++;
		}
	}

	// We hardened 5 implode IN clauses in auth.php with intval
	expect($intval_count)->toBeGreaterThanOrEqual(5);
});

// Verify files we modified don't accidentally reintroduce deprecated patterns
test('no deprecated each() function in modified files', function () {
	$files = [
		'lib/rrd.php', 'lib/ping.php', 'lib/auth.php', 'lib/poller.php',
		'lib/data_query.php', 'lib/api_graph.php', 'lib/functions.php',
	];

	$base = dirname(__DIR__, 2);
	foreach ($files as $file) {
		$path = $base . '/' . $file;
		if (file_exists($path)) {
			$content = file_get_contents($path);
			// The each() function was removed in PHP 8.0
			$has_each = preg_match('/\beach\s*\(/', $content);
			expect($has_each)->toBe(0, "File {$file} contains deprecated each() function");
		}
	}
});

// Verify no mysql_* (non-PDO) function calls remain in modified files
test('no legacy mysql_ function calls in modified files', function () {
	$files = [
		'lib/rrd.php', 'lib/auth.php', 'lib/poller.php',
		'lib/data_query.php', 'lib/api_graph.php', 'lib/functions.php',
	];

	$base = dirname(__DIR__, 2);
	foreach ($files as $file) {
		$path = $base . '/' . $file;
		if (file_exists($path)) {
			$content = file_get_contents($path);
			// mysql_* functions were removed in PHP 7.0
			$has_legacy = preg_match('/\bmysql_(connect|query|fetch|select_db|close|error|num_rows|result)\s*\(/', $content);
			expect($has_legacy)->toBe(0, "File {$file} contains legacy mysql_* function calls");
		}
	}
});

// Verify no create_function (removed in PHP 8.0) in modified files
test('no create_function calls in modified files', function () {
	$files = [
		'lib/rrd.php', 'lib/ping.php', 'lib/auth.php', 'lib/poller.php',
		'lib/data_query.php', 'lib/functions.php', 'lib/utility.php',
	];

	$base = dirname(__DIR__, 2);
	foreach ($files as $file) {
		$path = $base . '/' . $file;
		if (file_exists($path)) {
			$content = file_get_contents($path);
			$has_create_function = preg_match('/\bcreate_function\s*\(/', $content);
			expect($has_create_function)->toBe(0, "File {$file} contains deprecated create_function()");
		}
	}
});
