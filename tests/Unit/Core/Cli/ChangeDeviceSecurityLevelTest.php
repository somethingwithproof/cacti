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

namespace ChangeDeviceSecurityLevelFixture;

/*
 * Behavioural tests for the --security-level handling in cli/change_device.php.
 * The unchanged production functions are sliced out of the script so the
 * option normalisation and validation really execute without a database.
 */

$root = dirname(__DIR__, 4);
require_once $root . '/tests/Helpers/SourceSlice.php';

/**
 * Stand-in for the core helper of the same name, which needs no database.
 *
 * @param mixed $value The value to count.
 *
 * @return int The element count, or 0 for a non-array.
 */
function cacti_sizeof($value) {
	return is_array($value) ? count($value) : 0;
}

/**
 * Stand-in for the core helper of the same name.
 *
 * @param mixed $value The value to count.
 *
 * @return int The element count, or 0 for a non-array.
 */
function cacti_count($value) {
	return is_array($value) ? count($value) : 0;
}

/**
 * Stand-in for the database cell lookup: every referenced poller exists.
 *
 * @param string $sql    The query, ignored.
 * @param array  $params The bound parameters; the first is echoed back as the found id.
 *
 * @return mixed The first parameter.
 */
function db_fetch_cell_prepared($sql, $params = array()) {
	return $params[0];
}

/**
 * Stand-in for the version helper that the help screen prints.
 *
 * @return string A fixed version.
 */
function get_cacti_cli_version() {
	return '1.2.x';
}

foreach (array(
	'load_and_validate_device', 'reference_id_exists', 'display_version', 'validate_device_field_values', 'is_unsigned_integer',
	'device_override_definitions', 'csv_column_map', 'convert_override_value',
	'device_has_changes', 'device_has_requested_changes', 'device_editable_fields', 'display_help',
) as $function) {
	cacti_fixture_source_function($root . '/cli/change_device.php', $function, __NAMESPACE__);
}

define(__NAMESPACE__ . '\PING_ICMP', 1);
define(__NAMESPACE__ . '\PING_TCP', 2);
define(__NAMESPACE__ . '\PING_UDP', 3);
define(__NAMESPACE__ . '\COPYRIGHT_YEARS', '2004-2026');

/**
 * Build a stored SNMPv3 device row.
 *
 * @param array $changes Column values replacing the authPriv defaults.
 *
 * @return array The device row.
 */
function change_device_row(array $changes = array()) {
	return array_merge(array(
		'id'                   => 5,
		'host_template_id'     => 1,
		'description'          => 'core-router',
		'hostname'             => '192.0.2.10',
		'snmp_community'       => '',
		'snmp_version'         => 3,
		'snmp_username'        => 'monitor',
		'snmp_password'        => 'authSecret1',
		'snmp_auth_protocol'   => 'SHA',
		'snmp_priv_protocol'   => 'AES128',
		'snmp_priv_passphrase' => 'privSecret1',
		'snmp_context'         => '',
		'snmp_engine_id'       => '',
		'snmp_port'            => 161,
		'snmp_timeout'         => 500,
		'availability_method'  => 2,
		'ping_method'          => 1,
		'ping_port'            => 23,
		'ping_timeout'         => 400,
		'ping_retries'         => 1,
		'max_oids'             => 10,
		'device_threads'       => 1,
		'poller_id'            => 1,
		'site_id'              => 0,
		'external_id'          => '',
		'location'             => '',
		'notes'                => '',
		'disabled'             => '',
		'bulk_walk_size'       => -1,
	), $changes);
}

/**
 * Run load_and_validate_device() against a stored row and capture its output.
 *
 * @param array $stored    Column values replacing the authPriv defaults of the stored device.
 * @param array $overrides The overrides as the option parser would build them.
 *
 * @return array The validated host (or false), and the text printed.
 */
function change_device_validate(array $stored, array $overrides) {
	ob_start();
	$host = load_and_validate_device(5, $overrides, array(1 => 'Generic'), change_device_row($stored));
	$out  = ob_get_clean();

	return array($host, $out);
}

test('noAuthNoPriv clears the password, priv passphrase and both protocols', function () {
	list($host, $out) = change_device_validate(array(), array('snmp_security_level' => 'noauthnopriv'));

	expect($host['snmp_auth_protocol'])->toBe('[None]')
		->and($host['snmp_password'])->toBe('')
		->and($host['snmp_priv_protocol'])->toBe('[None]')
		->and($host['snmp_priv_passphrase'])->toBe('')
		->and($host['snmp_username'])->toBe('monitor')
		->and(array_key_exists('snmp_security_level', $host))->toBeFalse()
		->and($out)->toBe('');
});

