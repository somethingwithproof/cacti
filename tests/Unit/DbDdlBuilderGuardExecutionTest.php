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
 * Execution coverage for the DDL identifier guards. Each builder rejects an
 * unsafe identifier before composing its backtick-quoted statement. A dummy
 * connection object satisfies the is_object($db_conn) fast path so the guard
 * runs without a live server, and every call returns at a guard rather than
 * reaching a query.
 *
 * db_update_table()'s column-loop and index-loop guards sit after
 * db_table_exists(), which needs a live MySQL schema, so those two guards are
 * validated as a source contract in DbIdentifierValidationTest instead.
 *
 * This file requires lib/database.php before DbIdentifierValidationTest is
 * collected (alphabetical order), so that test's function_exists() fallback
 * skips its eval() of the predicates and nothing is declared twice.
 */

if (!function_exists('cacti_log')) {
	function cacti_log($message, $stdout = false, $environ = 'CMDPHP', $level = 0) {
		return true;
	}
}

require_once __DIR__ . '/../../lib/database.php';

/** A stand-in connection: is_object() is all the fast path checks. */
function ddl_conn() {
	return new stdClass();
}

test('db_add_column rejects unsafe table, column and AFTER identifiers', function () {
	$c = ddl_conn();

	expect(db_add_column('bad`table', array('name' => 'x'), false, $c))->toBeFalse();
	expect(db_add_column('host', array('name' => 'bad`col'), false, $c))->toBeFalse();
	expect(db_add_column('host', array('name' => 'good', 'after' => 'bad`col'), false, $c))->toBeFalse();
});

test('db_remove_column rejects an unsafe identifier', function () {
	$c = ddl_conn();

	expect(db_remove_column('bad`table', 'col', false, $c))->toBeFalse();
	expect(db_remove_column('host', 'bad`col', false, $c))->toBeFalse();
});

test('db_add_index rejects unsafe identifiers, index type and columns', function () {
	$c = ddl_conn();

	// Unsafe index type.
	expect(db_add_index('host', 'INDEX; DROP', 'idx', 'hostname', false, $c))->toBeFalse();
	// Valid table/key/type, unsafe column.
	expect(db_add_index('host', 'INDEX', 'idx', array('bad`col'), false, $c))->toBeFalse();
});

test('db_update_table rejects unsafe table, index and primary identifiers', function () {
	$c = ddl_conn();

	// Unsafe table.
	expect(db_update_table('bad`table', array(), false, false, $c))->toBeFalse();

	// Unsafe index name.
	expect(db_update_table('host', array('keys' => array(array('name' => 'bad`idx'))), false, false, $c))->toBeFalse();

	// Valid index name, unsafe index column.
	expect(db_update_table('host', array('keys' => array(array('name' => 'idx', 'columns' => array('bad`col')))), false, false, $c))->toBeFalse();

	// Unsafe primary key column.
	expect(db_update_table('host', array('primary' => array('bad`col')), false, false, $c))->toBeFalse();
});

test('db_table_create rejects unsafe table, column, primary and index identifiers', function () {
	$c = ddl_conn();

	// Unsafe table.
	expect(db_table_create('bad`table', array(), false, $c))->toBeFalse();

	// Unsafe column name.
	expect(db_table_create('host', array('columns' => array(array('name' => 'bad`col'))), false, $c))->toBeFalse();

	// Valid columns, unsafe primary key column.
	expect(db_table_create('host', array('columns' => array(array('name' => 'id')), 'primary' => array('bad`col')), false, $c))->toBeFalse();

	// A key with no name is skipped; a later key with an unsafe name is rejected.
	expect(db_table_create('host', array(
		'columns' => array(array('name' => 'id')),
		'primary' => array('id'),
		'keys'    => array(array('columns' => array('id')), array('name' => 'bad`idx')),
	), false, $c))->toBeFalse();

	// Valid key name, unsafe index column.
	expect(db_table_create('host', array(
		'columns' => array(array('name' => 'id')),
		'keys'    => array(array('name' => 'idx', 'columns' => array('bad`col'))),
	), false, $c))->toBeFalse();
});
