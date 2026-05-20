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

// =====================================================================
// array_map('intval', ...) sanitization pattern tests
//
// Validates that the intval mapping used throughout the codebase
// (lib/auth.php, lib/data_query.php, lib/api_data_source.php, etc.)
// correctly neutralizes SQL injection attempts when building IN() clauses.
// =====================================================================

test('intval maps clean numeric strings to integers', function () {
	$input  = ['1', '2', '3'];
	$result = array_map('intval', $input);

	expect($result)->toBe([1, 2, 3]);
});

test('intval converts non-numeric strings to zero', function () {
	$input  = ['1', 'abc', '3'];
	$result = array_map('intval', $input);

	expect($result)->toBe([1, 0, 3]);
});

test('intval neutralizes SQL injection in DROP TABLE attempt', function () {
	$input  = ['1; DROP TABLE users', '2 OR 1=1'];
	$result = array_map('intval', $input);

	expect($result)->toBe([1, 2]);
});

test('intval neutralizes UNION SELECT injection', function () {
	$input  = ['1 UNION SELECT * FROM passwords', '0x41'];
	$result = array_map('intval', $input);

	expect($result)->toBe([1, 0]);
});

test('intval handles empty array', function () {
	$result = array_map('intval', []);

	expect($result)->toBe([]);
});

test('intval handles negative numbers correctly', function () {
	$input  = ['-1', '-42', '0'];
	$result = array_map('intval', $input);

	expect($result)->toBe([-1, -42, 0]);
});

test('intval handles mixed integer and string types', function () {
	$input  = [1, '2', 3.7, '4abc'];
	$result = array_map('intval', $input);

	expect($result)->toBe([1, 2, 3, 4]);
});

test('implode with intval produces safe comma-separated list', function () {
	$input  = ['1', '2', 'abc'];
	$result = implode(', ', array_map('intval', $input));

	expect($result)->toBe('1, 2, 0');
});

test('full IN() clause pattern produces safe SQL fragment', function () {
	$input  = ['5', '10', 'abc; DROP TABLE', '20'];
	$result = 'IN(' . implode(', ', array_map('intval', $input)) . ')';

	expect($result)->toBe('IN(5, 10, 0, 20)');
});

test('full NOT IN() clause pattern produces safe SQL fragment', function () {
	$skipped = ['1', '2 OR 1=1', '3'];
	$result  = 'AND local_graph_id NOT IN(' . implode(',', array_map('intval', $skipped)) . ')';

	expect($result)->toBe('AND local_graph_id NOT IN(1,2,3)');
});

test('intval on array_keys produces safe template ID list', function () {
	$templates = [10 => 'Template A', 20 => 'Template B', 30 => 'Template C'];
	$result    = implode(', ', array_map('intval', array_keys($templates)));

	expect($result)->toBe('10, 20, 30');
});

test('intval preserves large but valid IDs', function () {
	$input  = ['999999', '1000000', '2147483647'];
	$result = array_map('intval', $input);

	expect($result)->toBe([999999, 1000000, 2147483647]);
});

test('intval converts whitespace-padded numbers', function () {
	$input  = [' 5 ', '  10', '15  '];
	$result = array_map('intval', $input);

	expect($result)->toBe([5, 10, 15]);
});
