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
require_once dirname(__DIR__, 2) . '/lib/database.php';

// _db_replace interpolates each column key into a backtick quoted identifier via
// cacti_safe_column_name(). Prove that sanitizer strips anything that could break
// out of the `key` context while leaving legitimate identifiers untouched.

test('valid column names pass through unchanged', function () {
	expect(cacti_safe_column_name('id'))->toBe('id')
		->and(cacti_safe_column_name('data_template_rrd_id'))->toBe('data_template_rrd_id')
		->and(cacti_safe_column_name('local_data_id'))->toBe('local_data_id');
});

test('backtick breakout in a column key is stripped', function () {
	$malicious = 'id`=1;-- ';
	$safe = cacti_safe_column_name($malicious);

	expect($safe)->not->toContain('`')
		->and($safe)->toBe('id1');

	// The interpolated identifier is a single backtick quoted token with no breakout.
	expect("`$safe`")->toBe('`id1`');
});

test('statement terminators and DDL in a column key are neutralized', function () {
	expect(cacti_safe_column_name('id; DROP TABLE host'))->toBe('idDROPTABLEhost')
		->and(cacti_safe_column_name('x`) VALUES (1);--'))->toBe('xVALUES1');
});

test('whitespace and quote characters are removed from column keys', function () {
	expect(cacti_safe_column_name("col'name"))->toBe('colname')
		->and(cacti_safe_column_name('col name'))->toBe('colname')
		->and(cacti_safe_column_name('col"name'))->toBe('colname');
});
