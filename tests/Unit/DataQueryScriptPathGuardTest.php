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

// get_script_query_path() rejects any resolved script path that still contains a
// parent-directory segment, so a malicious data query template cannot walk out
// of the Cacti tree to run an arbitrary binary.

it('returns an empty command when the resolved path contains a traversal', function () {
	expect(get_script_query_path('', '|path_cacti|/../../etc/passwd', 0))->toBe('')
		->and(get_script_query_path('', '/opt/x/../../bin/sh', 0))->toBe('')
		->and(get_script_query_path('', '..', 0))->toBe('');
});

it('returns the escaped script path for a clean template path', function () {
	$out = get_script_query_path('', '|path_cacti|/scripts/ss_host_disk.php', 0);

	expect($out)->not->toBe('')
		->and($out)->toContain('scripts/ss_host_disk.php')
		->and($out)->not->toContain('..');
});

it('keeps extra arguments after a clean script path', function () {
	$out = get_script_query_path('42', '|path_cacti|/scripts/query_host.php', 0);

	expect($out)->toContain('scripts/query_host.php')
		->and(trim($out))->not->toBe('');
});
