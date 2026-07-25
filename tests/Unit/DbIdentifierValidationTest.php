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
 * Backport hardening: 1.2.x composes schema DDL by interpolating table, column
 * and index identifiers directly into backtick-quoted ALTER/CREATE statements.
 * db_is_safe_identifier() and db_is_safe_index_column() gate those identifiers.
 * 1.2.x has no sqlite-backed DB harness, so the two pure predicates are exercised
 * directly and the builder guards are checked as a source contract.
 */

$source = file_get_contents(__DIR__ . '/../../lib/database.php');

function _db_ident_fn_body(string $src, string $fn): string {
	$start = strpos($src, 'function ' . $fn . '(');
	if ($start === false) {
		return '';
	}

	$end = strpos($src, "\nfunction ", $start + 1);

	return substr($src, $start, $end !== false ? $end - $start : strlen($src) - $start);
}

// Load the two pure predicates without pulling in the rest of database.php.
if (!function_exists('db_is_safe_identifier')) {
	eval(_db_ident_fn_body($source, 'db_is_safe_identifier'));
}

if (!function_exists('db_is_safe_index_column')) {
	eval(_db_ident_fn_body($source, 'db_is_safe_index_column'));
}

test('db_is_safe_identifier accepts legitimate schema identifiers', function () {
	expect(db_is_safe_identifier('host'))->toBeTrue()
		->and(db_is_safe_identifier('graph_templates_graph'))->toBeTrue()
		->and(db_is_safe_identifier('t_slope_mode'))->toBeTrue()
		->and(db_is_safe_identifier('PRIMARY'))->toBeTrue()
		->and(db_is_safe_identifier('column1'))->toBeTrue();
});

test('db_is_safe_identifier rejects backtick breakout and separators', function () {
	expect(db_is_safe_identifier('id` , drop_me int; --'))->toBeFalse()
		->and(db_is_safe_identifier('name`) ENGINE=InnoDB, ADD `x'))->toBeFalse()
		->and(db_is_safe_identifier('col; DROP TABLE host'))->toBeFalse()
		->and(db_is_safe_identifier('has space'))->toBeFalse()
		->and(db_is_safe_identifier('quo"te'))->toBeFalse()
		->and(db_is_safe_identifier("new\nline"))->toBeFalse()
		->and(db_is_safe_identifier('db`.`table'))->toBeFalse();
});

test('db_is_safe_identifier rejects empty and non-string input', function () {
	expect(db_is_safe_identifier(''))->toBeFalse()
		->and(db_is_safe_identifier(null))->toBeFalse()
		->and(db_is_safe_identifier(array('host')))->toBeFalse()
		->and(db_is_safe_identifier(123))->toBeFalse();
});

test('db_is_safe_index_column accepts a bare column and a prefix length', function () {
	expect(db_is_safe_index_column('hostname'))->toBeTrue()
		->and(db_is_safe_index_column('`hostname`'))->toBeTrue()
		->and(db_is_safe_index_column('description(32)'))->toBeTrue();
});

test('db_is_safe_index_column rejects breakout attempts', function () {
	expect(db_is_safe_index_column('id`), ADD INDEX evil (`id'))->toBeFalse()
		->and(db_is_safe_index_column('id); DROP TABLE host; --'))->toBeFalse()
		->and(db_is_safe_index_column('col(10) , other'))->toBeFalse()
		->and(db_is_safe_index_column(''))->toBeFalse();
});

/*
 * Source contract: every DDL builder that interpolates an identifier must reject
 * an unsafe one before composing the statement.
 */
test('db_add_column guards table and column identifiers', function () use ($source) {
	$body = _db_ident_fn_body($source, 'db_add_column');
	expect($body)->toContain('if (!db_is_safe_identifier($table)) {')
		->and($body)->toContain("!db_is_safe_identifier(\$column['name'])")
		->and($body)->toContain("!db_is_safe_identifier(\$column['after'])");
	// the guard precedes the ALTER TABLE ... ADD builder
	expect(strpos($body, 'db_is_safe_identifier($table)'))
		->toBeLessThan(strpos($body, 'ALTER TABLE `'));
});

test('db_remove_column guards table and column identifiers', function () use ($source) {
	$body = _db_ident_fn_body($source, 'db_remove_column');
	expect($body)->toContain('if (!db_is_safe_identifier($table) || !db_is_safe_identifier($column)) {');
	expect(strpos($body, 'db_is_safe_identifier($table)'))
		->toBeLessThan(strpos($body, 'ALTER TABLE `'));
});

test('db_add_index guards table, key, type and columns', function () use ($source) {
	$body = _db_ident_fn_body($source, 'db_add_index');
	expect($body)->toContain('!db_is_safe_identifier($table) || !db_is_safe_identifier($key)')
		->and($body)->toContain('!db_is_safe_identifier($column)');
	expect(strpos($body, 'db_is_safe_identifier($key)'))
		->toBeLessThan(strpos($body, 'ALTER TABLE `'));
});

test('db_update_table guards table, column, index and primary identifiers', function () use ($source) {
	$body = _db_ident_fn_body($source, 'db_update_table');
	expect($body)->toContain('if (!db_is_safe_identifier($table)) {')
		->and($body)->toContain("!db_is_safe_identifier(\$k['name'])")
		->and($body)->toContain('!db_is_safe_index_column($key_column)')
		->and($body)->toContain('!db_is_safe_index_column($primary_column)')
		->and($body)->toContain("!db_is_safe_identifier(\$column['name'])")
		->and($body)->toContain('if (!db_is_safe_identifier($n)) {');
	expect(strpos($body, 'db_is_safe_identifier($table)'))
		->toBeLessThan(strpos($body, 'ALTER TABLE `'));
});

test('db_table_create guards table, column, index and primary identifiers', function () use ($source) {
	$body = _db_ident_fn_body($source, 'db_table_create');
	expect($body)->toContain('if (!db_is_safe_identifier($table)) {')
		->and($body)->toContain("!db_is_safe_identifier(\$column['name'])")
		->and($body)->toContain("!db_is_safe_identifier(\$key['name'])")
		->and($body)->toContain('!db_is_safe_index_column($key_column)')
		->and($body)->toContain('!db_is_safe_index_column($primary_column)');
	expect(strpos($body, 'db_is_safe_identifier($table)'))
		->toBeLessThan(strpos($body, 'CREATE TABLE `'));
});
