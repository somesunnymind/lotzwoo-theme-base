# LotzApp B2B Theme (Basis)

Minimales Block-Theme für B2B-Großhandelsshops auf WooCommerce. Es trägt das Design-System in
`theme.json` und sonst so wenig wie möglich: Kopfbereich, Fußbereich, zwei zuweisbare
Seitenvorlagen. **Alles Kundenspezifische gehört in ein Kindtheme** — das erste ist
[`lotzwoo-theme-bersta`](https://github.com/somesunnymind/lotzwoo-theme-bersta).

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
`lotzwoo-theme-bersta` hat bewusst keinen Update-Kanal und wird per Deploy-Skript ausgeliefert — so liegt
in keiner Kundeninstallation ein Token.

## Die Schriftskala — eine Leiter, zwei Hälften

`theme.json` kann keine Kommentare tragen, und die elf Größen dort sind ohne Begründung nicht zu
lesen. Sie steht deshalb hier.

| Slug | Wert | 390px | 1440px | |
|---|---|---|---|---|
| `xs` … `3xl` | `0.75rem` … `1.875rem` | 12–30px | 12–30px | **fest**, die Bedienskala |
| `4xl` | `clamp(1.7rem, 3.2vw, 2.6rem)` | 27,2px | 41,6px | **fluid**, die redaktionelle Skala |
| `5xl` | `clamp(2.2rem, 4vw, 3.2rem)` | 35,2px | 51,2px | |
| `6xl` | `clamp(2.4rem, 5vw, 3.9rem)` | 38,4px | 62,4px | |

Die untere Hälfte ist die Skala der **Oberfläche**: Bedienbeschriftungen, Tabellenzellen,
Filtergruppen, der Fließtext. Sie ist fest, und das ist keine Nachlässigkeit — eine Bestellmaske,
deren Zeilenhöhe mit der Fensterbreite wandert, ist an jeder Breite anders zu treffen.

Die obere Hälfte ist die Skala der **Drucksache**: Seitentitel, Kennzahlen, Abschnittsüberschriften.
Die drei Werte sind wörtlich die des Entwurfs
([ADR 004](https://github.com/somesunnymind/bersta-website), `docs/mockups/richtung-entwurf/`) —
`3.9rem` ist dort die `h1`, `3.2rem` die Kennzahl, `2.6rem` die `h2`.

### Warum eigene `clamp()` und nicht `settings.typography.fluid`

Zwei Messungen, beide am Klon mit `wp_get_typography_font_size_value()`.

**Erstens: der Schalter greift die bestehenden Größen an.** Mit `"fluid": true` schreibt WordPress
sie um — `base`, die 15px-Basis, auf der die gesamte B2B-Oberfläche steht:

```
base  0.9375rem -> clamp(0.875rem, 0.875rem + ((1vw - 0.2rem) * 0.063), 0.938rem)
lg    1.0625rem -> clamp(0.875rem, …, 1.063rem)
3xl   1.875rem  -> clamp(1.185rem, …, 1.875rem)
```

Auf 390px Breite sind das **14,0px statt 15px** für den Fließtext, 14px statt 17px für `lg`, 19px
statt 30px für `3xl`. Der Shop verschöbe sich auf jedem Telefon. Zurückzuhalten wäre das nur mit
`"fluid": false` an jeder der acht bestehenden Größen — acht Ausnahmen, damit eine Regel gilt, und
eine Falle für die neunte, die jemand später hinzufügt.

**Zweitens: die Maschine kann den Entwurf nicht sagen.** Ihr Bezugsrahmen ist fest 320–1600px. Für
die `h1` mit `min: 2.4rem`, `max: 3.9rem` liefert sie:

```
clamp(2.4rem, 2.4rem + ((1vw - 0.2rem) * 1.5), 3.9rem)   ->  1440px: 55,2px
```

Der Entwurf will dort 62,4px. Der Grund ist nicht die Formel, sondern der Rahmen: die `h1` des
Entwurfs ist bei **1248px** ausgewachsen, die Kennzahl bei **1280px**, die `h2` bei **1300px** — und
ihre unteren Knicke liegen bei 768px, 880px und 850px. Drei Bänder. `minViewportWidth` und
`maxViewportWidth` gibt es nur **einmal**, global; ein Rahmen, der einer der drei Größen passt, ist
für die beiden anderen falsch.

Deshalb: `settings.typography.fluid` bleibt `false`, und die Fluidität steht als `clamp()` im Wert.
Bei `false` fasst WordPress die Werte gar nicht an — sie stehen unverändert im generierten
Stylesheet. Die acht bestehenden Größen können sich damit nicht verschieben, weil an ihnen nichts
gerechnet wird.

### Was diese Skala noch nicht tut

Sie ist **Vorrat**, keine Zuweisung. `styles.elements.heading` bleibt unberührt: eine `h1` global auf
`6xl` zu setzen träfe auch die `h1` des Sortiments und jede `h2` der Filtergruppen — die sind
Bedienbeschriftungen und keine Drucksache (dieselbe Grenze zieht schon die Schriftfamilien-Regel in
`style.css`). Welcher Block welche Größe trägt, entscheidet die Seite über
`has-4-xl-font-size` … `has-6-xl-font-size`. **Achtung beim Slug:** WordPress entstellt `4xl` zu
`4-xl`, wie schon bei `3xl` → `has-3-xl-font-size`.

## Die zwei zuweisbaren Seitenvorlagen

Neben `templates/page.html` — der Vorgabe für jede Seite — stehen zwei Vorlagen in
`customTemplates`. Der Redakteur wählt sie im Seitenattribut „Vorlage"; ein Skript setzt dasselbe
über das Meta `_wp_page_template`.

| Slug (`_wp_page_template`) | Titel im Editor | Wozu |
|---|---|---|
| `page-full-width` | Seite — volle Breite | Hebt die 960px des Inhaltsbereichs auf. Die Seite mit dem Kurzcode `[lotzwoo_b2b_shop]` läuft hierüber — ein Katalog in 960px ist unlesbar. |
| `page-no-title` | Seite — ohne Titelzeile | Rendert **keinen** `post_title`. Für Seiten, deren Überschrift im Inhalt steht. |

`page-no-title` ist die Umsetzung von **E1**: die H1 lebt im Inhalt, `post_title` bleibt Verwaltungs-
und Menütitel. Ohne sie steht die Überschrift auf redaktionellen Seiten doppelt — „Kontakt" über
einem Absatz „Kontakt" —, und eine Startseite heißt „Startseite" statt das zu sagen, wofür sie da
ist.

**`post-title` aus `page.html` zu entfernen wäre der falsche Weg** und ist deshalb nicht geschehen:
dieselbe Vorlage trägt die Sortimentsseite und das gesamte Konto — alle neun WooCommerce-Endpunkte
plus `nachbestellen`. Die bekämen dann gar keine Überschrift mehr. Eine zuweisbare Vorlage trifft
genau die Seiten, die es angeht.

Beide Vorlagen schreiben ihren Slug als Body-Klasse (`lotzwoo-page-full-width`,
`lotzwoo-page-no-title`) — der `body_class`-Filter in `functions.php` leitet den Namen aus dem Slug
ab. Nur die erste wird heute in `style.css` gelesen; die zweite ist der Haken, den ein Kindtheme
oder ein Site-Plugin braucht, wenn eine Kundenseite doch einmal anders aussehen soll.

## Der Blockname, der zwei Repos verbindet

```
lotzwoo/header-slot
```

`parts/header.html` enthält diesen Platzhalter-Block, den **dieses Theme registriert und leer
rendert** und den **das Plugin per `render_block`-Filter füllt** (Schnellsuche, Favoriten-Zähler,
Kundenchip). Ohne Plugin rendert der Kopf leer statt kaputt.

| | |
|---|---|
| Blockname | `lotzwoo/header-slot` |
| Registrierung | `blocks/header-slot/block.json`, `render_callback` gibt `''` zurück |
| Editor-Seite | `blocks/header-slot/index.js`, ohne Bauschritt |
| Ort im Markup | `parts/header.html`, erstes Kind von `.lotzwoo-header-actions`, links von Konto-Symbol und Mini-Warenkorb |
| Haken im Plugin | `add_filter('render_block_lotzwoo/header-slot', …)` — mit Schrägstrich, wie der Blockname |

Der Rückgabewert des Themes ist die leere Zeichenkette und **kein leeres `<div>`**: die
Aktionsgruppe ist ein Flex-Container mit `blockGap`, und ein leerer Kasten darin schöbe die
Nachbarn um eine Lücke.

Der Blockname ist die einzige harte Kopplung zwischen Theme- und Plugin-Repo. Er steht deshalb in
beiden READMEs. Wer ihn ändert, ändert beide.

## Flächen, die dieses Theme abschaltet

Ein B2B-Portal ist kein Blog. Das Theme schaltet drei Flächen ab, die WordPress sonst bedient:

| Fläche | Zustand | schaltbar? |
|---|---|---|
| Autorenarchiv (`/author/…`, `?author=1`) | 404 | **nein** — zählt Benutzernamen auf |
| Datumsarchiv (`/2026/08/`, `?m=202608`) | 404 | **nein** |
| Website-Suche ohne `post_type=product` | 404 | **ja**, siehe unten |

Die Produktsuche bleibt und bekommt `templates/product-search-results.html`.

**Ein Kunde mit redaktionellen Inhalten holt sich die Website-Suche zurück:**

```php
add_filter('lotzwoo_theme_site_search_enabled', '__return_true');
```

Standardwert ist `false`. Der Filter gehört ins Site-Plugin des Kunden, nicht ins Kindtheme — dort
liegen nach AD-8 nur `style.css` und `theme.json`, kein PHP.

Ein eigenes Template braucht der Rückweg nicht: `templates/index.html` trägt eine Beitragsschleife
mit `inherit`, die auch eine Suche bedient. `search.html`, `home.html` und `archive.html` entstehen,
wenn ein Kunde redaktionelle Inhalte wirklich mitbringt.

Nicht abgeschaltet und deshalb hier nicht aufgeführt: Beitragsindex, Kategorie- und
Schlagwortarchiv. Sie laufen heute schon über `templates/index.html` — unauffällig, aber
funktionsfähig.

## Wo die Entscheidungen stehen

Karten, Architekturentscheidungen (`AD-*`) und Befunde liegen im Plugin-Repo
[`somesunnymind/lotzapp-for-woocommerce`](https://github.com/somesunnymind/lotzapp-for-woocommerce)
unter `.hermes/wayfinder/` und `docs/`. Hier liegt kein `docs/` und kein `.hermes/` — ein Theme-Repo
ist kein Planungsort.

Der Zuschnitt dieses Repos ist Ticket 06 der Karte `theme-templates-und-repo`.
