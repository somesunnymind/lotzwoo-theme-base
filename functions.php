<?php

/**
 * LotzApp B2B Base — theme bootstrap.
 *
 * Deliberately thin. A block theme declares almost everything in
 * `theme.json`, and every line here is a line that has to be maintained
 * against a WordPress release. What remains is what `theme.json` cannot say.
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('after_setup_theme', static function (): void {
    add_theme_support('wp-block-styles');
    add_theme_support('responsive-embeds');
    add_theme_support('editor-styles');
    add_theme_support('custom-logo', [
        'height' => 40,
        'width' => 200,
        'flex-height' => true,
        'flex-width' => true,
    ]);

    // WooCommerce renders its cart, checkout and account through templates it
    // ships, and this declaration does *not* change what it loads: measured on
    // 2026-08-10, `woocommerce.css` is enqueued on every page either way.
    // `WC_Frontend_Scripts::get_styles()` never consults the support flag, and
    // the "unsupported theme" compatibility layer is already off because this
    // is a block theme. The line the comment used to carry — that declaring
    // support stops Woo's fallback stylesheet — was simply wrong.
    //
    // It stays for now because it is the documented way to say "this theme
    // handles Woo itself", and third-party extensions do read it. Whether it
    // earns its place is a question for the template cut, not for a comment.
    add_theme_support('woocommerce');

    load_theme_textdomain('lotzwoo-theme-base', get_template_directory() . '/languages');
});

/**
 * The stylesheet, and the child's on top of it when there is one.
 *
 * Version taken from the file's own mtime rather than a constant: a theme is
 * edited far more often than it is released, and a stale stylesheet behind a
 * CDN is the kind of bug that costs an afternoon.
 */
add_action('wp_enqueue_scripts', static function (): void {
    $parent = get_template_directory() . '/style.css';

    wp_enqueue_style(
        'lotzwoo-theme-base',
        get_template_directory_uri() . '/style.css',
        [],
        is_readable($parent) ? (string) filemtime($parent) : null
    );

    if (get_template_directory() === get_stylesheet_directory()) {
        return;
    }

    $child = get_stylesheet_directory() . '/style.css';

    if (!is_readable($child)) {
        return;
    }

    wp_enqueue_style(
        'lotzwoo-theme-child',
        get_stylesheet_uri(),
        ['lotzwoo-theme-base'],
        (string) filemtime($child)
    );
});

/**
 * Die zugewiesene Seitenvorlage auf den `<body>` schreiben.
 *
 * Block-Themes vergeben für eine `customTemplates`-Vorlage von sich aus keine
 * Body-Klasse. Das Stylesheet braucht aber eine: `page-full-width` hebt dort
 * die 960px des Inhaltsbereichs auf, und die Alternative — jede Seite zu
 * verbreitern und den Rest wieder einzufangen — hätte die Vorgabe für genau
 * die Seiten falsch, die gewöhnlicher Fließtext sind.
 *
 * Der Name wird aus dem Vorlagen-Slug **abgeleitet** statt je Vorlage
 * aufgezählt. Bis zum 2026-08-11 stand hier ein Vergleich gegen die
 * Zeichenkette `page-full-width`; mit der zweiten Vorlage (`page-no-title`)
 * wäre daraus eine zweite Bedingung geworden, und mit der dritten eine dritte.
 * `lotzwoo-page-full-width` heisst weiterhin genau so — die Regel in
 * `style.css` bleibt unberührt.
 *
 * `sanitize_html_class()`, weil der Slug aus `_wp_page_template` kommt und das
 * ein Meta-Wert ist: er steht so in der Datenbank, wie ihn zuletzt jemand
 * hineingeschrieben hat, und das muss kein Dateiname sein.
 */
add_filter('body_class', static function (array $classes): array {
    if (!is_page()) {
        return $classes;
    }

    $slug = (string) get_page_template_slug();

    // `default` ist der Wert, den WordPress für „keine eigene Vorlage" setzt —
    // eine Klasse `lotzwoo-page-default` behauptete eine Zuweisung, die es
    // nicht gibt.
    if ($slug === '' || $slug === 'default') {
        return $classes;
    }

    $class = sanitize_html_class('lotzwoo-' . $slug);

    if ($class !== '') {
        $classes[] = $class;
    }

    return $classes;
});

/**
 * Eine Logokachel, solange kein Logo hochgeladen ist.
 *
 * Der Prototyp setzt links ein 28px-Quadrat mit Farbverlauf und einem
 * Buchstaben darin. Das ist der Teil, der einen Kopfbereich fertig aussehen
 * lässt — und ohne ihn steht dort nur der Website-Titel in Fließtext.
 *
 * Der Buchstabe kommt aus dem Website-Titel und ist nicht fest verdrahtet.
 * Ein hart eingetragenes „L" wäre im Basis-Theme falsch: es trägt jedes
 * Kundentheme, und der Kunde heißt selten LotzApp.
 *
 * Der Block rendert leer, wenn kein Logo gesetzt ist — genau dann greift
 * das hier. Sobald jemand eines hochlädt, liefert der Block Markup und die
 * Kachel verschwindet von selbst. Ein Platzhalter, der neben dem echten
 * Logo stehen bliebe, wäre schlimmer als keiner.
 *
 * `aria-hidden`, weil der Website-Titel direkt daneben dasselbe sagt.
 *
 * Der Hook heißt `render_block_core/site-logo` — mit Schrägstrich, wie der
 * Blockname. `render_block_core_site_logo` sieht genauso plausibel aus, ist
 * aber der Name der Render-Funktion in WordPress und kein Filter: er lässt
 * sich ohne Fehler registrieren und feuert nie. Erste Fassung dieser Datei
 * hatte genau den, und nur der Blick auf die Ausgabe hat es gezeigt.
 */
