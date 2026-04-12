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

require(__DIR__ . '/include/cli_check.php');

// let's report all errors
error_reporting(E_ALL);

// allow the script to hang around.
set_time_limit(0);

chdir(__DIR__);

<<<<<<< HEAD
$last_time = time()-30;
$cache     = array();
||||||| 7dd05ee12
$last_time = time()-30;
=======
$last_time = time() - 30;
$cache     = [];
>>>>>>> origin/fix/jquery-deprecations

<<<<<<< HEAD
$path_mibcache      = $config['base_path'] . '/cache/mibcache/mibcache.tmp';
$path_mibcache_lock = $config['base_path'] . '/cache/mibcache/mibcache.lock';
||||||| 7dd05ee12
$path_mibcache = $config['base_path'] . '/cache/mibcache/mibcache.tmp';
$path_mibcache_lock = $config['base_path'] . '/cache/mibcache/mibcache.lock';
=======
$path_mibcache      = CACTI_PATH_CACHE . '/mibcache/mibcache.tmp';
$path_mibcache_lock = CACTI_PATH_CACHE . '/mibcache/mibcache.lock';
>>>>>>> origin/fix/jquery-deprecations

<<<<<<< HEAD
/* check mib cache table status */
$mibcache_changed = db_fetch_cell_prepared("SHOW TABLE STATUS
	WHERE `Name` LIKE 'snmpagent_cache'
	AND (UNIX_TIMESTAMP(`Update_time`)) >= ?",
	array($last_time));
||||||| 7dd05ee12
/* check mib cache table status */
$mibcache_changed = db_fetch_cell_prepared("SHOW TABLE STATUS WHERE `Name` LIKE 'snmpagent_cache' AND (UNIX_TIMESTAMP(`Update_time`)) >= ?", array($last_time));
=======
// check mib cache table status
$mibcache_changed = db_fetch_cell_prepared("SHOW TABLE STATUS
	WHERE `Name` LIKE 'snmpagent_cache'
	AND (UNIX_TIMESTAMP(`Update_time`)) >= ?",
	[$last_time]);
>>>>>>> origin/fix/jquery-deprecations

if ($mibcache_changed !== null || file_exists($path_mibcache) === false) {
<<<<<<< HEAD
	$objects = db_fetch_assoc("SELECT `oid`, LOWER(type) as type, `otype`, `max-access`, `value`
		FROM snmpagent_cache");
||||||| 7dd05ee12
if($mibcache_changed !== NULL || file_exists($path_mibcache) === false ) {
	$objects = db_fetch_assoc("SELECT `oid`, LOWER(type) as type, `otype`, `max-access`, `value` FROM snmpagent_cache");
=======
	$objects = db_fetch_assoc('SELECT `oid`, LOWER(type) as type, `otype`, `max-access`, `value`
		FROM snmpagent_cache');
>>>>>>> origin/fix/jquery-deprecations

	if (cacti_sizeof($objects)) {
<<<<<<< HEAD
		$oids = array();

		foreach($objects as &$object) {
||||||| 7dd05ee12
	if($objects && cacti_sizeof($objects)>0) {
		$oids = array();
		foreach($objects as &$object) {
=======
		$oids = [];

		foreach ($objects as &$object) {
>>>>>>> origin/fix/jquery-deprecations
			$oids[] = $object['oid'];

<<<<<<< HEAD
			$object = ($object['otype'] == 'DATA' && $object['max-access'] != 'not-accessible') ? array('type' => $object['type'], 'value' => $object['value']) : false;
||||||| 7dd05ee12
			$object = ($object['otype'] == 'DATA' && $object['max-access'] != 'not-accessible') ? array('type' => $object['type'], 'value' => $object['value']) : false;
=======
			$object = ($object['otype'] == 'DATA' && $object['max-access'] != 'not-accessible') ? ['type' => $object['type'], 'value' => $object['value']] : false;
>>>>>>> origin/fix/jquery-deprecations
		}

<<<<<<< HEAD
		/* natural sorting with MySQL is not available - especially not for OIDs */
||||||| 7dd05ee12
		/* natural sorting with MySQL is not available - especially not for OIDs */
=======
		// natural sorting with MySQL is not available - especially not for OIDs
>>>>>>> origin/fix/jquery-deprecations
		natsort($oids);

		$last_accessible_object          = false;
		$next_accessible_object_required = [];

<<<<<<< HEAD
		foreach($oids as $key => $oid) {
||||||| 7dd05ee12
		foreach($oids as $key => $oid) {
			if($objects[$key]) {
				if($last_accessible_object) {
=======
		foreach ($oids as $key => $oid) {
>>>>>>> origin/fix/jquery-deprecations
			if ($objects[$key]) {
				if ($last_accessible_object) {
					$cache[$last_accessible_object]['next'] = $oid;
				}

<<<<<<< HEAD
				if (cacti_sizeof($next_accessible_object_required)>0) {
					foreach($next_accessible_object_required as $next_accessible_object_required_oid) {
||||||| 7dd05ee12
				if(cacti_sizeof($next_accessible_object_required)>0) {
					foreach($next_accessible_object_required as $next_accessible_object_required_oid) {
=======
				if (cacti_sizeof($next_accessible_object_required) > 0) {
					foreach ($next_accessible_object_required as $next_accessible_object_required_oid) {
>>>>>>> origin/fix/jquery-deprecations
						$cache[$next_accessible_object_required_oid]['next'] = $oid;
					}

<<<<<<< HEAD
					$next_accessible_object_required = array();
||||||| 7dd05ee12
					$next_accessible_object_required = array();
=======
					$next_accessible_object_required = [];
>>>>>>> origin/fix/jquery-deprecations
				}

				$last_accessible_object = $oid;
			} else {
				$next_accessible_object_required[] = $oid;
			}

			$cache[$oid] = $objects[$key];
		}
	}

<<<<<<< HEAD
	/* create lock file */
||||||| 7dd05ee12
	/* create lock file */
=======
	// create lock file
>>>>>>> origin/fix/jquery-deprecations
	$lock = fopen($path_mibcache_lock, 'w');

<<<<<<< HEAD
	/* Note: If SNMPAgent plugin has been disabled the cache will be truncated automatically */
||||||| 7dd05ee12
	/* Note: If SNMPAgent plugin has been disabled the cache will be truncated automatically */
	file_put_contents($path_mibcache, '<?php $cache = ' . var_export($cache, true) . ';', LOCK_EX);
=======
	// Note: If SNMPAgent plugin has been disabled the cache will be truncated automatically
>>>>>>> origin/fix/jquery-deprecations
	if (cacti_sizeof($cache)) {
		file_put_contents($path_mibcache, '<?php $cache = ' . var_export($cache, true) . ';', LOCK_EX);
	}

	// destroy lock file
	fclose($lock);
	unlink($path_mibcache_lock);
}

return;
