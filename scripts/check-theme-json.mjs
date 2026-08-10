// Prueft `theme.json` gegen das **versionierte** Kernschema.
//
// Versioniert, nicht `trunk`: gegen `trunk` zu pruefen heisst, dass ein
// WordPress-Release die eigene CI rot faerben kann, ohne dass sich hier eine
// Zeile geaendert hat. Die Schema-URI in der Datei ist zugleich die Aussage,
// gegen welche WordPress-Version dieses Theme geschrieben ist — Pruefung 2
// haelt sie mit `Requires at least` zusammen.
//
// Das Schema wird zur Laufzeit geholt. Ohne Netz faellt die Pruefung aus, statt
// falsch gruen zu sein.
import { readFileSync } from 'node:fs';
// `ajv-draft-04`, nicht `ajv`: das Kernschema von WordPress deklariert
// `http://json-schema.org/draft-04/schema#`, und Ajv 8 kennt Draft-04 nicht
// mehr von Haus aus. Mit dem blossen `ajv` bricht die Pruefung mit
// "no schema with key or ref draft-04" ab — was wie ein Fehler im Theme
// aussieht und keiner ist.
import Ajv from 'ajv-draft-04';
import addFormats from 'ajv-formats';

const themeJson = JSON.parse(readFileSync('theme.json', 'utf8'));
const schemaUri = themeJson.$schema;

if (!schemaUri) {
  console.error('theme.json hat kein $schema.');
  process.exit(1);
}

if (schemaUri.includes('/trunk/')) {
  console.error(`$schema zeigt auf trunk statt auf eine Version: ${schemaUri}`);
  process.exit(1);
}

const res = await fetch(schemaUri);

if (!res.ok) {
  console.error(`Schema nicht erreichbar (${res.status}): ${schemaUri}`);
  process.exit(1);
}

const ajv = new Ajv({ allErrors: true, strict: false });
addFormats(ajv);

const validate = ajv.compile(await res.json());

if (!validate(themeJson)) {
  for (const err of validate.errors ?? []) {
    console.error(`${err.instancePath || '/'} ${err.message}`);
  }
  process.exit(1);
}

console.log(`theme.json ist gueltig gegen ${schemaUri}`);