add_filter('render_block_core/site-logo', static function (string $content): string {
    if (trim($content) !== '') {
        return $content;
    }

    $name = trim((string) get_bloginfo('name'));

    if ($name === '') {
        return $content;
    }

    $initial = function_exists('mb_substr') ? mb_substr($name, 0, 1) : substr($name, 0, 1);

    return '<div class="lotzwoo-logomark" aria-hidden="true">'
        . esc_html(function_exists('mb_strtoupper') ? mb_strtoupper($initial) : strtoupper($initial))
        . '</div>';
}, 10, 1);

/**
 * Lucide `circle-user` statt WooCommerces eigenem Konto-Icon.
 *
 * Der Block bringt ein gefülltes Personen-Symbol in einem eigenen viewBox
 * (`-5 -5 25 25`) mit. Es steht neben Lucide-Strichicons und fällt dort auf:
 * andere Strichstärke, andere Anmutung, andere Kantenrundung.
 *
 * Ersetzt wird nur das `<svg>`, nicht der Link und nicht die Beschriftung —
 * ein Block-Filter, der mehr anfasst als nötig, bricht beim nächsten
 * WooCommerce-Update an einer Stelle, die niemand mit ihm in Verbindung
 * bringt. Findet sich kein `<svg>`, bleibt alles, wie es war.
 *
 * Lucide (https://lucide.dev), ISC-Lizenz.
 */
add_filter('render_block_woocommerce/customer-account', static function (string $content): string {
    $lucide = '<svg class="icon lotzwoo-ico" width="20" height="20" viewBox="0 0 24 24"'
        . ' fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"'
        . ' stroke-linejoin="round" aria-hidden="true" focusable="false">'
        . '<circle cx="12" cy="12" r="10"/><circle cx="12" cy="10" r="3"/>'
        . '<path d="M7 20.662V19a2 2 0 0 1 2-2h6a2 2 0 0 1 2 2v1.662"/></svg>';

    $replaced = preg_replace('#<svg\b.*?</svg>#is', $lucide, $content, 1);

    return is_string($replaced) ? $replaced : $content;
}, 10, 1);

/**
 * Die aktuelle Seite im Kopfbereich markieren.
 *
 * Die Navigation in `parts/header.html` steht **inline** — sechs
 * `core/navigation-link` ohne `ref` auf ein `wp_navigation`-Objekt. Das ist die
 * Entscheidung aus Ticket 13: jede Zeile des Themes kommt aus einer Datei, der
 * Klon bleibt ohne Datenbank reproduzierbar, und beim Themewechsel bleibt keine
 * verwaiste Menü-Zeile in der Datenbank des Kunden zurück.
 *
 * Der Preis steht in `wp-includes/blocks/navigation-link.php`:
 *
 * ```php
 * $is_active = ! empty( $attributes['id'] ) && get_queried_object_id() === (int) $attributes['id'] …
 * ```
 *
 * Ein **eigener** Link hat kein `id` — er kann keines haben, ohne die
 * Beitrags-ID einer bestimmten Installation ins Theme zu schreiben. Damit ist
 * `$is_active` immer falsch, und der Kern vergibt weder `current-menu-item`
 * noch `aria-current="page"`. Die Fläche für die aktive Seite steht seit der
 * Markenarbeit in `style.css` und bekäme nie eine Zeile zu färben.
 *
 * Verglichen wird deshalb der **Pfad**. `$wp->request` ist der Pfad der
 * laufenden Anfrage, ohne Schrägstriche an den Enden und ohne Abfrageteil —
 * genau die Form, in der die `url` der Links im Markup steht. Kein
 * `home_url()`, keine Domäne: ein Theme, das an mehrere Kunden geht, darf seine
 * Aktivmarkierung nicht an einem Hostnamen festmachen.
 *
 * Ein Link auf die Startseite (`/`) bleibt bewusst unmarkiert: sein Pfad ist
 * leer, und leer gegen leer träfe auf jeder Seite zu, die WordPress als
 * Startseite ausliefert — einschließlich der Shop-Seite dieser Installation.
 * Die Navigation trägt heute keinen solchen Eintrag; der Riegel steht, bevor
 * jemand einen einfügt.
 */
