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
 * Hand-off (round-trip) test for the multi-sort ORDER BY injection fix.
 *
 * Simulates the full attack path:
 *   1. Attacker submits malicious sort_column / sort_direction via $_REQUEST.
 *   2. update_order_string() is called (as it would be on the first page load).
 *   3. get_order_string() is called (as it would be on the next request using
 *      the cached session).
 *   4. The output is verified to be either empty or a well-formed ORDER BY
 *      clause with no SQL metacharacters that could escape identifier quoting.
 */

require_once dirname(__DIR__) . '/Helpers/CactiStubs.php';

/* ---------------------------------------------------------------------------
 * Stubs for symbols html_utility.php calls but does not define itself.
 * Do NOT pre-declare get_request_var / isset_request_var / set_request_var —
 * html_utility.php owns those definitions.
 * ------------------------------------------------------------------------- */

if (!function_exists('cacti_sizeof')) {
	function cacti_sizeof(mixed $array) : int {
		return is_array($array) ? count($array) : 0;
	}
}

if (!function_exists('cacti_version_compare')) {
	function cacti_version_compare(string $a, string $b, string $op = '>') : bool {
		return version_compare($a, $b, $op);
	}
}

if (!function_exists('get_current_page')) {
	function get_current_page(bool $basename = true) : string {
		return 'handoff_test.php';
	}
}

if (!function_exists('get_mysql_info')) {
	function get_mysql_info(int $poller_id = 1) : array {
		return ['database' => 'MySQL', 'version' => '8.0.0', 'link_ver' => '8.0', 'variables' => []];
	}
}

if (!function_exists('read_config_option')) {
	function read_config_option(string $option, bool $force = false) : mixed {
		return '';
	}
}

if (!function_exists('html_log_input_error')) {
	function html_log_input_error(string $name) : void {}
}

require_once dirname(__DIR__, 2) . '/lib/html_utility.php';

/* ---------------------------------------------------------------------------
 * Helper: run one full round-trip with the given request vars.
 *
 * Resets $_CACTI_REQUEST and session state before each run so tests are
 * independent.  Returns whatever get_order_string() produces after
 * update_order_string() has processed the supplied input.
 * ------------------------------------------------------------------------- */

function handoff_round_trip(array $request) : string {
	global $_CACTI_REQUEST;

	$_CACTI_REQUEST = [];
	$_SESSION       = [];
	$_REQUEST       = $request;
	$_GET           = $request;
	$_POST          = $request;

	update_order_string();

	/* Simulate the next request arriving with the same parameters so
	 * get_order_string() can find the cached session value. */
	$_CACTI_REQUEST = [];
	$_REQUEST       = [];
	$_GET           = [];
	$_POST          = [];

	return get_order_string();
}

/* ===========================================================================
 * Round-trip: malicious sort_column with LIMIT injection
 * ========================================================================= */

test('round-trip: LIMIT injection in sort_column produces no SQL output', function () {
	$result = handoff_round_trip([
		'sort_column'    => 'name) LIMIT 0/*',
		'sort_direction' => 'ASC',
	]);

	expect($result)->toBe('');
});

test('round-trip: LIMIT injection output never contains LIMIT keyword', function () {
	$result = handoff_round_trip([
		'sort_column'    => 'name) LIMIT 0/*',
		'sort_direction' => 'ASC',
	]);

	expect($result)->not->toContain('LIMIT');
});

test('round-trip: LIMIT injection output never contains comment sequence', function () {
	$result = handoff_round_trip([
		'sort_column'    => 'name) LIMIT 0/*',
		'sort_direction' => 'ASC',
	]);

	expect($result)->not->toContain('/*');
});

/* ===========================================================================
 * Round-trip: malicious sort_direction with UNION SELECT
 * ========================================================================= */

test('round-trip: UNION SELECT in sort_direction is clamped to ASC', function () {
	$result = handoff_round_trip([
		'sort_column'    => 'hostname',
		'sort_direction' => 'ASC UNION SELECT password FROM user_auth--',
	]);

	expect($result)->not->toContain('UNION')
		->not->toContain('SELECT')
		->not->toContain('--');
});

test('round-trip: valid column with tainted direction produces safe ORDER BY', function () {
	$result = handoff_round_trip([
		'sort_column'    => 'hostname',
		'sort_direction' => 'ASC UNION SELECT password FROM user_auth--',
	]);

	/* Output must be either empty or a valid ORDER BY with ASC or DESC only. */
	if ($result !== '') {
		expect($result)->toMatch('/^ORDER BY [`a-zA-Z0-9_.]+\s+(ASC|DESC)$/');
	} else {
		expect($result)->toBe('');
	}
});

/* ===========================================================================
 * Round-trip: backtick escape attempt
 * ========================================================================= */

test('round-trip: backtick in sort_column produces no SQL output', function () {
	$result = handoff_round_trip([
		'sort_column'    => "name` OR 1=1--",
		'sort_direction' => 'ASC',
	]);

	expect($result)->toBe('');
});

/* ===========================================================================
 * Round-trip: legitimate single-column sort
 * ========================================================================= */

test('round-trip: legitimate sort_column produces well-formed ORDER BY', function () {
	$result = handoff_round_trip([
		'sort_column'    => 'description',
		'sort_direction' => 'DESC',
	]);

	expect($result)->toContain('ORDER BY')
		->toContain('description')
		->toContain('DESC')
		->not->toContain(');')
		->not->toContain('--');
});

test('round-trip: table.column qualified name is accepted', function () {
	$result = handoff_round_trip([
		'sort_column'    => 'host.description',
		'sort_direction' => 'ASC',
	]);

	expect($result)->toContain('ORDER BY')
		->toContain('description');
});

/* ===========================================================================
 * Round-trip: add=reset path (second branch in update_order_string)
 * ========================================================================= */

test('round-trip: add=reset with malicious column produces no SQL output', function () {
	$result = handoff_round_trip([
		'add'            => 'reset',
		'sort_column'    => 'name) LIMIT 0/*',
		'sort_direction' => 'ASC',
	]);

	expect($result)->toBe('');
});

test('round-trip: add=reset with valid column produces ORDER BY', function () {
	$result = handoff_round_trip([
		'add'            => 'reset',
		'sort_column'    => 'hostname',
		'sort_direction' => 'ASC',
	]);

	expect($result)->toContain('ORDER BY')
		->toContain('hostname');
});
