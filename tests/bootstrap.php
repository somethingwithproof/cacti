<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Test Bootstrap - Global State Isolation Shim
 *
 * This file configures the test environment so Cacti library code can be
 * included without a running web server or database. Integration tests
 * that need a real database use CactiTestDatabase (Testcontainers).
 *
 * Environment variable: CACTI_TEST_UNIT=1 activates DB function stubs.
 */

$cactiBase = dirname(__DIR__);

/* -----------------------------------------------------------------------
 * 1. Core constants that Cacti expects at include time.
 *    Production: set in include/global.php from the install path.
 *    Test: set from the repo root so lib/ files resolve correctly.
 * ----------------------------------------------------------------------- */
if (!defined('CACTI_PATH_BASE')) {
	define('CACTI_PATH_BASE', $cactiBase);
}

if (!defined('CACTI_PATH_INCLUDE')) {
	define('CACTI_PATH_INCLUDE', $cactiBase . '/include');
}

if (!defined('CACTI_PATH_LIBRARY')) {
	define('CACTI_PATH_LIBRARY', $cactiBase . '/lib');
}

if (!defined('CACTI_PATH_PLUGINS')) {
	define('CACTI_PATH_PLUGINS', $cactiBase . '/plugins');
}

if (!defined('CACTI_PATH_RRA')) {
	/* Use a temp dir so tests never touch real RRD files */
	define('CACTI_PATH_RRA', sys_get_temp_dir() . '/cacti-test-rra-' . getmypid());
}

if (!defined('CACTI_PATH_LOG')) {
	define('CACTI_PATH_LOG', sys_get_temp_dir() . '/cacti-test.log');
}

/* -----------------------------------------------------------------------
 * 2. Global $config array that nearly every Cacti file reads.
 *    Production: populated by include/config.php + include/global.php.
 *    Test: minimal set of keys that prevent undefined index errors.
 * ----------------------------------------------------------------------- */
global $config;
$config = [
	'base_path'        => $cactiBase,
	'library_path'     => $cactiBase . '/lib',
	'include_path'     => $cactiBase . '/include',
	'rra_path'         => CACTI_PATH_RRA,
	'cacti_server_os'  => 'unix',
	'is_web'           => false,
	'poller_id'        => 1,
	'url_path'         => '/',
	'cacti_db_version' => '1.3.0',
];

/* -----------------------------------------------------------------------
 * 3. Stub cacti_log() to write to a temp file instead of the DB.
 *    Production: writes to cacti.log and optionally to the DB log table.
 *    Test: writes to CACTI_PATH_LOG so tests can assert on log output
 *          without a database connection.
 *    NOTE: Only defined when CACTI_TEST_UNIT=1. Integration tests and
 *    unit tests that include global.php get the real cacti_log().
 * ----------------------------------------------------------------------- */

/* -----------------------------------------------------------------------
 * 4. Unit-mode DB stubs (activated by CACTI_TEST_UNIT=1).
 *    Production: these functions query MySQL via PDO.
 *    Test (unit): return safe defaults so unit tests never touch a DB.
 *    Integration tests do NOT set this variable and use real connections.
 * ----------------------------------------------------------------------- */
if (getenv('CACTI_TEST_UNIT') === '1') {
	if (!defined('PHP_TESTING')) {
		define('PHP_TESTING', true);
	}

	/* Stub cacti_log for unit tests that don't load global.php */
	if (!function_exists('cacti_log')) {
		function cacti_log($message, $echo = false, $subsystem = 'SYSTEM', $verbosity = 1) {
			$line = date('Y-m-d H:i:s') . " [$subsystem] $message\n";
			file_put_contents(CACTI_PATH_LOG, $line, FILE_APPEND);
		}
	}

	if (!function_exists('db_fetch_cell_prepared')) {
		function db_fetch_cell_prepared($sql, $params = [], $col = '', $log = true, $db_conn = false) {
			return '';
		}
	}

	if (!function_exists('db_fetch_row_prepared')) {
		function db_fetch_row_prepared($sql, $params = [], $log = true, $db_conn = false) {
			return [];
		}
	}

	if (!function_exists('db_fetch_assoc_prepared')) {
		function db_fetch_assoc_prepared($sql, $params = [], $log = true, $db_conn = false) {
			return [];
		}
	}

	if (!function_exists('db_execute_prepared')) {
		function db_execute_prepared($sql, $params = [], $log = true, $db_conn = false) {
			return true;
		}
	}

	if (!function_exists('db_table_exists')) {
		function db_table_exists($table, $log = true, $db_conn = false) {
			return true;
		}
	}

	if (!function_exists('read_config_option')) {
		function read_config_option($name, $force = false) {
			return '';
		}
	}
}

/* -----------------------------------------------------------------------
 * 5. Load the Composer autoloader for Pest and dependencies.
 * ----------------------------------------------------------------------- */
require_once $cactiBase . '/include/vendor/autoload.php';