add_filter('render_block_core/navigation-link', static function (string $content, array $block): string {
    $url = $block['attrs']['url'] ?? '';

    // Ein Link mit `id` ist ein Beitrags- oder Term-Link; für den hat der Kern
    // die Markierung schon gesetzt oder bewusst nicht gesetzt.
    if (!is_string($url) || $url === '' || !empty($block['attrs']['id'])) {
        return $content;
    }

    $path = wp_parse_url($url, PHP_URL_PATH);

    if (!is_string($path)) {
        return $content;
    }

    $path = trim($path, '/');

    global $wp;

    if ($path === '' || $path !== trim((string) ($wp->request ?? ''), '/')) {
        return $content;
    }

    $tags = new WP_HTML_Tag_Processor($content);

    if ($tags->next_tag('li')) {
        $tags->add_class('current-menu-item');
    }

    if ($tags->next_tag('a')) {
        $tags->set_attribute('aria-current', 'page');
    }

    return $tags->get_updated_html();
}, 10, 2);

/*
 * Der Weg ins Sortiment im Kopfbereich — und warum hier kein Filter mehr steht.
 *
 * `2debf23` hat den Knopf `.lotzwoo-shop-link` per `render_block_core/button`
 * auf `wc_get_page_permalink('shop')` umgeschrieben, mit einem guten Argument:
 * welchen Slug Woos Shop-Seite trägt, entscheidet jede Installation für sich,
 * und sie steht wenigstens in einer Option — die Adresse der Seite mit dem
 * Kurzcode `[lotzwoo_b2b_shop]` steht nirgends.
 *
 * Der Auftraggeber hat am 2026-08-11 anders entschieden: **der Knopf zeigt
 * auf `/`.** Der Grund ist der Zwischenstand, den das Argument in Kauf nahm.
 * Woos Shop-Seite trägt seit Ticket 12 den Hinweis „Kein Katalog an dieser
 * Stelle"; der Weg ins Sortiment endete also bei der Auskunft, dass es hier
 * keinen gibt — und zwar so lange, bis das Archiv-Routing im Plugin steht
 * (Nachbarkarte). Ein Knopf, der auf eine Absage führt, ist teurer als eine
 * Annahme, die stimmt, solange die Startseite das Sortiment ist.
 *
 * Der Einwand gegen `/` bleibt richtig und ist nicht widerlegt: stellt jemand
 * `page_on_front` um, zeigt der Knopf lautlos auf die neue Startseite. Er steht
 * dafür in einer Datei, die im Website-Editor änderbar ist, und dieselbe
 * Adresse steht schon in den drei Archiv-Templates hinter „Zum Sortiment" —
 * alle vier sagen jetzt dasselbe.
 *
 * Sobald das Routing steht, ist der Filter drei Zeilen entfernt wieder da.
 */

/**
 * Autoren- und Datumsarchive und die Seitensuche gibt es hier nicht.
 *
 * Es ist ein B2B-Portal ohne Blog. Heute fallen Autorenarchiv, Datumsarchiv und
 * die gewöhnliche Suche auf `templates/index.html` und zeigen dort eine
 * Beitragsschleife, die nie ein Ergebnis hat — und das Autorenarchiv zählt
 * dabei Benutzernamen auf, die niemand veröffentlichen wollte.
 *
 * Über `theme.json` ist das nicht zu lösen: die erlaubten Schlüssel dort sind
 * abschließend und kennen keinen Schalter für Anfragetypen. Ein Template
 * *bedient* eine Anfrage, es verhindert sie nicht.
 *
 * ## Warum beides — Rewrite-Regeln **und** ein 404 im Ablauf
 *
 * Die leeren Regeln nehmen `/author/name/` und `/2026/08/` aus den hübschen
 * Adressen. Sie nehmen aber **nicht** `?author=1` und `?m=202608`: die gehen an
 * jeder Rewrite-Regel vorbei, weil sie gar keine brauchen. Nur die Regeln zu
 * leeren sähe erledigt aus und wäre es nicht.
 *
 * ## Die Produktsuche bleibt
 *
 * Abgeschaltet wird nur die Suche **ohne** `post_type=product`. Die
 * Produktsuche behält ihr Template und den Hinweis aufs Sortiment; ein
 * pauschales 404 auf jedes `?s=` ließe `product-search-results.html` nie zum
 * Zug kommen.
 *
 * ## Und die Website-Suche lässt sich zurückholen
 *
 * `lotzwoo_theme_site_search_enabled` — Standardwert `false`, also das Verhalten
 * dieses Absatzes. Eine Installation, die redaktionelle Inhalte trägt, setzt den
 * Filter auf `true` und bekommt ihre Suche zurück, ohne dass jemand hier eine
 * Bedingung herausoperiert und dabei die Autoren- und Datumsabschaltung
 * mitnimmt.
 *
 * **Warum ein Filter und kein Schalter im Kindtheme:** AD-8 hält Kindthemes bei
 * `style.css` und `theme.json` — kein PHP, keine Templates. Ein Schalter dort
 * bräche die Form, die das Basis-Theme über alle Kunden hinweg trägt. Der
 * natürliche Aufrufer ist das kundeneigene Site-Plugin.
 *
 * **Warum nicht „hängt davon ab, ob `search.html` existiert":** Verhalten an
 * Dateiexistenz zu koppeln ist unsichtbare Magie. Irgendwann legt jemand das
 * Template nur an, um etwas zu gestalten, und öffnet dabei stillschweigend eine
 * Fläche, über die niemand entschieden hat.
 *
 * **Autoren- und Datumsarchive sind bewusst *nicht* schaltbar.** Sie zählen
 * Benutzernamen auf und nützen keinem B2B-Portal — das ist eine Eigenschaft
 * dieses Produkts und keine Kundenentscheidung.
 *
 * Ein Template braucht der Rückweg nicht: `templates/index.html` trägt eine
 * Beitragsschleife mit `inherit`, die auch eine Suche bedient. Sie sieht nicht
 * gut aus, aber sie funktioniert. `search.html`, `home.html` und `archive.html`
 * entstehen, wenn ein Kunde redaktionelle Inhalte wirklich mitbringt — gegen
 * eine echte Gestaltung statt gegen eine Vermutung. Notiert im Nebelabschnitt
 * der Karte `theme-templates-und-repo`.
 *
 * Die Bedingung dafür ist **wörtlich dieselbe**, die WooCommerce selbst
 * benutzt, um dieses Template vorzuziehen (`ProductSearchResultsTemplate`:
 * `is_search() && is_post_type_archive('product')`). Das ist der Grund, sie so
 * und nicht als eigene Prüfung auf `get_query_var('post_type')` zu schreiben:
 * beide Seiten meinen dann garantiert dieselbe Menge von Anfragen. Eine
 * gemischte Suche über Produkte *und* Seiten ist keine Produktsuche in diesem
 * Sinn — sie bekäme kein Template und fiele auf die Beitragsschleife zurück,
 * also fällt sie hier ins 404.
 *
 * `template_redirect` läuft im Template-Lader **vor** der Templateauswahl; ein
 * hier gesetztes 404 landet deshalb sauber auf `templates/404.html`.
 * `WP_Query::set_404()` setzt alle Anfrageflaggen zurück, nicht nur diese eine.
 *
 * ## Priorität 1, und warum das keine Kosmetik ist
 *
 * Am selben Haken hängt `redirect_canonical` mit Priorität 10 — und es kommt
 * bei gleicher Priorität zuerst, weil der Kern es früher registriert. Bei
 * Priorität 10 gemessen: `/?author=1` antwortete **301** mit
 * `Location: /author/temp_setup/`. Das 404 kam danach zwar auch, aber der
 * Benutzername stand da schon im Antwortkopf — genau die Aufzählung, gegen die
 * diese Abschaltung gebaut ist. Vor `redirect_canonical` gesetzt, gibt es die
 * Umleitung nicht mehr.
 */
