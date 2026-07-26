<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

// Regression guard: get_script_query_path() must reject a data query script path
// that contains a parent-directory traversal instead of handing it to the poller.

require_once __DIR__ . '/../Helpers/CactiLibBootstrap.php';
require_once __DIR__ . '/../../lib/data_query.php';

$traversal = get_script_query_path('', '|path_cacti|/../../etc/passwd', 0);

if ($traversal !== '') {
	fwrite(STDERR, "traversal path was not rejected: '$traversal'\n");
	exit(1);
}

$clean = get_script_query_path('', '|path_cacti|/scripts/ss_host_disk.php', 0);

if ($clean === '' || strpos($clean, 'scripts/ss_host_disk.php') === false) {
	fwrite(STDERR, "clean path was not returned as expected: '$clean'\n");
	exit(1);
}

print "develop data-query path guard regression passed\n";
