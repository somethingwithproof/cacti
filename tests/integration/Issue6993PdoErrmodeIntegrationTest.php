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
 * Issue #6993 across a real PDO boundary. db_execute_prepared() runs against a
 * live in-memory SQLite connection set to ERRMODE_WARNING, the same error mode
 * db_connect_real() now applies to MySQL. Two failure shapes are driven:
 *
 *   - a constraint violation, where PDO populates errorInfo() with the driver
 *     code (the deadlock/lock retry depends on reading that code correctly);
 *   - a PHP warning raised during execute() with no PDO error info, where the
 *     recovery falls back to the exception code and message.
 *
 * The ERRMODE_WARNING setAttribute() call itself lives in db_connect_real() and
 * only runs against a live MySQL server, so it is not executed here; this test
 * reproduces the identical error mode on SQLite to exercise the recovery it
 * enables.
 */

if (!function_exists('cacti_count')) {
	function cacti_count($a) {
		return is_array($a) ? count($a) : 0;
	}
}

if (!function_exists('clean_up_lines')) {
	function clean_up_lines($string) {
		return str_replace(array("\n", "\r"), ' ', (string) $string);
	}
}

if (!function_exists('cacti_log')) {
	function cacti_log($message, $stdout = false, $environ = 'CMDPHP', $level = 0) {
		return true;
	}
}

require_once __DIR__ . '/../../lib/database.php';

function make_errmode_warning_pdo(): PDO {
	$pdo = new PDO('sqlite::memory:');
	$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);
	$pdo->exec('CREATE TABLE t (id INTEGER PRIMARY KEY NOT NULL, v TEXT NOT NULL)');
	$pdo->exec("INSERT INTO t (id, v) VALUES (1, 'a')");

	return $pdo;
}

beforeEach(function () {
	global $config, $database_total_queries, $database_last_error, $database_log, $affected_rows, $error_logged;

	if (!extension_loaded('pdo_sqlite')) {
		$this->markTestSkipped('pdo_sqlite required for the PDO error-mode boundary test');
	}

	$config                 = array();
	$database_total_queries = 0;
	$database_last_error    = '';
	$database_log           = false;
	$affected_rows          = array();
	$error_logged           = array();
});

test('#6993: a constraint failure recovers the driver code from errorInfo()', function () {
	global $database_last_error;

	$pdo = make_errmode_warning_pdo();

	// Duplicate primary key: prepares cleanly, fails at execute with a real
	// SQLSTATE and driver code in errorInfo().
	$result = db_execute_prepared("INSERT INTO t (id, v) VALUES (1, 'b')", array(), false, $pdo);

	expect($result)->toBeFalse();
	// SQLite reports driver code 19 for a constraint violation; the recovery
	// must surface it rather than the E_WARNING level.
	expect($database_last_error)->toContain('Error 19');
});

test('#6993: a warning with no error info falls back to the exception code', function () {
	global $database_last_error;

	$pdo = make_errmode_warning_pdo();

	// Passing an array as a bound value raises a PHP "Array to string
	// conversion" warning during execute() while the statement errorInfo stays
	// empty, exercising the exception-code fallback.
	$result = db_execute_prepared('INSERT INTO t (id, v) VALUES (?, ?)', array(2, array('x')), false, $pdo);

	expect($result)->toBeFalse();
	expect($database_last_error)->toContain('Array to string conversion');
});
