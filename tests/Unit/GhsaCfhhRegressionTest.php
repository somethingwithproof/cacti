<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * Regression test for GHSA-cfhh-pwvx-gp5g / CVE-2026-40940.
 *
 * Reflected XSS via rfilter PCRE/HTML parser differential in
 * aggregate_graphs.php. validate_is_regex() accepts \' as an escaped PCRE
 * delimiter, but the HTML parser sees \' as a string terminator, letting
 * attacker break out of value='...' and inject onfocus=alert(1) autofocus.
 *
 * Fix wraps the output with htmle() so the attribute stays intact.
 */

test('aggregate_graphs.php escapes rfilter in HTML attribute', function () {
	$src = file_get_contents(__DIR__ . '/../../aggregate_graphs.php');
	expect($src)->not->toBeFalse();
	expect($src)->toContain("value='<?php print htmle(grv('rfilter')); ?>'");
	expect($src)->not->toMatch("/value='<\\?php print grv\\('rfilter'\\); \\?>'/");
});
