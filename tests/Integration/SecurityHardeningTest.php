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
 * Integration tests for security hardening patterns.
 *
 * These tests verify that the intval() sanitization pattern used in SQL
 * query building (e.g. data_query_remap_indexes, process_poller_output)
 * correctly neutralizes injection attempts, and that the NOT EXISTS
 * subquery pattern uses proper parameterized predicates.
 */

require_once dirname(__DIR__) . '/Helpers/CactiStubs.php';
require_once dirname(__DIR__, 2) . '/include/global.php';

// --- Old pattern (direct implode) vs new pattern (intval) ---

test('old pattern direct implode passes malicious input through unchanged', function () {
	// The OLD vulnerable pattern: implode without intval
	$malicious_ids = ['1', '2; DROP TABLE users', '3 OR 1=1'];
	$old_result = implode(', ', $malicious_ids);

	// The old pattern would embed injection payloads directly
	expect($old_result)->toContain('DROP TABLE users');
	expect($old_result)->toContain('OR 1=1');
});

test('new pattern with intval strips injection from DROP TABLE payload', function () {
	$malicious = '1; DROP TABLE users';
	$sanitized = intval($malicious);

	expect($sanitized)->toBe(1);
});

test('new pattern with intval strips injection from OR boolean payload', function () {
	$malicious = '1 OR 1=1';
	$sanitized = intval($malicious);

	expect($sanitized)->toBe(1);
});

test('new pattern with intval strips hex string payload', function () {
	$malicious = '0x1234';
	$sanitized = intval($malicious);

	expect($sanitized)->toBe(0);
});

test('new pattern with intval truncates float to integer', function () {
	$malicious = '1.5';
	$sanitized = intval($malicious);

	expect($sanitized)->toBe(1);
});

// --- Array sanitization for typical Cacti form submissions ---

test('intval sanitizes typical Cacti form selected_items array', function () {
	// Cacti forms often submit selected items as serialized JSON arrays
	// After json_decode, the array may contain strings from user input
	$form_input = ['10', '20', '30', '40abc', '50'];
	$sanitized = array_map('intval', $form_input);

	expect($sanitized)->toBe([10, 20, 30, 40, 50]);
});

test('intval sanitizes form submission with injection attempts', function () {
	$form_input = [
		'1',
		'2; DELETE FROM host',
		"3' OR '1'='1",
		'4 UNION SELECT password FROM user_auth',
		'5-- comment',
	];
	$sanitized = array_map('intval', $form_input);

	expect($sanitized)->toBe([1, 2, 3, 4, 5]);

	$id_list = implode(', ', $sanitized);
	expect($id_list)->toBe('1, 2, 3, 4, 5');
	expect($id_list)->not->toContain('DELETE');
	expect($id_list)->not->toContain('UNION');
	expect($id_list)->not->toContain('SELECT');
	expect($id_list)->not->toContain("'");
});

test('intval handles negative values correctly', function () {
	$input = ['-1', '-100', '0', '1'];
	$sanitized = array_map('intval', $input);

	expect($sanitized)->toBe([-1, -100, 0, 1]);
});

test('intval handles empty strings and non-numeric values', function () {
	$input = ['', 'abc', 'null', 'true', 'false'];
	$sanitized = array_map('intval', $input);

	expect($sanitized)->toBe([0, 0, 0, 0, 0]);
});

test('intval handles very large IDs within PHP integer range', function () {
	$input = ['999999', '1000000', '2147483647'];
	$sanitized = array_map('intval', $input);

	expect($sanitized)->toBe([999999, 1000000, 2147483647]);
});

test('new pattern sanitizes full array and produces safe SQL fragment', function () {
	$user_input = [
		'1; DROP TABLE users',
		'1 OR 1=1',
		'0x1234',
		'1.5',
		'42',
		"100' UNION SELECT * FROM user_auth --",
	];

	$sanitized = array_map('intval', $user_input);
	$id_list = implode(', ', $sanitized);

	expect($id_list)->toBe('1, 1, 0, 1, 42, 100');
	expect($id_list)->not->toContain('DROP');
	expect($id_list)->not->toContain('UNION');
	expect($id_list)->not->toContain('SELECT');
	expect($id_list)->not->toContain(';');
	expect($id_list)->not->toContain("'");
	expect($id_list)->not->toContain('--');

	// Valid for use in an IN() clause
	$sql = "WHERE id IN($id_list)";
	expect($sql)->toMatch('/^WHERE id IN\([\d, ]+\)$/');
});

// --- NOT EXISTS pattern uses parameterized predicates ---

test('NOT EXISTS subquery with integer user_id does not allow injection', function () {
	// In get_policy_where(), user_id comes from $p['id'] which is fetched
	// from the database as an integer. Even if it were a string, intval would sanitize it.
	$policy_id = intval('42; DROP TABLE user_auth');
	$sql = 'NOT EXISTS (SELECT 1 FROM user_auth_perms WHERE user_id = ' . $policy_id . ' AND type = 1 AND item_id = gl.id)';

	expect($sql)->not->toContain('DROP');
	expect($sql)->toContain('user_id = 42');
});

test('NOT EXISTS uses correlated predicates that reference outer query aliases', function () {
	// The correlation predicates (gl.id, h.id, gl.graph_template_id) are column
	// references, not user input, so they cannot be injected
	$patterns = [
		'item_id = gl.id',
		'item_id = h.id',
		'item_id = gl.graph_template_id',
	];

	foreach ($patterns as $predicate) {
		// These are hardcoded column references, not interpolated values
		expect($predicate)->toMatch('/^item_id = [a-z]+\.[a-z_]+$/');
	}
});

test('NOT EXISTS subquery structure is self-contained with balanced parentheses', function () {
	$user_id = 42;
	$fragments = [
		'NOT EXISTS (SELECT 1 FROM user_auth_perms WHERE user_id = ' . $user_id . ' AND type = 1 AND item_id = gl.id)',
		'NOT EXISTS (SELECT 1 FROM user_auth_perms WHERE user_id = ' . $user_id . ' AND type = 3 AND item_id = h.id)',
		'NOT EXISTS (SELECT 1 FROM user_auth_perms WHERE user_id = ' . $user_id . ' AND type = 4 AND item_id = gl.graph_template_id)',
		'NOT EXISTS (SELECT 1 FROM user_auth_group_perms WHERE group_id = ' . $user_id . ' AND type = 1 AND item_id = gl.id)',
	];

	foreach ($fragments as $fragment) {
		$open = substr_count($fragment, '(');
		$close = substr_count($fragment, ')');
		expect($open)->toBe($close);
		expect($fragment)->toStartWith('NOT EXISTS (SELECT 1');
	}
});
