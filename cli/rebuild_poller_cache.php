#!/usr/bin/env php
<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

if (function_exists('pcntl_async_signals')) {
	pcntl_async_signals(true);
} else {
	declare(ticks = 100);
<<<<<<< HEAD
}

ini_set('output_buffering', 'Off');

require(__DIR__ . '/../include/cli_check.php');

require_once($config['base_path'] . '/lib/utility.php');
require_once($config['base_path'] . '/lib/api_data_source.php');
require_once($config['base_path'] . '/lib/poller.php');

/* switch to main database for cli's */
if ($config['poller_id'] > 1) {
	db_switch_remote_to_main();
||||||| 7dd05ee12
require(__DIR__ . '/../include/cli_check.php');
require_once($config['base_path'] . '/lib/poller.php');
require_once($config['base_path'] . '/lib/utility.php');

if ($config['poller_id'] > 1) {
	print "FATAL: This utility is designed for the main Data Collector only" . PHP_EOL;
	exit(1);
=======
>>>>>>> origin/fix/jquery-deprecations
}

ini_set('output_buffering', 'Off');

require(__DIR__ . '/../include/cli_check.php');

require_once(CACTI_PATH_LIBRARY . '/utility.php');
require_once(CACTI_PATH_LIBRARY . '/api_data_source.php');
require_once(CACTI_PATH_LIBRARY . '/poller.php');

// switch to main database for cli's
if (POLLER_ID > 1) {
	db_switch_remote_to_main();
}

// process calling arguments
$parms = $_SERVER['argv'];
array_shift($parms);

<<<<<<< HEAD
/* system controlled parameters */
$type              = 'rmaster';
$thread_id         = 0;
||||||| 7dd05ee12
$debug = false;
$host_id = 0;
$host_template_id = 0;
=======
// system controlled parameters
$type              = 'rmaster';
$thread_id         = 0;

// mandatory parameters
$start_time        = false;
$end_time          = false;

// optional parameters for host selection
$debug            = false;
$host_id          = 0;
$host_template_id = 0;
$data_template_id = 0;
>>>>>>> origin/fix/jquery-deprecations

<<<<<<< HEAD
/* mandatory parameters */
$start_time        = false;
$end_time          = false;
||||||| 7dd05ee12
if (cacti_sizeof($parms)) {
	foreach($parms as $parameter) {
		if (strpos($parameter, '=')) {
			list($arg, $value) = explode('=', $parameter);
		} else {
			$arg = $parameter;
			$value = '';
		}
=======
// optional for threading and verbose display
$threads           = detect_cpu_cores();
>>>>>>> origin/fix/jquery-deprecations

<<<<<<< HEAD
/* optional parameters for host selection */
$debug            = false;
$host_id          = false;
$host_template_id = false;
$data_template_id = false;
||||||| 7dd05ee12
		switch ($arg) {
			case '-d':
			case '--debug':
				$debug = true;
				break;
			case '--host-id':
				$host_id = trim($value);
=======
if ($threads == 0) {
	$threads = 2;
}
>>>>>>> origin/fix/jquery-deprecations

<<<<<<< HEAD
/* optional for threading and verbose display */
$threads           = 5;
||||||| 7dd05ee12
				if (!is_numeric($host_id)) {
					print 'ERROR: You must supply a valid device id to run this script!' . PHP_EOL;
					exit(1);
				}
=======
// optional for force handing and resume
$forcerun = false;
>>>>>>> origin/fix/jquery-deprecations

<<<<<<< HEAD
/* optional for force handing and resume */
$forcerun          = false;
||||||| 7dd05ee12
				break;
			case '--host-template-id':
				$host_template_id = trim($value);
=======
foreach ($parms as $parameter) {
	if (str_contains($parameter, '=')) {
		[$arg, $value] = explode('=', $parameter, 2);
	} else {
		$arg   = $parameter;
		$value = '';
	}
>>>>>>> origin/fix/jquery-deprecations

<<<<<<< HEAD
foreach ($parms as $parameter) {
	if (strpos($parameter, '=')) {
		list($arg, $value) = explode('=', $parameter, 2);
	} else {
		$arg   = $parameter;
		$value = '';
	}

	switch ($arg) {
		case '--host-id':
			$host_id = trim($value);

			if (!is_numeric($host_id)) {
||||||| 7dd05ee12
				if (!is_numeric($host_id)) {
					print 'ERROR: You must supply a valid device template id to run this script!' . PHP_EOL;
					exit(1);
				}
=======
	switch ($arg) {
		case '--host-id':
			$host_id = intval($value);

			if ($host_id <= 0) {
>>>>>>> origin/fix/jquery-deprecations
				print 'ERROR: You must supply a valid Device Id to run this script!' . PHP_EOL;

				exit(1);
			}

			break;
		case '--host-template-id':
<<<<<<< HEAD
			$host_template_id = trim($value);

			if (!is_numeric($host_template_id)) {
				print 'ERROR: You must supply a valid Device Template Id to run this script!' . PHP_EOL;

				exit(1);
			}

			break;
		case '--data-template-id':
			$data_template_id = trim($value);

			if (!is_numeric($data_template_id)) {
				print 'ERROR: You must supply a valid Data Template Id to run this script!' . PHP_EOL;

				exit(1);
			}

			break;
		case '--type':
			$type = $value;

			break;
		case '--threads':
			if (!is_numeric(trim($value))) {
				print 'ERROR: You must supply a valid Number of Treads or skip this parameter for default value (' . $threads . ')' . PHP_EOL;
				exit(1);
			}

			$threads = $value;

			break;
		case '--child':
			$thread_id = $value;

			break;
		case '--force':
			$forcerun = true;

			break;
		case '-d':
		case '--debug':
			$debug = true;

			break;
		case '-h':
		case '-H':
		case '--help':
			display_help();

			exit;
		case '-v':
		case '-V':
		case '--version':
			display_version();

			exit;
||||||| 7dd05ee12
		}
=======
			$host_template_id = intval($value);

			if ($host_template_id <= 0) {
				print 'ERROR: You must supply a valid Device Template Id to run this script!' . PHP_EOL;

				exit(1);
			}

			break;
		case '--data-template-id':
			$data_template_id = intval($value);

			if ($data_template_id <= 0) {
				print 'ERROR: You must supply a valid Data Template Id to run this script!' . PHP_EOL;

				exit(1);
			}

			break;
		case '--type':
			$type = $value;

			break;
		case '--threads':
			if (!is_numeric(trim($value))) {
				print 'ERROR: You must supply a valid Number of Treads or skip this parameter for default value (' . $threads . ')' . PHP_EOL;

				exit(1);
			}

			$threads = $value;

			break;
		case '--child':
			$thread_id = $value;

			break;
		case '--force':
			$forcerun = true;

			break;
		case '-d':
		case '--debug':
			$debug = true;

			break;
		case '-h':
		case '-H':
		case '--help':
			display_help();

			exit;
		case '-v':
		case '-V':
		case '--version':
			display_version();

			exit;

>>>>>>> origin/fix/jquery-deprecations
		default:
			print 'ERROR: Invalid Parameter ' . $parameter . PHP_EOL . PHP_EOL;

			display_help();

			exit;
	}
}

<<<<<<< HEAD
/* install signal handlers for UNIX only */
if (function_exists('pcntl_signal')) {
	pcntl_signal(SIGTERM, 'sig_handler');
	pcntl_signal(SIGINT, 'sig_handler');
}

/* take time and log performance data */
$start = microtime(true);

/* set new timeout and memory settings */
ini_set('max_execution_time', '0');
ini_set('memory_limit', '-1');

$sql_where = '';
$params    = array();
||||||| 7dd05ee12
/* obtain timeout settings */
$max_execution = ini_get('max_execution_time');

/* set new timeout */
ini_set('max_execution_time', '0');

$sql_where = '';
$params    = array();
=======
/**
 * allow multiple runs for each type. This is mainly important for
 * the Data Template options to change the Profile Id of a data
 * source or data template.  These processes to repopulate the
 * poller cache are done in succession in background.
 */
$rp_type = '';
>>>>>>> origin/fix/jquery-deprecations

if ($host_id > 0) {
<<<<<<< HEAD
	$sql_where = ' AND h.id = ?';
	$params[]  = $host_id;
||||||| 7dd05ee12
	$sql_where = 'WHERE dl.host_id = ?';
	$params[] = $host_id;
=======
	$rp_type .= ($rp_type != '' ? ',' : ':') . "hi:$host_id";
>>>>>>> origin/fix/jquery-deprecations
}

if ($host_template_id > 0) {
<<<<<<< HEAD
	$sql_where .= ' AND h.host_template_id = ?';
	$params[] = $host_template_id;
||||||| 7dd05ee12
	$sql_where .= ($sql_where != '' ? ' AND ':'WHERE ') . ' h.host_template_id = ?';
	$params[] = $host_template_id;
=======
	$rp_type .= ($rp_type != '' ? ',' : ':') . "ht:$host_template_id";
>>>>>>> origin/fix/jquery-deprecations
}

<<<<<<< HEAD
/* issue warnings and start message if applicable */
print 'WARNING: Do not interrupt this script.  Rebuilding Poller Cache can take quite some time' . PHP_EOL;
||||||| 7dd05ee12
/* get the data_local Id's for the poller cache */
$poller_data  = db_fetch_assoc_prepared("SELECT dl.*
	FROM data_local AS dl
	INNER JOIN host AS h
	ON dl.host_id = h.id
	$sql_where",
	$params);

/* initialize some variables */
$current_ds = 1;
$total_ds = cacti_sizeof($poller_data);

/* setting local_data_ids to an empty array saves time during updates */
$local_data_ids = array();
$poller_items   = array();

/* issue warnings and start message if applicable */
print 'WARNING: Do not interrupt this script.  Rebuilding the Poller Cache can take quite some time' . PHP_EOL;
debug("There are '" . cacti_sizeof($poller_data) . "' data source elements to update.");
=======
if ($data_template_id > 0) {
	$rp_type .= ($rp_type != '' ? ',' : ':') . "dt:$data_template_id";
}

// install signal handlers for UNIX only
if (function_exists('pcntl_signal')) {
	pcntl_signal(SIGTERM, 'sig_handler');
	pcntl_signal(SIGINT, 'sig_handler');
}

// take time and log performance data
$start = microtime(true);

// set new timeout and memory settings
ini_set('max_execution_time', '0');
ini_set('memory_limit', '-1');
>>>>>>> origin/fix/jquery-deprecations

<<<<<<< HEAD
/* send a gentle message to the log and stdout */
pushout_debug('Rebuild poller cache starting');
||||||| 7dd05ee12
/* start rebuilding the poller cache */
if (cacti_sizeof($poller_data)) {
	foreach ($poller_data as $data) {
		if (!$debug) print '.';
		$local_data_ids[] = $data['id'];
		$poller_items = array_merge($poller_items, update_poller_cache($data));
=======
// issue warnings and start message if applicable
print 'WARNING: Do not interrupt this script.  Rebuilding Poller Cache can take quite some time' . PHP_EOL;
>>>>>>> origin/fix/jquery-deprecations

<<<<<<< HEAD
/* silently end if the registered process is still running  */
if (!$forcerun) {
	if (!register_process_start('pushout', $type, $thread_id, 86400)) {
||||||| 7dd05ee12
		debug("Data Source Item '$current_ds' of '$total_ds' updated");
		$current_ds++;
	}

	if (cacti_sizeof($local_data_ids)) {
		poller_update_poller_cache_from_buffer($local_data_ids, $poller_items);
=======
// send a gentle message to the log and stdout
pushout_debug('Rebuild poller cache starting');

// silently end if the registered process is still running
if (!$forcerun) {
	if (!register_process_start('pushout' . $rp_type, $type, $thread_id, 86400)) {
>>>>>>> origin/fix/jquery-deprecations
		exit(0);
	}
}

<<<<<<< HEAD
/* Collect data as determined by the type */
switch ($type) {
	case 'rmaster':
		pushout_master_handler($forcerun, $host_id, $host_template_id, $data_template_id, $threads);

		unregister_process('pushout', 'rmaster', 0);

		break;
	case 'child':  /* Launched by the rmaster process */
		$child_start = microtime(true);

		$sql_where  = '';
		$sql_params = array();

		if ($host_id !== false) {
			$sql_where .= 'AND id = ?';
			$sql_params[] = $host_id;
		}

		if ($host_template_id !== false) {
			$sql_where .= 'AND host_template_id = ?';
			$sql_params[] = $host_template_id;
		}

		$rows = db_fetch_cell_prepared("SELECT count(id) FROM host WHERE disabled='' " . $sql_where, $sql_params);

		$hosts_per_process = ceil($rows/$threads);

		$sql_where .= ' GROUP BY h.id ORDER BY h.id LIMIT ' . (($thread_id-1)*$hosts_per_process) . ',' . $hosts_per_process;

		$rows = db_fetch_assoc_prepared("SELECT h.id AS id, COUNT(dl.id) AS dl_count
			FROM host AS h
			LEFT JOIN data_local AS dl
			ON h.id=dl.host_id
			WHERE h.disabled='' " . $sql_where,
			$sql_params);

		cacti_log(sprintf('Child Started Process %s with %d hosts, from: %d', $thread_id, $hosts_per_process, ($thread_id-1)*$hosts_per_process), true, 'PUSHOUT');

		foreach ($rows as $row) {
			if (!$debug) {
				print '.';
			}

			if ($row['dl_count'] > 0) {
				push_out_host($row['id'], 0, $data_template_id);
			} else {
				db_execute_prepared('DELETE FROM poller_item WHERE host_id = ?', array($row['id']));
			}
		}

		$total_time = microtime(true) - $child_start;

		unregister_process('pushout', 'child', $thread_id);
||||||| 7dd05ee12
if (!$debug) {
	print PHP_EOL;
=======
// Collect data as determined by the type
switch ($type) {
	case 'rmaster':
		pushout_master_handler($forcerun, $host_id, $host_template_id, $data_template_id, $threads);

		unregister_process('pushout' . $rp_type, 'rmaster', 0);

		break;
	case 'child':  // Launched by the rmaster process
		$child_start = microtime(true);

		$sql_where  = '';
		$sql_params = [];

		if ($host_id > 0) {
			$sql_where .= 'AND id = ?';
			$sql_params[] = $host_id;
		}

		if ($host_template_id > 0) {
			$sql_where .= 'AND host_template_id = ?';
			$sql_params[] = $host_template_id;
		}

		$rows = db_fetch_cell_prepared("SELECT COUNT(id) FROM host WHERE disabled = '' " . $sql_where, $sql_params);

		$hosts_per_process = ceil($rows / $threads);

		$sql_where .= ' GROUP BY h.id ORDER BY h.id LIMIT ' . (($thread_id - 1) * $hosts_per_process) . ',' . $hosts_per_process;

		$rows = db_fetch_assoc_prepared("SELECT h.id AS id, COUNT(dl.id) AS dl_count
			FROM host AS h
			LEFT JOIN data_local AS dl
			ON h.id=dl.host_id
			WHERE h.disabled='' " . $sql_where,
			$sql_params);

		cacti_log(sprintf('Child Started Process %s with %d hosts, from: %d', $thread_id, $hosts_per_process, ($thread_id - 1) * $hosts_per_process), true, 'PUSHOUT');

		foreach ($rows as $row) {
			if (!$debug) {
				print '.';
			}

			if ($row['dl_count'] > 0) {
				push_out_host($row['id'], 0, $data_template_id);
			} else {
				db_execute_prepared('DELETE FROM poller_item WHERE host_id = ?', [$row['id']]);
			}
		}

		$total_time = microtime(true) - $child_start;

		unregister_process('pushout' . $rp_type, 'child', $thread_id);
>>>>>>> origin/fix/jquery-deprecations

		break;
}

pushout_debug('Polling Ending');

exit(0);

<<<<<<< HEAD

function pushout_master_handler($forcerun, $host_id, $host_template_id, $data_template_id, $threads) {
	global $type;

	$sql_where  = '';
	$sql_params = array();

	if ($host_id !== false) {
		$sql_where .= 'AND id = ?';
		$sql_params[] = $host_id;
	}

	if ($host_template_id !== false) {
		$sql_where .= 'AND host_template_id = ?';
		$sql_params[] = $host_template_id;
	}

	$rows = db_fetch_cell_prepared("SELECT COUNT(id)
		FROM host
		WHERE disabled = '' " . $sql_where, $sql_params);

	if ($rows == 0) {
		print 'WARNING: There are no hosts to process' . PHP_EOL;;

		return false;
	}

	$hosts_per_process = ceil($rows/$threads);

	print "There are $rows hosts, $threads threads and $hosts_per_process hosts to process per thread" . PHP_EOL;

	$h_done = 0;

	for ($thread_id = 1; $h_done < $rows; $thread_id++) {
		pushout_debug("Launching Process ID $thread_id");

		pushout_launch_child($thread_id, $threads);

		$h_done += $hosts_per_process;
	}

	$starting = true;

	while (true) {
		if ($starting) {

||||||| 7dd05ee12
/*  display_version - displays version information */
function display_version() {
	$version = get_cacti_cli_version();
	print "Cacti Rebuild Poller Cache Utility, Version $version, " . COPYRIGHT_YEARS . PHP_EOL;
=======
function pushout_master_handler(bool $forcerun, int $host_id, int $host_template_id, int $data_template_id, int $threads) : bool {
	global $type;

	$sql_where  = '';
	$sql_params = [];

	if ($host_id > 0) {
		$sql_where .= 'AND id = ?';
		$sql_params[] = $host_id;
	}

	if ($host_template_id > 0) {
		$sql_where .= 'AND host_template_id = ?';
		$sql_params[] = $host_template_id;
	}

	$rows = db_fetch_cell_prepared("SELECT COUNT(id)
		FROM host
		WHERE disabled = '' " . $sql_where, $sql_params);

	if ($rows == 0) {
		print 'WARNING: There are no hosts to process' . PHP_EOL;

		return false;
	}

	$hosts_per_process = ceil($rows / $threads);

	print "There are $rows hosts, $threads threads and $hosts_per_process hosts to process per thread" . PHP_EOL;

	$h_done = 0;

	for ($thread_id = 1; $h_done < $rows; $thread_id++) {
		pushout_debug("Launching Process ID $thread_id");

		pushout_launch_child($thread_id, $threads);

		$h_done += $hosts_per_process;
	}

	$starting = true;

	while (true) {
		if ($starting) {
>>>>>>> origin/fix/jquery-deprecations
			sleep(5);
			$starting = false;
		}

		$running = pushout_processes_running();

		if ($running > 0) {
			pushout_debug(sprintf('%s Processes Running, keeping for 2 seconds.', $running));
			sleep(2);
		} else {
			break;
		}
	}

	return true;
}

/**
 * pushout_launch_child - this function will launch collector children based upon
<<<<<<< HEAD
 *   the maximum number of threads and the process type
 *
 * @param $thread_id  (int)    The Thread id to launch
 *
 * @return - NULL
 */
function pushout_launch_child($thread_id, $threads) {
||||||| 7dd05ee12
/*	display_help - displays the usage of the function */
function display_help () {
	display_version();
=======
 * the maximum number of threads and the process type
 *
 * @param int $thread_id The Thread id to launch with
 * @param int $threads   The number of threads to run with
 *
 * @return void
 */
function pushout_launch_child(int $thread_id, int $threads) : void {
>>>>>>> origin/fix/jquery-deprecations
	global $config, $debug, $host_template_id, $data_template_id;

	$php_binary = read_config_option('path_php_binary');

	pushout_debug(sprintf('Launching Rebuild poller cache Process Number %s for Type %s', $thread_id, 'child'));

<<<<<<< HEAD
	cacti_log(sprintf('NOTE: Launching Push out hosts Number %s for Type %s', $thread_id, 'child'), true, 'PUSHOUT', POLLER_VERBOSITY_MEDIUM);

	exec_background($php_binary, $config['base_path'] . "/cli/push_out_hosts.php --type=child --threads=$threads --child=$thread_id " . ($debug ? " --debug":"") . ($host_template_id ? " --host-template-id=$host_template_id":"") . ($data_template_id ? " --data-template-id=$data_template_id":""));
||||||| 7dd05ee12
	print 'Optional:' . PHP_EOL;
	print '    --host-id=ID          - Limit the repopulation to a single Device' . PHP_EOL;
	print '    --host-template-id=ID - Limit the repopulation to a single Device Template' . PHP_EOL;
	print '    --debug               - Display verbose output during execution' . PHP_EOL . PHP_EOL;
=======
	cacti_log(sprintf('NOTE: Launching Rebuild poller cache Number %s for Type %s', $thread_id, 'child'), true, 'PUSHOUT', POLLER_VERBOSITY_MEDIUM);

	exec_background($php_binary, CACTI_PATH_CLI . "/rebuild_poller_cache.php --type=child --threads=$threads --child=$thread_id " . ($debug ? ' --debug' : '') . ($host_template_id ? " --host-template-id=$host_template_id" : '') . ($data_template_id ? " --data-template-id=$data_template_id" : ''));
>>>>>>> origin/fix/jquery-deprecations
}

/**
 * pushout_processes_running - given a type, determine the number
<<<<<<< HEAD
 *   of sub-type or children that are currently running
 *
 * @return - (int) The number of running processes
 */
function pushout_processes_running() {
	$running = db_fetch_cell('SELECT COUNT(*)
		FROM processes
		WHERE tasktype = "pushout"
		AND taskname = "child"');

	if ($running == 0) {
		return 0;
	}

	return $running;
}

/**
 * pushout_debug - this simple routine prints a standard message to the console
 *   when running in debug mode.
 *
 * @param $message - (string) The message to display
 *
 * @return - NULL
 */
function pushout_debug($message) {
||||||| 7dd05ee12
function debug($message) {
=======
 * of sub-type or children that are currently running
 *
 * @return int - The number of running processes
 */
function pushout_processes_running() : int {
	$running = db_fetch_cell('SELECT COUNT(*)
		FROM processes
		WHERE tasktype = "pushout"
		AND taskname = "child"');

	return intval($running);
}

/**
 * pushout_debug - this simple routine prints a standard message to the console
 * when running in debug mode.
 *
 * @param string $message The message to display
 *
 * @return void
 */
function pushout_debug(string $message) : void {
>>>>>>> origin/fix/jquery-deprecations
	global $debug;

	if ($debug) {
<<<<<<< HEAD
		print 'PUSHOUT: ' . $message . PHP_EOL;
||||||| 7dd05ee12
		print 'DEBUG: ' . trim($message) . "\n";
=======
		print 'PUSHOUT: ' . trim($message) . PHP_EOL;
>>>>>>> origin/fix/jquery-deprecations
	}
}

/**
<<<<<<< HEAD
 * display_version - displays version information
 */
function display_version() {
	print 'Cacti Rebuild poller cache Tool, Version ' . CACTI_VERSION . ' ' . COPYRIGHT_YEARS . PHP_EOL;
}

/**
 * display_help - generic help screen for utilities
 */
function display_help() {
	display_version();

	print PHP_EOL . 'usage: rebuild_poller_cache.php [--host-id=N] [--host-template-id=N] [--data-template-id=N] [--debug]' . PHP_EOL . PHP_EOL;

	print 'Cacti\'s repopulate poller cache tool.  This CLI script will ' . PHP_EOL;
	print 'repopulate poller cache for all or specified hosts.' . PHP_EOL . PHP_EOL;
	print 'This utility will run in parallel with the given number of threads,' . PHP_EOL;

	print 'Optional:' . PHP_EOL;
	print ' --threads=N           - The number of threads to use to repopulate, default = 5' . PHP_EOL;
	print ' --host-id=N           - Run for a specific Device' . PHP_EOL;
	print ' --host-template-id=N  - Run for a specific Device Template' . PHP_EOL;
	print ' --data-template-id=N  - Run for a specific Data Template' . PHP_EOL;
	print ' --debug               - Display verbose output during execution' . PHP_EOL . PHP_EOL;

	print 'System Controlled:' . PHP_EOL;
	print '    --type      - The type and subtype of the rebuild poller cache process' . PHP_EOL;
	print '    --child     - The thread id of the child process' . PHP_EOL . PHP_EOL;
}

/**
 * sig_handler - provides a generic means to catch exceptions to the Cacti log.
 *
 * @param $signo - (int) the signal that was thrown by the interface.
 *
 * @return - null
 */
function sig_handler($signo) {
	global $type, $thread_id;

	switch ($signo) {
		case SIGTERM:
		case SIGINT:
			cacti_log('WARNING: Rebuild poller cache terminated by user', false, 'PUSHOUT');

			if (strpos($type, 'rmaster') !== false) {
				pushout_kill_running_processes();
			}

			unregister_process('pushout', 'rmaster', $thread_id, getmypid());

			exit(1);

			break;

		default:
			/* ignore all other signals */
	}
}

/**
 * pushout_kill_running_processes - this function is part of an interrupt
 *   handler to kill children processes when the parent is killed
 *
 * @return - NULL
 */
function pushout_kill_running_processes() {
	global $type;

	$processes = db_fetch_assoc_prepared('SELECT *
		FROM processes
		WHERE tasktype = "pushout"
		AND taskname IN ("child")
		AND pid != ?',
		array(getmypid()));

	if (cacti_sizeof($processes)) {
		foreach ($processes as $p) {
			cacti_log(sprintf('WARNING: Killing Cleanup %s PID %d due to another due to signal or overrun.', ucfirst($p['taskname']), $p['pid']), false, 'PUSHOUT');
			posix_kill($p['pid'], SIGTERM);

			unregister_process($p['tasktype'], $p['taskname'], $p['taskid'], $p['pid']);
		}
	}
}

||||||| 7dd05ee12
=======
 * sig_handler - provides a generic means to catch exceptions to the Cacti log.
 *
 * @param int $signo The signal that was thrown by the interface.
 *
 * @return void
 */
function sig_handler(int $signo) : void {
	global $type, $thread_id, $rp_type;

	switch ($signo) {
		case SIGTERM:
		case SIGINT:
			cacti_log('WARNING: Rebuild poller cache terminated by user', false, 'PUSHOUT');

			if (str_contains($type, 'rmaster')) {
				pushout_kill_running_processes();
			}

			unregister_process('pushout' . $rp_type, 'rmaster', $thread_id, getmypid());

			exit(1);
		default:
			// ignore all other signals
	}
}

/**
 * pushout_kill_running_processes - this function is part of an interrupt
 * handler to kill children processes when the parent is killed
 *
 * @return void
 */
function pushout_kill_running_processes() : void {
	global $type;

	$processes = db_fetch_assoc_prepared('SELECT *
		FROM processes
		WHERE tasktype = "pushout"
		AND taskname IN ("child")
		AND pid != ?',
		[getmypid()]);

	if (cacti_sizeof($processes)) {
		foreach ($processes as $p) {
			cacti_log(sprintf('WARNING: Killing Cleanup %s PID %d due to another due to signal or overrun.', ucfirst($p['taskname']), $p['pid']), false, 'PUSHOUT');
			posix_kill($p['pid'], SIGTERM);

			unregister_process($p['tasktype'], $p['taskname'], $p['taskid'], $p['pid']);
		}
	}
}

/**
 * display_version - displays version information
 *
 * @return void
 */
function display_version() : void {
	print 'Cacti Rebuild poller cache Tool, Version ' . CACTI_VERSION . ' ' . COPYRIGHT_YEARS . PHP_EOL;
}

/**
 * display_help - generic help screen for utilities
 *
 * @return void
 */
function display_help() : void {
	display_version();

	print PHP_EOL . 'usage: rebuild_poller_cache.php [--host-id=N] [--host-template-id=N] [--data-template-id=N] [--debug]' . PHP_EOL . PHP_EOL;

	print 'Cacti\'s repopulate poller cache tool.  This CLI script will ' . PHP_EOL;
	print 'repopulate poller cache for all or specified hosts.' . PHP_EOL . PHP_EOL;

	print 'This utility will run in parallel with the given number of threads.' . PHP_EOL;
	print 'If threads argument is not specified, value is derived from the number of processor cores.' . PHP_EOL;
	print 'In case of a detection problem, 2 threads are used.' . PHP_EOL . PHP_EOL;

	print 'Optional:' . PHP_EOL;
	print ' --threads=N           - The number of threads to use to repopulate' . PHP_EOL;
	print ' --host-id=N           - Run for a specific Device' . PHP_EOL;
	print ' --host-template-id=N  - Run for a specific Device Template' . PHP_EOL;
	print ' --data-template-id=N  - Run for a specific Data Template' . PHP_EOL;
	print ' --debug               - Display verbose output during execution' . PHP_EOL . PHP_EOL;

	print 'System Controlled:' . PHP_EOL;
	print ' --type                - The type and subtype of the rebuild poller cache process' . PHP_EOL;
	print ' --child               - The thread id of the child process' . PHP_EOL . PHP_EOL;
}
>>>>>>> origin/fix/jquery-deprecations
