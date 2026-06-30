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
 * Guards for the multi-sort ORDER BY injection fix.
 *
 * Tests cover three layers:
 *   1. sort_direction_clamp() unit behaviour
 *   2. sort_column_is_valid() unit behaviour
 *   3. Source-scan regressions confirming the write-path validation is in place
 *   4. get_order_string() session-read path with a pre-poisoned session
 */

require_once dirname(__DIR__) . '/Helpers/CactiStubs.php';

/* ---------------------------------------------------------------------------
 * html_utility.php defines get_request_var / get_nfilter_request_var /
 * isset_request_var / set_request_var unconditionally.  Do not pre-declare
 * them.  Only stub the symbols it calls but does not define itself.
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
		return 'test.php';
	}
}

if (!function_exists('get_mysql_info')) {
	/* Non-MariaDB stub keeps $natural = false, avoiding db_fetch_assoc calls. */
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
 * Reset all Cacti request-var state between tests.  html_utility.php caches
 * values in $_CACTI_REQUEST; stale entries cause wrong results across tests.
 * ------------------------------------------------------------------------- */

function sqli_test_reset_request(array $vars) : void {
	global $_CACTI_REQUEST;
	$_CACTI_REQUEST = [];
	$_REQUEST       = $vars;
	$_GET           = $vars;
	$_POST          = $vars;
}

/* ===========================================================================
 * 1. sort_direction_clamp() unit tests
 * ========================================================================= */

test('sort_direction_clamp returns ASC for ASC', function () {
	expect(sort_direction_clamp('ASC'))->toBe('ASC');
});

test('sort_direction_clamp returns DESC for DESC', function () {
	expect(sort_direction_clamp('DESC'))->toBe('DESC');
});

test('sort_direction_clamp normalises lowercase asc', function () {
	expect(sort_direction_clamp('asc'))->toBe('ASC');
});

test('sort_direction_clamp normalises mixed case Desc', function () {
	expect(sort_direction_clamp('Desc'))->toBe('DESC');
});

test('sort_direction_clamp clamps LIMIT injection to ASC', function () {
	expect(sort_direction_clamp('ASC LIMIT 0)/*'))->toBe('ASC');
});

test('sort_direction_clamp clamps UNION SELECT payload to ASC', function () {
	expect(sort_direction_clamp('DESC UNION SELECT 1,2,3--'))->toBe('ASC');
});

test('sort_direction_clamp clamps empty string to ASC', function () {
	expect(sort_direction_clamp(''))->toBe('ASC');
});

test('sort_direction_clamp clamps whitespace-only to ASC', function () {
	expect(sort_direction_clamp('   '))->toBe('ASC');
});

/* ===========================================================================
 * 2. sort_column_is_valid() unit tests
 * ========================================================================= */

test('sort_column_is_valid accepts simple column name', function () {
	expect(sort_column_is_valid('hostname'))->toBeTrue();
});

test('sort_column_is_valid accepts table.column qualified name', function () {
	expect(sort_column_is_valid('host.description'))->toBeTrue();
});

test('sort_column_is_valid accepts column with digits', function () {
	expect(sort_column_is_valid('field1'))->toBeTrue();
});

test('sort_column_is_valid accepts multi-part qualified name', function () {
	expect(sort_column_is_valid('cacti.host.status'))->toBeTrue();
});

test('sort_column_is_valid rejects leading digit', function () {
	expect(sort_column_is_valid('1name'))->toBeFalse();
});

test('sort_column_is_valid rejects parenthesis', function () {
	expect(sort_column_is_valid('name()'))->toBeFalse();
});

test('sort_column_is_valid rejects SQL comment injection', function () {
	expect(sort_column_is_valid('name--'))->toBeFalse();
});

test('sort_column_is_valid rejects LIMIT injection payload', function () {
	expect(sort_column_is_valid('name) LIMIT 0/*'))->toBeFalse();
});

test('sort_column_is_valid rejects backtick escape', function () {
	expect(sort_column_is_valid("name` OR 1=1--"))->toBeFalse();
});

test('sort_column_is_valid rejects space in name', function () {
	expect(sort_column_is_valid('col name'))->toBeFalse();
});

test('sort_column_is_valid rejects semicolon', function () {
	expect(sort_column_is_valid('col;DROP TABLE hosts'))->toBeFalse();
});

test('sort_column_is_valid rejects empty string', function () {
	expect(sort_column_is_valid(''))->toBeFalse();
});

test('sort_column_is_valid rejects lone dot', function () {
	expect(sort_column_is_valid('.'))->toBeFalse();
});

/* ===========================================================================
 * 3. Source-scan regressions
 * ========================================================================= */

$htmlUtilitySource = file_get_contents(dirname(__DIR__, 2) . '/lib/html_utility.php');

test('update_order_string validates sort_column before writing to sort_data', function () use ($htmlUtilitySource) {
	expect($htmlUtilitySource)->toContain('sort_column_is_valid(');
});

test('update_order_string clamps sort_direction before writing to session', function () use ($htmlUtilitySource) {
	expect($htmlUtilitySource)->toContain('sort_direction_clamp(');
});

