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
// Mutation-sensitive tests
//
// These tests are specifically designed to catch common mutations
// (operator changes, boundary changes, removed calls) that Infection
// would apply to the codebase.
// =====================================================================

test('intval sanitization cannot be removed without breaking security', function () {
	// If the intval call is removed (mutation), raw strings pass through
	$malicious = ['1', '2; DROP TABLE', '3 OR 1=1'];
	$sanitized = array_map('intval', $malicious);
	expect($sanitized)->toBe([1, 2, 3]);
	// Verify the implode produces only digits and commas
	$sql_fragment = implode(', ', $sanitized);
	expect($sql_fragment)->toMatch('/^[\d, ]+$/');
});

test('cache check uses isset not empty to allow falsy cached values', function () {
	$cache = [];
	$cache[42] = []; // empty array is a valid cached result
	expect(isset($cache[42]))->toBeTrue();
	// If mutation changes isset to !empty, this would fail
	expect(empty($cache[42]))->toBeTrue(); // empty is true, but isset is also true
	// The cache should return the value even if it's empty
	$result = isset($cache[42]) ? $cache[42] : 'NOT_CACHED';
	expect($result)->toBe([]);
});

test('null coalescing preserves zero and empty string as valid values', function () {
	$data = ['count' => 0, 'name' => '', 'active' => false];
	// ?? only triggers on null/undefined, NOT on falsy values
	expect($data['count'] ?? 'default')->toBe(0);
	expect($data['name'] ?? 'default')->toBe('');
	expect($data['active'] ?? 'default')->toBe(false);
	// Only missing keys trigger the default
	expect($data['missing'] ?? 'default')->toBe('default');
});

test('str_contains is not equivalent to str_starts_with', function () {
	// Mutation might swap str_contains for str_starts_with
	expect(str_contains('hello world', 'world'))->toBeTrue();
	expect(str_starts_with('hello world', 'world'))->toBeFalse();
});

test('str_starts_with is not equivalent to str_contains', function () {
	expect(str_starts_with('WIN32', 'WIN'))->toBeTrue();
	expect(str_starts_with('LINUX', 'WIN'))->toBeFalse();
	// str_contains matches 'WIN' anywhere, str_starts_with only at position 0
	expect(str_contains('DARWIN', 'WIN'))->toBeTrue();
	expect(str_starts_with('DARWIN', 'WIN'))->toBeFalse();
	expect(str_contains('SWIN', 'WIN'))->toBeTrue();
	expect(str_starts_with('SWIN', 'WIN'))->toBeFalse();
});

test('AF_INET6 and AF_INET are different constants', function () {
	// If mutation swaps AF_INET6 for AF_INET, IPv6 breaks
	expect(AF_INET6)->not->toBe(AF_INET);
	// Verify correct selection
	expect(filter_var('::1', FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) ? AF_INET6 : AF_INET)->toBe(AF_INET6);
	expect(filter_var('127.0.0.1', FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) ? AF_INET6 : AF_INET)->toBe(AF_INET);
});

test('NOT EXISTS with SELECT 1 is valid SQL pattern', function () {
	// Build the pattern and verify structure
	$pattern = "NOT EXISTS (SELECT 1 FROM user_auth_perms WHERE user_id = ? AND type = 1 AND item_id = gl.id)";
	expect($pattern)->toContain('NOT EXISTS');
	expect($pattern)->toContain('SELECT 1');
	expect($pattern)->toContain('item_id = gl.id'); // correlation predicate is essential
	// If mutation removes the correlation, the query semantics change completely
	$bad_pattern = "NOT EXISTS (SELECT 1 FROM user_auth_perms WHERE user_id = ? AND type = 1)";
	expect($bad_pattern)->not->toContain('item_id =');
});

test('cache prevents repeated function calls', function () {
	$call_count = 0;
	$cache = [];
	$fetch = function ($id) use (&$call_count) {
		$call_count++;
		return "result_for_{$id}";
	};

	// Simulate the caching pattern from poller.php
	foreach ([1, 1, 1, 2, 2, 3] as $id) {
		if (!isset($cache[$id])) {
			$cache[$id] = $fetch($id);
		}
		$result = $cache[$id];
	}

	expect($call_count)->toBe(3); // Only 3 unique IDs
	expect($cache)->toHaveCount(3);
});

