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
 * Covers the PHP 8.4 idiom migration in the render-only helpers of lib/html.php:
 * (string) casts ahead of basename()/substr_count()/strstr() and str_starts_with()
 * on menu urls. These print HTML, so each case captures the output buffer and
 * asserts the migrated branch was taken. The rewrites are behaviour preserving.
 */

require_once dirname(__DIR__) . '/Helpers/LibHtmlHarness.php';

final class LibHtmlRenderModernizationTest extends \PHPUnit\Framework\TestCase {
	protected function setUp() : void {
		libhtml_reset();
	}

	private function render(callable $fn) : string {
		ob_start();
		$fn();

		return (string) ob_get_clean();
	}

	// --- is_console_page: basename((string) $page) == $basename ---

	public function test_is_console_page_matches_menu_entry() : void {
		$GLOBALS['menu'] = ['Console' => ['host.php' => 'Devices', 'graphs.php' => 'Graphs']];
		$this->assertTrue(is_console_page('host.php'));
	}

	public function test_is_console_page_returns_false_for_unknown_page() : void {
		$GLOBALS['menu'] = ['Console' => ['host.php' => 'Devices']];
		$this->assertFalse(is_console_page('unknown_page.php'));
	}

	// --- html_start_box: basename((string) get_current_page()) + help link ---

	public function test_start_box_emits_table_wrapper() : void {
		// use a page with no help mapping so the static help_count stays 0 for
		// the help-link case regardless of test execution order.
		libhtml_reset('no_such_help_page.php');
		$html = $this->render(fn() => html_start_box('Graph Management', '100%', true, 3, 'left', ''));
		$this->assertStringContainsString('cactiTable', $html);
	}

	public function test_start_box_emits_help_link_for_known_page() : void {
		// graph_view.php has a help mapping, so the is_realm_allowed(28) help
		// branch (basename((string) $help_file)) is exercised.
		libhtml_reset('graph_view.php');
		libhtml_grant_realms([28]);
		$html = $this->render(fn() => html_start_box('Graphs', '100%', true, 3, 'left', ''));
		$this->assertStringContainsString('helpPage', $html);
	}

	// --- html_sub_tabs: basename((string) get_current_page()) session default ---

	public function test_sub_tabs_render_with_default_session_var() : void {
		libhtml_reset('settings.php');
		$html = $this->render(fn() => html_sub_tabs(['general' => 'General', 'auth' => 'Authentication'], '', ''));
		$this->assertStringContainsString('subTab', $html);
		$this->assertStringContainsString('General', $html);
	}

	// --- html_header_sort / _checkbox: substr_count((string) $db_column, 'nosort') ---

	private function sortHeaders() : array {
		return [
			'name'     => ['display' => 'Name', 'sort' => 'ASC'],
			''         => ['display' => 'Spacer'],
			'nosortme' => ['display' => 'Actions'],
		];
	}

	public function test_header_sort_marks_nosort_and_empty_columns() : void {
		libhtml_reset('graphs.php', ['sort_column' => 'name', 'sort_direction' => 'ASC']);
		$html = $this->render(fn() => html_header_sort($this->sortHeaders(), 'name', 'ASC', 1, 'graphs.php'));
		$this->assertStringContainsString('<th', $html);
		$this->assertStringContainsString('sortable', $html);
	}

	public function test_header_sort_checkbox_marks_nosort_and_empty_columns() : void {
		libhtml_reset('graphs.php', ['sort_column' => 'name', 'sort_direction' => 'ASC']);
		$html = $this->render(fn() => html_header_sort_checkbox($this->sortHeaders(), 'name', 'ASC', false, 'graphs.php'));
		$this->assertStringContainsString('<th', $html);
	}

	// --- draw_menu: basename((string) $item_url), str_starts_with EXTERNAL:: ---

	public function test_draw_menu_renders_links_dropdowns_and_external_items() : void {
		$menu = [
			'Console' => [
				'host.php'                  => 'Devices',
				'link.php?id=3'             => 'External Link',
				'EXTERNAL::https://ext.example.com' => 'External Site',
				'graphs.php'                => [
					'graph_view.php'                    => 'Graphs',
					'EXTERNAL::https://sub.example.com' => 'Sub External',
					'other.php'                         => 'Other',
				],
			],
		];
		$GLOBALS['menu']                     = $menu;
		$GLOBALS['menu_glyphs']              = ['Console' => 'ti ti-home'];
		$GLOBALS['user_auth_realm_filenames'] = ['host.php' => 1, 'graphs.php' => 1, 'other.php' => 1];

		libhtml_reset('graph_view.php');
		libhtml_grant_realms([1, 10003]);
		$html = $this->render(fn() => draw_menu($menu));

		$this->assertStringContainsString("id='nav'", $html);
		$this->assertStringContainsString('target=\'_blank\'', $html);
		$this->assertStringContainsString('/cacti/host.php', $html);
	}

	// --- draw_graph_items_list: preg_match((string) $graph_type_name) branches ---

	private function graphItem(int $typeId, array $overrides = []) : array {
		return array_merge([
			'graph_type_id'                => $typeId,
			'consolidation_function_id'    => 1,
			'data_source_name'             => 'ds',
			'text_format'                  => 'label',
			'hard_return'                  => '',
			'gprint_name'                  => 'g',
			'legend'                       => 'legend',
			'cdef_name'                    => '',
			'vdef_name'                    => '',
			'hex'                          => '',
			'alpha'                        => 'FF',
			'hex2'                         => '',
			'alpha2'                       => 'FF',
			'value'                        => '10',
			'textalign'                    => 'left',
			'local_graph_template_item_id' => $typeId,
			'sequence'                     => $typeId,
			'id'                           => $typeId,
		], $overrides);
	}

	public function test_draw_graph_items_list_covers_every_item_type_branch() : void {
		libhtml_reset('graphs.php');
		$items = [
			$this->graphItem(7, ['hex' => '00FF00', 'hex2' => '0000FF']), // AREA + gradient
			$this->graphItem(8, ['hex' => 'FF8800']),                     // AREA:STACK
			$this->graphItem(40),                                         // TEXTALIGN
			$this->graphItem(30, ['hex' => 'AABBCC']),                    // TICK
			$this->graphItem(9),                                          // GPRINT
			$this->graphItem(2),                                          // HRULE
			$this->graphItem(3),                                          // VRULE
			$this->graphItem(1),                                          // COMMENT
			$this->graphItem(4, ['hex' => 'FF0000', 'hard_return' => 'on']), // LINE1 + hard return
		];

		$html = $this->render(fn() => draw_graph_items_list($items, 'graphs.php', 'action=item_edit', true));

		$this->assertStringContainsString('<td', $html);
		$this->assertStringContainsString('00FF00FF', $html); // AREA color1 = hex . alpha
		$this->assertStringContainsString('TEXTALIGN', $html);
	}

	// --- form_selectable_cell / _vcell: json_decode((string) read_user_setting(...)) ---

	public function test_selectable_cell_renders_td() : void {
		libhtml_reset('graphs.php');
		$GLOBALS['tableCount']['cell_table'] = 0;
		$html = $this->render(fn() => form_selectable_cell('value', 'cell_table', '20%', '', 'tip'));
		$this->assertStringContainsString('<td', $html);
	}

	public function test_selectable_vcell_renders_td() : void {
		libhtml_reset('graphs.php');
		$GLOBALS['tableCount']['vcell_table'] = 0;
		$html = $this->render(fn() => form_selectable_vcell('value', 'vcell_table', 'col'));
		$this->assertStringContainsString('<td', $html);
	}
}
