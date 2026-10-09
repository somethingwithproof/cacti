'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const v8toIstanbul = require('v8-to-istanbul');
const { productionFiles } = require('./browser-coverage');

async function report(directory, destination) {
    const root = path.resolve(__dirname, '../..');
    const files = new Map();
    for (const name of fs.readdirSync(directory).filter(name => name.endsWith('.json'))) {
        for (const entry of JSON.parse(fs.readFileSync(path.join(directory, name), 'utf8'))) {
            const file = new URL(entry.url).pathname.slice(1);
            assert.ok(productionFiles.has(file), `Unexpected coverage source: ${file}`);
            const source = fs.readFileSync(path.join(root, file), 'utf8');
            assert.ok(entry.source === source, `Coverage source differs from checkout: ${file}`);
            const converter = v8toIstanbul(path.join(root, file), 0, { source });
            await converter.load();
            converter.applyCoverage(entry.functions);
            const coverage = Object.values(converter.toIstanbul())[0];
            if (!files.has(file)) files.set(file, { lines: new Map(), branches: new Map() });
            const aggregate = files.get(file);
            for (const [id, location] of Object.entries(coverage.statementMap)) {
                const line = location.start.line;
                aggregate.lines.set(line, (aggregate.lines.get(line) || 0) + coverage.s[id]);
            }
            for (const [id, location] of Object.entries(coverage.branchMap)) {
                const key = `${location.line}:${location.loc.start.column}:${location.loc.end.line}:${location.loc.end.column}`;
                const counts = aggregate.branches.get(key) || { line: location.line, counts: Array(coverage.b[id].length).fill(0) };
                assert.equal(counts.counts.length, coverage.b[id].length);
                coverage.b[id].forEach((count, index) => { counts.counts[index] += count; });
                aggregate.branches.set(key, counts);
            }
        }
    }
    for (const file of productionFiles) assert.ok(files.has(file), `Missing real browser coverage: ${file}`);
    const xml = ['<coverage version="1">'];
    for (const [file, coverage] of [...files].sort()) {
        xml.push(`  <file path="${file}">`);
        for (const [line, count] of [...coverage.lines].sort((a, b) => a[0] - b[0])) {
            const branches = [...coverage.branches.values()].filter(branch => branch.line === line).flatMap(branch => branch.counts);
            const attributes = branches.length
                ? ` branchesToCover="${branches.length}" coveredBranches="${branches.filter(count => count > 0).length}"` : '';
            xml.push(`    <lineToCover lineNumber="${line}" covered="${count > 0}"${attributes}/>`);
        }
        xml.push('  </file>');
    }
    xml.push('</coverage>');
    fs.mkdirSync(path.dirname(destination), { recursive: true });
    fs.writeFileSync(destination, xml.join('\n') + '\n');
    console.log(`Converted real V8 browser coverage for ${files.size} production files.`);
}

report(process.argv[2], process.argv[3]).catch(error => { console.error(error); process.exitCode = 1; });