add_filter('author_rewrite_rules', '__return_empty_array');
add_filter('date_rewrite_rules', '__return_empty_array');

add_action('template_redirect', static function (): void {
    if (is_admin() || is_embed()) {
        return;
    }

    /**
     * Ob die Website-Suche jenseits der Produktsuche bedient wird.
     *
     * @param bool $enabled Standardwert `false` — jede Suche ohne
     *                      `post_type=product` antwortet 404.
     */
    $site_search = (bool) apply_filters('lotzwoo_theme_site_search_enabled', false);

    $unwanted = is_author()
        || is_date()
        || (!$site_search && is_search() && !is_post_type_archive('product'));

    if (!$unwanted) {
        return;
    }

    global $wp_query;

    $wp_query->set_404();
    status_header(404);
    nocache_headers();
}, 1);

/**
 * Die leeren Regeln müssen einmal in die Datenbank.
 *
 * `author_rewrite_rules` und `date_rewrite_rules` wirken erst, wenn WordPress
 * die Regeln neu schreibt — bis dahin stehen die alten in `rewrite_rules`. Beim
 * Themewechsel erledigt das dieser Haken; auf einer Installation, in der das
 * Theme schon aktiv ist, einmal `wp rewrite flush`.
 */
add_action('after_switch_theme', 'flush_rewrite_rules');

/**
 * `lotzwoo/header-slot` — die Fläche, die das Plugin im Kopfbereich füllt.
 *
 * Schnellsuche, Favoriten-Zähler und Kundenchip gehören dem Plugin, stehen aber
 * im Kopfbereich des Themes. Sie brauchen einen Ort, den beide Seiten kennen.
 *
 * **Die Zuständigkeit ist mit Absicht herum:** das Theme registriert den Block
 * und rendert ihn leer, das Plugin füllt ihn per `render_block`-Filter. Das ist
 * die Richtung, die AD-8 verlangt — die Abhängigkeit steht im Theme, nie im
 * Plugin —, und sie hat drei Folgen: ohne Plugin rendert der Kopf **leer statt
 * kaputt**; das Plugin kann den Inhalt ändern, ohne das Theme anzufassen; und
 * schaltet jemand das Plugin ab, zeigt der Website-Editor keine Fehlerkachel.
 *
 * Der Rückgabewert ist die **leere Zeichenkette**, kein leeres `<div>`: die
 * Aktionsgruppe im Kopf ist ein Flex-Container mit `blockGap`, und ein leerer
 * Kasten darin verschiebt Konto-Symbol und Mini-Warenkorb um eine Lücke.
 *
 * Der Gegenhaken im Plugin lautet
 * `add_filter('render_block_lotzwoo/header-slot', …)` — mit Schrägstrich, wie
 * der Blockname. Dokumentiert in beiden READMEs, weil er die einzige Kopplung
 * zwischen zwei Repos ist und sonst in einem Jahr ein Rätsel wäre.
 *
 * Verworfen, mit Grund: **Hooked Blocks** (WP 6.4/6.5) positionieren nur
 * relativ zu einem Ankerblock — „nach der Navigation" beschreibt die Stelle
 * nicht mehr, sobald jemand den Kopf im Website-Editor umbaut. **`wp_body_open`**
 * liegt vor dem Kopfbereich, nicht darin. Ein **Shortcode** hätte keine
 * Editor-Vorschau, keine `theme.json`-Stile und keinen Block-Support.
 */
