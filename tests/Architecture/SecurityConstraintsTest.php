<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Architecture constraint tests for security policy enforcement.
 *
 * These tests scan the codebase for dangerous function usage and enforce
 * that shell execution, deserialization, and eval are confined to known,
 * audited locations. New usages must be added to the whitelist with
 * justification and a GHSA reference if applicable.
 *
 * Uses source-scanning because Cacti is procedural (no namespaces for
 * Pest's native arch() to match against).
 */

$cactiBase = dirname(__DIR__, 2);

function scan_codebase(string $base, string $pattern, array $whitelist): array {
	$violations = [];
	$iterator   = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($base, RecursiveDirectoryIterator::SKIP_DOTS)
	);

	foreach ($iterator as $file) {
		if ($file->getExtension() !== 'php') {
			continue;
		}

		$path = $file->getRealPath();

		if (str_contains($path, '/vendor/') ||
			str_contains($path, '/tests/') ||
			str_contains($path, '/.git/') ||
			str_contains($path, '/.claude/') ||
			str_contains($path, '/.worktrees/') ||
			str_contains($path, '/node_modules/')) {
			continue;
		}

		$relative = str_replace($base . '/', '', $path);

		$skip = false;
		foreach ($whitelist as $allowed) {
			if (str_contains($relative, $allowed)) {
				$skip = true;
				break;
			}
		}
		if ($skip) {
			continue;
		}

		$lines = file($path);
		foreach ($lines as $num => $line) {
			$trimmed = ltrim($line);
			if (str_starts_with($trimmed, '//') || str_starts_with($trimmed, '*') || str_starts_with($trimmed, '/*')) {
				continue;
			}

			if (preg_match($pattern, $line)) {
				$violations[] = $relative . ':' . ($num + 1);
			}
		}
	}

	return $violations;
}

describe('dangerous function containment', function () use ($cactiBase) {
	it('has no eval() calls in the codebase', function () use ($cactiBase) {
		$violations = scan_codebase($cactiBase, '/\beval\s*\(/', []);

		/* Filter out string-context false positives (settings descriptions) */
		$real = array_filter($violations, function ($v) use ($cactiBase) {
			$parts = explode(':', $v, 2);
			$lines = file($cactiBase . '/' . $parts[0]);
			$code  = $lines[(int) $parts[1] - 1] ?? '';
			$stripped = preg_replace('/([\'"]).*?\1/', '', $code);

			return str_contains($stripped, 'eval(') || str_contains($stripped, 'eval (');
		});

		expect($real)->toBeEmpty();
	});

	it('confines shell execution to whitelisted files', function () use ($cactiBase) {
		$whitelist = [
			'lib/poller.php',       /* exec_background, exec_with_timeout */
			'lib/ping.php',         /* ICMP/fping shell_exec */
			'lib/snmp.php',         /* binary SNMP fallback */
			'lib/rrd.php',          /* rrdtool pipe management */
			'lib/utility.php',      /* PHP extension detection */
			'lib/import.php',       /* package extraction */
			'lib/boost.php',        /* boost poller launch */
			'lib/database.php',     /* db_dump_data mysqldump */
			'lib/installer.php',    /* install-time checks */
			'lib/api_automation.php',
			'lib/dsstats.php',
			'lib/rrdcheck.php',
			'lib/snmpagent.php',
			'lib/functions.php',    /* substitute_script_query_path */
			'poller.php',           /* main poller exec_background calls */
			'cactid.php',           /* daemon launcher */
			'poller_automation.php',
			'poller_spikekill.php', /* spikekill exec */
			'poller_realtime.php',  /* realtime graph polling */
			'automation_networks.php',
			'cmd.php',              /* script server proc_open */
			'cmd_realtime.php',     /* realtime script server */
			'host.php',             /* reindex trigger */
			'support.php',          /* version detection */
			'graph_realtime.php',   /* realtime graph shell_exec */
			'snmpagent_persist.php',/* SNMP agent cache */
			'script_server.php',    /* persistent script server */
			'install/',             /* installer */
			'scripts/',             /* data collection scripts */
			'cli/',                 /* CLI tools */
		];

		$violations = scan_codebase(
			$cactiBase,
			'/\b(shell_exec|(?<!\w)exec|passthru|system|proc_open|popen)\s*\(/',
			$whitelist
		);

		expect($violations)->toBeEmpty();
	});

	it('has no raw unserialize() without allowed_classes guard', function () use ($cactiBase) {
		$violations = scan_codebase(
			$cactiBase,
			'/\bunserialize\s*\((?!.*allowed_classes)/',
			['lib/functions.php']
		);

		$real = array_filter($violations, fn($v) => !str_contains($v, 'cacti_unserialize'));

		expect($real)->toBeEmpty();
	});
});

describe('input handling hygiene', function () use ($cactiBase) {
	it('keeps raw superglobal usage below the regression ceiling', function () use ($cactiBase) {
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

		/*
		 * Ceiling: current count + buffer. Decrease as callers migrate to
		 * get_request_var(). If this test fails, a new raw superglobal was
		 * added; use get_filter_request_var() or get_request_var() instead.
		 */
		expect(count($violations))->toBeLessThan(200);
	});

	it('confines $database_default access to the database layer', function () use ($cactiBase) {
		$whitelist = [
			'lib/database.php',
			'include/global.php',
			'include/config.php',
			'install/',
			'cli/',
		];

		$violations = scan_codebase($cactiBase, '/\$database_default\b/', $whitelist);

		/* Ceiling set to current count + buffer. Decrease as callers are migrated. */
		expect(count($violations))->toBeLessThan(30);
	});
});
