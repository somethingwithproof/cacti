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
 * Loads the real lib/html*.php sources against lightweight boundary stubs so
 * their pure and render-only helpers can be exercised without a database,
 * session store, RRDtool, or a rendered page context. The stubs mirror the
 * behaviour the helpers actually depend on: get_current_page() reads
 * $_SERVER['SCRIPT_NAME'] exactly like lib/functions.php, and sanitize_sql_column()
 * applies the same identifier allowlist. Test classes that use this harness run
 * in isolated processes so these definitions never race the real lib/functions.php
 * loaded by neighbouring suites.
 */

$root = dirname(__DIR__, 2);

if (!defined('CACTI_PATH_URL'))      define('CACTI_PATH_URL', '/cacti/');
if (!defined('CACTI_PATH_INCLUDE'))  define('CACTI_PATH_INCLUDE', $root . '/include');
if (!defined('CACTI_CONNECTION'))    define('CACTI_CONNECTION', 'online');
if (!defined('POLLER_ID'))           define('POLLER_ID', 1);
if (!defined('CACTI_WEB'))           define('CACTI_WEB', true);
if (!defined('CACTI_SERVER_OS'))     define('CACTI_SERVER_OS', 'unix');

// SESS_FIELD_VALUES, SESS_ERROR_FIELDS, GRAPH_ITEM_TYPE_* and the rest come from here.
require_once $root . '/include/global_constants.php';

// Load the real database layer up front so its unguarded get_mysql_info() (and
// the db_* API the integration suite exercises) always win. Loading it here,
// before the stubs below, keeps the guarded stub from ever racing it regardless
// of test collection order.
require_once $root . '/lib/database.php';

if (!function_exists('get_current_page')) {
	function get_current_page(bool $basename = true) : mixed {
		$name = $_SERVER['SCRIPT_NAME'] ?? '';
		return $basename ? basename($name) : $name;
	}
}
if (!function_exists('get_current_script_name')) {
	function get_current_script_name() : string {
		$page = get_current_page();
		return is_string($page) ? $page : '';
	}
}
if (!function_exists('read_config_option'))  { function read_config_option($name, $force = false) { return ''; } }
if (!function_exists('read_user_setting'))   { function read_user_setting($name, $default = false, $force = false, $user = 0) { return $default !== false ? $default : false; } }
if (!function_exists('is_urlencoded'))       { function is_urlencoded(string $s) : bool { return $s != urldecode($s); } }
if (!function_exists('sanitize_uri'))        { function sanitize_uri(string $uri) : string { return preg_replace('/[\r\n]/', '', $uri) ?? ''; } }
if (!function_exists('CactiErrorHandler'))   { function CactiErrorHandler($l = 0, $m = '', $f = '', $ln = 0, $c = []) { return true; } }
if (!function_exists('sanitize_sql_column')) { function sanitize_sql_column(string $column, string $default = 'id') : string { $c = preg_replace('/[^a-zA-Z0-9_().]/', '', $column) ?? ''; return $c === '' ? ($default === '' ? '' : $default) : $c; } }
if (!function_exists('cacti_version_compare')) { function cacti_version_compare($a, $b, $op = '>') { return version_compare($a, $b, $op); } }
if (!function_exists('array_rekey')) {
	function array_rekey($array, $key, $value) {
		$ret = [];
		if (is_array($array)) {
			foreach ($array as $row) {
				if (is_array($value)) {
					$new = [];
					foreach ($value as $v) { $new[$v] = $row[$v] ?? null; }
					$ret[$row[$key]] = $new;
				} else {
					$ret[$row[$key]] = $row[$value] ?? null;
				}
			}
		}
		return $ret;
	}
}
if (!function_exists('is_realm_allowed'))    { function is_realm_allowed($realm) { return true; } }
if (!function_exists('api_plugin_hook_function')) { function api_plugin_hook_function($name, $parm = null) { return $parm; } }
if (!function_exists('api_plugin_hook'))     { function api_plugin_hook($name, $args = []) {} }
if (!function_exists('api_user_realm_auth')) { function api_user_realm_auth($f = '') { return true; } }
if (!function_exists('get_selected_theme'))  { function get_selected_theme() { return 'modern'; } }
if (!function_exists('get_theme_paths'))     { function get_theme_paths($fmt, $p) { return $p; } }
if (!function_exists('is_view_allowed'))     { function is_view_allowed($v) { return true; } }
if (!function_exists('is_cacti_release'))    { function is_cacti_release() { return true; } }
if (!function_exists('set_user_setting'))    { function set_user_setting($n, $v) {} }
if (!function_exists('get_rrdtool_version')) { function get_rrdtool_version() { return '1.7.2'; } }
if (!function_exists('get_auth_realms'))     { function get_auth_realms($login = false) { return []; } }
if (!function_exists('clean_up_name'))       { function clean_up_name($string) { return preg_replace('/[^a-zA-Z0-9_]/', '_', (string) $string); } }
if (!function_exists('html_log_input_error')) { function html_log_input_error($name) {} }
if (!function_exists('__'))                  { function __($t, ...$a) { return $a ? vsprintf($t, $a) : $t; } }
if (!function_exists('__x'))                  { function __x($ctx, $t, ...$a) { return $a ? vsprintf($t, $a) : $t; } }
if (!function_exists('__esc'))               { function __esc($t, ...$a) { return htmlspecialchars($a ? vsprintf($t, $a) : $t); } }
if (!function_exists('cacti_sizeof'))        { function cacti_sizeof($a) { return (is_array($a) || $a instanceof Countable) ? count($a) : 0; } }
if (!function_exists('cacti_count'))         { function cacti_count($a) { return cacti_sizeof($a); } }
if (!function_exists('cacti_log'))           { function cacti_log($m, $p = false, $t = 'GENERAL', $l = 1) {} }
if (!function_exists('clean_up_lines'))      { function clean_up_lines($s) { return trim(str_replace(["\n", "\r"], ' ', (string) $s)); } }
if (!function_exists('raise_message'))       { function raise_message($id, $m = '', $lvl = 0, $ti = null) {} }
if (!function_exists('cacti_strtolower'))    { function cacti_strtolower($s) { return strtolower((string) $s); } }
if (!function_exists('cacti_strtoupper'))    { function cacti_strtoupper($s) { return strtoupper((string) $s); } }

