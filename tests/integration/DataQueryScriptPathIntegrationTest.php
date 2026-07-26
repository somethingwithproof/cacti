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

require_once dirname(__DIR__) . '/Helpers/CactiLibBootstrap.php';
require_once dirname(__DIR__, 2) . '/lib/data_query.php';

// Exercises get_script_query_path() through the real path substitution and
// cacti_escapeshellcmd() layer so the command string it hands to the poller is
// shell-safe and cannot escape the Cacti tree.

it('resolves a |path_cacti| template under the Cacti base', function () {
	$out = get_script_query_path('', '|path_cacti|/scripts/ss_net_snmp_disk.php', 0);

	expect($out)->toContain(CACTI_PATH_BASE)
		->and($out)->toContain('scripts/ss_net_snmp_disk.php');
});

it('shell-escapes metacharacters in the script path', function () {
	// No traversal segment, so the path passes the guard and reaches
	// cacti_escapeshellcmd, which must neutralize the command separator.
	$out = get_script_query_path('', '/tmp/evil;touch /tmp/cacti_pwned', 0);

	// escapeshellcmd backslash-escapes the separator, so the raw command break
	// is gone and the neutralized form is present.
	expect($out)->not->toContain('evil;touch')
		->and($out)->toContain('evil\\;touch');
});

it('blocks a traversal payload before it reaches the shell layer', function () {
	expect(get_script_query_path('', '|path_cacti|/scripts/../../../../bin/sh', 0))->toBe('');
});
