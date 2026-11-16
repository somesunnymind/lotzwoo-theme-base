<?php

/**
 * Prüft, dass keine Seite ungewollt auf einen Fallback fällt.
 *
 *     sg www-data -c "wp --path=/pfad/zu/wordpress eval-file <theme>/scripts/check-frontend.php"
 *
 * Jede Seite hat ihr Template. Diese Datei weist nach, dass es auch gewinnt,
 * und hält die Messungen dazu an einer Stelle, an der jeder sie wiederholen
 * kann.
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
 * gegen eine laufende Installation arbeitet. Ausgeschlossen ist *WordPress in
 * der CI* (kein `wp-env`, kein Docker) — nicht ein Skript, das jemand gegen
 * eine echte Installation laufen lässt. Die CI prüft, was ohne WordPress geht;
 * diese Datei prüft das eine, das ohne WordPress nicht zu prüfen ist.
 *
 * ## Zwei Ebenen, ungleich gewichtet
 *
 * Das **Rückgrat** ist die Auflösungsebene: `get_block_templates()` gegen die
 * Landkarte unten. Sie trifft den Fehlerfall genau, braucht keine Terme, keinen
 * gefüllten Warenkorb und keine Anmeldung.
 *
 * Dazu ein paar **HTTP-Abrufe** für das, was erst beim Rendern sichtbar wird:
 * hat die Seite Kopf und Fuß, steht kein Woo-Katalog darin. Ob ein Template den
 * richtigen Kopf trägt, sieht die Auflösungsebene nicht.
 *
 * ## Der B2B-Riegel gehört zur Erwartung, nicht gegen sie
 *
 * Das Plugin antwortet auf Produktseite, Produktarchiv, Produktsuche und
 * Produkt-Taxonomien mit **404**, sobald die Installation ein B2B-Shop ist
 * (`Product_Access_Gate`, gehängt an `template_redirect`, geschaltet über
 * `b2b_enabled`). Diese Prüfung fährt **anonym** — `wp_remote_get()` schickt
 * keinen Anmeldekeks —, und anonym kommt niemand durch den Riegel. Mit fester
 * Erwartung 200 wären drei Abrufe unten bei jedem Theme-Deploy rot, ohne dass
 * am Theme etwas kaputt ist.
 *
 * Die Erwartung ist deshalb an den Modus gekoppelt, statt die Messung
 * angemeldet zu fahren: ein Anmeldekeks wäre eine Schreiboperation, und der
 * Absatz darunter sagt zu, dass diese Datei nicht schreibt. Die Kopplung misst
 * außerdem mehr — unter dem Riegel ist die 404 die **zugesagte** Antwort, und
 * dass sie mit Kopf und Fuß aus der `404`-Vorlage dieses Themes kommt, ist
 * genau die Aussage, für die es den Abschnitt gibt.
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
 * richtig ist. Ein Inventar, das seine eigenen Entscheidungen mitführt.
 *
 * `theme`  — eine Datei dieses Themes (oder eines Kindthemes) muss gewinnen.
 * `plugin` — WooCommerce liefert, und zwar mit Absicht.
 */