add_action('init', static function (): void {
    register_block_type(get_template_directory() . '/blocks/header-slot', [
        'render_callback' => static fn (): string => '',
    ]);
});

/**
 * `lotzwoo/cart-slot` — die Fläche, die das Plugin im Warenkorb füllt.
 *
 * Zweiter Slot, **anderer** Mechanismus, und der Unterschied steht in einer
 * Zeile: der Rückgabewert ist der Anker `<div id="lotzwoo-cart-slot"></div>`
 * und nicht die leere Zeichenkette.
 *
 * Der Grund (Ticket 15, Frage 1): die zurückgehaltenen Positionen liegen
 * **clientseitig**. `Store_Api_Bridge::data()` hängt sie als Cart-Extension an
 * die Store-API, `assets/js/b2b-cart.js` liest sie aus
 * `wp.data.select('wc/store/cart')` und zeichnet die Sektion selbst. Auf einer
 * Block-Seite feuert `woocommerce_after_cart_table` nie, und ein
 * `render_block`-Filter wie beim Kopf-Slot hätte beim Templaterendering gar
 * nichts zum Füllen — die Daten gibt es zu diesem Zeitpunkt noch nicht.
 *
 * Also liefert das Theme keinen Inhalt, sondern einen **Ort**: das JS sucht als
 * Erstes `document.getElementById('lotzwoo-cart-slot')` und hängt sich hinein,
 * wenn es das Element findet.
 *
 * Damit besteht diese Kopplung aus **zwei** Zeichenketten statt einer — dem
 * Blocknamen und der Div-ID. `scripts/check-frontend.php` prüft deshalb drei
 * Dinge und nicht zwei; die dritte Prüfung ruft diesen Callback auf und sucht
 * die ID in seiner Ausgabe.
 *
 * Ohne Plugin bleibt das Div leer. `style.css` blendet es dann per `:empty`
 * aus, damit im Flex-Container des Warenkorbs keine Lücke entsteht — dieselbe
 * Sorge, die beim Kopf-Slot zur leeren Zeichenkette geführt hat, hier aber
 * lösbar, weil der Anker das Einzige ist, was das Theme beitragen kann.
 *
 * `id` und nicht `class`: das JS liest `getElementById`, und eine ID sagt
 * zugleich, dass es die Stelle genau einmal gibt.
 */
add_action('init', static function (): void {
    register_block_type(get_template_directory() . '/blocks/cart-slot', [
        'render_callback' => static fn (): string => '<div id="lotzwoo-cart-slot"></div>',
    ]);
});

/**
 * Die Editor-Seiten derselben Blöcke.
 *
 * Eine reine PHP-Registrierung genügt dem Website-Editor nicht: er braucht eine
 * `edit`-Komponente in JavaScript, sonst steht an der Stelle „Dieser Block wird
 * von deiner Website nicht unterstützt". Das Skript hängt nicht in `block.json`
 * unter `editorScript`, weil WordPress dort eine `index.asset.php` neben der
 * Datei erwartet und ohne sie `_doing_it_wrong` auslöst — die entsteht in einem
 * Bauschritt, den dieses Repo nicht hat und für fünfzehn Zeilen nicht bekommt.
 *
 * Eine Schleife über die Slugs, seit es zwei sind: ein zweiter Block hätte
 * sonst einen zweiten Haken mit derselben Mechanik bedeutet, und ein dritter
 * einen dritten.
 *
 * Version aus der Dateizeit, aus demselben Grund wie beim Stylesheet.
 */
add_action('enqueue_block_editor_assets', static function (): void {
    foreach (['header-slot', 'cart-slot'] as $slug) {
        $script = get_template_directory() . "/blocks/{$slug}/index.js";

        if (!is_readable($script)) {
            continue;
        }

        wp_enqueue_script(
            "lotzwoo-{$slug}-editor",
            get_template_directory_uri() . "/blocks/{$slug}/index.js",
            ['wp-blocks', 'wp-element', 'wp-block-editor'],
            (string) filemtime($script),
            true
        );
    }
});

