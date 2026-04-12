#!/usr/bin/env php
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

require(__DIR__ . '/../include/cli_check.php');
<<<<<<< HEAD
require_once(__DIR__ . '/../lib/template.php');
require_once(__DIR__ . '/../lib/utility.php');
include_once(__DIR__ . '/../lib/data_query.php');
||||||| 7dd05ee12
require_once(__DIR__ . '/../lib/template.php');
=======
require_once(CACTI_PATH_LIBRARY . '/template.php');
require_once(CACTI_PATH_LIBRARY . '/utility.php');
include_once(CACTI_PATH_LIBRARY . '/data_query.php');
>>>>>>> origin/fix/jquery-deprecations

// process calling arguments
$parms = $_SERVER['argv'];
array_shift($parms);

global $total_errors, $total_repairs, $repaired_hosts;
global $local, $debug, $force, $rtables, $form, $dynamic, $base_tables;

$debug   = false;
$form    = '';
$force   = false;
$rtables = false;
$dynamic = false;
$local   = false;

$total_errors   = 0;
$total_repairs  = 0;
<<<<<<< HEAD
$repaired_hosts = array();
||||||| 7dd05ee12
=======
$repaired_hosts = [];
>>>>>>> origin/fix/jquery-deprecations

if (cacti_sizeof($parms)) {
<<<<<<< HEAD
	foreach($parms as $parameter) {
		if (strpos($parameter, '=')) {
			list($arg, $value) = explode('=', $parameter, 2);
||||||| 7dd05ee12
	foreach($parms as $parameter) {
		if (strpos($parameter, '=')) {
			list($arg, $value) = explode('=', $parameter);
=======
	foreach ($parms as $parameter) {
		if (str_contains($parameter, '=')) {
			[$arg, $value] = explode('=', $parameter, 2);
>>>>>>> origin/fix/jquery-deprecations
		} else {
			$arg   = $parameter;
			$value = '';
		}

		switch ($arg) {
			case '-d':
			case '--debug':
				$debug = true;

				break;
			case '--tables':
				$rtables = true;

				break;
			case '--force':
				$force = true;

				break;
			case '--dynamic':
				$dynamic = true;

				break;
			case '--local':
				$local = true;

				break;
			case '-form':
			case '--form':
				$form = ' USE_FRM';

				break;
			case '--version':
			case '-V':
			case '-v':
				display_version();

				exit(0);
			case '--help':
			case '-H':
			case '-h':
				display_help();

				exit(0);

			default:
				print 'ERROR: Invalid Parameter ' . $parameter . PHP_EOL . PHP_EOL;
				display_help();

				exit(1);
		}
	}
}

$base_tables = get_cacti_base_tables();

table_structural_repair();
simple_checks();
detailed_checks();
snmp_repairs();
snmp_index_repairs();

<<<<<<< HEAD
if (cacti_sizeof($repaired_hosts) && $total_repairs > 0) {
	print_separator(true);
	printf('NOTE: Pushing out %s Devices after repairs!' . PHP_EOL . PHP_EOL, cacti_sizeof($repaired_hosts));

	foreach($repaired_hosts as $host_id) {
		$h = db_fetch_row_prepared('SELECT description, hostname
			FROM host
			WHERE id = ?',
			array($host_id));

		printf('NOTE: Pushing out Device %s (%s) after repair!' . PHP_EOL, $h['description'], $h['hostname']);

		push_out_host($host_id);
	}
}
||||||| 7dd05ee12
	print "NOTE: Repairing Tables for Main Database" . PHP_EOL;
} else {
	print "NOTE: Repairing Tables for Local Database" . PHP_EOL;
}
=======
if (cacti_sizeof($repaired_hosts) && $total_repairs > 0) { // @phpstan-ignore booleanAnd.alwaysFalse
	print_separator(true);
>>>>>>> origin/fix/jquery-deprecations

<<<<<<< HEAD
print_separator(true);
||||||| 7dd05ee12
$tables = db_fetch_assoc('SHOW TABLES FROM ' . $database_default);
=======
	printf('NOTE: Pushing out %s Devices after repairs!' . PHP_EOL . PHP_EOL, cacti_sizeof($repaired_hosts));
>>>>>>> origin/fix/jquery-deprecations

<<<<<<< HEAD
if ($total_errors == 0 && $total_repairs == 0) {
	printf('NOTE: Found 0 Cacti database issues to repair.' . PHP_EOL . PHP_EOL);
} elseif (($total_errors > 0 || $total_repairs > 0) && !$force) {
	printf('WARNING: Found %s problems in your Cacti database and automatically repaired %s of them.' . PHP_EOL, $total_errors, $total_repairs);
	printf('WARNING: Using the \'--force\' option will either repair, remove or ignore any additional issues' . PHP_EOL);
	printf('WARNING: if they can not be repaired.' . PHP_EOL . PHP_EOL);
	printf('WARNING: Because these changes can not be reversed, make sure you make a Cacti backup first.' . PHP_EOL . PHP_EOL);
} else {
	printf('WARNING: Found %s and repaired %s Cacti database issues.' . PHP_EOL . PHP_EOL, $total_errors, $total_repairs);
}
||||||| 7dd05ee12
if ($rtables) {
	printf('NOTE: Repairing All %s Cacti Database Tables' . PHP_EOL, cacti_sizeof($tables));
=======
	foreach ($repaired_hosts as $host_id) { // @phpstan-ignore foreach.emptyArray
		$h = db_fetch_row_prepared('SELECT description, hostname
			FROM host
			WHERE id = ?',
			[$host_id]);
>>>>>>> origin/fix/jquery-deprecations

<<<<<<< HEAD
function table_structural_repair() {
	global $config, $local, $total_errors, $total_repairs;
	global $debug, $force, $rtables, $form, $dynamic, $base_tables, $database_default;
||||||| 7dd05ee12
	db_execute('UNLOCK TABLES');
=======
		if (cacti_sizeof($h)) {
			printf('NOTE: Pushing out Device %s (%s) after repair!' . PHP_EOL, $h['description'], $h['hostname']);
>>>>>>> origin/fix/jquery-deprecations

<<<<<<< HEAD
	print_separator();

	if ($rtables) {
		if (!$local && $config['poller_id'] > 1) {
			db_switch_remote_to_main();

			printf("NOTE: Repairing tables for main database" . PHP_EOL);
		} else {
			printf("NOTE: Repairing tables for local database" . PHP_EOL);
		}

		printf('NOTE: Repairing all %s Cacti base database tables' . PHP_EOL, cacti_sizeof($base_tables));

		db_execute('UNLOCK TABLES');

		if (cacti_sizeof($base_tables)) {
			foreach($base_tables AS $table) {
				printf("Repairing table '%s'", $table);
				$status = db_execute("REPAIR TABLE $table QUICK" . $form);
				printf(($status == 0 ? ' Failed' : ' Successful') . PHP_EOL);

				if ($dynamic && stripos($table['CREATE_OPTIONS'], 'dynamic') === false && $table['ENGINE'] != 'MEMORY') {
					printf("Changing table row format to Dynamic '%s'", $table);
					$status = db_execute("ALTER TABLE $table ROW_FORMAT=DYNAMIC");
					print ($status == 0 ? ' Failed' : ' Successful') . PHP_EOL;
				}
			}
||||||| 7dd05ee12
	$tables = db_fetch_assoc('SHOW TABLES FROM ' . $database_default);

	if (cacti_sizeof($tables)) {
		foreach($tables AS $table) {
			print "Repairing Table '" . $table['Tables_in_' . $database_default] . "'";
			$status = db_execute('REPAIR TABLE ' . $table['Tables_in_' . $database_default] . ' QUICK' . $form);
			print ($status == 0 ? ' Failed' : ' Successful') . PHP_EOL;

			if ($dynamic) {
				print "Changing Table Row Format to Dynamic '" . $table['Tables_in_' . $database_default] . "'";
				$status = db_execute('ALTER TABLE ' . $table['Tables_in_' . $database_default] . ' ROW_FORMAT=DYNAMIC');
				print ($status == 0 ? ' Failed' : ' Successful') . PHP_EOL;
			}
=======
			push_out_host($host_id);
>>>>>>> origin/fix/jquery-deprecations
		}
	} else {
		printf('NOTE: Skipping table physical repair' . PHP_EOL);
		printf('NOTE: %s Cacti base tables would be checked/repaired if using --tables option.' . PHP_EOL, cacti_sizeof($base_tables));
	}
}

<<<<<<< HEAD
function simple_checks() {
	global $total_errors, $total_repairs;
||||||| 7dd05ee12
print PHP_EOL . '------------------------------------------------------------------------' . PHP_EOL;
print 'Simple Checks.  Automatically repair if Found' . PHP_EOL . PHP_EOL;
=======
print_separator(true);
>>>>>>> origin/fix/jquery-deprecations

<<<<<<< HEAD
	print_separator(true);
	printf('Simple Checks.  Automatically repair if Found' . PHP_EOL . PHP_EOL);

	printf('NOTE: Repairing potential issues with Data Query ids and indexes.' . PHP_EOL);

	db_execute('UPDATE graph_local AS gl
		INNER JOIN graph_templates_item AS gti
		ON gti.local_graph_id = gl.id
		INNER JOIN data_template_rrd AS dtr
		ON gti.task_item_id = dtr.id
		INNER JOIN data_local AS dl
		ON dl.id = dtr.local_data_id
		SET gl.snmp_query_id = dl.snmp_query_id, gl.snmp_index = dl.snmp_index
		WHERE gl.graph_template_id IN (SELECT graph_template_id FROM snmp_query_graph)
		AND (gl.snmp_query_id != dl.snmp_query_id OR gl.snmp_index != dl.snmp_index)
		AND gl.snmp_query_id = 0');
||||||| 7dd05ee12
print 'NOTE: Repairing some possibly corrupted Data Query IDs and Indexes.' . PHP_EOL;

db_execute('UPDATE graph_local AS gl
	INNER JOIN graph_templates_item AS gti
	ON gti.local_graph_id = gl.id
	INNER JOIN data_template_rrd AS dtr
	ON gti.task_item_id = dtr.id
	INNER JOIN data_local AS dl
	ON dl.id = dtr.local_data_id
	SET gl.snmp_query_id = dl.snmp_query_id, gl.snmp_index = dl.snmp_index
	WHERE gl.graph_template_id IN (SELECT graph_template_id FROM snmp_query_graph)
	AND (gl.snmp_query_id != dl.snmp_query_id OR gl.snmp_index != dl.snmp_index)
	AND gl.snmp_query_id = 0');

$fixes = db_affected_rows();
if ($fixes) {
	printf('NOTE: Found and Repaired %s Problems with Data Query IDs and Indexes' . PHP_EOL, $fixes);
} else {
	print 'NOTE: Found No Problems with Data Query Indexes or IDs' . PHP_EOL;
}
=======
if ($total_errors == 0 && $total_repairs == 0) { // @phpstan-ignore booleanAnd.alwaysTrue
	printf('NOTE: Found 0 Cacti database issues to repair.' . PHP_EOL . PHP_EOL);
} elseif (!$force) {
	printf('WARNING: Found %s problems in your Cacti database and automatically repaired %s of them.' . PHP_EOL, $total_errors, $total_repairs);
	printf('WARNING: Using the \'--force\' option will either repair, remove or ignore any additional issues' . PHP_EOL);
	printf('WARNING: if they can not be repaired.' . PHP_EOL . PHP_EOL);
	printf('WARNING: Because these changes can not be reversed, make sure you make a Cacti backup first.' . PHP_EOL . PHP_EOL);
} else {
	printf('WARNING: Found %s and repaired %s Cacti database issues.' . PHP_EOL . PHP_EOL, $total_errors, $total_repairs);
}
>>>>>>> origin/fix/jquery-deprecations

<<<<<<< HEAD
	$fixes = db_affected_rows();
||||||| 7dd05ee12
print 'NOTE: Repairing Incorrectly Set Data Query Graph IDs' . PHP_EOL;
=======
function table_structural_repair() : void {
	global $local, $total_errors, $total_repairs;
	global $debug, $force, $rtables, $form, $dynamic, $base_tables, $database_default;
>>>>>>> origin/fix/jquery-deprecations

<<<<<<< HEAD
	$total_repairs += $fixes;
	$total_errors  += $fixes;
||||||| 7dd05ee12
db_execute("UPDATE graph_local AS gl
	INNER JOIN (
		SELECT DISTINCT local_graph_id, task_item_id
		FROM graph_templates_item
	) AS gti
	ON gl.id = gti.local_graph_id
	INNER JOIN data_template_rrd AS dtr
	ON gti.task_item_id = dtr.id
	INNER JOIN data_template_data AS dtd
	ON dtr.local_data_id = dtd.local_data_id
	INNER JOIN data_input_fields AS dif
	ON dif.data_input_id = dtd.data_input_id
	INNER JOIN data_input_data AS did
	ON did.data_template_data_id = dtd.id
	AND did.data_input_field_id = dif.id
	INNER JOIN snmp_query_graph_rrd AS sqgr
	ON sqgr.snmp_query_graph_id = did.value
	SET gl.snmp_query_graph_id = did.value
	WHERE input_output = 'in'
	AND type_code = 'output_type'
	AND gl.graph_template_id IN (SELECT graph_template_id FROM snmp_query_graph)
	AND gl.snmp_query_graph_id != CAST(did.value AS signed)");
=======
	print_separator();
>>>>>>> origin/fix/jquery-deprecations

<<<<<<< HEAD
	if ($fixes) {
		printf('NOTE: Found and repaired %s problems with the simple Data Query id and index check.' . PHP_EOL, $fixes);
	} else {
		printf('NOTE: Found 0 problems with the simple Data Query id and index check.' . PHP_EOL);
	}
||||||| 7dd05ee12
$fixes = db_affected_rows();
if ($fixes) {
	printf('NOTE: Found and Repaired %s Problems with Data Query Graph IDs' . PHP_EOL, $fixes);
} else {
	print 'NOTE: Found No Problems with Data Query Graph IDs' . PHP_EOL;
}
=======
	if ($rtables) {
		if (!$local) {
			if (POLLER_ID > 1) {
				db_switch_remote_to_main();

				printf('NOTE: Repairing tables for main database' . PHP_EOL);
			} else {
				printf('NOTE: Repairing tables for local database' . PHP_EOL);
			}
		} else {
			printf('NOTE: Repairing tables for local database' . PHP_EOL);
		}

		printf('NOTE: Repairing all %s Cacti base database tables' . PHP_EOL, cacti_sizeof($base_tables));

		db_execute('UNLOCK TABLES');

		if (cacti_sizeof($base_tables)) {
			foreach ($base_tables as $table) {
				printf("Repairing table '%s'", $table);
				$status = db_execute("REPAIR TABLE $table QUICK" . $form);
				printf(($status == 0 ? ' Failed' : ' Successful') . PHP_EOL);

				if ($dynamic && stripos($table['CREATE_OPTIONS'], 'dynamic') === false && $table['ENGINE'] != 'MEMORY') {
					printf("Changing table row format to Dynamic '%s'", $table);
					$status = db_execute("ALTER TABLE $table ROW_FORMAT=DYNAMIC");
					print ($status == 0 ? ' Failed' : ' Successful') . PHP_EOL;
				}
			}
		}
	} else {
		printf('NOTE: Skipping table physical repair' . PHP_EOL);
		printf('NOTE: %s Cacti base tables would be checked/repaired if using --tables option.' . PHP_EOL, cacti_sizeof($base_tables));
	}
}
>>>>>>> origin/fix/jquery-deprecations

<<<<<<< HEAD
	printf('NOTE: Repairing incorrectly set Data Query Graph ids - This can take a while.' . PHP_EOL);
||||||| 7dd05ee12
print 'NOTE: Repairing Data Input Data hostname or host_id Type Code issues' . PHP_EOL;
=======
function simple_checks() : void {
	global $total_errors, $total_repairs;
>>>>>>> origin/fix/jquery-deprecations

<<<<<<< HEAD
	db_execute("UPDATE graph_local AS gl
		INNER JOIN (
			SELECT DISTINCT local_graph_id, task_item_id
			FROM graph_templates_item
			WHERE local_graph_id > 0
			AND task_item_id > 0
			AND graph_template_id IN (SELECT DISTINCT graph_template_id FROM snmp_query_graph)
		) AS gti
		ON gl.id = gti.local_graph_id
		INNER JOIN data_template_rrd AS dtr
		ON gti.task_item_id = dtr.id
		INNER JOIN data_template_data AS dtd
		ON dtr.local_data_id = dtd.local_data_id
		INNER JOIN data_input_data AS did
		ON did.data_template_data_id = dtd.id
		INNER JOIN snmp_query_graph_rrd AS sqgr
		ON sqgr.snmp_query_graph_id = did.value
		SET gl.snmp_query_graph_id = did.value
		WHERE did.data_input_field_id IN(
			SELECT id
			FROM data_input_fields
			WHERE input_output = 'in'
			AND type_code = 'output_type'
		)
		AND gl.graph_template_id IN (SELECT DISTINCT graph_template_id FROM snmp_query_graph)
		AND gl.snmp_query_graph_id != CAST(did.value AS signed)");
||||||| 7dd05ee12
// Correct bad hostnames and host_id's in the data_input_data table
$entries = db_fetch_assoc("SELECT did.*, dif.type_code
	FROM data_input_data AS did
	INNER JOIN data_input_fields AS dif
	ON did.data_input_field_id = dif.id
	WHERE data_input_field_id in (
		SELECT id
		FROM data_input_fields
		WHERE type_code != ''
	)
	AND data_template_data_id IN (
		SELECT id
		FROM data_template_data
		WHERE local_data_id > 0
		AND data_template_id > 0
	)
	AND type_code in ('host_id', 'hostname')
	AND value = ''");
=======
	print_separator(true);
	printf('Simple Checks.  Automatically repair if Found' . PHP_EOL . PHP_EOL);
>>>>>>> origin/fix/jquery-deprecations

<<<<<<< HEAD
	$fixes = db_affected_rows();
||||||| 7dd05ee12
$fixes = 0;
=======
	printf('NOTE: Repairing potential issues with Data Query ids and indexes.' . PHP_EOL);
>>>>>>> origin/fix/jquery-deprecations

<<<<<<< HEAD
||||||| 7dd05ee12
if (cacti_sizeof($entries)) {
	foreach($entries as $e) {
		$data_template_data = db_fetch_row_prepared('SELECT *
=======
	db_execute('UPDATE graph_local AS gl
		INNER JOIN graph_templates_item AS gti
		ON gti.local_graph_id = gl.id
		INNER JOIN data_template_rrd AS dtr
		ON gti.task_item_id = dtr.id
		INNER JOIN data_local AS dl
		ON dl.id = dtr.local_data_id
		SET gl.snmp_query_id = dl.snmp_query_id, gl.snmp_index = dl.snmp_index
		WHERE gl.graph_template_id IN (SELECT graph_template_id FROM snmp_query_graph)
		AND (gl.snmp_query_id != dl.snmp_query_id OR gl.snmp_index != dl.snmp_index)
		AND gl.snmp_query_id = 0');

	$fixes = db_affected_rows();

	$total_repairs += $fixes;
	$total_errors  += $fixes;

	if ($fixes) {
		printf('NOTE: Found and repaired %s problems with the simple Data Query id and index check.' . PHP_EOL, $fixes);
	} else {
		printf('NOTE: Found 0 problems with the simple Data Query id and index check.' . PHP_EOL);
	}

	printf('NOTE: Repairing incorrectly set Data Query Graph ids - This can take a while.' . PHP_EOL);

	db_execute("UPDATE graph_local AS gl
		INNER JOIN (
			SELECT DISTINCT local_graph_id, task_item_id
			FROM graph_templates_item
			WHERE local_graph_id > 0
			AND task_item_id > 0
			AND graph_template_id IN (SELECT DISTINCT graph_template_id FROM snmp_query_graph)
		) AS gti
		ON gl.id = gti.local_graph_id
		INNER JOIN data_template_rrd AS dtr
		ON gti.task_item_id = dtr.id
		INNER JOIN data_template_data AS dtd
		ON dtr.local_data_id = dtd.local_data_id
		INNER JOIN data_input_data AS did
		ON did.data_template_data_id = dtd.id
		INNER JOIN snmp_query_graph_rrd AS sqgr
		ON sqgr.snmp_query_graph_id = did.value
		SET gl.snmp_query_graph_id = did.value
		WHERE did.data_input_field_id IN(
			SELECT id
			FROM data_input_fields
			WHERE input_output = 'in'
			AND type_code = 'output_type'
		)
		AND gl.graph_template_id IN (SELECT DISTINCT graph_template_id FROM snmp_query_graph)
		AND gl.snmp_query_graph_id != CAST(did.value AS signed)");

	$fixes = db_affected_rows();

>>>>>>> origin/fix/jquery-deprecations
	$total_repairs += $fixes;
	$total_errors  += $fixes;

	if ($fixes) {
		printf('NOTE: Found and repaired %s problems with Data Query Graph ids.' . PHP_EOL, $fixes);
	} else {
		printf('NOTE: Found 0 problems with Data Query Graph ids.' . PHP_EOL);
	}

	printf('NOTE: Repairing Data Input Data hostname or host_id Type Code issues.' . PHP_EOL);

	// Correct bad hostnames and host_id's in the data_input_data table
	$entries = db_fetch_assoc("SELECT did.*, dif.type_code
		FROM data_input_data AS did
		INNER JOIN data_input_fields AS dif
		ON did.data_input_field_id = dif.id
		WHERE data_input_field_id in (
			SELECT id
			FROM data_input_fields
			WHERE type_code != ''
		)
		AND data_template_data_id IN (
			SELECT id
			FROM data_template_data
			WHERE local_data_id > 0
			AND data_template_id > 0
		)
		AND type_code in ('host_id', 'hostname')
		AND value = ''");

	$fixes = 0;

	if (cacti_sizeof($entries)) {
<<<<<<< HEAD
		foreach($entries as $e) {
||||||| 7dd05ee12
		if (cacti_sizeof($data_template_data)) {
			$local_data = db_fetch_row_prepared('SELECT *
				FROM data_local
=======
		foreach ($entries as $e) {
>>>>>>> origin/fix/jquery-deprecations
			$data_template_data = db_fetch_row_prepared('SELECT *
				FROM data_template_data
				WHERE id = ?',
<<<<<<< HEAD
				array($e['data_template_data_id']));
||||||| 7dd05ee12
				array($data_template_data['local_data_id']));
=======
				[$e['data_template_data_id']]);
>>>>>>> origin/fix/jquery-deprecations

			if (cacti_sizeof($data_template_data)) {
				$local_data = db_fetch_row_prepared('SELECT *
					FROM data_local
					WHERE id = ?',
<<<<<<< HEAD
					array($data_template_data['local_data_id']));
||||||| 7dd05ee12
			if (cacti_sizeof($local_data)) {
				switch($e['type_code']) {
					case 'hostname':
						$hostname = db_fetch_cell_prepared('SELECT hostname
							FROM host
							WHERE id = ?',
							array($local_data['host_id']));
=======
					[$data_template_data['local_data_id']]);
>>>>>>> origin/fix/jquery-deprecations

				if (cacti_sizeof($local_data)) {
					switch($e['type_code']) {
						case 'hostname':
							$hostname = db_fetch_cell_prepared('SELECT hostname
								FROM host
								WHERE id = ?',
<<<<<<< HEAD
								array($local_data['host_id']));
||||||| 7dd05ee12
						db_execute_prepared('UPDATE data_input_data
							SET value = ?
							WHERE data_input_field_id = ?
							AND data_template_data_id = ?',
							array($hostname, $e['data_input_field_id'], $e['data_template_data_id']));
=======
								[$local_data['host_id']]);
>>>>>>> origin/fix/jquery-deprecations

							db_execute_prepared('UPDATE data_input_data
								SET value = ?
								WHERE data_input_field_id = ?
								AND data_template_data_id = ?',
<<<<<<< HEAD
								array($hostname, $e['data_input_field_id'], $e['data_template_data_id']));
||||||| 7dd05ee12
						$fixes++;
=======
								[$hostname, $e['data_input_field_id'], $e['data_template_data_id']]);
>>>>>>> origin/fix/jquery-deprecations

							$fixes++;

							break;
						case 'host_id':
							db_execute_prepared('UPDATE data_input_data
								SET value = ?
								WHERE data_input_field_id = ?
								AND data_template_data_id = ?',
<<<<<<< HEAD
								array($local_data['host_id'], $e['data_input_field_id'], $e['data_template_data_id']));
||||||| 7dd05ee12
						$fixes++;
=======
								[$local_data['host_id'], $e['data_input_field_id'], $e['data_template_data_id']]);
>>>>>>> origin/fix/jquery-deprecations

							$fixes++;

							break;
					}
				}
			}
		}
	}

	$total_repairs += $fixes;
	$total_errors  += $fixes;

	if ($fixes) {
		printf('NOTE: Found and repaired %s invalid Data Input hostname or host_id Type Code issues.' . PHP_EOL, $fixes);
	} else {
		printf('NOTE: Found 0 Data Input Data hostname or host_id Type Code issues.' . PHP_EOL);
	}

	printf('NOTE: Removing incomplete Data Sources.' . PHP_EOL);

	db_execute('DELETE dl
		FROM data_local AS dl
		LEFT JOIN data_template_data AS dtd
		ON dl.id = dtd.local_data_id
		WHERE dtd.local_data_id IS NULL');

	$fixes = db_affected_rows();

	$total_repairs += $fixes;
	$total_errors  += $fixes;

	if ($fixes) {
		printf('NOTE: Found and removed %s incomplete Data Sources.' . PHP_EOL, $fixes);
	} else {
		printf('NOTE: Found 0 incomplete Data Sources.' . PHP_EOL);
	}

	printf('NOTE: Repairing orphaned Poller Items.' . PHP_EOL);
<<<<<<< HEAD

	db_execute('DELETE pi
		FROM poller_item AS pi
		LEFT JOIN data_local AS dl
		ON pi.local_data_id = dl.id
		WHERE dl.id IS NULL');

	$fixes = db_affected_rows();

	$total_repairs += $fixes;
	$total_errors  += $fixes;

	if ($fixes) {
		printf('NOTE: Found and removed %s orphaned Poller Items.' . PHP_EOL, $fixes);
	} else {
		printf('NOTE: Found 0 problems with orphaned Poller Items.' . PHP_EOL);
	}
}
||||||| 7dd05ee12
	print 'NOTE: Found ' . ($force ? 'and Fixed ':'') . "$rows Invalid CDEF Rows in Graph Templates" . PHP_EOL;
} else {
	print 'NOTE: Found No Invalid Cacti CDEFs' . PHP_EOL;
}
=======
>>>>>>> origin/fix/jquery-deprecations

<<<<<<< HEAD
function detailed_checks() {
	global $force, $total_errors, $total_repairs;
||||||| 7dd05ee12
print 'NOTE: Searching for Invalid Cacti Data Inputs' . PHP_EOL;
=======
	db_execute('DELETE pi
		FROM poller_item AS pi
		LEFT JOIN data_local AS dl
		ON pi.local_data_id = dl.id
		WHERE dl.id IS NULL');
>>>>>>> origin/fix/jquery-deprecations

<<<<<<< HEAD
	print_separator(true);
	if (!$force) {
		printf('Detailed Basic Checks.  Use --force to repair if found.' . PHP_EOL . PHP_EOL);
	} else {
		printf('Detailed Basic Checks and Repairs.' . PHP_EOL . PHP_EOL);
||||||| 7dd05ee12
/* remove invalid Data Templates from the Database, validated */
$rows = db_fetch_cell('SELECT COUNT(*)
	FROM data_template_data
	LEFT JOIN data_input
	ON data_template_data.data_input_id=data_input.id
	WHERE data_input.id IS NULL');

$total_rows += $rows;
if ($rows > 0) {
	if ($force) {
		db_execute('DELETE FROM data_template_data
			WHERE data_input_id NOT IN (SELECT id FROM data_input)');
=======
	$fixes = db_affected_rows();

	$total_repairs += $fixes;
	$total_errors  += $fixes;

	if ($fixes) {
		printf('NOTE: Found and removed %s orphaned Poller Items.' . PHP_EOL, $fixes);
	} else {
		printf('NOTE: Found 0 problems with orphaned Poller Items.' . PHP_EOL);
>>>>>>> origin/fix/jquery-deprecations
	}

<<<<<<< HEAD
	printf('NOTE: Searching for invalid Cacti GPRINT Presets.' . PHP_EOL);
||||||| 7dd05ee12
	print 'NOTE: Found ' . ($force ? 'and Fixed ':'') . "$rows Invalid Data Input Rows in Data Templates" . PHP_EOL;
} else {
	print 'NOTE: Found No Invalid Cacti Data Inputs' . PHP_EOL;
}
=======
	if (db_column_exists('data_input_data', 'local_data_id')) {
		printf('NOTE: Deleting Invalid Data Input Data rows due to incorrect Data Input Field Mappings' . PHP_EOL);

		db_execute('DELETE did
			FROM data_input_data AS did
			INNER JOIN data_template_data AS dtd
			ON dtd.local_data_id = did.local_data_id
			AND did.data_template_data_id = dtd.id
			AND did.local_data_id > 0
			AND data_input_field_id NOT IN (SELECT id FROM data_input_fields WHERE data_input_id = dtd.data_input_id)');

		$fixes = db_affected_rows();

		$total_repairs += $fixes;
		$total_errors  += $fixes;

		if ($fixes) {
			printf('NOTE: Found and removed %s Invalid Data Input Data rows.' . PHP_EOL, $fixes);
		} else {
			printf('NOTE: Found 0 problems with Invalid Data Input Data rows.' . PHP_EOL);
		}
	}
}
>>>>>>> origin/fix/jquery-deprecations

<<<<<<< HEAD
	/* remove invalid GPrint Presets from the Database, validated */
||||||| 7dd05ee12
print 'NOTE: Searching for Graph Templates whose Graphs have invalid item counts' . PHP_EOL;

$rows = db_fetch_assoc('SELECT gt.graph_template_id, gt.items, gt1.graph_items, gt1.graphs
	FROM (
		SELECT graph_template_id, COUNT(*) AS items
=======
function detailed_checks() : void {
	global $force, $total_errors, $total_repairs;

	print_separator(true);

	if (!$force) {
		printf('Detailed Basic Checks.  Use --force to repair if found.' . PHP_EOL . PHP_EOL);
	} else {
		printf('Detailed Basic Checks and Repairs.' . PHP_EOL . PHP_EOL);
	}

	printf('NOTE: Searching for invalid Cacti GPRINT Presets.' . PHP_EOL);

	// remove invalid GPrint Presets from the Database, validated
>>>>>>> origin/fix/jquery-deprecations
	$rows = db_fetch_cell('SELECT COUNT(*)
		FROM graph_templates_item
		LEFT JOIN graph_templates_gprint
		ON graph_templates_item.gprint_id = graph_templates_gprint.id
		WHERE graph_templates_gprint.id IS NULL
		AND graph_templates_item.gprint_id > 0');

	$total_errors += $rows;

	if ($rows > 0) {
		if ($force) {
			db_execute('DELETE FROM graph_templates_item
				WHERE gprint_id NOT IN (SELECT id FROM graph_templates_gprint)
<<<<<<< HEAD
				AND gprint_id>0');

			$fixes = db_affected_rows();

			$total_repairs += $fixes;
		}

		printf('NOTE: Found ' . ($force ? 'and repaired ':'') . "$rows invalid GPRINT Preset rows in Graph Templates." . PHP_EOL);
	} else {
		printf('NOTE: Found 0 invalid Cacti GPRINT Presets.' . PHP_EOL);
	}

	printf('NOTE: Searching for invalid Cacti CDEFs.' . PHP_EOL);

	/* remove invalid CDEF Items from the Database, validated */
	$rows = db_fetch_cell('SELECT COUNT(*)
		FROM cdef_items
		LEFT JOIN cdef
		ON cdef_items.cdef_id=cdef.id
		WHERE cdef.id IS NULL');

	$total_errors += $rows;

	if ($rows > 0) {
		if ($force) {
			db_execute('DELETE FROM cdef_items
				WHERE cdef_id NOT IN (SELECT id FROM cdef)');

			$fixes = db_affected_rows();

			$total_repairs += $fixes;
		}

		printf('NOTE: Found ' . ($force ? 'and repaired ':'') . "$fixes of $rows invalid CDEFs in Graph Templates." . PHP_EOL);
	} else {
		printf('NOTE: Found 0 invalid Cacti CDEFs.' . PHP_EOL);
	}

	printf('NOTE: Searching for invalid Cacti Data Inputs.' . PHP_EOL);

	/* remove invalid Data Templates from the Database, validated */
	$rows = db_fetch_cell('SELECT COUNT(*)
		FROM data_template_data
		LEFT JOIN data_input
		ON data_template_data.data_input_id=data_input.id
		WHERE data_input.id IS NULL');

	$total_errors += $rows;

	if ($rows > 0) {
		if ($force) {
			db_execute('DELETE FROM data_template_data
				WHERE data_input_id NOT IN (SELECT id FROM data_input)');

			$fixes = db_affected_rows();

			$total_repairs += $fixes;
		}

		printf('NOTE: Found ' . ($force ? 'and repaired ':'') . "$fixes of $rows invalid Data Inputs in Data Templates." . PHP_EOL);
||||||| 7dd05ee12
		WHERE local_graph_id=0
		GROUP BY graph_template_id
	) AS gt
	INNER JOIN (
		SELECT graph_template_id, MAX(items) AS graph_items, COUNT(*) AS graphs
=======
				AND gprint_id > 0');

			$fixes = db_affected_rows();

			$total_repairs += $fixes;
		}

		printf('NOTE: Found ' . ($force ? 'and repaired ' : '') . "$rows invalid GPRINT Preset rows in Graph Templates." . PHP_EOL);
	} else {
		printf('NOTE: Found 0 invalid Cacti GPRINT Presets.' . PHP_EOL);
	}

	printf('NOTE: Searching for invalid Cacti CDEFs.' . PHP_EOL);

	// remove invalid CDEF Items from the Database, validated
	$rows = db_fetch_cell('SELECT COUNT(*)
		FROM cdef_items
		LEFT JOIN cdef
		ON cdef_items.cdef_id=cdef.id
		WHERE cdef.id IS NULL');

	$total_errors += $rows;

	if ($rows > 0) {
		if ($force) {
			db_execute('DELETE FROM cdef_items
				WHERE cdef_id NOT IN (SELECT id FROM cdef)');

			$fixes = db_affected_rows();

			$total_repairs += $fixes;
		} else {
			$fixes = 0;
		}

		printf('NOTE: Found ' . ($force ? 'and repaired ' : '') . "$fixes of $rows invalid CDEFs in Graph Templates." . PHP_EOL);
	} else {
		printf('NOTE: Found 0 invalid Cacti CDEFs.' . PHP_EOL);
	}

	printf('NOTE: Searching for invalid Cacti Data Inputs.' . PHP_EOL);

	// remove invalid Data Templates from the Database, validated
	$rows = db_fetch_cell('SELECT COUNT(*)
		FROM data_template_data
		LEFT JOIN data_input
		ON data_template_data.data_input_id=data_input.id
		WHERE data_input.id IS NULL');

	$total_errors += $rows;

	if ($rows > 0) {
		if ($force) {
			db_execute('DELETE FROM data_template_data
				WHERE data_input_id NOT IN (SELECT id FROM data_input)');

			$fixes = db_affected_rows();

			$total_repairs += $fixes;
		} else {
			$fixes = 0;
		}

		printf('NOTE: Found ' . ($force ? 'and repaired ' : '') . "$fixes of $rows invalid Data Inputs in Data Templates." . PHP_EOL);
>>>>>>> origin/fix/jquery-deprecations
	} else {
		printf('NOTE: Found 0 invalid Cacti Data Inputs.' . PHP_EOL);
	}

	printf('NOTE: Searching for Graph Templates whose Graphs have invalid item counts.' . PHP_EOL);

	$rows = db_fetch_assoc('SELECT gt.graph_template_id, gt.items, gt1.graph_items, gt1.graphs
		FROM (
			SELECT graph_template_id, COUNT(*) AS items
			FROM graph_templates_item
			WHERE local_graph_id=0
			GROUP BY graph_template_id
		) AS gt
		INNER JOIN (
			SELECT graph_template_id, MAX(items) AS graph_items, COUNT(*) AS graphs
			FROM (
				SELECT graph_template_id, COUNT(*) AS items
				FROM graph_templates_item
				WHERE local_graph_id > 0
				AND graph_template_id > 0
				GROUP BY local_graph_id
			) AS rs
			GROUP BY graph_template_id
		) AS gt1
		ON gt.graph_template_id = gt1.graph_template_id
		HAVING graph_items != items');

	$total_errors += cacti_sizeof($rows);

	if (cacti_sizeof($rows)) {
		$total_errors += cacti_sizeof($rows);
		$total_graphs = 0;

		if ($force) {
<<<<<<< HEAD
			foreach($rows as $row) {
				$name = db_fetch_cell_prepared('SELECT name
					FROM graph_templates
					WHERE id = ?',
					array($row['graph_template_id']));

				printf('NOTE: Re-Templating Graphs for Template: %s (%s)' . PHP_EOL, $name, $row['graph_template_id']);

				retemplate_graphs($row['graph_template_id'], 0, true);

				$total_graphs += $row['graphs'];
			}
		} else {
			foreach($rows as $row) {
				$total_graphs += $row['graphs'];
			}
		}

		printf('NOTE: Found ' . ($force ? 'and repaired ':'') . '%s Graphs from %s Graph Templates that had invalid item counts.' . PHP_EOL, $total_graphs, cacti_sizeof($rows));
	} else {
		printf('NOTE: Found 0 Graph Templates whose Graphs had incorrect item counts.' . PHP_EOL);
	}

	printf('NOTE: Searching for invalid Cacti Data Input fields.' . PHP_EOL);

	/* remove invalid Data Input fields from the Database, validated */
	$rows = db_fetch_cell('SELECT COUNT(*)
		FROM data_input_fields
		LEFT JOIN data_input
		ON data_input_fields.data_input_id=data_input.id
		WHERE data_input.id IS NULL');

	$total_errors += $rows;

	if ($rows > 0) {
		if ($force) {
			$total_repairs += $rows;

			db_execute('DELETE FROM data_input_fields
				WHERE data_input_fields.data_input_id NOT IN (SELECT id FROM data_input)');

			update_replication_crc(0, 'poller_replicate_data_input_fields_crc');
		}

		printf('NOTE: Found ' . ($force ? 'and repaired ':'') . "$rows invalid Data Input fields in Data Templates." . PHP_EOL);
	} else {
		printf('NOTE: Found 0 invalid Cacti Data Input fields.' . PHP_EOL);
	}

	printf('NOTE: Searching for invalid Cacti Data Input Data rows (Pass 1).' . PHP_EOL);

	/* remove invalid Data Input Data Rows from the Database in two passes */
	$rows = db_fetch_cell('SELECT COUNT(*)
		FROM data_input_data
		LEFT JOIN data_template_data
		ON data_input_data.data_template_data_id=data_template_data.id
		WHERE data_template_data.id IS NULL');

	$total_errors += $rows;

	if ($rows > 0) {
		if ($force) {
			$total_repairs += $rows;

			db_execute('DELETE FROM data_input_data
				WHERE data_input_data.data_template_data_id NOT IN (SELECT id FROM data_template_data)');
		}

		printf('NOTE: Found ' . ($force ? 'and repaired ':'') . "$rows invalid Data Input Data rows in Data Templates" . PHP_EOL);
	} else {
		printf('NOTE: Found 0 invalid Cacti Data Input Data rows (Pass 1).' . PHP_EOL);
	}

	printf('NOTE: Searching for invalid Cacti Data Input Data rows (Pass 2).' . PHP_EOL);

	$rows = db_fetch_cell('SELECT COUNT(*)
		FROM data_input_data
		LEFT JOIN data_input_fields
		ON data_input_fields.id=data_input_data.data_input_field_id
		WHERE data_input_fields.id IS NULL');

	$total_errors += $rows;

	if ($rows > 0) {
		if ($force) {
			$total_repairs += $rows;

			db_execute('DELETE FROM data_input_data
				WHERE data_input_data.data_input_field_id NOT IN (SELECT id FROM data_input_fields)');
		}

		printf('NOTE: Found ' . ($force ? 'and repaired ':'') . "$rows invalid Data Input Data rows based upon field mappings in Data Templates." . PHP_EOL);
	} else {
		printf('NOTE: Found 0 invalid Cacti Data Input Data rows (Pass 2).' . PHP_EOL);
	}
}

/**
 * There have been reports of data_input_data not including the correct information for
 * snmp columns.  This is likely caused by a legacy bug in Cacti where snmp information
 * was not properly copied to the data_input_data table upon change.
 *
 * Therefore, let's detect that bogus information for the snmp Data Input types only
 * for now.
 */
function snmp_repairs() {
	global $force, $total_errors, $total_repairs, $repaired_hosts;

	print_separator(true);
	if (!$force) {
		printf('Detailed SNMP Checks.  Use --force to repair if found.' . PHP_EOL . PHP_EOL);
	} else {
		printf('Detailed SNMP Checks and Repairs.' . PHP_EOL . PHP_EOL);
	}

	printf('NOTE: Searching for Devices with invalid SNMP information propagated to poller cache.' . PHP_EOL);

	$snmp_hashes = array(
		'3eb92bb845b9660a7445cf9740726522',
		'bf566c869ac6443b0c75d1c32b5a350e'
	);

	$data_names = array(
		'management_ip'        => 'hostname',
		'ip'                   => 'hostname',
		'hostname'             => 'hostname',
		'snmp_version'         => 'snmp_version',
		'snmp_community'       => 'snmp_community',
		'snmp_username'        => 'snmp_username',
		'snmp_password'        => 'snmp_password',
		'snmp_port'            => 'snmp_port',
		'snmp_auth_protocol'   => 'snmp_auth_protocol',
		'snmp_auth_passphrase' => 'snmp_auth_passphrase',
		'snmp_priv_protocol'   => 'snmp_priv_protocol',
		'snmp_priv_passphrase' => 'snmp_priv_passphrase'
	);

	$hosts = db_fetch_assoc('SELECT * FROM host ORDER BY id');

	$errors      = array();
	$snmp_errors = 0;

	if (cacti_sizeof($hosts)) {
		foreach($hosts as $h) {
			$errors[$h['id']] = 0;

			foreach($snmp_hashes as $data_input) {
				$fields = db_fetch_assoc_prepared('SELECT did.*, dif.name, dif.data_name
					FROM data_input_data AS did
					INNER JOIN data_input_fields AS dif
					ON did.data_input_field_id = dif.id
					INNER JOIN data_template_data AS dtd
					ON dtd.id = did.data_template_data_id
					INNER JOIN data_input AS di
					ON dtd.data_input_id = di.id
					INNER JOIN data_local AS dl
					ON dl.id = dtd.local_data_id
					WHERE dl.host_id = ?
					AND di.hash = ?',
					array($h['id'], $data_input));

				if (cacti_sizeof($fields)) {
					foreach($fields as $f) {
						if ($f['t_value'] == null || $f['t_value'] == '') {
							if (isset($data_names[$f['data_name']])) {
								$hcolumn = $data_names[$f['data_name']];

								if ($f['value'] != $h[$hcolumn]) {
									if ($force) {
										$total_repairs++;

										db_execute_prepared('UPDATE data_input_data
											SET value = ?
											WHERE data_template_data_id = ?
											AND data_input_field_id = ?',
											array($h[$hcolumn], $f['data_template_data_id'], $f['data_input_field_id']));
									}

									$errors[$h['id']]++;
									$snmp_errors++;
								}
							}
						}
					}
				}
			}

			if ($errors[$h['id']] > 0 && $force) {
				$repaired_hosts[$h['id']] = $h['id'];
			}
		}

		if (cacti_sizeof($errors)) {
			if ($force) {
				printf('NOTE: Found and repaired %s Device SNMP issues in %s Devices.' . PHP_EOL, $snmp_errors, cacti_sizeof($errors));
			} else {
				printf('NOTE: Not repairing %s Device SNMP issues in %s Devices.' . PHP_EOL, $snmp_errors, cacti_sizeof($errors));
			}
		} else {
			printf('NOTE: Found 0 Device SNMP issues in %s Devices.' . PHP_EOL, cacti_sizeof($errors));
		}
	}
}

function print_separator($nl = false) {
	print ($nl ? PHP_EOL:'') . str_repeat('-', 90) . PHP_EOL;
}

function snmp_index_repairs() {
	global $config, $force, $total_errors, $total_repairs, $repaired_hosts;

	print_separator(true);
	if (!$force) {
		printf('Detailed SNMP Index Checks.  Use --force to repair if found.' . PHP_EOL . PHP_EOL);
	} else {
		printf('Detailed SNMP Index Checks and Repairs.' . PHP_EOL . PHP_EOL);
	}

	printf('NOTE: Searching for Data Sources with damaged Data Query information' . PHP_EOL);
	printf('      using multiple techniques to attempt the repair.' . PHP_EOL);

	// Correct bad hostnames and host_id's in the data_input_data table
	$entries = db_fetch_assoc("SELECT did.*, dif.type_code
		FROM data_input_data AS did
		INNER JOIN data_input_fields AS dif
		ON did.data_input_field_id = dif.id
		WHERE data_input_field_id in (
			SELECT id
			FROM data_input_fields
			WHERE type_code != ''
		)
		AND data_template_data_id IN (
			SELECT id
			FROM data_template_data
			WHERE local_data_id > 0
			AND data_template_id > 0
			AND local_data_id IN (SELECT id FROM data_local)
		)
		AND type_code in ('host_id', 'hostname')
		AND value = ''");

	if (cacti_sizeof($entries)) {
		printf('NOTE: Found and repairing %s Data Sources with missing host information.' . PHP_EOL, cacti_sizeof($entries));

		$fixes = 0;

		foreach($entries as $e) {
			$data_template_data = db_fetch_row_prepared('SELECT *
				FROM data_template_data
				WHERE id = ?',
				array($e['data_template_data_id']));

			if (cacti_sizeof($data_template_data)) {
				$local_data = db_fetch_row_prepared('SELECT *
					FROM data_local
					WHERE id = ?',
					array($data_template_data['local_data_id']));

				if (cacti_sizeof($local_data)) {
					switch($e['type_code']) {
						case 'hostname':
							$hostname = db_fetch_cell_prepared('SELECT hostname
								FROM host
								WHERE id = ?',
								array($local_data['host_id']));

							db_execute_prepared('UPDATE data_input_data
								SET value = ?
								WHERE data_input_field_id = ?
								AND data_template_data_id = ?',
								array($hostname, $e['data_input_field_id'], $e['data_template_data_id']));

							$fixes++;

							$repaired_hosts[$local_data['host_id']] = $local_data['host_id'];

							break;
						case 'host_id':
							db_execute_prepared('UPDATE data_input_data
								SET value = ?
								WHERE data_input_field_id = ?
								AND data_template_data_id = ?',
								array($local_data['host_id'], $e['data_input_field_id'], $e['data_template_data_id']));

							$fixes++;

							$repaired_hosts[$local_data['host_id']] = $local_data['host_id'];

							break;
					}
				}
			}
		}

		printf('NOTE: Found and repaired %s of %s Data Sources entries with invalid Device information.' . PHP_EOL, $fixes, cacti_sizeof($entries));

		$total_errors  += $fixes;
		$total_repairs += $fixes;
	} else {
		printf('NOTE: Found 0 Data Sources with invalid Device information.' . PHP_EOL);
	}

	// Correct issues with non-checked data input columns that must be checked.
	printf('NOTE: Searching for and repairing all Data Query required checked Data Input columns.' . PHP_EOL, cacti_sizeof($entries));

	db_execute("UPDATE data_input_data
		SET t_value = 'on'
		WHERE data_input_field_id IN (
			SELECT id
			FROM data_input_fields
			WHERE type_code IN ('output_type', 'index_type', 'index_value')
		)
		AND t_value != 'on'
		AND data_template_data_id IN (
			SELECT id
			FROM data_template_data
			WHERE data_template_id > 0
		)");

	$fixes = db_affected_rows();

	printf('NOTE: Found %s and repaired Data Query rows missing the required checked columns.' . PHP_EOL, $fixes);

	$total_errors  += $fixes;
	$total_repairs += $fixes;

	// Correct missing host_id checkmark value in the data input data table
	printf('NOTE: Searching for and repairing all Data Query rows with invalid host_id attributes.' . PHP_EOL);

	// Host ID should not be checked, but should not be 'on' either
	db_execute("UPDATE data_input_data
		SET t_value = ''
		WHERE data_input_field_id IN (
			SELECT id
			FROM data_input_fields
			WHERE type_code IN ('host_id')
		)
		AND t_value != ''
		AND data_template_data_id IN (
			SELECT id
			FROM data_template_data
			WHERE data_template_id > 0
		)");

	$fixes = db_affected_rows();

	printf('NOTE: Found and repaired %s Data Query rows with invalid host_id attributes.' . PHP_EOL, $fixes);

	$total_errors  += $fixes;
	$total_repairs += $fixes;

	printf('NOTE: Searching for damaged Data Query indexes (Pass 1).' . PHP_EOL);

	// Repairing Data Input values using graph_local and data_local data if available
	$broken_data_rows = db_fetch_assoc("SELECT did.*
		FROM data_input_data AS did
		INNER JOIN data_template_data AS dtd
		ON dtd.id = did.data_template_data_id
		INNER JOIN data_local AS dl
		ON dl.id = dtd.local_data_id
		WHERE data_input_field_id IN (
			SELECT id
			FROM data_input_fields
			WHERE type_code = 'index_value'
		)
		AND value = ''
		AND dl.snmp_query_id > 0");

	$broken_local_data_ids = db_fetch_cell("SELECT COUNT(DISTINCT dtd.local_data_id)
        FROM data_input_data AS did
		INNER JOIN data_template_data AS dtd
		ON dtd.id = did.data_template_data_id
		INNER JOIN data_local AS dl
		ON dl.id = dtd.local_data_id
        WHERE data_input_field_id IN (
            SELECT id
            FROM data_input_fields
            WHERE type_code = 'index_value'
        )
        AND value = ''
		AND dl.snmp_query_id > 0");

	if (cacti_sizeof($broken_data_rows)) {
		$total_errors += cacti_sizeof($broken_data_rows);

		if ($force) {
			printf('NOTE: Attempting to repair %s Data Query indexes from other Cacti tables.' . PHP_EOL, $broken_local_data_ids);

			$fixes = 0;

			foreach($broken_data_rows as $ds) {
				$data_template_data = db_fetch_row_prepared('SELECT *
					FROM data_template_data
					WHERE id = ?',
					array($ds['data_template_data_id']));

				$field_data = db_fetch_row_prepared('SELECT *
					FROM data_input_fields
					WHERE id = ?',
					array($ds['data_input_field_id']));

				if (cacti_sizeof($data_template_data)) {
					$local_data_id = $data_template_data['local_data_id'];

					$local_data = db_fetch_row_prepared('SELECT *
						FROM data_local
						WHERE id = ?',
						array($local_data_id));

					if (cacti_sizeof($local_data)) {
						$local_graph_ids = db_fetch_assoc_prepared('SELECT DISTINCT local_graph_id
							FROM data_template_rrd AS dtr
							INNER JOIN graph_templates_item AS gti
							ON dtr.id = gti.task_item_id
							WHERE dtr.local_data_id = ?',
							array($local_data_id));

						if (cacti_sizeof($local_graph_ids)) {
							foreach($local_graph_ids as $id) {
								$local_graph = db_fetch_row_prepared('SELECT *
									FROM graph_local
									WHERE id = ?',
									array($id['local_graph_id']));

								switch($field_data['type_code']) {
									case 'index_type':
										$index_type = get_best_data_query_index_type($local_graph['host_id'], $local_graph['snmp_query_id']);

										if ($index_type != '') {
											db_execute_prepared('UPDATE data_input_data
												SET value = ?
												WHERE data_input_field_id = ?
												AND data_template_data_id = ?',
												array($index_type, $ds['data_input_field_id'], $ds['data_template_data_id']));
										}

										break;
									case 'index_value':
										if ($local_graph['snmp_index'] != '') {
											db_execute_prepared('UPDATE data_input_data
												SET value = ?
												WHERE data_input_field_id = ?
												AND data_template_data_id = ?',
												array($local_graph['snmp_index'], $ds['data_input_field_id'], $ds['data_template_data_id']));

											$repaired_hosts[$local_graph['host_id']] = $local_graph['host_id'];

											$fixes++;
										}

										break;
									case 'output_type_id':
										if ($local_graph['snmp_query_graph_id'] == 0) {
											$local_graph['snmp_query_graph_id'] = db_fetch_cell_prepared('SELECT id
												FROM snmp_query_graph
												WHERE graph_template_id = ?
												AND snmp_query_id = ?',
												array($local_graph['graph_template_id'], $local_graph['snmp_query_id']));
										}

										if ($local_graph['snmp_query_graph_id'] > 0) {
											db_execute_prepared('UPDATE data_input_data
												SET value = ?
												WHERE data_input_field_id = ?
												AND data_template_data_id = ?',
												array($local_graph['snmp_query_graph_id'], $ds['data_input_field_id'], $ds['data_template_data_id']));
||||||| 7dd05ee12
	if ($force) {
		foreach($rows as $row) {
			retemplate_graphs($row['graph_template_id'], 0, true);
			$total_graphs += $row['graphs'];
=======
			foreach ($rows as $row) {
				$name = db_fetch_cell_prepared('SELECT name
					FROM graph_templates
					WHERE id = ?',
					[$row['graph_template_id']]);

				printf('NOTE: Re-Templating Graphs for Template: %s (%s)' . PHP_EOL, $name, $row['graph_template_id']);

				retemplate_graphs($row['graph_template_id'], 0, true);

				$total_graphs += $row['graphs'];
			}
		} else {
			foreach ($rows as $row) {
				$total_graphs += $row['graphs'];
			}
		}

		printf('NOTE: Found ' . ($force ? 'and repaired ' : '') . '%s Graphs from %s Graph Templates that had invalid item counts.' . PHP_EOL, $total_graphs, cacti_sizeof($rows));
	} else {
		printf('NOTE: Found 0 Graph Templates whose Graphs had incorrect item counts.' . PHP_EOL);
	}

	printf('NOTE: Searching for invalid Cacti Data Input fields.' . PHP_EOL);

	// remove invalid Data Input fields from the Database, validated
	$rows = db_fetch_cell('SELECT COUNT(*)
		FROM data_input_fields
		LEFT JOIN data_input
		ON data_input_fields.data_input_id=data_input.id
		WHERE data_input.id IS NULL');

	$total_errors += $rows;

	if ($rows > 0) {
		if ($force) {
			$total_repairs += $rows;

			db_execute('DELETE FROM data_input_fields
				WHERE data_input_fields.data_input_id NOT IN (SELECT id FROM data_input)');

			update_replication_crc(0, 'poller_replicate_data_input_fields_crc');
		}

		printf('NOTE: Found ' . ($force ? 'and repaired ' : '') . "$rows invalid Data Input fields in Data Templates." . PHP_EOL);
	} else {
		printf('NOTE: Found 0 invalid Cacti Data Input fields.' . PHP_EOL);
	}

	printf('NOTE: Searching for invalid Cacti Data Input Data rows (Pass 1).' . PHP_EOL);

	// remove invalid Data Input Data Rows from the Database in two passes
	$rows = db_fetch_cell('SELECT COUNT(*)
		FROM data_input_data
		LEFT JOIN data_template_data
		ON data_input_data.data_template_data_id=data_template_data.id
		WHERE data_template_data.id IS NULL');

	$total_errors += $rows;

	if ($rows > 0) {
		if ($force) {
			$total_repairs += $rows;

			db_execute('DELETE FROM data_input_data
				WHERE data_input_data.data_template_data_id NOT IN (SELECT id FROM data_template_data)');
		}

		printf('NOTE: Found ' . ($force ? 'and repaired ' : '') . "$rows invalid Data Input Data rows in Data Templates" . PHP_EOL);
	} else {
		printf('NOTE: Found 0 invalid Cacti Data Input Data rows (Pass 1).' . PHP_EOL);
	}

	printf('NOTE: Searching for invalid Cacti Data Input Data rows (Pass 2).' . PHP_EOL);

	$rows = db_fetch_cell('SELECT COUNT(*)
		FROM data_input_data
		LEFT JOIN data_input_fields
		ON data_input_fields.id=data_input_data.data_input_field_id
		WHERE data_input_fields.id IS NULL');

	$total_errors += $rows;

	if ($rows > 0) {
		if ($force) {
			$total_repairs += $rows;

			db_execute('DELETE FROM data_input_data
				WHERE data_input_data.data_input_field_id NOT IN (SELECT id FROM data_input_fields)');
		}

		printf('NOTE: Found ' . ($force ? 'and repaired ' : '') . "$rows invalid Data Input Data rows based upon field mappings in Data Templates." . PHP_EOL);
	} else {
		printf('NOTE: Found 0 invalid Cacti Data Input Data rows (Pass 2).' . PHP_EOL);
	}
}

/**
 * There have been reports of data_input_data not including the correct information for
 * snmp columns.  This is likely caused by a legacy bug in Cacti where snmp information
 * was not properly copied to the data_input_data table upon change.
 *
 * Therefore, let's detect that bogus information for the snmp Data Input types only
 * for now.
 */
function snmp_repairs() : void {
	global $force, $total_errors, $total_repairs, $repaired_hosts;

	print_separator(true);

	if (!$force) {
		printf('Detailed SNMP Checks.  Use --force to repair if found.' . PHP_EOL . PHP_EOL);
	} else {
		printf('Detailed SNMP Checks and Repairs.' . PHP_EOL . PHP_EOL);
	}

	printf('NOTE: Searching for Devices with invalid SNMP information propagated to poller cache.' . PHP_EOL);

	$snmp_hashes = [
		'3eb92bb845b9660a7445cf9740726522',
		'bf566c869ac6443b0c75d1c32b5a350e'
	];

	$data_names = [
		'management_ip'        => 'hostname',
		'ip'                   => 'hostname',
		'hostname'             => 'hostname',
		'snmp_version'         => 'snmp_version',
		'snmp_community'       => 'snmp_community',
		'snmp_username'        => 'snmp_username',
		'snmp_password'        => 'snmp_password',
		'snmp_port'            => 'snmp_port',
		'snmp_auth_protocol'   => 'snmp_auth_protocol',
		'snmp_auth_passphrase' => 'snmp_auth_passphrase',
		'snmp_priv_protocol'   => 'snmp_priv_protocol',
		'snmp_priv_passphrase' => 'snmp_priv_passphrase'
	];

	$hosts = db_fetch_assoc('SELECT * FROM host ORDER BY id');

	$errors      = [];
	$snmp_errors = 0;

	if (cacti_sizeof($hosts)) {
		foreach ($hosts as $h) {
			$errors[$h['id']] = 0;

			foreach ($snmp_hashes as $data_input) {
				$fields = db_fetch_assoc_prepared('SELECT did.*, dif.name, dif.data_name
					FROM data_input_data AS did
					INNER JOIN data_input_fields AS dif
					ON did.data_input_field_id = dif.id
					INNER JOIN data_template_data AS dtd
					ON dtd.id = did.data_template_data_id
					INNER JOIN data_input AS di
					ON dtd.data_input_id = di.id
					INNER JOIN data_local AS dl
					ON dl.id = dtd.local_data_id
					WHERE dl.host_id = ?
					AND di.hash = ?',
					[$h['id'], $data_input]);

				if (cacti_sizeof($fields)) {
					foreach ($fields as $f) {
						if ($f['t_value'] == null || $f['t_value'] == '') {
							if (isset($data_names[$f['data_name']])) {
								$hcolumn = $data_names[$f['data_name']];

								if ($f['value'] != $h[$hcolumn]) {
									if ($force) {
										$total_repairs++;

										db_execute_prepared('UPDATE data_input_data
											SET value = ?
											WHERE data_template_data_id = ?
											AND data_input_field_id = ?',
											[$h[$hcolumn], $f['data_template_data_id'], $f['data_input_field_id']]);
									}

									$errors[$h['id']]++;
									$snmp_errors++;
								}
							}
						}
					}
				}
			}

			if ($errors[$h['id']] > 0 && $force) {
				$repaired_hosts[$h['id']] = $h['id'];
			}
		}

		if (cacti_sizeof($errors)) {
			if ($force) {
				printf('NOTE: Found and repaired %s Device SNMP issues in %s Devices.' . PHP_EOL, $snmp_errors, cacti_sizeof($errors));
			} else {
				printf('NOTE: Not repairing %s Device SNMP issues in %s Devices.' . PHP_EOL, $snmp_errors, cacti_sizeof($errors));
			}
		} else {
			printf('NOTE: Found 0 Device SNMP issues in %s Devices.' . PHP_EOL, cacti_sizeof($errors));
		}
	}
}

function print_separator(bool $nl = false) : void {
	print ($nl ? PHP_EOL : '') . str_repeat('-', 90) . PHP_EOL;
}

function snmp_index_repairs() : void {
	global $config, $force, $total_errors, $total_repairs, $repaired_hosts;

	print_separator(true);

	if (!$force) {
		printf('Detailed SNMP Index Checks.  Use --force to repair if found.' . PHP_EOL . PHP_EOL);
	} else {
		printf('Detailed SNMP Index Checks and Repairs.' . PHP_EOL . PHP_EOL);
	}

	printf('NOTE: Searching for Data Sources with damaged Data Query information' . PHP_EOL);
	printf('      using multiple techniques to attempt the repair.' . PHP_EOL);

	// Correct bad hostnames and host_id's in the data_input_data table
	$entries = db_fetch_assoc("SELECT did.*, dif.type_code
		FROM data_input_data AS did
		INNER JOIN data_input_fields AS dif
		ON did.data_input_field_id = dif.id
		WHERE data_input_field_id in (
			SELECT id
			FROM data_input_fields
			WHERE type_code != ''
		)
		AND data_template_data_id IN (
			SELECT id
			FROM data_template_data
			WHERE local_data_id > 0
			AND data_template_id > 0
			AND local_data_id IN (SELECT id FROM data_local)
		)
		AND type_code in ('host_id', 'hostname')
		AND value = ''");

	if (cacti_sizeof($entries)) {
		printf('NOTE: Found and repairing %s Data Sources with missing host information.' . PHP_EOL, cacti_sizeof($entries));

		$fixes = 0;

		foreach ($entries as $e) {
			$data_template_data = db_fetch_row_prepared('SELECT *
				FROM data_template_data
				WHERE id = ?',
				[$e['data_template_data_id']]);

			if (cacti_sizeof($data_template_data)) {
				$local_data = db_fetch_row_prepared('SELECT *
					FROM data_local
					WHERE id = ?',
					[$data_template_data['local_data_id']]);

				if (cacti_sizeof($local_data)) {
					switch($e['type_code']) {
						case 'hostname':
							$hostname = db_fetch_cell_prepared('SELECT hostname
								FROM host
								WHERE id = ?',
								[$local_data['host_id']]);

							db_execute_prepared('UPDATE data_input_data
								SET value = ?
								WHERE data_input_field_id = ?
								AND data_template_data_id = ?',
								[$hostname, $e['data_input_field_id'], $e['data_template_data_id']]);

							$fixes++;

							$repaired_hosts[$local_data['host_id']] = $local_data['host_id'];

							break;
						case 'host_id':
							db_execute_prepared('UPDATE data_input_data
								SET value = ?
								WHERE data_input_field_id = ?
								AND data_template_data_id = ?',
								[$local_data['host_id'], $e['data_input_field_id'], $e['data_template_data_id']]);

							$fixes++;

							$repaired_hosts[$local_data['host_id']] = $local_data['host_id'];

							break;
					}
				}
			}
		}

		printf('NOTE: Found and repaired %s of %s Data Sources entries with invalid Device information.' . PHP_EOL, $fixes, cacti_sizeof($entries));

		$total_errors  += $fixes;
		$total_repairs += $fixes;
	} else {
		printf('NOTE: Found 0 Data Sources with invalid Device information.' . PHP_EOL);
	}

	// Correct issues with non-checked data input columns that must be checked.
	printf('NOTE: Searching for and repairing all Data Query required checked Data Input columns.' . PHP_EOL);

	db_execute("UPDATE data_input_data
		SET t_value = 'on'
		WHERE data_input_field_id IN (
			SELECT id
			FROM data_input_fields
			WHERE type_code IN ('output_type', 'index_type', 'index_value')
		)
		AND t_value != 'on'
		AND data_template_data_id IN (
			SELECT id
			FROM data_template_data
			WHERE data_template_id > 0
		)");

	$fixes = db_affected_rows();

	printf('NOTE: Found %s and repaired Data Query rows missing the required checked columns.' . PHP_EOL, $fixes);

	$total_errors  += $fixes;
	$total_repairs += $fixes;

	// Correct missing host_id checkmark value in the data input data table
	printf('NOTE: Searching for and repairing all Data Query rows with invalid host_id attributes.' . PHP_EOL);

	// Host ID should not be checked, but should not be 'on' either
	db_execute("UPDATE data_input_data
		SET t_value = ''
		WHERE data_input_field_id IN (
			SELECT id
			FROM data_input_fields
			WHERE type_code IN ('host_id')
		)
		AND t_value != ''
		AND data_template_data_id IN (
			SELECT id
			FROM data_template_data
			WHERE data_template_id > 0
		)");

	$fixes = db_affected_rows();

	printf('NOTE: Found and repaired %s Data Query rows with invalid host_id attributes.' . PHP_EOL, $fixes);

	$total_errors  += $fixes;
	$total_repairs += $fixes;

	printf('NOTE: Searching for damaged Data Query indexes (Pass 1).' . PHP_EOL);

	// Repairing Data Input values using graph_local and data_local data if available
	$broken_data_rows = db_fetch_assoc("SELECT did.*
		FROM data_input_data AS did
		INNER JOIN data_template_data AS dtd
		ON dtd.id = did.data_template_data_id
		INNER JOIN data_local AS dl
		ON dl.id = dtd.local_data_id
		WHERE data_input_field_id IN (
			SELECT id
			FROM data_input_fields
			WHERE type_code = 'index_value'
		)
		AND value = ''
		AND dl.snmp_query_id > 0");

	$broken_local_data_ids = db_fetch_cell("SELECT COUNT(DISTINCT dtd.local_data_id)
        FROM data_input_data AS did
		INNER JOIN data_template_data AS dtd
		ON dtd.id = did.data_template_data_id
		INNER JOIN data_local AS dl
		ON dl.id = dtd.local_data_id
        WHERE data_input_field_id IN (
            SELECT id
            FROM data_input_fields
            WHERE type_code = 'index_value'
        )
        AND value = ''
		AND dl.snmp_query_id > 0");

	if (cacti_sizeof($broken_data_rows)) {
		$total_errors += cacti_sizeof($broken_data_rows);

		if ($force) {
			printf('NOTE: Attempting to repair %s Data Query indexes from other Cacti tables.' . PHP_EOL, $broken_local_data_ids);

			$fixes = 0;

			foreach ($broken_data_rows as $ds) {
				$data_template_data = db_fetch_row_prepared('SELECT *
					FROM data_template_data
					WHERE id = ?',
					[$ds['data_template_data_id']]);

				$field_data = db_fetch_row_prepared('SELECT *
					FROM data_input_fields
					WHERE id = ?',
					[$ds['data_input_field_id']]);

				if (cacti_sizeof($data_template_data)) {
					$local_data_id = $data_template_data['local_data_id'];

					$local_data = db_fetch_row_prepared('SELECT *
						FROM data_local
						WHERE id = ?',
						[$local_data_id]);

					if (cacti_sizeof($local_data)) {
						$local_graph_ids = db_fetch_assoc_prepared('SELECT DISTINCT local_graph_id
							FROM data_template_rrd AS dtr
							INNER JOIN graph_templates_item AS gti
							ON dtr.id = gti.task_item_id
							WHERE dtr.local_data_id = ?',
							[$local_data_id]);

						if (cacti_sizeof($local_graph_ids)) {
							foreach ($local_graph_ids as $id) {
								$local_graph = db_fetch_row_prepared('SELECT *
									FROM graph_local
									WHERE id = ?',
									[$id['local_graph_id']]);

								switch($field_data['type_code']) {
									case 'index_type':
										$index_type = get_best_data_query_index_type($local_graph['host_id'], $local_graph['snmp_query_id']);

										if ($index_type != '') {
											db_execute_prepared('UPDATE data_input_data
												SET value = ?
												WHERE data_input_field_id = ?
												AND data_template_data_id = ?',
												[$index_type, $ds['data_input_field_id'], $ds['data_template_data_id']]);
										}

										break;
									case 'index_value':
										if ($local_graph['snmp_index'] != '') {
											db_execute_prepared('UPDATE data_input_data
												SET value = ?
												WHERE data_input_field_id = ?
												AND data_template_data_id = ?',
												[$local_graph['snmp_index'], $ds['data_input_field_id'], $ds['data_template_data_id']]);

											$repaired_hosts[$local_graph['host_id']] = $local_graph['host_id'];

											$fixes++;
										}

										break;
									case 'output_type_id':
										if ($local_graph['snmp_query_graph_id'] == 0) {
											$local_graph['snmp_query_graph_id'] = db_fetch_cell_prepared('SELECT id
												FROM snmp_query_graph
												WHERE graph_template_id = ?
												AND snmp_query_id = ?',
												[$local_graph['graph_template_id'], $local_graph['snmp_query_id']]);
										}

										if ($local_graph['snmp_query_graph_id'] > 0) {
											db_execute_prepared('UPDATE data_input_data
												SET value = ?
												WHERE data_input_field_id = ?
												AND data_template_data_id = ?',
												[$local_graph['snmp_query_graph_id'], $ds['data_input_field_id'], $ds['data_template_data_id']]);
>>>>>>> origin/fix/jquery-deprecations
										}

										break;
								}
							}
						}
					}
				}
			}

			$total_repairs += $fixes;

			printf('NOTE: Found and repaired %s of %s Data Query Index entries in (Pass 1).' . PHP_EOL, $fixes, cacti_sizeof($broken_data_rows));
		} else {
			printf('NOTE: Skipping attempt to repair %s Data Query indexes in (Pass 1).' . PHP_EOL, cacti_sizeof($broken_data_rows));
		}
	} else {
<<<<<<< HEAD
		printf('NOTE: Found 0 damaged Data Query indexes in (Pass 1).' . PHP_EOL, cacti_sizeof($broken_data_rows));
	}

	printf('NOTE: Searching for damaged Data Query indexes (Pass 2).' . PHP_EOL);

	// Repairing Broken Data Query indexes by Graph Name
	$hosts = db_fetch_assoc("SELECT DISTINCT host_id
		FROM data_local
		WHERE snmp_query_id > 0
		AND snmp_index = ''");

	$reindexes = array();

	$reindex_ds_cnt = 0;
	$nomatch_cnt    = 0;

	if (cacti_sizeof($hosts)) {
		printf('NOTE: Found %s Devices with damaged Data Query indexes in (Pass 2).' . PHP_EOL, cacti_sizeof($hosts));

		if ($force) {
			printf('NOTE: Attempting to repair Data Query indexes from Data Source titles.' . PHP_EOL);

			$match_cnt  = 0;
			$misses_cnt = 0;
			$check_cnt  = 0;

			foreach($hosts as $h) {
				$data_query_ids = array_rekey(
					db_fetch_assoc_prepared('SELECT DISTINCT snmp_query_id
						FROM data_local
						WHERE host_id = ?
						AND snmp_index = ""',
						array($h['host_id'])),
					'snmp_query_id', 'snmp_query_id'
				);

				foreach($data_query_ids as $dqid) {
					$local_data_ids = db_fetch_assoc_prepared('SELECT *
						FROM data_local
						WHERE snmp_query_id = ?
						AND snmp_index = ""
						AND host_id = ?',
						array($dqid, $h['host_id']));

					foreach($local_data_ids as $ldi) {
						$matches = db_fetch_assoc_prepared('SELECT DISTINCT dl.id, dl.data_template_id, dl.host_id,
							hsc.snmp_query_id, hsc.snmp_index, dtd.name_cache, dtd.id AS data_template_data_id, field_value
							FROM host_snmp_cache AS hsc
							INNER JOIN data_local AS dl
							ON hsc.host_id = dl.host_id
							AND hsc.snmp_query_id = dl.snmp_query_id
							INNER JOIN data_template_data AS dtd
							ON dl.id = dtd.local_data_id
							WHERE (
								dtd.name_cache LIKE CONCAT("% ", field_value, " %")
								OR dtd.name_cache LIKE CONCAT("% ", field_value)
							)
							AND dl.id = ?
							AND dl.host_id = ?
							AND dl.snmp_query_id = ?
							AND field_value NOT LIKE "%\%T"
							AND field_value NOT IN ("_", "%", "", "-")',
							array($ldi['id'], $h['host_id'], $dqid));

						$total_matches = cacti_sizeof($matches);

						$check_cnt++;

						if ($total_matches == 1) {
							$repaired_hosts[$h['host_id']] = $h['host_id'];

							$match_cnt++;

							db_execute_prepared('UPDATE data_local
								SET snmp_index = ?, orphan = 0
								WHERE id = ?',
								array($matches[0]['snmp_index'], $matches[0]['id']));

							db_execute('DELETE FROM user_auth_row_cache WHERE class IN ("graphs", "data_sources")');

							$graphs = db_fetch_assoc_prepared('SELECT DISTINCT gl.id
								FROM graph_local AS gl
								INNER JOIN graph_templates_item AS gti
								ON gl.id = gti.local_graph_id
								INNER JOIN data_template_rrd AS dtr
								ON gti.task_item_id = dtr.id
								WHERE dtr.local_data_id = ?',
								array($matches[0]['id']));

							if (cacti_sizeof($graphs)) {
								foreach($graphs as $g) {
									db_execute_prepared('UPDATE graph_local
										SET snmp_index = ?
										WHERE id = ?',
										array($matches[0]['snmp_index'], $g['id']));
								}
							}

							db_execute_prepared('UPDATE data_input_data AS did
								INNER JOIN data_input_fields AS dif
								ON did.data_input_field_id = dif.id
								SET value = ?
								WHERE dif.type_code = "index_value"
								AND did.data_template_data_id = ?',
								array($matches[0]['snmp_index'], $matches[0]['data_template_data_id']));

							$fixes++;
						} elseif ($total_matches > 1) {
							$misses_cnt++;

							$reindexes[$matches[0]['snmp_query_id']][$matches[0]['host_id']] = true;
							$reindex_ds_cnt++;
						} else {
							$misses_cnt++;

							$nomatch_cnt++;
						}

						if ($check_cnt % 1000 == 0) {
							printf("NOTE: Checks Completed: %s, Matches: %s, Missed: %s" . PHP_EOL, $check_cnt, $match_cnt, $misses_cnt);
						}
					}
				}
			}

			$total_errors  += ($fixes + $misses_cnt);
			$total_repairs += $fixes;

			printf("NOTE: Checks Completed: %s, Matches: %s, Missed: %s" . PHP_EOL, $check_cnt, $match_cnt, $misses_cnt);

			if (cacti_sizeof($reindexes)) {
				printf('WARNING: Found multiple valid indexes for %s Data Sources!' . PHP_EOL, $reindex_ds_cnt);
				printf('         Found %s Data Sources that had no corresponding possible index.' . PHP_EOL, $nomatch_cnt);
				printf('         Suggest you reindex your Devices for the following' . PHP_EOL);
				printf('         Data Queries and then rerun this repair tool.' . PHP_EOL . PHP_EOL);
				printf('         Eg: ./poller_reindex_hosts --host-id=N --qid=N' . PHP_EOL . PHP_EOL);

				foreach($reindexes as $snmp_query_id => $hosts) {
					$name = db_fetch_cell_prepared('SELECT name
						FROM snmp_query
						WHERE id = ?',
						array($snmp_query_id));
||||||| 7dd05ee12
		foreach($rows as $row) {
			$total_graphs += $row['graphs'];
=======
		printf('NOTE: Found no damaged Data Query indexes in (Pass 1).' . PHP_EOL);
	}

	printf('NOTE: Searching for damaged Data Query indexes (Pass 2).' . PHP_EOL);

	// Repairing Broken Data Query indexes by Graph Name
	$hosts = db_fetch_assoc("SELECT DISTINCT host_id
		FROM data_local
		WHERE snmp_query_id > 0
		AND snmp_index = ''");

	$reindexes = [];

	$reindex_ds_cnt = 0;
	$nomatch_cnt    = 0;

	if (cacti_sizeof($hosts)) {
		printf('NOTE: Found %s Devices with damaged Data Query indexes in (Pass 2).' . PHP_EOL, cacti_sizeof($hosts));

		if ($force) {
			printf('NOTE: Attempting to repair Data Query indexes from Data Source titles.' . PHP_EOL);

			$match_cnt  = 0;
			$misses_cnt = 0;
			$check_cnt  = 0;

			foreach ($hosts as $h) {
				$data_query_ids = array_rekey(
					db_fetch_assoc_prepared('SELECT DISTINCT snmp_query_id
						FROM data_local
						WHERE host_id = ?
						AND snmp_index = ""',
						[$h['host_id']]),
					'snmp_query_id', 'snmp_query_id'
				);

				foreach ($data_query_ids as $dqid) {
					$local_data_ids = db_fetch_assoc_prepared('SELECT *
						FROM data_local
						WHERE snmp_query_id = ?
						AND snmp_index = ""
						AND host_id = ?',
						[$dqid, $h['host_id']]);

					foreach ($local_data_ids as $ldi) {
						$matches = db_fetch_assoc_prepared('SELECT DISTINCT dl.id, dl.data_template_id, dl.host_id,
							hsc.snmp_query_id, hsc.snmp_index, dtd.name_cache, dtd.id AS data_template_data_id, field_value
							FROM host_snmp_cache AS hsc
							INNER JOIN data_local AS dl
							ON hsc.host_id = dl.host_id
							AND hsc.snmp_query_id = dl.snmp_query_id
							INNER JOIN data_template_data AS dtd
							ON dl.id = dtd.local_data_id
							WHERE (
								dtd.name_cache LIKE CONCAT("% ", field_value, " %")
								OR dtd.name_cache LIKE CONCAT("% ", field_value)
							)
							AND dl.id = ?
							AND dl.host_id = ?
							AND dl.snmp_query_id = ?
							AND field_value NOT LIKE "%\%T"
							AND field_value NOT IN ("_", "%", "", "-")',
							[$ldi['id'], $h['host_id'], $dqid]);

						$total_matches = cacti_sizeof($matches);

						$check_cnt++;

						if ($total_matches == 1) {
							$repaired_hosts[$h['host_id']] = $h['host_id'];

							$match_cnt++;

							db_execute_prepared('UPDATE data_local
								SET snmp_index = ?, orphan = 0
								WHERE id = ?',
								[$matches[0]['snmp_index'], $matches[0]['id']]);

							db_execute('DELETE FROM user_auth_row_cache WHERE class IN ("graphs", "data_sources")');

							$graphs = db_fetch_assoc_prepared('SELECT DISTINCT gl.id
								FROM graph_local AS gl
								INNER JOIN graph_templates_item AS gti
								ON gl.id = gti.local_graph_id
								INNER JOIN data_template_rrd AS dtr
								ON gti.task_item_id = dtr.id
								WHERE dtr.local_data_id = ?',
								[$matches[0]['id']]);

							if (cacti_sizeof($graphs)) {
								foreach ($graphs as $g) {
									db_execute_prepared('UPDATE graph_local
										SET snmp_index = ?
										WHERE id = ?',
										[$matches[0]['snmp_index'], $g['id']]);
								}
							}

							db_execute_prepared('UPDATE data_input_data AS did
								INNER JOIN data_input_fields AS dif
								ON did.data_input_field_id = dif.id
								SET value = ?
								WHERE dif.type_code = "index_value"
								AND did.data_template_data_id = ?',
								[$matches[0]['snmp_index'], $matches[0]['data_template_data_id']]);

							$fixes++;
						} elseif ($total_matches > 1) {
							$misses_cnt++;

							$reindexes[$matches[0]['snmp_query_id']][$matches[0]['host_id']] = true;
							$reindex_ds_cnt++;
						} else {
							$misses_cnt++;

							$nomatch_cnt++;
						}

						if ($check_cnt % 1000 == 0) {
							printf('NOTE: Checks Completed: %s, Matches: %s, Missed: %s' . PHP_EOL, $check_cnt, $match_cnt, $misses_cnt);
						}
					}
				}
			}

			$total_errors  += ($fixes + $misses_cnt);
			$total_repairs += $fixes;

			printf('NOTE: Checks Completed: %s, Matches: %s, Missed: %s' . PHP_EOL, $check_cnt, $match_cnt, $misses_cnt);

			if (cacti_sizeof($reindexes)) {
				printf('WARNING: Found multiple valid indexes for %s Data Sources!' . PHP_EOL, $reindex_ds_cnt);
				printf('         Found %s Data Sources that had no corresponding possible index.' . PHP_EOL, $nomatch_cnt);
				printf('         Suggest you reindex your Devices for the following' . PHP_EOL);
				printf('         Data Queries and then rerun this repair tool.' . PHP_EOL . PHP_EOL);
				printf('         Eg: ./poller_reindex_hosts --host-id=N --qid=N' . PHP_EOL . PHP_EOL);

				foreach ($reindexes as $snmp_query_id => $hosts) {
					$name = db_fetch_cell_prepared('SELECT name
						FROM snmp_query
						WHERE id = ?',
						[$snmp_query_id]);
>>>>>>> origin/fix/jquery-deprecations

					$total_hosts = cacti_sizeof($hosts);

					printf('         Data Query: %s (%s) with %s Devices impacted.' . PHP_EOL, $name, $snmp_query_id, $total_hosts);
				}
			}
		} else {
			printf('NOTE: Skipping attempt to repair Data Query indexes in (Pass 2).' . PHP_EOL);
		}
	} else {
		printf('NOTE: Found 0 Devices with damaged Data Query indexes in (Pass 2).' . PHP_EOL);
	}

	printf('NOTE: Searching for orphaned Data Sources that are not orphaned.' . PHP_EOL);

	$local_data_ids = db_fetch_assoc('SELECT *
		FROM data_local
		WHERE snmp_index != ""
		AND snmp_query_id > 0
		AND orphan = 1');

	$total_errors += cacti_sizeof($local_data_ids);

	if (cacti_sizeof($local_data_ids)) {
		printf('NOTE: Found %s Devices with orphaned Data Sources that may not be orphaned.' . PHP_EOL, cacti_sizeof($local_data_ids));

		if ($force) {
			printf('NOTE: Attempting to confirm and repair Data Sources are in fact not orphaned.' . PHP_EOL);

			$fixes = 0;

<<<<<<< HEAD
			foreach($local_data_ids as $ldi) {
				$snmp_index = $ldi['snmp_index'];

				$found = db_fetch_cell_prepared('SELECT count(*)
					FROM host_snmp_cache
					WHERE host_id = ?
					AND snmp_query_id = ?
					AND snmp_index = ?',
					array($ldi['host_id'], $ldi['snmp_query_id'], $ldi['snmp_index']));

				if ($found) {
					$repaired_hosts[$ldi['host_id']] = $ldi['host_id'];

					db_execute_prepared('UPDATE data_local
						SET orphan = 0
						WHERE id = ?',
						array($ldi['id']));
||||||| 7dd05ee12
		update_replication_crc(0, 'poller_replicate_data_input_fields_crc');
=======
			foreach ($local_data_ids as $ldi) {
				$snmp_index = $ldi['snmp_index'];

				$found = db_fetch_cell_prepared('SELECT count(*)
					FROM host_snmp_cache
					WHERE host_id = ?
					AND snmp_query_id = ?
					AND snmp_index = ?',
					[$ldi['host_id'], $ldi['snmp_query_id'], $ldi['snmp_index']]);

				if ($found) {
					$repaired_hosts[$ldi['host_id']] = $ldi['host_id'];

					db_execute_prepared('UPDATE data_local
						SET orphan = 0
						WHERE id = ?',
						[$ldi['id']]);
>>>>>>> origin/fix/jquery-deprecations

					$fixes++;
				}
			}

			$total_repairs += $fixes;

			printf('NOTE: Found and repaired %s of %s Data Sources with invalid orphan status.' . PHP_EOL, $fixes, cacti_sizeof($local_data_ids));
		} else {
			printf('NOTE: Skipping attempt to confirm and repair Data Sources orphan status.' . PHP_EOL);
		}
	} else {
		printf('NOTE: Found 0 Devices with Data Sources with a potentially incorrect orphan status.' . PHP_EOL);
	}

	$broken_ds = db_fetch_cell("SELECT COUNT(*)
		FROM data_local
		WHERE snmp_query_id > 0
		AND snmp_index = ''");

	if ($broken_ds > 0) {
		print_separator(true);
		printf('WARNING: There remain %s Data Sources with invalid SNMP index information.' . PHP_EOL, $broken_ds);
<<<<<<< HEAD
		printf('         Go to Console > Management > Data Sources and filter for the'. PHP_EOL);
||||||| 7dd05ee12
/* --------------------------------------------------------------------*/

print 'NOTE: Searching for Invalid Cacti Data Input Data Rows (Pass 1)' . PHP_EOL;

/* remove invalid Data Input Data Rows from the Database in two passes */
$rows = db_fetch_cell('SELECT COUNT(*)
	FROM data_input_data
	LEFT JOIN data_template_data
	ON data_input_data.data_template_data_id=data_template_data.id
	WHERE data_template_data.id IS NULL');

$total_rows += $rows;
if ($rows > 0) {
	if ($force) {
		db_execute('DELETE FROM data_input_data
			WHERE data_input_data.data_template_data_id NOT IN (SELECT id FROM data_template_data)');
=======
		printf('         Go to Console > Management > Data Sources and filter for the' . PHP_EOL);
>>>>>>> origin/fix/jquery-deprecations
		printf('         Status of Bad Indexes and repair by hand.' . PHP_EOL);
	}
}

/**
 * display_version - displays version information
<<<<<<< HEAD
 */
function display_version() {
||||||| 7dd05ee12
print 'NOTE: Searching for Invalid Cacti Data Input Data Rows (Pass 2)' . PHP_EOL;

$rows = db_fetch_cell('SELECT COUNT(*)
	FROM data_input_data
	LEFT JOIN data_input_fields
	ON data_input_fields.id=data_input_data.data_input_field_id
	WHERE data_input_fields.id IS NULL');

$total_rows += $rows;
if ($rows > 0) {
	if ($force) {
		db_execute('DELETE FROM data_input_data
			WHERE data_input_data.data_input_field_id NOT IN (SELECT id FROM data_input_fields)');
	}

	print 'NOTE: Found ' . ($force ? 'and Fixed ':'') . "$rows Invalid Data Input Data Rows based upon field mappings in Data Templates" . PHP_EOL;
} else {
	print 'NOTE: Found No Invalid Cacti Data Input Data Rows (Pass 2)' . PHP_EOL;
}

print PHP_EOL . '------------------------------------------------------------------------' . PHP_EOL;

if ($total_rows > 0 && !$force) {
	print 'WARNING: Cacti Template Problems found in your Database.  Using the \'--force\' option will remove' . PHP_EOL;
	print 'the invalid records.  However, these changes can be catastrophic to existing data sources.  Therefore, you' . PHP_EOL;
	print 'should contact your support organization prior to proceeding with that repair.' . PHP_EOL . PHP_EOL;
} elseif ($total_rows == 0) {
	print 'NOTE: No Invalid Cacti Template Records found in your Database' . PHP_EOL . PHP_EOL;
}

/*  display_version - displays version information */
function display_version() {
=======
 *
 * @return void
 */
function display_version() : void {
>>>>>>> origin/fix/jquery-deprecations
	$version = get_cacti_cli_version();
	print "Cacti Database Repair Utility, Version $version, " . COPYRIGHT_YEARS . PHP_EOL;
}

/**
 * display_help - displays the usage of the function
<<<<<<< HEAD
 */
function display_help () {
||||||| 7dd05ee12
/*	display_help - displays the usage of the function */
function display_help () {
=======
 *
 * @return void
 */
function display_help() : void {
>>>>>>> origin/fix/jquery-deprecations
	display_version();

	print PHP_EOL;
	print 'usage: repair_database.php [--dynamic] [--debug] [--force] [--form]' . PHP_EOL . PHP_EOL;
	print 'A utility designed to repair the Cacti database if damaged, and optionally repair any' . PHP_EOL;
	print 'corruption found in the Cacti databases various Templates.' . PHP_EOL . PHP_EOL;

	print 'Optional:' . PHP_EOL;
	print '    --dynamic - Convert a table to Dynamic row format if available' . PHP_EOL;
	print '    --form    - Force rebuilding the indexes from the database creation syntax.' . PHP_EOL;
	print '    --tables  - Repair Tables as well as possible database corruptions.' . PHP_EOL;
	print '    --local   - Perform the action on the Remote Data Collector if run from there' . PHP_EOL;
	print '    --force   - Remove Invalid Template records from the database.' . PHP_EOL;
	print '    --debug   - Display verbose output during execution.' . PHP_EOL . PHP_EOL;
}
