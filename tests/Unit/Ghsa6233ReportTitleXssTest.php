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
 * Regression test for GHSA-6233-v5hc-6gvf (CVE-2026-39952): stored XSS in
 * report tree expansion titles.
 *
 * In lib/reports.php, reports_expand_tree() builds $title from DB-sourced
 * tree/leaf/host/graph names and emits it raw inside <h3>$title</h3>. The
 * names were interpolated without escaping, so a tree/branch/device/graph
 * named e.g. <img src=x onerror=alert(document.cookie)> executed when the
 * report was viewed. The fix escapes each component at construction with
 * htmle(), matching the device line which already uses htmle($description).
 *
 * See: https://github.com/Cacti/cacti/security/advisories/GHSA-6233-v5hc-6gvf
 */

$reportsLib = __DIR__ . '/../../lib/reports.php';

test('lib/reports.php escapes the tree name in the report title', function () use ($reportsLib) {
	$src = file_get_contents($reportsLib);

	expect($src)->toContain("__('Tree:') . ' ' . htmle(\$tree_name)");
	expect($src)->not->toContain('__(\'Tree:\') . " $tree_name"');
});

test('lib/reports.php escapes the leaf name in the report title', function () use ($reportsLib) {
	$src = file_get_contents($reportsLib);

	expect($src)->toContain("\$title .= \$title_delimiter . ' ' . htmle(\$leaf_name)");
	expect($src)->not->toContain('$title .= $title_delimiter . " $leaf_name"');
});

test('lib/reports.php escapes the host name in the report title', function () use ($reportsLib) {
	$src = file_get_contents($reportsLib);

	expect($src)->toContain("\$title .= \$title_delimiter . ' ' . htmle(\$host_name)");
	expect($src)->not->toContain('$title .= $title_delimiter . " $host_name"');
});

test('lib/reports.php escapes the graph name in the report title', function () use ($reportsLib) {
	$src = file_get_contents($reportsLib);

	expect($src)->toContain("\$title .= \$title_delimiter . ' ' . htmle(\$graph_name)");
	expect($src)->not->toContain('$title .= $title_delimiter . " $graph_name"');
});
