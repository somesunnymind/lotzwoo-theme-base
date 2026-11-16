// Sucht Interna im Repo und im Release-ZIP: Kunden- und Personennamen,
// Serverpfade, Ticket-Kürzel und Datumsangaben.
//
// Kommentare in `style.css`, `functions.php` und `assets/` gehen mit dem ZIP an
// jeden Kunden. Ein Name oder ein Datum dort verrät, für wen und wann gearbeitet
// wurde, und veraltet. Die Begründung einer Regel gehört in den Kommentar, ihr
// Verlauf in die Git-Historie.
//
//     node scripts/check-sperrliste.mjs                 # alle Dateien im Baum
//     node scripts/check-sperrliste.mjs --zip x.zip     # das gebaute Paket
import { execFileSync } from 'node:child_process';
import { readFileSync, statSync } from 'node:fs';

const LISTE = new URL('./paket-sperrliste.txt', import.meta.url);

// Dateien, die selbst Sperrbegriffe nennen müssen.
const AUSNAHMEN = new Set(['scripts/paket-sperrliste.txt', 'scripts/check-sperrliste.mjs']);

const MONATE = 'Januar|Februar|März|Maerz|April|Mai|Juni|Juli|August|September|Oktober|November|Dezember';

const MUSTER = [
  ['Ticket-Kürzel', /\b(?:AP|AD|S|H)-\d+/],
  ['Datum', /\b20\d\d-\d\d-\d\d\b/],
  ['Datum', new RegExp(`\\b(?:${MONATE})\\s+20\\d\\d\\b`)],
  ['Datum', /\b\d{1,2}\.\s?\d{1,2}\.\s?20\d\d\b/],
];

const begriffe = readFileSync(LISTE, 'utf8')
  .split('\n')
  .map((zeile) => zeile.trim().toLowerCase())
  .filter((zeile) => zeile !== '' && !zeile.startsWith('#'));

const istWortZeichen = (zeichen) => zeichen !== undefined && /[\p{L}\p{N}]/u.test(zeichen);

// Ein reines Wort zählt nur am Wortanfang: „Klone" ist ein Treffer,
// „Datenbank" keiner.
function begriffTreffer(text) {
  const klein = text.toLowerCase();
  const treffer = [];
  for (const begriff of begriffe) {
    const nurWort = /^[a-z0-9-]+$/.test(begriff);
    let stelle = klein.indexOf(begriff);
    while (stelle !== -1) {
      if (!nurWort || !istWortZeichen(klein[stelle - 1])) {
        treffer.push(begriff);
        break;
      }
      stelle = klein.indexOf(begriff, stelle + 1);
    }
  }
  return treffer;
}

function pruefe(name, text) {
  const funde = [];
  text.split('\n').forEach((zeile, i) => {
    for (const begriff of begriffTreffer(zeile)) funde.push(`${name}:${i + 1}: Sperrbegriff „${begriff}"`);
    for (const [art, muster] of MUSTER) {
      const m = zeile.match(muster);
      if (m) funde.push(`${name}:${i + 1}: ${art} „${m[0]}"`);
    }
  });
  // Auch der Dateiname zählt.
  for (const begriff of begriffTreffer(name)) funde.push(`${name}: Sperrbegriff „${begriff}" im Namen`);
  return funde;
}

function baumDateien() {
  const aus = execFileSync('git', ['ls-files', '-z', '--cached', '--others', '--exclude-standard'], { encoding: 'utf8' });
  return aus
    .split('\0')
    .filter((datei) => datei !== '' && !AUSNAHMEN.has(datei))
    .filter((datei) => {
      try {
        return statSync(datei).isFile();
      } catch {
        return false; // gelöscht, aber noch nicht committet
      }
    })
    .map((datei) => [datei, () => readFileSync(datei).toString('latin1')]);
}

function zipDateien(zip) {
  const namen = execFileSync('unzip', ['-Z1', zip], { encoding: 'utf8' }).split('\n');
  return namen
    .filter((name) => name !== '' && !name.endsWith('/'))
    .map((name) => [name, () => execFileSync('unzip', ['-p', zip, name], { maxBuffer: 64 * 1024 * 1024 }).toString('latin1')]);
}

// Gelesen wird als latin1: ein Byte, ein Zeichen. So trifft die Suche auch in
// Binärdateien und fremder Kodierung, und Umlaute in der Sperrliste stören nicht,
// weil keiner darin steht.
const zipIndex = process.argv.indexOf('--zip');
const dateien = zipIndex === -1 ? baumDateien() : zipDateien(process.argv[zipIndex + 1]);

if (begriffe.length < 10 || dateien.length < 10) {
  console.error(`Zu wenig geprüft: ${begriffe.length} Begriffe, ${dateien.length} Dateien.`);
  process.exit(1);
}

const funde = dateien.flatMap(([name, lies]) => pruefe(name, lies()));

if (funde.length > 0) {
  console.error(funde.join('\n'));
  console.error(`\n${funde.length} Treffer in ${new Set(funde.map((f) => f.split(':')[0])).size} Dateien.`);
  process.exit(1);
}

console.log(`Sperrliste: ${dateien.length} Dateien, ${begriffe.length} Begriffe, keine Treffer.`);
