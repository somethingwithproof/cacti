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
 * Regression tests for issue #6993.
 *
 * Fix: db_connect_real() sets PDO::ERRMODE_WARNING instead of ERRMODE_SILENT so
 *      an unchecked query error still reaches the log. db_execute_prepared()
 *      then recovers the real driver code from the statement's errorInfo() in
 *      the catch block, because the thrown exception carries the PHP error
 *      level, not the SQL error number.
 */

$dbSource = file_get_contents(__DIR__ . '/../../lib/database.php');

test('#6993: the connection uses ERRMODE_WARNING, not ERRMODE_SILENT', function () use ($dbSource) {
	expect($dbSource)->toContain('PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING');
	expect($dbSource)->not->toContain('PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT');
});

test('#6993: the catch block recovers the driver code from errorInfo()', function () use ($dbSource) {
	$start = strpos($dbSource, 'function db_execute_prepared(');
	$end   = strpos($dbSource, "\nfunction ", $start + 1);
	$body  = substr($dbSource, $start, $end - $start);

	expect($body)->toContain('$errorinfo = $query->errorInfo();');
	expect($body)->toContain('$ex->getCode()');
});

test('#6993: db_warning_handler turns a PHP warning into a catchable exception', function () {
	require_once __DIR__ . '/../../lib/database.php';

	$threw = false;
	try {
		db_warning_handler(E_WARNING, 'query failed', __FILE__, __LINE__);
	} catch (Exception $ex) {
		$threw = true;
		expect($ex->getMessage())->toBe('query failed');
		expect($ex->getCode())->toBe(E_WARNING);
	}

	expect($threw)->toBeTrue();
});
