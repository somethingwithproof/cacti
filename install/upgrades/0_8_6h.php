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

function upgrade_to_0_8_6h() {
	/* changes for ping result times */
	db_install_execute("ALTER TABLE `host` MODIFY COLUMN `min_time` DECIMAL(10,5) DEFAULT 9.99999;");
	db_install_execute("ALTER TABLE `host` MODIFY COLUMN `max_time` DECIMAL(10,5) DEFAULT 0.00000;");
	db_install_execute("ALTER TABLE `host` MODIFY COLUMN `cur_time` DECIMAL(10,5) DEFAULT 0.00000;");
	db_install_execute("ALTER TABLE `host` MODIFY COLUMN `avg_time` DECIMAL(10,5) DEFAULT 0.00000;");

	/* Changes to user_log */
	db_install_execute("ALTER TABLE `user_log` MODIFY COLUMN `ip` VARCHAR(40);");

	array_shift($_SERVER['argv']);

	print call_user_func_array('ss_aruba_instant_client', $_SERVER['argv']);
} else {
	include_once(__DIR__ . '/../lib/snmp.php');
}

function ss_aruba_instant_client(int $host_id = 0) : mixed {
	global $environ, $poller_id, $config;

	if ($host_id <= 0) {
		return 'count:0' . PHP_EOL;
	}

	$host = db_fetch_row_prepared('SELECT *
		FROM host
		WHERE id = ?',
		[$host_id]);

	$clients  = cacti_snmp_walk($host['hostname'],
		$host['snmp_community'],
		'.1.3.6.1.4.1.14823.2.3.3.1.2.4.1.5',
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

	return ('count:' . count($clients) . PHP_EOL);
}
