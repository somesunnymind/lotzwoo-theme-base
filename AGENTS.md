# AGENTS.md

Hinweise für Menschen und Programmier-Agenten, die an diesem Repo arbeiten. Kurz gehalten; Details
stehen im [README](README.md) und in [CONTRIBUTING.md](CONTRIBUTING.md).

## Projekt

WordPress-Block-Theme `lotzwoo-theme-base`: die Basis für B2B-Shops auf WooCommerce mit dem Plugin
LotzApp for WooCommerce. PHP ≥ 8.1, WordPress ≥ 6.6, kein Bauschritt. Node braucht nur die CI.

## Befehle

```bash
npm ci && npm run check          # theme.json, Header, Blockmarkup, Sperrliste
php scripts/check-updater.php    # Update-Weg, ohne WordPress
php -l functions.php inc/*.php   # PHP-Syntax
```

`scripts/check-frontend.php` und `scripts/check-mail-angaben.php` brauchen eine laufende
Installation (`wp eval-file`).

## Harte Regeln

- **Slug und Vertragsnamen bleiben.** `lotzwoo-theme-base`, `lotzwoo/header-slot`,
  `lotzwoo/cart-slot` + `id="lotzwoo-cart-slot"`, `lotzwoo/context-slot`, Filter
  `lotzwoo/sortiment-url`, Token-Wurzeln `.lotzwoo-b2b-shop`, `.lotzwoo-reorder`,
  `.lotzwoo-heldback`. Tabelle im README unter „Vertrag mit dem Plugin".
- **Das Theme darf das Plugin kennen, das Plugin nie das Theme.** Das Theme ruft keine Funktion des
  Plugins auf und läuft ohne es.
- **Nichts Kundenspezifisches.** Menü, Fußlinks, Farben und Mail-Angaben eines Kunden gehören in
  sein Kindtheme. `parts/navigation.html` und `parts/footer-links.html` bleiben hier leer.
- **Kommentare beschreiben den Code, wie er ist:** das Warum, ohne Vorgangsnummern, Daten, Namen von
  Kunden oder Personen, Serverpfade. `scripts/paket-sperrliste.txt` prüft das.
- Keine Zugangsdaten ins Repo.
- Datei, die nicht ins Release-ZIP gehört: `export-ignore` in `.gitattributes`.

## Fertig heißt

- `npm run check` und `php scripts/check-updater.php` grün, CI grün.
- Bei Kommentar-Änderungen: Code ohne Kommentare vorher gleich nachher.
- README nachgezogen, wenn sich Verhalten oder der Vertrag mit dem Plugin ändert.