const LANDKARTE = [
    '404' => ['theme', 'Grundgerüst: eigene 404-Seite mit Kopf und Fuß'],
    'archive-product' => ['theme', 'Kein Katalog, Hinweis aufs Sortiment. Trägt auch Kategorie, Schlagwort und Marke — Woo liefert für die drei keine eigene Datei'],
    'coming-soon' => ['plugin', 'Woos Vor-dem-Start-Modus; kein Teil des Kaufwegs und keine Seite, die ein Kunde je sieht'],
    'index' => ['theme', 'Grundgerüst: der Rückfall des Kerns gehört dem Theme'],
    'order-confirmation' => ['plugin', 'Kein eigenes Template — die Bestätigung ist Woos Store-API-Fläche'],
    'page' => ['theme', 'Grundgerüst, und zugleich das gesamte Konto: alle neun Woo-Endpunkte plus `nachbestellen` laufen hierüber'],
    'page-cart' => ['theme', 'Eigenes Template — der Warenkorb-Slot muss zwischen Positionen und Summen stehen, und dafür führt die Datei den Cart-Block inline statt über `wp:post-content`'],
    'page-checkout' => ['plugin', 'Kein eigenes Template: der Austritt aus der Store API kostet die Zahlungsarten-Registrierung. Die Hülle kommt trotzdem von hier — siehe Kopplung 1'],
    'page-full-width' => ['theme', 'Das Sortiment (Seite mit dem Kurzcode) läuft hierüber'],
    'page-no-title' => ['theme', 'Die Überschrift lebt im Inhalt. Für redaktionelle Seiten, die ihre H1 selbst mitbringen — `page.html` behält `post-title`, weil es auch das Sortiment trägt'],
    'product-search-results' => ['theme', 'Die Produktsuche zeigt den Hinweis, keinen Katalog'],
    'single' => ['theme', 'Grundgerüst'],
    'single-product' => ['plugin', 'Die Produktseite gehört nicht zum Kaufweg und bekommt kein eigenes Template'],
    'taxonomy-product_attribute' => ['theme', 'Braucht eine eigene Datei, weil Woo hierfür — anders als für Kategorie und Schlagwort — selbst eine mitliefert'],
];

/** Die beiden Teile, die auf jeder Seite dieses Themes stehen. */
const TEILE = [
    'header' => 'Kopfbereich: Navigation, Einhängestelle, Konto, Warenkorb',
    'footer' => 'Fußbereich: Rechtsseiten und Copyright',
];

/**
 * Was dem Kindtheme legitim gehört.
 *
 * Die Seitenstruktur eines Kunden ist ausdrücklich im Kindtheme zuhause. Eine
 * Prüfung, die genau das jedes Mal anmahnt, wird nach dem dritten Lauf nicht
 * mehr gelesen.
 *
 * Die Liste ist **abschließend**, nicht beispielhaft. Alles, was nicht darauf
 * steht, warnt weiter: ein `templates/page.html` im Kindtheme fehlt beim
 * nächsten Kunden.
 *
 * Der Schlüssel ist der Slug, der Wert die Begründung, die in der Ausgabe
 * mitläuft — dieselbe Form wie in der Landkarte oben und aus demselben Grund.
 */
