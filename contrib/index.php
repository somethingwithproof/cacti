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
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

<<<<<<< HEAD
<<<<<<<< HEAD:contrib/index.php
header("Location:../index.php");
|||||||| 7dd05ee12:boost_rrdupdate.php
require(__DIR__ . '/include/cli_check.php');

/* include important functions */
include_once($config['base_path'] . '/lib/poller.php');
include_once($config['base_path'] . '/lib/boost.php');

/* get the boost polling cycle */
$max_run_duration = read_config_option('boost_rrd_update_max_runtime');

/* process calling arguments, first remove the script name */
$parms = $_SERVER['argv'];
array_shift($parms);

/* second, get the socket integer value */
$socket_int_value = $parms[0];
array_shift($parms);

/* last, recombine the arguments */
$command = implode(' ', $parms);

/* execute the command */
if ($config['cacti_server_os'] == 'win32') {
	$handle = popen($command, 'rb');
} else {
	$handle = popen($command, 'r');
}

/* get the results */
$result = fread($handle, 1024);

if (trim($result) == '') {
	$result = 'OK';
} else {
	if (substr_count($result, "\r")) {
		$result = str_replace("\r", '', $result);
	}
	$result_array = explode("\n", $result);

	if (cacti_sizeof($result_array)) {
		$result = $result_array[cacti_sizeof($result_array)-2];
	} else {
		$result = 'ERROR: Detected unknown error';
	}
}

/* add the value to the table */
db_execute_prepared('INSERT INTO poller_output_boost_processes
	(sock_int_value, status)
	VALUES (?, ?)', array($socket_int_value, $result));

/* close the connection */
pclose($handle);

/* return the rrdupdate results */
return $result;

========
global $config;

if (!isset($called_by_script_server)) {
	include_once(__DIR__ . '/../include/cli_check.php');
	include_once(__DIR__ . '/../lib/snmp.php');

	array_shift($_SERVER['argv']);
	print call_user_func_array('ss_nimble_alletra_total', $_SERVER['argv']);
} else {
	include_once(__DIR__ . '/../lib/snmp.php');
}

function ss_nimble_alletra_total(int $host_id = 0) : mixed {
	global $environ, $poller_id, $config;

	if ($host_id <= 0) {
		return 'iowrites:0 iowritebytes:0 ioreads:0 ioreadbytes:0 ioreadhits:0' . PHP_EOL;
	}

	$host = db_fetch_row_prepared('SELECT *
		FROM host
		WHERE id = ?',
		[$host_id]);

	$oids = [
		'iowrites'     => '.1.3.6.1.4.1.37447.1.3.4.0',
		'iowritebytes' => '.1.3.6.1.4.1.37447.1.3.10.0',
		'ioreads'      => '.1.3.6.1.4.1.37447.1.3.2.0',
		'ioreadbytes'  => '.1.3.6.1.4.1.37447.1.3.8.0',
		'ioreadhits'   => '.1.3.6.1.4.1.37447.1.3.16.0'
	];

	$result = '';

	foreach ($oids as $name => $oid) {
		$x = cacti_snmp_get($host['hostname'],
			$host['snmp_community'],
			$oid,
			$host['snmp_version'],
			$host['snmp_username'],
			$host['snmp_password'],
			$host['snmp_auth_protocol'],
			$host['snmp_priv_passphrase'],
			$host['snmp_priv_protocol'],
			$host['snmp_context'],
			$host['snmp_port'],
			$host['snmp_timeout'],
			$host['snmp_retries'],
			SNMP_POLLER,
			$host['snmp_engine_id']);

		if (is_numeric($x)) {
			$result .= $name . ':' . $x . ' ';
		} else {
			$result .= $name . ':0 ';
		}
	}

	return $result;
}
>>>>>>>> origin/fix/jquery-deprecations:scripts/ss_nimble_alletra_total.php
||||||| 7dd05ee12
=======
header('Location:../index.php');
>>>>>>> origin/fix/jquery-deprecations
