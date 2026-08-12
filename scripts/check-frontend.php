<?php

/**
 * Prüft, dass keine Seite ungewollt auf einen Fallback fällt.
 *
 *     sg www-data -c "wp --path=/var/www/b2b eval-file <theme>/scripts/check-frontend.php"
 *
 * Das ist die zweite Hälfte der Destination der Karte `theme-templates-und-repo`.
 * Die erste — jede Seite hat ihr Template — ist gebaut. Diese Datei ist der
 * Nachweis, und sie ersetzt sieben Messungen, die verstreut in Ticket-Antworten
 * stehen und die niemand wiederholen kann, ohne sie dort zusammenzusuchen.
 *
 * ## Warum ein Skript und keine Liste
 *
 * Eine Liste zum Abhaken verfällt still. Ein WooCommerce-Update, das ein
 * Template registriert, ändert keine Markdown-Zeile — und genau das ist der
 * Fehlerfall, um den es geht: Woos Registry gewinnt gegen eine Theme-Datei, die
 * es nicht gibt, und niemand merkt es.
 *
 * ## Warum es hier liegt und nicht in der CI
 *
 * `scripts/` ist in `.gitattributes` `export-ignore` und erreicht keinen
 * Kundenserver; daneben liegt mit `deploy-theme.sh` bereits ein Werkzeug, das
 * gegen eine laufende Installation arbeitet. Ticket 06 hat *WordPress in der
 * CI* ausgeschlossen (kein `wp-env`, kein Docker) — nicht ein Skript, das
 * jemand gegen eine echte Installation laufen lässt. Die CI prüft weiter fünf
 * Dinge ohne WordPress; diese Datei prüft das eine, das ohne WordPress nicht
 * zu prüfen ist.
 *
 * ## Zwei Ebenen, ungleich gewichtet
 *
 * Das **Rückgrat** ist die Auflösungsebene: `get_block_templates()` gegen die
 * Landkarte unten. Sie trifft den Fehlerfall genau, braucht keine Terme, keinen
 * gefüllten Warenkorb und keine Anmeldung.
 *
 * Dazu ein paar **HTTP-Abrufe** für das, was erst beim Rendern sichtbar wird:
 * hat die Seite Kopf und Fuß, steht kein Woo-Katalog darin. Die Auflösungsebene
 * hätte den fehlenden Kassen-Kopf aus Ticket 09 nie gefunden.
 *
 * ## Was es nicht tut
 *
 * Es schreibt nicht. Kein Warenkorb wird gefüllt, keine Bestellung angelegt,
 * keine Option gesetzt — dieselbe Linie, die `tests/runtime-b2b.php` im
 * Plugin-Repo zieht. Die Kasse wird deshalb auf der Auflösungsebene geprüft
 * und nicht per HTTP: mit leerem Warenkorb antwortet sie 302 auf `/cart/`, und
 * ein Warenkorb, den eine Prüfung füllt, ist eine Schreiboperation.
 *
 * ## Fail-closed
 *
 * Fehlt die Grundlage — kein WooCommerce, kein aktives `lotzwoo-theme-*` —,
 * verweigert das Skript die Arbeit. Eine Prüfung, die grün meldet, weil ihr
 * nichts widersprochen hat, ist schlimmer als keine.
 */

if (!defined('ABSPATH')) {
    fwrite(STDERR, "Nur über wp eval-file.\n");
    exit(1);
}

/**
 * Die Landkarte.
 *
 * Jede Zeile trägt ihre Begründung mit. Das ist der Teil, der zählt: In der
 * Ausgabe steht dann nicht „single-product kommt vom Plugin", sondern warum das
 * richtig ist. Ein Inventar, das seine eigenen Entscheidungen mitführt — das,
 * was Ticket 01 von Hand war.
 *
 * `theme`  — eine Datei dieses Themes (oder eines Kindthemes) muss gewinnen.
 * `plugin` — WooCommerce liefert, und zwar mit Absicht.
 */