const KIND_EIGEN = [
    'navigation' => 'Die Menüführung ist die Seitenstruktur des Kunden',
    'footer-links' => 'Welche Rechtsseiten es gibt, entscheidet der Kunde',
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
    // geschrieben. Kein Fehler, aber die Zusage „null Templates in der
    // Datenbank, jede Zeile kommt aus einer Datei" gilt dann nicht mehr, und
    // wer das liest, soll es merken.
    if ($template->source === 'custom') {
        $zeile('warnung', "{$slug}: liegt in der **Datenbank**. Die Installation ist damit nicht mehr ohne Datenbank reproduzierbar");
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
    // „Unerwünscht" gilt nicht pauschal: was auf KIND_EIGEN steht, gehört
    // dorthin. Alles andere warnt.
    $im_kind = get_stylesheet_directory() !== get_template_directory()
        && file_exists(get_stylesheet_directory() . "/templates/{$slug}.html");

    if ($im_kind && isset(KIND_EIGEN[$slug])) {
        $zeile('ok', "{$slug}: aus dem **Kindtheme**, wie vorgesehen — " . KIND_EIGEN[$slug]);
        continue;
    }

    if ($im_kind) {
        $zeile('warnung', "{$slug}: aus dem **Kindtheme**, steht aber nicht auf der Liste der kundeneigenen Slugs — ein Template dort fehlt beim nächsten Kunden");
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
 * Kopplung 4: die zwei Parts, die dem Kundentheme gehören.
 *
 * `parts/navigation.html` und `parts/footer-links.html` sind die einzige Stelle,
 * an der die Seitenstruktur eines Kunden steht. Das Basis-Theme liefert je eine
 * leere Vorgabe für den Fall, dass kein Kindtheme etwas mitbringt.
 *
 * Geprüft wird **nicht**, ob die Dateien existieren — das täte auch die
 * Auflösung oben. Geprüft wird, **wessen** Datei gewinnt, und zwar am
 * Verzeichnis statt am `theme`-Feld des Template-Objekts: das Feld trägt bei
 * datei-basierten Parts den Stylesheet-Namen, egal aus welchem der beiden
 * Verzeichnisse die Datei kam, und wäre hier eine Antwort, die immer stimmt.
 *
 * Der interessante Fall ist der **stille**: ein Kindtheme ist aktiv, bringt aber
 * keinen eigenen Part mit. Dann bleiben Menü und Fußlinks leer — die Seite ist
 * heil, die Rechtslinks sind weg, und niemand sucht danach. Genau dafür ist
 * diese Prüfung da.
 */
$abschnitt('Die Parts des Kundenthemes');

$kind_aktiv = get_stylesheet_directory() !== get_template_directory();

foreach (KIND_EIGEN as $slug => $grund) {
    $part = $teile[get_stylesheet() . '//' . $slug] ?? null;

    if (!$part instanceof WP_Block_Template) {
        $zeile('fehler', "parts/{$slug}.html: gar nicht aufgelöst — der Kopf- bzw. Fußbereich zieht diesen Part, und er kommt aus keinem der beiden Themes ({$grund})");
        continue;
    }

    if ($part->source === 'custom') {
        $zeile('warnung', "parts/{$slug}.html: liegt in der **Datenbank**. Der Website-Editor hat eine Fassung gespeichert — die Datei im Kindtheme wird nicht mehr gelesen");
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

    $zeile('warnung', "parts/{$slug}.html: aus dem **Kindtheme**, steht aber nicht auf der Liste der kundeneigenen Slugs — ein Part dort erbt keine Verbesserung am Rahmen mehr");
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
    $zeile('fehler', 'Kassen-Kopf: `woocommerce/woocommerce//checkout-header` gibt es nicht mehr. WooCommerce hat den Part umbenannt — der Filter in functions.php trifft ins Leere');
} elseif (!$eigenerkopf instanceof WP_Block_Template) {
    $zeile('fehler', 'Kassen-Kopf: der eigene Kopf ist nicht auflösbar, es gibt nichts unterzuschieben');
} elseif (md5((string) $kassenkopf->content) !== md5((string) $eigenerkopf->content)) {
    $zeile('fehler', 'Kassen-Kopf: die Kasse bekommt **nicht** denselben Kopf wie der Rest. Der Filter in functions.php greift nicht');
} else {
    $zeile('ok', 'Kassen-Kopf: dieselbe Datei wie überall');
}

// Der fehlende Fuß auf der Kasse ist Absicht: Woos Kassen-Template hat gar
// keinen Fuß-Part, und einen hineinzubekommen hieße, das Template zu
// übernehmen — und damit die Store API zu verlassen, was die
// Zahlungsarten-Registrierung kostet. Geprüft wird deshalb, dass es so *bleibt*.
$kasse = $gefunden['page-checkout'] ?? null;

if ($kasse instanceof WP_Block_Template && str_contains((string) $kasse->content, '"slug":"footer"')) {
    $zeile('warnung', 'Kasse: Woos Template zieht inzwischen einen Fuß-Part. Die Entscheidung gegen ein eigenes Kassen-Template ging davon aus, dass es keinen gibt — sie ist neu zu prüfen');
} else {
    $zeile('ok', 'Kasse: weiterhin ohne Fuß, wie vorgesehen');
}

/**
 * Kopplung 2: die Einhängestelle im Kopf.
 *
 * Das Theme registriert `lotzwoo/header-slot` und rendert die leere
 * Zeichenkette; das Plugin füllt ihn per `render_block`-Filter. Wer den Namen
 * auf einer der beiden Seiten ändert, bricht die andere, ohne dass irgendwo
 * sonst etwas rot wird.
 */
if (!WP_Block_Type_Registry::get_instance()->is_registered('lotzwoo/header-slot')) {
    $zeile('fehler', 'Einhängestelle: der Block `lotzwoo/header-slot` ist nicht registriert');
} elseif ($eigenerkopf instanceof WP_Block_Template
    && !str_contains((string) $eigenerkopf->content, 'wp:lotzwoo/header-slot')) {
    $zeile('fehler', 'Einhängestelle: der Block ist registriert, steht aber nicht mehr in parts/header.html. Das Plugin hat dann keinen Ort im Kopf');
} else {
    $zeile('ok', 'Einhängestelle: `lotzwoo/header-slot` registriert und im Kopf verbaut');
}

/**
 * Kopplung 3: der Anker im Warenkorb — und warum es hier **drei** Prüfungen sind.
 *
 * Der Kopf-Slot führt eine Zeichenkette: den Blocknamen. Der Warenkorb-Slot
 * führt zwei — den Blocknamen **und** die Div-ID `lotzwoo-cart-slot`, auf die
 * `MOUNT_ID` in `assets/js/b2b-cart.js` zeigt. Zwei Namen brauchen zwei
 * Prüfungen, plus die, dass der Block überhaupt registriert ist.
 *
 * Die dritte Prüfung ruft den `render_callback` auf, statt die Datei nach der
 * ID zu durchsuchen: die ID steht in PHP-Code, nicht in einem Template, und
 * eine Textsuche in `functions.php` fände sie auch in einem Kommentar.
 *
 * Wird eine der beiden Zeichenketten umbenannt, ohne die Gegenseite
 * nachzuziehen, bleibt die Seite **heil** — das JS legt sich dann ein eigenes
 * Div neben den Cart-Block. Genau darum braucht es diese
 * Prüfung: das Symptom ist eine Sektion an der falschen Stelle, und die sieht
 * niemand, der sie nicht sucht.
 */
$slot_typ = WP_Block_Type_Registry::get_instance()->get_registered('lotzwoo/cart-slot');
$warenkorb_template = $gefunden['page-cart'] ?? null;

if (!$slot_typ instanceof WP_Block_Type) {
    $zeile('fehler', 'Warenkorb-Slot: der Block `lotzwoo/cart-slot` ist nicht registriert');
} else {
    $zeile('ok', 'Warenkorb-Slot: `lotzwoo/cart-slot` registriert');
}

if (!$warenkorb_template instanceof WP_Block_Template
    || !str_contains((string) $warenkorb_template->content, 'wp:lotzwoo/cart-slot')) {
    $zeile('fehler', 'Warenkorb-Slot: der Block steht nicht in `templates/page-cart.html`. Das Plugin hängt seine Sektion dann wieder unter den Bestellknopf');
} else {
    $zeile('ok', 'Warenkorb-Slot: im Cart-Template verbaut, zwischen Positionen und Summen');
}

$slot_ausgabe = $slot_typ instanceof WP_Block_Type && is_callable($slot_typ->render_callback)
    ? (string) call_user_func($slot_typ->render_callback, [], '', null)
    : '';

if (!str_contains($slot_ausgabe, 'id="lotzwoo-cart-slot"')) {
    $zeile('fehler', 'Warenkorb-Slot: der `render_callback` liefert kein `id="lotzwoo-cart-slot"` — `MOUNT_ID` in b2b-cart.js findet den Anker nicht');
} else {
    $zeile('ok', 'Warenkorb-Slot: der `render_callback` liefert den Anker `id="lotzwoo-cart-slot"` — dieselbe Zeichenkette wie `MOUNT_ID` im Plugin');
}

/**
 * Kopplung 5: der Weg ins Sortiment.
 *
 * Die dritte Zeichenkette, die sich zwei Repos teilen: `lotzwoo/sortiment-url`.
 * Das Theme wendet den Filter auf das `href` seiner „Zum Sortiment"-Knöpfe an,
 * das Plugin beantwortet ihn mit der Seite, die `[lotzwoo_b2b_shop]` trägt.
 *
 * Geprüft wird **beides**: ein Hook, der korrekt hängt und nie feuert, sieht
 * von beiden Seiten aus wie der Rückfall auf `/` — also wie ein Theme ohne
 * Filter. Und ist `page_on_front` die Sortimentsseite, ist dieser Rückfall
 * noch dazu **zufällig richtig**. Eine Prüfung, die nur die Ausgabe ansieht,
 * wäre grün, ohne etwas zu bedeuten.
 *
 * Deshalb hängt sich die Prüfung selbst an den Filter, rendert einen
 * Musterknopf durch `do_blocks()` und sieht nach, ob **ihre eigene** Antwort im
 * `href` ankommt. Das misst die Naht im Theme und ist von der Anwesenheit des
 * Plugins unabhängig — genau richtig für ein Basis-Theme, das ohne das Plugin
 * lauffähig bleiben muss.
 *
 * Die Klasse `lotzwoo-shop-link` ist die zweite Zeichenkette dieser Kopplung,
 * diesmal eine theme-interne: sie steht im Markup und im Filter. Wer sie in
 * einem Template streicht, bekommt einen Knopf, der stumm auf `/` zeigt.
 */
$abschnitt('Der Weg ins Sortiment (Kopplung 5)');

/*
 * `parts/header.html` steht absichtlich **nicht** in dieser Liste. Die
 * Kopfzeile trägt keinen Knopf „Zum Sortiment": in ihrer oberen Bahn sitzt der
 * Konto-Chip als einziger Kontoweg, und der Weg ins Sortiment liegt in der
 * klebenden Bahn darunter — der Kontextleiste, die auf jeder Kaufwegseite
 * steht.
 *
 * Die Prüfung darunter gilt den vier Vorlagen, die den Knopf tragen. Den Kopf
 * hier zu führen hieße, eine Entscheidung als Fehler zu melden — und ein
 * Prüfwerkzeug, das bei jedem Lauf einen bekannten Fehler meldet, wird nach
 * dem dritten Mal nicht mehr gelesen.
 */
const SORTIMENT_KNOEPFE = [
    'templates/archive-product.html' => 'Produktarchiv',
    'templates/product-search-results.html' => 'Produktsuche',
    'templates/taxonomy-product_attribute.html' => 'Attribut-Archiv',
    'templates/page-cart.html' => 'Leerer Warenkorb',
];

$inhalte = [
];

foreach (['archive-product', 'product-search-results', 'taxonomy-product_attribute', 'page-cart'] as $slug) {
    $template = $gefunden[$slug] ?? null;
    $inhalte["templates/{$slug}.html"] = $template instanceof WP_Block_Template
        ? (string) $template->content
        : null;
}

foreach (SORTIMENT_KNOEPFE as $datei => $zweck) {
    $inhalt = $inhalte[$datei] ?? null;

    if ($inhalt === null) {
        $zeile('fehler', "{$datei}: nicht auflösbar — der Knopf ins Sortiment ist nicht prüfbar ({$zweck})");
        continue;
    }

    if (!str_contains($inhalt, 'lotzwoo-shop-link')) {
        $zeile('fehler', "{$datei}: der Knopf trägt kein `lotzwoo-shop-link` mehr — der Filter greift ihn nicht, er zeigt stumm auf `/` ({$zweck})");
        continue;
    }

    $zeile('ok', "{$datei}: Knopf mit `lotzwoo-shop-link` — {$zweck}");
}

// Die Naht selbst. Eigene Antwort hinein, `href` heraus.
$sonde = 'https://sortiment.pruefung.invalid/naht';
$antwort_sonde = static fn (): string => $sonde;

add_filter('lotzwoo/sortiment-url', $antwort_sonde, 99);

$gerendert = do_blocks(
    '<!-- wp:button {"className":"lotzwoo-shop-link"} -->'
    . '<div class="wp-block-button lotzwoo-shop-link">'
    . '<a class="wp-block-button__link wp-element-button" href="/">Zum Sortiment</a></div>'
    . '<!-- /wp:button -->'
);

remove_filter('lotzwoo/sortiment-url', $antwort_sonde, 99);

if (!str_contains($gerendert, 'href="' . $sonde . '"')) {
    $zeile('fehler', 'Sortiment-Naht: der Filter `lotzwoo/sortiment-url` **feuert nicht**. Ein Musterknopf behält sein `href` aus dem Markup — alle Knöpfe ins Sortiment zeigen dann auf `/`, und liegt dort das Sortiment, sieht man es nirgends');
} else {
    $zeile('ok', 'Sortiment-Naht: `lotzwoo/sortiment-url` feuert und schreibt das `href` — am Musterknopf gemessen, nicht am Code');
}

// Was heute wirklich herauskommt. Kein Urteil: ohne Plugin ist `/` die
// richtige Antwort, und das Basis-Theme muss ohne dieses Plugin laufen.
$aufgeloest = apply_filters('lotzwoo/sortiment-url', '/');

if ($aufgeloest === '/') {
    $zeile('hinweis', 'Sortiment: niemand beantwortet den Filter — die Knöpfe ins Sortiment zeigen auf `/`. Ohne das Plugin `lotzapp-for-woocommerce` ist genau das der vorgesehene Rückfall');
} else {
    $zeile('ok', "Sortiment: aufgelöst auf {$aufgeloest}");
}

/**
 * Kopplung 6: die Kontextleiste unter dem Kopf.
 *
 * Dritter Slot, und er misst sich wie der erste plus eine Prüfung, die es beim
 * ersten nicht gab.
 *
 * Wie beim Kopf-Slot: der Block muss registriert sein und an seinem Platz
 * stehen (siehe unten). Die dritte Prüfung ist die wichtigere — der
 * `render_callback` muss die **leere Zeichenkette** liefern und nicht etwa ein
 * leeres `<div>`. Der Block steht in jeder Vorlage mit Kopf, also auf jeder
 * Seite der Website; ein Anker-Div stünde leer auf der Startseite, auf jeder
 * Inhaltsseite und in der 404. Wer den Rückgabewert versehentlich auf einen Anker
 * umstellt, bricht nichts — er hinterlässt nur auf jeder Seite ein Element, das
 * niemand sucht. Genau dafür ist diese Zeile da.
 *
 * Was hier **nicht** geprüft wird: ob die Leiste erscheint. Das entscheidet das
 * Plugin, und dieses Skript misst das Theme.
 */
$abschnitt('Die Kontextleiste (Kopplung 6)');

$kontext_typ = WP_Block_Type_Registry::get_instance()->get_registered('lotzwoo/context-slot');

if (!$kontext_typ instanceof WP_Block_Type) {
    $zeile('fehler', 'Kontextleiste: der Block `lotzwoo/context-slot` ist nicht registriert');
} else {
    $zeile('ok', 'Kontextleiste: `lotzwoo/context-slot` registriert');
}

/*
 * Der Block steht **nicht** in `parts/header.html`, sondern in jeder Vorlage,
 * direkt hinter dem Kopf-Part. Der Grund ist gemessen und nicht
 * geschmacklich: die Leiste soll beim Scrollen kleben, ein `position: sticky`
 * klebt nur innerhalb seines Containers, und WordPress legt um jeden
 * Template-Part ein Element (`<header class="wp-block-template-part">`). Im
 * Kopf-Part wäre der Container also genau so hoch wie der Kopf — die Leiste
 * klebte an nichts.
 *
 * Der Preis ist eine Zeile in jeder Vorlage statt einer in einem Part, und
 * genau deshalb steht die Prüfung hier: **jede Vorlage, die den Kopf zieht,
 * zieht auch die Leiste, und zwar genau einmal.** Eine neue Vorlage, die es
 * vergisst, ist damit ein Fehlschlag und keine stille Lücke.
 */
$vorlagen = get_block_templates([], 'wp_template');
$ohne_leiste = [];
$doppelt = [];
$fremd = [];
$mit_kopf = 0;

foreach ($vorlagen as $vorlage) {
    $inhalt = (string) $vorlage->content;

    if (!str_contains($inhalt, 'wp:template-part {"slug":"header"')) {
        continue;
    }

    /*
     * Nur Vorlagen, die diesem Theme gehören. WooCommerce liefert eigene mit
     * (`single-product`, `order-confirmation`), und die ziehen denselben
     * Kopf-Part — ändern kann sie dieses Theme nur, indem es sie überschreibt.
     * Beides ist hier ausdrücklich nicht gewollt: die Produktseite gehört
     * nicht zum Kaufweg, und auf der Danke-Seite stünde der *heutige*
     * Kontext neben den eingefrorenen Werten der Bestellung. Sie werden
     * gezählt und genannt, nicht bemängelt.
     */
    if (($vorlage->source ?? '') === 'plugin') {
        $fremd[] = $vorlage->slug;
        continue;
    }

    $mit_kopf++;
    $anzahl = substr_count($inhalt, 'wp:lotzwoo/context-slot');

    if ($anzahl === 0) {
        $ohne_leiste[] = $vorlage->slug;
    } elseif ($anzahl > 1) {
        $doppelt[] = $vorlage->slug;
    }
}

if ($mit_kopf === 0) {
    $zeile('fehler', 'Kontextleiste: keine Vorlage zieht den Kopf-Part — die Prüfung misst nichts');
} elseif ($ohne_leiste !== []) {
    $zeile('fehler', 'Kontextleiste: diese Vorlagen ziehen den Kopf, aber nicht `wp:lotzwoo/context-slot`: ' . implode(', ', $ohne_leiste));
} elseif ($doppelt !== []) {
    $zeile('fehler', 'Kontextleiste: doppelter Slot in ' . implode(', ', $doppelt) . ' — die Leiste stünde zweimal auf derselben Seite');
} else {
    $zeile('ok', "Kontextleiste: in allen {$mit_kopf} Vorlagen dieses Themes mit Kopf-Part verbaut, je genau einmal");
}

if ($fremd !== []) {
    $zeile('ok', 'Kontextleiste: ohne Leiste bleiben die Vorlagen fremder Plugins — ' . implode(', ', $fremd) . ' (die Produktseite gehört nicht zum Kaufweg, die Danke-Seite zeigt die eingefrorenen Werte der Bestellung)');
}

if ($eigenerkopf instanceof WP_Block_Template
    && str_contains((string) $eigenerkopf->content, 'wp:lotzwoo/context-slot')) {
    $zeile('fehler', 'Kontextleiste: der Block steht (wieder) in `parts/header.html`. Dort kann er nicht kleben — WordPress legt um jeden Template-Part ein `<header>`, und ein sticky-Element klebt nur innerhalb seines Containers');
} elseif ($eigenerkopf instanceof WP_Block_Template) {
    $zeile('ok', 'Kontextleiste: nicht im Kopf-Part — sie steht als eigene Bahn darunter und kann kleben');
}

$kontext_ausgabe = $kontext_typ instanceof WP_Block_Type && is_callable($kontext_typ->render_callback)
    ? (string) call_user_func($kontext_typ->render_callback, [], '', null)
    : null;

if ($kontext_ausgabe === null) {
    $zeile('fehler', 'Kontextleiste: kein `render_callback` — der Block rendert dann sein gespeichertes Markup, und das gibt es nicht');
} elseif ($kontext_ausgabe !== '') {
    $zeile('fehler', 'Kontextleiste: der `render_callback` liefert Markup statt der leeren Zeichenkette. Der Kopf-Part hängt an **jeder** Seite — das stünde dann auch auf der Startseite und in der 404');
} else {
    $zeile('ok', 'Kontextleiste: der `render_callback` liefert die leere Zeichenkette — ohne Plugin bleibt auf keiner Seite ein Element zurück');
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

/**
 * Steht diese Installation auf B2B — sperrt der Riegel also die Produktflächen?
 *
 * Gefragt wird die Klasse des Plugins und nicht die Option: `B2B_Mode::is_on()`
 * ist die eine Stelle, die diese Frage beantwortet, und der Filter
 * `lotzwoo/b2b_enabled` gehört dazu. Fehlt die Klasse — kein Plugin, oder eines
 * ohne B2B-Schalter —, gilt die Erwartung ohne Riegel.
 *
 * Beide Prozesse lesen dieselbe Option: dieser hier und der, den `wp_remote_get()`
 * gleich anspricht. Auseinander liefen sie nur, wenn jemand den Filter allein im
 * CLI hinge — das tut niemand, und ein Lauf, der es doch täte, sähe es sofort an
 * der Zeile unter dieser.
 */
$riegel_an = class_exists('Lotzwoo\\B2B\\Access\\B2B_Mode')
    && \Lotzwoo\B2B\Access\B2B_Mode::is_on();

echo 'B2B-Modus: ' . ($riegel_an ? 'an' : 'aus') . ' — die Produktflächen werden anonym mit '
    . ($riegel_an ? '404 erwartet' : '200 erwartet') . "\n";

/**
 * Pfad · Zweck · kein Woo-Katalog erlaubt · Code ohne Riegel · vom Riegel gesperrt
 *
 * Die letzte Spalte ist der Befund aus `Product_Access_Gate::locks_current_view()`,
 * nicht eine Vermutung über Adressen: `is_shop()`, `is_search()` mit
 * `post_type=product` und `is_tax()` auf einer Produkt-Taxonomie. Der Warenkorb,
 * die Startseite und eine gewöhnliche Seite stehen dort nicht — sie bleiben in
 * beiden Modi 200, und dass das so bleibt, ist die halbe Aussage dieses
 * Abschnitts.
 */
$abrufe = [
    ['/', 'Startseite — hier das Sortiment (page-full-width)', true, 200, false],
    [wp_make_link_relative(wc_get_page_permalink('shop')), 'Woos Shop-Seite (archive-product)', false, 200, true],
    ['/?s=apfel&post_type=product', 'Produktsuche (product-search-results)', false, 200, true],
    [wp_make_link_relative(wc_get_page_permalink('cart')), 'Warenkorb (eigenes Template mit dem Slot)', true, 200, false],
    ['/diese-seite-gibt-es-nicht-' . substr(md5(get_stylesheet()), 0, 6) . '/', 'Eine Adresse ohne Seite (404)', true, 404, false],
];

$kategorien = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => false, 'number' => 1]);

if (!is_wp_error($kategorien) && $kategorien !== []) {
    $abrufe[] = [
        wp_make_link_relative(get_term_link($kategorien[0])),
        'Produktkategorie „' . $kategorien[0]->name . '" (fällt auf archive-product)',
        false,
        200,
        true,
    ];
}

if ($gewoehnliche !== []) {
    $abrufe[] = [
        wp_make_link_relative(get_permalink($gewoehnliche[0])),
        'Eine gewöhnliche Seite („' . $gewoehnliche[0]->post_title . '", page)',
        true,
        200,
        false,
    ];
}

foreach ($abrufe as [$pfad, $zweck, $katalog_verboten, $grundcode, $gesperrt]) {
    // Der Riegel greift nur, wenn beides zutrifft: die Fläche steht auf seiner
    // Liste **und** der Modus ist an. Sonst gilt der Code ohne Riegel.
    $unter_riegel = $gesperrt && $riegel_an;
    $erwarteter_code = $unter_riegel ? 404 : $grundcode;

    $antwort = wp_remote_get(home_url($pfad), ['timeout' => 20, 'redirection' => 3]);

    if (is_wp_error($antwort)) {
        $zeile('fehler', "{$pfad}: nicht abrufbar ({$antwort->get_error_message()}) — {$zweck}");
        continue;
    }

    $code = wp_remote_retrieve_response_code($antwort);
    $html = wp_remote_retrieve_body($antwort);

    if ($code !== $erwarteter_code) {
        // Beide Richtungen sagen, was sie meinen. Hinter einem bloßen
        // „erwartet 200" vermutet niemand den Riegel.
        $warum = '';

        if ($unter_riegel) {
            $warum = '. Der B2B-Modus steht **an** — der Riegel müsste diese Fläche anonym auf 404 setzen';
        } elseif ($gesperrt) {
            $warum = '. Der B2B-Modus steht **aus** — diese Antwort kommt dann nicht vom Riegel';
        }

        $zeile('fehler', "{$pfad}: HTTP {$code}, erwartet {$erwarteter_code} — {$zweck}{$warum}");
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
        $zeile('fehler', "{$pfad}: zeigt einen Woo-Katalog ({$spuren[0]}) — {$zweck}. Das ist die Tür an der Eligibility-Prüfung vorbei, die diese Vorlage schließen soll");
        continue;
    }

    $zeile('ok', $unter_riegel
        ? "{$pfad}: 404 mit Kopf und Fuß, kein Katalog — der B2B-Riegel sperrt anonym, wie das Plugin es zusagt (ohne Riegel: {$zweck})"
        : "{$pfad}: Kopf und Fuß da, kein Katalog — {$zweck}");
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