/**
 * Die fünf wiederkehrenden Bausteine als Block-Styles.
 *
 * Ticket 17. Das Importskript der Website baute dieselben fünf Bausteine an 45
 * Aufrufstellen aus Block-Attributen zusammen — Rahmen, Polsterung, Schriftgrad
 * je Aufruf ins `post_content` geschrieben. Jede Palettenänderung hätte damit 45
 * Stellen gehabt, und keine davon im Theme.
 *
 * Ein Block-Style ist eine registrierte Klasse plus CSS: das Skript schreibt
 * `is-style-…`, das Theme besitzt das Aussehen. **Eine** Quelle.
 *
 * Verworfen, mit Grund (entschieden 2026-08-12): **Patterns**. Ein
 * unsynchronisiertes Pattern wird beim Einfügen kopiert und ist konstruktions-
 * bedingt eine zweite Quelle, die lautlos auseinanderläuft; ein synchronisiertes
 * läge als `wp_block` in der Datenbank und widerspräche „das Repo ist die
 * Quelle, die Instanz das Abbild".
 *
 * Was ein Block-Style **nicht** trägt, ist die Zusammensetzung. `karte` ist
 * innen `h3` + `p`, `empfaenger` sind zwei Absätze — deren Aufbau bleibt im
 * Website-Repo. Hierher geht nur, wie sie aussehen.
 *
 * Das CSS steht in `style.css` und nicht als `inline_style` an dieser Stelle:
 * wer eine Farbe oder eine Polsterung sucht, sucht sie im Stylesheet, und ein
 * zweiter Ort für CSS ist genau der Fehler, den dieses Ticket abstellt. Dass
 * der Website-Editor dasselbe Stylesheet bekommt, regelt der Haken auf
 * `enqueue_block_assets` weiter unten.
 *
 * Die Namen sind eine Kopplung zum Website-Repo — `import-pages.py` schreibt
 * genau diese Zeichenketten. Wer einen umbenennt, muss dort nachziehen; hier
 * wird nichts rot.
 */
add_action('init', static function (): void {
    $styles = [
        'core/paragraph' => [
            'label' => __('Label', 'lotzwoo-theme-base'),
            'kennzahl' => __('Kennzahl', 'lotzwoo-theme-base'),
        ],
        'core/group' => [
            'karte' => __('Karte', 'lotzwoo-theme-base'),
            'abgesetzt' => __('Abgesetzt', 'lotzwoo-theme-base'),
            'empfaenger' => __('Empfänger', 'lotzwoo-theme-base'),
        ],
    ];

    foreach ($styles as $block => $variants) {
        foreach ($variants as $name => $label) {
            register_block_style($block, [
                'name' => $name,
                'label' => $label,
            ]);
        }
    }
});

/**
 * Das Stylesheet des Themes im Website-Editor.
 *
 * Ohne das hier sind die fünf Block-Styles oben zwar **auswählbar**, zeigen im
 * Editor aber nichts — die Registrierung meldet den Namen an, das Aussehen
 * steht in `style.css`, und die Editor-Leinwand bekam bis hierher aus diesem
 * Theme überhaupt kein CSS. `add_theme_support('editor-styles')` allein ist nur
 * die Erlaubnis, nicht die Zustellung. Derselbe Fehlerfall wie beim
 * `header-slot`: registriert ist nicht gleich sichtbar.
 *
 * `enqueue_block_assets` und nicht `add_editor_style()`, aus einem Grund, der
 * sich erst mit Kindtheme zeigt: `add_editor_style('style.css')` löst über
 * `get_theme_file_path()` auf, und das **bevorzugt das Kindtheme**. Bei
 * aktivem `lotzwoo-theme-bersta` hätte der Editor also die 4 KB des Kindes
 * bekommen und die 54 KB des Elternteils nie. Ein voller URI umginge das,
 * würde aber über `file_get_contents()` durchs Netz geladen.
 *
 * `enqueue_block_assets` läuft in beiden Welten; im Frontend hängt das
 * Stylesheet schon an `wp_enqueue_scripts`, deshalb der Riegel auf `is_admin()`.
 * Eltern zuerst, Kind danach — dieselbe Reihenfolge wie im Frontend, sonst
 * gewönne die falsche Seite.
 *
 * Version aus der Dateizeit, aus demselben Grund wie beim Stylesheet.
 */
add_action('enqueue_block_assets', static function (): void {
    if (!is_admin()) {
        return;
    }

    $parent = get_template_directory() . '/style.css';

    if (is_readable($parent)) {
        wp_enqueue_style(
            'lotzwoo-theme-base-editor',
            get_template_directory_uri() . '/style.css',
            [],
            (string) filemtime($parent)
        );
    }

    if (get_template_directory() === get_stylesheet_directory()) {
        return;
    }

    $child = get_stylesheet_directory() . '/style.css';

    if (!is_readable($child)) {
        return;
    }

    wp_enqueue_style(
        'lotzwoo-theme-child-editor',
        get_stylesheet_uri(),
        ['lotzwoo-theme-base-editor'],
        (string) filemtime($child)
    );
});

