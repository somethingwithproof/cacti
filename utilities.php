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

require('./include/auth.php');
require_once(CACTI_PATH_LIBRARY . '/api_data_source.php');
require_once(CACTI_PATH_LIBRARY . '/boost.php');
require_once(CACTI_PATH_LIBRARY . '/rrd.php');
require_once(CACTI_PATH_LIBRARY . '/clog_webapi.php');
require_once(CACTI_PATH_LIBRARY . '/poller.php');
require_once(CACTI_PATH_LIBRARY . '/utility.php');
require_once(CACTI_PATH_LIBRARY . '/clog_webapi.php');

// set default action
set_default_action();

switch (grv('action')) {
	case 'clear_poller_cache':
		$running = is_process_running('pushout', 'rmaster', 0);

		if ($running === false) {
			$php_binary = read_config_option('path_php_binary');

			exec_background($php_binary, CACTI_PATH_CLI . '/rebuild_poller_cache.php');

			usleep(300000);

			raise_message('repopulate_background', __('The Poller Cache Rebuild Operation has been started in background'), MESSAGE_LEVEL_INFO);
		} elseif ($running === true) {
			raise_message('repopulate_background', __('The Poller Cache Rebuild Operation has already been started.'), MESSAGE_LEVEL_INFO);
		}

		header('Location: utilities.php?action=view_poller_cache');

		exit;
	case 'rebuild_resource_cache':
		rebuild_resource_cache();
		header('Location: utilities.php');

		exit;
	case 'view_snmp_cache':
		top_header();
		utilities_view_snmp_cache();
		bottom_footer();

		break;
	case 'view_poller_cache':
		top_header();
		utilities_view_poller_cache();
		bottom_footer();

		break;
	case 'view_logfile':
		utilities_view_logfile();

		break;
	case 'clear_logfile':
		utilities_clear_logfile();
		utilities_view_logfile();

		break;
	case 'purge_logfile':
		clog_purge_logfile();
		utilities_view_logfile();

		break;
	case 'view_user_log':
		header('location: user_log.php');

		break;
	case 'clear_user_log':
		header('location: user_log.php?action=purge');

		break;
	case 'purge_user_log':
		header('location: user_log.php?action=purge');

		break;
	case 'view_tech':
		header('Location: support.php?tab=summary');

		exit();
	case 'view_boost_status':
		top_header();
		boost_display_run_status();
		bottom_footer();

		break;
	case 'view_snmpagent_cache':
		top_header();
		snmpagent_utilities_run_cache();
		bottom_footer();

		break;
	case 'purge_data_source_statistics':
		purge_data_source_statistics();
		raise_message('purge_dss', __('Data Source Statistics Purged.'), MESSAGE_LEVEL_INFO);
		header('Location: utilities.php');

		break;
	case 'rebuild_snmpagent_cache':
		snmpagent_cache_rebuilt();
		header('Location: utilities.php?action=view_snmpagent_cache');

		exit;
	case 'view_snmpagent_events':
		top_header();
		snmpagent_utilities_run_eventlog();
		bottom_footer();

		break;
	case 'ajax_hosts':
		get_allowed_ajax_hosts();

		break;
	case 'ajax_hosts_noany':
		get_allowed_ajax_hosts(false);

		break;
	default:
		if (!api_plugin_hook_function('utilities_action', grv('action'))) {
			top_header();
			utilities();
			bottom_footer();
		}

		break;
}

function rebuild_resource_cache() : void {
	db_execute('DELETE FROM settings WHERE name LIKE "md5dirsum%"');
	db_execute('TRUNCATE TABLE poller_resource_cache');

	raise_message('resource_cache_rebuild');

	cacti_log('NOTE: Poller Resource Cache scheduled for rebuild by user ' . get_username($_SESSION[SESS_USER_ID]), false, 'WEBUI');
}