const LANDKARTE = [
    '404' => ['theme', 'Grundgerüst: eigene 404-Seite mit Kopf und Fuß'],
    'archive-product' => ['theme', 'Ticket 12: kein Katalog, Hinweis aufs Sortiment. Trägt auch Kategorie, Schlagwort und Marke — Woo liefert für die drei keine eigene Datei'],
    'coming-soon' => ['plugin', 'Woos Vor-dem-Start-Modus; kein Teil des Kaufwegs und keine Seite, die ein Kunde je sieht'],
    'index' => ['theme', 'Grundgerüst: der Rückfall des Kerns gehört dem Theme'],
    'order-confirmation' => ['plugin', 'Ticket 05: kein eigenes Template — die Bestätigung ist Woos Store-API-Fläche'],
    'page' => ['theme', 'Grundgerüst, und zugleich das gesamte Konto: alle neun Woo-Endpunkte plus `nachbestellen` laufen hierüber (Ticket 01)'],
    'page-cart' => ['theme', 'Ticket 15: doch ein eigenes Template — der Warenkorb-Slot muss zwischen Positionen und Summen stehen, und dafür führt die Datei den Cart-Block inline statt über `wp:post-content`'],
    'page-checkout' => ['plugin', 'AD-1: der Austritt aus der Store API kostet die Zahlungsarten-Registrierung. Die Hülle kommt trotzdem von hier — siehe Kopplung 1'],
    'page-full-width' => ['theme', 'Das Sortiment (Seite mit dem Kurzcode) läuft hierüber'],
    'page-no-title' => ['theme', 'E1: die Überschrift lebt im Inhalt. Für redaktionelle Seiten, die ihre H1 selbst mitbringen — `page.html` behält `post-title`, weil es auch das Sortiment trägt'],
    'product-search-results' => ['theme', 'Ticket 12: die Produktsuche zeigt den Hinweis, keinen Katalog'],
    'single' => ['theme', 'Grundgerüst'],
    'single-product' => ['plugin', 'AD-10: die Produktseite bekommt kein eigenes Template'],
    'taxonomy-product_attribute' => ['theme', 'Ticket 12: braucht eine eigene Datei, weil Woo hierfür — anders als für Kategorie und Schlagwort — selbst eine mitliefert'],
];

/** Die beiden Teile, die auf jeder Seite dieses Themes stehen. */
const TEILE = [
    'header' => 'Kopfbereich: Navigation, Einhängestelle, Konto, Warenkorb',
    'footer' => 'Fußbereich: Rechtsseiten und Copyright',
];

/**
 * Was dem Kindtheme seit AD-11 legitim gehört.
 *
 * Vor dem 2026-08-12 war die Antwort „nichts": jede Datei im Kindtheme war ein
 * Verstoß gegen AD-8, und diese Prüfung meldete sie pauschal als Warnung. Seit
 * AD-11 ist die Seitenstruktur eines Kunden ausdrücklich dort zuhause — und
 * eine Prüfung, die genau das jedes Mal anmahnt, wird nach dem dritten Lauf
 * nicht mehr gelesen.
 *
 * Die Liste ist deshalb **abschließend**, nicht beispielhaft. Alles, was nicht
 * darauf steht, warnt weiter: ein `templates/page.html` im Kindtheme fehlt beim
 * nächsten Kunden, und daran hat AD-11 nichts geändert.
 *
 * Der Schlüssel ist der Slug, der Wert die Begründung, die in der Ausgabe
 * mitläuft — dieselbe Form wie in der Landkarte oben und aus demselben Grund.
 */
const KIND_EIGEN = [
    'navigation' => 'AD-11: die Menüführung ist die Seitenstruktur des Kunden',
    'footer-links' => 'AD-11: welche Rechtsseiten es gibt, entscheidet der Kunde',
];

/**
 * Die Seiten, die wirklich abgeholt werden.
 *
 * Bewusst kurz. Jede zusätzliche URL kostet einen Roundtrip und prüft meist
 * dasselbe noch einmal; was hier steht, deckt je eine Auflösungsart ab.
 */
const SIGNATUREN = [
    'kopf' => '<header class="wp-block-template-part">',
    'fuss' => '<footer class="wp-block-template-part">',
];

/** Woran ein Woo-Katalog zu erkennen ist, wo keiner stehen darf. */
const KATALOG_SPUREN = [
    'wp-block-woocommerce-product-template',
    'woocommerce-loop-product__link',
    'wc-block-product-template',
];

$fehler = [];
$warnungen = [];
$hinweise = 0;
$geprueft = 0;

