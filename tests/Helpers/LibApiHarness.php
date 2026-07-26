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
 * Loads the real lib/api_*.php sources against lightweight boundary stubs so the
 * pure helpers touched by the PHP 8.4 idiom migration can run without a
 * database, SNMP, RRDtool, or a rendered page context.
 */

$root = dirname(__DIR__, 2);

if (!defined('POLLER_ID')) define('POLLER_ID', 1);

require_once $root . '/include/global_constants.php';

if (!function_exists('cacti_log'))                 { function cacti_log($m, $p = false, $t = 'GENERAL', $l = 1) {} }
if (!function_exists('clean_up_lines'))            { function clean_up_lines($s) { return trim(str_replace(["\n", "\r"], ' ', (string) $s)); } }
if (!function_exists('cacti_sizeof'))              { function cacti_sizeof($a) { return (is_array($a) || $a instanceof Countable) ? count($a) : 0; } }
if (!function_exists('cacti_count'))               { function cacti_count($a) { return cacti_sizeof($a); } }
if (!function_exists('input_validate_input_number')) { function input_validate_input_number($value, $name = '') { return true; } }
if (!function_exists('array_rekey')) {
	function array_rekey($array, $key, $value) {
		$ret = [];
		if (is_array($array)) {
			foreach ($array as $row) { $ret[$row[$key]] = is_array($value) ? $row : ($row[$value] ?? null); }
		}
		return $ret;
	}
}

require_once $root . '/lib/api_aggregate.php';
require_once $root . '/lib/api_automation.php';
require_once $root . '/lib/api_data_source.php';
require_once $root . '/lib/api_device.php';
require_once $root . '/lib/api_graph.php';

/**
 * Open a scoped default connection to the local cacti database for the
 * read-only DB-boundary cases. A plain PDO avoids db_connect_real()'s
 * schema-marker validation. Returns whether a connection is available.
 */
function libapi_connect_default() : bool {
	global $database_sessions, $database_hostname, $database_port, $database_default, $config;

	if (!isset($config['cacti_db_version'])) {
		$config['cacti_db_version'] = '1.3.0';
	}

	$key = '127.0.0.1:3306:cacti';

	if (!isset($database_sessions[$key])) {
		try {
			$conn = new PDO('mysql:host=127.0.0.1;port=3306;dbname=cacti;charset=utf8', 'cacti', 'cacti');
			$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT);
		} catch (\Throwable $e) {
			return false;
		}

		$database_sessions[$key] = $conn;
	}

	$database_hostname = '127.0.0.1';
	$database_port     = 3306;
	$database_default  = 'cacti';

	return true;
}

function libapi_disconnect_default() : void {
	global $database_sessions, $database_hostname, $database_port, $database_default;

	unset($database_sessions['127.0.0.1:3306:cacti']);
	$database_hostname = '';
	$database_port     = 0;
	$database_default  = '';
}
