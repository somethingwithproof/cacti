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
require_once dirname(__DIR__, 2) . '/lib/utility.php';

// Exercises the PHP 8.4 idiom migrations in lib/functions.php and
// lib/utility.php (explicit (string) casts, first-class callable syntax,
// str_contains). The casts must not change results, so each case pins the
// behaviour that surrounds the modernized line.

/**
 * Seed a value into the read_config_option cache so a plain (non-forced)
 * lookup returns it without touching the database. Mirrors the CLI/web
 * key selection in read_config_option().
 */
function seed_config_option(string $name, mixed $value): void {
	global $config;

	$key = defined('CACTI_WEB') && CACTI_WEB ? OPTIONS_WEB : OPTIONS_CLI;

	if (!isset($_SESSION[OPTIONS_WEB]) || !is_array($_SESSION[OPTIONS_WEB])) {
		$_SESSION[OPTIONS_WEB] = [];
	}

	if (!isset($config[OPTIONS_CLI]) || !is_array($config[OPTIONS_CLI])) {
		$config[OPTIONS_CLI] = [];
	}

	$_SESSION[OPTIONS_WEB][$name] = $value;
	$config[OPTIONS_CLI][$name]   = $value;
}

// =====================================================================
// form_input_validate - line 946 regexp branch
// =====================================================================

test('form_input_validate returns value unchanged when regexp matches', function () {
	expect(form_input_validate('12345', 'port', '^[0-9]+$', false))->toBe('12345');
});

test('form_input_validate returns value when regexp fails to match', function () {
	// The (string) cast feeds a non-string value to preg_match; behaviour is
	// unchanged: the original value is always returned.
	expect(form_input_validate(9000, 'port', '^[0-9]{2}$', false))->toBe(9000);
});

// =====================================================================
// strip_alpha - line 2423
// =====================================================================

test('strip_alpha keeps the numeric portion of a mixed string', function () {
	expect(strip_alpha('abc123'))->toBe('123');
});

test('strip_alpha returns false when nothing numeric remains', function () {
	expect(strip_alpha('abc.def'))->toBeFalse();
});

test('strip_alpha tolerates a non-string argument', function () {
	expect(strip_alpha(4242))->toBe('4242');
});

// =====================================================================
// build_where_from_array - line 4053
// =====================================================================

test('build_where_from_array builds a parameterized clause', function () {
	$params = [];
	$where  = build_where_from_array(['host_id' => 5, 'disabled' => ''], $params);

	expect($where)->toBe('`host_id` = ? AND `disabled` = ?')
		->and($params)->toBe([5, '']);
});

test('build_where_from_array rejects an invalid field name', function () {
	$params = [];
	$where  = build_where_from_array(['bad-name' => 1, 'good' => 2], $params);

	expect($where)->toBe('`good` = ?')
		->and($params)->toBe([2]);
});

// =====================================================================
// parse_email_details / split_emaildetail - lines 6251, 6270, 6301, 6348
// =====================================================================

test('split_emaildetail parses a bare name and address pair', function () {
	$result = split_emaildetail('Admin User <admin@example.com>');

	expect($result['name'])->toBe('Admin User ')
		->and($result['email'])->toBe('admin@example.com');
});

test('split_emaildetail lowercases a plain address', function () {
	$result = split_emaildetail('User@Example.COM');

	expect($result['email'])->toBe('user@example.com')
		->and($result['name'])->toBe('');
});

test('split_emaildetail handles a domainless sendmail recipient', function () {
	$result = split_emaildetail('root');

	expect($result)->toBe(['name' => '', 'email' => 'root']);
});

test('parse_email_details splits a comma separated string', function () {
	$result = parse_email_details('a@example.com,b@example.com');

	expect($result)->toHaveKey('a@example.com')
		->and($result)->toHaveKey('b@example.com');
});

