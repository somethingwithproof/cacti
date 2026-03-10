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
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

function upgrade_to_1_2_32() : void {
	/* Convert poller_output from MEMORY to InnoDB.
	 *
	 * MEMORY tables are swapped to disk by the OS under memory pressure,
	 * negating the performance benefit while adding DDL overhead each poll
	 * cycle via the poller_refresh_output_table workaround.  InnoDB with
	 * the buffer pool delivers equivalent throughput without the instability.
	 */
	$engine = db_fetch_cell("SELECT ENGINE
		FROM information_schema.TABLES
		WHERE TABLE_SCHEMA = DATABASE()
		AND TABLE_NAME = 'poller_output'");

	if ($engine === 'MEMORY') {
		db_install_execute('ALTER TABLE poller_output ENGINE=InnoDB ROW_FORMAT=Dynamic');
	}

	// Remove the now-obsolete workaround setting.
	db_install_execute("DELETE FROM settings WHERE name = 'poller_refresh_output_table'");
}
