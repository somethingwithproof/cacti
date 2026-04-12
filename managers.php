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

$actions = [
	1 => __('Delete'),
	2 => __('Disable'),
	3 => __('Enable'),
];

$mactions = [
	1 => __('Disable'),
	2 => __('Enable')
];

$tabs_manager_edit = [
	'general'       => __('General'),
	'notifications' => __('Notifications'),
	'logs'          => __('Logs'),
];

// set default action
set_default_action();

gfrv('tab', FILTER_CALLBACK, ['options' => 'sanitize_search_string']);

switch (grv('action')) {
	case 'save':
		form_save();

		break;
	case 'actions':
		form_actions();

		break;
	case 'edit':
		top_header();
		manager_edit();
		bottom_footer();

		break;
	default:
		top_header();
		manager();
		bottom_footer();

		break;
}

<<<<<<< HEAD
function manager() {
	global $config, $manager_actions, $item_rows;
||||||| 7dd05ee12
function manager(){
	global $config, $manager_actions, $item_rows;
=======
function manager() : void {
	global $actions, $item_rows;
>>>>>>> origin/fix/jquery-deprecations

	// create the page filter
	$pageFilter = new CactiTableFilter(__('SNMP Notification Receivers'), 'managers.php', 'form_snmpagent_managers', 'sess_snmp_mgr', 'managers.php?action=edit');

	$pageFilter->rows_label = __('Receivers');
	$pageFilter->set_sort_array('hostname', 'ASC');
	$pageFilter->render();

	if (grv('rows') == '-1') {
		$rows = read_config_option('num_rows_table');
	} else {
		$rows = grv('rows');
	}

	// form the 'where' clause for our main sql query
	$sql_where = 'WHERE (
		sm.hostname LIKE ' . db_qstr('%' . grv('filter') . '%') . '
		OR sm.description LIKE ' . db_qstr('%' . grv('filter') . '%') . ')';

	$total_rows = db_fetch_cell("SELECT
		COUNT(sm.id)
		FROM snmpagent_managers AS sm
		$sql_where");

	$sql_order = get_order_string();
	$sql_limit = ' LIMIT ' . ($rows * (grv('page') - 1)) . ',' . $rows;

	$managers = db_fetch_assoc("SELECT sm.id, sm.description,
		sm.hostname, sm.disabled, smn.count_notify, snl.count_log
		FROM snmpagent_managers AS sm
		LEFT JOIN (
			SELECT COUNT(*) as count_notify, manager_id
			FROM snmpagent_managers_notifications
			GROUP BY manager_id
		) AS smn
		ON smn.manager_id = sm.id
		LEFT JOIN (
			SELECT COUNT(*) as count_log, manager_id
			FROM snmpagent_notifications_log
			GROUP BY manager_id
		) AS snl
		ON snl.manager_id = sm.id
		$sql_where
		$sql_order
		$sql_limit");

	$display_text = [
		'description'  => [ __('Description'), 'ASC'],
		'id'           => [ __('Id'), 'ASC'],
		'disabled'     => [ __('Status'), 'ASC'],
		'hostname'     => [ __('Hostname'), 'ASC'],
		'count_notify' => [ __('Notifications'), 'ASC'],
		'count_log'    => [ __('Logs'), 'ASC']
	];

	// generate page list
	$nav = html_nav_bar('managers.php?filter=' . grv('filter'), MAX_DISPLAY_PAGES, grv('page'), $rows, $total_rows, 11, __('Receivers'), 'page', 'main');

	form_start('managers.php', 'chk');

	print $nav;

	html_start_box('', '100%', false, 3, 'center', '');

	html_header_sort_checkbox($display_text, grv('sort_column'), grv('sort_direction'), false);

	if (cacti_sizeof($managers)) {
		foreach ($managers as $item) {
			$description = filter_value($item['description'], grv('filter'));
			$hostname    = filter_value($item['hostname'], grv('filter'));

			$url         = 'managers.php?action=edit&id=' . $item['id'];
			$url1        = 'managers.php?action=edit&tab=notifications&id=' . $item['id'];
			$url2        = 'managers.php?action=edit&tab=logs&id=' . $item['id'];

			form_alternate_row('line' . $item['id'], false);

			form_selectable_cell(filter_value($description, '', $url), $item['id']);
			form_selectable_cell($item['id'], $item['id']);

			form_selectable_cell($item['disabled'] ? '<span class="deviceDown">' . __('Disabled') . '</span>' : '<span class="deviceUp">' . __('Enabled') . '</span>', $item['id']);

			form_selectable_ecell($hostname, $item['id']);
			form_selectable_cell(filter_value($item['count_notify'] ? $item['count_notify'] : 0, '', $url1), $item['id']);
			form_selectable_cell(filter_value($item['count_log'] ? $item['count_log'] : 0, '', $url2), $item['id']);

			form_checkbox_cell($item['description'], $item['id']);

			form_end_row();
		}
	} else {
		print '<tr class="tableRow odd"><td colspan="7"><em>' . __('No SNMP Notification Receivers') . '</em></td></tr>';
	}

	html_end_box(false);

	if (cacti_sizeof($managers)) {
		print $nav;
	}

	form_hidden_box('action_receivers', '1', '');

	draw_actions_dropdown($actions);

	form_end();
}

function manager_edit() : void {
	global $snmp_auth_protocols, $snmp_priv_protocols, $snmp_versions,
	$tabs_manager_edit, $fields_manager_edit, $mactions;

	// ================= input validation =================
	gfrv('id');
	// ====================================================

	if (!isrv('tab')) {
		srv('tab', 'general');
	}

	$id	 = (isrv('id') ? grv('id') : '0');

	if ($id) {
		$manager      = db_fetch_row_prepared('SELECT * FROM snmpagent_managers WHERE id = ?', [grv('id')]);
		$header_label = __esc('SNMP Notification Receiver [edit: %s]', $manager['description']);
	} else {
		$header_label = __('SNMP Notification Receiver [new]');
	}

	if (cacti_sizeof($tabs_manager_edit) && isrv('id')) {
		$i = 0;

		// draw the tabs
		print "<div class='tabs'><nav><ul role='tablist'>";

		foreach (array_keys($tabs_manager_edit) as $tab_short_name) {
			if (($id == 0 && $tab_short_name != 'general')) {
<<<<<<< HEAD
				print "<li class='subTab'><a href='#' " . (($tab_short_name == get_request_var('tab')) ? "class='selected'" : '') . "'>" . $tabs_manager_edit[$tab_short_name] . '</a></li>';
			} else {
				print "<li class='subTab'><a " . (($tab_short_name == get_request_var('tab')) ? "class='selected'" : '') .
					" href='" . html_escape($config['url_path'] .
					'managers.php?action=edit&id=' . get_request_var('id') .
||||||| 7dd05ee12
			if (($id == 0 && $tab_short_name != 'general')){
				print "<li class='subTab'><a href='#' " . (($tab_short_name == get_request_var('tab')) ? "class='selected'" : '') . "'>" . $tabs_manager_edit[$tab_short_name] . '</a></li>';
			}else {
				print "<li class='subTab'><a " . (($tab_short_name == get_request_var('tab')) ? "class='selected'" : '') .
					" href='" . html_escape($config['url_path'] .
					'managers.php?action=edit&id=' . get_request_var('id') .
=======
				print "<li class='subTab'><a href='#' " . (($tab_short_name == grv('tab')) ? "class='selected'" : '') . "'>" . $tabs_manager_edit[$tab_short_name] . '</a></li>';
			} else {
				print "<li class='subTab'><a " . (($tab_short_name == grv('tab')) ? "class='selected'" : '') .
					" href='" . htmle(CACTI_PATH_URL .
					'managers.php?action=edit&id=' . grv('id') .
>>>>>>> origin/fix/jquery-deprecations
					'&tab=' . $tab_short_name) .
					"'>" . $tabs_manager_edit[$tab_short_name] . '</a></li>';
			}

			$i++;
		}

		print '</ul></nav></div>';

		if (read_config_option('legacy_menu_nav') != 'on') { ?>
		<script type='text/javascript'>

		$(function() {
			$('.subTab').find('a').click(function(event) {
				event.preventDefault();

				strURL  = $(this).attr('href');
				strURL += (strURL.indexOf('?') > 0 ? '&':'?');
				loadUrl({url:strURL})
			});
		});
		</script>
		<?php }
		}

<<<<<<< HEAD
	switch(get_request_var('tab')) {
||||||| 7dd05ee12
	switch(get_request_var('tab')){
=======
	switch(grv('tab')) {
>>>>>>> origin/fix/jquery-deprecations
		case 'notifications':
			manager_notifications($id, $header_label);

			break;
		case 'logs':
			manager_logs($id, $header_label);

			break;
		default:
			form_start('managers.php');

			html_start_box($header_label, '100%', true, 3, 'center', '');

			draw_edit_form(
				[
					'config' => ['no_form_tag' => true],
					'fields' => inject_form_variables($fields_manager_edit, (isset($manager) ? $manager : []))
				]
			);

			html_end_box(true, true);

			form_save_button('managers.php', 'return');

			?>
			<script type='text/javascript'>

			// Need to set this for global snmpv3 functions to remain sane between edits
			snmp_security_initialized = false;

			$(function() {
				setSNMP();
			});
			</script>
			<?php
	}

	?>
	<script language='javascript' type='text/javascript' >
	$(function() {
		$('.tooltip').tooltip({
			track: true,
			position: { collision: 'flipfit' },
			content: function() { return DOMPurify.sanitize($(this).attr('title')); }
		});
	});
	</script>
	<?php
}

function create_manager_notification_filter() : array {
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

function draw_manager_notification_filter(bool $render = false, string $header_label = '') : void {
	$filters = create_manager_notification_filter();

	// create the page filter
	$pageFilter = new CactiTableFilter($header_label, 'managers.php?action=edit&tab=notifications&id=' . gfrv('id'), 'form_snmpagent_managers', 'sess_snmp_cache');

	$pageFilter->rows_label = __('OIDs');
	$pageFilter->set_filter_array($filters);

	if ($render) {
		$pageFilter->render();
	} else {
		$pageFilter->sanitize();
	}
}

function manager_notifications(int $id, string $header_label) : void {
	global $item_rows, $mactions;

	draw_manager_notification_filter(true, $header_label);

	if (grv('rows') == '-1') {
		$rows = read_config_option('num_rows_table');
	} else {
		$rows = grv('rows');
	}

	html_start_box($header_label, '100%', false, 3, 'center', '');

	$sql_where  = "WHERE `kind`='Notification'";
	$sql_params = [];

<<<<<<< HEAD
	function applyFilter() {
		strURL  = 'managers.php?action=edit&tab=notifications&id=<?php print $id; ?>';
		strURL += '&mib=' + $('#mib').val();
		strURL += '&rows=' + $('#rows').val();
		strURL += '&filter=' + $('#filter').val();
		strURL += '&header=false';

		loadPageNoHeader(strURL);
||||||| 7dd05ee12
	function applyFilter() {
		strURL  = 'managers.php?action=edit&tab=notifications&id=<?php echo $id; ?>';
		strURL += '&mib=' + $('#mib').val();
		strURL += '&rows=' + $('#rows').val();
		strURL += '&filter=' + $('#filter').val();
		strURL += '&header=false';

		loadPageNoHeader(strURL);
=======
	// filter by host
	if (grv('mib') != 'any' && grv('mib') != '-1') {
		$sql_where .= ($sql_where != '' ? ' AND ' : 'WHERE ') . ' snmpagent_cache.mib = ?';
		$sql_params[] = grv('mib');
>>>>>>> origin/fix/jquery-deprecations
	}

<<<<<<< HEAD
	function clearFilter() {
		strURL = 'managers.php?action=edit&tab=notifications&id=<?php print $id; ?>&clear=1&header=false';
		loadPageNoHeader(strURL);
||||||| 7dd05ee12
	function clearFilter() {
		strURL = 'managers.php?action=edit&tab=notifications&id=<?php echo $id; ?>&clear=1&header=false';
		loadPageNoHeader(strURL);
=======
	// filter by search string
	if (grv('filter') != 'any') {
		$sql_where .= ($sql_where != '' ? ' AND ' : 'WHERE ') . ' (`oid` LIKE ? OR `name` LIKE ? OR `mib` LIKE ?)';

		$sql_params[] = '%' . grv('filter') . '%';
		$sql_params[] = '%' . grv('filter') . '%';
		$sql_params[] = '%' . grv('filter') . '%';
>>>>>>> origin/fix/jquery-deprecations
	}

	$sql_order = ' ORDER by `oid`';

	form_start('managers.php', 'chk');

	$total_rows = db_fetch_cell_prepared("SELECT COUNT(*)
		FROM snmpagent_cache
		$sql_where",
		$sql_params);

	$snmp_cache_sql = "SELECT *
		FROM snmpagent_cache
		$sql_where
		$sql_order
		LIMIT " . ($rows * (grv('page') - 1)) . ',' . $rows;

	$snmp_cache = db_fetch_assoc_prepared($snmp_cache_sql, $sql_params);

	$registered_notifications = db_fetch_assoc_prepared('SELECT notification, mib
		FROM snmpagent_managers_notifications
		WHERE manager_id = ?',
		[$id]);

	$notifications = [];

	if ($registered_notifications && cacti_sizeof($registered_notifications) > 0) {
		foreach ($registered_notifications as $registered_notification) {
			$notifications[$registered_notification['mib']][$registered_notification['notification']] = 1;
		}
	}

	$display_text = [
		__('Name'),
		__('OID'),
		__('MIB'),
		__('Kind'),
		__('Max-Access'),
		__('Monitored')
	];

	// generate page list
	$nav = html_nav_bar('managers.php?action=edit&id=' . $id . '&tab=notifications&mib=' . grv('mib') . '&filter=' . grv('filter'), MAX_DISPLAY_PAGES, grv('page'), $rows, $total_rows, cacti_sizeof($display_text) + 1, __('Notifications'), 'page', 'main');

	print $nav;

	html_start_box('', '100%', false, 3, 'center', '');

	html_header_checkbox($display_text, true, 'managers.php?action=edit&tab=notifications&id=' . $id);

	if (cacti_sizeof($snmp_cache)) {
		foreach ($snmp_cache as $item) {
			$row_id = $item['mib'] . '__' . $item['name'];
			$oid    = filter_value($item['oid'], grv('filter'));
			$name   = filter_value($item['name'], grv('filter'));
			$mib    = filter_value($item['mib'], grv('filter'));

			form_alternate_row('line' . $row_id, false);

			if ($item['description']) {
<<<<<<< HEAD
				print '<td><a href="#" title="<div class=\'header\'>' . $name . '</div><div class=\'content preformatted\'>' . $item['description']. '</div>" class="tooltip">' . $name . '</a></td>';
||||||| 7dd05ee12
				print '<td><a href="#" title="<div class=\'header\'>' . $name . '</div><div class=\'content preformatted\'>' . $item['description']. '</div>" class="tooltip">' . $name . '</a></td>';
			}else {
=======
				form_selectable_cell(filter_value($name, '', '#', $item['description']), $row_id);
>>>>>>> origin/fix/jquery-deprecations
			} else {
				form_selectable_cell($name, $row_id);
			}

			form_selectable_cell($oid, $row_id);
			form_selectable_cell($mib, $row_id);
			form_selectable_ecell($item['kind'], $row_id);
			form_selectable_cell($item['max-access'],$row_id);
			form_selectable_cell(((isset($notifications[$item['mib']]) && isset($notifications[$item['mib']][$item['name']])) ? '<span class="deviceUp">' . __('Enabled') : '<span class="deviceDown">' . __('Disabled')) . '</span>', $row_id);
			form_checkbox_cell($item['oid'], $row_id);

			form_end_row();
		}
	} else {
		print '<tr class="tableRow odd"><td colspan="7"><em>' . __('No SNMP Notifications') . '</em></td></tr>';
	}

	form_hidden_box('id', grv('id'), '');

	html_end_box(false);

	if (cacti_sizeof($snmp_cache)) {
		print $nav;
	}

	draw_actions_dropdown($mactions);

	form_end();
}

function create_manager_log_filter(array $severity_levels) : array {
	global $item_rows;

	$all = ['-1' => __('All')];

	$severity_levels = $all + $severity_levels;

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
					'filter'         => FILTER_VALIDATE_INT,
					'default'        => '-1',
					'pageset'        => true,
					'array'          => $severity_levels,
					'value'          => '-1'
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
			],
			'purge' => [
				'method'  => 'button',
				'display' => __('Purge'),
				'title'   => __('Purge the Notification Receiver Log'),
			]
		]
	];
}

function draw_manager_log_filter(bool $render = false, array $severity_levels = [], string $header_label = '') : void {
	$filters = create_manager_log_filter($severity_levels);

	// create the page filter
	$pageFilter = new CactiTableFilter($header_label, 'managers.php?action=edit&tab=logs&id=' . gfrv('id'), 'form_log', 'sess_snmp_log');

	$pageFilter->rows_label = __('Entries');
	$pageFilter->set_filter_array($filters);

	if ($render) {
		$pageFilter->render();
	} else {
		$pageFilter->sanitize();
	}
}

function manager_logs(int $id, string $header_label) : void {
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

	if (grv('action') == 'purge') {
		db_execute_prepared('DELETE FROM snmpagent_notifications_log WHERE manager_id = ?', [$id]);
		srv('clear', true);
	}

	draw_manager_log_filter(true, $severity_levels, $header_label);
	// ====================================================

	if (grv('rows') == '-1') {
		$rows = read_config_option('num_rows_table');
	} else {
		$rows = grv('rows');
	}

	$sql_params   = [];

	$sql_where    = 'WHERE snl.manager_id = ?';
	$sql_params[] = $id;

	// filter by severity
	if (grv('severity') > 0) {
		$sql_where .= ' AND snl.severity = ?';
		$sql_params[] = grv('severity');
	}

	// filter by search string
	if (grv('filter') != '') {
		$sql_where .= ' AND (`varbinds` LIKE ?)';
		$sql_params[] = '%' . grv('severity') . '%';
	}

	$sql_order = ' ORDER by `id` DESC';

<<<<<<< HEAD
	function highlightStatus(selectID) {
		if ($('#status_' + selectID).val() == 'ON') {
			$('#status_' + selectID).css('background-color', 'LawnGreen');
		} else {
			$('#status_' + selectID).css('background-color', 'OrangeRed');
		}
	}

	</script>
	<tr class='even'>
		<td>
			<form name='form_snmpagent_manager_logs' action='managers.php'>
				<table class='filterTable'>
					<tr>
						<td>
							<?php print __('Search');?>
						</td>
						<td>
							<input type='text' class='ui-state-default ui-corner-all' id='filter' size='25' value='<?php print html_escape_request_var('filter');?>'>
						</td>
						<td>
							<?php print __('Severity');?>
						</td>
						<td>
							<select id='severity' onChange='applyFilter()'>
								<option value='-1'<?php if (get_request_var('severity') == '-1') {?> selected<?php }?>><?php print __('Any');?></option>
								<?php
								foreach ($severity_levels as $level => $name) {
									print "<option value='" . $level . "'"; if (get_request_var('severity') == $level) { print ' selected'; } print '>' . $name . '</option>';
								}
								?>
							</select>
						</td>
						<td>
							<span>
								<input type='button' class='ui-button ui-corner-all ui-widget' id='refresh' value='<?php print __esc('Go');?>' title='<?php print __esc('Set/Refresh Filters');?>'>
								<input type='button' class='ui-button ui-corner-all ui-widget' id='clear' value='<?php print __esc('Clear');?>' title='<?php print __esc('Clear Filters');?>'>
								<input type='button' class='ui-button ui-corner-all ui-widget' id='purge' value='<?php print __esc('Purge');?>' title='<?php print __esc('Purge Notification Log');?>'>
							</span>
						</td>
					</tr>
				</table>
				<input type='hidden' name='action' value='edit'>
				<input type='hidden' name='tab' value='logs'>
				<input type='hidden' id='id' value='<?php print get_request_var('id'); ?>'>
			</form>
		</td>
	</tr>
	<?php

	html_end_box();

	$sql_where = " snl.manager_id='" . $id . "'";

	/* filter by severity */
	if (get_request_var('severity') == '-1') {
		/* Show all items */
	} elseif (!isempty_request_var('severity')) {
		$sql_where .= " AND snl.severity='" . get_request_var('severity') . "'";
	}

	/* filter by search string */
	if (get_request_var('filter') != '') {
		$sql_where .= ' AND (`varbinds` LIKE ' . db_qstr('%' . get_request_var('filter') . '%') . ')';
	}

	$sql_where .= ' ORDER by `id` DESC';
||||||| 7dd05ee12
	function highlightStatus(selectID){
		if ($('#status_' + selectID).val() == 'ON') {
			$('#status_' + selectID).css('background-color', 'LawnGreen');
		}else {
			$('#status_' + selectID).css('background-color', 'OrangeRed');
		}
	}

	</script>
	<tr class='even'>
		<td>
			<form name='form_snmpagent_manager_logs' action='managers.php'>
				<table class='filterTable'>
					<tr>
						<td>
							<?php print __('Search');?>
						</td>
						<td>
							<input type='text' class='ui-state-default ui-corner-all' id='filter' size='25' value='<?php print html_escape_request_var('filter');?>'>
						</td>
						<td>
							<?php print __('Severity');?>
						</td>
						<td>
							<select id='severity' onChange='applyFilter()'>
								<option value='-1'<?php if (get_request_var('severity') == '-1') {?> selected<?php }?>><?php print __('Any');?></option>
								<?php
								foreach ($severity_levels as $level => $name) {
									print "<option value='" . $level . "'"; if (get_request_var('severity') == $level) { print ' selected'; } print '>' . $name . '</option>';
								}
								?>
							</select>
						</td>
						<td>
							<span>
								<input type='button' class='ui-button ui-corner-all ui-widget' id='refresh' value='<?php print __esc('Go');?>' title='<?php print __esc('Set/Refresh Filters');?>'>
								<input type='button' class='ui-button ui-corner-all ui-widget' id='clear' value='<?php print __esc('Clear');?>' title='<?php print __esc('Clear Filters');?>'>
								<input type='button' class='ui-button ui-corner-all ui-widget' id='purge' value='<?php print __esc('Purge');?>' title='<?php print __esc('Purge Notification Log');?>'>
							</span>
						</td>
					</tr>
				</table>
				<input type='hidden' name='action' value='edit'>
				<input type='hidden' name='tab' value='logs'>
				<input type='hidden' id='id' value='<?php print get_request_var('id'); ?>'>
			</form>
		</td>
	</tr>
	<?php

	html_end_box();

	$sql_where = " snl.manager_id='" . $id . "'";

	/* filter by severity */
	if (get_request_var('severity') == '-1') {
		/* Show all items */
	} elseif (!isempty_request_var('severity')) {
		$sql_where .= " AND snl.severity='" . get_request_var('severity') . "'";
	}

	/* filter by search string */
	if (get_request_var('filter') != '') {
		$sql_where .= ' AND (`varbinds` LIKE ' . db_qstr('%' . get_request_var('filter') . '%') . ')';
	}

	$sql_where .= ' ORDER by `id` DESC';
=======
>>>>>>> origin/fix/jquery-deprecations
	$sql_query = "SELECT snl.*, sc.description
		FROM snmpagent_notifications_log AS snl
		LEFT JOIN snmpagent_cache AS sc
		ON sc.name = snl.notification
		$sql_where
		$sql_order
		LIMIT " . ($rows * (grv('page') - 1)) . ',' . $rows;

	form_start('managers.php', 'chk');

	$total_rows = db_fetch_cell_prepared("SELECT COUNT(*)
		FROM snmpagent_notifications_log AS snl
		$sql_where",
		$sql_params);

	$logs = db_fetch_assoc_prepared($sql_query, $sql_params);

	$display_text = [
		__('Data'),
		__('Time'),
		__('Notification'),
		__('Varbinds')
	];

	$nav = html_nav_bar('managers.php?action=exit&id=' . $id . '&tab=logs&mib=' . grv('mib') . '&filter=' . grv('filter'), MAX_DISPLAY_PAGES, grv('page'), $rows, $total_rows, cacti_sizeof($display_text), __('Receivers'), 'page', 'main');

	print $nav;

	html_start_box('', '100%', false, 3, 'center', '');

	html_header($display_text);

	if (cacti_sizeof($logs)) {
		foreach ($logs as $item) {
			$varbinds = filter_value($item['varbinds'], grv('filter'));

			form_alternate_row('line' . $item['id'], true);

			form_selectable_cell(filter_value('', '', '#', __esc('Severity Level') . ': ' . $severity_levels[$item['severity']]), $item['id'], '', 'width:10px;background-color:' . $severity_colors[$item['severity']] . ';border-top:1px solid white;border-bottom:1px solid white;');
			form_selectable_cell(date('Y/m/d H:i:s', $item['time']), $item['id']);

			if ($item['description']) {
				$description = '';
<<<<<<< HEAD
				$lines = preg_split( '/\r\n|\r|\n/', $item['description']);

				foreach($lines as $line) {
					$description .= html_escape(trim($line)) . '<br>';
||||||| 7dd05ee12
				$lines = preg_split( '/\r\n|\r|\n/', $item['description']);
				foreach($lines as $line) {
					$description .= html_escape(trim($line)) . '<br>';
=======
				$lines       = preg_split('/\r\n|\r|\n/', $item['description']);

				foreach ($lines as $line) {
					$description .= htmle(trim($line)) . '<br>';
>>>>>>> origin/fix/jquery-deprecations
				}

<<<<<<< HEAD
				print '<td><a href="#" onMouseOut="hideTooltip(snmpagentTooltip)" onMouseMove="showTooltip(event, snmpagentTooltip, \'' . $item['notification'] . '\', \'' . $description . '\')">' . $item['notification'] . '</a></td>';
			} else {

				print "<td>{$item['notification']}</td>";
||||||| 7dd05ee12
				print '<td><a href="#" onMouseOut="hideTooltip(snmpagentTooltip)" onMouseMove="showTooltip(event, snmpagentTooltip, \'' . $item['notification'] . '\', \'' . $description . '\')">' . $item['notification'] . '</a></td>';
			}else {
				print "<td>{$item['notification']}</td>";
=======
				form_selectable_cell(filter_value($item['notification'], '', '#', $item['notification'] . $description), $item['id']);
			} else {
				form_selectable_ecell($item['notification'], $item['id']);
>>>>>>> origin/fix/jquery-deprecations
			}

<<<<<<< HEAD
			print "<td>$varbinds</td>";
||||||| 7dd05ee12
			print "<td>$varbinds</td>";
=======
			form_selectable_cell($varbinds, $item['id']);
>>>>>>> origin/fix/jquery-deprecations

			form_end_row();
		}
	} else {
		print '<tr class="tableRow odd"><td colspan="4"><em>' . __('No SNMP Notification Log Entries') . '</em></td></tr>';
	}

	html_end_box();

	if (cacti_sizeof($logs)) {
		print $nav;
	}

	?>
	<input type='hidden' name='id' value='<?php print gfrv('id'); ?>'>
	<div style='display:none' id='snmpagentTooltip'></div>
	<?php
}

<<<<<<< HEAD
function form_save() {
	if (!isset_request_var('tab')) {
		set_request_var('tab', 'general');
	}

	/* ================= input validation ================= */
	get_filter_request_var('id');
	get_filter_request_var('max_log_size');

	if (!in_array(get_nfilter_request_var('max_log_size'), range(1,31))) {
		//	die_html_input_error();
||||||| 7dd05ee12
function form_save() {
	if (!isset_request_var('tab')) set_request_var('tab', 'general');

	/* ================= input validation ================= */
	get_filter_request_var('id');
	get_filter_request_var('max_log_size');

	if (!in_array(get_nfilter_request_var('max_log_size'), range(1,31))) {
		//	die_html_input_error();
=======
function form_save() : void {
	if (!isrv('tab')) {
		srv('tab', 'general');
>>>>>>> origin/fix/jquery-deprecations
	}

<<<<<<< HEAD
	switch(get_nfilter_request_var('tab')) {
||||||| 7dd05ee12
	switch(get_nfilter_request_var('tab')){
=======
	// ================= input validation =================
	gfrv('id');
	gfrv('max_log_size');

	if (!in_array(gnrv('max_log_size'), range(1,31), true)) {
		die_html_input_error('max_log_size');
	}
	// ================= input validation =================

	switch(gnrv('tab')) {
>>>>>>> origin/fix/jquery-deprecations
		case 'notifications':
			header('Location: managers.php?action=edit&tab=notifications&id=' . grv('id'));

			break;
		default:
			$save['id']             = grv('id');
			$save['description']    = form_input_validate(trim(gnrv('description')), 'description', '', false, 3);
			$save['hostname']       = form_input_validate(trim(gnrv('hostname')), 'hostname', '', false, 3);
			$save['disabled']       = form_input_validate(gnrv('disabled'), 'disabled', '^on$', true, 3);
			$save['max_log_size']   = gnrv('max_log_size');
			$save['snmp_version']   = form_input_validate(gnrv('snmp_version'), 'snmp_version', '^[1-3]$', false, 3);
			$save['snmp_community'] = form_input_validate(gnrv('snmp_community'), 'snmp_community', '', true, 3);

			if ($save['snmp_version'] == 3) {
<<<<<<< HEAD
				$save['snmp_username']        = form_input_validate(get_nfilter_request_var('snmp_username'), 'snmp_username', '', true, 3);
				$save['snmp_password']        = form_input_validate(get_nfilter_request_var('snmp_password'), 'snmp_password', '', true, 3);
				$save['snmp_auth_protocol']   = form_input_validate(get_nfilter_request_var('snmp_auth_protocol'), 'snmp_auth_protocol', "^\[None\]|MD5|SHA|SHA224|SHA256|SHA392|SHA512$", true, 3);
				$save['snmp_priv_passphrase'] = form_input_validate(get_nfilter_request_var('snmp_priv_passphrase'), 'snmp_priv_passphrase', '', true, 3);
				$save['snmp_priv_protocol']   = form_input_validate(get_nfilter_request_var('snmp_priv_protocol'), 'snmp_priv_protocol', "^\[None\]|DES|AES|AES128|AES192|AES192C|AES256|AES256C$", true, 3);
||||||| 7dd05ee12
				$save['snmp_username']        = form_input_validate(get_nfilter_request_var('snmp_username'), 'snmp_username', '', true, 3);
				$save['snmp_password']        = form_input_validate(get_nfilter_request_var('snmp_password'), 'snmp_password', '', true, 3);
				$save['snmp_auth_protocol']   = form_input_validate(get_nfilter_request_var('snmp_auth_protocol'), 'snmp_auth_protocol', "^\[None\]|MD5|SHA$", true, 3);
				$save['snmp_priv_passphrase'] = form_input_validate(get_nfilter_request_var('snmp_priv_passphrase'), 'snmp_priv_passphrase', '', true, 3);
				$save['snmp_priv_protocol']   = form_input_validate(get_nfilter_request_var('snmp_priv_protocol'), 'snmp_priv_protocol', "^\[None\]|DES|AES128$", true, 3);
=======
				$save['snmp_username']        = form_input_validate(gnrv('snmp_username'), 'snmp_username', '', true, 3);
				$save['snmp_password']        = form_input_validate(gnrv('snmp_password'), 'snmp_password', '', true, 3);
				$save['snmp_auth_protocol']   = form_input_validate(gnrv('snmp_auth_protocol'), 'snmp_auth_protocol', "^\[None\]|MD5|SHA|SHA224|SHA256|SHA392|SHA512$", true, 3);
				$save['snmp_priv_passphrase'] = form_input_validate(gnrv('snmp_priv_passphrase'), 'snmp_priv_passphrase', '', true, 3);
				$save['snmp_priv_protocol']   = form_input_validate(gnrv('snmp_priv_protocol'), 'snmp_priv_protocol', "^\[None\]|DES|AES|AES128|AES192|AES192C|AES256|AES256C$", true, 3);
>>>>>>> origin/fix/jquery-deprecations
				$save['snmp_engine_id']       = form_input_validate(get_request_var_post('snmp_engine_id'), 'snmp_engine_id', '', false, 3);
			} else {
				$save['snmp_username']        = '';
				$save['snmp_password']        = '';
				$save['snmp_auth_protocol']   = '';
				$save['snmp_priv_passphrase'] = '';
				$save['snmp_priv_protocol']   = '';
				$save['snmp_engine_id']       = '';
			}

			$save['snmp_port']         = form_input_validate(gnrv('snmp_port'), 'snmp_port', '^[0-9]+$', false, 3);
			$save['snmp_message_type'] = form_input_validate(gnrv('snmp_message_type'), 'snmp_message_type', '^[1-2]$', false, 3);
			$save['notes']             = form_input_validate(gnrv('notes'), 'notes', '', true, 3);

			if ($save['snmp_version'] == 3 && ($save['snmp_password'] != gnrv('snmp_password_confirm'))) {
				raise_message(4);
			}

			if ($save['snmp_version'] == 3 && ($save['snmp_priv_passphrase'] != gnrv('snmp_priv_passphrase_confirm'))) {
				raise_message(4);
			}

			$manager_id = 0;

			if (!is_error_message()) {
				$manager_id = sql_save($save, 'snmpagent_managers');
				raise_message(($manager_id) ? 1 : 2);
			}

			break;
	}

	header('Location: managers.php?action=edit&id=' . (empty($manager_id) ? gnrv('id') : $manager_id));
}

<<<<<<< HEAD
function form_actions() {
	global $manager_actions, $manager_notification_actions;
||||||| 7dd05ee12
function form_actions(){
	global $manager_actions, $manager_notification_actions;
=======
function form_actions() : void {
	global $actions, $mactions;
>>>>>>> origin/fix/jquery-deprecations

	if (isrv('selected_items')) {
		if (isrv('action_receivers')) {
			$selected_items = cacti_unserialize(stripslashes(gnrv('selected_graphs_array')));

<<<<<<< HEAD
			if ($selected_items !== false) {
				$ids = implode(',', array_map('intval', $selected_items));

				if (get_nfilter_request_var('drp_action') == '1') { // delete
					db_execute('DELETE FROM snmpagent_managers WHERE id IN (' . $ids . ')');
					db_execute('DELETE FROM snmpagent_managers_notifications WHERE manager_id IN (' . $ids . ')');
					db_execute('DELETE FROM snmpagent_notifications_log WHERE manager_id IN (' . $ids . ')');
				} elseif (get_nfilter_request_var('drp_action') == '2') { // enable
					db_execute("UPDATE snmpagent_managers SET disabled = '' WHERE id IN (" . $ids . ')');
				} elseif (get_nfilter_request_var('drp_action') == '3') { // disable
					db_execute("UPDATE snmpagent_managers SET disabled = 'on' WHERE id IN (" . $ids . ')');
||||||| 7dd05ee12
			if ($selected_items != false) {
				if (get_nfilter_request_var('drp_action') == '1') { // delete
					db_execute('DELETE FROM snmpagent_managers WHERE id IN (' . implode(',' ,$selected_items) . ')');
					db_execute('DELETE FROM snmpagent_managers_notifications WHERE manager_id IN (' . implode(',' ,$selected_items) . ')');
					db_execute('DELETE FROM snmpagent_notifications_log WHERE manager_id IN (' . implode(',' ,$selected_items) . ')');
				} elseif (get_nfilter_request_var('drp_action') == '2') { // enable
					db_execute("UPDATE snmpagent_managers SET disabled = '' WHERE id IN (" . implode(',' ,$selected_items) . ')');
				} elseif (get_nfilter_request_var('drp_action') == '3') { // disable
					db_execute("UPDATE snmpagent_managers SET disabled = 'on' WHERE id IN (" . implode(',' ,$selected_items) . ')');
=======
			if ($selected_items != false) {
				if (gnrv('drp_action') == '1') { // delete
					db_execute('DELETE FROM snmpagent_managers WHERE id IN (' . implode(',' ,$selected_items) . ')');
					db_execute('DELETE FROM snmpagent_managers_notifications WHERE manager_id IN (' . implode(',' ,$selected_items) . ')');
					db_execute('DELETE FROM snmpagent_notifications_log WHERE manager_id IN (' . implode(',' ,$selected_items) . ')');
				} elseif (gnrv('drp_action') == '2') { // disable
					db_execute("UPDATE snmpagent_managers SET disabled = 'on' WHERE id IN (" . implode(',' ,$selected_items) . ')');
				} elseif (gnrv('drp_action') == '3') { // enable
					db_execute("UPDATE snmpagent_managers SET disabled = '' WHERE id IN (" . implode(',' ,$selected_items) . ')');
>>>>>>> origin/fix/jquery-deprecations
				}

				header('Location: managers.php');

				exit;
			}
		} elseif (isrv('action_receiver_notifications')) {
			// ================= input validation =================
			gfrv('id');
			// ====================================================

<<<<<<< HEAD
			$selected_items = cacti_unserialize(stripslashes(get_nfilter_request_var('selected_items')));
||||||| 7dd05ee12
			$selected_items = unserialize(stripslashes(get_nfilter_request_var('selected_items')));
=======
			$selected_items = cacti_unserialize(stripslashes(gnrv('selected_items')));
>>>>>>> origin/fix/jquery-deprecations

<<<<<<< HEAD
			if (is_array($selected_items)) {
				if (get_nfilter_request_var('drp_action') == '1') { // disable
					foreach($selected_items as $mib => $notifications) {
						foreach($notifications as $notification => $state) {
||||||| 7dd05ee12
			if ($selected_items !== false) {
				if (get_nfilter_request_var('drp_action') == '1') { // disable
					foreach($selected_items as $mib => $notifications) {
						foreach($notifications as $notification => $state) {
=======
			if ($selected_items !== false) {
				if (gnrv('drp_action') == '1') { // disable
					foreach ($selected_items as $mib => $notifications) {
						foreach ($notifications as $notification => $state) {
>>>>>>> origin/fix/jquery-deprecations
							db_execute_prepared('DELETE FROM snmpagent_managers_notifications
								WHERE `manager_id` = ?
								AND `mib` = ?
								AND `notification` = ?
								LIMIT 1',
								[gnrv('id'), $mib, $notification]);
						}
					}
				} elseif (gnrv('drp_action') == '2') { // enable
					foreach ($selected_items as $mib => $notifications) {
						foreach ($notifications as $notification => $state) {
							db_execute_prepared('INSERT IGNORE INTO snmpagent_managers_notifications
								(`manager_id`, `notification`, `mib`)
								VALUES (?, ?, ?)',
								[gnrv('id'), $notification, $mib]);
						}
					}
				}
			}

			header('Location: managers.php?action=edit&id=' . gnrv('id') . '&tab=notifications');

			exit;
		}
<<<<<<< HEAD
	} else {
		if (isset_request_var('action_receivers')) {
			$selected_items = array();
			$list = '';
			foreach($_POST as $key => $value) {
				if (strstr($key, 'chk_')) {
					/* grep manager's id */
					$id = substr($key, 4);
					/* ================= input validation ================= */
					input_validate_input_number($id);
					/* ==================================================== */
					$list .= '<li>' . html_escape(db_fetch_cell_prepared('SELECT description FROM snmpagent_managers WHERE id = ?', array($id))) . '</li>';
					$selected_items[] = $id;
				}
||||||| 7dd05ee12
	}else {
		if (isset_request_var('action_receivers')) {
			$selected_items = array();
			$list = '';
			foreach($_POST as $key => $value) {
				if (strstr($key, 'chk_')) {
					/* grep manager's id */
					$id = substr($key, 4);
					/* ================= input validation ================= */
					input_validate_input_number($id);
					/* ==================================================== */
					$list .= '<li>' . html_escape(db_fetch_cell_prepared('SELECT description FROM snmpagent_managers WHERE id = ?', array($id))) . '</li>';
					$selected_items[] = $id;
				}
=======
	} elseif (isrv('action_receivers')) {
		$ilist  = '';
		$iarray = [];

		foreach ($_POST as $key => $value) {
			if (strstr($key, 'chk_')) {
				// grep manager's id
				$id = substr($key, 4);
				// ================= input validation =================
				input_validate_input_number($id, 'id');
				// ====================================================

				$ilist .= '<li>' . htmle(db_fetch_cell_prepared('SELECT description FROM snmpagent_managers WHERE id = ?', [$id])) . '</li>';

				$iarray[] = $id;
>>>>>>> origin/fix/jquery-deprecations
			}
<<<<<<< HEAD

			top_header();

			form_start('managers.php');

			html_start_box($manager_actions[get_nfilter_request_var('drp_action')], '60%', '', '3', 'center', '');

			if (cacti_sizeof($selected_items)) {
				if (get_nfilter_request_var('drp_action') == '1') { // delete
					$msg = __n('Click \'Continue\' to delete the following Notification Receiver', 'Click \'Continue\' to delete following Notification Receiver', cacti_sizeof($selected_items));
				} elseif (get_nfilter_request_var('drp_action') == '2') { // enable
					$msg = __n('Click \'Continue\' to enable the following Notification Receiver', 'Click \'Continue\' to enable following Notification Receiver', cacti_sizeof($selected_items));
				} elseif (get_nfilter_request_var('drp_action') == '3') { // disable
					$msg = __n('Click \'Continue\' to disable the following Notification Receiver', 'Click \'Continue\' to disable following Notification Receiver', cacti_sizeof($selected_items));
				}

				print "<tr>
					<td class='textArea'>
						<p>$msg</p>
						<div class='itemlist'><ul>$list</ul></div>
					</td>
				</tr>";

				$save_html = "<input type='button' class='ui-button ui-corner-all ui-widget' value='" . __esc('Cancel') . "' onClick='cactiReturnTo()'><input type='submit' class='ui-button ui-corner-all ui-widget' value='" . __esc('Continue') . "' title='" . __esc('%s Notification Receivers', $manager_actions[get_nfilter_request_var('drp_action')]) . "'>";
			} else {
				raise_message(40);
				header('Location: managers.php?header=false');
				exit;
			}

			print "<tr>
				<td class='saveRow'>
				<input type='hidden' name='action' value='actions'>
				<input type='hidden' name='action_receivers' value='1'>
				<input type='hidden' name='selected_items' value='" . (isset($selected_items) ? serialize($selected_items) : '') . "'>
				<input type='hidden' name='drp_action' value='" . html_escape(get_nfilter_request_var('drp_action')) . "'>
				$save_html
				</td>
			</tr>";

			html_end_box();

			form_end();

			bottom_footer();
		} else {
			$selected_items = array();
			$list = '';

			/* ================= input validation ================= */
			get_filter_request_var('id');
			/* ==================================================== */

			foreach($_POST as $key => $value) {
				if (strstr($key, 'chk_')) {
					/* grep mib and notification name */
					$row_id = substr($key, 4);

					list($mib, $name) = explode('__', $row_id);

					$list .= '<li>' . html_escape($name) . ' (' . html_escape($mib) .')</li>';

					$selected_items[$mib][$name] = 1;
				}
			}

			top_header();

			form_start('managers.php');

			html_start_box($manager_notification_actions[get_nfilter_request_var('drp_action')], '60%', '', '3', 'center', '');

			if (cacti_sizeof($selected_items)) {
				$msg = (get_nfilter_request_var('drp_action') == 2)
					 ? __('Click \'Continue\' to forward the following Notification Objects to this Notification Receiver.')
					 : __('Click \'Continue\' to disable forwarding the following Notification Objects to this Notification Receiver.');

				print "<tr>
					<td class='textArea'>
						<p>$msg</p>
						<ul>$list</ul>
					</td>
				</tr>";

				$save_html = "<input type='button' class='ui-button ui-corner-all ui-widget' value='" . __esc('Cancel') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' class='ui-button ui-corner-all ui-widget' value='" . __esc('Continue') . "' title='" . __esc('Disable Notification Objects') . "'>";
			} else {
				print "<tr><td><span class='textError'>" . __('You must select at least one notification object.') . "</span></td></tr>";
				$save_html = "<input type='button' class='ui-button ui-corner-all ui-widget' value='" . __esc('Return') . "' onClick='cactiReturnTo()'>";
			}

			print "<tr>
				<td class='saveRow'>
				<input type='hidden' name='action' value='actions'>
				<input type='hidden' name='action_receiver_notifications' value='1'>
				<input type='hidden' name='selected_items' value='" . (isset($selected_items) ? serialize($selected_items) : '') . "'>
				<input type='hidden' name='id' value='" . get_nfilter_request_var('id') . "'>
				<input type='hidden' name='drp_action' value='" . html_escape(get_nfilter_request_var('drp_action')) . "'>
				$save_html
				</td>
			</tr>";

			html_end_box();

			form_end();

			bottom_footer();
||||||| 7dd05ee12

			top_header();

			form_start('managers.php');

			html_start_box($manager_actions[get_nfilter_request_var('drp_action')], '60%', '', '3', 'center', '');

			if (cacti_sizeof($selected_items)) {
				if (get_nfilter_request_var('drp_action') == '1') { // delete
					$msg = __n('Click \'Continue\' to delete the following Notification Receiver', 'Click \'Continue\' to delete following Notification Receiver', cacti_sizeof($selected_items));
				} elseif (get_nfilter_request_var('drp_action') == '2') { // enable
					$msg = __n('Click \'Continue\' to enable the following Notification Receiver', 'Click \'Continue\' to enable following Notification Receiver', cacti_sizeof($selected_items));
				} elseif (get_nfilter_request_var('drp_action') == '3') { // disable
					$msg = __n('Click \'Continue\' to disable the following Notification Receiver', 'Click \'Continue\' to disable following Notification Receiver', cacti_sizeof($selected_items));
				}

				print "<tr>
					<td class='textArea'>
						<p>$msg</p>
						<div class='itemlist'><ul>$list</ul></div>
					</td>
				</tr>";

				$save_html = "<input type='button' class='ui-button ui-corner-all ui-widget' value='" . __esc('Cancel') . "' onClick='cactiReturnTo()'><input type='submit' class='ui-button ui-corner-all ui-widget' value='" . __esc('Continue') . "' title='" . __esc('%s Notification Receivers', $manager_actions[get_nfilter_request_var('drp_action')]) . "'>";
			} else {
				raise_message(40);
				header('Location: managers.php?header=false');
				exit;
			}

			print "<tr>
				<td class='saveRow'>
				<input type='hidden' name='action' value='actions'>
				<input type='hidden' name='action_receivers' value='1'>
				<input type='hidden' name='selected_items' value='" . (isset($selected_items) ? serialize($selected_items) : '') . "'>
				<input type='hidden' name='drp_action' value='" . html_escape(get_nfilter_request_var('drp_action')) . "'>
				$save_html
				</td>
			</tr>\n";

			html_end_box();

			form_end();

			bottom_footer();
		}else {
			$selected_items = array();
			$list = '';

			/* ================= input validation ================= */
			get_filter_request_var('id');
			/* ==================================================== */

			foreach($_POST as $key => $value) {
				if (strstr($key, 'chk_')) {
					/* grep mib and notification name */
					$row_id = substr($key, 4);
					list($mib, $name) = explode('__', $row_id);
					$list .= '<li>' . html_escape($name) . ' (' . html_escape($mib) .')</li>';
					$selected_items[$mib][$name] = 1;
				}
			}

			top_header();

			form_start('managers.php');

			html_start_box($manager_notification_actions[get_nfilter_request_var('drp_action')], '60%', '', '3', 'center', '');

			if (cacti_sizeof($selected_items)) {
				$msg = (get_nfilter_request_var('drp_action') == 2)
					 ? __('Click \'Continue\' to forward the following Notification Objects to this Notification Receiver.')
					 : __('Click \'Continue\' to disable forwarding the following Notification Objects to this Notification Receiver.');

				print "<tr>
					<td class='textArea'>
						<p>$msg</p>
						<ul>$list</ul>
					</td>
				</tr>";

				$save_html = "<input type='button' class='ui-button ui-corner-all ui-widget' value='" . __esc('Cancel') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' class='ui-button ui-corner-all ui-widget' value='" . __esc('Continue') . "' title='" . __esc('Disable Notification Objects') . "'>";
			} else {
				print "<tr><td><span class='textError'>" . __('You must select at least one notification object.') . "</span></td></tr>\n";
				$save_html = "<input type='button' class='ui-button ui-corner-all ui-widget' value='" . __esc('Return') . "' onClick='cactiReturnTo()'>";
			}

			print "<tr>
				<td class='saveRow'>
				<input type='hidden' name='action' value='actions'>
				<input type='hidden' name='action_receiver_notifications' value='1'>
				<input type='hidden' name='selected_items' value='" . (isset($selected_items) ? serialize($selected_items) : '') . "'>
				<input type='hidden' name='id' value='" . get_nfilter_request_var('id') . "'>
				<input type='hidden' name='drp_action' value='" . html_escape(get_nfilter_request_var('drp_action')) . "'>
				$save_html
				</td>
			</tr>";

			html_end_box();

			form_end();

			bottom_footer();
=======
>>>>>>> origin/fix/jquery-deprecations
		}

		$form_data = [
			'general' => [
				'page'       => 'managers.php',
				'actions'    => $actions,
				'eaction'    => 'action_receivers',
				'optvar'     => 'drp_action',
				'item_array' => $iarray,
				'item_list'  => $ilist
			],
			'options' => [
				1 => [
					'smessage' => __('Click \'Continue\' to Delete the following Notification Receiver.'),
					'pmessage' => __('Click \'Continue\' to Delete following Notification Receivers.'),
					'scont'    => __('Delete Notification Receiver'),
					'pcont'    => __('Delete Notification Receivers')
				],
				2 => [
					'smessage' => __('Click \'Continue\' to Disable the following Notification Receiver.'),
					'pmessage' => __('Click \'Continue\' to Disable following Notification Receivers.'),
					'scont'    => __('Disable Notification Receiver'),
					'pcont'    => __('Disable Notification Receivers')
				],
				3 => [
					'smessage' => __('Click \'Continue\' to Enable the following Notification Receiver.'),
					'pmessage' => __('Click \'Continue\' to Enable following Notification Receivers.'),
					'scont'    => __('Enable Notification Receiver'),
					'pcont'    => __('Enable Notification Receivers'),
				]
			]
		];

		form_continue_confirmation($form_data);
	} else {
		$ilist  = '';
		$iarray = [];

		// ================= input validation =================
		gfrv('id');
		// ====================================================

		foreach ($_POST as $key => $value) {
			if (strstr($key, 'chk_')) {
				// grep mib and notification name
				$row_id = substr($key, 4);

				[$mib, $name] = explode('__', $row_id);

				$ilist .= '<li>' . htmle($name) . ' (' . htmle($mib) . ')</li>';

				$iarray[$mib][$name] = 1;
			}
		}

		$form_data = [
			'general' => [
				'page'       => 'managers.php?action=edit&tab=notifications&id=' . grv('id'),
				'actions'    => $mactions,
				'eaction'    => 'action_receiver_notifications',
				'optvar'     => 'drp_action',
				'item_array' => $iarray,
				'item_list'  => $ilist
			],
			'options' => [
				1 => [
					'smessage' => __('Click \'Continue\' to Disable Forwarding the following Notification Object the following Notification Receiver.'),
					'pmessage' => __('Click \'Continue\' to Disable Forwarding the following Notification Objects to the following Notification Receiver.'),
					'scont'    => __('Disable Forwarding Object'),
					'pcont'    => __('Disable Forwarding Objects')
				],
				2 => [
					'smessage' => __('Click \'Continue\' to Enable Forwarding the following Notification Object to this Notification Receiver.'),
					'pmessage' => __('Click \'Continue\' to Enable Forwarding the following Notification Objects Notification Receivers.'),
					'scont'    => __('Enable Forwarding Object'),
					'pcont'    => __('Enable Forwarding Objects')
				]
			]
		];

		form_continue_confirmation($form_data);
	}
}
