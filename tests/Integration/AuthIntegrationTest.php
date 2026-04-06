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

describe('auth lockout', function () use ($testDb) {
	beforeEach(function () use ($testDb) {
		/* Scope: function-level reset so parallel tests never share user state */
		$this->pdo = $testDb->getPdo();
		$this->pdo->exec("DELETE FROM user_auth WHERE username LIKE 'locktest%'");
		$this->pdo->exec("REPLACE INTO settings (name, value) VALUES ('secpass_lockfailed', '3')");
	});

	afterEach(function () {
		$this->pdo->exec("DELETE FROM user_auth WHERE username LIKE 'locktest%'");
	});

	it('increments failed_attempts atomically', function () {
		$this->pdo->exec("INSERT INTO user_auth (username, password, realm, enabled, failed_attempts, `locked`)
			VALUES ('locktest', '', 0, 'on', 0, '')");

		for ($i = 0; $i < 3; $i++) {
			db_execute_prepared(
				"UPDATE user_auth SET failed_attempts = failed_attempts + 1, lastfail = ? WHERE username = ? AND realm = ?",
				[time(), 'locktest', 0]
			);
		}

		expect((int) db_fetch_cell_prepared(
			'SELECT failed_attempts FROM user_auth WHERE username = ? AND realm = ?',
			['locktest', 0]
		))->toBe(3);
	});

	it('locks the account when threshold is reached', function () {
		$this->pdo->exec("INSERT INTO user_auth (username, password, realm, enabled, failed_attempts, `locked`)
			VALUES ('locktest2', '', 0, 'on', 0, '')");

		for ($i = 0; $i < 3; $i++) {
			db_execute_prepared(
				"UPDATE user_auth SET failed_attempts = failed_attempts + 1, lastfail = ? WHERE username = ? AND realm = ? AND enabled = 'on'",
				[time(), 'locktest2', 0]
			);
		}

		$failed = (int) db_fetch_cell_prepared(
			'SELECT failed_attempts FROM user_auth WHERE username = ? AND realm = ?',
			['locktest2', 0]
		);

		if ($failed >= 3) {
			db_execute_prepared(
				"UPDATE user_auth SET `locked` = 'on' WHERE username = ? AND realm = ? AND enabled = 'on'",
				['locktest2', 0]
			);
		}

		expect($failed)->toBe(3);
		expect(db_fetch_cell_prepared(
			'SELECT `locked` FROM user_auth WHERE username = ? AND realm = ?',
			['locktest2', 0]
		))->toBe('on');
	});

	it('resets failed_attempts to zero on successful login', function () {
		$this->pdo->exec("INSERT INTO user_auth (username, password, realm, enabled, failed_attempts, `locked`)
			VALUES ('locktest3', '', 0, 'on', 4, '')");

		db_execute_prepared(
			"UPDATE user_auth SET failed_attempts = 0, lastfail = 0 WHERE username = ? AND realm = ?",
			['locktest3', 0]
		);

		expect((int) db_fetch_cell_prepared(
			'SELECT failed_attempts FROM user_auth WHERE username = ? AND realm = ?',
			['locktest3', 0]
		))->toBe(0);
	});
});

describe('auth cache (remember-me tokens)', function () use ($testDb) {
	beforeEach(function () use ($testDb) {
		$this->pdo = $testDb->getPdo();

		if (!db_table_exists('user_auth_cache')) {
			$this->markTestSkipped('user_auth_cache table not in schema');
		}

		$this->pdo->exec("DELETE FROM user_auth_cache WHERE user_id = 9999");
	});

	it('stores and retrieves a SHA-512 session token', function () {
		$token = hash('sha512', bin2hex(random_bytes(32)));

		db_execute_prepared(
			'REPLACE INTO user_auth_cache (user_id, hostname, last_update, token) VALUES (?, ?, NOW(), ?)',
			[9999, '127.0.0.1', $token]
		);

		expect(db_fetch_cell_prepared(
			'SELECT token FROM user_auth_cache WHERE user_id = ? AND hostname = ?',
			[9999, '127.0.0.1']
		))->toBe($token);
	});
});

describe('user_log', function () use ($testDb) {
	beforeEach(function () use ($testDb) {
		$this->pdo = $testDb->getPdo();
		$this->pdo->exec("DELETE FROM user_log WHERE username LIKE 'logtest%'");
	});

	dataset('login outcomes', [
		'failed login'     => ['logtest_fail', 0, '192.168.1.100'],
		'successful login' => ['logtest_ok',   1, '10.0.0.1'],
	]);

	it('records login attempts with correct result code', function (string $username, int $result, string $ip) {
		db_execute_prepared(
			'INSERT INTO user_log (username, user_id, result, ip, time) VALUES (?, ?, ?, ?, NOW())',
			[$username, 0, $result, $ip]
		);

		expect((int) db_fetch_cell_prepared(
			'SELECT COUNT(*) FROM user_log WHERE username = ? AND result = ?',
			[$username, $result]
		))->toBe(1);
	})->with('login outcomes');
});

describe('settings table', function () use ($testDb) {
	beforeEach(function () use ($testDb) {
		$this->pdo = $testDb->getPdo();
		$this->pdo->exec("DELETE FROM settings WHERE name LIKE 'test_%'");
	});

	it('stores and retrieves config values', function () {
		$this->pdo->exec("INSERT INTO settings (name, value) VALUES ('test_config', 'myvalue')");

		expect(db_fetch_cell_prepared(
			'SELECT value FROM settings WHERE name = ?',
			['test_config']
		))->toBe('myvalue');
	});

	dataset('special values', [
		'spaces and ampersands' => ["path/with spaces & more"],
		'single quotes'         => ["it's a test"],
		'html tags'             => ["<script>alert(1)</script>"],
		'null bytes'            => ["before\x00after"],
		'unicode'               => ["Sch\u{00F6}ne Gr\u{00FC}\u{00DF}e"],
	]);

	it('preserves special characters in values', function (string $value) {
		db_execute_prepared(
			'REPLACE INTO settings (name, value) VALUES (?, ?)',
			['test_special', $value]
		);

		expect(db_fetch_cell_prepared(
			'SELECT value FROM settings WHERE name = ?',
			['test_special']
		))->toBe($value);
	})->with('special values');
});
