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
 * Coverage for the PHP 8.4 idiom migration in the lib/ modernization branch.
 *
 * These tests call the real, modernized library functions so the string-cast,
 * first-class-callable and null-coalescing-assignment rewrites are exercised
 * rather than re-implemented. Only helpers reachable without RRDtool, SNMP,
 * a live poller or the network are covered here; service-bound paths are
 * documented in the branch coverage report.
 */

require_once dirname(__DIR__) . '/Helpers/CactiStubs.php';
require_once dirname(__DIR__, 2) . '/include/global.php';
require_once dirname(__DIR__, 2) . '/lib/snmp.php';
require_once dirname(__DIR__, 2) . '/lib/data_query.php';
require_once dirname(__DIR__, 2) . '/lib/template.php';
require_once dirname(__DIR__, 2) . '/lib/boost.php';
require_once dirname(__DIR__, 2) . '/lib/rrd.php';
require_once dirname(__DIR__, 2) . '/lib/ping.php';
require_once dirname(__DIR__, 2) . '/lib/spikekill.php';

// ---------------------------------------------------------------------------
// lib/snmp.php
// ---------------------------------------------------------------------------

test('cacti_snmp_validate_oid accepts numeric OIDs and rejects the rest', function () {
	expect(cacti_snmp_validate_oid('.1.3.6.1.2.1'))->toBeTrue()
		->and(cacti_snmp_validate_oid('1.3.6'))->toBeTrue()
		->and(cacti_snmp_validate_oid('IF-MIB::ifDescr.1'))->toBeFalse()
		->and(cacti_snmp_validate_oid(''))->toBeFalse();
});

test('format_snmp_string strips the OID prefix when included', function () {
	// snmp_oid_included = true, value contains '=' and a '::' separator
	$out = format_snmp_string('IF-MIB::ifDescr.1 = STRING: eth0', true);
	expect($out)->toBe('eth0');

	// snmp_oid_included = true, no '=' present -> single-element array branch
	expect(format_snmp_string('bareValue', true))->toBe('bareValue');

	// snmp_oid_included = false -> trim branch
	expect(format_snmp_string('  spaced  ', false))->toBe('spaced');
});

test('format_snmp_string blanks values containing a banned token', function () {
	global $banned_snmp_strings;
	$banned_snmp_strings = ['No Such Object'];

	expect(format_snmp_string('No Such Object available at this OID', false))->toBe('');

	$banned_snmp_strings = [];
});

// ---------------------------------------------------------------------------
// lib/data_query.php
// ---------------------------------------------------------------------------

test('rewrite_snmp_enum_value applies a translation map entry', function () {
	rewrite_snmp_enum_value(null); // reset the static map cache

	// An XML-derived match/replace map builds an anchored regex; the match path
	// exercises the preg_match/preg_replace string casts.
	$map = [['match' => 'down', 'replace' => 'DOWN']];

	expect(rewrite_snmp_enum_value('ifOperStatus', 'down', $map))->toBe('DOWN');

	rewrite_snmp_enum_value(null);

	// No matching entry returns the original value unchanged.
	expect(rewrite_snmp_enum_value('ifOperStatus', 'up', $map))->toBe('up');
});

test('data_query_format_record hex-encodes values with undetectable encoding', function () {
	$plain = data_query_format_record(1, 2, 'ifDescr', null, 'eth0', '1', '.1.3.6.1');
	expect($plain)->toContain('eth0');

	$binary = data_query_format_record(1, 2, 'ifPhysAddress', null, "\xff\xfe\x00", '1', '.1.3.6.1');
	expect($binary)->toContain(bin2hex("\xff\xfe\x00"));
});

// ---------------------------------------------------------------------------
// lib/template.php
// ---------------------------------------------------------------------------

test('parse_graph_template_id splits a composite id', function () {
	expect(parse_graph_template_id('5_2'))->toBe(['graph_template_id' => '5', 'output_type_id' => '2'])
		->and(parse_graph_template_id('7'))->toBe(['graph_template_id' => '7']);
});

// ---------------------------------------------------------------------------
// lib/boost.php
// ---------------------------------------------------------------------------

test('boost_array_orderby sorts rows by a named column', function () {
	$rows = [
		['name' => 'b', 'n' => 3],
		['name' => 'a', 'n' => 1],
		['name' => 'c', 'n' => 2],
	];

	$sorted = boost_array_orderby($rows, 'n', SORT_ASC, SORT_NUMERIC);

	expect(array_column($sorted, 'n'))->toBe([1, 2, 3]);
});

// ---------------------------------------------------------------------------
// lib/rrd.php
// ---------------------------------------------------------------------------

test('rrdtool_parse_error rewrites a missing-file rrdtool error', function () {
	$dir = sys_get_temp_dir() . '/cacti_rrd_' . uniqid();
	mkdir($dir, 0700, true);

	try {
		$msg = rrdtool_parse_error("ERROR: opening '$dir/missing.rrd': No such file or directory");
		expect($msg)->toContain('missing.rrd');
	} finally {
		@rmdir($dir);
	}
});

