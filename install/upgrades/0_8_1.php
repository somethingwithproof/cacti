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

function upgrade_to_0_8_1() {
	db_install_add_column('user_log', array('name' => 'user_id', 'type' => 'mediumint(8)', 'NULL' => false, 'after' => 'username'));
	db_install_add_column('user_auth', array('name' => 'realm', 'type' => 'mediumint(8)', 'NULL' => false, 'after' => 'password'));

	db_install_execute("ALTER TABLE user_log change time time datetime not null;");

	db_install_drop_key('user_log', '', 'PRIMARY KEY');
	db_install_add_key('user_log', '', 'PRIMARY KEY', array('username', 'user_id', 'time'));

	db_install_execute("UPDATE user_auth set realm = 1 where full_name='ldap user';");

	$_src_results = db_install_fetch_assoc("select id, username from user_auth");
	$_src         = $_src_results['data'];

	if (cacti_sizeof($_src) > 0) {
		foreach ($_src as $item) {
			db_install_execute("UPDATE user_log
				SET user_id = ?
				WHERE username = ?",
				array($item["id"], $item["username"]));
		}
	}

	$host = db_fetch_row_prepared('SELECT *
		FROM host
		WHERE id = ?',
		[$host_id]);

	$aps  = cacti_snmp_walk($host['hostname'],
		$host['snmp_community'],
		'.1.3.6.1.4.1.14823.2.3.3.1.2.1.1.3',
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

	return ('count:' . count($aps) . PHP_EOL);
}