$zeile = static function (string $art, string $text) use (&$fehler, &$warnungen, &$hinweise, &$geprueft): void {
    $geprueft++;

    switch ($art) {
        case 'fehler':
            $fehler[] = $text;
            echo "FEHLER   {$text}\n";
            break;
        case 'warnung':
            $warnungen[] = $text;
            echo "WARNUNG  {$text}\n";
            break;
        case 'hinweis':
            $hinweise++;
            echo "hinweis  {$text}\n";
            break;
        default:
            echo "ok       {$text}\n";
    }
};

$abschnitt = static function (string $titel): void {
    echo "\n== {$titel} ==\n";
};

// ---------------------------------------------------------------------------
// Fail-closed: die Grundlage, ohne die jede Aussage unten wertlos wäre.
// ---------------------------------------------------------------------------

$abbruch = static function (string $grund): void {
    fwrite(STDERR, "\nABBRUCH: {$grund}\n");
    fwrite(STDERR, "Diese Prüfung verweigert die Arbeit, statt grün zu melden, weil ihr nichts widersprochen hat.\n");
    exit(1);
};

if (!class_exists('WooCommerce')) {
    $abbruch('Kein WooCommerce. Die halbe Landkarte besteht aus Woo-Templates.');
}

if (!str_starts_with(get_template(), 'lotzwoo-theme-')) {
    $abbruch('Aktives Elterntheme ist "' . get_template() . '", nicht ein lotzwoo-theme-*.');
}

if (!wp_is_block_theme()) {
    $abbruch('Kein Block-Theme aktiv — es gäbe keine Block-Templates zu prüfen.');
}

echo "Prüfung der Frontend-Fläche\n";
echo "Theme:   " . get_stylesheet() . " (Elternteil: " . get_template() . ")\n";
echo "Adresse: " . home_url('/') . "\n";

// ---------------------------------------------------------------------------
// 1. Die Auflösungsebene — das Rückgrat.
// ---------------------------------------------------------------------------

$abschnitt('Templates');

$gefunden = [];

foreach (get_block_templates([], 'wp_template') as $template) {
    $gefunden[$template->slug] = $template;
}

foreach (LANDKARTE as $slug => [$erwartet, $grund]) {
    $template = $gefunden[$slug] ?? null;

    if ($template === null) {
        $zeile('fehler', "{$slug}: gar nicht aufgelöst — erwartet war `{$erwartet}` ({$grund})");
        continue;
    }

    // `custom` heißt: der Website-Editor hat eine Fassung in die Datenbank
    // geschrieben. Kein Fehler, aber der Rahmenbefund der Karte — „null
    // Templates in der Datenbank, jede Zeile kommt aus einer Datei" — gilt dann
    // nicht mehr, und wer das liest, soll es merken.
    if ($template->source === 'custom') {
        $zeile('warnung', "{$slug}: liegt in der **Datenbank**. Der Klon ist damit nicht mehr ohne Datenbank reproduzierbar");
        continue;
    }

    if ($template->source !== $erwartet) {
        $zeile('fehler', "{$slug}: kommt von `{$template->source}`, erwartet war `{$erwartet}` — {$grund}");
        continue;
    }

    if ($erwartet === 'plugin') {
        $zeile('hinweis', "{$slug}: Fallback auf WooCommerce, wie entschieden — {$grund}");
        continue;
    }

    // Welche Datei der Kette gewinnt, ist eine zweite, informative Frage. Ein
    // Kindtheme, das ein Template überschreibt, ist unerwünscht — aber es ist
    // kein *Fallback*, und das ist der Fehler, gegen den diese Prüfung antritt.
    //
    // „Unerwünscht" gilt seit AD-11 nicht mehr pauschal: was auf KIND_EIGEN
    // steht, gehört dorthin. Alles andere warnt weiter.
    $im_kind = get_stylesheet_directory() !== get_template_directory()
        && file_exists(get_stylesheet_directory() . "/templates/{$slug}.html");

    if ($im_kind && isset(KIND_EIGEN[$slug])) {
        $zeile('ok', "{$slug}: aus dem **Kindtheme**, wie vorgesehen — " . KIND_EIGEN[$slug]);
        continue;
    }

    if ($im_kind) {
        $zeile('warnung', "{$slug}: aus dem **Kindtheme**, steht aber nicht auf der Liste der kundeneigenen Slugs (AD-11) — ein Template dort fehlt beim nächsten Kunden");
        continue;
    }

    $zeile('ok', "{$slug}: aus dem Theme");
}

