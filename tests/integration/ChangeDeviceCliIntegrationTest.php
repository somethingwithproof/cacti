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
 | Cacti is designed, written and maintained by the Cacti Group.           |
 |                                                                         |
 | Please read the included docs/CONTRIBUTING.md file for more information.|
 +-------------------------------------------------------------------------+
 */

/*
 * Runs the real cli/change_device.php in a child PHP process so the option
 * parser, CSV reader, validation, no-change detection and save path execute
 * end to end.  The script is copied into a scratch tree whose include/ and
 * lib/ files are stubs: only the database lookup and api_device_save() are
 * replaced, with the saved arguments recorded for assertions.
 */

/**
 * Create the scratch tree holding a copy of the script and the stub libraries.
 *
 * @return string The absolute path of the tree.
 */
function change_device_cli_tree() {
	static $tree = null;

	if ($tree !== null) {
		return $tree;
	}

	$tree = sys_get_temp_dir() . '/cacti-change-device-' . bin2hex(random_bytes(6));

	mkdir($tree . '/cli', 0700, true);
	mkdir($tree . '/include', 0700, true);
	mkdir($tree . '/lib', 0700, true);

	copy(dirname(__DIR__, 2) . '/cli/change_device.php', $tree . '/cli/change_device.php');

	foreach (array('api_automation_tools', 'api_device', 'api_data_source', 'api_graph', 'api_tree', 'data_query', 'poller', 'snmp', 'template', 'utility') as $library) {
		file_put_contents($tree . '/lib/' . $library . '.php', "<?php\n");
	}

	file_put_contents($tree . '/include/cli_check.php', <<<'STUB'
<?php
$config = array('base_path' => dirname(__DIR__), 'poller_id' => 1);

function cacti_sizeof($value) { return is_array($value) ? count($value) : 0; }
function cacti_count($value) { return is_array($value) ? count($value) : 0; }
function getHostTemplates() { return array(1 => 'Generic'); }
function db_switch_remote_to_main() {}
function db_fetch_row_prepared($sql, $params = array()) {
	$host = json_decode(getenv('CD_HOST'), true);
	return $host['id'] == $params[0] ? $host : array();
}
function db_fetch_cell_prepared($sql, $params = array()) { return $params[0]; }
function is_error_message() { return false; }
function kill_session_var($name) {}
function get_cacti_cli_version() { return '1.2.x'; }
function api_device_save() {
	file_put_contents(getenv('CD_SAVED'), json_encode(func_get_args()));
	return func_get_arg(0);
}
define('COPYRIGHT_YEARS', '2004-2026');
define('PING_ICMP', 1);
define('PING_TCP', 2);
define('PING_UDP', 3);
STUB);

	return $tree;
}

/**
 * Build a stored SNMPv3 authPriv device row.
 *
 * @param array $changes Column values replacing the defaults.
 *
 * @return array The device row.
 */
function change_device_cli_row(array $changes = array()) {
	return array_merge(array(
		'id' => 5, 'host_template_id' => 1, 'description' => 'core-router', 'hostname' => '192.0.2.10',
		'snmp_community' => '', 'snmp_version' => 3, 'snmp_username' => 'monitor', 'snmp_password' => 'authSecret1',
		'snmp_auth_protocol' => 'SHA', 'snmp_priv_protocol' => 'AES128', 'snmp_priv_passphrase' => 'privSecret1',
		'snmp_context' => '', 'snmp_engine_id' => '', 'snmp_port' => 161, 'snmp_timeout' => 500,
		'availability_method' => 2, 'ping_method' => 1, 'ping_port' => 23, 'ping_timeout' => 400, 'ping_retries' => 1,
		'max_oids' => 10, 'device_threads' => 1, 'poller_id' => 1, 'site_id' => 0, 'external_id' => '',
		'location' => '', 'notes' => '', 'disabled' => '', 'bulk_walk_size' => -1,
	), $changes);
}

/**
 * Run the script and return its exit status, output and the recorded save arguments.
 *
 * @param array $args The command line arguments.
 * @param array $row  Column values replacing the stored device defaults.
 *
 * @return array The exit status, combined output, and the decoded api_device_save() arguments or null.
 */
function change_device_cli_run(array $args, array $row = array()) {
	$tree  = change_device_cli_tree();
	$saved = $tree . '/saved-' . bin2hex(random_bytes(4)) . '.json';
	$pipes = array();
	$proc  = proc_open(
		array_merge(array(PHP_BINARY, $tree . '/cli/change_device.php'), $args),
		array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w')),
		$pipes,
		$tree,
		array('CD_HOST' => json_encode(change_device_cli_row($row)), 'CD_SAVED' => $saved)
	);

	expect($proc)->not->toBeFalse();

	fclose($pipes[0]);

	$output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);

	fclose($pipes[1]);
	fclose($pipes[2]);

	$status = proc_close($proc);
	$args   = is_file($saved) ? json_decode(file_get_contents($saved), true) : null;

	if (is_file($saved)) {
		unlink($saved);
	}

	return array($status, $output, $args);
}

/**
 * Write a CSV file for the script's --file option.
 *
 * @param string $content The file content.
 *
 * @return string The absolute path of the file.
 */
function change_device_cli_csv($content) {
	$file = change_device_cli_tree() . '/devices-' . bin2hex(random_bytes(4)) . '.csv';

	file_put_contents($file, $content);

	return $file;
}

/* api_device_save() argument positions: 7 password, 17 auth proto, 18 priv passphrase, 19 priv proto */

