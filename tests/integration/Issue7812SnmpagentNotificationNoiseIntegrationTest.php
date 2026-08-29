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
 * Coverage for issue #7812. An install that never configured an SNMP trap
 * receiver still got a WARNING on every poller pass that raised a device
 * event, and the line named no remedy, so it read like a fault in the poller.
 *
 * snmpagent_notification() now tells the two cases apart. With no receiver at
 * all the notification facility is simply unused, which is a debug detail.
 * With a receiver defined but not subscribed the warning stays and names the
 * page that fixes it.
 *
 * The real function runs against a sqlite-backed connection and the assertions
 * read the log file it writes, so the shipped code decides the outcome.
 */

require_once dirname(__DIR__) . '/Helpers/FakeMySQLPDO.php';
require_once dirname(__DIR__, 2) . '/include/vendor/autoload.php';
require_once dirname(__DIR__, 2) . '/include/global_constants.php';
require_once dirname(__DIR__, 2) . '/lib/database.php';
require_once dirname(__DIR__, 2) . '/lib/functions.php';
require_once dirname(__DIR__, 2) . '/lib/snmpagent.php';

if (!defined('CACTI_WEB')) {
	define('CACTI_WEB', false);
}

if (!defined('CACTI_PATH_LOG')) {
	define('CACTI_PATH_LOG', sys_get_temp_dir());
}

/* include/global_arrays.php wants a live install behind it, and the function
   under test reads just this one array out of it. */
$GLOBALS['snmpagent_event_severity'] = [
	SNMPAGENT_EVENT_SEVERITY_LOW      => 'low',
	SNMPAGENT_EVENT_SEVERITY_MEDIUM   => 'medium',
	SNMPAGENT_EVENT_SEVERITY_HIGH     => 'high',
	SNMPAGENT_EVENT_SEVERITY_CRITICAL => 'critical'
];

/**
 * Seeds the tables snmpagent_notification() reads before it decides whether
 * anything is listening.
 *
 * @param int  $verbosity     Value for the log_verbosity setting.
 * @param bool $with_receiver Whether to define a receiver that is not subscribed.
 *
 * @return FakeMySQLPDO A connection holding the seeded schema.
 */
function snmpagent_noise_seed(int $verbosity, bool $with_receiver) : FakeMySQLPDO {
	$conn = new FakeMySQLPDO();

	$conn->exec('CREATE TABLE settings (name TEXT PRIMARY KEY, value TEXT)');
	$conn->exec("INSERT INTO settings (name, value) VALUES
		('log_destination', '1'),
		('log_verbosity', '$verbosity'),
		('path_cactilog', '" . snmpagent_noise_logfile() . "')");

	$conn->exec('CREATE TABLE snmpagent_cache (name TEXT, mib TEXT, oid TEXT)');
	$conn->exec("INSERT INTO snmpagent_cache (name, mib, oid)
		VALUES ('cactiNotifyDeviceFailedPoll', 'CACTI-MIB', '.1.3.6.1.4.1.29489.1.6.0.4')");

	$conn->exec('CREATE TABLE snmpagent_managers (id INTEGER PRIMARY KEY, hostname TEXT, disabled TEXT)');
	$conn->exec('CREATE TABLE snmpagent_managers_notifications (manager_id INTEGER, notification TEXT, mib TEXT)');

	if ($with_receiver) {
		// subscribed to a different event, so the join still comes back empty
		$conn->exec("INSERT INTO snmpagent_managers (id, hostname, disabled) VALUES (1, 'trapsink.example.net', '')");
		$conn->exec("INSERT INTO snmpagent_managers_notifications (manager_id, notification, mib)
			VALUES (1, 'cactiNotifyDeviceDown', 'CACTI-MIB')");
	}

	return $conn;
}

/**
 * @return string The path cacti_log() is pointed at for this test.
 */
function snmpagent_noise_logfile() : string {
	// per pid, so a parallel run does not read another worker's log
	return sys_get_temp_dir() . '/cacti-issue7812-' . getmypid() . '.log';
}

/**
 * Raises the failed poll event and returns whatever reached the log.
 *
 * @return string The log file contents, empty when nothing was written.
 */
function snmpagent_noise_raise() : string {
	snmpagent_notification(
		'cactiNotifyDeviceFailedPoll',
		'CACTI-MIB',
		['cactiApplDeviceIndex' => 20],
		SNMPAGENT_EVENT_SEVERITY_MEDIUM
	);

	$logfile = snmpagent_noise_logfile();

	return is_file($logfile) ? (string) file_get_contents($logfile) : '';
}

beforeEach(function () {
	global $database_sessions, $database_hostname, $database_port, $database_default, $config;

	$this->db_globals = [$database_sessions, $database_hostname, $database_port, $database_default];
	$this->config     = $config ?? [];

	if (is_file(snmpagent_noise_logfile())) {
		unlink(snmpagent_noise_logfile());
	}
});

/* The fake handle throws on Cacti's MySQL syntax, so leaving it in place would
   abort every later test that reads a setting. The cached option array goes
   with it, or log_verbosity leaks out of this file. */
afterEach(function () {
	global $database_sessions, $database_hostname, $database_port, $database_default, $config;

	[$database_sessions, $database_hostname, $database_port, $database_default] = $this->db_globals;

	$config = $this->config;

	if (is_file(snmpagent_noise_logfile())) {
		unlink(snmpagent_noise_logfile());
	}
});

/**
 * Points lib/database.php at a seeded connection and clears the option cache.
 *
 * @param int  $verbosity     Value for the log_verbosity setting.
 * @param bool $with_receiver Whether to define a receiver that is not subscribed.
 *
 * @return void
 */
function snmpagent_noise_connect(int $verbosity, bool $with_receiver) : void {
	$GLOBALS['database_hostname']      = 'unittest';
	$GLOBALS['database_port']          = 0;
	$GLOBALS['database_default']       = 'unittest';
	$GLOBALS['database_total_queries'] = 0;
	$GLOBALS['database_sessions']      = ['unittest:0:unittest' => snmpagent_noise_seed($verbosity, $with_receiver)];
	$GLOBALS['config']                 = [];
}

test('an install with no receivers keeps the warning out of the log', function () {
	snmpagent_noise_connect(verbosity: POLLER_VERBOSITY_LOW, with_receiver: false);

	// pre-fix this wrote the warning at POLLER_VERBOSITY_NONE, so every level saw it
	expect(snmpagent_noise_raise())->toBe('');
});

test('the discarded event is still visible at debug verbosity', function () {
	snmpagent_noise_connect(verbosity: POLLER_VERBOSITY_DEBUG, with_receiver: false);

	expect(snmpagent_noise_raise())
		->toContain('No SNMP notification receivers are defined')
		->toContain('cactiNotifyDeviceFailedPoll');
});

test('a defined receiver that is not subscribed still warns, and says where to fix it', function () {
	snmpagent_noise_connect(verbosity: POLLER_VERBOSITY_LOW, with_receiver: true);

	expect(snmpagent_noise_raise())
		->toContain('WARNING: No notification receivers configured for event: cactiNotifyDeviceFailedPoll (CACTI-MIB), severity: medium')
		->toContain('Console > Utilities > SNMP Managers');
});

test('the event is raised once per process, not once per poll of a device', function () {
	snmpagent_noise_connect(verbosity: POLLER_VERBOSITY_DEBUG, with_receiver: false);

	snmpagent_noise_raise();
	$log = snmpagent_noise_raise();

	expect(substr_count($log, 'cactiNotifyDeviceFailedPoll'))->toBe(1);
});
