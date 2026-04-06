<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

require_once dirname(__DIR__) . '/Helpers/CactiTestDatabase.php';

$testDb = CactiTestDatabase::getInstance();
$testDb->configureCactiGlobals();

require_once dirname(__DIR__, 2) . '/include/global_constants.php';

global $config;
$config['cacti_server_os'] ??= 'unix';
$config['is_web'] ??= false;
$config['base_path'] ??= dirname(__DIR__, 2);

require_once dirname(__DIR__, 2) . '/lib/functions.php';
require_once dirname(__DIR__, 2) . '/lib/database.php';

describe('db_execute_prepared', function () use ($testDb) {
	beforeEach(function () use ($testDb) {
		$this->pdo = $testDb->getPdo();
		$this->pdo->exec("DELETE FROM settings WHERE name LIKE 'test_%'");
	});

	it('inserts a row into the database', function () {
		$result = db_execute_prepared(
			'INSERT INTO settings (name, value) VALUES (?, ?)',
			['test_key_1', 'test_value_1']
		);

		expect($result)->not->toBeFalse();
		expect($this->pdo->query("SELECT value FROM settings WHERE name = 'test_key_1'")->fetchColumn())
			->toBe('test_value_1');
	});

	it('updates an existing row', function () {
		$this->pdo->exec("INSERT INTO settings (name, value) VALUES ('test_key_2', 'old')");

		db_execute_prepared(
			'UPDATE settings SET value = ? WHERE name = ?',
			['new', 'test_key_2']
		);

		expect($this->pdo->query("SELECT value FROM settings WHERE name = 'test_key_2'")->fetchColumn())
			->toBe('new');
	});
});

describe('db_fetch_cell_prepared', function () use ($testDb) {
	beforeEach(function () use ($testDb) {
		$this->pdo = $testDb->getPdo();
		$this->pdo->exec("DELETE FROM settings WHERE name LIKE 'test_%'");
	});

	it('returns a single scalar value', function () {
		$this->pdo->exec("INSERT INTO settings (name, value) VALUES ('test_cell', '42')");

		expect(db_fetch_cell_prepared('SELECT value FROM settings WHERE name = ?', ['test_cell']))
			->toBe('42');
	});

	it('returns falsy when no rows match', function () {
		$result = db_fetch_cell_prepared(
			'SELECT value FROM settings WHERE name = ?',
			['nonexistent_key_xyz']
		);

		expect($result === '' || $result === false)->toBeTrue();
	});
});

describe('db_fetch_row_prepared', function () use ($testDb) {
	beforeEach(function () use ($testDb) {
		$this->pdo = $testDb->getPdo();
		$this->pdo->exec("DELETE FROM settings WHERE name LIKE 'test_%'");
	});

	it('returns an associative array for a matched row', function () {
		$this->pdo->exec("INSERT INTO settings (name, value) VALUES ('test_row', 'hello')");

		$result = db_fetch_row_prepared(
			'SELECT name, value FROM settings WHERE name = ?',
			['test_row']
		);

		expect($result)
			->toBeArray()
			->and($result['name'])->toBe('test_row')
			->and($result['value'])->toBe('hello');
	});
});

describe('db_fetch_assoc_prepared', function () use ($testDb) {
	beforeEach(function () use ($testDb) {
		$this->pdo = $testDb->getPdo();
		$this->pdo->exec("DELETE FROM settings WHERE name LIKE 'test_%'");
		$this->pdo->exec("INSERT INTO settings (name, value) VALUES ('test_assoc_a', '1')");
		$this->pdo->exec("INSERT INTO settings (name, value) VALUES ('test_assoc_b', '2')");
	});

	it('returns multiple rows as indexed array', function () {
		$result = db_fetch_assoc_prepared(
			"SELECT name, value FROM settings WHERE name LIKE ? ORDER BY name",
			['test_assoc_%']
		);

		expect($result)
			->toBeArray()
			->toHaveCount(2)
			->and($result[0]['name'])->toBe('test_assoc_a')
			->and($result[1]['name'])->toBe('test_assoc_b');
	});
});

describe('db_qstr', function () {
	it('escapes single quotes via PDO quote()', function () {
		$result = db_qstr("O'Brien");

		expect($result)
			->toContain('O')
			->toContain('Brien')
			->not->toBe("'O'Brien'");
	});

	it('returns the string NULL for null input', function () {
		expect(db_qstr(null))->toBe('NULL');
	});
});

describe('sql_save', function () use ($testDb) {
	beforeEach(function () use ($testDb) {
		$this->pdo = $testDb->getPdo();
		$this->pdo->exec("DELETE FROM settings WHERE name LIKE 'test_%'");
	});

	it('inserts a new row', function () {
		$result = sql_save(
			['name' => 'test_sql_save', 'value' => 'saved_value'],
			'settings', 'name', false
		);

		expect($result)->not->toBeFalse();
		expect($this->pdo->query("SELECT value FROM settings WHERE name = 'test_sql_save'")->fetchColumn())
			->toBe('saved_value');
	});

	it('updates on duplicate key', function () {
		$this->pdo->exec("INSERT INTO settings (name, value) VALUES ('test_dup', 'first')");

		sql_save(
			['name' => 'test_dup', 'value' => 'updated'],
			'settings', 'name', false
		);

		expect($this->pdo->query("SELECT value FROM settings WHERE name = 'test_dup'")->fetchColumn())
			->toBe('updated');
	});
});

describe('SQL injection resistance', function () use ($testDb) {
	beforeEach(function () use ($testDb) {
		$this->pdo = $testDb->getPdo();
	});

	dataset('injection payloads', [
		'drop table'        => ["'; DROP TABLE settings; --"],
		'union select'      => ["' UNION SELECT password FROM user_auth; --"],
		'boolean blind'     => ["' OR '1'='1"],
		'stacked queries'   => ["'; INSERT INTO settings VALUES('pwned','yes'); --"],
	]);

	it('stores injection payloads as literal strings', function (string $payload) {
		db_execute_prepared(
			'REPLACE INTO settings (name, value) VALUES (?, ?)',
			['test_sqli', $payload]
		);

		$stored = $this->pdo->query("SELECT value FROM settings WHERE name = 'test_sqli'")->fetchColumn();

		expect($stored)->toBe($payload);
		expect($this->pdo->query("SHOW TABLES LIKE 'settings'")->rowCount())->toBe(1);
	})->with('injection payloads');
});

describe('db_table_exists', function () {
	it('returns true for an existing table', function () {
		expect(db_table_exists('settings'))->toBeTrue();
	});

	it('returns false for a nonexistent table', function () {
		expect(db_table_exists('nonexistent_table_xyz'))->toBeFalse();
	});
});
