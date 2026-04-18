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
require_once dirname(__DIR__, 2) . '/include/global.php';

// GHSA-84q3-92xc-c3pf: ORDER BY SQL injection. Eight pages interpolated the
// raw sort_column and sort_direction request vars straight into ORDER BY
// clauses. The fix introduces cacti_validate_sort_column and
// cacti_validate_sort_direction, gates each site behind them, and forbids
// get_order_string() from returning a stale session cache when the current
// request supplies a malformed sort_column.

// -------- helper unit tests --------

test('cacti_validate_sort_column accepts allowlisted bare column', function () {
	expect(cacti_validate_sort_column('name', ['name', 'id'], 'id'))->toBe('name');
});

test('cacti_validate_sort_column accepts dotted column identifier', function () {
	$allowed = ['report.name', 'report.id'];
	expect(cacti_validate_sort_column('report.name', $allowed, 'report.id'))->toBe('report.name');
});

test('cacti_validate_sort_column rejects non-allowlisted column', function () {
	expect(cacti_validate_sort_column('password', ['name', 'id'], 'id'))->toBe('id');
});

test('cacti_validate_sort_column rejects SQL injection payload', function () {
	$payload = 'name, (SELECT password FROM user_auth LIMIT 1)';
	expect(cacti_validate_sort_column($payload, ['name'], 'id'))->toBe('id');
});

test('cacti_validate_sort_column rejects UNION payload', function () {
	$payload = 'name UNION SELECT 1';
	expect(cacti_validate_sort_column($payload, ['name'], 'id'))->toBe('id');
});

test('cacti_validate_sort_column accepts INET_ATON function when inner column allowed', function () {
	expect(cacti_validate_sort_column('INET_ATON(ip)', ['ip'], 'ip'))->toBe('INET_ATON(ip)');
});

test('cacti_validate_sort_column rejects function over non-allowed column', function () {
	expect(cacti_validate_sort_column('INET_ATON(password)', ['ip'], 'ip'))->toBe('ip');
});

test('cacti_validate_sort_column rejects function not in allowlist', function () {
	expect(cacti_validate_sort_column('LOAD_FILE(ip)', ['ip'], 'ip'))->toBe('ip');
});

test('cacti_validate_sort_column returns default for empty input', function () {
	expect(cacti_validate_sort_column('', ['name'], 'name'))->toBe('name');
});

test('cacti_validate_sort_direction normalises case', function () {
	expect(cacti_validate_sort_direction('asc'))->toBe('ASC');
	expect(cacti_validate_sort_direction('DESC'))->toBe('DESC');
});

test('cacti_validate_sort_direction rejects SQL payloads', function () {
	expect(cacti_validate_sort_direction("ASC; DROP TABLE user_auth"))->toBe('ASC');
	expect(cacti_validate_sort_direction("' OR 1=1--"))->toBe('ASC');
});

// -------- call-site migration invariants --------

test('lib/html_reports.php calls cacti_validate_sort_column before ORDER BY', function () {
	$source = file_get_contents(dirname(__DIR__, 2) . '/lib/html_reports.php');

	expect($source)->toContain('cacti_validate_sort_column((string) grv(\'sort_column\')');
	expect($source)->not->toMatch("/ORDER BY\s+\"\s*\.\s*grv\('sort_column'\)/");
});

test('user_log.php no longer concatenates raw sort_column into ORDER BY', function () {
	$source = file_get_contents(dirname(__DIR__, 2) . '/user_log.php');

	expect($source)->toContain('cacti_validate_sort_column((string) grv(\'sort_column\')');
	expect($source)->not->toMatch("/ORDER BY\s+\"\s*\.\s*grv\('sort_column'\)/");
});

test('utilities.php no longer concatenates raw sort_column into ORDER BY', function () {
	$source = file_get_contents(dirname(__DIR__, 2) . '/utilities.php');

	expect($source)->toContain('cacti_validate_sort_column((string) grv(\'sort_column\')');
	expect($source)->not->toMatch("/ORDER BY\s+\"\s*\.\s*grv\('sort_column'\)/");
});

test('user_domains.php no longer concatenates raw sort_column into ORDER BY', function () {
	$source = file_get_contents(dirname(__DIR__, 2) . '/user_domains.php');

	expect($source)->toContain('cacti_validate_sort_column((string) grv(\'sort_column\')');
	expect($source)->not->toMatch("/ORDER BY\s+\"\s*\.\s*grv\('sort_column'\)/");
});

test('user_group_admin.php no longer concatenates raw sort_column into ORDER BY', function () {
	$source = file_get_contents(dirname(__DIR__, 2) . '/user_group_admin.php');

	expect($source)->toContain('cacti_validate_sort_column((string) grv(\'sort_column\')');
	expect($source)->not->toMatch("/ORDER BY\s+\"\s*\.\s*grv\('sort_column'\)/");
});

test('get_order_string clears stale session cache on malformed sort_column', function () {
	$source = file_get_contents(dirname(__DIR__, 2) . '/lib/html_utility.php');

	$openPos = strpos($source, 'function get_order_string() : string');
	expect($openPos)->not->toBeFalse();

	$slice = substr($source, $openPos, 2500);

	$unsetPos = strpos($slice, "unset(\$_SESSION['sort_string'][\$page])");
	$cachePos = strpos($slice, "isset(\$_SESSION['sort_string'][\$page])");

	expect($unsetPos)->not->toBeFalse();
	expect($cachePos)->not->toBeFalse();
	expect($unsetPos)->toBeLessThan($cachePos);
});
