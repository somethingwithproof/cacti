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

'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');

const root = path.join(__dirname, '..', '..', 'include');
const layoutSource = fs.readFileSync(path.join(root, 'layout.js'), 'utf8');
const themes = ['cacti', 'dark', 'deepness', 'midwinter', 'modern', 'paper-plane', 'paw', 'raspberry', 'sunrise'];

function extractFunction(name) {
	const start = layoutSource.indexOf(`function ${name}(`);

	assert.notEqual(start, -1, `${name}() must exist`);

	const bodyStart = layoutSource.indexOf('{', start);
	let depth = 0;

	for (let offset = bodyStart; offset < layoutSource.length; offset++) {
		if (layoutSource[offset] === '{') {
			depth++;
		} else if (layoutSource[offset] === '}') {
			depth--;

			if (depth === 0) {
				return layoutSource.slice(start, offset + 1);
			}
		}
	}

	throw new Error(`Unable to extract ${name}()`);
}

/* A tiny DOM plus the subset of jQuery the helpers use.  Visibility follows
 * the real rule that matters here: a node is visible only when it and every
 * ancestor are not hidden, so hiding the filter container hides the input
 * and its sibling icon together. */
function makeNode(tag, props = {}) {
	return { tag, id: props.id || '', cls: props.cls || '', attrs: {}, style: {}, hidden: false, parent: null, kids: [] };
}

function append(parent, child) {
	child.parent = parent;
	parent.kids.push(child);
	return child;
}

function isVisible(node) {
	for (let n = node; n; n = n.parent) {
		if (n.hidden) {
			return false;
		}
	}

	return true;
}

function build(inputIds) {
	const table = makeNode('div', { id: 'filter_table' });
	const child = append(table, makeNode('div', { id: 'filter_table_child' }));
	const row = append(child, makeNode('tr'));
	const nodes = [];

	for (const id of inputIds) {
		const td = append(row, makeNode('td'));
		nodes.push(append(td, makeNode('input', { id })));
	}

	return { table, child, row, nodes };
}

function loadEnvironment(dom) {
	const store = {};
	const all = () => {
		const out = [];
		const walk = (n) => { out.push(n); n.kids.forEach(walk); };
		walk(dom.table);
		return out;
	};

	function wrap(list) {
		const w = {
			length: list.length,
			list,
			each(fn) { list.forEach((n) => fn.call(n, 0, n)); return w; },
			attr(k, v) { list.forEach((n) => { n.attrs[k] = v; }); return w; },
			css(k, v) { list.forEach((n) => { n.style[k] = v; }); return w; },
			parent(sel) { return wrap(list.map((n) => n.parent).filter((p) => p && (!sel || p.tag === sel))); },
			next(sel) {
				const out = [];
				list.forEach((n) => {
					const sib = n.parent.kids[n.parent.kids.indexOf(n) + 1];
					const wanted = sel.split('.').slice(1);
					if (sib && sib.tag === 'i' && wanted.every((c) => sib.cls.split(/\s+/).includes(c))) {
						out.push(sib);
					}
				});
				return wrap(out);
			},
			after(html) {
				const cls = /class='([^']*)'/.exec(html)[1];
				list.forEach((n) => {
					const icon = makeNode('i', { cls });
					icon.parent = n.parent;
					n.parent.kids.splice(n.parent.kids.indexOf(n) + 1, 0, icon);
				});
				return w;
			},
			is(sel) { assert.equal(sel, ':visible'); return list.length > 0 && list.every(isVisible); },
			hide() { list.forEach((n) => { n.hidden = true; }); return w; },
			show() { list.forEach((n) => { n.hidden = false; }); return w; },
			closest(sel) {
				const cls = sel.slice(1);
				const out = [];
				list.forEach((n) => {
					for (let p = n.parent; p; p = p.parent) {
						if (p.cls.split(/\s+/).includes(cls)) {
							out.push(p);
							break;
						}
					}
				});
				return wrap(out);
			},
			find() { return wrap([]); },
			removeClass() { return w; },
			addClass() { return w; },
			trigger() { return w; },
		};

		return w;
	}

	function $(sel) {
		if (sel === 'window' || sel === windowObject) {
			return wrap([]);
		}

		if (sel && typeof sel === 'object' && sel.tag) {
			return wrap([sel]);
		}

		let m = /^input\[id="(\w+)"\]$/.exec(sel);
		if (m) {
			return wrap(all().filter((n) => n.tag === 'input' && n.id === m[1]));
		}

		m = /^#(\w+)$/.exec(sel);
		if (m) {
			return wrap(all().filter((n) => n.id === m[1]));
		}

		return wrap([]);
	}

	$.each = (obj, fn) => Object.keys(obj).forEach((k) => fn(k, obj[k]));

	const windowObject = {};
	const context = {
		$,
		window: windowObject,
		searchFilter: 'Search',
		searchRFilter: 'RSearch',
		Storages: { sessionStorage: { isSet: (k) => k in store, get: (k) => store[k], set: (k, v) => { store[k] = v; } } },
		store,
	};

	vm.createContext(context);
	vm.runInContext(
		[extractFunction('setupFilterSearchIcons'), extractFunction('toggleFilterAndIcon')].join('\n') +
		'\nthis.setupFilterSearchIcons = setupFilterSearchIcons; this.toggleFilterAndIcon = toggleFilterAndIcon;',
		context
	);

	return context;
}

