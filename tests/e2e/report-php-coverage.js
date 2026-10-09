'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const crypto = require('node:crypto');

const root = path.resolve(process.argv[4] || path.join(__dirname, '../..'));
const [directory, destination] = process.argv.slice(2);
const selectedFiles = new Set(process.argv.slice(5));
const files = new Map();
for (const name of fs.readdirSync(directory).filter(name => name.endsWith('.json'))) {
    for (const [file, coverage] of Object.entries(JSON.parse(fs.readFileSync(path.join(directory, name), 'utf8')))) {
        if (selectedFiles.size && !selectedFiles.has(file)) continue;
        assert.ok(!path.isAbsolute(file) && !file.split('/').includes('..') && file.endsWith('.php'));
        const hash = crypto.createHash('sha256').update(fs.readFileSync(path.join(root, file))).digest('hex');
        assert.equal(coverage.sha256, hash, `Request coverage differs from checkout: ${file}`);
        if (!files.has(file)) files.set(file, new Map());
        for (const [line, count] of Object.entries(coverage.lines)) {
            assert.ok(Number.isInteger(Number(line)) && Number(line) > 0);
            files.get(file).set(Number(line), (files.get(file).get(Number(line)) || false) || count > 0);
        }
    }
}
assert.ok(files.size > 0, 'No measured PHP request coverage was collected.');
const xml = ['<coverage version="1">'];
for (const [file, lines] of [...files].sort()) {
    const escaped = file.replace(/[&<>"']/g, character => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&apos;' })[character]);
    xml.push(`  <file path="${escaped}">`);
    for (const [line, covered] of [...lines].sort((a, b) => a[0] - b[0])) {
        xml.push(`    <lineToCover lineNumber="${line}" covered="${covered}"/>`);
    }
    xml.push('  </file>');
}
xml.push('</coverage>');
fs.mkdirSync(path.dirname(destination), { recursive: true });
fs.writeFileSync(destination, xml.join('\n') + '\n');
console.log(`Converted PCOV request coverage for ${files.size} application files.`);
