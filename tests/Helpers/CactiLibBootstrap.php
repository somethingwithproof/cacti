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

// Lightweight runtime for tests that need real library functions but not the
// full include/global.php stack (which pulls in auth.php and the web session
// layer). It defines the path and environment constants the libraries expect
// and loads them directly. Without a database connection read_config_option()
// falls back to defaults, which is all these tests rely on.

$cacti_root = dirname(__DIR__, 2);

if (!defined('CACTI_PATH_BASE')) {
	define('CACTI_PATH_BASE', $cacti_root);
}

if (!defined('CACTI_PATH_INCLUDE')) {
	define('CACTI_PATH_INCLUDE', $cacti_root . '/include');
}

if (!defined('CACTI_PATH_LIBRARY')) {
	define('CACTI_PATH_LIBRARY', $cacti_root . '/lib');
}

if (!defined('CACTI_PATH_SCRIPTS')) {
	define('CACTI_PATH_SCRIPTS', $cacti_root . '/scripts');
}

if (!defined('CACTI_PATH_LOG')) {
	define('CACTI_PATH_LOG', sys_get_temp_dir());
}

if (!defined('CACTI_WEB')) {
	define('CACTI_WEB', false);
}

if (!defined('CACTI_SERVER_OS')) {
	define('CACTI_SERVER_OS', str_starts_with(PHP_OS, 'WIN') ? 'win32' : 'unix');
}

require_once $cacti_root . '/include/global_constants.php';
require_once $cacti_root . '/lib/functions.php';
require_once $cacti_root . '/lib/database.php';

// Keep file logging quiet: with no database the default log path may be
// unwritable, and these tests do not assert on log output.
$GLOBALS['config'][OPTIONS_CLI]['log_verbosity'] = POLLER_VERBOSITY_NONE;

// Options read during path substitution. Seeding them keeps read_config_option()
// from returning null (a PHP 8.4 deprecation in str_replace) when no database
// backs the settings table.
$GLOBALS['config'][OPTIONS_CLI]['path_php_binary'] = '';