/**
 * Der eigene Kopfbereich auf der Kasse.
 *
 * WooCommerces `page-checkout.html` zieht einen Kopf-Part, der fest auf
 * `woocommerce/woocommerce` verdrahtet ist — Website-Titel und ein
 * Warenkorb-Link, sonst nichts. Es ist die einzige Seite des Kaufwegs ohne
 * Navigation und ohne Mini-Warenkorb: der Kunde verlässt an der teuersten
 * Stelle sichtbar den Shop. Alle anderen Woo-Templates schreiben
 * `{"slug":"header"}` ohne `theme` und bekommen die Teile dieses Themes von
 * allein.
 *
 * Geliefert wird **derselbe** `parts/header.html` wie überall. Ein eigener
 * Kassen-Kopf entsteht nicht, und Woos Kassen-Template wird nicht
 * überschrieben — AD-1 lässt den Inhalt der Kasse bei WooCommerce.
 *
 * Einen **Fuß** gibt es auf der Kasse weiterhin nicht. Woos Template hat gar
 * keinen Fuß-Part; einen hineinzubekommen hieße, das Template zu übernehmen.
 *
 * ## Warum dieser Hebel und kein anderer
 *
 * - `parts/checkout-header.html` im Theme greift **nicht**, doppelt gesperrt:
 *   der Kern bricht bei fremdem `theme`-Attribut ab
 *   (`wp-includes/blocks/template-part.php`, `get_stylesheet() === $theme`),
 *   und WooCommerce ersetzt zusätzlich den Render-Callback von
 *   `core/template-part` und löst stur gegen seine eigene Plugin-Datei auf
 *   (`BlockTemplatesController::render_woocommerce_template_part()`).
 * - `pre_get_block_file_template` ginge, aber dort hängt Woo selbst mit
 *   Priorität 10. `get_block_template` läuft nach Woos Auflösung.
 *
 * ## Zwei Eigenschaften des Kerns, auf denen das hier ruht
 *
 * `get_block_template()` gibt **früh** zurück, sobald es zu dieser Kennung
 * einen `wp_template_part`-Beitrag in der Datenbank gibt — dieser Filter läuft
 * dann gar nicht. Eine bewusste Anpassung im Website-Editor gewinnt also von
 * selbst, und der Filter kann sie nicht stillschweigend überschreiben.
 *
 * `get_stylesheet() . '//header'` löst über das Kindtheme auf und fällt auf die
 * Datei des Elternthemes zurück. Ein fest eingetragenes `lotzwoo-theme-base`
 * wäre falsch: es überginge einen Kopf, den ein Kindtheme mitbringt.
 *
 * ## Wie es bricht, wenn WooCommerce den Part umbenennt
 *
 * Dann trifft die Kennung nicht mehr, der Filter tut nichts, und auf der Kasse
 * steht wieder Woos eigener Kopf. Das ist **sichtbar** und liefert nichts
 * Falsches — der Grund, warum die Prüfung auf die vollständige Kennung geht und
 * nicht auf ein Muster, das auch den nächsten Namen noch fangen würde.
 */
add_filter('get_block_template', static function ($block_template, $id, $template_type) {
    if ($template_type !== 'wp_template_part' || $id !== 'woocommerce/woocommerce//checkout-header') {
        return $block_template;
    }

    // Ohne Woos Part gibt es nichts zu ersetzen. Der Kopf dieses Themes an
    // eine Stelle zu setzen, an der WooCommerce selbst nichts mehr erwartet,
    // wäre geraten und nicht gefiltert.
    if (!$block_template instanceof WP_Block_Template) {
        return $block_template;
    }

    // Der eigene Kopf wird über dieselbe Funktion geholt, in deren Filter wir
    // gerade stehen. Die Kennung ist eine andere, die Bedingung oben greift
    // also nicht — der Riegel steht trotzdem, weil ein Rekursionsschutz, den
    // man erst braucht, wenn er fehlt, zu spät kommt.
    static $running = false;

    if ($running) {
        return $block_template;
    }

    $running = true;
    $own = get_block_template(get_stylesheet() . '//header', 'wp_template_part');
    $running = false;

    if (!$own instanceof WP_Block_Template || trim((string) $own->content) === '') {
        return $block_template;
    }

    // Kopie, nicht Mutation: was hier hereinkommt, kann aus einem Cache des
    // Kerns stammen, und ein verändertes Objekt darin verändert jeden
    // späteren Aufruf mit.
    $checkout_header = clone $block_template;
    $checkout_header->content = $own->content;

    return $checkout_header;
}, 10, 3);

