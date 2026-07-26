<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

// Regression guard: a data input command string carrying a shell metacharacter
// must be rejected by cacti_input_string_is_safe() and by the import path that
// persists data input methods.

require_once __DIR__ . '/../Helpers/CactiLibBootstrap.php';
require_once __DIR__ . '/../../lib/import.php';

$GLOBALS['config'][OPTIONS_CLI]['allow_unsafe_metachars'] = '';

$payload = 'ss_check.php; rm -rf /';

if (cacti_input_string_is_safe($payload) !== false) {
	fwrite(STDERR, "shell metacharacter payload was accepted by cacti_input_string_is_safe\n");
	exit(1);
}

if (cacti_input_string_is_safe('perl <path_cacti>/scripts/ss.pl <hostname>') !== true) {
	fwrite(STDERR, "legitimate placeholder template was wrongly rejected\n");
	exit(1);
}

$GLOBALS['fields_data_input_edit']         = ['name' => [], 'input_string' => []];
$GLOBALS['fields_data_input_field_edit']    = [];
$GLOBALS['fields_data_input_field_edit_1']  = [];
$GLOBALS['import_debug_info']               = [];
$GLOBALS['preview_only']                    = false;

$xml   = ['name' => 'Evil', 'input_string' => $payload];
$cache = [];

if (xml_to_data_input_method('00112233445566778899aabbccddeeff', $xml, $cache) !== false) {
	fwrite(STDERR, "import path accepted an injecting input_string\n");
	exit(1);
}

print "develop input string guard regression passed\n";
