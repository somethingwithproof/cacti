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

if (!defined('CACTI_CLI')) {
	define('CACTI_CLI', true);
}

if (!function_exists('__')) {
	function __() {
		$args   = func_get_args();
		$format = array_shift($args);

		return $args ? vsprintf($format, $args) : $format;
	}
}

require_once dirname(__DIR__, 4) . '/lib/rrd.php';

/**
 * Locate an RRDtool binary for the real-process acknowledgement contracts.
 *
 * @return string|null Path from CACTI_TEST_RRDTOOL, or null when no binary is configured.
 */
function rrd_ack_real_binary() {
	$binary = getenv('CACTI_TEST_RRDTOOL');

	return ($binary !== false && $binary !== '' && is_file($binary)) ? $binary : null;
}

/**
 * Start a real RRDtool worker wired the way __rrd_init_acknowledged() wires one.
 *
 * @param string $binary Path to rrdtool.
 *
 * @return resource Writable command pipe carrying the worker stream context options.
 */
function rrd_ack_real_worker($binary) {
	$descriptors = array(
		0 => array('pipe', 'r'),
		1 => array('pipe', 'w'),
		2 => array('redirect', 1),
	);

	$process = proc_open(array($binary, '-'), $descriptors, $pipes);

	expect(is_resource($process))->toBeTrue();
	expect(stream_set_blocking($pipes[1], false))->toBeTrue();

	stream_context_set_option($pipes[0], 'cacti', 'rrd_poll', PHP_OS_FAMILY === 'Windows');
	stream_context_set_option($pipes[0], 'cacti', 'rrd_output', $pipes[1]);
	stream_context_set_option($pipes[0], 'cacti', 'rrd_process', $process);

	return $pipes[0];
}

test('a real rrdtool worker acknowledges OK and ERROR commands', function () {
	$binary = rrd_ack_real_binary();

	if ($binary === null) {
		$this->markTestSkipped('CACTI_TEST_RRDTOOL does not point at an rrdtool binary.');
	}

	$rrd   = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'cacti-ack-' . getmypid() . '.rrd';
	$input = rrd_ack_real_worker($binary);

	fwrite($input, 'create "' . $rrd . '" --step 60 DS:x:GAUGE:120:0:U RRA:AVERAGE:0.5:1:10' . "\r\n");
	fflush($input);

	expect(__rrd_read_acknowledgement($input, 20))->toMatch('/^OK\b/');

	fwrite($input, 'update "' . $rrd . '.missing" N:1' . "\r\n");
	fflush($input);

	expect(__rrd_read_acknowledgement($input, 20))->toMatch('/^ERROR:/');

	$options = stream_context_get_options($input);

	fclose($input);
	proc_terminate($options['cacti']['rrd_process'], 9);
	proc_close($options['cacti']['rrd_process']);
	@unlink($rrd);
});

test('a real rrdtool worker that receives no command times out and is terminated', function () {
	$binary = rrd_ack_real_binary();

	if ($binary === null) {
		$this->markTestSkipped('CACTI_TEST_RRDTOOL does not point at an rrdtool binary.');
	}

	$input   = rrd_ack_real_worker($binary);
	$options = stream_context_get_options($input);

	$start = microtime(true);

	expect(__rrd_read_acknowledgement($input, 1))->toBeFalse();
	expect(microtime(true) - $start)->toBeLessThan(6.0);

	$deadline = microtime(true) + 2;

	while (proc_get_status($options['cacti']['rrd_process'])['running'] && microtime(true) < $deadline) {
		usleep(20000);
	}

	expect(proc_get_status($options['cacti']['rrd_process'])['running'])->toBeFalse();

	fclose($input);
	proc_close($options['cacti']['rrd_process']);
});
