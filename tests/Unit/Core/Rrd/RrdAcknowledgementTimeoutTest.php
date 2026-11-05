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
 * Start a child PHP process that stands in for an RRDtool worker.
 *
 * The child runs the supplied PHP code with its stdout connected to a non-blocking pipe, which
 * is how __rrd_init_acknowledged() wires a real worker. The command pipe carries the same
 * stream context options the production code records.
 *
 * @param string $code PHP code the child executes.
 * @param bool $poll True to exercise the polling read used on Windows.
 *
 * @return array{input: resource, process: resource, output: resource} Fake worker handles.
 */
function rrd_ack_start_worker($code, $poll) {
	$descriptors = array(
		0 => array('pipe', 'r'),
		1 => array('pipe', 'w'),
		2 => array('redirect', 1),
	);

	$process = proc_open(array(PHP_BINARY, '-r', $code), $descriptors, $pipes);

	expect(is_resource($process))->toBeTrue();

	expect(stream_set_blocking($pipes[1], false))->toBeTrue();

	stream_context_set_option($pipes[0], 'cacti', 'rrd_poll', $poll);
	stream_context_set_option($pipes[0], 'cacti', 'rrd_output', $pipes[1]);
	stream_context_set_option($pipes[0], 'cacti', 'rrd_process', $process);

	return array('input' => $pipes[0], 'process' => $process, 'output' => $pipes[1]);
}

/**
 * Release the handles of a fake worker and make sure its process is gone.
 *
 * @param array{input: resource, process: resource, output: resource} $worker Handles from rrd_ack_start_worker().
 *
 * @return void
 */
function rrd_ack_stop_worker($worker) {
	proc_terminate($worker['process'], 9);
	fclose($worker['input']);
	fclose($worker['output']);
	proc_close($worker['process']);
}

/**
 * Wait briefly for a terminated child to be reported as no longer running.
 *
 * @param resource $process proc_open() handle.
 *
 * @return bool True when the process exited within two seconds.
 */
function rrd_ack_process_exited($process) {
	$deadline = microtime(true) + 2;

	while (microtime(true) < $deadline) {
		$status = proc_get_status($process);

		if (!$status['running']) {
			return true;
		}

		usleep(20000);
	}

	return false;
}

/* The select path cannot run on Windows, so only the polling read is exercised there. */
$rrd_ack_modes = PHP_OS_FAMILY === 'Windows' ? array('poll' => true) : array('poll' => true, 'select' => false);

test('acknowledgement returns an OK response', function ($poll) {
	$worker = rrd_ack_start_worker('echo "OK u:0.001 s:0.002 cu:0 cs:0\n"; sleep(30);', $poll);

	$response = __rrd_read_acknowledgement($worker['input'], 5);

	expect($response)->toBe("OK u:0.001 s:0.002 cu:0 cs:0\n");

	rrd_ack_stop_worker($worker);
})->with($rrd_ack_modes);

test('acknowledgement returns an ERROR response', function ($poll) {
	$worker = rrd_ack_start_worker('echo "ERROR: illegal attempt to update using time 1 when last update time is 2\n"; sleep(30);', $poll);

	$response = __rrd_read_acknowledgement($worker['input'], 5);

	expect($response)->toStartWith('ERROR: illegal attempt');

	rrd_ack_stop_worker($worker);
})->with($rrd_ack_modes);

test('acknowledgement is assembled from a response split across writes', function ($poll) {
	$worker = rrd_ack_start_worker('echo "OK u:0"; fflush(STDOUT); usleep(300000); echo ".1 s:0.2\n"; sleep(30);', $poll);

	$response = __rrd_read_acknowledgement($worker['input'], 5);

	expect($response)->toBe("OK u:0.1 s:0.2\n");

	rrd_ack_stop_worker($worker);
})->with($rrd_ack_modes);

test('acknowledgement times out on a worker that never answers and terminates it', function ($poll) {
	$worker = rrd_ack_start_worker('sleep(30);', $poll);

	$start    = microtime(true);
	$response = __rrd_read_acknowledgement($worker['input'], 1);
	$elapsed  = microtime(true) - $start;

	expect($response)->toBeFalse();
	expect($elapsed)->toBeLessThan(6.0);

	$options = stream_context_get_options($worker['input']);

	expect($options['cacti']['rrd_failed'] ?? false)->toBeTrue();
	expect(rrd_ack_process_exited($worker['process']))->toBeTrue();

	rrd_ack_stop_worker($worker);
})->with($rrd_ack_modes);

test('acknowledgement times out on a partial response and terminates the worker', function ($poll) {
	$worker = rrd_ack_start_worker('echo "OK u:0.1"; fflush(STDOUT); sleep(30);', $poll);

	$start    = microtime(true);
	$response = __rrd_read_acknowledgement($worker['input'], 1);
	$elapsed  = microtime(true) - $start;

	expect($response)->toBeFalse();
	expect($elapsed)->toBeLessThan(6.0);
	expect(rrd_ack_process_exited($worker['process']))->toBeTrue();

	rrd_ack_stop_worker($worker);
})->with($rrd_ack_modes);

test('acknowledgement fails when the worker exits without answering', function ($poll) {
	$worker = rrd_ack_start_worker('exit(0);', $poll);

	$start    = microtime(true);
	$response = __rrd_read_acknowledgement($worker['input'], 5);

	expect($response)->toBeFalse();
	expect(microtime(true) - $start)->toBeLessThan(5.0);

	rrd_ack_stop_worker($worker);
})->with($rrd_ack_modes);

test('acknowledgement rejects an oversized response', function ($poll) {
	$worker = rrd_ack_start_worker('echo str_repeat("x", 70000); sleep(30);', $poll);

	$response = __rrd_read_acknowledgement($worker['input'], 5);

	expect($response)->toBeFalse();
	expect(rrd_ack_process_exited($worker['process']))->toBeTrue();

	rrd_ack_stop_worker($worker);
})->with($rrd_ack_modes);

test('acknowledgement fails for a pipe that was not opened as an acknowledged worker', function () {
	$pipe = fopen('php://memory', 'w+');

	expect(__rrd_read_acknowledgement($pipe, 1))->toBeFalse();

	fclose($pipe);
});
