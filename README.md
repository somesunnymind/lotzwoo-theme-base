# LotzApp B2B Theme (Basis)

Minimales Block-Theme für B2B-Großhandelsshops auf WooCommerce. Es trägt das Design-System in
`theme.json` und sonst so wenig wie möglich: Kopfbereich, Fußbereich, ein Seiten-Template über die
volle Breite. **Alles Kundenspezifische gehört in ein Kindtheme** — das erste ist
[`lotzapp-bersta`](https://github.com/somesunnymind/lotzapp-bersta).

## Die Arbeitsteilung, in einem Absatz

Das Plugin `lotzapp-for-woocommerce` besitzt die B2B-Oberflächen — Sortiment, Warenkorb-Logik,
Konto-Endpunkte — und bringt sein eigenes Design-System mit (`b2b-tokens.css`, 54 Custom Properties
auf den Wurzeln `.lotzwoo-b2b-shop` und `.lotzwoo-reorder`). Dieses Theme liefert die Hülle und
übersetzt in `style.css` die `--wp--preset--*` aus `theme.json` in die `--lwb-*` des Plugins.

**Die Abhängigkeit steht im Theme, nie im Plugin.** Wer das Theme wechselt, bekommt die
mitgelieferten Standardwerte des Plugins statt einer kaputten Oberfläche — jedes `var()` in der
Brücke trägt denselben Wert als Fallback, den das Plugin ohnehin hat.

## Prüfen

```bash
npm install
npm run check
```

Fünf Prüfungen, alle ohne WordPress: `theme.json` gegen das **versionierte** Kernschema, die
Klammer zwischen `theme.json`-Version und `Requires at least`, Header-Vollständigkeit, `php -l` und
Blockmarkup auf Wohlgeformtheit. Kein Theme Check per `wp-env` — das führt ein ganzes WordPress in
Docker hoch, um Regeln zu prüfen, die für ein Verzeichnis gelten, in das dieses Theme nie
eingereicht wird.

## Ausliefern

```bash
scripts/deploy-theme.sh [ZIEL-THEMES-VERZEICHNIS]
```

Standardziel ist `/var/www/b2b/wp-content/themes`. Ausgeliefert wird der verfolgte Baum von `HEAD`
ohne die `export-ignore`-Pfade aus `.gitattributes`.

Zwei Dinge, die das Skript ausdrücklich tut: es liefert in den **Theme-Ordner** aus und nicht in das
Verzeichnis darüber (ein früherer Kopiervorgang hat die Repo-Wurzel nach `themes/` gelegt), und es
prüft nach dem Auspacken jede Datei per `cmp` sowie auf Waisen — `git archive` überschreibt, aber
löscht nie.

## Versionieren und veröffentlichen

Eine eigene Semver-Reihe, unabhängig vom Kindtheme. Ein Kindtheme, dessen Version steigt, weil das
Elterntheme ein Template bekam, lügt über sich selbst.

- **Erhöht wird**, wenn sich eine Datei ändert, die im ZIP landet — nicht bei jedem Commit. Die
  Erhöhung gehört in den Release-Commit.
- **`1.0.0`** ist erreicht, wenn jede Seite des B2B-Kaufwegs und des WordPress-Grundgerüsts ihr
  eigenes Block-Template hat und keine ungewollt auf einen WooCommerce- oder Core-Fallback fällt.
  Prüfbar, nicht Geschmack.

Ein Release entsteht durch einen Tag `X.Y.Z` (kein `v`-Präfix). Zwei Torwächter laufen davor: der
Tag muss dem `Version:`-Header entsprechen, und das gebaute ZIP muss die
`Theme_Upgrader::check_package()`-Bedingungen erfüllen.

**Ein Tag ist die Auslieferung an Kunden**, nicht ein Push. Installationen aktualisieren über den
`Update URI`-Header gegen `releases/latest`.

## Updates

Über den `Update URI`-Header (WordPress ≥ 6.1) und den Filter `update_themes_github.com` in
`functions.php`. Der Kern übernimmt damit Versionsvergleich, `no_update`-Pflege und
Kollisionsschutz.

Zwei Dinge, die dabei leicht falsch verstanden werden:

- Die URL im Header wird vom Kern **nie abgerufen**. Sie ist Kennung, nicht Endpunkt — das Paket
  kommt aus `package` im Filterergebnis.
- `update_themes_github.com` ist ein **geteilter** Namensraum. Jedes andere Theme, das denselben Weg
  geht, hängt am selben Filter; der Callback prüft deshalb zuerst Stylesheet und `UpdateURI`.

**Das Kindtheme aktualisiert sich nicht mit.** Der Kern führt jedes Stylesheet einzeln.
`lotzapp-bersta` hat bewusst keinen Update-Kanal und wird per Deploy-Skript ausgeliefert — so liegt
in keiner Kundeninstallation ein Token.

## Der Blockname, der zwei Repos verbindet

`parts/header.html` enthält einen Platzhalter-Block, den **dieses Theme registriert und leer
rendert** und den **das Plugin per `render_block`-Filter füllt** (Favoriten-Zähler, Kundenchip,
Schnellsuche). Ohne Plugin rendert der Kopf leer statt kaputt.

Der Blockname ist die einzige harte Kopplung zwischen Theme- und Plugin-Repo. Er steht deshalb in
beiden READMEs.

## Wo die Entscheidungen stehen

Karten, Architekturentscheidungen (`AD-*`) und Befunde liegen im Plugin-Repo
[`somesunnymind/lotzapp-for-woocommerce`](https://github.com/somesunnymind/lotzapp-for-woocommerce)
unter `.hermes/wayfinder/` und `docs/`. Hier liegt kein `docs/` und kein `.hermes/` — ein Theme-Repo
ist kein Planungsort.

Der Zuschnitt dieses Repos ist Ticket 06 der Karte `theme-templates-und-repo`.
