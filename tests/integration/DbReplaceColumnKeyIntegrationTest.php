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

// Integration coverage for issue 7028. Runs the real _db_replace()/db_replace()
// path against a live MySQL/MariaDB server so cacti_safe_column_name() is proven
// at the point the identifier reaches the engine's backtick quoting. Connection
// parameters come from the environment; the test skips when no server answers,
// so it stays inert on a database-less CI runner.

require_once dirname(__DIR__) . '/Helpers/UnitStubs.php';
require_once dirname(__DIR__, 2) . '/lib/database.php';

if (!defined('PHP_TESTING')) {
	define('PHP_TESTING', true);
}

putenv('CACTI_TEST_BOOTSTRAP=1');

function issue7028_connect(): ?PDO {
	$host = getenv('CACTI_DB_HOST') ?: '127.0.0.1';
	$port = (int) (getenv('CACTI_DB_PORT') ?: 3306);
	$user = getenv('CACTI_DB_USER') ?: 'cacti';
	$pass = getenv('CACTI_DB_PASS') ?: 'cacti';
	$name = getenv('CACTI_DB_NAME') ?: 'cacti';

	$conn = db_connect_real($host, $user, $pass, $name, 'mysql', $port, 1);

	return is_object($conn) ? $conn : null;
}

$conn = issue7028_connect();

if ($conn === null) {
	test('db_replace column key hardening: no database reachable', function () {})
		->skip('no MySQL/MariaDB server reachable for integration coverage');

	return;
}

beforeEach(function () use ($conn) {
	$this->conn = $conn;
	$this->conn->exec('DROP TEMPORARY TABLE IF EXISTS test_7028_cols');
	$this->conn->exec('CREATE TEMPORARY TABLE test_7028_cols (id INT PRIMARY KEY, val VARCHAR(64))');
});

it('replaces a row through the real engine when column keys are legitimate', function () {
	db_replace('test_7028_cols', ['id' => 1, 'val' => db_qstr('legit')], 'id', $this->conn);

	$row = $this->conn->query('SELECT val FROM test_7028_cols WHERE id = 1')->fetch(PDO::FETCH_ASSOC);

	expect($row)->not->toBeFalse()
		->and($row['val'])->toBe('legit');
});

it('neutralizes an injecting column key against the real engine', function () {
	// The array key carries a statement-breakout payload. Once sanitized it is
	// an unknown column, so the INSERT fails and the trailing DROP never runs.
	$payload = 'val`); DROP TABLE test_7028_cols;-- ';

	db_replace('test_7028_cols', ['id' => 2, $payload => db_qstr('x')], 'id', $this->conn);

	// A dropped table would raise here; a surviving table returns a clean count.
	$rows = $this->conn->query('SELECT COUNT(*) c FROM test_7028_cols')->fetch(PDO::FETCH_ASSOC);

	expect((int) $rows['c'])->toBe(0);
});
