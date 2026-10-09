'use strict';

const fs = require('node:fs');
const path = require('node:path');
const crypto = require('node:crypto');

const productionFiles = new Set([
    'install/install.js', 'include/layout.js', 'include/realtime.js',
    'include/themes/midwinter/main.js', 'include/themes/sunrise/main.js',
]);

function writeBrowserCoverage(entries) {
    const directory = process.env.CACTI_BROWSER_COVERAGE_DIR;
    if (!directory) return;
    const scripts = entries.filter(entry => {
        try { return productionFiles.has(new URL(entry.url).pathname.slice(1)); }
        catch { return false; }
    });
    fs.mkdirSync(directory, { recursive: true });
    fs.writeFileSync(path.join(directory, crypto.randomUUID() + '.json'), JSON.stringify(scripts));
}

module.exports = { productionFiles, writeBrowserCoverage };