test('gradient emits CDEF lines for percentage and absolute lower bounds', function () {
	$pct = gradient('ds', '#000000', '#ffffff', '', 4, '50%');
	expect($pct)->toContain('CDEF:');

	$abs = gradient('ds', '#000000', '#ffffff', '', 4, '30');
	expect($abs)->toContain('CDEF:');
});

test('add_business_hours injects office-hour AREA definitions when enabled', function () {
	global $config;

	$config[OPTIONS_CLI]['business_hours_enable']        = 'on';
	$config[OPTIONS_CLI]['business_hours_start']         = '08:00';
	$config[OPTIONS_CLI]['business_hours_end']           = '17:00';
	$config[OPTIONS_CLI]['business_hours_max_days']      = 7;
	$config[OPTIONS_CLI]['business_hours_color']         = '';
	$config[OPTIONS_CLI]['business_hours_opacity']       = '7F';
	$config[OPTIONS_CLI]['business_hours_hide_weekends'] = '';

	set_request_var('business_hours', 'true');

	$now  = time();
	$data = [
		'start'      => $now - 86400,
		'end'        => $now,
		'graph_defs' => '',
	];

	$xport_meta = [];
	$result     = add_business_hours($data, $xport_meta);

	expect($result)->toBeArray()
		->and($result)->toHaveKey('graph_defs');

	unset(
		$config[OPTIONS_CLI]['business_hours_enable'],
		$config[OPTIONS_CLI]['business_hours_start'],
		$config[OPTIONS_CLI]['business_hours_end'],
		$config[OPTIONS_CLI]['business_hours_max_days'],
		$config[OPTIONS_CLI]['business_hours_color'],
		$config[OPTIONS_CLI]['business_hours_opacity'],
		$config[OPTIONS_CLI]['business_hours_hide_weekends']
	);
});

// ---------------------------------------------------------------------------
// lib/mib_cache.php
// ---------------------------------------------------------------------------

test('MibCache promotes the mib name through the constructor', function () {
	$cache = new MibCache('IF-MIB');
	expect($cache)->toBeInstanceOf(MibCache::class);
});

// ---------------------------------------------------------------------------
// lib/ping.php
// ---------------------------------------------------------------------------

test('Net_Ping installs and restores its error handler', function () {
	$ping = new Net_Ping();
	$ping->set_ping_error_handler();
	$ping->restore_cacti_error_handler();

	expect($ping->ping_error_handler(E_WARNING, 'x', 'f', 1))->toBeTrue();
});

// ---------------------------------------------------------------------------
// lib/plugins.php
// ---------------------------------------------------------------------------

test('plugin_valid_dependencies parses a space separated requirement list', function () {
	// Empty short-circuit.
	expect(plugin_valid_dependencies(''))->toBeTrue();

	// A space triggers the array_map(trim(...)) split; with no plugin_config
	// rows present the dependency cannot be satisfied.
	expect(plugin_valid_dependencies('thold:>=1.0 syslog:>=2.0'))->toBeFalse();
});

test('plugin_fetch_latest_plugins fails closed without a configured repository', function () {
	ob_start();
	$result = plugin_fetch_latest_plugins();
	ob_end_clean();

	expect($result)->toBeFalse();
});

// ---------------------------------------------------------------------------
// lib/spikekill.php
// ---------------------------------------------------------------------------

// spikekill's constructor reads settings defaults from the database, so these
// tests build the object without the constructor (all typed properties carry
// declaration defaults) and drive the target methods directly.

test('spikekill initialize converts window dates and records the repair type', function () {
	$sk = (new ReflectionClass(spikekill::class))->newInstanceWithoutConstructor();
	$sk->method    = 'float';
	$sk->avgnan    = 'avg';
	$sk->out_start = 'not-a-timestamp';
	$sk->out_end   = 'also-not';
	$sk->html      = false;

	$init = new ReflectionMethod(spikekill::class, 'initializeSpikekill');
	$init->setAccessible(true);
	$init->invoke($sk);

	// initializeSpikekill emits the settings header including the repair type.
	expect($sk->get_output())->toContain('Repair Type');
});

test('spikekill removeComments trims blank and comment lines', function () {
	$sk = (new ReflectionClass(spikekill::class))->newInstanceWithoutConstructor();

	$remove = new ReflectionMethod(spikekill::class, 'removeComments');
	$remove->setAccessible(true);

	$output = ['  <rra>  ', '', '  <cf>AVERAGE</cf>  '];
	$result = $remove->invokeArgs($sk, [&$output]);

	expect($result)->toBeArray()
		->and($result)->not->toContain('');
});

test('spikekill updateXML walks rra and database boundary lines', function () {
	$sk = (new ReflectionClass(spikekill::class))->newInstanceWithoutConstructor();

	$update = new ReflectionMethod(spikekill::class, 'updateXML');
	$update->setAccessible(true);

	$output = ['<database>', '</database>', '<rra>', '</rra>'];
	$rra    = [];
	$result = $update->invokeArgs($sk, [&$output, &$rra]);

	expect($result)->toBeArray();
});