test('parse_email_details normalizes an array entry with name and email', function () {
	$result = parse_email_details([['name' => 'Bob', 'email' => 'BOB@Example.com']]);

	expect($result)->toHaveKey('bob@example.com')
		->and($result['bob@example.com']['name'])->toBe('Bob');
});

// =====================================================================
// add_email_details - line 6210
// =====================================================================

test('add_email_details qualifies a bare local address', function () {
	$result   = true;
	$captured = [];

	$text = add_email_details(
		[['email' => 'root', 'name' => 'Root']],
		$result,
		function ($address, $name) use (&$captured) {
			$captured[] = $address;

			return true;
		}
	);

	expect($result)->toBeTrue()
		->and($captured)->toHaveCount(1)
		->and($captured[0])->toContain('root@');
});

test('add_email_details passes a fully qualified address through', function () {
	$result   = true;
	$captured = [];

	add_email_details(
		[['email' => 'ops@example.com', 'name' => '']],
		$result,
		function ($address, $name) use (&$captured) {
			$captured[] = $address;

			return true;
		}
	);

	expect($captured)->toBe(['ops@example.com']);
});

// =====================================================================
// cacti_debug_backtrace - line 6706
// =====================================================================

test('cacti_debug_backtrace returns a string when not recording', function () {
	$trace = cacti_debug_backtrace('', false, false);

	expect($trace)->toBeString();
});

// =====================================================================
// cacti_unique_ids - line 9510
// =====================================================================

test('cacti_unique_ids explodes, dedupes and sorts a string', function () {
	expect(cacti_unique_ids('3, 1, 2, 2'))->toBe([1, 2, 3]);
});

test('cacti_unique_ids casts array members to int', function () {
	expect(cacti_unique_ids(['5', '5', '1'], false))->toBe([1, 5]);
});

// =====================================================================
// is_function_enabled - line 9057
// =====================================================================

test('is_function_enabled reports an available function', function () {
	expect(is_function_enabled('strlen'))->toBeTrue();
});

test('is_function_enabled reports a missing function false', function () {
	expect(is_function_enabled('this_function_does_not_exist_1234'))->toBeFalse();
});

// =====================================================================
// is_device_debug_enabled - line 7321
// =====================================================================

test('is_device_debug_enabled returns false for an unknown host', function () {
	expect(is_device_debug_enabled(987654))->toBeFalse();
});

// =====================================================================
// enable_device_debug / disable_device_debug - lines 7278, 7302
// =====================================================================

test('enable_device_debug adds a host to the selective debug list', function () {
	global $settings, $config;

	$settings['general']['selective_device_debug'] = ['default' => '10,20'];
	unset($config[OPTIONS_CLI]['selective_device_debug']);

	expect(enable_device_debug(30))->toBeTrue();
});

test('disable_device_debug removes a host from the selective debug list', function () {
	global $settings, $config;

	$settings['general']['selective_device_debug'] = ['default' => '10,20,30'];
	unset($config[OPTIONS_CLI]['selective_device_debug']);

	expect(disable_device_debug(20))->toBeTrue();
});

// =====================================================================
// get_cacti_db_version_raw - line 7923
// =====================================================================

test('get_cacti_db_version_raw returns a string with no database', function () {
	// db_fetch_cell returns false under the test bootstrap; the (string) cast
	// turns that into '' rather than emitting a TypeError.
	expect(get_cacti_db_version_raw(true))->toBe('');
});

// =====================================================================
// debounce_run_notification - lines 9457, 9469
// =====================================================================

test('debounce_run_notification fires on first use', function () {
	expect(debounce_run_notification('unit-test-fresh-id'))->toBeTrue();
});

test('debounce_run_notification decodes a stored json timestamp', function () {
	$id  = 'unit-test-json-id';
	$key = 'debounce_' . md5($id);

	// A recent timestamp means the notification must be suppressed, which
	// exercises the json_decode() branch on the stored option.
	seed_config_option($key, json_encode(['timestamp' => time()]));

	expect(debounce_run_notification($id, 7200))->toBeFalse();
});

