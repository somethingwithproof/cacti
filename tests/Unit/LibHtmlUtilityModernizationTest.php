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
 * Covers the PHP 8.4 idiom migration in lib/html_utility.php, lib/html_filter.php
 * and lib/html_form.php: (string) casts ahead of string builtins, strpos()===0 to
 * str_starts_with(), and the 'CactiErrorHandler' string to first-class callable
 * form. The rewrites are behaviour preserving, so these cases pin the migrated
 * lines' runtime behaviour rather than re-testing the surrounding logic.
 */

require_once dirname(__DIR__) . '/Helpers/LibHtmlHarness.php';

final class LibHtmlUtilityModernizationTest extends \PHPUnit\Framework\TestCase {
	// update_order_string() consults get_mysql_info(); a scoped default
	// connection keeps that off the deprecation path and is torn down so it
	// never leaks into other suites.
	public static function setUpBeforeClass() : void {
		libhtml_connect_default();
	}

	public static function tearDownAfterClass() : void {
		libhtml_disconnect_default();
	}

	protected function setUp() : void {
		libhtml_reset();
	}

	// --- validate_redirect_url: strpos($url, '//') === 0 -> str_starts_with ---

	public function test_protocol_relative_url_is_rejected() : void {
		$this->assertSame('index.php', validate_redirect_url('//evil.example.com/x'));
	}

	public function test_same_site_relative_url_passes() : void {
		$result = validate_redirect_url('graph_view.php?action=tree');
		$this->assertNotSame('index.php', $result);
		$this->assertStringContainsString('graph_view.php', $result);
	}

	public function test_single_leading_slash_is_not_treated_as_protocol_relative() : void {
		$result = validate_redirect_url('/cacti/host.php');
		$this->assertNotSame('index.php', $result);
		$this->assertStringContainsString('host.php', $result);
	}

	// --- validate_is_regex: set_error_handler(CactiErrorHandler(...)) callable ---

	public function test_valid_regex_returns_true() : void {
		$this->assertTrue(validate_is_regex('^abc$'));
	}

	public function test_invalid_regex_reaches_error_handler_restore_and_returns_message() : void {
		// An unterminated character class fails preg_match, driving execution
		// through the first-class-callable set_error_handler() branch.
		$result = validate_is_regex('([');
		$this->assertIsString($result);
		$this->assertNotSame('', $result);
	}

	public function test_regex_with_semicolon_is_rejected() : void {
		$this->assertIsString(validate_is_regex('abc;def'));
	}

	// --- form_get_table_id: basename((string) get_current_page(), '.php') ---

	// The numeric segment is a process-static counter shared across the suite,
	// so match the migrated basename/action/tab shape rather than the count.

	public function test_table_id_action_and_tab() : void {
		libhtml_reset('graphs.php', ['action' => 'edit', 'tab' => 'general']);
		$this->assertMatchesRegularExpression('/^graphs:\d+:action-tab-edit-general:$/', form_get_table_id());
	}

	public function test_table_id_action_only() : void {
		libhtml_reset('graphs.php', ['action' => 'edit']);
		$this->assertMatchesRegularExpression('/^graphs:\d+:action-edit:$/', form_get_table_id());
	}

	public function test_table_id_tab_only() : void {
		libhtml_reset('graphs.php', ['tab' => 'general']);
		$this->assertSame('graphs:tab-general:', form_get_table_id());
	}

	public function test_table_id_neither() : void {
		libhtml_reset('graphs.php', []);
		$this->assertMatchesRegularExpression('/^graphs:\d+$/', form_get_table_id());
	}

	// --- get_order_string: strtoupper((string) get_request_var('sort_direction')) ---

	public function test_order_string_desc() : void {
		libhtml_reset('graphs.php', ['sort_column' => 'name', 'sort_direction' => 'DESC']);
		$this->assertSame('ORDER BY `name` DESC', get_order_string());
	}

	public function test_order_string_defaults_to_asc_for_unknown_direction() : void {
		libhtml_reset('graphs.php', ['sort_column' => 'name', 'sort_direction' => 'sideways']);
		$this->assertSame('ORDER BY `name` ASC', get_order_string());
	}

	public function test_order_string_empty_when_no_sort_column() : void {
		libhtml_reset('graphs.php', ['sort_column' => '', 'sort_direction' => 'ASC']);
		$this->assertSame('', get_order_string());
	}

	// --- update_order_string: (string) casts on column/direction ---

	public function test_update_order_string_add_reset_builds_session_clause() : void {
		libhtml_reset('graphs.php', ['sort_column' => 'hostname', 'sort_direction' => 'ASC', 'add' => 'reset']);
		$page = get_order_string_page(false);
		update_order_string(false);
		$this->assertArrayHasKey('sort_string', $_SESSION);
		$this->assertStringContainsString('ORDER BY', $_SESSION['sort_string'][$page]);
		$this->assertStringContainsString('hostname', $_SESSION['sort_string'][$page]);
	}

	public function test_update_order_string_sort_column_branch() : void {
		libhtml_reset('graphs.php', ['sort_column' => 'name', 'sort_direction' => 'DESC']);
		$page = get_order_string_page(false);
		update_order_string(false);
		$this->assertStringContainsString('name', $_SESSION['sort_string'][$page]);
		$this->assertStringContainsString('DESC', $_SESSION['sort_string'][$page]);
	}

	public function test_update_order_string_inplace_revalidates_session_columns() : void {
		libhtml_reset('graphs.php');
		$page = get_order_string_page(false);
		$_SESSION['sort_data'][$page] = ['name' => 'DESC', 'ip' => 'ASC'];
		update_order_string(true);
		$this->assertSame('ORDER BY name DESC, INET_ATON(ip) ASC', $_SESSION['sort_string'][$page]);
	}

	// --- CactiTableFilter: session_var from basename((string) get_current_page()) ---

	public function test_table_filter_session_var_from_action() : void {
		libhtml_reset('graphs.php', ['action' => 'edit']);
		$filter = new CactiTableFilter('Header', 'graphs.php', 'form_graphs', '');
		$this->assertSame('graphs_edit', $filter->session_var);
	}

	public function test_table_filter_session_var_from_tab() : void {
		libhtml_reset('graphs.php', ['tab' => 'general']);
		$filter = new CactiTableFilter('Header', 'graphs.php', 'form_graphs', '');
		$this->assertSame('graphs_general', $filter->session_var);
	}

	public function test_table_filter_session_var_page_only() : void {
		libhtml_reset('graphs.php', []);
		$filter = new CactiTableFilter('Header', 'graphs.php', 'form_graphs', '');
		$this->assertSame('graphs', $filter->session_var);
	}

	// --- form_filepath_box: is_file/is_dir(trim((string) $prev_val)) ---

	public function test_filepath_box_reports_file_found() : void {
		ob_start();
		form_filepath_box('path_field', __FILE__, '', '', 40);
		$html = ob_get_clean();
		$this->assertStringContainsString('File Found', $html);
	}

	public function test_filepath_box_reports_directory() : void {
		ob_start();
		form_filepath_box('path_field', __DIR__, '', '', 40);
		$html = ob_get_clean();
		$this->assertStringContainsString('Directory', $html);
	}

	// --- form_multi_dropdown: explode(',', (string) $prev_vals) ---

	public function test_multi_dropdown_renders_selected_from_csv() : void {
		ob_start();
		form_multi_dropdown('templates', [1 => 'Alpha', 2 => 'Beta', 3 => 'Gamma'], '1,3', 'id');
		$html = ob_get_clean();
		$this->assertStringContainsString('<select', $html);
		$this->assertStringContainsString('Alpha', $html);
	}
}
