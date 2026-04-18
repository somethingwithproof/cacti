<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * Regression test for GHSA-977w-79m7-xjc4 / CVE-2026-40307.
 *
 * Stored XSS via SNMP-derived legend data in graph_xport.php HTML table
 * output. Crafted sysName/sysDescr values emit attacker-controlled HTML.
 * Fix wraps the legend output in html_escape().
 */

test('graph_xport.php escapes legend output in HTML table headers', function () {
	$src = file_get_contents(__DIR__ . '/../../graph_xport.php');
	expect($src)->not->toBeFalse();
	expect($src)->toContain("html_escape(\$xport_array['meta']['legend']['col' . \$i])");
});
