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

require(__DIR__ . '/../include/cli_check.php');
require_once(CACTI_PATH_LIBRARY . '/api_automation_tools.php');
require_once(CACTI_PATH_LIBRARY . '/api_data_source.php');
require_once(CACTI_PATH_LIBRARY . '/api_device.php');
require_once(CACTI_PATH_LIBRARY . '/api_graph.php');
require_once(CACTI_PATH_LIBRARY . '/data_query.php');
require_once(CACTI_PATH_LIBRARY . '/snmp.php');
require_once(CACTI_PATH_LIBRARY . '/poller.php');
require_once(CACTI_PATH_LIBRARY . '/utility.php');

// switch to main database for cli's
if (POLLER_ID > 1) {
	db_switch_remote_to_main();
}

// process calling arguments
$parms = $_SERVER['argv'];
array_shift($parms);

if (cacti_sizeof($parms)) {
<<<<<<< HEAD
	/* setup defaults */
	$description = '';
	$ip          = '';
	$id          = '';
	$ids_id      = array();
	$host_id     = '';
||||||| 7dd05ee12

	/* setup defaults */
	$description   = '';
	$ip            = '';
	$host_id       = '';
=======
	// setup defaults
	$description = '';
	$ip          = '';
	$host_id     = '';

	$ids_id      = [];
	$quietMode   = false;
	$confirm     = false;
	$debug       = false;
>>>>>>> origin/fix/jquery-deprecations

<<<<<<< HEAD
	$quietMode   = false;
	$confirm     = false;
	$quiet       = false;
	$debug       = false;

	foreach($parms as $parameter) {
		if (strpos($parameter, '=')) {
			list($arg, $value) = explode('=', $parameter, 2);
||||||| 7dd05ee12
	$quietMode     = false;
	$confirm       = false;
	$quiet         = false;
	$debug         = false;

	foreach($parms as $parameter) {
		if (strpos($parameter, '=')) {
			list($arg, $value) = explode('=', $parameter);
=======
	foreach ($parms as $parameter) {
		if (str_contains($parameter, '=')) {
			[$arg, $value] = explode('=', $parameter, 2);
>>>>>>> origin/fix/jquery-deprecations
		} else {
			$arg   = $parameter;
			$value = '';
		}

		switch ($arg) {
			case '-d':
			case '--debug':
				display_version();
				$debug = true;

				break;
			case '--confirm':
				$confirm = true;

				break;
			case '--description':
				$description = trim($value);

				break;
			case '--ip':
				$ip = trim($value);

<<<<<<< HEAD
			break;
		case '--id':
			$id = trim($value);

			if (strpos($id, ',') !== false) {
				$ids_id = explode(',', $id);
			} else {
				$ids_id = array($id);
			}

			break;
		case '--version':
		case '-V':
		case '-v':
			display_version();
			exit(0);
		case '--help':
		case '-H':
		case '-h':
			display_help();
			exit(0);
		case '--quiet':
			$quietMode = true;
||||||| 7dd05ee12
			break;
		case '--version':
		case '-V':
		case '-v':
			display_version();
			exit(0);
		case '--help':
		case '-H':
		case '-h':
			display_help();
			exit(0);
		case '--quiet':
			$quietMode = true;
=======
				break;
			case '--id':
				$id = trim($value);
>>>>>>> origin/fix/jquery-deprecations

<<<<<<< HEAD
			break;
		default:
			print "ERROR: Invalid Argument: ($arg)" . PHP_EOL;
			display_help();
			exit(1);
||||||| 7dd05ee12
			break;
		default:
			print "ERROR: Invalid Argument: ($arg)\n\n";
			display_help();
			exit(1);
=======
				if (str_contains($id, ',')) {
					$ids_id = explode(',', $id);
				} else {
					$ids_id = [$id];
				}

				break;
			case '--version':
			case '-V':
			case '-v':
				display_version();

				exit(0);
			case '--help':
			case '-H':
			case '-h':
				display_help();

				exit(0);
			case '--quiet':
				$quietMode = true;

				break;
			default:
				print "ERROR: Invalid Argument: ($arg)" . PHP_EOL;
				display_help();

				exit(1);
>>>>>>> origin/fix/jquery-deprecations
		}
	}

<<<<<<< HEAD
	/* process the various lists into validation arrays */
	$hosts     = getHostsByDescription();
	$addresses = getAddresses();
	$ids_host  = array();
	$ids_ip    = array();
||||||| 7dd05ee12
	/* process the various lists into validation arrays */
	$hosts     = getHostsByDescription();
	$addresses = getAddresses();
	$ids_host	 = array();
	$ids_ip    = array();
=======
	// process the various lists into validation arrays
	$ids_host  = [];
	$ids_ip    = [];
>>>>>>> origin/fix/jquery-deprecations

<<<<<<< HEAD
	/* process host description */
	if ($description != '') {
		if ($debug) {
			print "Searching hosts by description..." . PHP_EOL;
		}
||||||| 7dd05ee12
	/* process host description */
	if ($description > '') {
		if ($debug) {
			print "Searching hosts by description...\n";
		}
=======
	// process host description
	if ($description != '') {
		debug('Searching hosts by description...');

		$ids_host = array_rekey(
			db_fetch_assoc_prepared('SELECT id
				FROM host
				WHERE description RLIKE ?
				OR description LIKE ?',
				[$description, '%' . $description . '%']),
			'id', 'id'
		);
>>>>>>> origin/fix/jquery-deprecations

		if (cacti_sizeof($ids_host) == 0) {
			print "ERROR: Unable to find host in the database matching description ($description)" . PHP_EOL;
<<<<<<< HEAD
||||||| 7dd05ee12
			print "ERROR: Unable to find host in the database matching description ($description)\n";
=======

>>>>>>> origin/fix/jquery-deprecations
			exit(1);
		}
	}

	if ($ip != '') {
<<<<<<< HEAD
		if ($debug) {
			print "Searching hosts by IP..." . PHP_EOL;
		}
||||||| 7dd05ee12
	if ($ip > '') {
		if ($debug) {
			print "Searching hosts by IP...\n";
		}
=======
		debug('Searching hosts by IP...');

		$ids_ip = array_rekey(
			db_fetch_assoc_prepared('SELECT id
				FROM host
				WHERE hostname RLIKE ?
				OR hostname LIKE ?',
				[$ip, '%' . $ip . '%']),
			'id', 'id'
		);
>>>>>>> origin/fix/jquery-deprecations

		if (cacti_sizeof($ids_ip) == 0) {
			print "ERROR: Unable to find host in the database matching IP ($ip)" . PHP_EOL;
<<<<<<< HEAD
||||||| 7dd05ee12
			print "ERROR: Unable to find host in the database matching IP ($ip)\n";
=======

>>>>>>> origin/fix/jquery-deprecations
			exit(1);
		}
	}

<<<<<<< HEAD
	if (cacti_sizeof($ids_host) == 0 && cacti_sizeof($ids_ip) == 0 && cacti_sizeof($ids_id) == 0) {
		print "ERROR: No matches found, was IP or Description set properly?" . PHP_EOL;
||||||| 7dd05ee12
	if (cacti_sizeof($ids_host) == 0 && cacti_sizeof($ids_ip) == 0) {
		print "ERROR: No matches found, was IP or Description set properly?\n";
=======
	if (cacti_sizeof($ids_host) == 0 && cacti_sizeof($ids_ip) == 0) {
		print 'ERROR: No matches found, was IP or Description set properly?' . PHP_EOL;

>>>>>>> origin/fix/jquery-deprecations
		exit(1);
	}

	$ids = array_merge($ids_host, $ids_ip);
	$ids = array_unique($ids, SORT_NUMERIC);

	if (cacti_sizeof($ids_id)) {
		$ids = array_merge($ids, $ids_id);
		$ids = array_unique($ids, SORT_NUMERIC);
	}

	$ids_sql = implode(',', $ids);
<<<<<<< HEAD
	if ($debug) {
		print "Finding devices with ids $ids_sql" . PHP_EOL;
	}

	$hosts = db_fetch_assoc("SELECT id, hostname, description
		FROM host
		WHERE id IN ($ids_sql)
		ORDER BY description");

	$ids_found = array();
	if (!$quiet) {
		printf('%8.s | %30.s | %30.s' . PHP_EOL, 'id', 'host', 'description');

		foreach ($hosts as $host) {
			printf('%8.d | %30.s | %30.s' . PHP_EOL,$host['id'],$host['hostname'],$host['description']);
			$ids_found[] = $host['id'];
||||||| 7dd05ee12
	$hosts = db_fetch_assoc("SELECT id, hostname, description FROM host WHERE id IN ($ids_sql) ORDER by description");
	$ids_found = array();
	if (!$quiet) {
		printf("%8.s | %30.s | %30.s\n",'id','host','description');
		foreach ($hosts as $host) {
			printf("%8.d | %30.s | %30.s\n",$host['id'],$host['hostname'],$host['description']);
			$ids_found[] = $host['id'];
=======

	debug("Finding devices with ids $ids_sql");

	$ids_found = [];

	$hosts = db_fetch_assoc_prepared('SELECT id, hostname, description
		FROM host
		WHERE id IN (?)',
		[$ids_found]);

	if (!$quietMode) {
		if (cacti_sizeof($hosts)) {
			printf('%8.s | %30.s | %30.s' . PHP_EOL, 'id', 'host', 'description');

			foreach ($hosts as $host) {
				printf('%8.d | %30.s | %30.s' . PHP_EOL, $host['id'], $host['hostname'], $host['description']);

				$ids_found[] = $host['id'];
			}

			print PHP_EOL;
>>>>>>> origin/fix/jquery-deprecations
		}
<<<<<<< HEAD

		print PHP_EOL;
||||||| 7dd05ee12
		print "\n";
=======
>>>>>>> origin/fix/jquery-deprecations
	}

	if ($confirm) {
<<<<<<< HEAD
		$ids_confirm = implode(', ', $ids_found);
		if (!$quiet) {
			print "Removing devices with ids: $ids_confirm" . PHP_EOL;
		}

		$host_id = api_device_remove_multi($ids);
||||||| 7dd05ee12
		$ids_confirm = implode(', ',$ids_found);
		if (!$quiet) {
			print "Removing devices with ids: $ids_confirm\n";
		}
		$host_id = api_device_remove_multi($ids);
=======
		if (cacti_sizeof($ids_found)) {
			$ids_confirm = implode(', ', $ids_found);
>>>>>>> origin/fix/jquery-deprecations

<<<<<<< HEAD
		if (is_error_message()) {
			print "ERROR: Failed to remove devices" . PHP_EOL;
			exit(1);
||||||| 7dd05ee12
		if (is_error_message()) {
			print "ERROR: Failed to remove devices\n";
			exit(1);
=======
			if (!$quietMode) {
				print "Removing devices with ids: $ids_confirm" . PHP_EOL;
			}

			api_device_remove_multi($ids);

			if (is_error_message()) {
				print 'ERROR: Failed to remove devices' . PHP_EOL;

				exit(1);
			} else {
				print "Success - removed device-ids: $ids_confirm" . PHP_EOL;

				foreach ($hosts as $host) {
					cacti_log('Device Removed via remove_device.php - Device ID: ' . $host['id'] . ', Hostname: ' . $host['hostname'] . ', Description: ' . $host['description'], false, 'CLI');
				}
			}
>>>>>>> origin/fix/jquery-deprecations
		} else {
<<<<<<< HEAD
			print "Success - removed device-ids: $ids_confirm" . PHP_EOL;
			foreach ($hosts as $host) {
				cacti_log("Device Removed via remove_device.php - Device ID: " . $host['id'] . ", Hostname: " . $host['hostname'] . ", Description: " . $host['description'], false, 'CLI');
			}

			exit(0);
||||||| 7dd05ee12
			print "Success - removed device-ids: $ids_confirm\n";
			exit(0);
=======
			print 'No devices found that match your search criteria.' . PHP_EOL;
>>>>>>> origin/fix/jquery-deprecations
		}
	} else {
<<<<<<< HEAD
		print "Please use --confirm to remove these devices" . PHP_EOL;
||||||| 7dd05ee12
		print "Please use --confirm to remove these devices\n";
=======
		print 'Please use --confirm to remove these devices' . PHP_EOL;
>>>>>>> origin/fix/jquery-deprecations
	}
} else {
	display_help();
}

<<<<<<< HEAD
/*  display_version - displays version information */
function display_version() {
	$version = get_cacti_cli_version();
	print "Cacti Remove Device Utility, Version $version, " . COPYRIGHT_YEARS . PHP_EOL;
}
||||||| 7dd05ee12
/*  display_version - displays version information */
function display_version() {
	$version = get_cacti_cli_version();
	print "Cacti Remove Device Utility, Version $version, " . COPYRIGHT_YEARS . "\n";
}
=======
exit(0);
>>>>>>> origin/fix/jquery-deprecations

function preg_array_key_match(string $needle, mixed $haystack) : array {
	$matches = [];

<<<<<<< HEAD
	print PHP_EOL;
	print 'usage: remove_device.php --description=\'S\' | --ip=\'S\' | --id=N,N,N,...' . PHP_EOL;
	print '    [--confirm] [--quiet]' . PHP_EOL . PHP_EOL;

	print 'Required: (on or more)' . PHP_EOL;
	print "    --description='S' A substring or regular expression of the hostname or description." . PHP_EOL;
	print "    --ip='S'          A IP or hostname (can also be a FQDN)." . PHP_EOL;
	print '    --id=N,N,...      A column delimited list of device ids.' . PHP_EOL . PHP_EOL;

	print '   (both --description and --ip can be a regex)' . PHP_EOL . PHP_EOL;
	print 'Optional:' . PHP_EOL;
	print '    --confirm           confirms that you wish to remove matches' . PHP_EOL . PHP_EOL;

	print 'List Options:' . PHP_EOL;
	print '    --quiet             batch mode value return' . PHP_EOL . PHP_EOL;
}

function preg_array_key_match($needle, $haystack) {
	global $debug;

	$matches = array();

	if (isset($haystack)) {
		if (!is_array($haystack)) {
			$haystack = array($haystack);
		}
	} else {
		$haystack = array();
||||||| 7dd05ee12
	print "\nusage: remove_device.php --description=[description] --ip=[IP]\n";
	print "    [--confirm] [--quiet]\n\n";
	print "Required:\n";
	print "    --description  the name that will be displayed by Cacti in the graphs\n";
	print "    --ip           self explanatory (can also be a FQDN)\n";
	print "   (either one or both fields can be used and may be regex)\n\n";
	print "Optional:\n";
	print "    -confirm       confirms that you wish to remove matches\n\n";
	print "List Options:\n";
	print "    --quiet - batch mode value return\n\n";
}

function preg_array_key_match($needle, $haystack) {
	global $debug;
	$matches = array ();

	if (isset($haystack)) {
		if (!is_array($haystack)) {
			$haystack = array($haystack);
		}
	} else {
		$haystack = array();
=======
	if (!is_array($haystack)) {
		$haystack = [$haystack];
>>>>>>> origin/fix/jquery-deprecations
	}

<<<<<<< HEAD
	if ($debug) {
		print "Attempting to match against '$needle' against " . cacti_sizeof($haystack) . " entries" . PHP_EOL;
	}
||||||| 7dd05ee12
	if ($debug) {
		print "Attempting to match against '$needle' against ".cacti_sizeof($haystack)." entries\n";
	}
=======
	debug("Attempting to match against '$needle' against " . cacti_sizeof($haystack) . ' entries');
>>>>>>> origin/fix/jquery-deprecations

	foreach ($haystack as $str => $value) {
<<<<<<< HEAD
		if ($debug) {
			print " - Key $str => Value $value" . PHP_EOL;
		}
||||||| 7dd05ee12
		if ($debug) {
			print " - Key $str => Value $value\n";
		}
=======
		debug(" - Key $str => Value $value");

		if (preg_match($needle, $str, $m)) {
			debug("   + $str: $value");
>>>>>>> origin/fix/jquery-deprecations

<<<<<<< HEAD
		if (preg_match ($needle, $str, $m)) {
			if ($debug) {
				print "   + $str: $value" . PHP_EOL;
			}

||||||| 7dd05ee12
		if (preg_match ($needle, $str, $m)) {
			if ($debug) {
				print "   + $str: $value\n";
			}
=======
>>>>>>> origin/fix/jquery-deprecations
			$matches[] = $value;
		}
	}

	return $matches;
}

<<<<<<< HEAD
||||||| 7dd05ee12
=======
function debug(string $message) : void {
	global $debug;

	if ($debug) {
		cacti_log('REMOTE DEBUG: ' . trim($message), false, 'WEBSVCS');
	}
}

/**
 * display_version - displays version information
 *
 * @return void
 */
function display_version() : void {
	$version = get_cacti_cli_version();
	print "Cacti Remove Device Utility, Version $version, " . COPYRIGHT_YEARS . PHP_EOL;
}

/**
 * display_help - displays help information
 *
 * @return void
 */
function display_help() : void {
	display_version();

	print PHP_EOL;
	print 'usage: remove_device.php --description=\'S\' | --ip=\'S\' | --id=N,N,N,...' . PHP_EOL;
	print '    [--confirm] [--quiet]' . PHP_EOL . PHP_EOL;

	print 'Required: (on or more)' . PHP_EOL;
	print '    --description=S   A substring or regular expression of the hostname or description.' . PHP_EOL;
	print '    --ip=S            A IP or hostname (can also be a FQDN).' . PHP_EOL;
	print '    --id=N,N,...      A column delimited list of device ids.' . PHP_EOL . PHP_EOL;

	print '   (both --description and --ip can be a regex)' . PHP_EOL . PHP_EOL;
	print 'Optional:' . PHP_EOL;
	print '    --confirm           confirms that you wish to remove matches' . PHP_EOL . PHP_EOL;

	print 'List Options:' . PHP_EOL;
	print '    --quiet             batch mode value return' . PHP_EOL . PHP_EOL;
}
>>>>>>> origin/fix/jquery-deprecations