// Was aufgelöst wird, aber in der Landkarte fehlt. Genau der Fall, für den es
// ein Skript und keine Markdown-Liste ist: WooCommerce registriert im nächsten
// Update ein Template, von dem hier niemand weiß.
foreach ($gefunden as $slug => $template) {
    if (isset(LANDKARTE[$slug])) {
        continue;
    }

    $zeile('warnung', "{$slug}: nicht in der Landkarte — von `{$template->source}` aufgelöst. Neu seit dem letzten Mal; eintragen und entscheiden");
}

// ---------------------------------------------------------------------------
// 2. Die Teile und die zwei stillen Kopplungen.
// ---------------------------------------------------------------------------

$abschnitt('Kopf, Fuß und die Kopplungen nach außen');

$teile = [];

foreach (get_block_templates([], 'wp_template_part') as $part) {
    $teile[$part->theme . '//' . $part->slug] = $part;
}

foreach (TEILE as $slug => $zweck) {
    $part = $teile[get_stylesheet() . '//' . $slug] ?? null;

    if (!$part instanceof WP_Block_Template || $part->source !== 'theme') {
        $zeile('fehler', "parts/{$slug}.html: nicht aus dem Theme aufgelöst — {$zweck}");
        continue;
    }

    $zeile('ok', "parts/{$slug}.html: aus dem Theme");
}

/**
 * Kopplung 4: die zwei Parts, die dem Kundentheme gehören (AD-11).
 *
 * `parts/navigation.html` und `parts/footer-links.html` sind die einzige Stelle,
 * an der die Seitenstruktur eines Kunden steht. Das Basis-Theme liefert je eine
 * Vorgabe für den Fall, dass kein Kindtheme etwas mitbringt.
 *
 * Geprüft wird **nicht**, ob die Dateien existieren — das täte auch die
 * Auflösung oben. Geprüft wird, **wessen** Datei gewinnt, und zwar am
 * Verzeichnis statt am `theme`-Feld des Template-Objekts: das Feld trägt bei
 * datei-basierten Parts den Stylesheet-Namen, egal aus welchem der beiden
 * Verzeichnisse die Datei kam, und wäre hier eine Antwort, die immer stimmt.
 *
 * Der interessante Fall ist der **stille**: ein Kindtheme ist aktiv, bringt aber
 * keinen eigenen Part mit. Dann steht im Kopf die generische Seitenliste der
 * Basis, im Fuß gar nichts — die Seite ist heil, die Rechtslinks sind weg, und
 * niemand sucht danach. Genau dafür ist diese Prüfung da.
 */
$abschnitt('Die Parts des Kundenthemes (AD-11)');

$kind_aktiv = get_stylesheet_directory() !== get_template_directory();

foreach (KIND_EIGEN as $slug => $grund) {
    $part = $teile[get_stylesheet() . '//' . $slug] ?? null;

    if (!$part instanceof WP_Block_Template) {
        $zeile('fehler', "parts/{$slug}.html: gar nicht aufgelöst — der Kopf- bzw. Fußbereich zieht diesen Part, und er kommt aus keinem der beiden Themes ({$grund})");
        continue;
    }

    if ($part->source === 'custom') {
        $zeile('warnung', "parts/{$slug}.html: liegt in der **Datenbank**. Der Website-Editor hat eine Fassung gespeichert — die Datei im Kindtheme wird nicht mehr gelesen, und Ticket 13 gilt für diesen Part nicht mehr");
        continue;
    }

    $aus_kind = $kind_aktiv && file_exists(get_stylesheet_directory() . "/parts/{$slug}.html");

    if ($aus_kind) {
        $zeile('ok', "parts/{$slug}.html: aus dem **Kindtheme** — {$grund}");
        continue;
    }

    if ($kind_aktiv) {
        $zeile('warnung', "parts/{$slug}.html: ein Kindtheme ist aktiv, bringt diesen Part aber nicht mit — ausgeliefert wird die neutrale Vorgabe der Basis ({$grund})");
        continue;
    }

    $zeile('ok', "parts/{$slug}.html: die neutrale Vorgabe der Basis, kein Kindtheme aktiv — {$grund}");
}

