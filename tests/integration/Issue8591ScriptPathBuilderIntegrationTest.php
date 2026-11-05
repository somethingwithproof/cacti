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
*/

/*
 * Integration coverage for issue #8591: substitute_script_path() mangled a
 * double-quoted template that holds a command substitution before a field
 * ("$(date)<arg1>" lost its closing parenthesis), and it refused templates
 * whose unsupported construct did not touch a field, while the callers
 * silently used the resulting empty string as the command.
 *
 * This runs the real get_full_script_path() and get_full_test_script_path()
 * from lib/functions.php against a sqlite-backed connection, so the shipped
 * queries, the substitution and the refusal handling run together.
 */

require_once dirname(__DIR__) . '/Helpers/FakeMySQLPDO.php';
require_once dirname(__DIR__, 2) . '/include/vendor/autoload.php';
require_once dirname(__DIR__, 2) . '/include/global_constants.php';
require_once dirname(__DIR__, 2) . '/lib/database.php';
require_once dirname(__DIR__, 2) . '/lib/functions.php';
require_once dirname(__DIR__) . '/Helpers/UnitStubs.php';

/* include/global.php decides these from the SAPI and the settings table, and
   pulling it in would need a live Cacti install. The log path only has to be
   somewhere this test can read back. */
if (!defined('CACTI_WEB')) {
	define('CACTI_WEB', false);
}

if (!defined('CACTI_PATH_LOG')) {
	define('CACTI_PATH_LOG', sys_get_temp_dir() . '/cacti-8591-' . getmypid());
}

if (!defined('CACTI_SERVER_OS')) {
	define('CACTI_SERVER_OS', 'unix');
}

if (!defined('CACTI_PATH_BASE')) {
	define('CACTI_PATH_BASE', dirname(__DIR__, 2));
}

if (!defined('CACTI_PATH_LIBRARY')) {
	define('CACTI_PATH_LIBRARY', dirname(__DIR__, 2) . '/lib');
}

if (!defined('CACTI_PATH_RRA')) {
	define('CACTI_PATH_RRA', dirname(__DIR__, 2) . '/rra');
}

require_once dirname(__DIR__, 2) . '/lib/utility.php';

if (!defined('POLLER_ID')) {
	define('POLLER_ID', 1);
}

/**
 * Seeds one data source (id 5, template 3) whose data input uses the template.
 *
 * @param string $input_string The data input method's command template.
 * @param string $arg_value    The stored value of the <arg1> input field.
 *
 * @return FakeMySQLPDO A connection holding the seeded schema.
 */