test('session sort_data is not written before the column validation guard', function () use ($htmlUtilitySource) {
	/* In both branches that write sort_data, the validation guard must appear
	 * before the first write.  Check the source-level ordering. */
	$validPos = strpos($htmlUtilitySource, 'sort_column_is_valid($column)');
	$writePos = strpos($htmlUtilitySource, "\$_SESSION['sort_data'][\$page][\$column] = \$direction;");

	expect($validPos)->toBeLessThan($writePos);
});

test('get_order_string re-validates session sort_data on read', function () use ($htmlUtilitySource) {
	$readFnPos   = strpos($htmlUtilitySource, 'function get_order_string()');
	$validInRead = strpos($htmlUtilitySource, 'sort_column_is_valid($col)', $readFnPos);

	expect($validInRead)->toBeGreaterThan($readFnPos);
});

/* ===========================================================================
 * 4. update_order_string() write-path behavioural tests
 * ========================================================================= */

test('malicious sort_column is not written to sort_data', function () {
	$_SESSION = [];
	sqli_test_reset_request(['sort_column' => 'name) LIMIT 0/*', 'sort_direction' => 'ASC']);
	update_order_string();

	expect($_SESSION)->not->toHaveKey('sort_data');
});

test('malicious sort_column is not written to sort_string', function () {
	$_SESSION = [];
	sqli_test_reset_request(['sort_column' => 'name) LIMIT 0/*', 'sort_direction' => 'ASC']);
	update_order_string();

	expect($_SESSION)->not->toHaveKey('sort_string');
});

test('tainted sort_direction is clamped to ASC in sort_string', function () {
	$_SESSION = [];
	sqli_test_reset_request(['sort_column' => 'hostname', 'sort_direction' => 'ASC UNION SELECT password FROM user_auth--']);
	update_order_string();

	$ss = !empty($_SESSION['sort_string']) ? reset($_SESSION['sort_string']) : '';

	expect($ss)->not->toContain('UNION')
		->not->toContain('SELECT')
		->not->toContain('--')
		->toContain('ASC');
});

test('valid sort_column and DESC direction produce correct ORDER BY', function () {
	$_SESSION = [];
	sqli_test_reset_request(['sort_column' => 'hostname', 'sort_direction' => 'DESC']);
	update_order_string();

	$ss = !empty($_SESSION['sort_string']) ? reset($_SESSION['sort_string']) : '';

	expect($ss)->toContain('hostname')
		->toContain('DESC')
		->not->toContain('LIMIT');
});

/* ===========================================================================
 * 5. get_order_string() session-read guard tests
 * ========================================================================= */

test('get_order_string returns empty string when session sort_data contains malicious column', function () {
	/* Write a valid column via update_order_string to create the page key,
	 * then corrupt sort_data to simulate a pre-patch session token. */
	$_SESSION = [];
	sqli_test_reset_request(['sort_column' => 'hostname', 'sort_direction' => 'ASC']);
	update_order_string();

	if (!empty($_SESSION['sort_data'])) {
		$k = array_key_first($_SESSION['sort_data']);
		$_SESSION['sort_data'][$k]   = ['name) LIMIT 0/*' => 'ASC'];
		$_SESSION['sort_string'][$k] = 'ORDER BY name) LIMIT 0/* ASC';
	}

	sqli_test_reset_request([]);
	$result = get_order_string();

	expect($result)->toBe('');
});

test('get_order_string clears poisoned sort_string from session', function () {
	$_SESSION = [];
	sqli_test_reset_request(['sort_column' => 'hostname', 'sort_direction' => 'ASC']);
	update_order_string();

	if (!empty($_SESSION['sort_data'])) {
		$k = array_key_first($_SESSION['sort_data']);
		$_SESSION['sort_data'][$k]   = ['name) LIMIT 0/*' => 'ASC'];
		$_SESSION['sort_string'][$k] = 'ORDER BY name) LIMIT 0/* ASC';
	}

	sqli_test_reset_request([]);
	get_order_string();

	expect($_SESSION)->not->toHaveKey('sort_string');
});

test('get_order_string output never contains LIMIT when session is poisoned', function () {
	$_SESSION = [];
	sqli_test_reset_request(['sort_column' => 'hostname', 'sort_direction' => 'ASC']);
	update_order_string();

	if (!empty($_SESSION['sort_data'])) {
		$k = array_key_first($_SESSION['sort_data']);
		$_SESSION['sort_data'][$k]   = ['name) LIMIT 0/*' => 'ASC'];
		$_SESSION['sort_string'][$k] = 'ORDER BY name) LIMIT 0/* ASC';
	}

	sqli_test_reset_request([]);
	$result = get_order_string();

	expect($result)->not->toContain('LIMIT')
		->not->toContain('/*');
});

test('get_order_string returns cached ORDER BY when session is clean', function () {
	$_SESSION = [];
	sqli_test_reset_request(['sort_column' => 'description', 'sort_direction' => 'ASC']);
	update_order_string();

	sqli_test_reset_request([]);
	$result = get_order_string();

	expect($result)->toStartWith('ORDER BY')
		->toContain('description')
		->not->toMatch('/[;\'"\(\)]/');
});