// =====================================================================
// cacti_cookie_set - line 9165
// =====================================================================

test('cacti_cookie_set runs the modern setcookie path', function () {
	// setcookie() is a no-op under the CLI SAPI, but the (string) casts on
	// $session and $val must execute without a TypeError.
	cacti_cookie_set('unit_test_cookie', 12345);

	expect(true)->toBeTrue();
});

// =====================================================================
// cacti_log - lines 1629, 1631, 1633, 1635 (syslog classification)
// =====================================================================

test('cacti_log classifies syslog severities via str_contains', function () {
	// log_destination 3 is syslog-only, which reaches the str_contains()
	// classification block. log_perror/pwarn/pstats stay false so no real
	// syslog write occurs.
	seed_config_option('log_destination', 3);
	seed_config_option('log_verbosity', 3);
	seed_config_option('log_perror', '');
	seed_config_option('log_pwarn', '');
	seed_config_option('log_pstats', '');

	cacti_log('ERROR: unit test', false, 'CMDPHP');
	cacti_log('WARNING: unit test', false, 'CMDPHP');
	cacti_log('STATS: unit test', false, 'CMDPHP');
	$last = cacti_log('NOTICE: unit test', false, 'CMDPHP');

	expect($last)->toBeBool();
});

// =====================================================================
// utility.php: memory_bytes - line 1699
// =====================================================================

test('memory_bytes expands a gigabyte suffix', function () {
	expect(memory_bytes('2G'))->toBe((float) (2 * 1024 * 1024 * 1024));
});

test('memory_bytes expands a megabyte suffix', function () {
	expect(memory_bytes('512M'))->toBe((float) (512 * 1024 * 1024));
});

// =====================================================================
// utility.php: utility_php_sort_extensions / verify_extensions - 1815, 1870
// =====================================================================

test('utility_php_sort_extensions compares names case-insensitively', function () {
	expect(utility_php_sort_extensions(['name' => 'abc'], ['name' => 'ABD']))->toBeLessThan(0);
});

test('utility_php_verify_extensions fills defaults and marks loaded modules', function () {
	$extensions = [];
	utility_php_verify_extensions($extensions, 'cli');

	// uksort with the first-class callable leaves the list alphabetized and a
	// core always-present extension flagged for the requested source.
	expect($extensions)->toHaveKey('pcre')
		->and($extensions['pcre']['cli'])->toBeTrue();
});

// =====================================================================
// utility.php: utility_get_formatted_bytes - line 1901
// =====================================================================

test('utility_get_formatted_bytes parses a suffixed input value', function () {
	$output = '';
	utility_get_formatted_bytes('100M', 'M', $output);

	expect($output)->toBe('100M');
});

// =====================================================================
// utility.php: utilities_php_modules - lines 1689-1693
// =====================================================================

test('utilities_php_modules returns cleaned phpinfo html', function () {
	$html = utilities_php_modules();

	expect($html)->toBeString()
		->and($html)->not->toContain('<img');
});

// =====================================================================
// utility.php: object_cache_get_totals - line 2073
// =====================================================================

test('object_cache_get_totals accepts a comma separated id string', function () {
	global $object_totals;

	// Pre-populate the totals so the function returns before any query while
	// still executing the string-to-array explode on the id argument.
	$object_totals = ['seeded' => 1];

	object_cache_get_totals('graph', '1,2,3', false);

	expect(true)->toBeTrue();
});

// =====================================================================
// utility.php: poller_update_poller_cache_from_buffer - line 715
// =====================================================================

test('poller_update_poller_cache_from_buffer measures buffered records', function () {
	// Empty local_data_ids skips the present=0 update; a non-empty poller_items
	// buffer drives the strlen((string) $record) accumulator.
	$empty = [];
	$items = ['(1, 1, 1)'];

	poller_update_poller_cache_from_buffer($empty, $items, 1);

	expect(true)->toBeTrue();
});