function script_path_seed(string $input_string, string $arg_value) : FakeMySQLPDO {
	$conn = new FakeMySQLPDO();

	$conn->exec('CREATE TABLE settings (name TEXT PRIMARY KEY, value TEXT)');
	$conn->exec("INSERT INTO settings (name, value) VALUES
		('log_destination', '1'), ('log_verbosity', '6'),
		('path_php_binary', '/usr/bin/php'), ('path_snmpget', '/usr/bin/snmpget')");
	$conn->exec('CREATE TABLE data_template_data (id INTEGER PRIMARY KEY, local_data_id INTEGER, data_template_id INTEGER, data_input_id INTEGER, name TEXT, data_source_path TEXT, active TEXT, rrd_step INTEGER)');
	$conn->exec('CREATE TABLE data_input (id INTEGER PRIMARY KEY, type_id INTEGER, input_string TEXT, active TEXT, name TEXT)');
	$conn->exec('CREATE TABLE data_input_fields (id INTEGER PRIMARY KEY, data_input_id INTEGER, data_name TEXT, type_code TEXT, input_output TEXT, update_rra TEXT)');
	$conn->exec('CREATE TABLE data_input_data (data_input_field_id INTEGER, data_template_data_id INTEGER, value TEXT)');
	$conn->exec('CREATE TABLE data_local (id INTEGER PRIMARY KEY, host_id INTEGER)');
	$conn->exec('CREATE TABLE host (id INTEGER PRIMARY KEY, poller_id INTEGER, hostname TEXT, snmp_community TEXT, snmp_version INTEGER,
		snmp_username TEXT, snmp_password TEXT, snmp_auth_protocol TEXT, snmp_priv_passphrase TEXT, snmp_priv_protocol TEXT,
		snmp_context TEXT, snmp_engine_id TEXT, snmp_port INTEGER, snmp_timeout INTEGER, snmp_retries INTEGER, disabled TEXT)');
	$conn->exec('CREATE TABLE data_template_rrd (id INTEGER PRIMARY KEY, local_data_id INTEGER, data_source_name TEXT)');

	// the data source (local_data_id 5) and its template row (local_data_id 0)
	$conn->exec('INSERT INTO data_template_data (id, local_data_id, data_template_id, data_input_id, name, data_source_path, active, rrd_step) VALUES (11, 5, 3, 7, \'ds\', \'ds_5.rrd\', \'on\', 300), (12, 0, 3, 7, \'ds\', \'\', \'on\', 300)');
	$conn->exec('INSERT INTO data_input (id, type_id, input_string, active, name) VALUES (7, ' . DATA_INPUT_TYPE_SCRIPT . ', ' . $conn->quote($input_string) . ', \'on\', \'script\')');
	$conn->exec("INSERT INTO data_input_fields (id, data_input_id, data_name, type_code, input_output, update_rra) VALUES (21, 7, 'arg1', '', 'in', ''), (22, 7, 'out', '', 'out', 'on')");
	$conn->exec('INSERT INTO data_input_data (data_input_field_id, data_template_data_id, value) VALUES (21, 11, ' . $conn->quote($arg_value) . '), (21, 12, ' . $conn->quote($arg_value) . ')');
	$conn->exec('INSERT INTO data_local (id, host_id) VALUES (5, 9)');
	$conn->exec("INSERT INTO host (id, poller_id, hostname, snmp_community, snmp_version, snmp_port, snmp_timeout, snmp_retries, disabled)
		VALUES (9, 1, 'host.example.net', 'public', 2, 161, 500, 3, '')");
	$conn->exec("INSERT INTO data_template_rrd (id, local_data_id, data_source_name) VALUES (31, 5, 'out')");

	return $conn;
}

/**
 * Points Cacti's database layer at a seeded connection.
 *
 * @param FakeMySQLPDO $conn The connection to install.
 *
 * @return void
 */
function script_path_install(FakeMySQLPDO $conn) : void {
	$GLOBALS['database_hostname']      = 'unittest';
	$GLOBALS['database_port']          = 0;
	$GLOBALS['database_default']       = 'unittest';
	$GLOBALS['database_total_queries'] = 0;
	$GLOBALS['database_sessions']      = ['unittest:0:unittest' => $conn];
}

/**
 * Runs a command through /bin/sh -n so only its syntax is checked.
 *
 * @param string $command The command line to parse.
 *
 * @return int The shell's exit status; 0 means the command parses.
 */
function script_path_syntax_status(string $command) : int {
	$pipes   = [];
	$process = proc_open(['/bin/sh', '-n', '-c', $command], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);

	if (!is_resource($process)) {
		return -1;
	}

	stream_get_contents($pipes[1]);
	stream_get_contents($pipes[2]);
	fclose($pipes[1]);
	fclose($pipes[2]);

	return proc_close($process);
}

beforeEach(function () {
	global $database_sessions, $database_hostname, $database_port, $database_default, $config;

	$this->db_globals = [$database_sessions, $database_hostname, $database_port, $database_default];
	$this->config     = $config ?? [];
	$this->session    = $_SESSION ?? [];

	/* read_config_option() memoises settings in $config (CLI) or $_SESSION (web).
	   An earlier test in the shared process may have cached a log destination or
	   verbosity that silences cacti_log(), so start from the defaults. */
	$config[OPTIONS_CLI]      = [];
	$_SESSION[OPTIONS_WEB]    = [];

	if (!is_dir(CACTI_PATH_LOG)) {
		mkdir(CACTI_PATH_LOG, 0700, true);
	}

	@unlink(CACTI_PATH_LOG . '/cacti.log');
});

/* Left in place, the fake handle answers every later read_config_option() in
   the run and throws on Cacti's MySQL SQL, aborting the suite. */
afterEach(function () {
	global $database_sessions, $database_hostname, $database_port, $database_default, $config;

	[$database_sessions, $database_hostname, $database_port, $database_default] = $this->db_globals;

	$config   = $this->config;
	$_SESSION = $this->session;

	@unlink(CACTI_PATH_LOG . '/cacti.log');
});

test('a command substitution before the field keeps the template balanced', function () {
	script_path_install(script_path_seed('echo "$(date)<arg1>"', 'x'));

	$command = get_full_script_path(5);

	// pre-fix this was echo "$(date'x' with the closing parenthesis dropped
	expect($command)->toBe('echo "$(date)"\'x\'""')
		->and(script_path_syntax_status($command))->toBe(0);
});

test('a backtick substitution before the field keeps the template balanced', function () {
	script_path_install(script_path_seed('echo "`echo hi`<arg1>"', 'x'));

	$command = get_full_script_path(5);

	expect($command)->toBe('echo "`echo hi`"\'x\'""')
		->and(script_path_syntax_status($command))->toBe(0);
});

test('a hostile stored value never reaches the shell as code', function () {
	$marker = CACTI_PATH_LOG . '/executed';

	script_path_install(script_path_seed('echo "$(date)<arg1>"', '$(touch ' . $marker . ')`touch ' . $marker . '`;touch ' . $marker));

	$command = get_full_script_path(5);

	shell_exec($command);

	expect($command)->not->toBe('')
		->and(file_exists($marker))->toBeFalse();
});

test('redirections and parameter expansions that do not touch a field are not refused', function () {
	script_path_install(script_path_seed('cmd $(probe 2>/dev/null) ${FOO:-x} $((1+2)) <arg1>', 'v'));

	$command = get_full_script_path(5);

	expect($command)->toBe("cmd $(probe 2>/dev/null) \${FOO:-x} \$((1+2)) 'v'")
		->and(script_path_syntax_status($command))->toBe(0);
});

test('a template the scanner cannot follow is refused, logged and reported as false', function () {
	script_path_install(script_path_seed("cat <<EOF\n<arg1>\nEOF", 'secret-value'));

	$command = get_full_script_path(5);

	$log = (string) @file_get_contents(CACTI_PATH_LOG . '/cacti.log');

	// false is the builders' documented error result; an empty command is not
	expect($command)->toBeFalse()
		->and($log)->toContain('Command for Data Source[5] (Data Input[7]) was refused')
		->and($log)->toContain('here-document')
		->and($log)->not->toContain('secret-value');
});

test('the test-command builder shares the fix and the refusal handling', function () {
	script_path_install(script_path_seed('echo "$(date)<arg1>"', 'x'));

	$command = get_full_test_script_path(3, 9);

	expect($command)->toBe('echo "$(date)"\'x\'""');

	script_path_install(script_path_seed("cat <<EOF\n<arg1>\nEOF", 'secret-value'));

	$refused = get_full_test_script_path(3, 9);
	$log     = (string) @file_get_contents(CACTI_PATH_LOG . '/cacti.log');

	expect($refused)->toBeFalse()
		->and($log)->toContain('Test command for Data Template[3] and Device[9] (Data Input[7]) was refused')
		->and($log)->not->toContain('secret-value');
});

test('an empty stored value is still a valid empty argument, not a refusal', function () {
	script_path_install(script_path_seed('echo <arg1> tail', ''));

	expect(get_full_script_path(5))->toBe("echo '' tail");
});

test('the poller cache gets the balanced command for an accepted template', function () {
	script_path_install(script_path_seed('echo "$(date)<arg1>"', 'x'));

	$items = update_poller_cache(['id' => 5, 'host_id' => 9, 'poller_id' => 1], false, 1);

	expect(count($items))->toBe(1)
		->and($items[0])->toContain(db_qstr('echo "$(date)"\'x\'""'));
});

test('the poller cache skips a refused template instead of caching an empty command', function () {
	script_path_install(script_path_seed("cat <<EOF\n<arg1>\nEOF", 'secret-value'));

	$items = update_poller_cache(['id' => 5, 'host_id' => 9, 'poller_id' => 1], false, 1);

	expect($items)->toBe([]);
});

test('the data source test stops on a refused template without running a command', function () {
	script_path_install(script_path_seed("cat <<EOF\n<arg1>\nEOF", 'secret-value'));

	$result = test_data_source(3, 9);
	$log    = (string) @file_get_contents(CACTI_PATH_LOG . '/cacti.log');

	expect($result)->toBeFalse()
		->and($log)->toContain('Test command for Data Template[3] and Device[9] (Data Input[7]) was refused');
});