/**
 * Updates über GitHub, ohne einen eigenen Updater mitzuschleppen.
 *
 * Seit WordPress 6.1 genügt der `Update URI`-Header plus ein Filter, dessen
 * Name aus dem Hostnamen dieses Headers gebildet wird. Der Kern übernimmt
 * damit Versionsvergleich, `no_update`-Pflege und den Kollisionsschutz — gut
 * 500 der 545 Zeilen, die der `GitHub_Updater` des Plugins dafür selbst
 * mitbringt und die hier kein zweites Mal gepflegt werden.
 *
 * Der Kern ruft die URL im Header **nie** ab. Sie ist Kennung, nicht Endpunkt:
 * das Paket kommt aus `package` im Rückgabewert dieses Filters. Deshalb darf
 * dort etwas stehen, das ein Mensch aufmachen kann.
 *
 * `update_themes_github.com` ist ein **geteilter** Namensraum — jedes andere
 * Theme, das denselben Weg geht, hängt am selben Filter. Der Callback prüft
 * darum zuerst, ob er überhaupt gemeint ist, und gibt sonst unverändert
 * zurück, was er bekommen hat.
 *
 * Der Rückgabewert ist ein **Array**, kein Objekt: Themes laufen über
 * `update_themes` statt `update_plugins`, und dort erwartet der Kern ein
 * Array mit `theme`, `url` und `package`. Ein Objekt wird stillschweigend
 * verworfen.
 *
 * ## `version` **und** `new_version`, und warum das kein Gürtel-und-Hosenträger ist
 *
 * `wp_update_themes()` prüft die Antwort dieses Filters so:
 *
 * ```php
 * // Is it valid? We require at least a version.
 * if ( ! isset( $update->version ) ) { continue; }
 * // WordPress needs the version field specified as 'new_version'.
 * if ( ! isset( $update->new_version ) ) { $update->new_version = $update->version; }
 * ```
 *
 * Der Schlüssel, über den entschieden wird, heißt **`version`**. `new_version`
 * leitet der Kern daraus ab — es ist der *abgeleitete* Name, nicht der
 * geforderte. Eine Antwort mit nur `new_version` fällt durch das `continue`
 * und wird **stillschweigend** verworfen: kein Fehler, kein Eintrag im
 * Transienten, in der Themes-Übersicht schlicht kein Update.
 *
 * Genau das ist am 2026-08-11 passiert. Installiert `0.2.0`, veröffentlicht
 * `0.3.0`, der Filter feuerte und gab das richtige Paket zurück — und
 * `update_themes->response` blieb leer. Die Read-only-Probe aus Ticket 07 hat
 * geprüft, dass der Filter ein Array liefert, aber nicht, dass der Kern es
 * annimmt. Das ist der Unterschied zwischen „geprüft" und „gelaufen".
 *
 * `new_version` bleibt trotzdem stehen: der Kern setzt es sonst selbst, und
 * die Stelle liest sich besser, wenn dort steht, was ankommt.
 */
add_filter('update_themes_github.com', static function ($update, array $theme_data, string $theme_stylesheet) {
    if ($theme_stylesheet !== 'lotzwoo-theme-base') {
        return $update;
    }

    $uri = $theme_data['UpdateURI'] ?? '';

    if (!is_string($uri) || !str_starts_with($uri, 'https://github.com/somesunnymind/lotzwoo-theme-base')) {
        return $update;
    }

    $installed = $theme_data['Version'] ?? '0';

    $response = wp_remote_get(
        'https://api.github.com/repos/somesunnymind/lotzwoo-theme-base/releases/latest',
        [
            'timeout' => 10,
            'headers' => [
                'Accept' => 'application/vnd.github+json',
                'User-Agent' => 'lotzwoo-theme-base/' . $installed,
            ],
        ]
    );

    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
        return $update;
    }

    $release = json_decode(wp_remote_retrieve_body($response), true);

    if (!is_array($release) || empty($release['tag_name'])) {
        return $update;
    }

    $latest = ltrim((string) $release['tag_name'], 'v');

    // Der Kern vergleicht selbst noch einmal. Die Prüfung hier spart nur den
    // Fall, in dem wir ein Paket anbieten, das gar keines ist.
    if (version_compare($latest, (string) $installed, '<=')) {
        return $update;
    }

    $package = '';

    foreach ($release['assets'] ?? [] as $asset) {
        if (($asset['name'] ?? '') === 'lotzwoo-theme-base.zip') {
            $package = (string) ($asset['browser_download_url'] ?? '');
            break;
        }
    }

    // Ohne Paket kein Update. Die Quell-Archive, die GitHub automatisch
    // beilegt, taugen nicht: ihr einziges Unterverzeichnis heisst
    // `lotzwoo-theme-base-<tag>` und nicht `lotzwoo-theme-base`, und WordPress würde das
    // Theme unter diesem Namen installieren — womit das Kindtheme sein
    // Elterntheme verliert.
    if ($package === '') {
        return $update;
    }

    // `$theme_data` traegt nur acht Felder — Name, Title, Version, Author,
    // Author URI, UpdateURI, Template, Stylesheet (`wp_update_themes()`).
    // `RequiresWP` und `RequiresPHP` sind **nicht** darunter; die frueheren
    // `$theme_data['RequiresWP'] ?? ''` lieferten deshalb immer die leere
    // Zeichenkette, und `is_wp_version_compatible('')` gibt true zurueck: die
    // Vertraeglichkeitspruefung in der Themes-Uebersicht lief leer. Gemessen am
    // 2026-08-11 an der echten Aktualisierungszeile.
    //
    // Was hier steht, ist die Anforderung der **installierten** Fassung, nicht
    // die der angebotenen — die stuende in der `style.css` des Tags und kostete
    // einen zweiten Abruf bei jeder Update-Pruefung. Solange die Anforderung
    // zwischen zwei Versionen steigt, ist das eine Naeherung; sie ist immer noch
    // besser als der leere Wert, der gar nichts prueft. Steigt sie einmal
    // wirklich, gehoert der zweite Abruf hierher.
    $installed_theme = wp_get_theme($theme_stylesheet);

    return [
        'theme' => 'lotzwoo-theme-base',
        'version' => $latest,
        'new_version' => $latest,
        'url' => (string) ($release['html_url'] ?? $uri),
        'package' => $package,
        'requires' => (string) $installed_theme->get('RequiresWP'),
        'requires_php' => (string) $installed_theme->get('RequiresPHP'),
    ];
}, 10, 3);
