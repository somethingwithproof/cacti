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

define('CACTI_IN_INSTALL', 1);
require('./include/auth.php');

// forcibly unlock cacti if the user logg's out
$admin_user = read_config_option('admin_user');
$lockout    = read_config_option('cacti_lockout_status', true);

if ($lockout != '') {
	$lockout = json_decode($lockout, true);

	if ($admin_user == $_SESSION[SESS_USER_ID] && $lockout['session'] == session_id()) {
		set_config_option('cacti_lockout_status', '');
	}
}

set_default_action();

api_plugin_hook('logout_pre_session_destroy');

// Clear session
cacti_cookie_logout();
cacti_session_destroy();

$version = CACTI_VERSION;

api_plugin_hook('logout_post_session_destroy');

// allow for plugin based logout page
if (api_plugin_hook_function('custom_logout_message', OPER_MODE_NATIVE) === OPER_MODE_RESKIN) {
	exit;
}

<<<<<<< HEAD
/* Check to see if we are using Web Basic Auth */
if (get_request_var('action') == 'timeout' || get_request_var('action') == 'disabled' || get_request_var('action') == 'remote') {
	if (get_request_var('action') == 'timeout') {
		$message = __('You have been logged out of Cacti due to a session timeout.');
	} elseif (get_request_var('action') == 'disabled') {
		$message = __('You have been logged out of Cacti due to an account suspension.');
	} elseif (get_request_var('action') == 'remove') {
		$message = __('You have been logged out of Cacti due to a Remote Data Collector state change');
	} else {
		$message = '';
	}

	print "<!DOCTYPE HTML PUBLIC '-//W3C//DTD HTML 4.01 Transitional//EN' 'http://www.w3.org/TR/html4/loose.dtd'>";
	print "<html>";
	print "<head>";
	html_common_header(__('Logout of Cacti'));
	print "</head>";
	print "<body class='logoutBody'>
	<div class='logoutLeft'></div>
	<div class='logoutCenter'>
		<div class='logoutArea'>
			<div class='cactiLogoutLogo'></div>
			<legend>" . __('Automatic Logout') . "</legend>
			<div class='logoutTitle'>
				<p>" . $message . "</p>
				<p>" . __('Please close your browser or %sLogin Again%s', '</p><center>[<a href="index.php">', '</a>]</center>') . "
			</div>
			<div class='logoutErrors'></div>
		</div>
		<div class='versionInfo'>" . __('Version %s', $version) . " | " . COPYRIGHT_YEARS_SHORT . "</div>
	</div>
	<div class='logoutRight'></div>
	<script type='text/javascript'>
	$(function() {
		if (typeof myRefresh != 'undefined') {
			clearTimeout(myRefresh);
		}
||||||| 7dd05ee12
/* Check to see if we are using Web Basic Auth */
if (get_request_var('action') == 'timeout') {
	print "<!DOCTYPE HTML PUBLIC '-//W3C//DTD HTML 4.01 Transitional//EN' 'http://www.w3.org/TR/html4/loose.dtd'>";
	print "<html>";
	print "<head>";
	html_common_header(__('Logout of Cacti'));
	print "</head>";
	print "<body class='logoutBody'>
	<div class='logoutLeft'></div>
	<div class='logoutCenter'>
		<div class='logoutArea'>
			<div class='cactiLogoutLogo'></div>
			<legend>" . __('Automatic Logout') . "</legend>
			<div class='logoutTitle'>
				<p>" . __('You have been logged out of Cacti due to a session timeout.') . "</p>
				<p>" . __('Please close your browser or %sLogin Again%s', '</p><center>[<a href="index.php">', '</a>]</center>') . "
			</div>
			<div class='logoutErrors'></div>
		</div>
		<div class='versionInfo'>" . __('Version %s', $version) . " | " . COPYRIGHT_YEARS_SHORT . "</div>
	</div>
	<div class='logoutRight'></div>
	<script type='text/javascript'>
	$(function() {
		if (typeof myRefresh != 'undefined') {
			clearTimeout(myRefresh);
		}

		$('.loginLeft').css('width',parseInt($(window).width()*0.33)+'px');
		$('.loginRight').css('width',parseInt($(window).width()*0.33)+'px');
	});
	</script>";
	include('./include/global_session.php');
	print "</body>
	</html>";
} elseif (get_request_var('action') == 'disabled') {
	print "<!DOCTYPE HTML PUBLIC '-//W3C//DTD HTML 4.01 Transitional//EN' 'http://www.w3.org/TR/html4/loose.dtd'>";
	print "<html>";
	print "<head>";
	html_common_header(__('Logout of Cacti'));
	print "</head>";
	print "<body class='logoutBody'>
	<div class='logoutLeft'></div>
	<div class='logoutCenter'>
		<div class='logoutArea'>
			<div class='cactiLogoutLogo cactiLoginSuspend'></div>
			<legend>" . __('Automatic Logout') . "</legend>
			<div class='logoutTitle'>
				<p>" . __('You have been logged out of Cacti due to an account suspension.') . "</p>
				<p>" . __('Please close your browser or %sLogin Again%s', '</p><center>[<a href="index.php">', '</a>]</center>') . "
			</div>
			<div class='logoutErrors'></div>
		</div>
		<div class='versionInfo'>" . __('Version %s', $version) . " | " . COPYRIGHT_YEARS_SHORT . "</div>
	</div>
	<div class='logoutRight'></div>
	<script type='text/javascript'>
	$(function() {
		if (typeof myRefresh != 'undefined') {
			clearTimeout(myRefresh);
		}
=======
// Check to see if we are using Web Basic Auth
if (grv('action') == 'timeout' || grv('action') == 'disabled' || grv('action') == 'remote') {
	$hook   = 'logout';
	$reason = __('a Session Timeout');

	if (grv('action') == 'disabled') {
		$hook   = 'disabled';
		$reason = __('an Account Suspension');
	} elseif (grv('action') == 'remote') {
		$hook   = 'logout';
		$reason = __('a change in state of the Remote Data Collector');
	}
>>>>>>> origin/fix/jquery-deprecations

	html_auth_header($hook, __('Logout of Cacti'),  __('Automatic Logout'), __('You have been logged out of Cacti due to %s.', $reason));
	print '<div>' . __('Please close your browser or %sLogin Again%s', '[<a href="index.php">', '</a>]') . '</div>';
	html_auth_footer($hook, __('Cookies have been cleared'), '');
} else {
	// Default action
	clear_auth_cookie();

	header('Location: index.php');
}
