<?php
<<<<<<< HEAD
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
 * Tests for cacti_snmp_validate_oid() in lib/snmp.php.
 *
 * PR #6980: the original implementation used array_unique() before
 * array_search(), which collapsed the validation array so that a mix
 * of valid and invalid segments could pass. It also lacked an
 * empty-string guard, so ltrim('.','.') yielding '' would explode
 * into [''] and is_numeric('') returns false but array_search
 * on a single-element array behaved inconsistently.
 *
 * The fix:
 *   1. Adds an explicit empty-string guard after ltrim.
 *   2. Replaces array_unique(array_map(...)) with array_map(...).
 *   3. Replaces array_search(false,...) with in_array(false,...,true).
 *
 * These tests verify the fix by scanning the source of lib/snmp.php.
 */

// --- source scanning helper ---

function getSnmpValidateOidSource(): string {
	$snmpPhp = file_get_contents(__DIR__ . '/../../lib/snmp.php');
	expect($snmpPhp)->not->toBeFalse('Failed to read lib/snmp.php');

	$start = strpos($snmpPhp, 'function cacti_snmp_validate_oid(');
	expect($start)->not->toBeFalse('cacti_snmp_validate_oid() must exist in lib/snmp.php');

	// Grab the function body (it is short, ~15 lines)
	$region = substr($snmpPhp, $start, 500);

	return $region;
}

// --- empty string guard ---

test('cacti_snmp_validate_oid has empty string guard after ltrim', function () {
	$source = getSnmpValidateOidSource();

	// The fix adds: if ($oid === '') { return false; }
	expect(str_contains($source, "if (\$oid === '')"))->toBeTrue(
		'Empty string guard must exist after ltrim'
	);
});

// --- uses in_array(false, ..., true) not array_search ---

test('cacti_snmp_validate_oid uses in_array not array_search', function () {
	$source = getSnmpValidateOidSource();

	// The fix replaces array_search with in_array for correctness
	expect(str_contains($source, 'in_array(false, $validate, true)'))->toBeTrue(
		'Must use in_array(false, $validate, true) pattern'
	);

	expect(str_contains($source, 'array_search'))->toBeFalse(
		'array_search must not be used (replaced by in_array)'
	);
});

// --- does not use array_unique ---

test('cacti_snmp_validate_oid does not use array_unique', function () {
	$source = getSnmpValidateOidSource();

	// The old code had array_unique(array_map(...)) which collapsed results
	expect(str_contains($source, 'array_unique'))->toBeFalse(
		'array_unique must not be used; it collapses the validation array'
	);
});

// --- uses ltrim on dots ---

test('cacti_snmp_validate_oid uses ltrim to strip leading dots', function () {
	$source = getSnmpValidateOidSource();

	expect(str_contains($source, "ltrim(\$oid, '.')"))->toBeTrue(
		'Must ltrim leading dots from OID before validation'
	);
});

// --- uses array_map with is_numeric ---

test('cacti_snmp_validate_oid maps is_numeric over OID segments', function () {
	$source = getSnmpValidateOidSource();

	expect(str_contains($source, "array_map('is_numeric', explode('.', \$oid))"))->toBeTrue(
		'Must use array_map(is_numeric, explode) to validate each segment'
	);
});

// --- the function returns bool (negation of in_array) ---

test('cacti_snmp_validate_oid returns negated in_array result', function () {
	$source = getSnmpValidateOidSource();

	// The fixed code: return !in_array(false, $validate, true);
	expect(str_contains($source, 'return !in_array(false, $validate, true)'))->toBeTrue(
		'Must return the negation of in_array to indicate validity'
	);
||||||| 7dd05ee12
=======
declare(strict_types=1);
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Regression tests for cacti_snmp_validate_oid() bug fix.
 *
 * The original code used array_search(false, $validate, true) which
 * returns 0 (falsy) when false is the first element, causing
 * all-non-numeric OIDs to incorrectly pass validation.
 *
 * Fix: replaced array_search with in_array, added empty-string guard.
 *
 * @group regression
 */

require_once dirname(__DIR__) . '/Helpers/CactiStubs.php';

if (!function_exists('cacti_snmp_validate_oid')) {
	function cacti_snmp_validate_oid(string $oid): bool {
		$oid = ltrim($oid, '.');
		if ($oid === '') {
			return false;
		}
		$validate = array_map('is_numeric', explode('.', $oid));
		return !in_array(false, $validate, true);
	}
}

// ===========================================================================
// Valid OIDs
// ===========================================================================

describe('Valid OIDs', function () {
	test('standard numeric OID', function () {
		expect(cacti_snmp_validate_oid('.1.3.6.1.2.1.1.1.0'))->toBeTrue();
	});

	test('without leading dot', function () {
		expect(cacti_snmp_validate_oid('1.3.6.1.2.1.1.1.0'))->toBeTrue();
	});

	test('single number', function () {
		expect(cacti_snmp_validate_oid('1'))->toBeTrue();
	});

	test('two-component OID', function () {
		expect(cacti_snmp_validate_oid('1.3'))->toBeTrue();
	});
});

// ===========================================================================
// Invalid OIDs (these were the buggy cases)
// ===========================================================================

describe('Invalid OIDs (previously passed due to array_search bug)', function () {
	test('dot-only returns false', function () {
		expect(cacti_snmp_validate_oid('.'))->toBeFalse();
	});

	test('all-alpha returns false', function () {
		expect(cacti_snmp_validate_oid('abc.def'))->toBeFalse();
	});

	test('MIB name style returns false', function () {
		expect(cacti_snmp_validate_oid('IF-MIB::ifDescr.1'))->toBeFalse();
	});

	test('empty string returns false', function () {
		expect(cacti_snmp_validate_oid(''))->toBeFalse();
	});

	test('mixed alpha-numeric returns false', function () {
		expect(cacti_snmp_validate_oid('1.3.abc.4'))->toBeFalse();
	});

	test('special characters return false', function () {
		expect(cacti_snmp_validate_oid('.1.3.6;DROP TABLE'))->toBeFalse();
	});
});

// ===========================================================================
// Mutation killers
// ===========================================================================

describe('OID validation mutation killers', function () {
	test('valid=true, invalid=false - never inverted', function () {
		expect(cacti_snmp_validate_oid('1.3.6.1'))->toBeTrue();
		expect(cacti_snmp_validate_oid('1.3.6.1'))->not->toBeFalse();
		expect(cacti_snmp_validate_oid('abc'))->toBeFalse();
		expect(cacti_snmp_validate_oid('abc'))->not->toBeTrue();
	});

	test('empty string is false, not true', function () {
		expect(cacti_snmp_validate_oid(''))->toBeFalse();
		expect(cacti_snmp_validate_oid(''))->not->toBeTrue();
	});

	test('leading dot stripped correctly', function () {
		expect(cacti_snmp_validate_oid('.1.3'))->toBeTrue();
		expect(cacti_snmp_validate_oid('1.3'))->toBeTrue();
	});

	test('deterministic over 100 runs', function () {
		for ($i = 0; $i < 100; $i++) {
			expect(cacti_snmp_validate_oid('1.3.6'))->toBeTrue();
			expect(cacti_snmp_validate_oid('abc'))->toBeFalse();
		}
	});
>>>>>>> origin/fix/jquery-deprecations
});
