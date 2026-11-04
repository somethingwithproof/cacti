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
 * Behavioural tests for the rrdcheck retention purge (#8574).  When Boost is
 * active poller_rrdcheck.php skips its periodic rollup and the hand-off runs
 * through rrdcheck_boost_bottom() in lib/rrdcheck.php, so both paths must
 * purge through the shared rrdcheck_purge_old_findings() helper.  The
 * unchanged production functions are sliced out and run against recording
 * stand-ins for the database and process helpers.
 */

namespace RrdcheckBoostPurgeFixture;

$root = dirname(__DIR__, 4);
require_once $root . '/tests/Helpers/SourceSlice.php';

/**
 * Record a database statement in the shared event log.
 *
 * @param string $sql The statement.
 *
 * @return void No value is returned.
 */
function db_execute($sql) {
	$GLOBALS['rrdcheck_purge_events'][] = array('sql', $sql);
}

/**
 * Return a configured option value for the scenario under test.
 *
 * @param string $name The option name.
 *
 * @return mixed The option value, or an empty string.
 */
function read_config_option($name) {
	return $GLOBALS['rrdcheck_purge_settings'][$name] ?? '';
}

/**
 * Record a configuration write.
 *
 * @param string $name  The option name.
 * @param mixed  $value The new value.
 *
 * @return void No value is returned.
 */
function set_config_option($name, $value) {
	$GLOBALS['rrdcheck_purge_events'][] = array('set', $name);
}

/**
 * Record the launch of the child collectors.
 *
 * @param string $type The collector type.
 *
 * @return void No value is returned.
 */
function rrdcheck_launch_children($type) {
	$GLOBALS['rrdcheck_purge_events'][] = array('launch', $type);
}

/**
 * Report running children, draining one scripted count per call.
 *
 * @param string $type The collector type.
 *
 * @return int The scripted number of running processes.
 */
function rrdcheck_processes_running($type) {
	$GLOBALS['rrdcheck_purge_events'][] = array('running', $type);

	return array_shift($GLOBALS['rrdcheck_purge_running']);
}

/**
 * Ignore debug output.
 *
 * @param string $message The message.
 *
 * @return void No value is returned.
 */
function rrdcheck_debug($message) {
}

/**
 * Skip the real two second wait between process checks.
 *
 * @param int $seconds The requested delay.
 *
 * @return int Always zero.
 */
function sleep($seconds) {
	return 0;
}

/**
 * Record the statistics roll-up that closes a cycle.
 *
 * @param string $label The statistics label.
 *
 * @return void No value is returned.
 */
function rrdcheck_log_statistics($label) {
	$GLOBALS['rrdcheck_purge_events'][] = array('stats', $label);
}

/**
 * Ignore log output.
 *
 * @param string $message The message.
 * @param bool   $output  Unused.
 * @param string $source  Unused.
 *
 * @return void No value is returned.
 */
function cacti_log($message, $output = false, $source = '') {
}

cacti_fixture_source_function($root . '/lib/rrdcheck.php', 'rrdcheck_purge_old_findings', __NAMESPACE__);
cacti_fixture_source_function($root . '/lib/rrdcheck.php', 'rrdcheck_boost_bottom', __NAMESPACE__);
cacti_fixture_source_function($root . '/poller_rrdcheck.php', 'rrdcheck_master_handler', __NAMESPACE__);

/**
 * Reset the scenario state and the stand-in base path.
 *
 * @param array $settings Option values to serve.
 * @param array $running  Scripted running-process counts.
 *
 * @return void No value is returned.
 */
function rrdcheck_purge_scenario(array $settings, array $running = array(0)) {
	global $config, $type;

	$base = sys_get_temp_dir() . '/cacti-rrdcheck-purge';

	if (!is_dir($base . '/lib')) {
		mkdir($base . '/lib', 0700, true);
		file_put_contents($base . '/lib/rrd.php', "<?php\n");
	}

	$config                             = array('base_path' => $base);
	$type                               = 'master';
	$GLOBALS['rrdcheck_purge_events']   = array();
	$GLOBALS['rrdcheck_purge_settings'] = $settings;
	$GLOBALS['rrdcheck_purge_running']  = $running;
}

/**
 * Return the recorded event names in order, without their arguments.
 *
 * @return array The ordered event kinds, with launch/stats/sql labels appended.
 */
function rrdcheck_purge_trace() {
	return array_map(function ($event) {
		return $event[0] == 'sql' ? 'purge' : $event[0] . ':' . $event[1];
	}, $GLOBALS['rrdcheck_purge_events']);
}

const PURGE_SQL = 'DELETE FROM rrdcheck WHERE test_date < DATE_SUB(NOW(), INTERVAL 7 DAY)';

test('the shared helper issues the seven day retention delete', function () {
	rrdcheck_purge_scenario(array());
	rrdcheck_purge_old_findings();

	expect($GLOBALS['rrdcheck_purge_events'])->toBe(array(array('sql', PURGE_SQL)));
});

test('the boost hand-off purges after its children finish and before BOOST statistics', function () {
	rrdcheck_purge_scenario(array('rrdcheck_enable' => 'on'), array(2, 1, 0));
	rrdcheck_boost_bottom();

	expect(rrdcheck_purge_trace())->toBe(array(
		'set:rrdcheck_last_run_time', 'launch:bmaster',
		'running:bmaster', 'running:bmaster', 'running:bmaster',
		'purge', 'stats:BOOST',
	))->and($GLOBALS['rrdcheck_purge_events'][5][1])->toBe(PURGE_SQL);
});

test('the boost hand-off does nothing when rrdcheck is disabled', function () {
	rrdcheck_purge_scenario(array('rrdcheck_enable' => ''));
	rrdcheck_boost_bottom();

	expect($GLOBALS['rrdcheck_purge_events'])->toBe(array());
});

test('the periodic master handler purges after its children finish and before HOURLY statistics', function () {
	rrdcheck_purge_scenario(array('rrdcheck_interval' => 60, 'rrdcheck_last_run_time' => 1, 'boost_rrd_update_enable' => ''), array(1, 0));
	rrdcheck_master_handler(false);

	expect(rrdcheck_purge_trace())->toBe(array(
		'launch:master', 'running:master', 'running:master', 'purge', 'stats:HOURLY',
	));
});

test('the periodic master handler purges on a forced run even before the interval elapses', function () {
	rrdcheck_purge_scenario(array('rrdcheck_interval' => 60, 'rrdcheck_last_run_time' => time(), 'boost_rrd_update_enable' => ''));
	rrdcheck_master_handler(true);

	expect(rrdcheck_purge_trace())->toContain('purge');
});

test('the periodic master handler neither collects nor purges before the interval elapses', function () {
	rrdcheck_purge_scenario(array('rrdcheck_interval' => 60, 'rrdcheck_last_run_time' => time(), 'boost_rrd_update_enable' => ''));
	rrdcheck_master_handler(false);

	expect($GLOBALS['rrdcheck_purge_events'])->toBe(array());
});

test('the periodic master handler leaves the cycle to Boost when Boost is active', function () {
	rrdcheck_purge_scenario(array('rrdcheck_interval' => 60, 'rrdcheck_last_run_time' => 1, 'boost_rrd_update_enable' => 'on'));
	rrdcheck_master_handler(true);

	expect($GLOBALS['rrdcheck_purge_events'])->toBe(array());
});
