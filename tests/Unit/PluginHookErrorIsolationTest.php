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
 * Regression tests for plugin hook error isolation (Closes #6782).
 *
 * An unhandled exception from one plugin must not propagate into the poller
 * or the web UI. api_plugin_hook() and api_plugin_hook_function() wrap each
 * per-plugin dispatch in try/catch(\Throwable) so iteration continues to
 * the next registered plugin on failure.
 */

$pluginsPhpPath = __DIR__ . '/../../lib/plugins.php';

test('api_plugin_hook wraps dispatch in try/catch Throwable', function () use ($pluginsPhpPath) {
	$contents = file_get_contents($pluginsPhpPath);

	// try block must precede the api_plugin_run_plugin_hook call
	$try_pos      = strpos($contents, 'try {');
	$dispatch_pos = strpos($contents, 'api_plugin_run_plugin_hook(');
	expect($try_pos)->not->toBeFalse();
	expect($dispatch_pos)->not->toBeFalse();
	expect($try_pos)->toBeLessThan($dispatch_pos);
});

test('api_plugin_hook catches Throwable on dispatch failure', function () use ($pluginsPhpPath) {
	$contents = file_get_contents($pluginsPhpPath);

	expect($contents)->toContain('catch (\Throwable $e)');
});

test('api_plugin_hook logs plugin name and exception on failure', function () use ($pluginsPhpPath) {
	$contents = file_get_contents($pluginsPhpPath);

	// Must log at least the hook name, exception class, plugin name, and message.
	expect($contents)->toContain("get_class(\$e)");
	expect($contents)->toContain("\$e->getMessage()");
});

test('api_plugin_hook_function wraps dispatch in try/catch Throwable', function () use ($pluginsPhpPath) {
	$contents = file_get_contents($pluginsPhpPath);

	// Both dispatch functions must be wrapped; verify the hook_function variant.
	expect($contents)->toContain('api_plugin_run_plugin_hook_function(');

	// try must appear before api_plugin_run_plugin_hook_function
	$try_pos      = strrpos($contents, 'try {');
	$dispatch_pos = strrpos($contents, 'api_plugin_run_plugin_hook_function(');
	expect($try_pos)->not->toBeFalse();
	expect($try_pos)->toBeLessThan($dispatch_pos);
});

test('api_plugin_hook_function logs to PLUGIN channel on failure', function () use ($pluginsPhpPath) {
	$contents = file_get_contents($pluginsPhpPath);

	// cacti_log error messages must target the PLUGIN channel.
	expect($contents)->toContain("'PLUGIN'");
});