test('authNoPriv keeps the auth settings and clears only the privacy settings', function () {
	list($host, $out) = change_device_validate(array(), array('snmp_security_level' => 'authnopriv'));

	expect($host['snmp_auth_protocol'])->toBe('SHA')
		->and($host['snmp_password'])->toBe('authSecret1')
		->and($host['snmp_priv_protocol'])->toBe('[None]')
		->and($host['snmp_priv_passphrase'])->toBe('')
		->and($out)->toBe('');
});

test('authNoPriv is rejected without a usable auth protocol', function () {
	foreach (array('[None]', '') as $protocol) {
		list($host, $out) = change_device_validate(array('snmp_auth_protocol' => $protocol), array('snmp_security_level' => 'authnopriv'));

		expect($host)->toBeFalse()
			->and($out)->toContain('For SNMP security level authNoPriv, you must supply an auth protocol other than [None]');
	}
});

test('authPriv keeps a complete credential set untouched', function () {
	list($host, $out) = change_device_validate(array(), array('snmp_security_level' => 'authpriv'));

	expect($host['snmp_auth_protocol'])->toBe('SHA')
		->and($host['snmp_priv_protocol'])->toBe('AES128')
		->and($host['snmp_priv_passphrase'])->toBe('privSecret1')
		->and($out)->toBe('');
});

test('authPriv is rejected when any of auth protocol, priv protocol or passphrase is missing', function () {
	foreach (array(
		array('snmp_auth_protocol' => '[None]'),
		array('snmp_auth_protocol' => ''),
		array('snmp_priv_protocol' => '[None]'),
		array('snmp_priv_protocol' => ''),
		array('snmp_priv_passphrase' => ''),
	) as $stored) {
		list($host, $out) = change_device_validate($stored, array('snmp_security_level' => 'authpriv'));

		expect($host)->toBeFalse()
			->and($out)->toContain('For SNMP security level authPriv, you must supply an auth protocol, priv protocol and priv passphrase');
	}
});

test('--security-level is rejected for SNMP versions below 3 and for no other version', function () {
	foreach (array(0, 1, 2) as $version) {
		list($host, $out) = change_device_validate(array(), array('snmp_version' => $version, 'snmp_security_level' => 'authnopriv'));

		expect($host)->toBeFalse()
			->and($out)->toBe("ERROR: --security-level is only valid with SNMP version 3\n");
	}

	list($host) = change_device_validate(array('snmp_version' => 2), array('snmp_security_level' => 'noauthnopriv', 'snmp_version' => 3));

	expect($host)->not->toBeFalse();
});

test('without --security-level a version below 3 still ignores the SNMPv3 fields', function () {
	list($host, $out) = change_device_validate(array(), array('snmp_version' => 2, 'snmp_community' => 'public'));

	expect($host['snmp_auth_protocol'])->toBe('')
		->and($host['snmp_username'])->toBe('')
		->and($out)->toBe('');
});

test('an explicit [None] auth protocol is rejected while privacy is configured', function () {
	list($host, $out) = change_device_validate(array(), array('snmp_auth_protocol' => '[None]'));

	expect($host)->toBeFalse()
		->and($out)->toContain('SNMP privacy requires authentication')
		->and($out)->toContain('--security-level=noAuthNoPriv');

	list($host, $out) = change_device_validate(array(), array('snmp_auth_protocol' => ''));

	expect($host)->toBeFalse();
});

test('an explicit [None] auth protocol is accepted once privacy is removed or never configured', function () {
	list($host) = change_device_validate(array(), array('snmp_auth_protocol' => '[None]', 'snmp_priv_protocol' => '[None]'));

	expect($host)->not->toBeFalse();

	list($host) = change_device_validate(array(), array('snmp_auth_protocol' => '[None]', 'snmp_password' => '', 'snmp_priv_passphrase' => ''));

	expect($host)->not->toBeFalse();

	list($host) = change_device_validate(array(), array('snmp_auth_protocol' => '[None]', 'snmp_security_level' => 'noauthnopriv'));

	expect($host['snmp_priv_protocol'])->toBe('[None]')
		->and($host['snmp_priv_passphrase'])->toBe('');
});

test('a stored [None] auth protocol with leftover privacy does not block unrelated edits', function () {
	list($host, $out) = change_device_validate(array('snmp_auth_protocol' => '[None]', 'snmp_password' => ''), array('description' => 'renamed'));

	expect($host['description'])->toBe('renamed')
		->and($out)->toBe('');
});

test('a legacy empty auth protocol without a password names the missing protocol', function () {
	list($host, $out) = change_device_validate(array('snmp_auth_protocol' => '', 'snmp_password' => '', 'snmp_priv_protocol' => '[None]', 'snmp_priv_passphrase' => ''), array('description' => 'renamed'));

	expect($host)->toBeFalse()
		->and($out)->toContain('No SNMP auth protocol is set for this device')
		->and($out)->not->toContain('with an auth protocol you must supply a password');
});

