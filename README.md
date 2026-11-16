# LotzApp B2B Theme (Basis)

Minimales Block-Theme für B2B-Großhandelsshops auf WooCommerce. Es trägt das Design-System in
`theme.json` und sonst so wenig wie möglich: Kopfbereich, Fußbereich, zwei zuweisbare
Seitenvorlagen. **Alles Kundenspezifische gehört in ein Kindtheme**, je Kunde eines.

Theme-Slug: `lotzwoo-theme-base`. Kindthemes hängen per `Template: lotzwoo-theme-base` daran; der
Slug ändert sich deshalb nie.

## Die Arbeitsteilung

Das Plugin [LotzApp for WooCommerce](https://github.com/LotzApp/LotzWoo) besitzt
die B2B-Oberflächen — Sortiment, Warenkorb-Logik, Konto-Endpunkte — und bringt sein eigenes
Design-System mit (`b2b-tokens.css`, Custom Properties `--lwb-*`). Dieses Theme liefert die Hülle
und übersetzt in `style.css` die `--wp--preset--*` aus `theme.json` in die `--lwb-*` des Plugins.

**Die Abhängigkeit steht im Theme, nie im Plugin.** Das Theme läuft ohne Plugin: die Slots rendern
leer, der Weg ins Sortiment fällt auf `/` zurück. Wer das Theme wechselt, bekommt die
Standardwerte des Plugins statt einer kaputten Oberfläche — jedes `var()` in der Token-Brücke
trägt denselben Wert als Rückfall, den das Plugin ohnehin hat.

## Installation

1. ZIP `lotzwoo-theme-base.zip` aus dem neuesten
   [Release](https://github.com/LotzApp/LotzWooThemeBase/releases/latest) unter *Design → Themes →
   Theme hinzufügen → Theme hochladen* installieren.
2. Ein Kindtheme des Kunden installieren und aktivieren (siehe unten). Ohne Kindtheme läuft das
   Theme auch, aber Menü und Fußlinks bleiben leer.
3. Optional: das Plugin LotzApp for WooCommerce. Erst damit füllen sich die Slots.

Voraussetzungen: WordPress 6.6, PHP 8.1. WooCommerce ist nicht Pflicht, aber der Zweck.

## Kindtheme

Ein Kindtheme trägt nur `style.css`, `theme.json` und zwei Parts, kein PHP:

| Datei | Inhalt |
|---|---|
| `style.css` | Header mit `Template: lotzwoo-theme-base`, eigene Regeln |
| `theme.json` | Farben, Schriften, Abstände des Kunden |
| `parts/navigation.html` | das Hauptmenü des Kunden |
| `parts/footer-links.html` | die Linkspalte im Fuß |
| `mail-angaben.json` | optional: Logo, Fußzeile und Schrift der Mails (siehe unten) |

Die beiden Parts sind in der Basis **leer**: die Seitenstruktur eines Kunden gehört nicht hierher.
Eine generische Seitenliste zöge alle Seiten aus der Datenbank in die Kopfzeile, auch „Warenkorb"
und „Kasse". `scripts/check-frontend.php` warnt, wenn ein aktives Kindtheme seinen Part nicht
mitbringt.

Der Rahmen drumherum — Logo, Slots, Konto, Warenkorb, der Knopf „Zum Sortiment", die rechte
Spalte des Fußes — bleibt in der Basis und verbessert sich damit für alle Kunden zugleich.

**Ein Kindtheme, das die Schriftfamilie `sans` neu definiert, muss das `fontFace` mitbringen** —
gleichnamige Preset-Listen ersetzen die des Elternthemes ganz. `file:./assets/fonts/inter-var.woff2`
darf dort stehen bleiben: WordPress sucht die Datei zuerst im Kind, dann im Eltern.

**Das Kindtheme aktualisiert sich nicht mit.** Der Kern führt jedes Stylesheet einzeln.

## Vertrag mit dem Plugin

Diese Namen verbinden Theme und Plugin. Wer einen ändert, bricht die andere Seite, ohne dass hier
etwas rot wird. Sie ändern sich deshalb nur gemeinsam.

| Name | Art | Theme | Plugin |
|---|---|---|---|
| `lotzwoo/header-slot` | Block | registriert, rendert `''`; steht in `parts/header.html`, Attribut `bereich` (`telefon`, `kunde`) | füllt per `add_filter('render_block_lotzwoo/header-slot', …)` |
| `lotzwoo/cart-slot` | Block | registriert, rendert `<div id="lotzwoo-cart-slot"></div>`; steht in `templates/page-cart.html` | — |
| `lotzwoo-cart-slot` | Element-ID | Ausgabe des `cart-slot` | `assets/js/b2b-cart.js` hängt die zurückgehaltenen Positionen hinein (`MOUNT_ID`) |
| `lotzwoo/context-slot` | Block | registriert, rendert `''`; steht in jeder Vorlage direkt hinter dem Kopf-Part, an der Kasse per Filter | füllt per `add_filter('render_block_lotzwoo/context-slot', …)` (Lieferort, Liefertermin) |
| `lotzwoo/sortiment-url` | Filter | fragt ihn für den Menüpunkt „Shop" und jeden Knopf mit Klasse `lotzwoo-shop-link`; Rückfall `/` | liefert die Seite mit dem Kurzcode `[lotzwoo_b2b_shop]` |
| `.lotzwoo-b2b-shop`, `.lotzwoo-reorder`, `.lotzwoo-heldback` | CSS-Wurzeln | Token-Brücke `--wp--preset--*` → `--lwb-*` in `style.css` | setzt die Wurzeln ins Markup |

Dazu einige Namen im Kopf, die das Plugin setzt und das Theme liest: `data-lotzwoo-customer`
(Konto-Chip), `data-lotzwoo-headerphone` (Bestellannahme), `lotzwoo-headerphone__ico`,
`lotzwoo-headerphone__num`.

### Warum drei Slots mit zwei Mechanismen

- **Kopf und Kontextleiste** werden serverseitig gefüllt. Das Theme rendert die **leere
  Zeichenkette** und kein leeres `<div>`: im Flex-Container des Kopfes schöbe ein leerer Kasten die
  Nachbarn um eine Lücke, und auf Seiten ohne B2B-Bezug bliebe sonst ein leeres Element stehen.
  Welche Seiten die Kontextleiste tragen, entscheidet das Plugin.
- **Der Warenkorb** wird im Browser gefüllt. Die zurückgehaltenen Positionen liegen in der Store
  API; beim Rendern der Vorlage gibt es sie noch nicht. Das Theme liefert deshalb keinen Inhalt,
  sondern einen **Ort** — zwei Namen statt einem: Blockname und Element-ID. Ohne Plugin bleibt das
  `div` leer und wird per `:empty` ausgeblendet.
- **Die Kontextleiste steht in den Vorlagen, nicht im Kopf-Part.** Sie klebt beim Scrollen, und
  `position: sticky` klebt nur in seinem Container. WordPress legt um jeden Template-Part ein
  `<header>`, das genau so hoch ist wie der Kopf. Außerhalb davon ist der Container die ganze Seite.

Das eigene `templates/page-cart.html` führt den Cart-Block **inline** statt über
`wp:post-content`. Folgen: Woos innere Blockstruktur des Warenkorbs ist damit festgeschrieben;
ändert Woo sie, fehlt hier, was Woo neu hineinlegt. Der Inhalt der Warenkorb-*Seite* wird nicht
gerendert. Das Produktgitter des leeren Warenkorbs ist weg, weil es ein Katalog ohne
Freigabeprüfung wäre; an seiner Stelle steht der Knopf „Zum Sortiment".

Die Kasse behält die Vorlage von WooCommerce. Filter setzen dort nur den Kopf dieses Themes, die
Kontextleiste (nur lesend) und die Seitenüberschrift ein.

`scripts/check-frontend.php` prüft diese Kopplungen an einer laufenden Installation (siehe
*Prüfen*).

## Updates

Über den `Update URI`-Header (WordPress ≥ 6.1) und den Filter `update_themes_github.com` in
`inc/updater.php`. Der Kern übernimmt Versionsvergleich, `no_update`-Pflege und
Kollisionsschutz. Das Theme fragt `releases/latest` des Repos `LotzApp/LotzWooThemeBase` und
bietet das Asset `lotzwoo-theme-base.zip` an. Antworten werden sechs Stunden zwischengespeichert.

Das Repo ist **privat**. Jede Installation braucht deshalb in der `wp-config.php` einen GitHub-Token
mit Leserecht auf das Repo (fein granular: *Contents: Read-only*):

```php
define('LOTZWOO_GITHUB_TOKEN', 'github_pat_…');
```

Dieselbe Konstante liest das Plugin; ein Token mit Leserecht auf beide Repos genügt. Der Token
geht nur an die API-Adressen dieses Repos und wird bei der Weiterleitung zum Download-Speicher
wieder abgenommen. Ohne Token gibt es still kein Update. Würde das Repo öffentlich, ginge es auch
ohne Token.

Zwei Dinge, die leicht falsch verstanden werden:

- Die URL im Header wird vom Kern **nie abgerufen**. Sie ist Kennung, nicht Endpunkt.
- `update_themes_github.com` ist ein **geteilter** Namensraum. Der Callback prüft deshalb zuerst
  Slug und `Update URI` auf Gleichheit.

## Prüfen

```bash
npm ci
npm run check
php scripts/check-updater.php
```

Ohne WordPress: `theme.json` gegen das **versionierte** Kernschema, die Klammer zwischen
`theme.json`-Version und `Requires at least`, Header-Vollständigkeit, Blockmarkup, die Sperrliste
(`scripts/paket-sperrliste.txt`: keine Kunden- und Personennamen, Serverpfade, Arbeitskürzel oder
Datumsangaben) und der Updater. Die CI führt dazu `php -l` auf allen PHP-Dateien aus.

Kein Theme Check per `wp-env` — das fährt ein ganzes WordPress in Docker hoch, um Regeln zu
prüfen, die für ein Verzeichnis gelten, in das dieses Theme nie eingereicht wird.

An einer laufenden Installation, nicht in der CI:

```bash
wp --path=/pfad/zu/wordpress eval-file scripts/check-frontend.php
wp --path=/pfad/zu/wordpress eval-file scripts/check-mail-angaben.php [KINDTHEME-VERZEICHNIS]
```

## Ausliefern ohne Release

```bash
scripts/deploy-theme.sh /pfad/zu/wordpress/wp-content/themes
```

Das Ziel ist Pflicht. Ausgeliefert wird der verfolgte Baum von `HEAD` ohne die
`export-ignore`-Pfade aus `.gitattributes`, in `<themes>/lotzwoo-theme-base`. Das Skript bricht bei
unsauberer Arbeitskopie ab, prüft danach jede Datei per `cmp` und meldet Waisen — `git archive`
überschreibt, löscht aber nie. Ist `wp` vorhanden, läuft danach `check-frontend.php`.

## Versionieren und veröffentlichen

Eine eigene Semver-Reihe, unabhängig von den Kindthemes.

- **Erhöht wird**, wenn sich eine Datei ändert, die im ZIP landet — nicht bei jedem Commit. Die
  Erhöhung gehört in den Release-Commit (`Version:` in `style.css`).
- **`1.0.0`** ist erreicht, wenn jede Seite des B2B-Kaufwegs und des WordPress-Grundgerüsts ihr
  eigenes Block-Template hat und keine ungewollt auf einen WooCommerce- oder Core-Fallback fällt.

Ein Release entsteht durch einen Tag `X.Y.Z` (kein `v`-Präfix). Der Workflow
`.github/workflows/release.yml` prüft: Tag gleich `Version:`-Header, das ZIP erfüllt
`Theme_Upgrader::check_package()` (genau ein Ordner `lotzwoo-theme-base/`), und das ZIP besteht
die Sperrliste. Dann hängt er `lotzwoo-theme-base.zip` an das Release.

**Ein Tag ist die Auslieferung an Kunden.** Jede Installation sieht ihn bei der nächsten
Update-Prüfung.

## Die Hausschrift — Inter, selbst ausgeliefert

`assets/fonts/inter-var.woff2` ist Inter als variable Schrift (Gewicht 100–900, Achse `slnt`),
dieselbe Datei, die WooCommerce unter `assets/fonts/Inter-VariableFont_slnt,wght.woff2` mitbringt.
Eingebunden über `fontFace` in `theme.json`, ohne externe Quelle. Ohne sie zeigte jede Maschine
ihre Ersatzschrift, und Knopfbreiten wichen ab. Lizenz: SIL Open Font License 1.1,
`assets/fonts/OFL.txt`.

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
Filtergruppen, der Fließtext. Sie ist fest — eine Bestellmaske, deren Zeilenhöhe mit der
Fensterbreite wandert, ist an jeder Breite anders zu treffen.

Die obere Hälfte ist die Skala der **Drucksache**: Seitentitel (`6xl`), Kennzahlen (`5xl`),
Abschnittsüberschriften (`4xl`).

### Warum eigene `clamp()` und nicht `settings.typography.fluid`

**Erstens: der Schalter greift die bestehenden Größen an.** Mit `"fluid": true` schreibt WordPress
sie um. Auf 390px Breite wären das 14px statt 15px für den Fließtext (`base`), 14px statt 17px für
`lg`, 19px statt 30px für `3xl` — der Shop verschöbe sich auf jedem Telefon. Zurückzuhalten wäre
das nur mit `"fluid": false` an jeder bestehenden Größe, und die nächste neue Größe wäre eine Falle.

**Zweitens: die Maschine kann den Entwurf nicht sagen.** Ihr Bezugsrahmen ist fest 320–1600px,
global. Die drei redaktionellen Größen sind aber bei verschiedenen Breiten ausgewachsen; ein
Rahmen, der einer passt, ist für die beiden anderen falsch.

Deshalb bleibt `settings.typography.fluid` auf `false`, und die Fluidität steht als `clamp()` im
Wert. Bei `false` fasst WordPress die Werte gar nicht an.

### Was diese Skala nicht tut

Sie ist **Vorrat**, keine Zuweisung. `styles.elements.heading` bleibt unberührt: eine `h1` global
auf `6xl` zu setzen träfe auch die `h1` des Sortiments und jede `h2` der Filtergruppen. Welcher
Block welche Größe trägt, entscheidet die Seite über `has-4-xl-font-size` … `has-6-xl-font-size`.
**Achtung beim Slug:** WordPress macht aus `4xl` ein `4-xl`, wie bei `3xl` → `has-3-xl-font-size`.

## Die zwei zuweisbaren Seitenvorlagen

Neben `templates/page.html` — der Vorgabe für jede Seite — stehen zwei Vorlagen in
`customTemplates`. Der Redakteur wählt sie im Seitenattribut „Vorlage"; ein Skript setzt dasselbe
über das Meta `_wp_page_template`.

| Slug (`_wp_page_template`) | Titel im Editor | Wozu |
|---|---|---|
| `page-full-width` | Seite — volle Breite | Hebt die 960px des Inhaltsbereichs auf. Für die Seite mit dem Kurzcode `[lotzwoo_b2b_shop]` — ein Katalog in 960px ist unlesbar. |
| `page-no-title` | Seite — ohne Titelzeile | Rendert **keinen** `post_title`. Für Seiten, deren Überschrift im Inhalt steht. |

Ohne `page-no-title` stünde die Überschrift auf redaktionellen Seiten doppelt — „Kontakt" über
einem Absatz „Kontakt". `post-title` aus `page.html` zu entfernen wäre falsch: dieselbe Vorlage
trägt die Sortimentsseite und das ganze Kundenkonto, die dann keine Überschrift mehr hätten.

Beide Vorlagen schreiben ihren Slug als Body-Klasse (`lotzwoo-page-full-width`,
`lotzwoo-page-no-title`).

## Die Block-Styles

Wiederkehrende Bausteine als `register_block_style()`: die Website schreibt nur die Klasse, das
Aussehen gehört hierher. Eine Palettenänderung hat damit eine Stelle, im Theme.

| Klasse | Blocktyp | Was sie trägt |
|---|---|---|
| `is-style-label` | `core/paragraph` | Versalien, `brand-500`, Grad `sm`, Gewicht 600, Sperrung `.13em` |
| `is-style-kennzahl` | `core/paragraph` | Grad `5-xl`, Serifenschrift des Kindthemes, Gewicht 500 |
| `is-style-zweispaltig` | `core/list` | zwei Spalten |
| `is-style-karte` | `core/group` | Rahmen 1px `ink-200`, Polsterung `spacing--50` |
| `is-style-abgesetzt` | `core/group` | Polsterung `spacing--70` oben/unten, `spacing--50` seitlich |
| `is-style-empfaenger` | `core/group` | Kante 3px `ink-200` am Textanfang, Polsterung `40`/`50` |
| `is-style-zeitleiste-schritt` | `core/group` | ein Schritt einer Zeitleiste |
| `is-style-lesebreite` | `core/group` | begrenzte Lesebreite |

- **Der Hintergrund von `abgesetzt` bleibt Attribut.** Er wechselt je Seite und ist damit Inhalt.
- **`karte` und `empfaenger` sind innen mehrteilig** (`h3` + `p`, bzw. zwei Absätze). Ein
  Block-Style trägt Aussehen, nicht Zusammensetzung.
- **Klasse *statt* Attribut, nie zusätzlich.** Bleiben Inline-Styles stehen, gewinnen sie.

Die Namen sind eine Kopplung zu den Inhalten der Website: wer hier umbenennt, muss die Seiten
nachziehen. Im Editor sichtbar werden die Styles über `enqueue_block_assets` (nicht
`add_editor_style()`, das bei aktivem Kindtheme nur dessen Stylesheet lädt).

## Die Mail-Angaben des Kindthemes

Ein Kindtheme liefert Logo, Fußzeile und Schrift der WooCommerce-Mails als Daten, ohne PHP. Die
Datei `mail-angaben.json` liegt neben seiner `theme.json`:

```json
{
  "header_image": "assets/email/logo.png",
  "footer_text": "Firma<br />Adresse",
  "font_family": "Helvetica"
}
```

| Schlüssel | Woo-Feld | Regel |
|---|---|---|
| `header_image` | `woocommerce_email_header_image` | PNG im Kindtheme, Pfad relativ dazu. Bis 400 px breit: Outlook ignoriert die CSS-Breite |
| `footer_text` | `woocommerce_email_footer_text` | Woos Platzhalter gelten (`{site_title}`, `{site_url}`, `{store_address}`, `{store_email}`) |
| `font_family` | `woocommerce_email_font_family` | Ein Eintrag aus Woos Schriftauswahl |

**Was im Woo-Feld steht, gewinnt.** Das Kindtheme füllt nur ein leeres Feld. Ein neu installiertes
WooCommerce legt Fußzeile und Schrift mit eigenen Standardwerten an; dort greift das Kindtheme
erst, wenn die Felder geleert sind:

```bash
wp option update woocommerce_email_footer_text ''
```

Leeren heißt `update`, nicht `delete`: eine leere Zeile übersteht ein Woo-Update, eine fehlende kann
Woo dabei mit seinem Standard neu anlegen.

Das Formular zeigt den Wert des Kindthemes. Unverändert gespeichert wird daraus keine Eingabe, die
Zeile bleibt leer. Zwei Folgen: Ein geleertes Feld, auch „Logo entfernen", bringt den Wert des
Kindthemes zurück; „keine Fußzeile" gibt es damit nicht. Und wer genau den Wert des Kindthemes
eingibt, bindet sich nicht daran. Die Gründe stehen in `inc/mail-angaben.php`.

## Flächen, die dieses Theme abschaltet

Ein B2B-Portal ist kein Blog:

| Fläche | Zustand | schaltbar? |
|---|---|---|
| Autorenarchiv (`/author/…`, `?author=1`) | 404 | **nein** — zählt Benutzernamen auf |
| Datumsarchiv (`/JJJJ/MM/`, `?m=JJJJMM`) | 404 | **nein** |
| Website-Suche ohne `post_type=product` | 404 | **ja**, siehe unten |

Die Produktsuche bleibt und bekommt `templates/product-search-results.html`.

**Ein Kunde mit redaktionellen Inhalten holt sich die Website-Suche zurück:**

```php
add_filter('lotzwoo_theme_site_search_enabled', '__return_true');
```

Der Filter gehört in ein Site-Plugin des Kunden, nicht ins Kindtheme — das trägt kein PHP.

## Lizenz

GPL-2.0-or-later, siehe `LICENSE`. Die Schrift Inter steht unter der SIL Open Font License 1.1
(`assets/fonts/OFL.txt`). Symbole aus [Lucide](https://lucide.dev), ISC-Lizenz.
