// Prueft die Theme-Header in `style.css` auf Vollstaendigkeit und Form —
// und haelt die `$schema`-Version aus `theme.json` gegen `Requires at least`.
//
// Der zweite Teil ist der eigentliche Grund fuer diese Datei: `theme.json`
// `"version": 3` gibt es erst ab WordPress 6.6. Stand bis 2026-08-10 im Header
// 6.5, waere das Theme auf jeder 6.5-Installation stillschweigend halb
// interpretiert worden.
import { readFileSync } from 'node:fs';

const css = readFileSync('style.css', 'utf8');
const block = css.slice(0, css.indexOf('*/'));

const header = (name) => {
  const m = block.match(new RegExp(`^\\s*${name}:\\s*(.+)$`, 'm'));
  return m ? m[1].trim() : null;
};

const required = [
  'Theme Name',
  'Author',
  'Description',
  'Version',
  'Requires at least',
  'Tested up to',
  'Requires PHP',
  'License',
  'License URI',
  'Text Domain',
];

let failed = false;

for (const name of required) {
  if (!header(name)) {
    console.error(`Header fehlt: ${name}`);
    failed = true;
  }
}

const version = header('Version');

if (version && !/^\d+\.\d+\.\d+/.test(version)) {
  console.error(`Version ist kein Semver: ${version}`);
  failed = true;
}

// theme.json `version` gegen `Requires at least`.
const themeJson = JSON.parse(readFileSync('theme.json', 'utf8'));
const minWpForThemeJson = { 1: '5.8', 2: '5.9', 3: '6.6' };
const needed = minWpForThemeJson[themeJson.version];
const requires = header('Requires at least');

if (!needed) {
  console.error(`Unbekannte theme.json-Version: ${themeJson.version}`);
  failed = true;
} else if (requires) {
  const cmp = (a, b) => {
    const pa = a.split('.').map(Number);
    const pb = b.split('.').map(Number);
    for (let i = 0; i < Math.max(pa.length, pb.length); i++) {
      const d = (pa[i] ?? 0) - (pb[i] ?? 0);
      if (d !== 0) return d;
    }
    return 0;
  };

  if (cmp(requires, needed) < 0) {
    console.error(
      `theme.json version ${themeJson.version} braucht WordPress ${needed}, ` +
        `"Requires at least" sagt aber ${requires}.`
    );
    failed = true;
  }

  const schemaVersion = (themeJson.$schema ?? '').match(/\/wp\/(\d+\.\d+)\//)?.[1];

  if (schemaVersion && cmp(requires, schemaVersion) < 0) {
    console.error(
      `$schema zeigt auf WordPress ${schemaVersion}, "Requires at least" sagt ${requires}.`
    );
    failed = true;
  }
}

if (failed) {
  process.exit(1);
}

console.log('Theme-Header vollstaendig, theme.json-Version und "Requires at least" passen zusammen.');
