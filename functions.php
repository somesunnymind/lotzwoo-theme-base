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

    load_theme_textdomain('lotzapp-base', get_template_directory() . '/languages');
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
        'lotzapp-base',
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
        'lotzapp-child',
        get_stylesheet_uri(),
        ['lotzapp-base'],
        (string) filemtime($child)
    );
});

/**
 * Mark the full-width template on the body, so the stylesheet can widen it.
 *
 * `wp_is_block_theme()` templates carry no body class of their own for a custom
 * template, and the alternative — widening every page and narrowing the rest —
 * gets the default wrong for the pages that are ordinary prose.
 */
add_filter('body_class', static function (array $classes): array {
    if (is_page() && get_page_template_slug() === 'page-full-width') {
        $classes[] = 'lotzwoo-page-full-width';
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
 * Array mit `theme`, `new_version`, `url` und `package`. Ein Objekt wird
 * stillschweigend verworfen.
 */
add_filter('update_themes_github.com', static function ($update, array $theme_data, string $theme_stylesheet) {
    if ($theme_stylesheet !== 'lotzapp-base') {
        return $update;
    }

    $uri = $theme_data['UpdateURI'] ?? '';

    if (!is_string($uri) || !str_starts_with($uri, 'https://github.com/somesunnymind/lotzapp-base')) {
        return $update;
    }

    $installed = $theme_data['Version'] ?? '0';

    $response = wp_remote_get(
        'https://api.github.com/repos/somesunnymind/lotzapp-base/releases/latest',
        [
            'timeout' => 10,
            'headers' => [
                'Accept' => 'application/vnd.github+json',
                'User-Agent' => 'lotzapp-base/' . $installed,
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
        if (($asset['name'] ?? '') === 'lotzapp-base.zip') {
            $package = (string) ($asset['browser_download_url'] ?? '');
            break;
        }
    }

    // Ohne Paket kein Update. Die Quell-Archive, die GitHub automatisch
    // beilegt, taugen nicht: ihr einziges Unterverzeichnis heisst
    // `lotzapp-base-<tag>` und nicht `lotzapp-base`, und WordPress würde das
    // Theme unter diesem Namen installieren — womit das Kindtheme sein
    // Elterntheme verliert.
    if ($package === '') {
        return $update;
    }

    return [
        'theme' => 'lotzapp-base',
        'new_version' => $latest,
        'url' => (string) ($release['html_url'] ?? $uri),
        'package' => $package,
        'requires' => $theme_data['RequiresWP'] ?? '',
        'requires_php' => $theme_data['RequiresPHP'] ?? '',
    ];
}, 10, 3);
