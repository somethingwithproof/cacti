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

namespace BoostPollerOnDemandFixture;

/*
 * Behavioural tests for boost_poller_on_demand(): it takes no arguments and
 * reports whether the caller should update the RRDs itself.
 */

$root = dirname(__DIR__, 4);
require_once $root . '/tests/Helpers/SourceSlice.php';

/**
 * Serve a scenario option value.
 *
 * @param string $name The option name.
 *
 * @return mixed The option value, or an empty string.
 */
function read_config_option($name) {
	return $GLOBALS['boost_on_demand_settings'][$name] ?? '';
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
	$GLOBALS['boost_on_demand_writes'][$name] = $value;
}

/**
 * Stand-in for the boost configuration check.
 *
 * @return bool The scenario's configured verdict.
 */
function boost_check_correct_enabled() {
	return $GLOBALS['boost_on_demand_correct'];
}

/* set_error_handler() resolves its callback name in the global namespace */
if (!function_exists('boost_error_handler')) {
	cacti_fixture_source_function($root . '/lib/boost.php', 'boost_error_handler', '');
}

cacti_fixture_source_function($root . '/lib/boost.php', 'boost_poller_on_demand', __NAMESPACE__);

/**
 * Run the function under a scenario.
 *
 * @param int    $poller_id The poller id.
 * @param string $enabled   The boost_rrd_update_enable value.
 * @param bool   $correct   The boost configuration verdict.
 *
 * @return bool The function result.
 */
function boost_on_demand_run($poller_id, $enabled, $correct) {
	global $config;

	$config                                 = array('poller_id' => $poller_id);
	$GLOBALS['boost_on_demand_settings']    = array('boost_rrd_update_enable' => $enabled);
	$GLOBALS['boost_on_demand_writes']      = array();
	$GLOBALS['boost_on_demand_correct']     = $correct;

	return boost_poller_on_demand();
}

test('boost off on the main poller tells the caller to update the RRDs itself', function () {
	expect(boost_on_demand_run(1, '', true))->toBeTrue()
		->and($GLOBALS['boost_on_demand_writes'])->toBe(array());
});

test('boost on and correctly configured tells the caller to leave the RRDs to boost', function () {
	$level = error_reporting();

	expect(boost_on_demand_run(1, 'on', true))->toBeFalse()
		->and($GLOBALS['boost_on_demand_writes'])->toBe(array('boost_rrd_update_enable' => 'on'))
		->and(error_reporting())->toBe($level);
});

test('boost on but misconfigured falls back to updating the RRDs directly', function () {
	expect(boost_on_demand_run(1, 'on', false))->toBeTrue();
});

test('a remote poller forces boost on even when the setting is off', function () {
	expect(boost_on_demand_run(2, '', true))->toBeFalse()
		->and($GLOBALS['boost_on_demand_writes'])->toBe(array('boost_rrd_update_enable' => 'on'));
});

test('the function accepts no parameters', function () {
	expect((new \ReflectionFunction(__NAMESPACE__ . '\boost_poller_on_demand'))->getNumberOfParameters())->toBe(0);
});
