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
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 */

/*
 * Source-scan regression tests for JavaScript-context output hardening.
 *
 * Each test verifies that PHP values injected into JS variable assignments
 * go through json_encode rather than bare single-quoted string literals.
 * No runtime bootstrap needed — these are static file checks.
 */

$authProfilePath  = __DIR__ . '/../../auth_profile.php';
$htmlPath         = __DIR__ . '/../../lib/html.php';
$htmlReportsPath  = __DIR__ . '/../../lib/html_reports.php';
$settingsPath     = __DIR__ . '/../../settings.php';

// ------------------------------------------------------------------
// auth_profile.php
// ------------------------------------------------------------------

test('auth_profile currentTheme uses json_encode, not bare string literal', function () use ($authProfilePath) {
	$src = file_get_contents($authProfilePath);

	expect($src)->toContain('json_encode((string) get_selected_theme())');
	expect($src)->not->toContain("var currentTheme = '<?php print get_selected_theme();");
});

test('auth_profile currentLang uses json_encode, not bare string literal', function () use ($authProfilePath) {
	$src = file_get_contents($authProfilePath);

	expect($src)->toContain("json_encode((string) read_config_option('user_language'))");
	expect($src)->not->toContain("var currentLang = '<?php print read_config_option('user_language');");
});

test('auth_profile authMethod uses json_encode, not bare string literal', function () use ($authProfilePath) {
	$src = file_get_contents($authProfilePath);

	expect($src)->toContain("json_encode((string) read_config_option('auth_method'))");
	expect($src)->not->toContain("var authMethod = '<?php print read_config_option('auth_method');");
});

// ------------------------------------------------------------------
// lib/html.php — SNMP protocol vars + theme
// ------------------------------------------------------------------

test('html.php defaultSNMPAuthProtocol uses json_encode', function () use ($htmlPath) {
	$src = file_get_contents($htmlPath);

	expect($src)->toContain("json_encode((string) read_config_option('snmp_auth_protocol'))");
	expect($src)->not->toContain("var defaultSNMPAuthProtocol = '<?php print read_config_option('snmp_auth_protocol');");
});

test('html.php defaultSNMPPrivProtocol uses json_encode', function () use ($htmlPath) {
	$src = file_get_contents($htmlPath);

	expect($src)->toContain("json_encode((string) read_config_option('snmp_priv_protocol'))");
	expect($src)->not->toContain("var defaultSNMPPrivProtocol = '<?php print read_config_option('snmp_priv_protocol');");
});

test('html.php defaultSNMPSecurityLevel uses json_encode', function () use ($htmlPath) {
	$src = file_get_contents($htmlPath);

	expect($src)->toContain("json_encode((string) read_config_option('snmp_security_level'))");
	expect($src)->not->toContain("var defaultSNMPSecurityLevel = '<?php print read_config_option('snmp_security_level');");
});

test('html.php theme var uses json_encode', function () use ($htmlPath) {
	$src = file_get_contents($htmlPath);

	expect($src)->toContain('json_encode((string) $selectedTheme)');
	expect($src)->not->toContain("var theme = '<?php print \$selectedTheme;");
});

// ------------------------------------------------------------------
// lib/html_reports.php — reportsPage
// ------------------------------------------------------------------

test('html_reports.php reportsPage uses json_encode', function () use ($htmlReportsPath) {
	$src = file_get_contents($htmlReportsPath);

	expect($src)->toContain('json_encode((string) get_reports_page())');
	expect($src)->not->toContain("var reportsPage = '<?php print get_reports_page();");
});

// ------------------------------------------------------------------
// settings.php — dataCollectors
// ------------------------------------------------------------------

test('settings.php dataCollectors uses json_encode', function () use ($settingsPath) {
	$src = file_get_contents($settingsPath);

	expect($src)->toContain('json_encode((string) $data_collectors)');
	expect($src)->not->toContain("var dataCollectors = '<?php print \$data_collectors;");
});
