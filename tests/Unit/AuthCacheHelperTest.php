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

if (!function_exists('read_config_option')) {
	function read_config_option(string $config_name, bool $force = false) {
		return $GLOBALS['_test_config'][$config_name] ?? '';
	}
}

require_once __DIR__ . '/../../lib/functions.php';

describe('is_auth_cache_enabled', function () {
	beforeEach(function () {
		$GLOBALS['_test_config'] = [];
	});

	it('returns true when auth cache is on', function () {
		$GLOBALS['_test_config']['auth_cache_enabled'] = 'on';
		expect(is_auth_cache_enabled())->toBeTrue();
	});

	it('returns false when auth cache is empty', function () {
		$GLOBALS['_test_config']['auth_cache_enabled'] = '';
		expect(is_auth_cache_enabled())->toBeFalse();
	});

	it('returns false when not set', function () {
		expect(is_auth_cache_enabled())->toBeFalse();
	});
});