require_once $root . '/lib/html_utility.php';
require_once $root . '/lib/html.php';
require_once $root . '/lib/html_filter.php';
require_once $root . '/lib/html_form.php';

/**
 * Open a default connection to the local cacti database so real
 * get_mysql_info()/db_* calls resolve against a server, and return whether one
 * is now available. Scoped by the caller (setUpBeforeClass/tearDownAfterClass)
 * so the routing globals never leak into neighbouring suites. A plain PDO avoids
 * db_connect_real()'s schema-marker validation and its log noise.
 */
function libhtml_connect_default() : bool {
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

/**
 * Drop the default connection opened by libhtml_connect_default() so it does not
 * leak past the test class that requested it.
 */
function libhtml_disconnect_default() : void {
	global $database_sessions, $database_hostname, $database_port, $database_default;

	unset($database_sessions['127.0.0.1:3306:cacti']);
	$database_hostname = '';
	$database_port     = 0;
	$database_default  = '';
}

/**
 * Reset request/session state between cases and point get_current_page() at a
 * known script.
 */
function libhtml_reset(string $script = 'graph_view.php', array $request = [], array $session = []) : void {
	$_SERVER['SCRIPT_NAME'] = '/cacti/' . $script;
	$_SERVER['REQUEST_URI'] = '/cacti/' . $script;
	$_REQUEST               = $request;
	$_SESSION               = $session;
	$GLOBALS['_CACTI_REQUEST'] = [];

	// globals the CactiTableFilter constructor copies into typed array props
	$GLOBALS['item_rows']       = $GLOBALS['item_rows']       ?? [];
	$GLOBALS['graph_timespans'] = $GLOBALS['graph_timespans'] ?? [];
	$GLOBALS['graph_timeshifts'] = $GLOBALS['graph_timeshifts'] ?? [];
}

/**
 * Grant the given realm ids to a fake authenticated session so realm-gated
 * render branches execute. Works against the harness stub (which ignores it and
 * always allows) and against the real lib/auth.php is_realm_allowed(): setting
 * cacti_db_version below 1.0.0 skips its permission-cache DB lookup, and a
 * pre-populated SESS_USER_REALMS map answers the check without a database.
 */
function libhtml_grant_realms(array $realm_ids) : void {
	$GLOBALS['config']['cacti_db_version'] = '0.0';
	$_SESSION[SESS_USER_ID] = 1;
	unset($_SESSION[SESS_USER_PERMS_KEY]);
	$_SESSION[SESS_USER_REALMS] = [];

	foreach ($realm_ids as $id) {
		$_SESSION[SESS_USER_REALMS][$id] = true;
	}
}