/* The same order applySkin() uses: themeReady() decorates, then
 * makeFiltersResponsive() applies the stored visibility (initial). */
function skin(ctx) {
	ctx.setupFilterSearchIcons();
	ctx.toggleFilterAndIcon('filter_table', 'filter_table_child', true);
}

function icons(dom, input) {
	const siblings = input.parent.kids;
	return siblings.filter((n) => n.tag === 'i' && n.cls === 'ti ti-search filter').length;
}

function assertDecorated(dom, input, placeholder) {
	assert.equal(icons(dom, input), 1, `${input.id} has exactly one icon`);
	assert.equal(input.attrs.autocomplete, 'off');
	assert.equal(input.attrs.placeholder, placeholder);
	assert.equal(input.parent.style['white-space'], 'nowrap');
}

test('visible at load: icon, placeholder and autocomplete are applied', () => {
	const dom = build(['filter', 'filterd', 'rfilter']);
	const ctx = loadEnvironment(dom);

	skin(ctx);

	assertDecorated(dom, dom.nodes[0], 'Search');
	assertDecorated(dom, dom.nodes[1], 'Search');
	assertDecorated(dom, dom.nodes[2], 'RSearch');
	assert.equal(isVisible(dom.nodes[0]), true);
});

test('hidden at load then shown: decoration exists before and after expanding', () => {
	const dom = build(['filter']);
	const ctx = loadEnvironment(dom);

	ctx.store.filterVisibility = 'hidden';
	skin(ctx);

	assert.equal(isVisible(dom.nodes[0]), false, 'filter is hidden after skinning');
	assertDecorated(dom, dom.nodes[0], 'Search');

	ctx.toggleFilterAndIcon('filter_table', 'filter_table_child', false);

	assert.equal(isVisible(dom.nodes[0]), true, 'filter is shown after toggle');
	assert.equal(ctx.store.filterVisibility, 'visible');
	assertDecorated(dom, dom.nodes[0], 'Search');

	const icon = dom.nodes[0].parent.kids[1];
	assert.equal(isVisible(icon), true, 'icon is shown with the input');
});

test('icon hides with its input when the filter is collapsed', () => {
	const dom = build(['filter']);
	const ctx = loadEnvironment(dom);

	skin(ctx);
	ctx.toggleFilterAndIcon('filter_table', 'filter_table_child', false);

	assert.equal(isVisible(dom.nodes[0]), false);
	assert.equal(isVisible(dom.nodes[0].parent.kids[1]), false);
});

test('narrow viewport then widened: a cell hidden for width keeps one icon', () => {
	const dom = build(['filter']);
	const ctx = loadEnvironment(dom);

	skin(ctx);
	dom.nodes[0].parent.hidden = true;
	assert.equal(isVisible(dom.nodes[0]), false);
	skin(ctx);
	assertDecorated(dom, dom.nodes[0], 'Search');

	dom.nodes[0].parent.hidden = false;
	assert.equal(isVisible(dom.nodes[0].parent.kids[1]), true);
	assertDecorated(dom, dom.nodes[0], 'Search');
});

test('applySkin run repeatedly leaves exactly one icon', () => {
	const dom = build(['filter', 'rfilter']);
	const ctx = loadEnvironment(dom);

	skin(ctx);
	skin(ctx);
	skin(ctx);

	assertDecorated(dom, dom.nodes[0], 'Search');
	assertDecorated(dom, dom.nodes[1], 'RSearch');
});

test('input already hidden when the helper runs (deferred or AJAX refresh) is still decorated', () => {
	const dom = build(['filter']);
	const ctx = loadEnvironment(dom);

	dom.child.hidden = true;
	ctx.setupFilterSearchIcons();

	assertDecorated(dom, dom.nodes[0], 'Search');

	ctx.toggleFilterAndIcon('filter_table', 'filter_table_child', false);

	assert.equal(isVisible(dom.nodes[0].parent.kids[1]), true);
	assertDecorated(dom, dom.nodes[0], 'Search');
});

test('rfilter inside a filter edit table is left undecorated', () => {
	const dom = build(['rfilter']);
	const ctx = loadEnvironment(dom);

	dom.child.cls = 'cactiFilterEditTable';
	ctx.setupFilterSearchIcons();

	assert.equal(icons(dom, dom.nodes[0]), 0);
	assert.equal(dom.nodes[0].attrs.placeholder, undefined);
});

test('page without filter inputs is a no-op', () => {
	const dom = build([]);
	const ctx = loadEnvironment(dom);

	assert.doesNotThrow(() => skin(ctx));
});

for (const theme of themes) {
	test(`${theme} themeReady delegates to the shared helper without deferral`, () => {
		const source = fs.readFileSync(path.join(root, 'themes', theme, 'main.js'), 'utf8');

		assert.equal((source.match(/setupFilterSearchIcons\(\);/g) || []).length, 1);
		assert.equal(source.includes('ti ti-search filter'), false, 'icon markup lives in layout.js only');
		assert.equal(/setTimeout\(function\(\) \{\s*if \(\$\('input\[id="filter"\]'\)/.test(source), false);
	});
}