// Die Gegenprobe zur Liste: ein Part im Kindtheme, der nicht darauf steht.
// Ohne diese Schleife wäre die Liste eine Erlaubnis ohne Grenze, und der Satz
// „das Kindtheme trägt nur Farben, Schrift, Logo und seine Seitenstruktur"
// stünde nur noch in einer Markdown-Datei.
foreach ($kind_aktiv ? (glob(get_stylesheet_directory() . '/parts/*.html') ?: []) : [] as $datei) {
    $slug = basename($datei, '.html');

    if (isset(KIND_EIGEN[$slug])) {
        continue;
    }

    $zeile('warnung', "parts/{$slug}.html: aus dem **Kindtheme**, steht aber nicht auf der Liste der kundeneigenen Slugs (AD-11) — ein Part dort erbt keine Verbesserung am Rahmen mehr");
}

/**
 * Kopplung 1: der Kassen-Kopf.
 *
 * Woos `page-checkout.html` zieht einen Part, der fest auf
 * `woocommerce/woocommerce` verdrahtet ist. Der Filter in `functions.php`
 * schiebt dort den eigenen Kopf unter. Benennt WooCommerce den Part um, trifft
 * die Kennung nicht mehr, der Filter tut nichts, und die Kasse hat wieder Woos
 * eigenen Kopf — sichtbar für den Kunden, unsichtbar für jede andere Prüfung.
 *
 * Verglichen wird der Inhalts-Hash gegen den eigenen Kopf. Gleichheit heißt:
 * es ist derselbe Part, nicht nur einer, der auch existiert.
 */
$kassenkopf = get_block_template('woocommerce/woocommerce//checkout-header', 'wp_template_part');
$eigenerkopf = get_block_template(get_stylesheet() . '//header', 'wp_template_part');

if (!$kassenkopf instanceof WP_Block_Template) {
    $zeile('fehler', 'Kassen-Kopf: `woocommerce/woocommerce//checkout-header` gibt es nicht mehr. WooCommerce hat den Part umbenannt — der Filter in functions.php trifft ins Leere (Ticket 09)');
} elseif (!$eigenerkopf instanceof WP_Block_Template) {
    $zeile('fehler', 'Kassen-Kopf: der eigene Kopf ist nicht auflösbar, es gibt nichts unterzuschieben');
} elseif (md5((string) $kassenkopf->content) !== md5((string) $eigenerkopf->content)) {
    $zeile('fehler', 'Kassen-Kopf: die Kasse bekommt **nicht** denselben Kopf wie der Rest. Der Filter aus Ticket 09 greift nicht');
} else {
    $zeile('ok', 'Kassen-Kopf: dieselbe Datei wie überall (Ticket 09)');
}

// Der Fuß auf der Kasse ist Absicht: Woos Kassen-Template hat gar keinen
// Fuß-Part, und einen hineinzubekommen hieße, das Template zu übernehmen —
// genau das, was AD-1 ausschließt. Geprüft wird deshalb, dass es so *bleibt*.
$kasse = $gefunden['page-checkout'] ?? null;

if ($kasse instanceof WP_Block_Template && str_contains((string) $kasse->content, '"slug":"footer"')) {
    $zeile('warnung', 'Kasse: Woos Template zieht inzwischen einen Fuß-Part. AD-1 ging davon aus, dass es keinen gibt — die Entscheidung ist neu zu prüfen');
} else {
    $zeile('ok', 'Kasse: weiterhin ohne Fuß, wie AD-1 es beschreibt');
}

/**
 * Kopplung 2: die Einhängestelle im Kopf.
 *
 * Das Theme registriert `lotzwoo/header-slot` und rendert die leere
 * Zeichenkette; das Plugin füllt ihn per `render_block`-Filter. Wer den Namen
 * auf einer der beiden Seiten ändert, bricht die andere, ohne dass irgendwo
 * etwas rot wird — die einzige Kopplung zwischen zwei Repos (Ticket 10).
 */
