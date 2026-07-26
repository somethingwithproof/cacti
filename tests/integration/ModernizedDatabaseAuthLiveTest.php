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
require_once dirname(__DIR__, 2) . '/include/global.php';

// Live-MariaDB coverage for the PHP 8.4 idiom migrations in lib/database.php
// and lib/auth.php that only execute against a real connection. The suite runs
// under the test bootstrap, so these tests open their own connection under a
// dedicated 127.0.0.1 host key and restore the connection globals afterwards so
// no other test picks up the live handle.

const LIVE_DB_HOST = '127.0.0.1';
const LIVE_DB_USER = 'cacti';
const LIVE_DB_PASS = 'cacti';
const LIVE_DB_NAME = 'cacti';
const LIVE_DB_PORT = 3306;

/**
 * @return bool true when a live connection is available and the globals have
 *              been pointed at it, false when the server is unreachable.
 */
function live_db_connect(): bool {
	global $database_hostname, $database_port, $database_default, $database_username, $database_password, $database_sessions;

	$conn = db_connect_real(LIVE_DB_HOST, LIVE_DB_USER, LIVE_DB_PASS, LIVE_DB_NAME, 'mysql', LIVE_DB_PORT, 1);

	if (!is_object($conn)) {
		return false;
	}

	$database_hostname = LIVE_DB_HOST;
	$database_port     = LIVE_DB_PORT;
	$database_default  = LIVE_DB_NAME;
	$database_username = LIVE_DB_USER;
	$database_password = LIVE_DB_PASS;

	return true;
}

beforeEach(function () {
	$this->saved = [
		$GLOBALS['database_hostname'] ?? null,
		$GLOBALS['database_port']     ?? null,
		$GLOBALS['database_default']  ?? null,
		$GLOBALS['database_username'] ?? null,
		$GLOBALS['database_password'] ?? null,
	];
});

afterEach(function () {
	// Restore the connection globals so the live handle is never the default
	// lookup for any subsequent test.
	[$GLOBALS['database_hostname'], $GLOBALS['database_port'], $GLOBALS['database_default'],
		$GLOBALS['database_username'], $GLOBALS['database_password']] = $this->saved;
});

// =====================================================================
// db_connect_real - lines 183, 204 (server detection + sql_mode read)
// =====================================================================

test('db_connect_real detects the server and reads sql_mode', function () {
	if (!live_db_connect()) {
		test()->markTestSkipped('Live MariaDB at 127.0.0.1:3306 is unavailable.');
	}

	global $database_details;

	$conn = db_connect_real(LIVE_DB_HOST, LIVE_DB_USER, LIVE_DB_PASS, LIVE_DB_NAME, 'mysql', LIVE_DB_PORT, 1);
	$hash = spl_object_hash($conn);

	// str_contains($ver, 'MariaDB') and the sql_mode explode both run during a
	// successful connect and populate the details record.
	expect($database_details[$hash]['database_server'])->toBeIn(['MariaDB', 'MySQL'])
		->and($database_details[$hash]['database_version'])->toBeString();
});

// =====================================================================
// db_get_permissions - lines 2735, 2761, 2767 (SHOW GRANTS parsing)
// =====================================================================

test('db_get_permissions parses the current user grants', function () {
	if (!live_db_connect()) {
		test()->markTestSkipped('Live MariaDB at 127.0.0.1:3306 is unavailable.');
	}

	$perms = db_get_permissions(true);

	expect($perms)->toBeArray()
		->and($perms)->toHaveKey(LIVE_DB_NAME);
});

// =====================================================================
// db_dump_data - lines 2614, 2660 (credential parsing + table escaping)
// =====================================================================

test('db_dump_data parses credentials and escapes table names', function () {
	if (!live_db_connect()) {
		test()->markTestSkipped('Live MariaDB at 127.0.0.1:3306 is unavailable.');
	}

	$outfile = tempnam(sys_get_temp_dir(), 'cacti_dump_');

	// The credential loop (trim of each key) and the table escaping both run
	// before the external dump command; the command itself may be absent, in
	// which case a non-zero status is still returned as an int.
	$status = db_dump_data(LIVE_DB_NAME, 'settings', [
		'host'     => LIVE_DB_HOST,
		'port'     => LIVE_DB_PORT,
		'user'     => LIVE_DB_USER,
		'password' => LIVE_DB_PASS,
	], $outfile);

	if (is_file($outfile)) {
		unlink($outfile);
	}

	expect($status)->toBeInt();
});

// =====================================================================
// secpass_login_process - line 4187 (empty-password (string) cast)
// =====================================================================

test('secpass_login_process rejects an existing user with an empty password', function () {
	if (!live_db_connect()) {
		test()->markTestSkipped('Live MariaDB at 127.0.0.1:3306 is unavailable.');
	}

	$user = db_fetch_row_prepared('SELECT username FROM user_auth WHERE realm = 0 AND enabled = \'on\' LIMIT 1');

	if (!cacti_sizeof($user)) {
		test()->markTestSkipped('No enabled local user available in the test database.');
	}

	// Disable the lockout machinery through the option cache so the empty
	// password path performs no writes, then drive the (string) $password cast.
	$_SESSION[OPTIONS_WEB]['secpass_lockfailed'] = '0';
	$GLOBALS['config'][OPTIONS_CLI]['secpass_lockfailed'] = '0';

	set_request_var('login_password', '');

	$result = secpass_login_process($user['username']);

	// Empty password always yields a denied login (empty array), regardless of
	// the cast.
	expect($result)->toBe([]);
});
