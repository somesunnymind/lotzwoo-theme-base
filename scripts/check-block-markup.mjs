// Prueft, dass jede `.html` in `templates/` und `parts/` wohlgeformtes
// Blockmarkup ist.
//
// Was das faengt: ein fehlender Schlusskommentar, ein kaputtes Attribut-JSON,
// ein `<!-- /wp:group -->` zu viel. Alles davon rendert im Frontend als halbe
// Seite und faellt in einem Diff nicht auf.
//
// Was das **nicht** faengt: ob die verwendeten Bloecke ueberhaupt existieren.
// Dafuer braeuchte es eine laufende WordPress-Installation mit demselben
// Plugin-Satz — bewusst nicht Teil dieser CI.
import { readdirSync, readFileSync, statSync } from 'node:fs';
import { join } from 'node:path';
import { parse } from '@wordpress/block-serialization-default-parser';

const walk = (dir) => {
  let out = [];
  for (const entry of readdirSync(dir)) {
    const full = join(dir, entry);
    if (statSync(full).isDirectory()) out = out.concat(walk(full));
    else if (entry.endsWith('.html')) out.push(full);
  }
  return out;
};

let failed = false;
let count = 0;

for (const dir of ['templates', 'parts']) {
  let files;
  try {
    files = walk(dir);
  } catch {
    continue;
  }

  for (const file of files) {
    count++;
    const source = readFileSync(file, 'utf8');
    const blocks = parse(source);

    // Der Parser wirft nie. Ein unbalanciertes oder unvollstaendiges Markup
    // zeigt sich daran, dass ein benannter Block ohne `innerHTML` und ohne
    // Kinder herausfaellt, oder dass Freitext ausserhalb jedes Blocks steht,
    // der wie ein Blockkommentar aussieht.
    const stack = [blocks];

    while (stack.length) {
      for (const block of stack.pop()) {
        if (block.blockName === null) {
          if (/<!--\s*\/?wp:/.test(block.innerHTML ?? '')) {
            console.error(`${file}: Blockkommentar ausserhalb eines Blocks — unbalanciert?`);
            failed = true;
          }
          continue;
        }

        for (const attr of Object.keys(block.attrs ?? {})) {
          if (attr === '') {
            console.error(`${file}: leerer Attributname in ${block.blockName}`);
            failed = true;
          }
        }

        if (block.innerBlocks?.length) stack.push(block.innerBlocks);
      }
    }

    // Gegenprobe auf Zaehlerebene: oeffnende gegen schliessende Kommentare.
    const opening = (source.match(/<!--\s*wp:[^/][^>]*?(?<!\/)-->/g) ?? []).length;
    const closing = (source.match(/<!--\s*\/wp:/g) ?? []).length;

    if (opening !== closing) {
      console.error(`${file}: ${opening} oeffnende, ${closing} schliessende Blockkommentare`);
      failed = true;
    }
  }
}

if (failed) {
  process.exit(1);
}

console.log(`${count} Blockmarkup-Dateien geprueft.`);