if (!WP_Block_Type_Registry::get_instance()->is_registered('lotzwoo/header-slot')) {
    $zeile('fehler', 'Einhängestelle: der Block `lotzwoo/header-slot` ist nicht registriert (Ticket 10)');
} elseif ($eigenerkopf instanceof WP_Block_Template
    && !str_contains((string) $eigenerkopf->content, 'wp:lotzwoo/header-slot')) {
    $zeile('fehler', 'Einhängestelle: der Block ist registriert, steht aber nicht mehr in parts/header.html. Das Plugin hat dann keinen Ort im Kopf');
} else {
    $zeile('ok', 'Einhängestelle: `lotzwoo/header-slot` registriert und im Kopf verbaut (Ticket 10)');
}

/**
 * Kopplung 3: der Anker im Warenkorb — und warum es hier **drei** Prüfungen sind.
 *
 * Der Kopf-Slot führt eine Zeichenkette: den Blocknamen. Der Warenkorb-Slot
 * führt zwei — den Blocknamen **und** die Div-ID `lotzwoo-cart-slot`, auf die
 * `MOUNT_ID` in `assets/js/b2b-cart.js` zeigt (Ticket 15, Frage 1 und 6). Zwei
 * Namen brauchen zwei Prüfungen, plus die, dass der Block überhaupt registriert
 * ist.
 *
 * Die dritte Prüfung ruft den `render_callback` auf, statt die Datei nach der
 * ID zu durchsuchen: die ID steht in PHP-Code, nicht in einem Template, und
 * eine Textsuche in `functions.php` fände sie auch in einem Kommentar.
 *
 * Wird eine der beiden Zeichenketten umbenannt, ohne die Gegenseite
 * nachzuziehen, bleibt die Seite **heil** — das JS legt sich sein eigenes Div
 * neben den Cart-Block, wie vor Ticket 15. Genau darum braucht es diese
 * Prüfung: das Symptom ist eine Sektion an der falschen Stelle, und die sieht
 * niemand, der sie nicht sucht.
 */
$slot_typ = WP_Block_Type_Registry::get_instance()->get_registered('lotzwoo/cart-slot');
$warenkorb_template = $gefunden['page-cart'] ?? null;

if (!$slot_typ instanceof WP_Block_Type) {
    $zeile('fehler', 'Warenkorb-Slot: der Block `lotzwoo/cart-slot` ist nicht registriert (Ticket 15)');
} else {
    $zeile('ok', 'Warenkorb-Slot: `lotzwoo/cart-slot` registriert (Ticket 15)');
}

if (!$warenkorb_template instanceof WP_Block_Template
    || !str_contains((string) $warenkorb_template->content, 'wp:lotzwoo/cart-slot')) {
    $zeile('fehler', 'Warenkorb-Slot: der Block steht nicht in `templates/page-cart.html`. Das Plugin hängt seine Sektion dann wieder unter den Bestellknopf (Posten 6 der Nachbarkarte)');
} else {
    $zeile('ok', 'Warenkorb-Slot: im Cart-Template verbaut, zwischen Positionen und Summen (Ticket 15, Frage 3)');
}

$slot_ausgabe = $slot_typ instanceof WP_Block_Type && is_callable($slot_typ->render_callback)
    ? (string) call_user_func($slot_typ->render_callback, [], '', null)
    : '';

if (!str_contains($slot_ausgabe, 'id="lotzwoo-cart-slot"')) {
    $zeile('fehler', 'Warenkorb-Slot: der `render_callback` liefert kein `id="lotzwoo-cart-slot"` — `MOUNT_ID` in b2b-cart.js findet den Anker nicht (Ticket 15, Frage 6)');
} else {
    $zeile('ok', 'Warenkorb-Slot: der `render_callback` liefert den Anker `id="lotzwoo-cart-slot"` — dieselbe Zeichenkette wie `MOUNT_ID` im Plugin');
}

// ---------------------------------------------------------------------------
// 3. Was der Kunde wirklich bekommt.
// ---------------------------------------------------------------------------

$abschnitt('Abgerufene Seiten');

/**
 * Eine gewöhnliche Inhaltsseite finden — keine der Woo-Systemseiten und nicht
 * die Startseite. Fest verdrahtet wäre falsch: dieses Skript läuft auf jeder
 * Installation, und `sample-page` gehört gelöscht.
 */
$system = array_filter([
    (int) get_option('woocommerce_shop_page_id'),
    (int) get_option('woocommerce_cart_page_id'),
    (int) get_option('woocommerce_checkout_page_id'),
    (int) get_option('woocommerce_myaccount_page_id'),
    (int) get_option('page_on_front'),
]);