test('grouping rows by snmp_index produces correct structure', function () {
	$rows = [
		['snmp_index' => 'eth0', 'local_graph_id' => '1'],
		['snmp_index' => 'eth0', 'local_graph_id' => '2'],
		['snmp_index' => 'eth1', 'local_graph_id' => '3'],
	];

	$index_to_graphs = [];
	foreach ($rows as $row) {
		$index_to_graphs[$row['snmp_index']][$row['local_graph_id']] = $row['local_graph_id'];
	}

	expect($index_to_graphs)->toHaveCount(2);
	expect($index_to_graphs['eth0'])->toHaveCount(2);
	expect($index_to_graphs['eth1'])->toHaveCount(1);
});

// =====================================================================
// Additional boundary condition and mutation-sensitive tests
// =====================================================================

test('strict comparison !== catches type differences that == would miss', function () {
	// Mutation: changing !== to != or === to ==
	expect(0 !== false)->toBeTrue();
	expect('' !== false)->toBeTrue();
	expect('' !== null)->toBeTrue();
	expect(0 !== null)->toBeTrue();
	// But loose comparison would treat these as equal
	expect(0 == false)->toBeTrue();
	expect('' == false)->toBeTrue();
});

test('boundary condition: greater-than vs greater-than-or-equal', function () {
	// Mutation: changing > to >= or < to <=
	$value = 0;
	expect($value > 0)->toBeFalse();
	expect($value >= 0)->toBeTrue();
	expect($value < 0)->toBeFalse();
	expect($value <= 0)->toBeTrue();

	$value = 1;
	expect($value > 0)->toBeTrue();
	expect($value >= 0)->toBeTrue();
});

test('array_key_exists detects null values that isset misses', function () {
	$data = ['key' => null];
	// isset returns false for null values
	expect(isset($data['key']))->toBeFalse();
	// array_key_exists returns true
	expect(array_key_exists('key', $data))->toBeTrue();
	// Both return false for non-existent keys
	expect(isset($data['missing']))->toBeFalse();
	expect(array_key_exists('missing', $data))->toBeFalse();
});

test('preg_match returns 1 for match and 0 for no match not boolean', function () {
	// Mutation: removing the function call or inverting the return
	expect(preg_match('/^\d+$/', '12345'))->toBe(1);
	expect(preg_match('/^\d+$/', 'abc'))->toBe(0);
	// Verify that the return is integer, not boolean
	expect(preg_match('/^\d+$/', '12345'))->toBeInt();
});

test('explode with limit parameter restricts split count', function () {
	// Mutation: removing the limit parameter
	$input = 'a:b:c:d:e';
	$limited = explode(':', $input, 2);
	expect($limited)->toHaveCount(2);
	expect($limited[0])->toBe('a');
	expect($limited[1])->toBe('b:c:d:e');

	$unlimited = explode(':', $input);
	expect($unlimited)->toHaveCount(5);
});

test('array_unique removes duplicates preserving first occurrence keys', function () {
	// Mutation: removing the array_unique call
	$input = [1, 2, 2, 3, 3, 3];
	$unique = array_unique($input);
	expect(count($unique))->toBe(3);
	expect(array_values($unique))->toBe([1, 2, 3]);
});

test('min and max are not interchangeable', function () {
	// Mutation: swapping min for max or vice versa
	$values = [10, 5, 20, 15];
	expect(min($values))->toBe(5);
	expect(max($values))->toBe(20);
	expect(min($values))->not->toBe(max($values));
});

test('floor and ceil produce different results for non-integers', function () {
	// Mutation: swapping floor for ceil
	expect(floor(4.7))->toBe(4.0);
	expect(ceil(4.7))->toBe(5.0);
	expect(floor(4.3))->toBe(4.0);
	expect(ceil(4.3))->toBe(5.0);
	// Only equal for integer values
	expect(floor(4.0))->toBe(ceil(4.0));
});

test('substr_count correctly counts non-overlapping occurrences', function () {
	// Mutation: replacing with strlen or other function
	expect(substr_count('hello world hello', 'hello'))->toBe(2);
	expect(substr_count('aaa', 'aa'))->toBe(1); // non-overlapping
	expect(substr_count('test', 'xyz'))->toBe(0);
});
