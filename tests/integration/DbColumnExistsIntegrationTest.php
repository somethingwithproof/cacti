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

require_once dirname(__DIR__) . '/Helpers/CactiRealDb.php';

$conn = cacti_real_db_connect();

if ($conn === null) {
	test('db_column_exists injection boundary: no database reachable', function () {})
		->skip('no MySQL/MariaDB server reachable');

	return;
}

// Drives db_column_exists() against a live engine so the prepared SHOW COLUMNS
// query and the identifier validation are proven where injection would occur.

it('binds a malicious column value instead of interpolating it', function () use ($conn) {
	// The old code inlined the column into a quoted literal. As a bound value the
	// payload is just a column name that does not exist.
	expect(db_column_exists('host', "id' OR '1'='1", true, $conn))->toBeFalse();
});

it('does not execute a statement smuggled through the table name', function () use ($conn) {
	// The regex extracts only the leading identifier, so the trailing DROP never
	// reaches the engine and the host table survives.
	db_column_exists("host` LIKE '%'; DROP TABLE host; -- ", 'id', true, $conn);

	$still = db_fetch_cell_prepared(
		'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?',
		['host'], '', true, $conn
	);

	expect((int) $still)->toBe(1);
});