$gewoehnliche = get_posts([
    'post_type' => 'page',
    'post_status' => 'publish',
    'posts_per_page' => 1,
    'exclude' => $system,
    'orderby' => 'ID',
    'order' => 'ASC',
]);

$abrufe = [
    ['/', 'Startseite — hier das Sortiment (page-full-width)', true],
    [wp_make_link_relative(wc_get_page_permalink('shop')), 'Woos Shop-Seite (archive-product)', false],
    ['/?s=apfel&post_type=product', 'Produktsuche (product-search-results)', false],
    [wp_make_link_relative(wc_get_page_permalink('cart')), 'Warenkorb (eigenes Template mit dem Slot; Ticket 15)', true],
    ['/diese-seite-gibt-es-nicht-' . substr(md5(get_stylesheet()), 0, 6) . '/', 'Eine Adresse ohne Seite (404)', true],
];

$kategorien = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => false, 'number' => 1]);

if (!is_wp_error($kategorien) && $kategorien !== []) {
    $abrufe[] = [
        wp_make_link_relative(get_term_link($kategorien[0])),
        'Produktkategorie „' . $kategorien[0]->name . '" (fällt auf archive-product)',
        false,
    ];
}

if ($gewoehnliche !== []) {
    $abrufe[] = [
        wp_make_link_relative(get_permalink($gewoehnliche[0])),
        'Eine gewöhnliche Seite („' . $gewoehnliche[0]->post_title . '", page)',
        true,
    ];
}

foreach ($abrufe as [$pfad, $zweck, $katalog_verboten]) {
    $antwort = wp_remote_get(home_url($pfad), ['timeout' => 20, 'redirection' => 3]);

    if (is_wp_error($antwort)) {
        $zeile('fehler', "{$pfad}: nicht abrufbar ({$antwort->get_error_message()}) — {$zweck}");
        continue;
    }

    $code = wp_remote_retrieve_response_code($antwort);
    $html = wp_remote_retrieve_body($antwort);

    // Ein 404 ist auf der 404-Adresse die richtige Antwort; überall sonst nicht.
    $erwarteter_code = str_contains($pfad, 'diese-seite-gibt-es-nicht') ? 404 : 200;

    if ($code !== $erwarteter_code) {
        $zeile('fehler', "{$pfad}: HTTP {$code}, erwartet {$erwarteter_code} — {$zweck}");
        continue;
    }

    $fehlend = [];

    foreach (SIGNATUREN as $name => $marke) {
        if (!str_contains($html, $marke)) {
            $fehlend[] = $name;
        }
    }

    if ($fehlend !== []) {
        $zeile('fehler', "{$pfad}: ohne " . implode(' und ', $fehlend) . " — {$zweck}");
        continue;
    }

    if ($katalog_verboten) {
        $zeile('ok', "{$pfad}: Kopf und Fuß da — {$zweck}");
        continue;
    }

    $spuren = array_values(array_filter(
        KATALOG_SPUREN,
        static fn (string $spur): bool => str_contains($html, $spur)
    ));

    if ($spuren !== []) {
        $zeile('fehler', "{$pfad}: zeigt einen Woo-Katalog ({$spuren[0]}) — {$zweck}. Das ist die Tür an der Eligibility-Prüfung vorbei, gegen die Ticket 12 gebaut wurde");
        continue;
    }

    $zeile('ok', "{$pfad}: Kopf und Fuß da, kein Katalog — {$zweck}");
}

// ---------------------------------------------------------------------------

echo "\n" . str_repeat('-', 70) . "\n";
printf(
    "%d geprüft — %d Fehler, %d Warnungen, %d erwartete Fallbacks\n",
    $geprueft,
    count($fehler),
    count($warnungen),
    $hinweise
);

if ($warnungen !== []) {
    echo "\nWarnungen (kein Fehler, aber eine Aussage):\n";

    foreach ($warnungen as $text) {
        echo "  · {$text}\n";
    }
}

if ($fehler === []) {
    echo "\nKeine Seite fällt ungewollt auf einen Fallback.\n";
    exit(0);
}

echo "\nFehler:\n";

foreach ($fehler as $text) {
    echo "  · {$text}\n";
}

exit(1);