<<<<<<< HEAD
function utilities_view_tech() {
	global $database_default, $config, $rrdtool_versions, $poller_options, $input_types, $local_db_cnn_id, $remote_db_cnn_id;

	/* ================= input validation ================= */
	get_filter_request_var('tab', FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '/^([a-z_A-Z]+)$/')));
	/* ==================================================== */

	/* present a tabbed interface */
	$tabs = array(
		'summary'    => __('Summary'),
		'database'   => __('Database'),
		'dbsettings' => __('Database Settings'),
		'dbstatus'   => __('Database Status'),
		'phpinfo'    => __('PHP Info'),
		'changelog'  => __('ChangeLog'),
	);

	/* set the default tab */
	load_current_session_value('tab', 'sess_ts_tabs', 'summary');
	$current_tab = get_nfilter_request_var('tab');

	$page = 'utilities.php?action=view_tech&header=false&tab=' . $current_tab;

	$refresh = array(
		'seconds' => 999999,
		'page'    => $page,
		'logout'  => 'false'
	);

	set_page_refresh($refresh);

	$header_label = __esc('Technical Support [%s]', $tabs[get_request_var('tab')]);

	top_header();

	if (cacti_sizeof($tabs)) {
		$i = 0;

		/* draw the tabs */
		print "<div class='tabs'><nav><ul role='tablist'>";

		foreach (array_keys($tabs) as $tab_short_name) {
			print "<li class='subTab'><a class='tab" . (($tab_short_name == $current_tab) ? " selected'" : "'") .
				" href='" . html_escape($config['url_path'] .
				'utilities.php?action=view_tech' .
				'&tab=' . $tab_short_name) .
				"'>" . $tabs[$tab_short_name] . "</a></li>";

			$i++;
		}

		api_plugin_hook('utilities_tab');

		print "</ul></nav></div>";
	}

	/* Display tech information */
	html_start_box($header_label, '100%', '', '3', 'center', '');

	if (get_request_var('tab') == 'summary') {
		/* Get poller stats */
		$poller_item = db_fetch_assoc('SELECT action, count(action) AS total
			FROM poller_item
			GROUP BY action');

		/* Get system stats */
		$host_count  = db_fetch_cell('SELECT COUNT(*) FROM host WHERE deleted = ""');
		$graph_count = db_fetch_cell('SELECT COUNT(*) FROM graph_local');
		$data_count  = db_fetch_assoc('SELECT i.type_id, COUNT(i.type_id) AS total
			FROM data_template_data AS d, data_input AS i
			WHERE d.data_input_id = i.id
			AND local_data_id <> 0
			GROUP BY i.type_id');

		/* Get RRDtool version */
		$rrdtool_version = __('Unknown');
		$rrdtool_release = __('Unknown');
		$storage_location = read_config_option('storage_location');

		$out_array = array();

		if ($storage_location == 0) {
			if ((file_exists(read_config_option('path_rrdtool'))) && ((function_exists('is_executable')) && (is_executable(read_config_option('path_rrdtool'))))) {
				exec(cacti_escapeshellcmd(read_config_option('path_rrdtool')), $out_array);
			}
		}else {
			$rrdtool_pipe = rrd_init();
			$out_array[] = rrdtool_execute('info', false, RRDTOOL_OUTPUT_STDOUT, $rrdtool_pipe, 'WEBLOG');
			rrd_close($rrdtool_pipe);
		}

		if (cacti_sizeof($out_array) > 0) {
			if (preg_match('/^RRDtool ([0-9.]+)/', $out_array[0], $m)) {
				preg_match('/^([0-9]+\.[0-9]+\.[0.9]+)/', $m[1], $m2);
				$rrdtool_release = $m[1];
				$rrdtool_version = $rrdtool_release;
			}
		}

		/* Get SNMP cli version */
		if ((file_exists(read_config_option('path_snmpget'))) && ((function_exists('is_executable')) && (is_executable(read_config_option('path_snmpget'))))) {
			$snmp_version = shell_exec(cacti_escapeshellcmd(read_config_option('path_snmpget')) . ' -V 2>&1');
		} else {
			$snmp_version = "<span class='deviceDown'>" . __('NET-SNMP Not Installed or its paths are not set.  Please install if you wish to monitor SNMP enabled devices.') . "</span>";
		}

		/* Check RRDtool issues */
		$rrdtool_errors = array();
		if (cacti_version_compare($rrdtool_version, get_rrdtool_version(), '<')) {
			$rrdtool_errors[] = "<span class='deviceDown'>" . __('ERROR: Installed RRDtool version does not exceed configured version.<br>Please visit the %s and select the correct RRDtool Utility Version.', "<a href='" . html_escape('settings.php?tab=general') . "'>" . __('Configuration Settings') . '</a>') . "</span>";
		}

		$graph_gif_count = db_fetch_cell('SELECT COUNT(*) FROM graph_templates_graph WHERE image_format_id = 2');
		if ($graph_gif_count > 0) {
			$rrdtool_errors[] = "<span class='deviceDown'>" . __('ERROR: RRDtool 1.2.x+ does not support the GIF images format, but %d" graph(s) and/or templates have GIF set as the image format.', $graph_gif_count) . '</span>';
		}

		/* Get spine version */
		$spine_version = 'Unknown';
		if ((file_exists(read_config_option('path_spine'))) && ((function_exists('is_executable')) && (is_executable(read_config_option('path_spine'))))) {
			$out_array = array();
			exec(cacti_escapeshellcmd(read_config_option('path_spine')) . ' --version', $out_array);
			if (cacti_sizeof($out_array) > 0) {
				$spine_version = $out_array[0];
			}
		}

		html_section_header(__('General Information'), 2);
		form_alternate_row();
		print '<td>' . __('Date') . '</td>';
		print '<td>' . date('r') . '</td>';
		form_end_row();

		api_plugin_hook_function('custom_version_info');

		form_alternate_row();
		print '<td>' . __('Cacti Version') . '</td>';
		print '<td>' . CACTI_VERSION . '</td>';
		form_end_row();

		form_alternate_row();
		print '<td>' . __('Cacti OS') . '</td>';
		print '<td>' . $config['cacti_server_os'] . '</td>';
		form_end_row();

		form_alternate_row();
		print '<td>' . __('RSA Fingerprint') . '</td>';
		print '<td>' . read_config_option('rsa_fingerprint') . '</td>';
		form_end_row();

		form_alternate_row();
		print '<td>' . __('NET-SNMP Version') . '</td>';
		print '<td>' . $snmp_version . '</td>';
		form_end_row();

		form_alternate_row();
		print '<td>' . __('RRDtool Version') . ' ' . __('Configured') . '</td>';
		print '<td>' . get_rrdtool_version() . "+</td>";
		form_end_row();

		form_alternate_row();
		print '<td>' . __('RRDtool Version') . ' ' . __('Found') . '</td>';
		print '<td>' . $rrdtool_release . '</td>';
		form_end_row();

		if (!empty($rrdtool_errors)) {
			form_alternate_row();
			print "<td>&nbsp;</td>";
			$br = '';
			print '<td>';
			foreach ($rrdtool_errors as $rrdtool_error) {
				print $br . $rrdtool_error;
				$br = '<br/>';
			}
			print '</td>';
			form_end_row();
		}

		form_alternate_row();
		print '<td>' . __('Devices') . '</td>';
		print '<td>' . number_format_i18n($host_count) . '</td>';
		form_end_row();

		form_alternate_row();
		print '<td>' . __('Graphs') . '</td>';
		print '<td>' . number_format_i18n($graph_count) . '</td>';
		form_end_row();

		form_alternate_row();
		print '<td>' . __('Data Sources') . '</td>';
		print '<td>';
		$data_total = 0;
		if (cacti_sizeof($data_count)) {
			foreach ($data_count as $item) {
				print $input_types[$item['type_id']] . ': ' . number_format_i18n($item['total']) . '<br>';
				$data_total += $item['total'];
			}
			print __('Total: %s', number_format_i18n($data_total));
		} else {
			print "<span class='deviceDown'>0</span>";
		}
		print '</td>';
		form_end_row();

		html_section_header(__('Poller Information'), 2);

		form_alternate_row();
		print '<td>' . __('Interval') . '</td>';
		print '<td>' . read_config_option('poller_interval') . '</td>';
		if (file_exists(read_config_option('path_spine')) && $poller_options[read_config_option('poller_type')] == 'spine') {
			$type = $spine_version;
		        if (!strpos($spine_version, CACTI_VERSION)) {
		    	    $type .= '<span class="textError"> (' . __('Different version of Cacti and Spine!') . ')</span>';
			}
		} else {
			$type = $poller_options[read_config_option('poller_type')];
		}
		form_end_row();

		form_alternate_row();
		print '<td>' . __('Type') . '</td>';
		print '<td>' . $type . '</td>';
		form_end_row();

		form_alternate_row();
		print '<td>' . __('Items') . '</td>';
		print '<td>';
		$total = 0;
		if (cacti_sizeof($poller_item)) {
			foreach ($poller_item as $item) {
				print __('Action[%s]', $item['action']) . ': ' . number_format_i18n($item['total']) . '<br>';
				$total += $item['total'];
			}
			print __('Total: %s', number_format_i18n($total));
		} else {
			print "<span class='deviceDown'>" . __('No items to poll') . "</span>";
		}
		print '</td>';
		form_end_row();

		$processes = db_fetch_cell('SELECT
			GROUP_CONCAT(
				CONCAT("' . __('Name: ') . '", name, ", ' . __('Procs: ') . '", processes) SEPARATOR "<br>"
			) AS poller
			FROM poller
			WHERE disabled=""');

		$threads = db_fetch_cell('SELECT
			GROUP_CONCAT(
				CONCAT("' . __('Name: ') . '", name, ", ' . __('Threads: ') . '", threads) SEPARATOR "<br>"
			) AS poller
			FROM poller
			WHERE disabled=""');

		form_alternate_row();
		print '<td>' . __('Concurrent Processes') . '</td>';
		print '<td>' . $processes . '</td>';
		form_end_row();

		form_alternate_row();
		print '<td>' . __('Max Threads') . '</td>';
		print '<td>' . $threads . '</td>';
		form_end_row();

		$script_servers = read_config_option('php_servers');

		form_alternate_row();
		print '<td>' . __('PHP Servers') . '</td>';
		print '<td>' . html_escape($script_servers) . '</td>';
		form_end_row();

		if ($config['poller_id'] == 1) {
			$max_connections       = db_fetch_row('SHOW GLOBAL VARIABLES LIKE "max_connections"');
			$max_local_connections = array();
		} elseif ($config['connection'] == 'online') {
			$max_connections        = db_fetch_row('SHOW GLOBAL VARIABLES LIKE "max_connections"');
			$max_local_connections  = db_fetch_row('SHOW GLOBAL VARIABLES LIKE "max_connections"', false, $local_db_cnn_id);
		} else {
			$max_connections        = array();
			$max_local_connections  = db_fetch_row('SHOW GLOBAL VARIABLES LIKE "max_connections"');
		}

		if (cacti_sizeof($max_connections)) {
			$max_connections = $max_connections['Value'];
		} else {
			$max_connections = 0;
		}

		if (cacti_sizeof($max_local_connections)) {
			$max_local_connections = $max_local_connections['Value'];
		} else {
			$max_local_connections = 0;
		}

		$total_dc_threads = db_fetch_cell("SELECT
			SUM((processes * threads) + (processes * $script_servers)) AS threads
			FROM poller
			WHERE disabled = ''");

		$recommend_mc = $total_dc_threads + 100;

		if ($recommend_mc > $max_connections) {
			if ($config['poller_id'] == 1) {
				$db_connections = '<span class="deviceDown">' . __('Main Server: Current: %s, Min Required: %s', $max_connections, $recommend_mc) . '</span>';
			} elseif ($config['connection'] == 'online') {
				$db_connections = '<span class="deviceDown">' . __('Main Server: Current: %s, Min Required: %s', $max_connections, $recommend_mc) . '</span>';
			} else {
				$db_connections = '';
			}
		} else {
			if ($config['poller_id'] == 1) {
				$db_connections = '<span class="deviceUp">' . __('Main Server: Current: %s, Min Required: %s', $max_connections, $recommend_mc) . '</span>';
			} elseif ($config['connection'] == 'online') {
				$db_connections = '<span class="deviceUp">' . __('Main Server: Current: %s, Min Required: %s', $max_connections, $recommend_mc) . '</span>';
			} else {
				$db_connections = '';
			}
		}

		if ($config['poller_id'] > 1) {
			if ($recommend_mc > $max_local_connections) {
				$db_connections .= '<br><span class="deviceDown">' . __('Local Server: Current: %s, Min Required: %s', $max_local_connections, $recommend_mc) . '</span>';
			} else {
				$db_connections .= '<br><span class="deviceUp">' . __('Local Server: Current: %s, Min Required: %s', $max_local_connections, $recommend_mc) . '</span>';
			}
		}

		form_alternate_row();
		print '<td>' . __('Minimum Connections:') . '</td>';
		print '<td>' . $db_connections . '<br>' .
			__('Assumes 100 spare connections for Web page users and other various connections.') . '<br>' .
			__('The minimum required can vary greatly if there is heavy user Graph viewing activity.') . '<br>' .
			__('Each browser tab can use upto 10 connections depending on the browser.') . '</td>';
		form_end_row();

		form_alternate_row();
		print '<td>' . __('Script Timeout') . '</td>';
		print '<td>' . read_config_option('script_timeout') . '</td>';
		form_end_row();

		form_alternate_row();
		print '<td>' . __('Max OID') . '</td>';
		print '<td>' . read_config_option('max_get_size') . '</td>';
		form_end_row();

		form_alternate_row();
		print '<td>' . __('Last Run Statistics') . '</td>';
		print '<td>' . read_config_option('stats_poller') . '</td>';
		form_end_row();

		/* Get System Memory */
		$memInfo = utilities_get_system_memory();

		//print '<pre>';print_r($memInfo);print '</pre>';

		$total_memory = 0;

		if (cacti_sizeof($memInfo)) {
			html_section_header(__('System Memory'), 2);

			foreach($memInfo as $name => $value) {
				if ($config['cacti_server_os'] == 'win32') {
					form_alternate_row();
					print "<td>$name</td>";
					print '<td>' . number_format_i18n($value/1000, 2) . " MB</td>";
					form_end_row();
				} else {
					switch($name) {
					case 'SwapTotal':
					case 'SwapFree':
					case 'Cached':
					case 'MemTotal':
					case 'MemFree':
					case 'MemAvailable':
					case 'Buffers':
					case 'Active':
					case 'Inactive':
						// Convert to GBi
						$value /= (1000 * 1000 * 1000);

						form_alternate_row();
						print "<td>$name</td>";
						print '<td>' . __('%s GB', number_format_i18n($value, 2, 1000)) . '</td>';
						form_end_row();

						if ($name == 'MemTotal') {
							$total_memory = $value;
						}
					}
				}
			}

			form_end_row();
		}

		$mysql_info = utilities_get_mysql_info($config['poller_id']);

		$database  = $mysql_info['database'];
		$version   = $mysql_info['version'];
		$link_ver  = $mysql_info['link_ver'];
		$variables = $mysql_info['variables'];

		// Get Maximum Memory in GB for MySQL/MariaDB
		if ($config['poller_id'] == 1) {
			if (($database == 'MySQL' && version_compare($version, '8.0', '<')) || $database == 'MariaDB') {
				$systemMemory = db_fetch_cell('SELECT
					(@@GLOBAL.key_buffer_size
					+ @@GLOBAL.query_cache_size
					+ @@GLOBAL.tmp_table_size
					+ @@GLOBAL.innodb_buffer_pool_size
					+ @@GLOBAL.innodb_log_buffer_size) / 1024 / 1024 / 1024');

				$maxPossibleMyMemory = db_fetch_cell('SELECT (
					(@@GLOBAL.key_buffer_size
					+ @@GLOBAL.query_cache_size
					+ @@GLOBAL.tmp_table_size
					+ @@GLOBAL.innodb_buffer_pool_size
					+ @@GLOBAL.innodb_log_buffer_size
					+ @@GLOBAL.max_connections * (
						@@GLOBAL.sort_buffer_size
						+ @@GLOBAL.read_buffer_size
						+ @@GLOBAL.read_rnd_buffer_size
						+ @@GLOBAL.join_buffer_size
						+ @@GLOBAL.thread_stack
						+ @@GLOBAL.binlog_cache_size)
					) / 1024 / 1024 / 1024)');
			} else {
				$systemMemory = db_fetch_cell('SELECT
					(@@GLOBAL.key_buffer_size
					+ @@GLOBAL.tmp_table_size
					+ @@GLOBAL.innodb_buffer_pool_size
					+ @@GLOBAL.innodb_log_buffer_size) / 1024 / 1024 / 1024');

				$maxPossibleMyMemory = db_fetch_cell('SELECT (
					(@@GLOBAL.key_buffer_size
					+ @@GLOBAL.tmp_table_size
					+ @@GLOBAL.innodb_buffer_pool_size
					+ @@GLOBAL.innodb_log_buffer_size
					+ @@GLOBAL.max_connections * (
						@@GLOBAL.sort_buffer_size
						+ @@GLOBAL.read_buffer_size
						+ @@GLOBAL.read_rnd_buffer_size
						+ @@GLOBAL.join_buffer_size
						+ @@GLOBAL.thread_stack
						+ @@GLOBAL.binlog_cache_size)
					) / 1024 / 1024 / 1024)');
			}

			$clientMemory = db_fetch_cell('SELECT @@GLOBAL.max_connections * (
				@@GLOBAL.sort_buffer_size
				+ @@GLOBAL.read_buffer_size
				+ @@GLOBAL.read_rnd_buffer_size
				+ @@GLOBAL.join_buffer_size
				+ @@GLOBAL.thread_stack
				+ @@GLOBAL.binlog_cache_size) / 1024 / 1024 / 1024');
		} else {
			if (($database == 'MySQL' && version_compare($version, '8.0', '<')) || $database == 'MariaDB') {
				$maxPossibleMyMemory = db_fetch_cell('SELECT (
					(@@GLOBAL.key_buffer_size
					+ @@GLOBAL.query_cache_size
					+ @@GLOBAL.tmp_table_size
					+ @@GLOBAL.innodb_buffer_pool_size
					+ @@GLOBAL.innodb_log_buffer_size
					+ @@GLOBAL.max_connections * (
						@@GLOBAL.sort_buffer_size
						+ @@GLOBAL.read_buffer_size
						+ @@GLOBAL.read_rnd_buffer_size
						+ @@GLOBAL.join_buffer_size
						+ @@GLOBAL.thread_stack
						+ @@GLOBAL.binlog_cache_size)
					) / 1024 / 1024 / 1024)', '', false, $local_db_cnn_id);

				$systemMemory = db_fetch_cell('SELECT
					(@@GLOBAL.key_buffer_size
					+ @@GLOBAL.query_cache_size
					+ @@GLOBAL.tmp_table_size
					+ @@GLOBAL.innodb_buffer_pool_size
					+ @@GLOBAL.innodb_log_buffer_size) / 1024 / 1024 / 1024', '', false, $local_db_cnn_id);
			} else {
				$maxPossibleMyMemory = db_fetch_cell('SELECT (
					(@@GLOBAL.key_buffer_size
					+ @@GLOBAL.tmp_table_size
					+ @@GLOBAL.innodb_buffer_pool_size
					+ @@GLOBAL.innodb_log_buffer_size
					+ @@GLOBAL.max_connections * (
						@@GLOBAL.sort_buffer_size
						+ @@GLOBAL.read_buffer_size
						+ @@GLOBAL.read_rnd_buffer_size
						+ @@GLOBAL.join_buffer_size
						+ @@GLOBAL.thread_stack
						+ @@GLOBAL.binlog_cache_size)
					) / 1024 / 1024 / 1024)', '', false, $local_db_cnn_id);

				$systemMemory = db_fetch_cell('SELECT
					(@@GLOBAL.key_buffer_size
					+ @@GLOBAL.tmp_table_size
					+ @@GLOBAL.innodb_buffer_pool_size
					+ @@GLOBAL.innodb_log_buffer_size) / 1024 / 1024 / 1024', '', false, $local_db_cnn_id);
			}

			$clientMemory = db_fetch_cell('SELECT @@GLOBAL.max_connections * (
				@@GLOBAL.sort_buffer_size
				+ @@GLOBAL.read_buffer_size
				+ @@GLOBAL.read_rnd_buffer_size
				+ @@GLOBAL.join_buffer_size
				+ @@GLOBAL.thread_stack
				+ @@GLOBAL.binlog_cache_size) / 1024 / 1024 / 1024', '', false, $local_db_cnn_id);
		}

		html_section_header(__('MySQL/MariaDB Memory Statistics (Source: MySQL Tuner)'), 2);

		if ($total_memory > 0) {
			if ($maxPossibleMyMemory > ($total_memory * 0.8)) {
				form_alternate_row();
				print '<td>' . __('Max Total Memory Possible') . '</td>';
				print '<td class="deviceDown">' . __('%s GB', number_format_i18n($maxPossibleMyMemory, 2, 1000)) . '</td>';
				form_end_row();
				form_alternate_row();
				print '<td></td>';
				print '<td>' . __('Reduce MySQL/MariaDB Memory to less than 80% of System Memory.  Preserve additional Cache Memory for RRDfiles if the Database is on the same system as the RRDfiles.  See Core and Client Totals below for explanation of calculation method.') . '</td>';
				form_end_row();
			} else {
				form_alternate_row();
				print '<td>' . __('Max Total Memory Possible') . '</td>';
				print '<td class="deviceUp">' . __('%s GB', number_format_i18n($maxPossibleMyMemory, 2, 1000)) . '</td>';
				form_end_row();
			}
		} else {
			form_alternate_row();
			print '<td>' . __('Max Total Memory Possible') . '</td>';
			print '<td>' . __('%s GB', number_format_i18n($maxPossibleMyMemory, 2, 1000)) . '</td>';
			form_end_row();
		}

		if ($total_memory > 0) {
			if ($systemMemory > ($total_memory * 0.8)) {
				form_alternate_row();
				print '<td>' . __('Max Core Memory Possible') . '</td>';
				print '<td class="deviceDown">' . __('%s GB', number_format_i18n($systemMemory, 2, 1000)) . '&nbsp;&nbsp;(' . __('Reduce Total Core Memory') . '</td>';
				form_end_row();
			} else {
				form_alternate_row();
				print '<td>' . __('Max Core Memory Possible') . '</td>';
				print '<td class="deviceUp">' . __('%s GB', number_format_i18n($systemMemory, 2, 1000)) . '</td>';
				form_end_row();
			}

			form_alternate_row();
			print '<td>' . __('Calculation Formula') . '</td>';
			print '<td>SELECT @@GLOBAL.key_buffer_size + <br>@@GLOBAL.query_cache_size + <br>@@GLOBAL.tmp_table_size + <br>@@GLOBAL.innodb_buffer_pool_size + <br>@@GLOBAL.innodb_log_buffer_size</td>';
			form_end_row();

			if ($clientMemory > ($total_memory * 0.8)) {
				form_alternate_row();
				print '<td>' . __('Max Connection Memory Possible') . '</td>';
				print '<td class="deviceDown">' . __('%s GB', number_format_i18n($clientMemory, 2, 1000)) . '&nbsp;&nbsp;(' . __('Reduce Total Client Memory') . ')</td>';
				form_end_row();
			} else {
				form_alternate_row();
				print '<td>' . __('Max Connection Memory Possible') . '</td>';
				print '<td class="deviceUp">' . __('%s GB', number_format_i18n($clientMemory, 2, 1000)) . '</td>';
				form_end_row();
			}

			form_alternate_row();
			print '<td>' . __('Calculation Formula') . '</td>';
			print '<td>SELECT @@GLOBAL.max_connections * (<br>@@GLOBAL.sort_buffer_size + <br>@@GLOBAL.read_buffer_size + <br>@@GLOBAL.read_rnd_buffer_size + <br>@@GLOBAL.join_buffer_size + <br>@@GLOBAL.thread_stack + <br>@@GLOBAL.binlog_cache_size)</td>';
			form_end_row();
		} else {
			form_alternate_row();
			print '<td>' . __('Max Core Memory Possible') . '</td>';
			print '<td class="deviceUp">' . __('%s GB', number_format_i18n($systemMemory, 2, 1000)) . '</td>';
			form_end_row();

			form_alternate_row();
			print '<td>' . __('Max Connection Memory Possible') . '</td>';
			print '<td>' . __('%s GB', number_format_i18n($clientMemory, 2, 1000)) . '</td>';
			form_end_row();
		}

		html_section_header(__('PHP Information'), 2);

		form_alternate_row();
		print '<td>' . __('PHP Version') . '</td>';
		if (version_compare(PHP_VERSION, '5.5.0') >= 0) {
			print '<td>' . PHP_VERSION . '</td>';
		} else {
			print '<td>' . PHP_VERSION . "</br><span class='deviceDown'>" . __('PHP Version 5.5.0+ is recommended due to strong password hashing support.') . "</span></td>";
		}
		form_end_row();

		form_alternate_row();
		print '<td>' . __('PHP OS') . '</td>';
		print '<td>' . PHP_OS . '</td>';
		form_end_row();

		form_alternate_row();
		print '<td>' . __('PHP uname') . '</td>';
		print '<td>';
		if (function_exists('php_uname')) {
			print php_uname();
		} else {
			print __('N/A');
		}
		print '</td>';
		form_end_row();

		form_alternate_row();
		print '<td>' . __('PHP SNMP') . '</td>';
		print '<td>';
		if (function_exists('snmpget')) {
			print __('Installed. <span class="deviceDown">Note: If you are planning on using SNMPv3, you must remove php-snmp and use the Net-SNMP toolset.</span>');
		} else {
			print __('Not Installed');
		}
		print '</td>';
		form_end_row();

		form_alternate_row();
		print "<td>max_execution_time</td>";
		print '<td>' . ini_get('max_execution_time') . '</td>';
		form_end_row();

		form_alternate_row();
		print "<td>memory_limit</td>";
		print '<td>' . ini_get('memory_limit');

		/* Calculate memory suggestion based off of data source count */
		$memory_suggestion = $data_total * 32768;

		/* Set minimum - 16M */
		if ($memory_suggestion < 16777216) {
			$memory_suggestion = 16777216;
		}

		/* Set maximum - 512M */
		if ($memory_suggestion > 536870912) {
			$memory_suggestion = 536870912;
		}

		/* Suggest values in 8M increments */
		$memory_suggestion = round($memory_suggestion / 8388608) * 8388608;
		if (memory_bytes(ini_get('memory_limit')) < $memory_suggestion) {
			print "<br><span class='deviceDown'>";
			if ((ini_get('memory_limit') == -1)) {
				print __("You've set memory limit to 'unlimited'.") . "<br>";
			}
			print __('It is highly suggested that you alter you php.ini memory_limit to %s or higher.', memory_readable($memory_suggestion)) . ' <br/>' .
				__('This suggested memory value is calculated based on the number of data source present and is only to be used as a suggestion, actual values may vary system to system based on requirements.');
			print '</span><br>';
		}
		print '</td>';
		form_end_row();

		utilities_get_mysql_recommendations();
	} elseif (get_request_var('tab') == 'dbstatus') {
		$status = db_fetch_assoc('show global status');

		print "<table id='tables' class='cactiTable' style='width:100%'>";
		print '<thead>';
		print "<tr class='tableHeader'>";
		print "  <th class='tableSubHeaderColumn'>" . __('Variable Name') . '</th>';
		print "  <th class='tableSubHeaderColumn'>" . __('Value') . '</th>';
		print '</tr>';
		print '</thead>';

		foreach($status as $s) {
			form_alternate_row();
			print '<td>' . $s['Variable_name'] . '</td>';
			print '<td>' . (is_numeric($s['Value']) ? number_format_i18n($s['Value']):$s['Value']) . '</td>';
			form_end_row();
		}
	} elseif (get_request_var('tab') == 'dbsettings') {
		$status = db_fetch_assoc('show global variables');

		print "<table id='tables' class='cactiTable' style='width:100%'>";
		print '<thead>';
		print "<tr class='tableHeader'>";
		print "  <th class='tableSubHeaderColumn'>" . __('Variable Name') . '</th>';
		print "  <th class='tableSubHeaderColumn'>" . __('Value') . '</th>';
		print '</tr>';
		print '</thead>';

		foreach($status as $s) {
			form_alternate_row();
			print '<td>' . $s['Variable_name'] . '</td>';

			if (strlen($s['Value']) > 70) {
				$s['Value'] = str_replace(',', ', ', $s['Value']);
			}
			print '<td>' . (is_numeric($s['Value']) ? number_format_i18n($s['Value']):$s['Value']) . '</td>';
			form_end_row();
		}
	} elseif (get_request_var('tab') == 'changelog') {
		$changelog = file($config['base_path'] . '/CHANGELOG');

		foreach($changelog as $s) {
			if (strlen(trim($s)) && stripos($s, 'CHANGELOG') === false) {
				if (strpos($s, '-') === false) {
					html_section_header(__('Version %s', $s), 2);
				} else {
					form_alternate_row();
					print '<td>' . $s . '</td>';
					form_end_row();
				}
			}
		}
	} elseif (get_request_var('tab') == 'database') {
		/* Get table status */
		if ($config['poller_id'] == 1) {
			$tables = db_fetch_assoc('SELECT *
				FROM information_schema.tables
				WHERE table_schema = SCHEMA()');
		} else {
			$tables = db_fetch_assoc('SELECT *
				FROM information_schema.tables
				WHERE table_schema = SCHEMA()', false, $local_db_cnn_id);
		}

		html_section_header(__('MySQL Table Information - Sizes in KBytes'), 2);

		form_alternate_row();
		print "		<td colspan='2' style='text-align:left;padding:0px'>";

		if (cacti_sizeof($tables)) {
			print "<table id='tables' class='cactiTable' style='width:100%'>";
			print '<thead>';
			print "<tr class='tableHeader'>";
			print "  <th class='tableSubHeaderColumn'>" . __('Name') . '</th>';
			print "  <th class='tableSubHeaderColumn'>" . __('Engine') . '</th>';
			print "  <th class='tableSubHeaderColumn right'>" . __('Rows') . '</th>';
			print "  <th class='tableSubHeaderColumn right'>" . __('Avg Row Length') . '</th>';
			print "  <th class='tableSubHeaderColumn right'>" . __('Data Length') . '</th>';
			print "  <th class='tableSubHeaderColumn right'>" . __('Index Length') . '</th>';
			print "  <th class='tableSubHeaderColumn'>" . __('Collation') . '</th>';
			print "  <th class='tableSubHeaderColumn'>" . __('Row Format') . '</th>';
			print "  <th class='tableSubHeaderColumn'>" . __('Comment') . '</th>';
			print '</tr>';
			print '</thead>';
			foreach ($tables as $table) {
				form_alternate_row();
				print '<td>' . $table['TABLE_NAME'] . '</td>';
				print '<td>' . $table['ENGINE'] . '</td>';
				print '<td class="right">' . number_format_i18n($table['TABLE_ROWS']) . '</td>';
				print '<td class="right">' . number_format_i18n($table['AVG_ROW_LENGTH']) . '</td>';
				print '<td class="right">' . number_format_i18n($table['DATA_LENGTH']) . '</td>';
				print '<td class="right">' . number_format_i18n($table['INDEX_LENGTH']) . '</td>';
				print '<td>' . $table['TABLE_COLLATION'] . '</td>';
				print '<td>' . $table['ROW_FORMAT'] . '</td>';
				print '<td>' . $table['TABLE_COMMENT'] . '</td>';
				form_end_row();
			}

			print "</table>";
		} else {
			print __('Unable to retrieve table status');
		}
		print '</td>';

		form_end_row();
	} else {
		$php_info = utilities_php_modules();

		html_section_header(__('PHP Module Information'), 2);
		$php_info = str_replace(
			array('width="600"', 'th colspan="2"', ','),
			array('', 'th class="subHeaderColumn"', ', '),
			$php_info
		);
		print "<tr><td colspan='2'>" . $php_info . '</td></tr>';
	}

	html_end_box();

	?>
	<script type='text/javascript'>
	$(function() {
		$('#tables').tablesorter({
			widgets: ['zebra'],
			widgetZebra: { css: ['even', 'odd'] },
			headerTemplate: '<div class="textSubHeaderDark">{content} {icon}</div>',
			cssIconAsc: 'fa-sort-up',
			cssIconDesc: 'fa-sort-down',
			cssIconNone: 'fa-sort',
			cssIcon: 'fa'
		});
	});
	</script>
	<?php

	bottom_footer();
||||||| 7dd05ee12
function utilities_view_tech() {
	global $database_default, $config, $rrdtool_versions, $poller_options, $input_types, $local_db_cnn_id, $remote_db_cnn_id;

	/* ================= input validation ================= */
	get_filter_request_var('tab', FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '/^([a-z_A-Z]+)$/')));
	/* ==================================================== */

	/* present a tabbed interface */
	$tabs = array(
		'summary'    => __('Summary'),
		'database'   => __('Database'),
		'dbsettings' => __('Database Settings'),
		'dbstatus'   => __('Database Status'),
		'phpinfo'    => __('PHP Info'),
		'changelog'  => __('ChangeLog'),
	);

	/* set the default tab */
	load_current_session_value('tab', 'sess_ts_tabs', 'summary');
	$current_tab = get_nfilter_request_var('tab');

	$page = 'utilities.php?action=view_tech&header=false&tab=' . $current_tab;

	$refresh = array(
		'seconds' => 999999,
		'page'    => $page,
		'logout'  => 'false'
	);

	set_page_refresh($refresh);

	$header_label = __esc('Technical Support [%s]', $tabs[get_request_var('tab')]);

	top_header();

	if (cacti_sizeof($tabs)) {
		$i = 0;

		/* draw the tabs */
		print "<div class='tabs'><nav><ul role='tablist'>";

		foreach (array_keys($tabs) as $tab_short_name) {
			print "<li class='subTab'><a class='tab" . (($tab_short_name == $current_tab) ? " selected'" : "'") .
				" href='" . html_escape($config['url_path'] .
				'utilities.php?action=view_tech' .
				'&tab=' . $tab_short_name) .
				"'>" . $tabs[$tab_short_name] . "</a></li>";

			$i++;
		}

		api_plugin_hook('utilities_tab');

		print "</ul></nav></div>";
	}

	/* Display tech information */
	html_start_box($header_label, '100%', '', '3', 'center', '');

	if (get_request_var('tab') == 'summary') {
		/* Get poller stats */
		$poller_item = db_fetch_assoc('SELECT action, count(action) AS total
			FROM poller_item
			GROUP BY action');

		/* Get system stats */
		$host_count  = db_fetch_cell('SELECT COUNT(*) FROM host WHERE deleted = ""');
		$graph_count = db_fetch_cell('SELECT COUNT(*) FROM graph_local');
		$data_count  = db_fetch_assoc('SELECT i.type_id, COUNT(i.type_id) AS total
			FROM data_template_data AS d, data_input AS i
			WHERE d.data_input_id = i.id
			AND local_data_id <> 0
			GROUP BY i.type_id');

		/* Get RRDtool version */
		$rrdtool_version = __('Unknown');
		$rrdtool_release = __('Unknown');
		$storage_location = read_config_option('storage_location');

		$out_array = array();

		if ($storage_location == 0) {
			if ((file_exists(read_config_option('path_rrdtool'))) && ((function_exists('is_executable')) && (is_executable(read_config_option('path_rrdtool'))))) {
				exec(cacti_escapeshellcmd(read_config_option('path_rrdtool')), $out_array);
			}
		}else {
			$rrdtool_pipe = rrd_init();
			$out_array[] = rrdtool_execute('info', false, RRDTOOL_OUTPUT_STDOUT, $rrdtool_pipe, 'WEBLOG');
			rrd_close($rrdtool_pipe);
		}

		if (cacti_sizeof($out_array) > 0) {
			if (preg_match('/^RRDtool ([0-9.]+)/', $out_array[0], $m)) {
				preg_match('/^([0-9]+\.[0-9]+\.[0.9]+)/', $m[1], $m2);
				$rrdtool_release = $m[1];
				$rrdtool_version = $rrdtool_release;
			}
		}

		/* Get SNMP cli version */
		if ((file_exists(read_config_option('path_snmpget'))) && ((function_exists('is_executable')) && (is_executable(read_config_option('path_snmpget'))))) {
			$snmp_version = shell_exec(cacti_escapeshellcmd(read_config_option('path_snmpget')) . ' -V 2>&1');
		} else {
			$snmp_version = "<span class='deviceDown'>" . __('NET-SNMP Not Installed or its paths are not set.  Please install if you wish to monitor SNMP enabled devices.') . "</span>";
		}

		/* Check RRDtool issues */
		$rrdtool_errors = array();
		if (cacti_version_compare($rrdtool_version, get_rrdtool_version(), '<')) {
			$rrdtool_errors[] = "<span class='deviceDown'>" . __('ERROR: Installed RRDtool version does not exceed configured version.<br>Please visit the %s and select the correct RRDtool Utility Version.', "<a href='" . html_escape('settings.php?tab=general') . "'>" . __('Configuration Settings') . '</a>') . "</span>";
		}

		$graph_gif_count = db_fetch_cell('SELECT COUNT(*) FROM graph_templates_graph WHERE image_format_id = 2');
		if ($graph_gif_count > 0) {
			$rrdtool_errors[] = "<span class='deviceDown'>" . __('ERROR: RRDtool 1.2.x+ does not support the GIF images format, but %d" graph(s) and/or templates have GIF set as the image format.', $graph_gif_count) . '</span>';
		}

		/* Get spine version */
		$spine_version = 'Unknown';
		if ((file_exists(read_config_option('path_spine'))) && ((function_exists('is_executable')) && (is_executable(read_config_option('path_spine'))))) {
			$out_array = array();
			exec(cacti_escapeshellcmd(read_config_option('path_spine')) . ' --version', $out_array);
			if (cacti_sizeof($out_array) > 0) {
				$spine_version = $out_array[0];
			}
		}

		html_section_header(__('General Information'), 2);
		form_alternate_row();
		print '<td>' . __('Date') . '</td>';
		print '<td>' . date('r') . '</td>';
		form_end_row();

		api_plugin_hook_function('custom_version_info');

		form_alternate_row();
		print '<td>' . __('Cacti Version') . '</td>';
		print '<td>' . CACTI_VERSION . '</td>';
		form_end_row();

		form_alternate_row();
		print '<td>' . __('Cacti OS') . '</td>';
		print '<td>' . $config['cacti_server_os'] . '</td>';
		form_end_row();

		form_alternate_row();
		print '<td>' . __('RSA Fingerprint') . '</td>';
		print '<td>' . read_config_option('rsa_fingerprint') . '</td>';
		form_end_row();

		form_alternate_row();
		print '<td>' . __('NET-SNMP Version') . '</td>';
		print '<td>' . $snmp_version . '</td>';
		form_end_row();

		form_alternate_row();
		print '<td>' . __('RRDtool Version') . ' ' . __('Configured') . '</td>';
		print '<td>' . get_rrdtool_version() . "+</td>";
		form_end_row();

		form_alternate_row();
		print '<td>' . __('RRDtool Version') . ' ' . __('Found') . '</td>';
		print '<td>' . $rrdtool_release . '</td>';
		form_end_row();

		if (!empty($rrdtool_errors)) {
			form_alternate_row();
			print "<td>&nbsp;</td>";
			$br = '';
			print '<td>';
			foreach ($rrdtool_errors as $rrdtool_error) {
				print $br . $rrdtool_error;
				$br = '<br/>';
			}
			print '</td>';
			form_end_row();
		}

		form_alternate_row();
		print '<td>' . __('Devices') . '</td>';
		print '<td>' . number_format_i18n($host_count, -1) . '</td>';
		form_end_row();

		form_alternate_row();
		print '<td>' . __('Graphs') . '</td>';
		print '<td>' . number_format_i18n($graph_count, -1) . '</td>';
		form_end_row();

		form_alternate_row();
		print '<td>' . __('Data Sources') . '</td>';
		print '<td>';
		$data_total = 0;
		if (cacti_sizeof($data_count)) {
			foreach ($data_count as $item) {
				print $input_types[$item['type_id']] . ': ' . number_format_i18n($item['total'], -1) . '<br>';
				$data_total += $item['total'];
			}
			print __('Total: %s', number_format_i18n($data_total, -1));
		} else {
			print "<span class='deviceDown'>0</span>";
		}
		print '</td>';
		form_end_row();

		html_section_header(__('Poller Information'), 2);

		form_alternate_row();
		print '<td>' . __('Interval') . '</td>';
		print '<td>' . read_config_option('poller_interval') . '</td>';
		if (file_exists(read_config_option('path_spine')) && $poller_options[read_config_option('poller_type')] == 'spine') {
			$type = $spine_version;
		        if (!strpos($spine_version, CACTI_VERSION)) {
		    	    $type .= '<span class="textError"> (' . __('Different version of Cacti and Spine!') . ')</span>';
			}
		} else {
			$type = $poller_options[read_config_option('poller_type')];
		}
		form_end_row();

		form_alternate_row();
		print '<td>' . __('Type') . '</td>';
		print '<td>' . $type . '</td>';
		form_end_row();

		form_alternate_row();
		print '<td>' . __('Items') . '</td>';
		print '<td>';
		$total = 0;
		if (cacti_sizeof($poller_item)) {
			foreach ($poller_item as $item) {
				print __('Action[%s]', $item['action']) . ': ' . number_format_i18n($item['total'], -1) . '<br>';
				$total += $item['total'];
			}
			print __('Total: %s', number_format_i18n($total, -1));
		} else {
			print "<span class='deviceDown'>" . __('No items to poll') . "</span>";
		}
		print '</td>';
		form_end_row();

		$processes = db_fetch_cell('SELECT
			GROUP_CONCAT(
				CONCAT("' . __('Name: ') . '", name, ", ' . __('Procs: ') . '", processes) SEPARATOR "<br>"
			) AS poller
			FROM poller
			WHERE disabled=""');

		$threads = db_fetch_cell('SELECT
			GROUP_CONCAT(
				CONCAT("' . __('Name: ') . '", name, ", ' . __('Threads: ') . '", threads) SEPARATOR "<br>"
			) AS poller
			FROM poller
			WHERE disabled=""');

		form_alternate_row();
		print '<td>' . __('Concurrent Processes') . '</td>';
		print '<td>' . $processes . '</td>';
		form_end_row();

		form_alternate_row();
		print '<td>' . __('Max Threads') . '</td>';
		print '<td>' . $threads . '</td>';
		form_end_row();

		$script_servers = read_config_option('php_servers');

		form_alternate_row();
		print '<td>' . __('PHP Servers') . '</td>';
		print '<td>' . html_escape($script_servers) . '</td>';
		form_end_row();

		if ($config['poller_id'] == 1) {
			$max_connections       = db_fetch_row('SHOW GLOBAL VARIABLES LIKE "max_connections"');
			$max_local_connections = array();
		} elseif ($config['connection'] == 'online') {
			$max_connections        = db_fetch_row('SHOW GLOBAL VARIABLES LIKE "max_connections"');
			$max_local_connections  = db_fetch_row('SHOW GLOBAL VARIABLES LIKE "max_connections"', false, $local_db_cnn_id);
		} else {
			$max_connections        = array();
			$max_local_connections  = db_fetch_row('SHOW GLOBAL VARIABLES LIKE "max_connections"');
		}

		if (cacti_sizeof($max_connections)) {
			$max_connections = $max_connections['Value'];
		} else {
			$max_connections = 0;
		}

		if (cacti_sizeof($max_local_connections)) {
			$max_local_connections = $max_local_connections['Value'];
		} else {
			$max_local_connections = 0;
		}

		$total_dc_threads = db_fetch_cell("SELECT
			SUM((processes * threads) + (processes * $script_servers)) AS threads
			FROM poller
			WHERE disabled = ''");

		$recommend_mc = $total_dc_threads + 100;

		if ($recommend_mc > $max_connections) {
			if ($config['poller_id'] == 1) {
				$db_connections = '<span class="deviceDown">' . __('Main Server: Current: %s, Min Required: %s', $max_connections, $recommend_mc) . '</span>';
			} elseif ($config['connection'] == 'online') {
				$db_connections = '<span class="deviceDown">' . __('Main Server: Current: %s, Min Required: %s', $max_connections, $recommend_mc) . '</span>';
			} else {
				$db_connections = '';
			}
		} else {
			if ($config['poller_id'] == 1) {
				$db_connections = '<span class="deviceUp">' . __('Main Server: Current: %s, Min Required: %s', $max_connections, $recommend_mc) . '</span>';
			} elseif ($config['connection'] == 'online') {
				$db_connections = '<span class="deviceUp">' . __('Main Server: Current: %s, Min Required: %s', $max_connections, $recommend_mc) . '</span>';
			} else {
				$db_connections = '';
			}
		}

		if ($config['poller_id'] > 1) {
			if ($recommend_mc > $max_local_connections) {
				$db_connections .= '<br><span class="deviceDown">' . __('Local Server: Current: %s, Min Required: %s', $max_local_connections, $recommend_mc) . '</span>';
			} else {
				$db_connections .= '<br><span class="deviceUp">' . __('Local Server: Current: %s, Min Required: %s', $max_local_connections, $recommend_mc) . '</span>';
			}
		}

		form_alternate_row();
		print '<td>' . __('Minimum Connections:') . '</td>';
		print '<td>' . $db_connections . '<br>' .
			__('Assumes 100 spare connections for Web page users and other various connections.') . '<br>' .
			__('The minimum required can vary greatly if there is heavy user Graph viewing activity.') . '<br>' .
			__('Each browser tab can use upto 10 connections depending on the browser.') . '</td>';
		form_end_row();

		form_alternate_row();
		print '<td>' . __('Script Timeout') . '</td>';
		print '<td>' . read_config_option('script_timeout') . '</td>';
		form_end_row();

		form_alternate_row();
		print '<td>' . __('Max OID') . '</td>';
		print '<td>' . read_config_option('max_get_size') . '</td>';
		form_end_row();

		form_alternate_row();
		print '<td>' . __('Last Run Statistics') . '</td>';
		print '<td>' . read_config_option('stats_poller') . '</td>';
		form_end_row();

		/* Get System Memory */
		$memInfo = utilities_get_system_memory();

		//print '<pre>';print_r($memInfo);print '</pre>';

		$total_memory = 0;

		if (cacti_sizeof($memInfo)) {
			html_section_header(__('System Memory'), 2);

			foreach($memInfo as $name => $value) {
				if ($config['cacti_server_os'] == 'win32') {
					form_alternate_row();
					print "<td>$name</td>";
					print '<td>' . number_format_i18n($value/1000, 2) . " MB</td>";
					form_end_row();
				} else {
					switch($name) {
					case 'SwapTotal':
					case 'SwapFree':
					case 'Cached':
					case 'MemTotal':
					case 'MemFree':
					case 'MemAvailable':
					case 'Buffers':
					case 'Active':
					case 'Inactive':
						// Convert to GBi
						$value /= (1000 * 1000 * 1000);

						form_alternate_row();
						print "<td>$name</td>";
						print '<td>' . __('%0.2f GB', number_format_i18n($value, 2, 1000)) . '</td>';
						form_end_row();

						if ($name == 'MemTotal') {
							$total_memory = $value;
						}
					}
				}
			}

			form_end_row();
		}

		$mysql_info = utilities_get_mysql_info($config['poller_id']);

		$database  = $mysql_info['database'];
		$version   = $mysql_info['version'];
		$link_ver  = $mysql_info['link_ver'];
		$variables = $mysql_info['variables'];

		// Get Maximum Memory in GB for MySQL/MariaDB
		if ($config['poller_id'] == 1) {
			if (($database == 'MySQL' && version_compare($version, '8.0', '<')) || $database == 'MariaDB') {
				$systemMemory = db_fetch_cell('SELECT
					(@@GLOBAL.key_buffer_size
					+ @@GLOBAL.query_cache_size
					+ @@GLOBAL.tmp_table_size
					+ @@GLOBAL.innodb_buffer_pool_size
					+ @@GLOBAL.innodb_log_buffer_size) / 1024 / 1024 / 1024');

				$maxPossibleMyMemory = db_fetch_cell('SELECT (
					(@@GLOBAL.key_buffer_size
					+ @@GLOBAL.query_cache_size
					+ @@GLOBAL.tmp_table_size
					+ @@GLOBAL.innodb_buffer_pool_size
					+ @@GLOBAL.innodb_log_buffer_size
					+ @@GLOBAL.max_connections * (
						@@GLOBAL.sort_buffer_size
						+ @@GLOBAL.read_buffer_size
						+ @@GLOBAL.read_rnd_buffer_size
						+ @@GLOBAL.join_buffer_size
						+ @@GLOBAL.thread_stack
						+ @@GLOBAL.binlog_cache_size)
					) / 1024 / 1024 / 1024)');
			} else {
				$systemMemory = db_fetch_cell('SELECT
					(@@GLOBAL.key_buffer_size
					+ @@GLOBAL.tmp_table_size
					+ @@GLOBAL.innodb_buffer_pool_size
					+ @@GLOBAL.innodb_log_buffer_size) / 1024 / 1024 / 1024');

				$maxPossibleMyMemory = db_fetch_cell('SELECT (
					(@@GLOBAL.key_buffer_size
					+ @@GLOBAL.tmp_table_size
					+ @@GLOBAL.innodb_buffer_pool_size
					+ @@GLOBAL.innodb_log_buffer_size
					+ @@GLOBAL.max_connections * (
						@@GLOBAL.sort_buffer_size
						+ @@GLOBAL.read_buffer_size
						+ @@GLOBAL.read_rnd_buffer_size
						+ @@GLOBAL.join_buffer_size
						+ @@GLOBAL.thread_stack
						+ @@GLOBAL.binlog_cache_size)
					) / 1024 / 1024 / 1024)');
			}

			$clientMemory = db_fetch_cell('SELECT @@GLOBAL.max_connections * (
				@@GLOBAL.sort_buffer_size
				+ @@GLOBAL.read_buffer_size
				+ @@GLOBAL.read_rnd_buffer_size
				+ @@GLOBAL.join_buffer_size
				+ @@GLOBAL.thread_stack
				+ @@GLOBAL.binlog_cache_size) / 1024 / 1024 / 1024');
		} else {
			if (($database == 'MySQL' && version_compare($version, '8.0', '<')) || $database == 'MariaDB') {
				$maxPossibleMyMemory = db_fetch_cell('SELECT (
					(@@GLOBAL.key_buffer_size
					+ @@GLOBAL.query_cache_size
					+ @@GLOBAL.tmp_table_size
					+ @@GLOBAL.innodb_buffer_pool_size
					+ @@GLOBAL.innodb_log_buffer_size
					+ @@GLOBAL.max_connections * (
						@@GLOBAL.sort_buffer_size
						+ @@GLOBAL.read_buffer_size
						+ @@GLOBAL.read_rnd_buffer_size
						+ @@GLOBAL.join_buffer_size
						+ @@GLOBAL.thread_stack
						+ @@GLOBAL.binlog_cache_size)
					) / 1024 / 1024 / 1024)', '', false, $local_db_cnn_id);

				$systemMemory = db_fetch_cell('SELECT
					(@@GLOBAL.key_buffer_size
					+ @@GLOBAL.query_cache_size
					+ @@GLOBAL.tmp_table_size
					+ @@GLOBAL.innodb_buffer_pool_size
					+ @@GLOBAL.innodb_log_buffer_size) / 1024 / 1024 / 1024', '', false, $local_db_cnn_id);
			} else {
				$maxPossibleMyMemory = db_fetch_cell('SELECT (
					(@@GLOBAL.key_buffer_size
					+ @@GLOBAL.tmp_table_size
					+ @@GLOBAL.innodb_buffer_pool_size
					+ @@GLOBAL.innodb_log_buffer_size
					+ @@GLOBAL.max_connections * (
						@@GLOBAL.sort_buffer_size
						+ @@GLOBAL.read_buffer_size
						+ @@GLOBAL.read_rnd_buffer_size
						+ @@GLOBAL.join_buffer_size
						+ @@GLOBAL.thread_stack
						+ @@GLOBAL.binlog_cache_size)
					) / 1024 / 1024 / 1024)', '', false, $local_db_cnn_id);

				$systemMemory = db_fetch_cell('SELECT
					(@@GLOBAL.key_buffer_size
					+ @@GLOBAL.tmp_table_size
					+ @@GLOBAL.innodb_buffer_pool_size
					+ @@GLOBAL.innodb_log_buffer_size) / 1024 / 1024 / 1024', '', false, $local_db_cnn_id);
			}

			$clientMemory = db_fetch_cell('SELECT @@GLOBAL.max_connections * (
				@@GLOBAL.sort_buffer_size
				+ @@GLOBAL.read_buffer_size
				+ @@GLOBAL.read_rnd_buffer_size
				+ @@GLOBAL.join_buffer_size
				+ @@GLOBAL.thread_stack
				+ @@GLOBAL.binlog_cache_size) / 1024 / 1024 / 1024', '', false, $local_db_cnn_id);
		}

		html_section_header(__('MySQL/MariaDB Memory Statistics (Source: MySQL Tuner)'), 2);

		if ($total_memory > 0) {
			if ($maxPossibleMyMemory > ($total_memory * 0.8)) {
				form_alternate_row();
				print '<td>' . __('Max Total Memory Possible') . '</td>';
				print '<td class="deviceDown">' . __('%0.2f GB', number_format_i18n($maxPossibleMyMemory, 2, 1000)) . '</td>';
				form_end_row();
				form_alternate_row();
				print '<td></td>';
				print '<td>' . __('Reduce MySQL/MariaDB Memory to less than 80% of System Memory.  Preserve additional Cache Memory for RRDfiles if the Database is on the same system as the RRDfiles.  See Core and Client Totals below for explanation of calculation method.') . '</td>';
				form_end_row();
			} else {
				form_alternate_row();
				print '<td>' . __('Max Total Memory Possible') . '</td>';
				print '<td class="deviceUp">' . __('%0.2f GB', number_format_i18n($maxPossibleMyMemory, 2, 1000)) . '</td>';
				form_end_row();
			}
		} else {
			form_alternate_row();
			print '<td>' . __('Max Total Memory Possible') . '</td>';
			print '<td>' . __('%0.2f GB', number_format_i18n($maxPossibleMyMemory, 2, 1000)) . '</td>';
			form_end_row();
		}

		if ($total_memory > 0) {
			if ($systemMemory > ($total_memory * 0.8)) {
				form_alternate_row();
				print '<td>' . __('Max Core Memory Possible') . '</td>';
				print '<td class="deviceDown">' . __('%0.2f GB', number_format_i18n($systemMemory, 2, 1000)) . '&nbsp;&nbsp;(' . __('Reduce Total Core Memory') . '</td>';
				form_end_row();
			} else {
				form_alternate_row();
				print '<td>' . __('Max Core Memory Possible') . '</td>';
				print '<td class="deviceUp">' . __('%0.2f GB', number_format_i18n($systemMemory, 2, 1000)) . '</td>';
				form_end_row();
			}

			form_alternate_row();
			print '<td>' . __('Calculation Formula') . '</td>';
			print '<td>SELECT @@GLOBAL.key_buffer_size + <br>@@GLOBAL.query_cache_size + <br>@@GLOBAL.tmp_table_size + <br>@@GLOBAL.innodb_buffer_pool_size + <br>@@GLOBAL.innodb_log_buffer_size</td>';
			form_end_row();

			if ($clientMemory > ($total_memory * 0.8)) {
				form_alternate_row();
				print '<td>' . __('Max Connection Memory Possible') . '</td>';
				print '<td class="deviceDown">' . __('%0.2f GB', number_format_i18n($clientMemory, 2, 1000)) . '&nbsp;&nbsp;(' . __('Reduce Total Client Memory') . ')</td>';
				form_end_row();
			} else {
				form_alternate_row();
				print '<td>' . __('Max Connection Memory Possible') . '</td>';
				print '<td class="deviceUp">' . __('%0.2f GB', number_format_i18n($clientMemory, 2, 1000)) . '</td>';
				form_end_row();
			}

			form_alternate_row();
			print '<td>' . __('Calculation Formula') . '</td>';
			print '<td>SELECT @@GLOBAL.max_connections * (<br>@@GLOBAL.sort_buffer_size + <br>@@GLOBAL.read_buffer_size + <br>@@GLOBAL.read_rnd_buffer_size + <br>@@GLOBAL.join_buffer_size + <br>@@GLOBAL.thread_stack + <br>@@GLOBAL.binlog_cache_size)</td>';
			form_end_row();
		} else {
			form_alternate_row();
			print '<td>' . __('Max Core Memory Possible') . '</td>';
			print '<td class="deviceUp">' . __('%0.2f GB', number_format_i18n($systemMemory, 2, 1000)) . '</td>';
			form_end_row();

			form_alternate_row();
			print '<td>' . __('Max Connection Memory Possible') . '</td>';
			print '<td>' . __('%0.2f GB', number_format_i18n($clientMemory, 2, 1000)) . '</td>';
			form_end_row();
		}

		html_section_header(__('PHP Information'), 2);

		form_alternate_row();
		print '<td>' . __('PHP Version') . '</td>';
		if (version_compare(PHP_VERSION, '5.5.0') >= 0) {
			print '<td>' . PHP_VERSION . '</td>';
		} else {
			print '<td>' . PHP_VERSION . "</br><span class='deviceDown'>" . __('PHP Version 5.5.0+ is recommended due to strong password hashing support.') . "</span></td>";
		}
		form_end_row();

		form_alternate_row();
		print '<td>' . __('PHP OS') . '</td>';
		print '<td>' . PHP_OS . '</td>';
		form_end_row();

		form_alternate_row();
		print '<td>' . __('PHP uname') . '</td>';
		print '<td>';
		if (function_exists('php_uname')) {
			print php_uname();
		} else {
			print __('N/A');
		}
		print '</td>';
		form_end_row();

		form_alternate_row();
		print '<td>' . __('PHP SNMP') . '</td>';
		print '<td>';
		if (function_exists('snmpget')) {
			print __('Installed. <span class="deviceDown">Note: If you are planning on using SNMPv3, you must remove php-snmp and use the Net-SNMP toolset.</span>');
		} else {
			print __('Not Installed');
		}
		print '</td>';
		form_end_row();

		form_alternate_row();
		print "<td>max_execution_time</td>";
		print '<td>' . ini_get('max_execution_time') . '</td>';
		form_end_row();

		form_alternate_row();
		print "<td>memory_limit</td>";
		print '<td>' . ini_get('memory_limit');

		/* Calculate memory suggestion based off of data source count */
		$memory_suggestion = $data_total * 32768;

		/* Set minimum - 16M */
		if ($memory_suggestion < 16777216) {
			$memory_suggestion = 16777216;
		}

		/* Set maximum - 512M */
		if ($memory_suggestion > 536870912) {
			$memory_suggestion = 536870912;
		}

		/* Suggest values in 8M increments */
		$memory_suggestion = round($memory_suggestion / 8388608) * 8388608;
		if (memory_bytes(ini_get('memory_limit')) < $memory_suggestion) {
			print "<br><span class='deviceDown'>";
			if ((ini_get('memory_limit') == -1)) {
				print __("You've set memory limit to 'unlimited'.") . "<br>";
			}
			print __('It is highly suggested that you alter you php.ini memory_limit to %s or higher.', memory_readable($memory_suggestion)) . ' <br/>' .
				__('This suggested memory value is calculated based on the number of data source present and is only to be used as a suggestion, actual values may vary system to system based on requirements.');
			print '</span><br>';
		}
		print '</td>';
		form_end_row();

		utilities_get_mysql_recommendations();
	} elseif (get_request_var('tab') == 'dbstatus') {
		$status = db_fetch_assoc('show global status');

		print "<table id='tables' class='cactiTable' style='width:100%'>";
		print '<thead>';
		print "<tr class='tableHeader'>";
		print "  <th class='tableSubHeaderColumn'>" . __('Variable Name') . '</th>';
		print "  <th class='tableSubHeaderColumn'>" . __('Value') . '</th>';
		print '</tr>';
		print '</thead>';

		foreach($status as $s) {
			form_alternate_row();
			print '<td>' . $s['Variable_name'] . '</td>';
			print '<td>' . (is_numeric($s['Value']) ? number_format_i18n($s['Value'], -1):$s['Value']) . '</td>';
			form_end_row();
		}
	} elseif (get_request_var('tab') == 'dbsettings') {
		$status = db_fetch_assoc('show global variables');

		print "<table id='tables' class='cactiTable' style='width:100%'>";
		print '<thead>';
		print "<tr class='tableHeader'>";
		print "  <th class='tableSubHeaderColumn'>" . __('Variable Name') . '</th>';
		print "  <th class='tableSubHeaderColumn'>" . __('Value') . '</th>';
		print '</tr>';
		print '</thead>';

		foreach($status as $s) {
			form_alternate_row();
			print '<td>' . $s['Variable_name'] . '</td>';

			if (strlen($s['Value']) > 70) {
				$s['Value'] = str_replace(',', ', ', $s['Value']);
			}
			print '<td>' . (is_numeric($s['Value']) ? number_format_i18n($s['Value'], -1):$s['Value']) . '</td>';
			form_end_row();
		}
	} elseif (get_request_var('tab') == 'changelog') {
		$changelog = file($config['base_path'] . '/CHANGELOG');

		foreach($changelog as $s) {
			if (strlen(trim($s)) && stripos($s, 'CHANGELOG') === false) {
				if (strpos($s, '-') === false) {
					html_section_header(__('Version %s', $s), 2);
				} else {
					form_alternate_row();
					print '<td>' . $s . '</td>';
					form_end_row();
				}
			}
		}
	} elseif (get_request_var('tab') == 'database') {
		/* Get table status */
		if ($config['poller_id'] == 1) {
			$tables = db_fetch_assoc('SELECT *
				FROM information_schema.tables
				WHERE table_schema = SCHEMA()');
		} else {
			$tables = db_fetch_assoc('SELECT *
				FROM information_schema.tables
				WHERE table_schema = SCHEMA()', false, $local_db_cnn_id);
		}

		html_section_header(__('MySQL Table Information - Sizes in KBytes'), 2);

		form_alternate_row();
		print "		<td colspan='2' style='text-align:left;padding:0px'>";

		if (cacti_sizeof($tables)) {
			print "<table id='tables' class='cactiTable' style='width:100%'>";
			print '<thead>';
			print "<tr class='tableHeader'>";
			print "  <th class='tableSubHeaderColumn'>" . __('Name') . '</th>';
			print "  <th class='tableSubHeaderColumn'>" . __('Engine') . '</th>';
			print "  <th class='tableSubHeaderColumn right'>" . __('Rows') . '</th>';
			print "  <th class='tableSubHeaderColumn right'>" . __('Avg Row Length') . '</th>';
			print "  <th class='tableSubHeaderColumn right'>" . __('Data Length') . '</th>';
			print "  <th class='tableSubHeaderColumn right'>" . __('Index Length') . '</th>';
			print "  <th class='tableSubHeaderColumn'>" . __('Collation') . '</th>';
			print "  <th class='tableSubHeaderColumn'>" . __('Row Format') . '</th>';
			print "  <th class='tableSubHeaderColumn'>" . __('Comment') . '</th>';
			print '</tr>';
			print '</thead>';
			foreach ($tables as $table) {
				form_alternate_row();
				print '<td>' . $table['TABLE_NAME'] . '</td>';
				print '<td>' . $table['ENGINE'] . '</td>';
				print '<td class="right">' . number_format_i18n($table['TABLE_ROWS'], -1) . '</td>';
				print '<td class="right">' . number_format_i18n($table['AVG_ROW_LENGTH'], -1) . '</td>';
				print '<td class="right">' . number_format_i18n($table['DATA_LENGTH'], -1) . '</td>';
				print '<td class="right">' . number_format_i18n($table['INDEX_LENGTH'], -1) . '</td>';
				print '<td>' . $table['TABLE_COLLATION'] . '</td>';
				print '<td>' . $table['ROW_FORMAT'] . '</td>';
				print '<td>' . $table['TABLE_COMMENT'] . '</td>';
				form_end_row();
			}

			print "</table>";
		} else {
			print __('Unable to retrieve table status');
		}
		print '</td>';

		form_end_row();
	} else {
		$php_info = utilities_php_modules();

		html_section_header(__('PHP Module Information'), 2);
		$php_info = str_replace(
			array('width="600"', 'th colspan="2"', ','),
			array('', 'th class="subHeaderColumn"', ', '),
			$php_info
		);
		print "<tr><td colspan='2'>" . $php_info . '</td></tr>';
	}

	html_end_box();

	?>
	<script type='text/javascript'>
	$(function() {
		$('#tables').tablesorter({
			widgets: ['zebra'],
			widgetZebra: { css: ['even', 'odd'] },
			headerTemplate: '<div class="textSubHeaderDark">{content} {icon}</div>',
			cssIconAsc: 'fa-sort-up',
			cssIconDesc: 'fa-sort-down',
			cssIconNone: 'fa-sort',
			cssIcon: 'fa'
		});
	});
	</script>
	<?php

	bottom_footer();
=======
function utilities_view_logfile() : void {
	clog_view_logfile();
>>>>>>> origin/fix/jquery-deprecations
}

function utilities_clear_logfile() : void {
	load_current_session_value('refresh', 'sess_logfile_refresh', read_config_option('log_refresh_interval'));

	$refresh['seconds'] = grv('refresh');
	$refresh['page']    = 'utilities.php?action=view_logfile';
	$refresh['logout']  = 'false';

	set_page_refresh($refresh);

	top_header();

	$logfile = read_config_option('path_cactilog');

	if ($logfile == '') {
		$logfile = './log/cacti.log';
	}

	html_start_box(__('Clear Cacti Log'), '100%', false, 3, 'center', '');

	if (file_exists($logfile)) {
		if (is_writable($logfile)) {
			// fill in the current date for printing in the log
			if (defined('CACTI_DATE_TIME_FORMAT')) {
				$date = date(CACTI_DATE_TIME_FORMAT);
			} else {
				$date = date('Y-m-d H:i:s');
			}

			$log_fh = fopen($logfile, 'w');
			fwrite($log_fh, __('%s - WEBUI NOTE: Cacti Log Cleared from Web Management Interface.', $date) . PHP_EOL);
			fclose($log_fh);
			print '<tr><td>' . __('Cacti Log Cleared') . '</td></tr>';
		} else {
			print "<tr><td class='deviceDown'><b>" . __('Error: Unable to clear log, no write permissions.') . '<b></td></tr>';
		}
	} else {
		print "<tr><td class='deviceDown'><b>" . __('Error: Unable to clear log, file does not exist.') . '</b></td></tr>';
	}

	html_end_box();
}

function create_data_query_filter(string $session_var) : array {
	global $item_rows;

	$all     = ['-1' => __('All')];
	$any     = ['-1' => __('Any')];
	$none    = ['0'  => __('None')];
	$deleted = ['-2' => __('Deleted/Invalid')];

	$sites   = array_rekey(
		db_fetch_assoc('SELECT id, name
			FROM sites
			ORDER BY name'),
		'id', 'name'
	);
	$sites   = $any + $none + $sites;

	$status = [
		'-1' => __('Any'),
		'1'  => __('Enabled'),
		'0'  => __('Disabled')
	];

	$pactions = [
		'-1' => __('Any'),
		'0'  => __('SNMP'),
		'1'  => __('Script'),
		'2'  => __('Script Server')
	];

	$sql_where  = '';
	$sql_params = [];

	if (isrv('host_id')) {
		$host_id = grv('host_id');
	} elseif (isset($_SESSION[$session_var . '_host_id'])) {
		$host_id = $_SESSION[$session_var . '_host_id'];
	} else {
		$host_id = '-1';
	}

	if ($host_id > 0) {
		// for the templates dropdown
		$sql_where .= ($sql_where != '' ? ' AND ' : 'WHERE ') . 'h.id = ?';
		$sql_params[] = $host_id;

		$hostname = db_fetch_cell_prepared('SELECT description
			FROM host
			WHERE id = ?',
			[$host_id]);
	} elseif ($host_id == '' || $host_id == '-1') {
		$host_id  = '-1';
		$hostname = __('Any');
	} else {
		$host_id  = '0';
		$hostname = __('None');
	}

	if (grv('site_id') >= 0) {
		$sql_where .= ($sql_where != '' ? ' AND ' : 'WHERE ') . 'site_id = ?';
		$sql_params[] = gfrv('site_id');
	}

	$data_queries = array_rekey(
		db_fetch_assoc_prepared("SELECT DISTINCT sq.id, sq.name
			FROM host_snmp_cache AS hsc
			INNER JOIN snmp_query AS sq
			ON hsc.snmp_query_id = sq.id
			INNER JOIN host AS h
			ON hsc.host_id = h.id
			$sql_where
			ORDER by sq.name",
			$sql_params),
		'id', 'name'
	);

	$data_queries = $any + $data_queries;

	if (isrv('with_index')) {
		$value = gnrv('with_index');
	} else {
		$value = read_config_option('default_has') == 'on' ? 'true' : 'false';
	}

	return [
		'rows' => [
			[
				'site_id' => [
					'method'        => 'drop_array',
					'friendly_name' => __('Site'),
					'filter'        => FILTER_VALIDATE_INT,
					'default'       => '-1',
					'pageset'       => true,
					'array'         => $sites,
					'value'         => '-1'
				],
				'host_id' => [
					'method'        => 'drop_callback',
					'friendly_name' => __('Device'),
					'filter'        => FILTER_VALIDATE_INT,
					'default'       => '-1',
					'pageset'       => true,
					'sql'           => 'SELECT DISTINCT id, description AS name FROM host ORDER BY description',
					'action'        => 'ajax_hosts',
					'id'            => $host_id,
					'value'         => $hostname,
					'on_change'     => 'applyFilter()'
				],
				'snmp_query_id' => [
					'method'         => 'drop_array',
					'friendly_name'  => __('Data Query'),
					'filter'         => FILTER_VALIDATE_INT,
					'default'        => '-1',
					'pageset'        => true,
					'array'          => $data_queries,
					'value'          => '-1'
				],
			],
			[
				'filter' => [
					'method'         => 'textbox',
					'friendly_name'  => __('Search'),
					'filter'         => FILTER_DEFAULT,
					'placeholder'    => __('Enter a search term'),
					'size'           => '30',
					'default'        => '',
					'pageset'        => true,
					'max_length'     => '120',
					'value'          => ''
				],
				'rows' => [
					'method'         => 'drop_array',
					'friendly_name'  => __('Entries'),
					'filter'         => FILTER_VALIDATE_INT,
					'default'        => '-1',
					'pageset'        => true,
					'array'          => $item_rows,
					'value'          => '-1'
				],
				'with_index' => [
					'method'         => 'filter_checkbox',
					'friendly_name'  => __('Include Index'),
					'filter'         => FILTER_VALIDATE_REGEXP,
					'filter_options' => ['options' => ['regexp' => '(true|false)']],
					'default'        => '',
					'pageset'        => true,
					'value'          => $value
				]
			]
		],
		'buttons' => [
			'go' => [
				'method'  => 'submit',
				'display' => __('Go'),
				'title'   => __('Apply Filter to Table'),
			],
			'clear' => [
				'method'  => 'button',
				'display' => __('Clear'),
				'title'   => __('Reset Filter to Default Values'),
			]
		]
	];
}

function draw_data_query_filter(bool $render = false) : void {
	$filters = create_data_query_filter('sess_usnmp');

	// create the page filter
	$pageFilter = new CactiTableFilter(__('Data Query Cache Items'), 'utilities.php?action=view_snmp_cache', 'form_snmpcache', 'sess_usnmp');

	$pageFilter->rows_label = __('Entries');
	$pageFilter->set_filter_array($filters);

	if ($render) {
		$pageFilter->render();
	} else {
		$pageFilter->sanitize();
	}
}

function utilities_view_snmp_cache() : void {
	global $poller_actions, $item_rows;

	draw_data_query_filter(true);

	if (grv('rows') == '-1') {
		$rows = read_config_option('num_rows_table');
	} else {
		$rows = grv('rows');
	}

	$sql_where  = '';
	$sql_params = [];

	// filter by host
	if (grv('host_id') == '0') {
		$sql_where .= ($sql_where != '' ? ' AND ' : 'WHERE ') . ' h.id=0';
	} elseif (grv('host_id') > 0) {
		$sql_where .= ($sql_where != '' ? ' AND ' : 'WHERE ') . ' h.id = ?';
		$sql_params[] = grv('host_id');
	}

	// filter by query name
	if (grv('snmp_query_id') == '-1') {
		// Show all items
	} elseif (!ierv('snmp_query_id')) {
		$sql_where .= ($sql_where != '' ? ' AND ' : 'WHERE ') . ' hsc.snmp_query_id=' . grv('snmp_query_id');
	}

	// filter by search string
	if (grv('filter') != '') {
		$sql_where .= ($sql_where != '' ? ' AND ' : 'WHERE ') . ' (
			h.description LIKE ?
			OR sq.name LIKE ?
			OR hsc.field_name LIKE ?
			OR hsc.field_value LIKE ?
			OR hsc.oid LIKE ?';

		$sql_params[] = '%' . grv('filter') . '%';
		$sql_params[] = '%' . grv('filter') . '%';
		$sql_params[] = '%' . grv('filter') . '%';
		$sql_params[] = '%' . grv('filter') . '%';
		$sql_params[] = '%' . grv('filter') . '%';

<<<<<<< HEAD
		$('#form_snmpcache').submit(function(event) {
			event.preventDefault();
			applyFilter();
		});
	});
	</script>
	<?php

	html_start_box(__('Data Query Cache Items'), '100%', '', '3', 'center', '');

	?>
	<tr class='even noprint'>
		<td>
		<form id='form_snmpcache' action='utilities.php'>
			<table class='filterTable'>
				<tr>
					<?php print html_host_filter(get_request_var('host_id'));?>
					<td>
						<?php print __('Query Name');?>
					</td>
					<td>
						<select id='snmp_query_id' onChange='applyFilter()'>
							<option value='-1'<?php if (get_request_var('host_id') == '-1') {?> selected<?php }?>><?php print __('Any');?></option>
							<?php
							if (get_request_var('host_id') == -1) {
								$snmp_queries = db_fetch_assoc('SELECT DISTINCT sq.id, sq.name
									FROM host_snmp_cache AS hsc
									INNER JOIN snmp_query AS sq
									ON hsc.snmp_query_id = sq.id
									INNER JOIN host AS h
									ON hsc.host_id = h.id
									ORDER by sq.name');
							} else {
								$snmp_queries = db_fetch_assoc_prepared("SELECT DISTINCT sq.id, sq.name
									FROM host_snmp_cache AS hsc
									INNER JOIN snmp_query AS sq
									ON hsc.snmp_query_id = sq.id
									INNER JOIN host AS h
									ON hsc.host_id = h.id
									WHERE h.id = ?
									ORDER by sq.name",
									array(get_request_var('host_id')));
							}

							if (cacti_sizeof($snmp_queries)) {
								foreach ($snmp_queries as $snmp_query) {
									print "<option value='" . $snmp_query['id'] . "'"; if (get_request_var('snmp_query_id') == $snmp_query['id']) { print ' selected'; } print '>' . html_escape($snmp_query['name']) . '</option>';
								}
							}
							?>
						</select>
					</td>
					<td>
						<span>
							<input type='submit' class='ui-button ui-corner-all ui-widget' id='refresh' value='<?php print __esc_x('Button: use filter settings', 'Go');?>' title='<?php print __esc('Set/Refresh Filters');?>'>
							<input type='button' class='ui-button ui-corner-all ui-widget' id='clear' value='<?php print __esc_x('Button: reset filter settings', 'Clear');?>' title='<?php print __esc('Clear Filters');?>'>
						</span>
					</td>
				</tr>
			</table>
			<table class='filterTable'>
				<tr>
					<td>
						<?php print __('Search');?>
					</td>
					<td>
						<input type='text' class='ui-state-default ui-corner-all' id='filter' size='25' value='<?php print html_escape_request_var('filter');?>'>
					</td>
					<td>
						<?php print __('Rows');?>
					</td>
					<td>
						<select id='rows' onChange='applyFilter()'>
							<option value='-1'<?php print (get_request_var('rows') == '-1' ? ' selected>':'>') . __('Default');?></option>
							<?php
							if (cacti_sizeof($item_rows)) {
								foreach ($item_rows as $key => $value) {
									print "<option value='" . $key . "'"; if (get_request_var('rows') == $key) { print ' selected'; } print '>' . html_escape($value) . '</option>';
								}
							}
							?>
						</select>
					</td>
					<td>
						<input type='checkbox' id='with_index' onChange='applyFilter()' title='<?php print __esc('Allow the search term to include the index column');?>' <?php if (get_request_var('with_index') == 1) { print ' checked '; }?>>
						<label for='with_index'><?php print __('Include Index') ?></label>
					</td>
				</tr>
			</table>
			<input type='hidden' name='action' value='view_snmp_cache'>
		</form>
		</td>
	</tr>
	<?php

	html_end_box();

	$sql_where = '';

	/* filter by host */
	if (get_request_var('host_id') == '-1') {
		/* Show all items */
	} elseif (get_request_var('host_id') == '0') {
		$sql_where .= ' AND h.id=0';
	} elseif (!isempty_request_var('host_id')) {
		$sql_where .= ' AND h.id=' . get_request_var('host_id');
	}

	/* filter by query name */
	if (get_request_var('snmp_query_id') == '-1') {
		/* Show all items */
	} elseif (!isempty_request_var('snmp_query_id')) {
		$sql_where .= ' AND hsc.snmp_query_id=' . get_request_var('snmp_query_id');
	}

	/* filter by search string */
	if (get_request_var('filter') != '') {
		$sql_where .= ' AND (
			h.description LIKE '      . db_qstr('%' . get_request_var('filter') . '%') . '
			OR sq.name LIKE '         . db_qstr('%' . get_request_var('filter') . '%') . '
			OR hsc.field_name LIKE '  . db_qstr('%' . get_request_var('filter') . '%') . '
			OR hsc.field_value LIKE ' . db_qstr('%' . get_request_var('filter') . '%') . '
			OR hsc.oid LIKE '         . db_qstr('%' . get_request_var('filter') . '%');

		if (get_request_var('with_index') == 1) {
			$sql_where .= ' OR hsc.snmp_index LIKE ' . db_qstr('%' . get_request_var('filter') . '%');
||||||| 7dd05ee12
		$('#form_snmpcache').submit(function(event) {
			event.preventDefault();
			applyFilter();
		});
	});
	</script>
	<?php

	html_start_box(__('Data Query Cache Items'), '100%', '', '3', 'center', '');

	?>
	<tr class='even noprint'>
		<td>
		<form id='form_snmpcache' action='utilities.php'>
			<table class='filterTable'>
				<tr>
					<?php print html_host_filter(get_request_var('host_id'));?>
					<td>
						<?php print __('Query Name');?>
					</td>
					<td>
						<select id='snmp_query_id' onChange='applyFilter()'>
							<option value='-1'<?php if (get_request_var('host_id') == '-1') {?> selected<?php }?>><?php print __('Any');?></option>
							<?php
							if (get_request_var('host_id') == -1) {
								$snmp_queries = db_fetch_assoc('SELECT DISTINCT sq.id, sq.name
									FROM host_snmp_cache AS hsc
									INNER JOIN snmp_query AS sq
									ON hsc.snmp_query_id=sq.id
									INNER JOIN host AS h
									ON hsc.host_id=h.id
									ORDER by sq.name');
							} else {
								$snmp_queries = db_fetch_assoc_prepared("SELECT DISTINCT sq.id, sq.name
									FROM host_snmp_cache AS hsc
									INNER JOIN snmp_query AS sq
									ON hsc.snmp_query_id=sq.id
									INNER JOIN host AS h
									ON hsc.host_id=h.id
									ORDER by sq.name", array(get_request_var('host_id')));
							}

							if (cacti_sizeof($snmp_queries)) {
								foreach ($snmp_queries as $snmp_query) {
									print "<option value='" . $snmp_query['id'] . "'"; if (get_request_var('snmp_query_id') == $snmp_query['id']) { print ' selected'; } print '>' . html_escape($snmp_query['name']) . '</option>';
								}
							}
							?>
						</select>
					</td>
					<td>
						<span>
							<input type='submit' class='ui-button ui-corner-all ui-widget' id='refresh' value='<?php print __esc_x('Button: use filter settings', 'Go');?>' title='<?php print __esc('Set/Refresh Filters');?>'>
							<input type='button' class='ui-button ui-corner-all ui-widget' id='clear' value='<?php print __esc_x('Button: reset filter settings', 'Clear');?>' title='<?php print __esc('Clear Filters');?>'>
						</span>
					</td>
				</tr>
			</table>
			<table class='filterTable'>
				<tr>
					<td>
						<?php print __('Search');?>
					</td>
					<td>
						<input type='text' class='ui-state-default ui-corner-all' id='filter' size='25' value='<?php print html_escape_request_var('filter');?>'>
					</td>
					<td>
						<?php print __('Rows');?>
					</td>
					<td>
						<select id='rows' onChange='applyFilter()'>
							<option value='-1'<?php print (get_request_var('rows') == '-1' ? ' selected>':'>') . __('Default');?></option>
							<?php
							if (cacti_sizeof($item_rows)) {
								foreach ($item_rows as $key => $value) {
									print "<option value='" . $key . "'"; if (get_request_var('rows') == $key) { print ' selected'; } print '>' . html_escape($value) . '</option>';
								}
							}
							?>
						</select>
					</td>
					<td>
						<input type='checkbox' id='with_index' onChange='applyFilter()' title='<?php print __esc('Allow the search term to include the index column');?>' <?php if (get_request_var('with_index') == 1) { print ' checked '; }?>>
						<label for='with_index'><?php print __('Include Index') ?></label>
					</td>
				</tr>
			</table>
			<input type='hidden' name='action' value='view_snmp_cache'>
		</form>
		</td>
	</tr>
	<?php

	html_end_box();

	$sql_where = '';

	/* filter by host */
	if (get_request_var('host_id') == '-1') {
		/* Show all items */
	} elseif (get_request_var('host_id') == '0') {
		$sql_where .= ' AND h.id=0';
	} elseif (!isempty_request_var('host_id')) {
		$sql_where .= ' AND h.id=' . get_request_var('host_id');
	}

	/* filter by query name */
	if (get_request_var('snmp_query_id') == '-1') {
		/* Show all items */
	} elseif (!isempty_request_var('snmp_query_id')) {
		$sql_where .= ' AND hsc.snmp_query_id=' . get_request_var('snmp_query_id');
	}

	/* filter by search string */
	if (get_request_var('filter') != '') {
		$sql_where .= ' AND (
			h.description LIKE '      . db_qstr('%' . get_request_var('filter') . '%') . '
			OR sq.name LIKE '         . db_qstr('%' . get_request_var('filter') . '%') . '
			OR hsc.field_name LIKE '  . db_qstr('%' . get_request_var('filter') . '%') . '
			OR hsc.field_value LIKE ' . db_qstr('%' . get_request_var('filter') . '%') . '
			OR hsc.oid LIKE '         . db_qstr('%' . get_request_var('filter') . '%');

		if (get_request_var('with_index') == 1) {
			$sql_where .= ' OR hsc.snmp_index LIKE ' . db_qstr('%' . get_request_var('filter') . '%');
=======
		if (grv('with_index') == 1) {
			$sql_where .= ' OR hsc.snmp_index LIKE ?';
			$sql_params[] = '%' . grv('filter') . '%';
>>>>>>> origin/fix/jquery-deprecations
		}

		$sql_where .= ')';
	}

	$total_rows = db_fetch_cell_prepared("SELECT COUNT(*)
		FROM host_snmp_cache AS hsc
		INNER JOIN snmp_query AS sq
		ON hsc.snmp_query_id = sq.id
		INNER JOIN host AS h
		ON hsc.host_id = h.id
		$sql_where",
		$sql_params);

	$snmp_cache_sql = "SELECT hsc.*, h.description, sq.name
		FROM host_snmp_cache AS hsc
		INNER JOIN snmp_query AS sq
		ON hsc.snmp_query_id = sq.id
		INNER JOIN host AS h
		ON hsc.host_id = h.id
		$sql_where
		LIMIT " . ($rows * (grv('page') - 1)) . ',' . $rows;

	$snmp_cache = db_fetch_assoc_prepared($snmp_cache_sql, $sql_params);

	$display_text = [
		__('Device'),
		__('Data Query Name'),
		__('Index'),
		__('Field Name'),
		__('Field Value'),
		__('OID')
	];

	$nav = html_nav_bar('utilities.php?action=view_snmp_cache&host_id=' . grv('host_id') . '&filter=' . grv('filter'), MAX_DISPLAY_PAGES, grv('page'), $rows, $total_rows, 6, __('Entries'), 'page', 'main');

	print $nav;

	html_start_box('', '100%', false, 3, 'center', '');

	html_header($display_text, 1);

	$i = 0;

	if (cacti_sizeof($snmp_cache)) {
		foreach ($snmp_cache as $item) {
			form_alternate_row('line' . $i, true);

			form_selectable_cell(filter_value($item['description'], grv('filter')), $i);
			form_selectable_cell(filter_value($item['name'], grv('filter')), $i);
			form_selectable_ecell($item['snmp_index'], $i);
			form_selectable_cell(filter_value($item['field_name'], grv('filter')), $i);
			form_selectable_cell(filter_value($item['field_value'], grv('filter')), $i);
			form_selectable_cell(filter_value($item['oid'], grv('filter')), $i);

			form_end_row();
		}
	} else {
		print '<tr class="tableRow odd"><td colspan="6"><em>' . __('No Data Query Entries Found') . '</em></td></tr>';
	}

	html_end_box();

	if (cacti_sizeof($snmp_cache)) {
		print $nav;
	}
}

function utilities_view_poller_cache() : void {
	global $poller_actions, $item_rows;

	draw_poller_cache_filter(true);

	if (grv('rows') == '-1') {
		$rows = read_config_option('num_rows_table');
	} else {
		$rows = grv('rows');
	}

	// form the 'where' clause for our main sql query
	$sql_where  = '';
	$sql_params = [];

	if (grv('site_id') > 0) {
		$sql_where .= ($sql_where != '' ? ' AND ' : ' WHERE') . ' h.site_id = ?';
		$sql_params[] = grv('site_id');
	}

	if (grv('poller_action') != '-1') {
		$sql_where .= ($sql_where != '' ? ' AND ' : ' WHERE') . ' pi.action = ?';
		$sql_params[] = grv('poller_action');
	}

	if (grv('host_id') == '0') {
		$sql_where .= ($sql_where != '' ? ' AND ' : ' WHERE') . ' pi.host_id = 0';
	} elseif (grv('host_id') > 0) {
		$sql_where .= ($sql_where != '' ? ' AND ' : ' WHERE') . ' pi.host_id = ?';
		$sql_params[] = grv('host_id');
	}

	if (grv('template_id') == '0') {
		$sql_where .= ($sql_where != '' ? ' AND ' : 'WHERE ') . ' dtd.data_template_id = 0';
	} elseif (grv('template_id') > 0) {
		$sql_where .= ($sql_where != '' ? ' AND ' : 'WHERE ') . ' dtd.data_template_id = ?';
		$sql_params[] = grv('template_id');
	}

	if (grv('status') == 0) {
		$sql_where .= ($sql_where != '' ? ' AND ' : 'WHERE ') . ' (h.disabled = "on" OR dtd.active = "")';
	} elseif (grv('status') == 1) {
		$sql_where .= ($sql_where != '' ? ' AND ' : 'WHERE ') . ' (h.disabled = "" AND dtd.active = "on")';
	}

	if (grv('filter') != '') {
		if (grv('host_id') > 0) {
			$sql_where .= ($sql_where != '' ? ' AND ' : ' WHERE ') .
				'(
					dtd.name_cache LIKE ? OR
					pi.arg1 LIKE ? OR
					pi.rrd_path LIKE ?
				)';

<<<<<<< HEAD
	if (get_request_var('filter') != '') {
		if (get_request_var('host_id') > 0) {
			$sql_where .= ($sql_where != '' ? ' AND ':' WHERE') . ' (
				dtd.name_cache LIKE '   . db_qstr('%' . get_request_var('filter') . '%') . '
				OR pi.arg1 LIKE '       . db_qstr('%' . get_request_var('filter') . '%') . '
				OR pi.rrd_path  LIKE '  . db_qstr('%' . get_request_var('filter') . '%') . ')';
||||||| 7dd05ee12
	if (get_request_var('filter') != '') {
		if (get_request_var('host_id') > 0) {
			$sql_where .= ($sql_where != '' ? ' AND ':' WHERE') . ' (
				dtd.name_cache LIKE '   . db_qstr('%' . get_request_var('filter') . '%') . '
				OR pi.arg1 LIKE '       . db_qstr('%' . get_request_var('filter') . '%') . '
				OR pi.rrd_path  LIKE '  . db_qstr('%' . get_request_var('filter') . '%') . ')';
=======
			$sql_params[] = '%' . grv('filter') . '%';
			$sql_params[] = '%' . grv('filter') . '%';
			$sql_params[] = '%' . grv('filter') . '%';
>>>>>>> origin/fix/jquery-deprecations
		} else {
			$sql_where .= ($sql_where != '' ? ' AND ' : ' WHERE ') .
				'(
					dtd.name_cache LIKE ? OR
					h.description LIKE ? OR
					pi.arg1 LIKE ? OR
					pi.hostname LIKE ? OR
					pi.rrd_path LIKE ?
				)';

			$sql_params[] = '%' . grv('filter') . '%';
			$sql_params[] = '%' . grv('filter') . '%';
			$sql_params[] = '%' . grv('filter') . '%';
			$sql_params[] = '%' . grv('filter') . '%';
			$sql_params[] = '%' . grv('filter') . '%';
		}
	}

	$sql = "SELECT COUNT(*)
		FROM poller_item AS pi
		INNER JOIN data_local AS dl
		ON dl.id = pi.local_data_id
		LEFT JOIN data_template_data AS dtd
		ON dtd.local_data_id = pi.local_data_id
		LEFT JOIN host AS h
		ON pi.host_id = h.id
		$sql_where";

	$total_rows = get_total_row_data($_SESSION[SESS_USER_ID], $sql, $sql_params, 'poller_item');

	$poller_sql = "SELECT pi.*, dtd.name_cache, h.description, h.id AS host_id
		FROM poller_item AS pi
		INNER JOIN data_local AS dl
		ON dl.id = pi.local_data_id
		LEFT JOIN data_template_data AS dtd
		ON dtd.local_data_id = pi.local_data_id
		LEFT JOIN host AS h
		ON pi.host_id = h.id
		$sql_where
		ORDER BY " . grv('sort_column') . ' ' . grv('sort_direction') . ', action ASC
		LIMIT ' . ($rows * (grv('page') - 1)) . ',' . $rows;

	$items = db_fetch_assoc_prepared($poller_sql, $sql_params);

	$display_text = [
		'dtd.name_cache' => [__('Data Source Name'), 'ASC'],
		'h.description'  => [__('Device Description'), 'ASC'],
		'nosort'         => [__('Details'), 'ASC']
	];

	$nav = html_nav_bar('utilities.php?action=view_poller_cache&filter=' . grv('filter'), MAX_DISPLAY_PAGES, grv('page'), $rows, $total_rows, 3, __('Entries'), 'page', 'main');

	print $nav;

	html_start_box('', '100%', false, 3, 'center', '');

	html_header_sort($display_text, grv('sort_column'), grv('sort_direction'), 1, 'utilities.php?action=view_poller_cache');

	$i = 0;

	if (cacti_sizeof($items)) {
		foreach ($items as $item) {
			form_alternate_row('line' . $i, true);

			$url  = 'data_sources.php?action=ds_edit&id=' . $item['local_data_id'];
			$url1 = 'host.php?action=edit&id=' . $item['host_id'];

			form_selectable_cell(filter_value($item['name_cache'], grv('filter'), $url), $i);
			form_selectable_cell(filter_value($item['description'], grv('filter'), $url1), $i);

			if ($item['action'] == 0) {
				if ($item['snmp_version'] != 3) {
					$details =
						'<b>' . __('SNMP Version:') . '</b> ' . $item['snmp_version'] . ', ' .
						'<b>' . __('Community:') . '</b> ' . htmle($item['snmp_community']) . ', ' .
						'<b>' . __('OID:') . '</b> ' . filter_value($item['arg1'], grv('filter'));
				} else {
					$details =
						'<b>' . __('SNMP Version:') . '</b>' . $item['snmp_version'] . ', ' .
						'<b>' . __('User:') . '</b>' . htmle($item['snmp_username']) . ', ' .
						'<b>' . __('OID:') . '</b>' . htmle($item['arg1']);
				}
			} elseif ($item['action'] == 1) {
				$details = '<b>' . __('Script:') . '</b>' . filter_value($item['arg1'], grv('filter'));
			} else {
				$details = '<b>' . __('Script Server:') . '</b>' . filter_value($item['arg1'], grv('filter'));
			}

			$details .= '<br><b>' . __('RRD Path:') . '</b> ' . htmle($item['rrd_path']);

			form_selectable_cell($details, $i);

			form_end_row();

			$i++;
		}
	}

	html_end_box();

	if (cacti_sizeof($items)) {
		print $nav;
	}
}

function create_poller_cache_filter(string $session_var) : array {
	global $item_rows;

	$all     = ['-1' => __('All')];
	$any     = ['-1' => __('Any')];
	$none    = ['0'  => __('None')];
	$deleted = ['-2' => __('Deleted/Invalid')];

	$sites   = array_rekey(
		db_fetch_assoc('SELECT id, name
			FROM sites
			ORDER BY name'),
		'id', 'name'
	);
	$sites   = $any + $none + $sites;

	$status = [
		'-1' => __('Any'),
		'1'  => __('Enabled'),
		'0'  => __('Disabled')
	];

	$pactions = [
		'-1' => __('Any'),
		'0'  => __('SNMP'),
		'1'  => __('Script'),
		'2'  => __('Script Server')
	];

	$sql_where  = '';
	$sql_params = [];

	if (isrv('host_id')) {
		$host_id = gfrv('host_id');
	} elseif (isset($_SESSION[$session_var . '_host_id'])) {
		$host_id = $_SESSION[$session_var . '_host_id'];
	} else {
		$host_id = '-1';
	}

	if ($host_id > 0) {
		// for the templates dropdown
		$sql_where .= ' AND h.id = ?';
		$sql_params[] = $host_id;

		$hostname = db_fetch_cell_prepared('SELECT description
			FROM host
			WHERE id = ?',
			[$host_id]);
	} elseif ($host_id == '' || $host_id == '-1') {
		$host_id  = '-1';
		$hostname = __('Any');
	} else {
		$host_id  = '0';
		$hostname = __('None');
	}

	if (gfrv('site_id') >= 0) {
		$sql_where .= ' AND site_id = ?';
		$sql_params[] = grv('site_id');
	}

	$templates = array_rekey(
		db_fetch_assoc_prepared("SELECT DISTINCT dt.id, dt.name
			FROM data_template AS dt
			INNER JOIN data_template_data AS dtd
			ON dt.id = dtd.data_template_id
			LEFT JOIN data_local AS dl
			ON dtd.local_data_id = dl.id
			LEFT JOIN host AS h
			ON dl.host_id = h.id
			WHERE dtd.local_data_id > 0
			$sql_where
			ORDER BY dt.name",
			$sql_params),
		'id', 'name'
	);

	$templates = $any + $templates;

	return [
		'rows' => [
			[
				'site_id' => [
					'method'        => 'drop_array',
					'friendly_name' => __('Site'),
					'filter'        => FILTER_VALIDATE_INT,
					'default'       => '-1',
					'pageset'       => true,
					'array'         => $sites,
					'value'         => '-1'
				],
				'host_id' => [
					'method'        => 'drop_callback',
					'friendly_name' => __('Device'),
					'filter'        => FILTER_VALIDATE_INT,
					'default'       => '-1',
					'pageset'       => true,
					'sql'           => 'SELECT DISTINCT id, description AS name FROM host ORDER BY description',
					'action'        => 'ajax_hosts',
					'id'            => $host_id,
					'value'         => $hostname,
					'on_change'     => 'applyFilter()'
				],
				'template_id' => [
					'method'        => 'drop_array',
					'friendly_name' => __('Template'),
					'filter'        => FILTER_VALIDATE_INT,
					'default'       => '-1',
					'pageset'       => true,
					'array'         => $templates,
					'value'         => '-1'
				]
			],
			[
				'filter' => [
					'method'         => 'textbox',
					'friendly_name'  => __('Search'),
					'filter'         => FILTER_DEFAULT,
					'placeholder'    => __('Enter a search term'),
					'size'           => '30',
					'default'        => '',
					'pageset'        => true,
					'max_length'     => '120',
					'value'          => ''
				],
				'status' => [
					'method'        => 'drop_array',
					'friendly_name' => __('Status'),
					'filter'        => FILTER_VALIDATE_INT,
					'default'       => '-1',
					'pageset'       => true,
					'array'         => $status,
					'value'         => '-1'
				],
				'poller_action' => [
					'method'        => 'drop_array',
					'friendly_name' => __('Actions'),
					'filter'        => FILTER_VALIDATE_INT,
					'default'       => '-1',
					'pageset'       => true,
					'array'         => $pactions,
					'value'         => '-1'
				],
				'rows' => [
					'method'        => 'drop_array',
					'friendly_name' => __('Entries'),
					'filter'        => FILTER_VALIDATE_INT,
					'default'       => '-1',
					'pageset'       => true,
					'array'         => $item_rows,
					'value'         => '-1'
				]
			]
		],
		'buttons' => [
			'go' => [
				'method'  => 'submit',
				'display' => __('Go'),
				'title'   => __('Apply Filter to Table'),
			],
			'clear' => [
				'method'  => 'button',
				'display' => __('Clear'),
				'title'   => __('Reset Filter to Default Values'),
			]
		],
		'sort' => [
			'sort_column'    => 'dtd.name_cache',
			'sort_direction' => 'DESC'
		]
	];
}

function draw_poller_cache_filter(bool $render = false) : void {
	$filters = create_poller_cache_filter('sess_pollerc');

	$running = is_process_running('pushout', 'rmaster', 0);

	switch($running) {
		case false:
			$header = __('Poller Cache Items');

			break;
		case true:
			$header = __('Poller Cache Items [ <span class="blink deviceUp">Rebuild In Process - Press Go to Check Status</span> ]');

			break;
		case 97:
			$header = __('Poller Cache Items [ Rebuild Crashed without Unregistering ]');

			break;
		case 98:
			$header = __('Poller Cache Items [ Rebuild Timed out but is Running ]');

			break;
		case 99:
			$header = __('Poller Cache Items [ Rebuild Timed out and Crashed ]');

			break;
	}

	// create the page filter
	$pageFilter = new CactiTableFilter($header, 'utilities.php?action=view_poller_cache', 'form_pollerc', 'sess_pollerc');

	$pageFilter->rows_label = __('Entries');
	$pageFilter->set_filter_array($filters);

	if ($render) {
		$pageFilter->render();
	} else {
		$pageFilter->sanitize();
	}
}

function utilities() : void {
	global $utilities;

	$utilities[__('Technical Support')] = [
		__('Technical Support') => [
			'link'        => 'support.php?tab=summary',
			'description' => __('Cacti technical support page.  Used by developers and technical support persons to assist with issues in Cacti.  Includes checks for common configuration issues.')
		],
		__('Log Administration') => [
			'link'        => 'utilities.php?action=view_logfile',
			'description' => __('The Cacti Log stores statistic, error and other message depending on system settings.  This information can be used to identify problems with the poller and application.')
		],
		__('View User Log') => [
			'link'        => 'utilities.php?action=view_user_log',
			'description' => __('Allows Administrators to browse the user log.  Administrators can filter and export the log as well.')
		]
	];

	$utilities[__('Poller Cache Administration')] = [
		__('View Poller Cache') => [
			'link'        => 'utilities.php?action=view_poller_cache',
			'description' => __('This is the data that is being passed to the poller each time it runs. This data is then in turn executed/interpreted and the results are fed into the RRDfiles for graphing or the database for display.')
		],
		__('View Data Query Cache') => [
			'link'        => 'utilities.php?action=view_snmp_cache',
			'description' => __('The Data Query Cache stores information gathered from Data Query input types. The values from these fields can be used in the text area of Graphs for Legends, Vertical Labels, and GPRINTS as well as in CDEF\'s.')
<<<<<<< HEAD
		),
		__('Rebuild Poller Cache') => array(
			'link'  => 'utilities.php?action=clear_poller_cache',
			'mode'  => 'online',
			'description' => __('The Poller Cache will be re-generated if you select this option. Use this option only in the event of a database crash if you are experiencing issues after the crash and have already run the database repair tools.  Alternatively, if you are having problems with a specific Device, simply re-save that Device to rebuild its Poller Cache.  There is also a command line interface equivalent to this command that is recommended for large systems.'),
			'note'        => array (
				'message' => __('NOTE: On large systems, this command may take several minutes to hours to complete and therefore should not be run from the Cacti UI.  You can simply run \'php -q cli/rebuild_poller_cache.php --help\' at the command line for more information.'),
				'class'   => 'textWarning'
			)
		),
		__('Rebuild Resource Cache') => array(
			'link'  => 'utilities.php?action=rebuild_resource_cache',
			'mode'  => 'online',
||||||| 7dd05ee12
		),
		__('Rebuild Poller Cache') => array(
			'link'  => 'utilities.php?action=clear_poller_cache',
			'mode'  => 'online',
			'description' => __('The Poller Cache will be re-generated if you select this option. Use this option only in the event of a database crash if you are experiencing issues after the crash and have already run the database repair tools.  Alternatively, if you are having problems with a specific Device, simply re-save that Device to rebuild its Poller Cache.  There is also a command line interface equivalent to this command that is recommended for large systems.  <i class="deviceDown">NOTE: On large systems, this command may take several minutes to hours to complete and therefore should not be run from the Cacti UI.  You can simply run \'php -q cli/rebuild_poller_cache.php --help\' at the command line for more information.</i>')
		),
		__('Rebuild Resource Cache') => array(
			'link'  => 'utilities.php?action=rebuild_resource_cache',
			'mode'  => 'online',
=======
		],
		__('Rebuild Poller Cache') => [
			'link'        => 'utilities.php?action=clear_poller_cache',
			'mode'        => 'online',
			'description' => __('The Poller Cache will be re-generated if you select this option. Use this option only in the event of a database crash if you are experiencing issues after the crash and have already run the database repair tools.  Alternatively, if you are having problems with a specific Device, simply re-save that Device to rebuild its Poller Cache.  There is also a command line interface equivalent to this command that is recommended for large systems.'),
			'note'        => [
				'message' => __('NOTE: On large systems, this command may take several minutes to hours to complete and therefore should not be run from the Cacti UI.  You can simply run \'php -q cli/rebuild_poller_cache.php --help\' at the command line for more information.'),
				'class'   => 'textWarning'
			]
		],
		__('Rebuild Resource Cache') => [
			'link'        => 'utilities.php?action=rebuild_resource_cache',
			'mode'        => 'online',
>>>>>>> origin/fix/jquery-deprecations
			'description' => __('When operating multiple Data Collectors in Cacti, Cacti will attempt to maintain state for key files on all Data Collectors.  This includes all core, non-install related website and plugin files.  When you force a Resource Cache rebuild, Cacti will clear the local Resource Cache, and then rebuild it at the next scheduled poller start.  This will trigger all Remote Data Collectors to recheck their website and plugin files for consistency.')
		],
	];

	$utilities[__('Boost Utilities')] = [
		__('View Boost Status') => [
			'link'        => 'utilities.php?action=view_boost_status',
			'description' => __('This menu pick allows you to view various boost settings and statistics associated with the current running Boost configuration.')
		],
	];

	$utilities[__('Data Source Statistics Utilities')] = [
		__('Purge Data Source Statistics') => [
			'link'        => 'utilities.php?action=purge_data_source_statistics',
			'mode'        => 'online',
			'description' => __('This menu pick will purge all existing Data Source Statistics from the Database.  If Data Source Statistics is enabled, the Data Sources Statistics will start collection again on the next Data Collector pass.')
<<<<<<< HEAD
		),
	);

	if ($config['poller_id'] == 1) {
		$utilities[__('RRD Utilities')] = array(
			__('RRDfile Cleaner') => array(
				'link'  => 'rrdcleaner.php',
				'mode'  => 'online',
				'description' => __('When you delete Data Sources from Cacti, the corresponding RRDfiles are not removed automatically.  Use this utility to facilitate the removal of these old files.')
			),
			__('RRDfile Checker') => array(
				'link'  => 'rrdcheck.php',
				'mode'  => 'online',
				'description' => __('Use this utility to display problems with missing rrd files or missing values in rrdfiles. You need enable rrdcheck in Configuration->Settings->Data')
			),
		);
	}
||||||| 7dd05ee12
		),
	);

	$utilities[__('RRD Utilities')] = array(
		__('RRDfile Cleaner') => array(
			'link'  => 'rrdcleaner.php',
			'mode'  => 'online',
			'description' => __('When you delete Data Sources from Cacti, the corresponding RRDfiles are not removed automatically.  Use this utility to facilitate the removal of these old files.')
		),
		__('RRDfile Checker') => array(
			'link'  => 'rrdcheck.php',
			'mode'  => 'online',
			'description' => __('Use this utility to display problems with missing rrd files or missing values in rrdfiles. You need enable rrdcheck in Configuration->Settings->Data')
		),
	);
=======
		],
	];
>>>>>>> origin/fix/jquery-deprecations

	if (snmpagent_enabled()) {
		$utilities[__('SNMP Agent Utilities')] = [
			__('View SNMP Agent Cache') => [
				'link'        => 'utilities.php?action=view_snmpagent_cache',
				'mode'        => 'online',
				'description' => __('This shows all objects being handled by the SNMP Agent.')
			],
			__('Rebuild SNMP Agent Cache') => [
				'link'        => 'utilities.php?action=rebuild_snmpagent_cache',
				'mode'        => 'online',
				'description' => __('The SNMP cache will be cleared and re-generated if you select this option. Note that it takes another poller run to restore the SNMP cache completely.')
			],
			__('View SNMP Agent Notification Log') => [
				'link'        => 'utilities.php?action=view_snmpagent_events',
				'mode'        => 'online',
				'description' => __('This menu pick allows you to view the latest events SNMP Agent has handled in relation to the registered notification receivers.')
			]
		];
	}

	api_plugin_hook('utilities_array');

	html_start_box(__('Cacti System Utilities'), '100%', false, 3, 'center', '');

	foreach ($utilities as $header => $content) {
		$i = 0;

		foreach ($content as $title => $details) {
			if ((isset($details['mode']) && $details['mode'] == 'online' && CACTI_CONNECTION == 'online') || !isset($details['mode'])) {
				if ($i == 0) {
					html_section_header($header, 2);
				}

				form_alternate_row();
				print "<td class='nowrap' style='vertical-align:top;'>";
				print "<a class='hyperLink' href='" . htmle($details['link']) . "'>" . $title . '</a>';
				print '</td>';
				print '<td>';
<<<<<<< HEAD
				print html_escape($details['description']);

				if(isset($details['note'])) {
					print '<br/><i class="' . $details['note']['class'] . '">' . html_escape($details['note']['message']) . '</i>';
||||||| 7dd05ee12
				print html_escape($details['description']);
=======
				print htmle($details['description']);

				if (isset($details['note'])) {
					print '<br/><i class="' . $details['note']['class'] . '">' . htmle($details['note']['message']) . '</i>';
>>>>>>> origin/fix/jquery-deprecations
				}

				print '</td>';
				form_end_row();

				$i++;
			}
		}
	}

	api_plugin_hook('utilities_list');

	html_end_box();
}

function purge_data_source_statistics() : void {
	$tables = [
		'data_source_stats_daily',
		'data_source_stats_hourly',
		'data_source_stats_hourly_cache',
		'data_source_stats_hourly_last',
		'data_source_stats_monthly',
		'data_source_stats_weekly',
		'data_source_stats_yearly'
	];

	foreach ($tables as $table) {
		db_execute('TRUNCATE TABLE ' . $table);
	}

	auth_row_cache_purge(0, 'graphs');

	if (isset($_SESSION[SESS_USER_ID])) {
		cacti_log('NOTE: Cacti DS Stats purged by user ' . get_username($_SESSION[SESS_USER_ID]), false, 'WEBUI');
	} else {
		cacti_log('NOTE: Cacti DS Stats purged by cli script');
	}
}

function boost_display_run_status() : void {
	global $refresh_interval, $boost_utilities_interval, $boost_refresh_interval, $boost_max_runtime;

	// ================= input validation =================
	gfrv('refresh');
	// ====================================================

	load_current_session_value('refresh', 'sess_boost_utilities_refresh', '30');

	$last_run_time   = read_config_option('boost_last_run_time', true);
	$next_run_time   = read_config_option('boost_next_run_time', true);

	$rrd_updates     = read_config_option('boost_rrd_update_enable', true);
	$boost_cache     = read_config_option('boost_png_cache_enable', true);

	$max_records     = read_config_option('boost_rrd_update_max_records', true);
	$max_runtime     = read_config_option('boost_rrd_update_max_runtime', true);
	$update_interval = read_config_option('boost_rrd_update_interval', true);
	$peak_memory     = read_config_option('boost_peak_memory', true);
	$detail_stats    = read_config_option('stats_detail_boost', true);

	$refresh['seconds'] = grv('refresh');
	$refresh['page']    = 'utilities.php?action=view_boost_status';
	$refresh['logout']  = 'false';

	set_page_refresh($refresh);

	html_start_box(__('Boost Status'), '100%', false, 3, 'center', '');

	?>
	<script type='text/javascript'>

	function applyFilter() {
		strURL = urlPath+'utilities.php?action=view_boost_status&refresh=' + $('#refresh').val();
		loadUrl({url:strURL})
	}
	</script>
	<tr class='even'>
		<form id='form_boost_utilities_stats' method='post'>
		<td>
			<table>
				<tr>
					<td class='nowrap'>
						<?php print __('Refresh Interval'); ?>
					</td>
					<td>
						<select id='refresh' name='refresh' onChange='applyFilter()' data-defaultLabel='<?php print __('Refresh Interval'); ?>'>
						<?php
						foreach ($boost_utilities_interval as $key => $interval) {
							print '<option value="' . $key . '"';

							if (grv('refresh') == $key) {
								print ' selected';
							} print '>' . $interval . '</option>';
						}
	?>
					</td>
					<td>
						<button type='button' class='ui-button ui-corner-all ui-widget' onClick='applyFilter()'><?php print __esc('Refresh'); ?></button>
					</td>
				</tr>
			</table>
		</td>
		</form>
	</tr>
	<?php
	html_end_box(true);

	html_start_box('', '100%', false, 3, 'center', '');

	// get the boost table status
	$boost_table_status = db_fetch_assoc("SELECT *
		FROM INFORMATION_SCHEMA.TABLES
		WHERE table_schema = SCHEMA()
		AND (table_name LIKE 'poller_output_boost_arch_%'
		OR table_name LIKE 'poller_output_boost')");

	$pending_records = 0;
	$arch_records    = 0;
	$data_length     = 0;
	$engine          = '';
	$max_data_length = 0;

	foreach ($boost_table_status as $table) {
		if ($table['TABLE_NAME'] == 'poller_output_boost') {
			$pending_records += $table['TABLE_ROWS'];
		} else {
			$arch_records += $table['TABLE_ROWS'];
		}

		$data_length    += $table['DATA_LENGTH'];
		$data_length    += $table['INDEX_LENGTH'];
		$engine          = $table['ENGINE'];
		$max_data_length = $table['MAX_DATA_LENGTH'];
	}

	if (CACTI_CONNECTION == 'online' && db_table_exists('poller_output_boost_local_data_ids')) {
		$pending_ds = db_fetch_cell('SELECT COUNT(local_data_id) FROM poller_output_boost_local_data_ids');
	} else {
		$pending_ds = 0;
	}

	$poller_items = db_fetch_cell('SELECT COUNT(local_data_id)
		FROM poller_item AS pi
		INNER JOIN host AS h
		ON h.id = pi.host_id
		WHERE h.disabled = ""');

	$data_sources = db_fetch_cell('SELECT COUNT(DISTINCT local_data_id)
		FROM poller_item AS pi
		INNER JOIN host AS h
		ON h.id = pi.host_id
		WHERE h.disabled = ""');

	$pi_ds = ($data_sources ? ($poller_items / $data_sources) : 0);

	if ($pending_ds == 0) {
		$remaining = $arch_records;
	} else {
		$remaining = $arch_records * (($pending_ds * $pi_ds) / $data_sources);
	}

	$total_records  = $pending_records + $remaining;
	$avg_row_length = ($total_records ? intval($data_length / $total_records) : 0);

	$boost_status = read_config_option('boost_poller_status', true);
<<<<<<< HEAD
||||||| 7dd05ee12
	if ($boost_status != '' && $boost_status != 'Disabled') {
=======

>>>>>>> origin/fix/jquery-deprecations
	if ($boost_status != '' && $boost_status != 'disabled') {
		$boost_status_array = explode(':', $boost_status);

		if (isset($boost_status_array[1])) {
			$boost_status_date = $boost_status_array[1];
		} else {
			$boost_status_date = null;
		}

		if (substr_count($boost_status_array[0], 'complete')) {
			$status = '<span class="deviceRecovering">' . __('Idle') . '</span>';
		} elseif (substr_count($boost_status_array[0], 'running')) {
			$status = '<span class="deviceUp">' . __('Running') . '</span>';
		} elseif (substr_count($boost_status_array[0], 'overrun')) {
			$status = '<span class="deviceDown">' . __('Overrun Warning') . '</span>';
		} elseif (substr_count($boost_status_array[0], 'timeout')) {
			$status = '<span class="deviceDown">' . __('Timed Out') . '</span>';
		} else {
			$status = '<span class="deviceDown">' . __('Other') . '</span>';
		}
	} else {
<<<<<<< HEAD
		$status = '<span class="deviceDisabled">' . __('Disabled') . '</span>';
||||||| 7dd05ee12
		$status = '<span class="deviceDisabled">' . __('Disabled') . '</span>';
		$boost_status_date = '';
=======
		$status            = '<span class="deviceDisabled">' . __('Disabled') . '</span>';
>>>>>>> origin/fix/jquery-deprecations
		$boost_status_date = null;
	}

	$stats_boost = read_config_option('stats_boost', true);

	if ($stats_boost != '') {
		$stats_boost_array = explode(' ', $stats_boost);

		$stats_duration          = explode(':', $stats_boost_array[0]);
		$boost_last_run_duration = $stats_duration[1];

		$stats_rrds         = explode(':', $stats_boost_array[1]);
		$boost_rrds_updated = $stats_rrds[1];
	} else {
		$boost_last_run_duration = '';
		$boost_rrds_updated      = '';
	}

	// get cache directory size/contents
	$cache_directory    = read_config_option('boost_png_cache_directory', true);
	$directory_contents = [];

	if (is_dir($cache_directory)) {
		if ($handle = @opendir($cache_directory)) {
			// This is the correct way to loop over the directory.
			while (false !== ($file = readdir($handle))) {
				$directory_contents[] = $file;
			}

			closedir($handle);

			// get size of directory
			$directory_size = 0;
			$cache_files    = 0;

			if (cacti_sizeof($directory_contents)) {
				// goto the cache directory
				chdir($cache_directory);

				// check and fry as applicable
				foreach ($directory_contents as $file) {
					// only remove jpeg's and png's
					if ((substr_count(cacti_strtolower($file), '.png')) ||
						(substr_count(cacti_strtolower($file), '.jpg'))) {
						$cache_files++;
						$directory_size += filesize($file);
					}
				}
			}

			$directory_size = boost_file_size_display($directory_size);
			$cache_files    = $cache_files . ' Files';
		} else {
			$directory_size = '<strong>' . __('WARNING:') . '</strong>' . __('Cannot open directory');
			$cache_files    = '<strong>' . __('WARNING:') . '</strong> ' . __('Unknown');
		}
	} else {
		$directory_size = '<strong>' . __('WARNING:') . '</strong> ' . __('Directory Does NOT Exist!!');
		$cache_files    = '<strong>' . __('WARNING:') . '</strong> ' . __('N/A');
	}

	$running = db_fetch_cell('SELECT COUNT(*) FROM processes WHERE tasktype="boost" AND taskname="child"');

	$i = 0;

	// boost status display
	html_section_header(__('Current Boost Status'), 2);

	if (CACTI_CONNECTION == 'online') {
		form_alternate_row();
		print '<td>' . __('Boost On-demand Updating:') . '</td><td><b>' . $status . '</b></td>';

		if ($running > 0) {
			form_alternate_row();
			print '<td>' . __('Running Processes:') . '</td><td>' . ($running) . '</td>';
		}
	}

	form_alternate_row();
	print '<td>' . __('Total Poller Items:') . '</td><td>' . number_format_i18n($poller_items) . '</td>';

	$premaining = ($data_sources ? (round(($pending_ds / $data_sources) * 100, 1)) : 0);

	if ($total_records) {
		form_alternate_row();
		print '<td>' . __('Total Data Sources:') . '</td><td>' . number_format_i18n($data_sources) . '</td>';

		if (CACTI_CONNECTION == 'online') {
			form_alternate_row();
<<<<<<< HEAD
			print '<td>' . __('Remaining Data Sources:') . '</td><td>' . ($pending_ds > 0 ? number_format_i18n($pending_ds) . " ($premaining %)":__('TBD')) . '</td>';
||||||| 7dd05ee12
			print '<td>' . __('Remaining Data Sources:') . '</td><td>' . ($pending_ds > 0 ? number_format_i18n($pending_ds, -1) . " ($premaining %)":__('TBD')) . '</td>';
=======
			print '<td>' . __('Remaining Data Sources:') . '</td><td>' . ($pending_ds > 0 ? number_format_i18n($pending_ds) . " ($premaining %)" : __('TBD')) . '</td>';
>>>>>>> origin/fix/jquery-deprecations
		}

		form_alternate_row();
		print '<td>' . __('Queued Boost Records:') . '</td><td>' . number_format_i18n($pending_records) . '</td>';

		if (CACTI_CONNECTION == 'online') {
			form_alternate_row();
			print '<td>' . __('Approximate in Process:') . '</td><td>' . number_format_i18n($remaining) . '</td>';

			form_alternate_row();
			print '<td>' . __('Total Boost Records:') . '</td><td>' . number_format_i18n($total_records) . '</td>';
		}
	}

	// boost status display
	html_section_header(__('Boost Storage Statistics'), 2);

	// describe the table format
	form_alternate_row();
	print '<td>' . __('Database Engine:') . '</td><td>' . $engine . '</td>';

	// tell the user how big the table is
	form_alternate_row();
	print '<td>' . __('Current Boost Table(s) Size:') . '</td><td>' . boost_file_size_display($data_length, 2) . '</td>';

	// tell the user about the average size/record
	form_alternate_row();
	print '<td>' . __('Avg Bytes/Record:') . '</td><td>' . boost_file_size_display($avg_row_length, 0) . '</td>';

	// tell the user about the average size/record
	$output_length = read_config_option('boost_max_output_length');

	if ($output_length != '') {
		$parts = explode(':', $output_length);

		if ((time() - 1200) > $parts[0]) {
			$ref = true;
		} else {
			$ref = false;
		}
	} else {
		$ref = true;
	}

	if ($ref) {
		if (strcmp($engine, 'MEMORY') == 0) {
			$max_length = db_fetch_cell('SELECT MAX(LENGTH(output)) FROM poller_output_boost');
		} else {
			$max_length = 0;
		}
		db_execute("REPLACE INTO settings (name, value) VALUES ('boost_max_output_length', '" . time() . ':' . $max_length . "')");
	} elseif (isset($parts[1])) {
		$max_length = $parts[1];
	} else {
		$max_length = 0;
	}

	if ($max_length != 0) {
		form_alternate_row();
		print '<td>' . __('Max Record Length:') . '</td><td>' . __('%d Bytes', number_format_i18n($max_length)) . '</td>';
	}

	// tell the user about the "Maximum Size" this table can be
	form_alternate_row();

	if (strcmp($engine, 'MEMORY')) {
		$max_table_allowed = __('Unlimited');
		$max_table_records = __('Unlimited');
	} else {
		$max_table_allowed = boost_file_size_display($max_data_length, 2);
		$max_table_records = number_format_i18n(($avg_row_length ? $max_data_length / $avg_row_length : 0), 3, 1000);
	}
	print '<td>' . __('Max Allowed Boost Table Size:') . '</td><td>' . $max_table_allowed . '</td>';

	// tell the user about the estimated records that "could" be held in memory
	form_alternate_row();
	print '<td>' . __('Estimated Maximum Records:') . '</td><td>' . $max_table_records . ' Records</td>';

	if (CACTI_CONNECTION == 'online') {
		// boost last runtime display
		html_section_header(__('Previous Runtime Statistics'), 2);

		form_alternate_row();

		if (is_numeric($last_run_time)) {
			print '<td class="utilityPick">' . __('Last Start Time:') . '</td><td>' . date('Y-m-d H:i:s', (int) $last_run_time) . '</td>';
		} else {
			print '<td class="utilityPick">' . __('Last Start Time:') . '</td><td>' . $last_run_time . '</td>';
		}

		// get the last end time
		$last_end_time = read_config_option('boost_last_end_time', true);

		form_alternate_row();
		print '<td class="utilityPick">' . __('Last End Time:') . '</td><td>' . ($last_end_time != '' ? date('Y-m-d H:i:s', (int) $last_end_time) : __('Never Run')) . '</td>';

		/* get the last end time */
		$last_end_time = read_config_option('boost_last_end_time', true);

		form_alternate_row();
		print '<td class="utilityPick">' . __('Last End Time:') . '</td><td>' . ($last_end_time != '' ? date('Y-m-d H:i:s', $last_end_time):__('Never Run')) . '</td>';

		form_alternate_row();
		print '<td class="utilityPick">' . __('Last Run Duration:') . '</td><td>';

		if (is_numeric($boost_last_run_duration)) {
			print ($boost_last_run_duration > 60 ? __('%d minutes', (int)$boost_last_run_duration / 60) . ', ' : '') . __('%d seconds', (int) $boost_last_run_duration % 60);

			if ($rrd_updates != '') {
				print ' (' . __('%0.2f percent of update frequency)', round(100 * (float) $boost_last_run_duration / (float) $update_interval / 60));
			}
		} else {
			print __('N/A');
		}
		print '</td>';

		form_alternate_row();
<<<<<<< HEAD
		print '<td class="utilityPick">' . __('RRD Updates:') . '</td><td>' . ($boost_rrds_updated != '' ? number_format_i18n($boost_rrds_updated):'-') . '</td>';
||||||| 7dd05ee12
		print '<td class="utilityPick">' . __('RRD Updates:') . '</td><td>' . ($boost_rrds_updated != '' ? number_format_i18n($boost_rrds_updated, -1):'-') . '</td>';
=======
		print '<td class="utilityPick">' . __('RRD Updates:') . '</td><td>' . ($boost_rrds_updated != '' ? number_format_i18n($boost_rrds_updated) : '-') . '</td>';
>>>>>>> origin/fix/jquery-deprecations

		form_alternate_row();
		print '<td class="utilityPick">' . __('Peak Poller Memory:') . '</td><td>' . ((read_config_option('boost_peak_memory') != '' && is_numeric(read_config_option('boost_peak_memory'))) ? (round(read_config_option('boost_peak_memory') / 1024 / 1024,2)) . ' ' . __('MBytes') : __('N/A')) . '</td>';

		form_alternate_row();
<<<<<<< HEAD

		$memory_limit = read_config_option('boost_poller_mem_limit');

		if ($memory_limit == -1) {
			$memory_limit = __('Unlimited');
		} elseif ($memory_limit != '') {
			$memory_limit = __('%s MBytes', number_format_i18n($memory_limit) );
		} else {
			$memory_limit = __('N/A');
		}

		print '<td class="utilityPick">' . __('Max Poller Memory Allowed:') . '</td><td>' . $memory_limit . '</td>';
||||||| 7dd05ee12
		print '<td class="utilityPick">' . __('Max Poller Memory Allowed:') . '</td><td>' . ((read_config_option('boost_poller_mem_limit') != '') ? (read_config_option('boost_poller_mem_limit')) . ' ' . __('MBytes') : __('N/A')) . '</td>';
=======
>>>>>>> origin/fix/jquery-deprecations

		$memory_limit = read_config_option('boost_poller_mem_limit');

		if ($memory_limit == -1) {
			$memory_limit = __('Unlimited');
		} elseif ($memory_limit != '') {
			$memory_limit = __('%s MBytes', number_format_i18n($memory_limit));
		} else {
			$memory_limit = __('N/A');
		}

		print '<td class="utilityPick">' . __('Max Poller Memory Allowed:') . '</td><td>' . $memory_limit . '</td>';

		// boost last runtime display
		html_section_header(__('Detailed Runtime Statistics'), 2);

		form_alternate_row();

		if ($detail_stats == '') {
			$detail_stats = __('N/A');
		} else {
			$values = explode(' ', $detail_stats);
			$rows   = explode(':', $values[0])[1];
			$time   = explode(':', $values[1])[1];
			$recs   = explode(':', $values[2])[1];
			$rcycle = explode(':', $values[3])[1];
			$fandt  = explode(':', $values[4])[1];
			$lastu  = explode(':', $values[5])[1];
			$update = explode(':', $values[6])[1];
			$delete = explode(':', $values[7])[1];

			$detail_stats = __('Records: %s (ds rows), Time: %s (secs), GetRows: %s (secs), ResultsCycle: %s (secs), FileAndTemplate: %s (secs), LastUpdate: %s (secs), RRDUpdate: %s (secs), Delete: %s (secs)',
				number_format_i18n($rows),
				number_format_i18n($time),
				number_format_i18n($recs),
				number_format_i18n($rcycle),
				number_format_i18n($fandt),
				number_format_i18n($lastu),
				number_format_i18n($update),
				number_format_i18n($delete));
		}

		print '<td class="utilityPick">' . __('Previous Runtime Timers:') . '</td><td>' . (($detail_stats != '') ? $detail_stats : __('N/A')) . '</td>';

		$runtimes = db_fetch_assoc('SELECT name, value, CAST(replace(name, "stats_boost_", "") AS signed) AS ome
			FROM settings
			WHERE name LIKE "stats_boost_%"
			ORDER BY ome');

		if (cacti_sizeof($runtimes)) {
			foreach ($runtimes as $r) {
				$process = str_replace('stats_boost_', '', $r['name']);

				if ($r['value'] != '') {
					$values = explode(' ', $r['value']);
					$time   = explode(':', $values[0])[1];
					$rrds   = explode(':', $values[2])[1];
				} else {
					$time = 0;
					$rrds = 0;
				}

				$rows_to_process = db_fetch_cell_prepared('SELECT COUNT(*)
					FROM poller_output_boost_local_data_ids
					WHERE process_handler = ?',
					[$process]);

				$runtime = db_fetch_cell_prepared('SELECT UNIX_TIMESTAMP() - UNIX_TIMESTAMP(started)
					FROM processes
					WHERE tasktype = "boost"
					AND taskname = "child"
					AND taskid = ?',
					[$process]);

				form_alternate_row();

				if ($rows_to_process > 0) {
					print '<td class="utilityPick">' . __esc('Process: %d', $process) . '</td><td>' . __('Status: <span class="deviceUp"><b>Running</b></span>, Remaining: %s (dses), CurrentRuntime: %s (secs), PrevRuntime: %s (secs), PrevProcessed: %10s (ds rows)', number_format_i18n((int) $rows_to_process), number_format_i18n((float) $runtime), number_format_i18n((float) $time), number_format_i18n((int) $rrds)) . '</td>';
				} else {
					print '<td class="utilityPick">' . __esc('Process: %d', $process) . '</td><td>' . __('Status: <span class="deviceRecovering"><b>Idle</b></span>, PrevRuntime: %s (secs), PrevProcessed: %10s (ds rows)', number_format_i18n((float) $time), number_format_i18n((int) $rrds)) . '</td>';
				}
			}
		}

		// boost runtime display
		html_section_header(__('Run Time Configuration'), 2);

		form_alternate_row();
		print '<td class="utilityPick">' . __('Update Frequency:') . '</td><td>' . ($rrd_updates == '' ? __('N/A') : $boost_refresh_interval[$update_interval]) . '</td>';

		form_alternate_row();
		print '<td class="utilityPick">' . __('Concurrent Processes:') . '</td><td>' . read_config_option('boost_parallel') . '</td>';

		form_alternate_row();
		print '<td class="utilityPick">' . __('Next Start Time:') . '</td><td>' . (is_numeric($next_run_time) ? date('Y-m-d H:i:s', (int) $next_run_time) : $next_run_time) . '</td>';

		form_alternate_row();
		print '<td class="utilityPick">' . __('Maximum Records:') . '</td><td>' . number_format_i18n($max_records) . ' ' . __('Records') . '</td>';

		form_alternate_row();
		print '<td class="utilityPick">' . __('Maximum Allowed Runtime:') . '</td><td>' . $boost_max_runtime[$max_runtime] . '</td>';

		// boost caching
		html_section_header(__('Image Caching'), 2);

		form_alternate_row();
		print '<td>' . __('Image Caching Status:') . '</td><td>' . ($boost_cache == '' ? __('Disabled') : __('Enabled')) . '</td>';

		form_alternate_row();
		print '<td>' . __('Cache Directory:') . '</td><td>' . $cache_directory . '</td>';

		form_alternate_row();
		print '<td>' . __('Cached Files:') . '</td><td>' . $cache_files . '</td>';

		form_alternate_row();
		print '<td>' . __('Cached Files Size:') . '</td><td>' . $directory_size . '</td>';

		html_end_box(true);
	}
}

function create_snmp_agent_cache_filter() : array {
	global $item_rows;

	$mibs = array_rekey(
		db_fetch_assoc("SELECT 'any' AS id, '" . __esc('Any') . "' AS name UNION SELECT DISTINCT mib AS id, mib AS name FROM snmpagent_cache"),
		'id', 'name'
	);

	return [
		'rows' => [
			[
				'filter' => [
					'method'         => 'textbox',
					'friendly_name'  => __('Search'),
					'filter'         => FILTER_DEFAULT,
					'placeholder'    => __('Enter a search term'),
					'size'           => '30',
					'default'        => '',
					'pageset'        => true,
					'max_length'     => '120',
					'value'          => ''
				],
				'mib' => [
					'method'         => 'drop_array',
					'friendly_name'  => __('MIB'),
					'filter'         => FILTER_CALLBACK,
					'filter_options' => ['options' => 'sanitize_search_string'],
					'default'        => 'any',
					'pageset'        => true,
					'array'          => $mibs,
					'value'          => 'any'
				],
				'rows' => [
					'method'         => 'drop_array',
					'friendly_name'  => __('Entries'),
					'filter'         => FILTER_VALIDATE_INT,
					'default'        => '-1',
					'pageset'        => true,
					'array'          => $item_rows,
					'value'          => '-1'
				]
			]
		],
		'buttons' => [
			'go' => [
				'method'  => 'submit',
				'display' => __('Go'),
				'title'   => __('Apply Filter to Table'),
			],
			'clear' => [
				'method'  => 'button',
				'display' => __('Clear'),
				'title'   => __('Reset Filter to Default Values'),
			]
		]
	];
}

function draw_snmp_agent_cache_filter(bool $render = false) : void {
	$filters = create_snmp_agent_cache_filter();

	// create the page filter
	$pageFilter = new CactiTableFilter(__('SNMP Agent Cache'), 'utilities.php?action=view_snmpagent_cache', 'form_agent', 'sess_snmpc');

	$pageFilter->rows_label = __('OIDs');
	$pageFilter->set_filter_array($filters);

	if ($render) {
		$pageFilter->render();
	} else {
		$pageFilter->sanitize();
	}
}

/**
 * snmpagent_utilities_run_cache()
 *
 * @return void
 */
function snmpagent_utilities_run_cache() : void {
	$mibs = db_fetch_assoc('SELECT DISTINCT mib FROM snmpagent_cache');

	$registered_mibs = [];

	if (cacti_sizeof($mibs)) {
		foreach ($mibs as $mib) {
			$registered_mibs[] = $mib['mib'];
		}
	}

	draw_snmp_agent_cache_filter(true);

	if (grv('rows') == '-1') {
		$rows = read_config_option('num_rows_table');
	} else {
		$rows = grv('rows');
	}

	$sql_where  = '';
	$sql_params = [];

	// filter by host
	if (!ierv('mib') && grv('mib') != 'any') {
		$sql_where .= ($sql_where != '' ? ' AND ' : 'WHERE ') . 'snmpagent_cache.mib = ?';
		$sql_params[] = grv('mib');
	}

	// filter by search string
	if (grv('filter') != '') {
		$sql_where .= ($sql_where != '' ? ' AND ' : 'WHERE ') .
			' (`oid` LIKE ? OR `name` LIKE ? OR `mib` LIKE ? OR `max-access` LIKE ?)';

		$sql_params[] = '%' . grv('filter') . '%';
		$sql_params[] = '%' . grv('filter') . '%';
		$sql_params[] = '%' . grv('filter') . '%';
		$sql_params[] = '%' . grv('filter') . '%';
	}

	$sql_where .= ' ORDER by `oid`';

	$total_rows = db_fetch_cell_prepared("SELECT COUNT(*)
		FROM snmpagent_cache
		$sql_where",
		$sql_params);

	$snmp_cache_sql = "SELECT *
		FROM snmpagent_cache
		$sql_where
		LIMIT " . ($rows * (grv('page') - 1)) . ',' . $rows;

	$snmp_cache = db_fetch_assoc_prepared($snmp_cache_sql, $sql_params);

	$display_text = [
		__('OID'),
		__('Name'),
		__('MIB'),
		__('Type'),
		__('Max-Access'),
		__('Value')
	];

	// generate page list
	$nav = html_nav_bar('utilities.php?action=view_snmpagent_cache&mib=' . grv('mib') . '&filter=' . grv('filter'), MAX_DISPLAY_PAGES, grv('page'), $rows, $total_rows, 11, __('Entries'), 'page', 'main');

	print $nav;

	html_start_box('', '100%', false, 3, 'center', '');

	html_header($display_text);

	if (cacti_sizeof($snmp_cache)) {
		foreach ($snmp_cache as $item) {
			$oid        = filter_value($item['oid'], grv('filter'));
			$name       = $item['name'];
			$mib        = filter_value($item['mib'], grv('filter'));
			$max_access = filter_value($item['max-access'], grv('filter'));

			form_alternate_row('line' . $item['oid'], false);

			form_selectable_cell($oid, $item['oid']);

			if ($item['description']) {
				form_selectable_cell(filter_value($name, grv('filter'), '#', $item['description']), $item['oid']);
			} else {
				form_selectable_ecell($name, $item['oid']);
			}

			form_selectable_cell($mib, $item['oid']);
			form_selectable_ecell($item['kind'], $item['oid']);
			form_selectable_cell($max_access, $item['oid']);
			form_selectable_ecell((in_array($item['kind'], [__('Scalar'), __('Column Data')], true) ? $item['value'] : __('N/A')), $item['oid']);

			form_end_row();
		}
	} else {
		print '<tr class="tableRow odd"><td colspan="6"><em>' . __('No SNMP Agent Cache Entries Found') . '</em></td></tr>';
	}

	html_end_box();

	if (cacti_sizeof($snmp_cache)) {
		print $nav;
	}
<<<<<<< HEAD

	?>
	<script type='text/javascript'>
	$(function() {
		$('.tooltip').tooltip({
			track: true,
			show: 250,
			hide: 250,
			position: { collision: 'flipfit' },
			content: function() { return DOMPurify.sanitize($(this).attr('title')); }
		});
	});
	</script>
	<?php
||||||| 7dd05ee12

	?>
	<script type='text/javascript'>
		$('.tooltip').tooltip({
			track: true,
			show: 250,
			hide: 250,
			position: { collision: 'flipfit' },
			content: function() { return $(this).attr('title'); }
		});
	</script>
	<?php
=======
>>>>>>> origin/fix/jquery-deprecations
}

function create_snmp_agent_events_filter() : array {
	global $item_rows, $severity_levels, $severity_colors, $receivers;

	$any = ['-1' => __('Any')];

	$mibs = array_rekey(
		db_fetch_assoc("SELECT 'any' AS id, '" . __esc('Any') . "' AS name
			UNION
			SELECT DISTINCT mib AS id, mib AS name FROM snmpagent_cache"),
		'id', 'name'
	);

	$receivers = $any + $receivers;

	return [
		'rows' => [
			[
				'filter' => [
					'method'         => 'textbox',
					'friendly_name'  => __('Search'),
					'filter'         => FILTER_DEFAULT,
					'placeholder'    => __('Enter a search term'),
					'size'           => '30',
					'default'        => '',
					'pageset'        => true,
					'max_length'     => '120',
					'value'          => ''
				],
				'severity' => [
					'method'         => 'drop_array',
					'friendly_name'  => __('Severity'),
					'filter'         => FILTER_CALLBACK,
					'default'        => '-1',
					'pageset'        => true,
					'array'          => $severity_levels,
					'value'          => '-1'
				],
				'receiver' => [
					'method'         => 'drop_array',
					'friendly_name'  => __('Receiver'),
					'filter'         => FILTER_VALIDATE_INT,
					'default'        => '-1',
					'pageset'        => true,
					'array'          => $receivers,
					'value'          => '-1'
				],
				'rows' => [
					'method'         => 'drop_array',
					'friendly_name'  => __('Events'),
					'filter'         => FILTER_VALIDATE_INT,
					'default'        => '-1',
					'pageset'        => true,
					'array'          => $item_rows,
					'value'          => '-1'
				]
			]
		],
		'buttons' => [
			'go' => [
				'method'  => 'submit',
				'display' => __('Go'),
				'title'   => __('Apply Filter to Table'),
			],
			'clear' => [
				'method'  => 'button',
				'display' => __('Clear'),
				'title'   => __('Reset Filter to Default Values'),
			],
			'purge' => [
				'method'  => 'button',
				'display' => __('Purge'),
				'title'   => __('Purge the SNMP Agent Event Log'),
				'url'     => 'utilities.php?action=view_snmpagent_events&purge=true'
			]
		]
	];
}

function draw_snmp_agent_events_filter(bool $render = false) : void {
	$filters = create_snmp_agent_events_filter();

	// create the page filter
	$pageFilter = new CactiTableFilter(__('SNMP Agent Notification Log'), 'utilities.php?action=view_snmpagent_events', 'form_agent', 'sess_snmpae');

	$pageFilter->rows_label = __('Events');
	$pageFilter->set_filter_array($filters);

	if ($render) {
		$pageFilter->render();
	} else {
		$pageFilter->sanitize();
	}
}

function snmpagent_utilities_run_eventlog() : void {
	global $severity_levels, $severity_colors, $receivers;

	$severity_levels = [
		SNMPAGENT_EVENT_SEVERITY_LOW      => 'LOW',
		SNMPAGENT_EVENT_SEVERITY_MEDIUM   => 'MEDIUM',
		SNMPAGENT_EVENT_SEVERITY_HIGH     => 'HIGH',
		SNMPAGENT_EVENT_SEVERITY_CRITICAL => 'CRITICAL'
	];

	$severity_colors = [
		SNMPAGENT_EVENT_SEVERITY_LOW      => '#00FF00',
		SNMPAGENT_EVENT_SEVERITY_MEDIUM   => '#FFFF00',
		SNMPAGENT_EVENT_SEVERITY_HIGH     => '#FF0000',
		SNMPAGENT_EVENT_SEVERITY_CRITICAL => '#FF00FF'
	];

	$receivers = array_rekey(
		db_fetch_assoc('SELECT DISTINCT manager_id, hostname
			FROM snmpagent_notifications_log AS al
			INNER JOIN snmpagent_managers AS am
			ON am.id = al.manager_id'),
		'manager_id', 'hostname'
	);

	if (isrv('purge')) {
		db_execute('TRUNCATE table snmpagent_notifications_log');

		// reset filters
		srv('clear', true);
	}

	draw_snmp_agent_events_filter(true);

	if (grv('rows') == -1) {
		$rows = read_config_option('num_rows_table');
	} else {
		$rows = grv('rows');
	}

	$sql_where  = '';
	$sql_params = [];

	// filter by severity
	if (grv('receiver') != '-1') {
		$sql_where .= ($sql_where != '' ? ' AND ' : 'WHERE ') . ' snl.manager_id = ?';
		$sql_params[] = grv('receiver');
	}

	// filter by severity
	if (!ierv('severity') && grv('severity') != '-1') {
		$sql_where .= ($sql_where != '' ? ' AND ' : 'WHERE ') . ' snl.severity = ?';
		$sql_params[] = grv('severity');
	}

	// filter by search string
	if (grv('filter') != '') {
		$sql_where .= ($sql_where != '' ? ' AND ' : 'WHERE ') . ' (`varbinds` LIKE ?';
		$sql_params[] = '%' . grv('filter') . '%';
	}

	$sql_where .= ' ORDER by `time` DESC';

	$sql_query  = "SELECT snl.*, sm.hostname, sc.description
		FROM snmpagent_notifications_log AS snl
		INNER JOIN snmpagent_managers AS sm
		ON sm.id = snl.manager_id
		LEFT JOIN snmpagent_cache AS sc
		ON sc.name = snl.notification
		$sql_where
		LIMIT " . ($rows * (grv('page') - 1)) . ',' . $rows;

	$total_rows = db_fetch_cell_prepared("SELECT COUNT(*)
		FROM snmpagent_notifications_log AS snl
		$sql_where",
		$sql_params);

	$logs = db_fetch_assoc_prepared($sql_query, $sql_params);

	$nav = html_nav_bar('utilities.php?action=view_snmpagent_events&severity=' . grv('severity') . '&receiver=' . grv('receiver') . '&filter=' . grv('filter'), MAX_DISPLAY_PAGES, grv('page'), $rows, $total_rows, 11, __('Log Entries'), 'page', 'main');

	form_start('managers.php', 'chk');

	print $nav;

	html_start_box('', '100%', false, 3, 'center', '');

	html_header([' ', __('Time'), __('Receiver'), __('Notification'), __('Varbinds')]);

	if (cacti_sizeof($logs)) {
		foreach ($logs as $item) {
			$varbinds = filter_value($item['varbinds'], grv('filter'));

			form_alternate_row('line' . $item['id'], false);

			form_selectable_cell(__esc('Severity Level: %s', $severity_levels[$item['severity']]), $item['id'], '', 'background-color:' . $severity_colors[$item['severity']]);

			form_selectable_cell(date('Y-m-d H:i:s', $item['time']), $item['id']);
			form_selectable_ecell($item['hostname'], $item['id']);

			if ($item['description']) {
				form_selectable_cell(filter_value($item['notification'], '', '#', htmle($item['notification'])), $item['id']);
			} else {
				form_selectable_ecell($item['notification'], $item['id']);
			}

			form_selectable_cell($varbinds, $item['id']);

			form_end_row();
		}
	} else {
		print '<tr class="tableRow odd"><td colspan="5"><em>' . __('No SNMP Notification Log Entries') . '</em></td></tr>';
	}

	html_end_box();

	if (cacti_sizeof($logs)) {
		print $nav;
	}
}
