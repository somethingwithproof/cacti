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

namespace ProcessPollerOutputOnDemandFixture;

/*
 * Behavioural tests for process_poller_output(): the RRDs are updated by the
 * poller itself only when boost_poller_on_demand() says boost is not taking
 * the hand-off.
 */

$root = dirname(__DIR__, 4);
require_once $root . '/tests/Helpers/SourceSlice.php';

const POLLER_VERBOSITY_HIGH = 3;
const SQL_NO_CACHE          = '';

/**
 * Serve the scenario's poller_output rows; no other table has rows.
 *
 * @param string $sql The query text.
 *
 * @return array The scenario rows.
 */
function db_fetch_assoc($sql) {
	return $GLOBALS['process_poller_output']['rows'];
}

/**
 * Serve an empty prepared result set.
 *
 * @param string $sql    The query text.
 * @param array  $params The bound parameters.
 *
 * @return array An empty result.
 */
function db_fetch_assoc_prepared($sql, $params = array()) {
	return array();
}

/**
 * Report an empty poller_output table after processing.
 *
 * @param string $sql The query text.
 *
 * @return int Zero.
 */
function db_fetch_cell($sql) {
	return 0;
}

/**
 * Record the poller_output cleanup statements.
 *
 * @param string $sql The statement.
 *
 * @return bool True.
 */
function db_execute($sql) {
	$GLOBALS['process_poller_output']['deleted'][] = $sql;

	return true;
}

/**
 * Count array members without failing on non-arrays.
 *
 * @param mixed $value The value.
 *
 * @return int The member count.
 */
function cacti_sizeof($value) {
	return is_array($value) ? count($value) : 0;
}

/**
 * Re-key a result set the way the production helper does.
 *
 * @param array $data The rows.
 * @param string $key The key column.
 * @param mixed $value The value column(s).
 *
 * @return array The re-keyed rows.
 */
function array_rekey($data, $key, $value) {
	return array();
}

/**
 * Ignore log output.
 *
 * @param string $message The message.
 *
 * @return void
 */
function cacti_log($message, $output = false, $environ = 'CMDPHP', $level = '') {
}

/**
 * Report that the value is not hexadecimal.
 *
 * @param string $value The value.
 *
 * @return bool False.
 */
function is_hexadecimal($value) {
	return false;
}

/**
 * Ignore the dsstats hand-off.
 *
 * @param array $rrd_update_array The pending updates.
 *
 * @return void
 */
function dsstats_poller_output(&$rrd_update_array) {
}

/**
 * Ignore the dsdebug hand-off.
 *
 * @param array $rrd_update_array The pending updates.
 *
 * @return void
 */
function dsdebug_poller_output(&$rrd_update_array) {
}

/**
 * Ignore plugin hooks.
 *
 * @param string $hook The hook name.
 * @param mixed  $data The hook data.
 *
 * @return mixed The unchanged data.
 */
function api_plugin_hook_function($hook, $data = '') {
	return $data;
}

/**
 * Serve the scenario's boost decision.
 *
 * @return bool True when the caller must update the RRDs itself.
 */
function boost_poller_on_demand() {
	return $GLOBALS['process_poller_output']['on_demand'];
}

/**
 * Record the direct RRD update.
 *
 * @param array $rrd_update_array The pending updates.
 * @param mixed $rrdtool_pipe     The rrdtool pipe.
 * @param array $unused_cache     The unused data source cache.
 *
 * @return int The scenario's processed count.
 */
function rrdtool_function_update($rrd_update_array, &$rrdtool_pipe, &$unused_cache) {
	$GLOBALS['process_poller_output']['updates'][] = $rrd_update_array;

	return 1;
}

beforeEach(function () use ($root) {
	global $config;

	$library = sys_get_temp_dir() . '/cacti-process-poller-output-' . getmypid();

	if (!is_dir($library)) {
		mkdir($library);
	}
	file_put_contents($library . '/rrd.php', "<?php\n");
	$config['library_path'] = $library;

	if (!function_exists(__NAMESPACE__ . '\\process_poller_output')) {
		\cacti_fixture_source_function($root . '/lib/poller.php', 'process_poller_output', __NAMESPACE__);
	}

	$GLOBALS['process_poller_output'] = array(
		'on_demand' => true,
		'updates'   => array(),
		'deleted'   => array(),
		'rows'      => array(array(
			'output'           => '42',
			'time'             => '2026-01-01 00:00:00',
			'unix_time'        => 1767225600,
			'local_data_id'    => 7,
			'data_template_id' => 1,
			'rrd_path'         => '/rra/device_traffic_7.rrd',
			'rrd_name'         => 'traffic',
			'rrd_num'          => 1,
		)),
	);
});

afterEach(function () {
	global $config;

	@unlink($config['library_path'] . '/rrd.php');
	@rmdir($config['library_path']);
});

test('the poller updates the RRDs itself when boost is not taking the hand-off', function () {
	$pipe = array();

	expect(process_poller_output($pipe))->toBe(1)
		->and($GLOBALS['process_poller_output']['updates'])->toHaveCount(1)
		->and($GLOBALS['process_poller_output']['updates'][0]['/rra/device_traffic_7.rrd']['times'][1767225600])->toBe(array('traffic' => '42'))
		->and($GLOBALS['process_poller_output']['deleted'])->toHaveCount(1);
});

test('the poller leaves the RRD updates to boost when boost takes the hand-off', function () {
	$GLOBALS['process_poller_output']['on_demand'] = false;
	$pipe = array();

	expect(process_poller_output($pipe))->toBe(0)
		->and($GLOBALS['process_poller_output']['updates'])->toBe(array())
		->and($GLOBALS['process_poller_output']['deleted'])->toHaveCount(1);
});
