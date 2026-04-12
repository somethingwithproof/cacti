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

$guest_account = true;

require('./include/auth.php');
require_once(CACTI_PATH_LIBRARY . '/clog_webapi.php');
require_once(CACTI_PATH_LIBRARY . '/poller.php');
require_once(CACTI_PATH_LIBRARY . '/utility.php');

// check edit/alter permissions
if (!clog_admin()) {
<<<<<<< HEAD
	if (isset_request_var('header')) {
		if ($config['poller_id'] > 1) {
			print '<div style="display:none">cactiRemoteState</div>';
		} else {
			print '<div style="display:none">cactiPermissionDenied</div>';
		}
	} elseif ($config['poller_id'] > 1) {
||||||| 7dd05ee12
	echo __('FATAL: YOU DO NOT HAVE ACCESS TO THIS AREA OF CACTI');
=======
	if (POLLER_ID > 1) {
>>>>>>> origin/fix/jquery-deprecations
		header('Location: logout.php?action=remote');
	} else {
		header('Location: permission_denied.php');
	}

	exit;
}

load_current_session_value('page_referrer', 'page_referrer', '');

clog_view_logfile();
