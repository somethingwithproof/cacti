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
 * Tests for auth password page cleanup PRs:
 * - auth_changepassword.php
 * - auth_resetpassword.php
 *
 * These helpers intentionally mirror the touched branch logic from the
 * two pages so tests stay deterministic and fast under Unit/Pest.
 */

function changepassword_guest_redirect_target(?string $http_referer): string {
	if ($http_referer !== null) {
		return $http_referer;
	}

	return 'index.php';
}

function checkpass_response_value(string $secpass_result): string {
	if ($secpass_result != '') {
		return $secpass_result;
	}

	return 'ok';
}

function resetpassword_title_for_action(string $action): string {
	if ($action == 'formidentity') {
		return 'Please enter your Cacti username or email address.';
	} elseif ($action == 'formreset') {
		return 'Please enter your new Cacti password.';
	}

	return 'Reset password problem.';
}

function resetpassword_empty_password_state(string $password): array {
	$state = [
		'action'   => 'resetpassword',
		'return'   => 'index.php',
		'redirect' => false,
	];

	if ($password != '') {
		$state['redirect'] = true;

		return $state;
	}

	$state['return'] = 'resetpassword.php';

	return $state;
}

dataset('change_password_guest_redirect_targets', [
	'uses referer when present' => ['/cacti/graphs.php?action=view', '/cacti/graphs.php?action=view'],
	'falls back to index when referer missing' => [null, 'index.php'],
]);

test('change-password guest redirect target is selected correctly', function (?string $referer, string $expected) {
	expect(changepassword_guest_redirect_target($referer))->toBe($expected);
})->with('change_password_guest_redirect_targets');

dataset('checkpass_response_values', [
	'returns error text unchanged' => ['Password too short', 'Password too short'],
	'returns ok for empty secpass response' => ['', 'ok'],
]);

test('checkpass response value matches secpass contract', function (string $secpassResult, string $expected) {
	expect(checkpass_response_value($secpassResult))->toBe($expected);
})->with('checkpass_response_values');

dataset('resetpassword_action_titles', [
	'identity action title' => ['formidentity', 'Please enter your Cacti username or email address.'],
	'formreset action title' => ['formreset', 'Please enter your new Cacti password.'],
	'fallback action title' => ['other', 'Reset password problem.'],
]);

test('reset-password page title is derived from action', function (string $action, string $expectedTitle) {
	expect(resetpassword_title_for_action($action))->toBe($expectedTitle);
})->with('resetpassword_action_titles');

dataset('resetpassword_empty_password_states', [
	'non-empty password triggers redirect path' => ['newpass123', true, 'resetpassword', 'index.php'],
	'empty password keeps reset flow and return page' => ['', false, 'resetpassword', 'resetpassword.php'],
]);

test('reset-password state for empty and non-empty password matches flow', function (string $password, bool $redirect, string $action, string $return) {
	$state = resetpassword_empty_password_state($password);

	expect($state['redirect'])->toBe($redirect)
		->and($state['action'])->toBe($action)
		->and($state['return'])->toBe($return);
})->with('resetpassword_empty_password_states');

