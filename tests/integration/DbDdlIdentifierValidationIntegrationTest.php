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
 * Issue: DDL identifier validation against a live MySQL schema. The guards in
 * db_update_table()'s column and index loops sit after db_table_exists(), so
 * they only run when the table already exists. This test creates a scratch
 * table on a real connection, then confirms an unsafe column or index
 * identifier is rejected without altering the schema. It skips when no MySQL
 * server is configured.
 */

require_once dirname(__DIR__, 2) . '/lib/database.php';
require_once dirname(__DIR__, 2) . '/lib/functions.php';

function ddl_live_connection() {
	$host = getenv('CACTI_DB_HOST') ?: 'localhost';
	$user = getenv('CACTI_DB_USER') ?: 'cactiuser';
	$pass = getenv('CACTI_DB_PASS') ?: 'cactipassword';
	$name = getenv('CACTI_DB_NAME') ?: 'cacti';
	$port = (int) (getenv('CACTI_DB_PORT') ?: 3306);

	$conn = db_connect_real($host, $user, $pass, $name, 'mysql', $port, 1);

	return is_object($conn) ? $conn : false;
}

beforeEach(function () {
	$conn = ddl_live_connection();

	if ($conn === false) {
		$this->markTestSkipped('no live MySQL connection configured');
	}

	$this->conn = $conn;
	db_execute('DROP TABLE IF EXISTS ddl_guard_scratch', false, $conn);
});

afterEach(function () {
	if (isset($this->conn) && is_object($this->conn)) {
		db_execute('DROP TABLE IF EXISTS ddl_guard_scratch', false, $this->conn);
	}
});

test('db_table_create builds a legitimate table on a live connection', function () {
	$data = array(
		'columns' => array(
			array('name' => 'id', 'type' => 'int', 'unsigned' => true, 'NULL' => false, 'auto_increment' => true),
			array('name' => 'label', 'type' => 'varchar(64)', 'NULL' => false),
		),
		'primary' => 'id',
	);

	expect(db_table_create('ddl_guard_scratch', $data, false, $this->conn))->toBeTrue();
	expect(db_table_exists('ddl_guard_scratch', false, $this->conn))->toBeTrue();
});

test('db_update_table rejects an unsafe column identifier on an existing table', function () {
	db_table_create('ddl_guard_scratch', array(
		'columns' => array(array('name' => 'id', 'type' => 'int', 'NULL' => false)),
		'primary' => 'id',
	), false, $this->conn);

	$malicious = array(
		'columns' => array(array('name' => 'evil` int; DROP TABLE ddl_guard_scratch; --')),
	);

	expect(db_update_table('ddl_guard_scratch', $malicious, false, false, $this->conn))->toBeFalse();
	// The guard fired before any ALTER, so the table is still intact.
	expect(db_table_exists('ddl_guard_scratch', false, $this->conn))->toBeTrue();
});

test('db_update_table rejects an unsafe index identifier on an existing table', function () {
	db_table_create('ddl_guard_scratch', array(
		'columns' => array(array('name' => 'id', 'type' => 'int', 'NULL' => false)),
		'primary' => 'id',
	), false, $this->conn);

	$malicious = array(
		'columns' => array(array('name' => 'id', 'type' => 'int', 'NULL' => false)),
		'keys'    => array(array('name' => 'idx`) ,ADD INDEX x (`id', 'columns' => array('id'))),
	);

	expect(db_update_table('ddl_guard_scratch', $malicious, false, false, $this->conn))->toBeFalse();
	expect(db_table_exists('ddl_guard_scratch', false, $this->conn))->toBeTrue();
});