test('--security-level=noAuthNoPriv clears the stored secrets and saves the device', function () {
	list($status, $out, $saved) = change_device_cli_run(array('--id=5', '--security-level=noAuthNoPriv'));

	expect($status)->toBe(0)
		->and($out)->toContain("SNMP Auth Protocol: 'SHA' -> '[None]'")
		->and($out)->toContain('Success')
		->and($out)->not->toContain('authSecret1')
		->and($out)->not->toContain('privSecret1')
		->and($saved[7])->toBe('')
		->and($saved[17])->toBe('[None]')
		->and($saved[18])->toBe('')
		->and($saved[19])->toBe('[None]');
});

test('--security-level=authNoPriv keeps the auth settings and drops privacy', function () {
	list($status, $out, $saved) = change_device_cli_run(array('--id=5', '--security-level=authNoPriv'));

	expect($status)->toBe(0)
		->and($saved[7])->toBe('authSecret1')
		->and($saved[17])->toBe('SHA')
		->and($saved[18])->toBe('')
		->and($saved[19])->toBe('[None]');
});

test('--security-level=authPriv with complete credentials changes nothing', function () {
	list($status, $out, $saved) = change_device_cli_run(array('--id=5', '--security-level=authPriv'));

	expect($status)->toBe(0)
		->and($out)->toContain('already has the requested settings; no changes are needed.')
		->and($out)->toContain('No devices require changes.')
		->and($saved)->toBeNull();
});

test('a level the device already has is a no-op', function () {
	$none = array('snmp_auth_protocol' => '[None]', 'snmp_password' => '', 'snmp_priv_protocol' => '[None]', 'snmp_priv_passphrase' => '');

	list($status, $out, $saved) = change_device_cli_run(array('--id=5', '--security-level=noAuthNoPriv'), $none);

	expect($status)->toBe(0)
		->and($out)->toContain('No devices require changes.')
		->and($saved)->toBeNull();
});

test('without the flag and without other options nothing is saved', function () {
	list($status, $out, $saved) = change_device_cli_run(array('--id=5', '--description=core-router'));

	expect($status)->toBe(0)
		->and($out)->toContain('No devices require changes.')
		->and($saved)->toBeNull();
});

test('without the flag an ordinary change keeps the stored security settings', function () {
	list($status, $out, $saved) = change_device_cli_run(array('--id=5', '--description=edge-router'));

	expect($status)->toBe(0)
		->and($saved[2])->toBe('edge-router')
		->and($saved[17])->toBe('SHA')
		->and($saved[18])->toBe('privSecret1')
		->and($saved[19])->toBe('AES128');
});

test('--security-level on a device below SNMP version 3 is rejected', function () {
	list($status, $out, $saved) = change_device_cli_run(array('--id=5', '--version=2', '--community=public', '--security-level=authNoPriv'));

	expect($status)->toBe(1)
		->and($out)->toContain('--security-level is only valid with SNMP version 3')
		->and($saved)->toBeNull();
});

test('an unknown security level is rejected before any device is loaded', function () {
	list($status, $out, $saved) = change_device_cli_run(array('--id=5', '--security-level=secure'));

	expect($status)->toBe(1)
		->and($out)->toContain('Invalid security level (secure)')
		->and($saved)->toBeNull();
});

test('--authproto=[None] is rejected while privacy stays configured', function () {
	list($status, $out, $saved) = change_device_cli_run(array('--id=5', '--authproto=[None]'));

	expect($status)->toBe(1)
		->and($out)->toContain('SNMP privacy requires authentication')
		->and($saved)->toBeNull();
});

test('--authproto=[None] with --privproto=[None] lowers the device to noAuthNoPriv', function () {
	list($status, $out, $saved) = change_device_cli_run(array('--id=5', '--authproto=[None]', '--privproto=[None]', '--password='));

	expect($status)->toBe(0)
		->and($saved[17])->toBe('[None]')
		->and($saved[19])->toBe('[None]');
});

test('a legacy device with no auth protocol gets an accurate message', function () {
	$legacy = array('snmp_auth_protocol' => '', 'snmp_password' => '', 'snmp_priv_protocol' => '[None]', 'snmp_priv_passphrase' => '');

	list($status, $out, $saved) = change_device_cli_run(array('--id=5', '--description=edge-router'), $legacy);

	expect($status)->toBe(1)
		->and($out)->toContain('No SNMP auth protocol is set for this device')
		->and($out)->not->toContain('with an auth protocol you must supply a password');
});

test('a CSV security-level column is mapped and applied per row', function () {
	$file = change_device_cli_csv("id,security-level\n5,authNoPriv\n");

	list($status, $out, $saved) = change_device_cli_run(array('--file=' . $file, '--force'));

	expect($status)->toBe(0)
		->and($saved[17])->toBe('SHA')
		->and($saved[18])->toBe('')
		->and($saved[19])->toBe('[None]');
});

test('a CSV row with an invalid security level or on a v2 device fails the run', function () {
	$bad = change_device_cli_csv("id,security-level\n5,secure\n");

	list($status, $out) = change_device_cli_run(array('--file=' . $bad, '--force'));

	expect($status)->toBe(1)
		->and($out)->toContain('Invalid security level (secure)');

	$v2 = change_device_cli_csv("id,security-level\n5,authNoPriv\n");

	list($status, $out, $saved) = change_device_cli_run(array('--file=' . $v2, '--force'), array('snmp_version' => 2));

	expect($status)->toBe(1)
		->and($out)->toContain('only valid with SNMP version 3')
		->and($saved)->toBeNull();
});

test('the help screen describes the real security level derivation', function () {
	list($status, $out) = change_device_cli_run(array('--help'));

	expect($status)->toBe(0)
		->and($out)->toContain('whatever the auth protocol')
		->and($out)->not->toContain('an auth protocol of [None] is noAuthNoPriv');
});
