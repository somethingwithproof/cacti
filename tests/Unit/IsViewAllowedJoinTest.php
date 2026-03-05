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
 * Tests for the is_view_allowed() JOIN condition fix.
 *
 * The JOIN between user_auth_group (uag) and user_auth_group_members (uagm)
 * must use uag.id = uagm.group_id so rows match by group membership.
 * A previous bug used uagm.user_id in the ON clause, which joined on
 * the wrong column and returned incorrect permission results.
 *
 * See: https://github.com/Cacti/cacti/pull/XXXX
 */

// --- is_view_allowed function exists in lib/auth.php ---

test('is_view_allowed function is defined in lib/auth.php', function () {
	$path = dirname(__DIR__, 2) . '/lib/auth.php';

	expect(file_exists($path))->toBeTrue();

	$source = file_get_contents($path);

	expect($source)->toContain('function is_view_allowed(');
});

// --- JOIN uses uagm.group_id, not uagm.user_id ---

test('is_view_allowed JOIN condition uses uagm.group_id', function () {
	$path = dirname(__DIR__, 2) . '/lib/auth.php';
	$source = file_get_contents($path);

	/* Extract the is_view_allowed function body */
	$start = strpos($source, 'function is_view_allowed(');
	expect($start)->not->toBeFalse();

	/* Find the closing brace by counting braces from the opening */
	$braceDepth = 0;
	$funcStart = strpos($source, '{', $start);
	$len = strlen($source);
	$funcEnd = $funcStart;

	for ($i = $funcStart; $i < $len; $i++) {
		if ($source[$i] === '{') {
			$braceDepth++;
		} elseif ($source[$i] === '}') {
			$braceDepth--;

			if ($braceDepth === 0) {
				$funcEnd = $i;

				break;
			}
		}
	}

	$funcBody = substr($source, $funcStart, $funcEnd - $funcStart + 1);

	/* The JOIN must reference uagm.group_id, not uagm.user_id */
	expect($funcBody)->toContain('ON uag.id = uagm.group_id');
});

// --- JOIN does NOT use the old incorrect column ---

test('is_view_allowed JOIN condition does not use uagm.user_id as join key', function () {
	$path = dirname(__DIR__, 2) . '/lib/auth.php';
	$source = file_get_contents($path);

	$start = strpos($source, 'function is_view_allowed(');
	$funcStart = strpos($source, '{', $start);
	$len = strlen($source);
	$braceDepth = 0;
	$funcEnd = $funcStart;

	for ($i = $funcStart; $i < $len; $i++) {
		if ($source[$i] === '{') {
			$braceDepth++;
		} elseif ($source[$i] === '}') {
			$braceDepth--;

			if ($braceDepth === 0) {
				$funcEnd = $i;

				break;
			}
		}
	}

	$funcBody = substr($source, $funcStart, $funcEnd - $funcStart + 1);

	/* The ON clause must NOT join on uagm.user_id */
	expect($funcBody)->not->toContain('ON uag.id = uagm.user_id');
});

// --- WHERE clause still filters by uagm.user_id (session user) ---

test('is_view_allowed WHERE clause filters by uagm.user_id for session user', function () {
	$path = dirname(__DIR__, 2) . '/lib/auth.php';
	$source = file_get_contents($path);

	$start = strpos($source, 'function is_view_allowed(');
	$funcStart = strpos($source, '{', $start);
	$len = strlen($source);
	$braceDepth = 0;
	$funcEnd = $funcStart;

	for ($i = $funcStart; $i < $len; $i++) {
		if ($source[$i] === '{') {
			$braceDepth++;
		} elseif ($source[$i] === '}') {
			$braceDepth--;

			if ($braceDepth === 0) {
				$funcEnd = $i;

				break;
			}
		}
	}

	$funcBody = substr($source, $funcStart, $funcEnd - $funcStart + 1);

	/* uagm.user_id must still appear in the WHERE clause for filtering */
	expect($funcBody)->toContain('AND uagm.user_id = ?');
});
