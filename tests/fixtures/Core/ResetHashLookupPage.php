<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

// Runs the checked-in auth_resetpassword.php page, unmodified, in a child
// process against a minimal bootstrap stub. The stub answers the reset-hash
// lookup like the database does: the stored row is returned only when it
// matches the submitted hash, otherwise an empty array. Child warnings are
// captured and any output on stderr fails the run.

const RESET_PAGE_CHILD = 'child';

if (($argv[1] ?? '') === RESET_PAGE_CHILD) {
	define('CACTI_PATH_URL', '/');
	define('OPER_MODE_NATIVE', 1);
	define('OPER_MODE_RESKIN', 2);

	$_REQUEST['action'] = getenv('FIXTURE_ACTION');

	/** Stub: record the default action. */
	function set_default_action(string $action): void {
	}

	/** Stub: read the action request value. */
	function grv(string $name): string {
		return (string) ($_REQUEST[$name] ?? '');
	}

	/** Stub: mirror gfrv() regexp filtering, which yields false on mismatch. */
	function gfrv(string $name, int $filter, array $options): string|false {
		return filter_var(getenv('FIXTURE_SUBMITTED_HASH'), $filter, $options);
	}

	/** Stub: no extra request values. */
	function gnrv(string $name): string {
		return '';
	}

	/** Stub: reset-hash rows match on equality like the WHERE clause; other tables are empty. */
	function db_fetch_row_prepared(string $sql, array $params = []): array {
		$stored = (string) getenv('FIXTURE_STORED_HASH');

		if (str_contains($sql, 'user_auth_reset_hashes') && $stored !== '' && ($params[0] ?? null) === $stored) {
			return ['hash' => $stored, 'user_id' => 7];
		}

		return [];
	}

	/** Stub: element count that tolerates non-arrays like the real helper. */
	function cacti_sizeof(mixed $array): int {
		return is_array($array) ? count($array) : 0;
	}

	/** Stub: translation passthrough. */
	function __(string $text): string {
		return $text;
	}

	/** Stub: translation passthrough. */
	function __esc(string $text): string {
		return $text;
	}

	/** Stub: no plugin hooks. */
	function api_plugin_hook_function(string $hook, mixed $data): mixed {
		return $data;
	}

	/** Stub: all options unset. */
	function read_config_option(string $name): string {
		return '0';
	}

	/** Stub: theme name. */
	function get_selected_theme(): string {
		return 'classic';
	}

	/** Stub: tooltip passthrough. */
	function display_tooltip(string $text): string {
		return '';
	}

	/** Stub: no escaped request value. */
	function htmlerv(string $name): string {
		return '';
	}

	/** Stub: header renders nothing. */
	function html_auth_header(string $a, string $b, string $c, string $d): void {
	}

	/** Stub: report the page state the real footer would render. */
	function html_auth_footer(string $page, string $message, string $script): void {
		global $action;
		print "\n@@" . json_encode(['action' => $action, 'message' => strip_tags($message)]);
	}

	require getcwd() . '/auth_resetpassword.php';
	exit;
}

$root = dirname(__DIR__, 3);
$work = sys_get_temp_dir() . '/cacti-reset-page-' . bin2hex(random_bytes(6));
mkdir($work . '/include', 0700, true);
copy($root . '/auth_resetpassword.php', $work . '/auth_resetpassword.php');
file_put_contents($work . '/include/global.php', '<?php');

$wrong = 'Incorrect resetlink hash';
$cases = [
	// action, stored hash, submitted hash, expected action, expected message
	['formreset', '', '', 'formidentity', $wrong],
	['formreset', '', 'abc123', 'formidentity', $wrong],
	['formreset', 'valid1', '', 'formidentity', $wrong],
	['formreset', 'valid1', 'valid2', 'formidentity', $wrong],
	['formreset', 'valid1', 'bad hash!', 'formidentity', $wrong],
	['formreset', 'valid1', 'valid1', 'formreset', ''],
	['resetpassword', '', '', 'formidentity', $wrong],
	['resetpassword', '', 'abc123', 'formidentity', $wrong],
	['resetpassword', 'valid1', '', 'formidentity', $wrong],
	['resetpassword', 'valid1', 'valid2', 'formidentity', $wrong],
	// A correct hash passes the lookup; the user query is empty in the stub.
	['resetpassword', 'valid1', 'valid1', 'formidentity', 'Password reset is not available for this account.'],
];
$count = 0;

try {
	foreach ($cases as [$action, $stored, $sent, $expectedAction, $expectedMessage]) {
		$pipes   = [];
		$process = proc_open(
			[PHP_BINARY, '-d', 'error_reporting=-1', '-d', 'display_errors=stderr', '-d', 'html_errors=0', __DIR__ . '/ResetHashLookupPage.php', RESET_PAGE_CHILD],
			[1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
			$pipes,
			$work,
			['FIXTURE_ACTION' => $action, 'FIXTURE_STORED_HASH' => $stored, 'FIXTURE_SUBMITTED_HASH' => $sent, 'PATH' => getenv('PATH') ?: '']
		);
		$out    = stream_get_contents($pipes[1]);
		$err    = stream_get_contents($pipes[2]);
		fclose($pipes[1]);
		fclose($pipes[2]);
		proc_close($process);

		$state = str_contains($out, "\n@@") ? json_decode(substr($out, strrpos($out, "\n@@") + 3), true) : null;
		$label = sprintf('%s stored=%s sent=%s', $action, var_export($stored, true), var_export($sent, true));

		if ($err !== '') {
			throw new RuntimeException("Unexpected child output for $label: $err");
		}

		if (!is_array($state) || $state['action'] !== $expectedAction || $state['message'] !== $expectedMessage) {
			throw new RuntimeException("Reset hash decision mismatch for $label: $out");
		}
		$count++;
	}
} finally {
	foreach ([$work . '/include/global.php', $work . '/auth_resetpassword.php'] as $file) {
		@unlink($file);
	}
	@rmdir($work . '/include');
	@rmdir($work);
}

print json_encode(['passed' => $count], JSON_THROW_ON_ERROR) . "\n";