test('a real auth protocol without a password still asks for the password', function () {
	list($host, $out) = change_device_validate(array(), array('snmp_password' => '', 'snmp_priv_protocol' => '[None]'));

	expect($host)->toBeFalse()
		->and($out)->toContain('When using snmpv3 with an auth protocol you must supply a password');
});

test('an SNMPv3 device without a username is rejected', function () {
	list($host, $out) = change_device_validate(array(), array('snmp_username' => ''));

	expect($host)->toBeFalse()
		->and($out)->toContain('you must supply an username');
});

test('the security level column maps to its override key and values are validated', function () {
	expect(csv_column_map()['security-level'])->toBe('snmp_security_level');

	foreach (array('noAuthNoPriv' => 'noauthnopriv', 'AUTHNOPRIV' => 'authnopriv', 'authPriv' => 'authpriv') as $given => $expected) {
		expect(convert_override_value('security-level', $given))->toBe($expected);
	}

	ob_start();
	$converted = convert_override_value('security-level', 'secure');
	$out       = ob_get_clean();

	expect($converted)->toBeFalse()
		->and($out)->toContain('Invalid security level (secure)');
});

test('every override definition lines up its arrow column', function () {
	$source = file_get_contents(dirname(__DIR__, 4) . '/cli/change_device.php');
	$start  = strpos($source, 'function device_override_definitions()');
	$block  = substr($source, $start, strpos($source, '}', $start) - $start);

	preg_match_all("/^\t\t'[a-z_-]+'\s*=> array/m", $block, $lines);

	expect(count($lines[0]))->toBe(count(device_override_definitions()));

	$columns = array_unique(array_map(function ($line) {
		return strpos($line, '=>');
	}, $lines[0]));

	expect(count($columns))->toBe(1);
});

test('every numeric override accepts its bounds and rejects values just outside them', function () {
	/* column => [accepted, rejected] boundary values, taken from the documented ranges */
	$bounds = array(
		'template'     => array(array('0'), array('-1')),
		'version'      => array(array('0', '3'), array('4')),
		'site'         => array(array('0'), array('-1')),
		'poller'       => array(array('0'), array('-1')),
		'port'         => array(array('1', '65534'), array('0', '65535')),
		'timeout'      => array(array('1', '20000'), array('0', '20001')),
		'ping_timeout' => array(array('1'), array('0')),
		'threads'      => array(array('1', '10'), array('0', '11')),
		'ping_port'    => array(array('1', '65534'), array('0', '65535')),
		'ping_retries' => array(array('0', '5'), array('-1', 'x')),
		'max_oids'     => array(array('1', '60'), array('0', '61')),
		'bulk_walk'    => array(array('-1', '1', '60'), array('0', '61', '-2', '-1.5')),
	);

	foreach ($bounds as $column => list($accepted, $rejected)) {
		foreach ($accepted as $value) {
			ob_start();
			$converted = convert_override_value($column, $value);
			ob_end_clean();

			expect($converted)->toBe($value, "$column=$value must be accepted");
		}

		foreach ($rejected as $value) {
			ob_start();
			$converted = convert_override_value($column, (string) $value);
			$out       = ob_get_clean();

			expect($converted)->toBeFalse("$column=$value must be rejected")
				->and($out)->toContain("Invalid $column value ($value)");
		}
	}
});

test('a security level alone is not a requested change but can change the stored device', function () {
	$original = change_device_row();

	list($authpriv_to_none) = change_device_validate(array(), array('snmp_security_level' => 'noauthnopriv'));
	list($already_none)     = change_device_validate(
		array('snmp_auth_protocol' => '[None]', 'snmp_password' => '', 'snmp_priv_protocol' => '[None]', 'snmp_priv_passphrase' => ''),
		array('snmp_security_level' => 'noauthnopriv')
	);
	$none_row = change_device_row(array('snmp_auth_protocol' => '[None]', 'snmp_password' => '', 'snmp_priv_protocol' => '[None]', 'snmp_priv_passphrase' => ''));

	expect(device_has_requested_changes($original, array('snmp_security_level' => 'noauthnopriv')))->toBeFalse()
		->and(device_has_changes($original, $authpriv_to_none))->toBeTrue()
		->and(device_has_changes($none_row, $already_none))->toBeFalse()
		->and(device_has_requested_changes($original, array('snmp_username' => 'other')))->toBeTrue();
});

test('the help text describes the derivation that lib/snmp.php applies', function () {
	ob_start();
	display_help();
	$help = ob_get_clean();

	expect($help)->toContain('a priv protocol other than [None] and a priv passphrase are set (checked')
		->and($help)->toContain('first, whatever the auth protocol)')
		->and($help)->toContain('--authproto=[None] is')
		->and($help)->toContain('rejected while a priv protocol and passphrase are set')
		->and($help)->not->toContain('an auth protocol of [None] is noAuthNoPriv')
		->and($help)->toContain('--security-level=noAuthNoPriv clears the password');
});
