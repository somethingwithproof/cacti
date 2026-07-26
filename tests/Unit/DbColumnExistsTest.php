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
	test('db_column_exists: no database reachable', function () {})
		->skip('no MySQL/MariaDB server reachable');

	return;
}

// db_column_exists() validates and backtick-quotes the table identifier and
// binds the column as a parameter, so it resolves real columns while an
// unparsable table name short-circuits to false.

it('reports an existing column as present', function () use ($conn) {
	expect(db_column_exists('host', 'hostname', true, $conn))->toBeTrue();
});

it('reports a missing column as absent', function () use ($conn) {
	expect(db_column_exists('host', 'no_such_column_xyz', true, $conn))->toBeFalse();
});

it('accepts a database-qualified table name', function () use ($conn) {
	expect(db_column_exists('cacti.host', 'id', true, $conn))->toBeTrue();
});

it('returns false when the table name has no identifier characters', function () use ($conn) {
	expect(db_column_exists('', 'id', true, $conn))->toBeFalse();
});
