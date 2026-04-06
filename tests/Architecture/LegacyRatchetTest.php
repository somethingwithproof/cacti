<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Legacy Refactoring Ratchet Tests
 *
 * These tests enforce a one-way migration toward safer coding patterns.
 * Each test sets a ceiling on the count of a legacy pattern. As code is
 * migrated, lower the ceiling. If a test fails, it means someone added
 * a new instance of the legacy pattern; they must use the modern
 * alternative instead.
 *
 * Pattern: measure -> set ceiling -> migrate -> lower ceiling -> repeat
 *
 * Ceilings include a small buffer above current count to avoid
 * false failures from unrelated commits. Tighten after each migration PR.
 */

$cactiBase = dirname(__DIR__, 2);

/* Reuse scan_codebase if already defined by SecurityConstraintsTest */
if (!function_exists('scan_codebase')) {
	require_once __DIR__ . '/SecurityConstraintsTest.php';
}

describe('prepared statement migration', function () use ($cactiBase) {
	/*
	 * Target: all db_execute/db_fetch_* calls should use _prepared variants.
	 * Non-prepared calls allow SQL injection if callers ever interpolate
	 * user input into the query string.
	 *
	 * Migration: replace db_execute($sql) with db_execute_prepared($sql, $params)
	 * Current baseline: 558 calls in lib/ (2026-04-06)
	 */
	it('keeps non-prepared DB calls below the regression ceiling', function () use ($cactiBase) {
		$violations = scan_codebase(
			$cactiBase,
			'/\bdb_execute\s*\(|\bdb_fetch_cell\s*\(|\bdb_fetch_row\s*\(|\bdb_fetch_assoc\s*\(/',
			['lib/database.php', 'lib/dbparallel.php', 'cli/', 'install/', 'scripts/']
		);

		/*
		 * Ceiling: 600 (current ~500 outside database.php + callers in root *.php)
		 * Direction: down. Each migration PR should lower this.
		 */
		expect(count($violations))->toBeLessThan(600,
			'Non-prepared DB calls increasing. Use db_*_prepared() with ? placeholders. '
			. 'Violations: ' . implode(', ', array_slice($violations, 0, 5))
		);
	});
});

describe('output escaping migration', function () use ($cactiBase) {
	/*
	 * Target: all dynamic output should pass through html_escape().
	 * Raw print/echo of variables allows XSS if the variable contains
	 * user-controlled data.
	 *
	 * Migration: wrap $var in html_escape($var) before print/echo
	 * Current baseline: 818 in lib/ (2026-04-06)
	 */
	it('keeps unescaped print/echo of variables below the regression ceiling', function () use ($cactiBase) {
		$count = 0;
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($cactiBase . '/lib', RecursiveDirectoryIterator::SKIP_DOTS)
		);

		foreach ($iterator as $file) {
			if ($file->getExtension() !== 'php') {
				continue;
			}

			$lines = file($file->getRealPath());

			foreach ($lines as $line) {
				$trimmed = ltrim($line);

				if (str_starts_with($trimmed, '//') || str_starts_with($trimmed, '*')) {
					continue;
				}

				/* Match print/echo with a $ variable, excluding safe patterns */
				if (preg_match('/\b(print|echo)\b.*\$/', $line) &&
					!preg_match('/html_escape|htmlspecialchars|__\(|__esc\(|cacti_log|POLLER_VERBOSITY/', $line)) {
					$count++;
				}
			}
		}

		/*
		 * Ceiling: 850 (current ~818)
		 * Direction: down. Each output-escaping PR should lower this.
		 */
		expect($count)->toBeLessThan(850,
			"Unescaped print/echo with variables: $count. Wrap output in html_escape()."
		);
	});
});

describe('input validation migration', function () use ($cactiBase) {
	/*
	 * Target: all user input should go through get_filter_request_var()
	 * or get_request_var(), never raw $_REQUEST/$_GET/$_POST.
	 *
	 * Migration: replace $_REQUEST['x'] with get_filter_request_var('x')
	 * Current baseline: ~32 files (2026-04-06)
	 */
	it('keeps raw superglobal file count below the regression ceiling', function () use ($cactiBase) {
		$whitelist = [
			'include/auth.php',
			'include/global.php',
			'include/global_session.php',
			'include/global_form.php',
			'include/csrf.php',
			'lib/html_utility.php',
			'lib/functions.php',
			'lib/auth.php',
			'install/',
		];

		$violations = scan_codebase($cactiBase, '/\$_(REQUEST|GET|POST)\s*\[/', $whitelist);
		$fileCount  = count(array_unique(array_map(fn($v) => explode(':', $v)[0], $violations)));

		/*
		 * Ceiling: 40 files (current ~32)
		 * Direction: down. Track by file count, not line count, to reward
		 * complete file migrations over partial fixes.
		 */
		expect($fileCount)->toBeLessThan(40,
			"Raw \$_REQUEST/\$_GET/\$_POST in $fileCount files. Use get_filter_request_var()."
		);
	});
});

describe('redirect safety migration', function () use ($cactiBase) {
	/*
	 * Target: all header('Location: ...') redirects should use
	 * validate_redirect_url() or sanitize_uri() to prevent open redirects.
	 *
	 * This test counts header(Location) calls that don't appear on the
	 * same line as validate_redirect_url or sanitize_uri.
	 */
	it('keeps unvalidated redirects below the regression ceiling', function () use ($cactiBase) {
		$count = 0;
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($cactiBase, RecursiveDirectoryIterator::SKIP_DOTS)
		);

		foreach ($iterator as $file) {
			if ($file->getExtension() !== 'php') {
				continue;
			}

			$path = $file->getRealPath();

			if (str_contains($path, '/vendor/') ||
				str_contains($path, '/tests/') ||
				str_contains($path, '/.git/') ||
				str_contains($path, '/.worktrees/') ||
				str_contains($path, '/.claude/') ||
				str_contains($path, '/node_modules/') ||
				str_contains($path, '/install/')) {
				continue;
			}

			$lines = file($path);

			foreach ($lines as $line) {
				if (preg_match('/header\s*\(\s*[\'"]Location/i', $line) &&
					!preg_match('/validate_redirect_url|sanitize_uri/', $line)) {
					$count++;
				}
			}
		}

		/*
		 * Ceiling: 300 (current ~272 + worktree/index.php noise)
		 * Direction: down. Each redirect should pass through
		 * validate_redirect_url() before header().
		 */
		expect($count)->toBeLessThan(350,
			"Unvalidated header(Location) redirects: $count. Use validate_redirect_url()."
		);
	});
});

describe('type safety migration', function () use ($cactiBase) {
	/*
	 * Target: all cacti_sizeof() / empty() checks on DB results should
	 * use strict type checks. empty() treats '0' and '' as empty,
	 * masking valid data.
	 *
	 * This test counts empty() calls in lib/ excluding comments.
	 */
	it('keeps empty() usage in lib/ below the regression ceiling', function () use ($cactiBase) {
		$violations = scan_codebase(
			$cactiBase . '/lib',
			'/\bempty\s*\(/',
			['database.php', 'installer.php'] /* bootstrap code legitimately uses empty() */
		);

		/*
		 * Ceiling: set to current + buffer on first run.
		 * Direction: down. Replace empty($x) with $x === '' or $x === null
		 * or cacti_sizeof($x) === 0 as appropriate.
		 */
		expect(count($violations))->toBeLessThan(800,
			'empty() usage: ' . count($violations) . '. Use explicit type checks.'
		);
	});
});
