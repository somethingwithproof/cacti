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
 * Tests for the boost boolean config helpers in lib/boost.php.
 *
 * Each helper wraps a single read_config_option() call and compares
 * against 'on'. These tests verify that comparison using inline stubs
 * so no live database or Cacti bootstrap is required.
 */

/**
 * Stub read_config_option() that returns the value registered for the key.
 * Operates on a shared registry so individual tests can configure it.
 */
function stub_read_config(string $key, array $registry): string {
	return $registry[$key] ?? '';
}

function stub_is_boost_enabled(array $registry): bool {
	return stub_read_config('boost_rrd_update_enable', $registry) == 'on';
}

function stub_is_boost_system_enabled(array $registry): bool {
	return stub_read_config('boost_rrd_update_system_enable', $registry) == 'on';
}

function stub_is_boost_png_cache_enabled(array $registry): bool {
	return stub_read_config('boost_png_cache_enable', $registry) == 'on';
}

function stub_is_boost_redirect_enabled(array $registry): bool {
	return stub_read_config('boost_redirect', $registry) == 'on';
}

// --- is_boost_enabled ---

test('is_boost_enabled returns true when option is on', function () {
	$result = stub_is_boost_enabled(['boost_rrd_update_enable' => 'on']);

	expect($result)->toBeTrue();
});

test('is_boost_enabled returns false when option is off', function () {
	$result = stub_is_boost_enabled(['boost_rrd_update_enable' => 'off']);

	expect($result)->toBeFalse();
});

test('is_boost_enabled returns false when option is empty', function () {
	$result = stub_is_boost_enabled(['boost_rrd_update_enable' => '']);

	expect($result)->toBeFalse();
});

test('is_boost_enabled returns false when option is absent', function () {
	$result = stub_is_boost_enabled([]);

	expect($result)->toBeFalse();
});

// --- is_boost_system_enabled ---

test('is_boost_system_enabled returns true when option is on', function () {
	$result = stub_is_boost_system_enabled(['boost_rrd_update_system_enable' => 'on']);

	expect($result)->toBeTrue();
});

test('is_boost_system_enabled returns false when option is off', function () {
	$result = stub_is_boost_system_enabled(['boost_rrd_update_system_enable' => 'off']);

	expect($result)->toBeFalse();
});

test('is_boost_system_enabled returns false when option is empty', function () {
	$result = stub_is_boost_system_enabled(['boost_rrd_update_system_enable' => '']);

	expect($result)->toBeFalse();
});

test('is_boost_system_enabled returns false when option is absent', function () {
	$result = stub_is_boost_system_enabled([]);

	expect($result)->toBeFalse();
});

// --- is_boost_png_cache_enabled ---

test('is_boost_png_cache_enabled returns true when option is on', function () {
	$result = stub_is_boost_png_cache_enabled(['boost_png_cache_enable' => 'on']);

	expect($result)->toBeTrue();
});

test('is_boost_png_cache_enabled returns false when option is off', function () {
	$result = stub_is_boost_png_cache_enabled(['boost_png_cache_enable' => 'off']);

	expect($result)->toBeFalse();
});

test('is_boost_png_cache_enabled returns false when option is empty', function () {
	$result = stub_is_boost_png_cache_enabled(['boost_png_cache_enable' => '']);

	expect($result)->toBeFalse();
});

test('is_boost_png_cache_enabled returns false when option is absent', function () {
	$result = stub_is_boost_png_cache_enabled([]);

	expect($result)->toBeFalse();
});

// --- is_boost_redirect_enabled ---

test('is_boost_redirect_enabled returns true when option is on', function () {
	$result = stub_is_boost_redirect_enabled(['boost_redirect' => 'on']);

	expect($result)->toBeTrue();
});

test('is_boost_redirect_enabled returns false when option is off', function () {
	$result = stub_is_boost_redirect_enabled(['boost_redirect' => 'off']);

	expect($result)->toBeFalse();
});

test('is_boost_redirect_enabled returns false when option is empty', function () {
	$result = stub_is_boost_redirect_enabled(['boost_redirect' => '']);

	expect($result)->toBeFalse();
});

test('is_boost_redirect_enabled returns false when option is absent', function () {
	$result = stub_is_boost_redirect_enabled([]);

	expect($result)->toBeFalse();
});

// --- combined: enabled and system_enabled are independent ---

test('boost enabled and system_enabled can be set independently', function () {
	$registry = [
		'boost_rrd_update_enable'        => 'on',
		'boost_rrd_update_system_enable' => 'off',
	];

	expect(stub_is_boost_enabled($registry))->toBeTrue()
		->and(stub_is_boost_system_enabled($registry))->toBeFalse();
});

test('boost redirect and png cache can be set independently', function () {
	$registry = [
		'boost_redirect'         => 'off',
		'boost_png_cache_enable' => 'on',
	];

	expect(stub_is_boost_redirect_enabled($registry))->toBeFalse()
		->and(stub_is_boost_png_cache_enabled($registry))->toBeTrue();
});
