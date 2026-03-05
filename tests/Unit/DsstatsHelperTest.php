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

// is_dsstats_enabled() and is_dsstats_gdg_enabled() delegate to read_config_option().
// Stub it here so the helpers can be exercised without a database.

function read_config_option(string $option): mixed {
	return $GLOBALS['_test_config'][$option] ?? '';
}

require_once __DIR__ . '/../../lib/dsstats.php';

// --- is_dsstats_enabled ---

test('is_dsstats_enabled returns true when option is on', function () {
	$GLOBALS['_test_config']['dsstats_enable'] = 'on';
	expect(is_dsstats_enabled())->toBeTrue();
});

test('is_dsstats_enabled returns false when option is off', function () {
	$GLOBALS['_test_config']['dsstats_enable'] = 'off';
	expect(is_dsstats_enabled())->toBeFalse();
});

test('is_dsstats_enabled returns false when option is absent', function () {
	unset($GLOBALS['_test_config']['dsstats_enable']);
	expect(is_dsstats_enabled())->toBeFalse();
});

// --- is_dsstats_gdg_enabled ---

test('is_dsstats_gdg_enabled returns true when option is on', function () {
	$GLOBALS['_test_config']['dsstats_gdg_enable'] = 'on';
	expect(is_dsstats_gdg_enabled())->toBeTrue();
});

test('is_dsstats_gdg_enabled returns false when option is off', function () {
	$GLOBALS['_test_config']['dsstats_gdg_enable'] = 'off';
	expect(is_dsstats_gdg_enabled())->toBeFalse();
});

test('is_dsstats_gdg_enabled returns false when option is absent', function () {
	unset($GLOBALS['_test_config']['dsstats_gdg_enable']);
	expect(is_dsstats_gdg_enabled())->toBeFalse();
});
