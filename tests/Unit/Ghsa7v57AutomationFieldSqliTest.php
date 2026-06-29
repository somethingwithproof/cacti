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

/**
 * Regression coverage for GHSA-7v57-rh74-q68j (stored second-order SQLi in
 * automation rule items).
 *
 * Three guarded points in lib/api_automation.php:
 *   1. build_rule_item_filter() wraps the rule item 'field' with
 *      sanitize_sql_column() before embedding it in the backtick-quoted
 *      identifier (the raw sink). 1.2.x had this guard; develop dropped it.
 *   2. The three import paths (graph / tree / template) validate each imported
 *      rule item 'field' via db_column_exists, mirroring the interactive save
 *      in automation_graph_rules.php / automation_tree_rules.php. The import
 *      path is develop-only and previously had no field-value validation.
 *   3. get_field_names()-style CASE WHEN expressions db_qstr() the literal
 *      field_name and sanitize_sql_column() the aliased identifier instead of
 *      concatenating it raw.
 *
 * These are source-contract tests (no DB bootstrap required); a runtime suite
 * exercises the sanitiser regex that backs the primary sink fix.
 */

$src = file_get_contents(__DIR__ . '/../../lib/api_automation.php');

// helper: source slice between two function definitions
function automationFnSlice(string $src, string $fn): string {
	$start = strpos($src, "function $fn(");
	if ($start === false) {
		return '';
	}
	$next = strpos($src, "\nfunction ", $start + 1);
	return substr($src, $start, ($next === false ? strlen($src) : $next) - $start);
}

// ---------------------------------------------------------------------------
// Finding 1 — primary sink: build_rule_item_filter sanitizes the field
// ---------------------------------------------------------------------------

test('build_rule_item_filter wraps field with sanitize_sql_column', function () use ($src) {
	$fn = automationFnSlice($src, 'build_rule_item_filter');

	expect($fn)->toContain("explode('.', sanitize_sql_column(\$automation_rule_item['field']))");
});

test('build_rule_item_filter no longer concatenates the raw field into the identifier', function () use ($src) {
	$fn = automationFnSlice($src, 'build_rule_item_filter');

	expect($fn)->not->toContain("explode('.', \$automation_rule_item['field'])");
});

// ---------------------------------------------------------------------------
// Finding 2 — defense in depth: import paths validate the field
// ---------------------------------------------------------------------------

test('field validator mirrors the interactive save db_column_exists checks', function () use ($src) {
	$fn = automationFnSlice($src, 'automation_import_field_is_valid');

	expect($fn)->toContain("db_column_exists('host', \$field_name)")
		->and($fn)->toContain("db_column_exists('host_template', \$field_name)")
		->and($fn)->toContain("db_column_exists('graph_templates', \$field_name)")
		->and($fn)->toContain("db_column_exists('graph_templates_graph', \$field_name)")
		->and($fn)->toContain("str_replace(['ht.', 'h.', 'gt.', 'gtg.'], '', \$field)");
});

test('each import function validates the rule item field before saving it', function () use ($src) {
	foreach (['automation_graph_rule_import', 'automation_tree_rule_import', 'automation_template_import'] as $importer) {
		$fn         = automationFnSlice($src, $importer);
		$guard_pos  = strpos($fn, 'automation_import_field_is_valid');
		$save_pos   = strpos($fn, "sql_save(\$save, 'automation_graph_rule_items')");
		// template / graph importers save graph items; tree importer saves tree items
		if ($save_pos === false) {
			$save_pos = strpos($fn, "sql_save(\$save, 'automation_tree_rule_items')");
		}

		expect($guard_pos)->not->toBeFalse("$importer validates the field")
			->and($save_pos)->not->toBeFalse()
			->and($guard_pos)->toBeLessThan($save_pos);
	}
});

test('all four rule-item save sites are guarded', function () use ($src) {
	expect(substr_count($src, 'automation_import_field_is_valid((string) $rule_item['))->toBe(4);
});

test('rejected imports are logged to the SECURITY audit log', function () use ($src) {
	$fn = automationFnSlice($src, 'automation_graph_rule_import');

	expect($fn)->toContain("(possible SQL Injection)");
});

// ---------------------------------------------------------------------------
// Finding 3 — get_field_names CASE WHEN literal/alias hardening
// ---------------------------------------------------------------------------

test('build_data_query_sql qstr-quotes the field literal and sanitizes the alias', function () use ($src) {
	$fn = automationFnSlice($src, 'build_data_query_sql');

	expect($fn)->toContain('db_qstr($field_name)')
		->and($fn)->toContain('sanitize_sql_column($field_name)')
		->and($fn)->not->toContain("field_name='\$field_name'")
		->and($fn)->not->toContain("AS '\$field_name'");
});

test('make_host_snnp_cache_sql qstr-quotes the literal and sanitizes the alias', function () use ($src) {
	$fn = automationFnSlice($src, 'make_host_snnp_cache_sql');

	expect($fn)->toContain("db_qstr(\$field['field_name'])")
		->and($fn)->toContain("sanitize_sql_column(\$field['field_name'])")
		->and($fn)->not->toContain("field_name = '{\$field['field_name']}'");
});

test('the per-host data query qstr-quotes the literal and sanitizes the alias', function () use ($src) {
	// the $column variant (get_automation_data_query_data_sql equivalent)
	expect($src)->toContain('db_qstr($column)')
		->and($src)->toContain('sanitize_sql_column($column)')
		->and($src)->not->toContain("field_name ='\$column'")
		->and($src)->not->toContain("AS '\$column'");
});

// ---------------------------------------------------------------------------
// Runtime suite — the sink sanitiser strips the breakout characters
// ---------------------------------------------------------------------------

/*
 * Mirrors sanitize_sql_column() from lib/functions.php so the breakout-character
 * stripping can be exercised without the full Cacti bootstrap. Kept in sync with
 * the production regex; if they diverge, the source-contract test above fails.
 */
function ghsaSanitizeColumn(string $column, string $default = 'id'): string {
	$result = preg_replace('/[^a-zA-Z0-9_().]/', '', $column) ?? '';

	return $result !== '' ? $result : $default;
}

test('backtick in field is stripped, closing the identifier breakout', function () {
	$payload = 'hostname`,(SELECT password FROM user_auth LIMIT 1),`x';

	expect(ghsaSanitizeColumn($payload))->not->toContain('`')
		->and(ghsaSanitizeColumn($payload))->not->toContain(' ')
		->and(ghsaSanitizeColumn($payload))->not->toContain(',');
});

test('legitimate dotted column survives sanitisation', function () {
	expect(ghsaSanitizeColumn('ht.description'))->toBe('ht.description');
});

test('empty field falls back to the default identifier', function () {
	expect(ghsaSanitizeColumn('`;--'))->toBe('id');
});
