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

// Opens a real MySQL/MariaDB connection for tests that must exercise database
// behavior the FakeMySQLPDO translator cannot reproduce (for example a prepared
// SHOW COLUMNS ... LIKE ?). Connection parameters come from the environment and
// the caller skips when no server answers.

require_once __DIR__ . '/UnitStubs.php';
require_once dirname(__DIR__, 2) . '/lib/database.php';

if (!defined('PHP_TESTING')) {
	define('PHP_TESTING', true);
}

function cacti_real_db_connect(): ?PDO {
	putenv('CACTI_TEST_BOOTSTRAP=1');

	$host = getenv('CACTI_DB_HOST') ?: '127.0.0.1';
	$port = (int) (getenv('CACTI_DB_PORT') ?: 3306);
	$user = getenv('CACTI_DB_USER') ?: 'cacti';
	$pass = getenv('CACTI_DB_PASS') ?: 'cacti';
	$name = getenv('CACTI_DB_NAME') ?: 'cacti';

	$GLOBALS['database_hostname'] = $host;
	$GLOBALS['database_port']     = (string) $port;
	$GLOBALS['database_default']  = $name;

	$conn = db_connect_real($host, $user, $pass, $name, 'mysql', $port, 1);

	return is_object($conn) ? $conn : null;
}
