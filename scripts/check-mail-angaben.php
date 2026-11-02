<?php

/**
 * Prüft, dass ein Kindtheme Logo, Fußzeile und Schrift der Mails liefert, ohne
 * dass ein gespeichertes Formular daraus eine Eingabe macht.
 *
 *     sg www-data -c "wp --path=<site> eval-file <basis>/scripts/check-mail-angaben.php [<kindtheme-repo>]"
 *
 * Ohne Argument prüft es das aktive Kindtheme. Mit Argument liest es die Angaben
 * aus diesem Verzeichnis und, solange die Basis dort nicht ausgeliefert ist,
 * `inc/mail-angaben.php` aus diesem Repo: so lässt sich vor dem Deploy prüfen.
 *
 * ## Es schreibt nicht
 *
 * Die Prüfung fährt das echte `update_option()` und das echte
 * `WC_Admin_Settings::save_fields()`. Damit das nicht in die Datenbank geht,
 * wird jede schreibende Abfrage vor dem Absenden durch `SELECT 1` ersetzt, und
 * jeder HTTP-Abruf abgewiesen. Leere und fehlende Zeilen werden im Objekt-Cache
 * dieses Prozesses nachgestellt. Deshalb verweigert sich das Skript, wenn ein
 * persistenter Objekt-Cache läuft: der würde die nachgestellten Zeilen behalten.
 * Am Ende vergleicht es die Zeilen direkt in der Datenbank.
 */

if (!defined('ABSPATH')) {
    fwrite(STDERR, "Nur über wp eval-file.\n");
    exit(1);
}

if (wp_using_ext_object_cache()) {
    fwrite(STDERR, "Persistenter Objekt-Cache aktiv: die Prüfung würde ihn beschreiben.\n");
    exit(1);
}

if (!class_exists('WooCommerce')) {
    fwrite(STDERR, "WooCommerce fehlt.\n");
    exit(1);
}

const MAIL_FELDER = [
    'woocommerce_email_header_image',
    'woocommerce_email_footer_text',
    'woocommerce_email_font_family',
];

// `wp eval-file` läuft in einer Funktion: Variablen hier oben sind nicht global,
// und ein `global $fehler` in `pruefe()` zählte ins Leere — grün trotz FEHL.
$GLOBALS['lotzwoo_pruefung'] = ['fehler' => [], 'geprueft' => 0, 'geschrieben' => []];

function db_zeilen(array $namen): array
{
    global $wpdb;

    $zeilen = [];

    foreach ($namen as $name) {
        $zeile = $wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $name));
        $zeilen[$name] = $zeile;
    }

    return $zeilen;
}

$db_vorher = db_zeilen(MAIL_FELDER);

add_filter('query', static function (string $abfrage) {
    return preg_match('/^\s*(INSERT|UPDATE|DELETE|REPLACE|ALTER|DROP|TRUNCATE|CREATE)\b/i', $abfrage) ? 'SELECT 1' : $abfrage;
}, PHP_INT_MAX);
add_filter('pre_http_request', static fn() => new WP_Error('lotzwoo_pruefung', 'Prüfung ohne HTTP'), PHP_INT_MAX);

add_action('updated_option', static function (string $name, $alt, $neu): void {
    if (in_array($name, MAIL_FELDER, true)) {
        $GLOBALS['lotzwoo_pruefung']['geschrieben'][$name] = $neu;
    }
}, 10, 3);
add_action('added_option', static function (string $name, $neu): void {
    if (in_array($name, MAIL_FELDER, true)) {
        $GLOBALS['lotzwoo_pruefung']['geschrieben'][$name] = $neu;
    }
}, 10, 2);

function pruefe(string $name, bool $ok, string $detail = ''): void
{
    ++$GLOBALS['lotzwoo_pruefung']['geprueft'];
    echo ($ok ? '  ok    ' : '  FEHL  ') . $name . ($detail !== '' ? " — {$detail}" : '') . "\n";

    if (!$ok) {
        $GLOBALS['lotzwoo_pruefung']['fehler'][] = $name;
    }
}

/** Beleg: keine Zusage dieses Themes, sondern die Messung, warum es so gebaut ist. */
function beleg(string $text): void
{
    echo "  beleg {$text}\n";
}

/** Stellt eine Zeile im Cache dieses Prozesses nach. `null` heißt: die Zeile fehlt. */
function zeile(string $name, ?string $wert): void
{
    $alle = wp_load_alloptions();
    unset($alle[$name]);
    wp_cache_set('alloptions', $alle, 'options');

    $fehlend = wp_cache_get('notoptions', 'options');
    $fehlend = is_array($fehlend) ? $fehlend : [];
    unset($fehlend[$name]);
    wp_cache_delete($name, 'options');

    if ($wert === null) {
        $fehlend[$name] = true;
    } else {
        wp_cache_set($name, $wert, 'options');
    }

    wp_cache_set('notoptions', $fehlend, 'options');
}

function alle_zeilen(?string $wert): void
{
    foreach (MAIL_FELDER as $name) {
        zeile($name, $wert);
    }
}

/** Was in der nachgestellten Zeile steht, ohne die Filter. `null` heißt: fehlt. */
function zeile_roh(string $name): ?string
{
    $alle = wp_load_alloptions();

    if (isset($alle[$name])) {
        return (string) $alle[$name];
    }

    $fehlend = wp_cache_get('notoptions', 'options');

    if (is_array($fehlend) && isset($fehlend[$name])) {
        return null;
    }

    $wert = wp_cache_get($name, 'options');

    return $wert === false ? null : (string) $wert;
}

function formular_felder(): array
{
    include_once WC_ABSPATH . 'includes/admin/class-wc-admin-settings.php';

    foreach (WC_Admin_Settings::get_settings_pages() as $seite) {
        if ($seite->get_id() === 'email') {
            return array_values(array_filter(
                $seite->get_settings_for_section(''),
                static fn(array $feld): bool => in_array($feld['id'] ?? '', MAIL_FELDER, true)
            ));
        }
    }

    return [];
}

/**
 * Rendert die drei Felder und liest sie zurück, wie ein Browser sie absendet.
 * Ein `select` ohne `selected` schickt seine erste Option.
 */
function formular_absenden(array $felder): array
{
    ob_start();
    WC_Admin_Settings::output_fields($felder);
    $html = (string) ob_get_clean();
    $daten = [];

    if (preg_match('/<input[^>]*name="woocommerce_email_header_image"[^>]*value="([^"]*)"/s', $html, $treffer)) {
        $daten['woocommerce_email_header_image'] = html_entity_decode($treffer[1], ENT_QUOTES);
    }

    if (preg_match('/<textarea[^>]*name="woocommerce_email_footer_text"[^>]*>(.*?)<\/textarea>/s', $html, $treffer)) {
        $daten['woocommerce_email_footer_text'] = html_entity_decode($treffer[1], ENT_QUOTES);
    }

    if (preg_match('/<select[^>]*name="woocommerce_email_font_family".*?<\/select>/s', $html, $select)) {
        preg_match_all('/<option\s[^>]*value="([^"]*)"[^>]*?(selected)?[^>]*>/s', $select[0], $optionen, PREG_SET_ORDER);
        $gewaehlt = array_values(array_filter($optionen, static fn(array $o): bool => str_contains($o[0], 'selected')));
        $daten['woocommerce_email_font_family'] = ($gewaehlt[0] ?? $optionen[0])[1];
    }

    return $daten;
}

function formular_speichern(array $felder, array $daten): array
{
    $GLOBALS['lotzwoo_pruefung']['geschrieben'] = [];
    WC_Admin_Settings::save_fields($felder, $daten);

    return $GLOBALS['lotzwoo_pruefung']['geschrieben'];
}

$repo = dirname(__DIR__);
$kind = isset($args[0]) ? rtrim((string) realpath($args[0]), '/') : '';

if (isset($args[0]) && $kind === '') {
    fwrite(STDERR, "Kindtheme-Verzeichnis nicht gefunden: {$args[0]}\n");
    exit(1);
}

$stilverzeichnis = '';

add_filter('stylesheet_directory', static function (string $pfad) use (&$stilverzeichnis): string {
    return $stilverzeichnis !== '' ? $stilverzeichnis : $pfad;
});
add_filter('stylesheet_directory_uri', static function (string $uri) use (&$stilverzeichnis): string {
    return $stilverzeichnis !== '' ? dirname($uri) . '/' . basename($stilverzeichnis) : $uri;
});

$stilverzeichnis = $kind;

$im_theme_geladen = function_exists('lotzwoo_mail_angaben');

if (!$im_theme_geladen && is_file($repo . '/inc/mail-angaben.php')) {
    require_once $repo . '/inc/mail-angaben.php';
}

if (!function_exists('lotzwoo_mail_angaben')) {
    echo "FEHL  inc/mail-angaben.php fehlt, nichts zu prüfen.\n";
    exit(1);
}

echo 'Basis: ' . ($im_theme_geladen ? 'ausgeliefert' : 'aus dem Repo') . ' · Kindtheme: ' . get_stylesheet_directory() . "\n";

$erwartet = lotzwoo_mail_angaben();
$logo = $erwartet['woocommerce_email_header_image'] ?? '';
$fuss = $erwartet['woocommerce_email_footer_text'] ?? '';
$schrift = $erwartet['woocommerce_email_font_family'] ?? '';

echo "\n1. Die Datei des Kindthemes\n";

pruefe('Fußzeile geliefert', $fuss !== '');
pruefe('Logo geliefert', $logo !== '', $logo);
pruefe('Logo ist PNG', (bool) preg_match('/\.png$/', $logo));
pruefe(
    'Logo-URL trägt das Schema der Seite, auch ohne HTTPS-Anfrage (CLI, Cron)',
    wp_parse_url($logo, PHP_URL_SCHEME) === wp_parse_url((string) get_option('home'), PHP_URL_SCHEME)
);
pruefe('Schrift geliefert', $schrift !== '', $schrift);
pruefe(
    'Schrift steht in Woos Auswahl — sonst wählt das Formular still die erste',
    isset(\Automattic\WooCommerce\Internal\Email\EmailFont::$font[$schrift])
);

$logodatei = get_stylesheet_directory() . '/' . ltrim((string) preg_replace('~^.*/themes/[^/]+/~', '', $logo), '/');

if (is_file($logodatei)) {
    $masse = getimagesize($logodatei);
    pruefe('Logodatei ist ein PNG', ($masse['mime'] ?? '') === 'image/png', sprintf('%d×%d, %d kB', $masse[0], $masse[1], filesize($logodatei) / 1024));
    pruefe('Logo nicht über 400 px breit — Outlook ignoriert die CSS-Breite', $masse[0] <= 400);
} else {
    pruefe('Logodatei liegt im Kindtheme', false, $logodatei);
}

echo "\n2. Rangfolge: Eingabe vor Kindtheme\n";

alle_zeilen('EINGABE');
foreach (MAIL_FELDER as $name) {
    pruefe("Zeile gefüllt: {$name} bleibt Eingabe", get_option($name) === 'EINGABE');
}

echo "\n3. Falle 1: leere und fehlende Zeile\n";

alle_zeilen('');
foreach (MAIL_FELDER as $name) {
    pruefe("Zeile leer: {$name}", get_option($name) === $erwartet[$name]);
}

alle_zeilen('   ');
pruefe('Zeile nur Leerzeichen zählt als leer', get_option('woocommerce_email_footer_text') === $fuss);

alle_zeilen(null);
foreach (MAIL_FELDER as $name) {
    pruefe("Zeile fehlt: {$name}", get_option($name) === $erwartet[$name]);
}

pruefe('Zeile fehlt, Aufrufer gibt Vorgabe mit: Kindtheme gewinnt', get_option('woocommerce_email_font_family', 'Vorgabe') === $schrift);

remove_filter('default_option_woocommerce_email_footer_text', 'lotzwoo_mail_wert_vorgabe', 10);
beleg('nur option_*, Zeile fehlt: ' . var_export(get_option('woocommerce_email_footer_text'), true) . ' statt der Fußzeile');
add_filter('default_option_woocommerce_email_footer_text', 'lotzwoo_mail_wert_vorgabe', 10, 2);

echo "\n4. Falle 2: Speichern im Woo-Formular\n";

$felder = formular_felder();
pruefe('Formular kennt alle drei Felder', count($felder) === 3);

foreach (['leer' => '', 'fehlt' => null] as $zustand => $wert) {
    alle_zeilen($wert);
    $abgesendet = formular_absenden($felder);
    pruefe("Zeile {$zustand}: Schrift-Auswahl zeigt das Kindtheme", ($abgesendet['woocommerce_email_font_family'] ?? '') === $schrift);
    pruefe("Zeile {$zustand}: Formular trägt das Logo", ($abgesendet['woocommerce_email_header_image'] ?? '') === $logo);
    pruefe(
        "Zeile {$zustand}: Formular trägt die Fußzeile",
        lotzwoo_mail_wert_vergleichbar($abgesendet['woocommerce_email_footer_text'] ?? '') === lotzwoo_mail_wert_vergleichbar($fuss)
    );
    $schreibt = formular_speichern($felder, $abgesendet);

    foreach (MAIL_FELDER as $name) {
        pruefe(
            "Zeile {$zustand}, Speichern ohne Änderung: {$name} wird keine Eingabe",
            in_array(zeile_roh($name), ['', null], true) && !in_array(trim((string) ($schreibt[$name] ?? '')), [$erwartet[$name]], true),
            'Zeile danach: ' . var_export(zeile_roh($name), true)
        );
        pruefe("Zeile {$zustand}, nach dem Speichern: {$name} kommt weiter vom Kindtheme", get_option($name) === $erwartet[$name]);
    }
}

alle_zeilen('');
$abgesendet = formular_absenden($felder);
$schreibt = formular_speichern($felder, array_replace($abgesendet, [
    'woocommerce_email_footer_text' => 'Eigener Text',
    'woocommerce_email_font_family' => $schrift === 'Georgia' ? 'Verdana' : 'Georgia',
]));
pruefe('Geänderter Text wird Eingabe', zeile_roh('woocommerce_email_footer_text') === 'Eigener Text');
pruefe('Geänderte Schrift wird Eingabe', zeile_roh('woocommerce_email_font_family') === ($schrift === 'Georgia' ? 'Verdana' : 'Georgia'));
pruefe('Eingabe schlägt das Kindtheme', get_option('woocommerce_email_footer_text') === 'Eigener Text');

$schreibt = formular_speichern($felder, array_replace($abgesendet, ['woocommerce_email_footer_text' => '']));
pruefe('Feld geleert: Zeile leer', zeile_roh('woocommerce_email_footer_text') === '');
pruefe('Feld geleert: Kindtheme übernimmt wieder', get_option('woocommerce_email_footer_text') === $fuss);

zeile('woocommerce_email_footer_text', 'EINGABE');
update_option('woocommerce_email_footer_text', '');
pruefe('update_option leert eine gefüllte Zeile', zeile_roh('woocommerce_email_footer_text') === '');
pruefe('Danach übernimmt das Kindtheme', get_option('woocommerce_email_footer_text') === $fuss);

echo "\n  Woo-Update: `create_options()` ruft add_option für jedes Feld\n";

foreach (['leer' => '', 'gleich dem Kindtheme' => $fuss, 'Eingabe' => 'EINGABE'] as $zustand => $wert) {
    zeile('woocommerce_email_footer_text', $wert);
    add_option('woocommerce_email_footer_text', 'WOO-STANDARD', '', false);
    pruefe("Zeile {$zustand}: add_option lässt sie stehen", zeile_roh('woocommerce_email_footer_text') === $wert);
}

$ungeprueft = static fn($vorgabe, string $option) => lotzwoo_mail_angaben()[$option] ?? $vorgabe;
zeile('woocommerce_email_footer_text', '');
remove_filter('default_option_woocommerce_email_footer_text', 'lotzwoo_mail_wert_vorgabe', 10);
add_filter('default_option_woocommerce_email_footer_text', $ungeprueft, 10, 2);
add_option('woocommerce_email_footer_text', 'WOO-STANDARD', '', false);
beleg('default_option_* ohne Zeilenprüfung, leere Zeile, add_option: ' . var_export(zeile_roh('woocommerce_email_footer_text'), true));
remove_filter('default_option_woocommerce_email_footer_text', $ungeprueft, 10);
add_filter('default_option_woocommerce_email_footer_text', 'lotzwoo_mail_wert_vorgabe', 10, 2);

echo "\n  Gegenprobe, was der naheliegende Weg täte:\n";

$im_formular_aus = static function () use ($felder): array {
    alle_zeilen('');

    foreach (MAIL_FELDER as $name) {
        remove_filter("option_{$name}", 'lotzwoo_mail_wert_fuellen', 10);
    }

    $abgesendet = formular_absenden($felder);
    $schreibt = formular_speichern($felder, $abgesendet);

    foreach (MAIL_FELDER as $name) {
        add_filter("option_{$name}", 'lotzwoo_mail_wert_fuellen', 10, 2);
    }

    return $schreibt;
};
beleg('Filter im Formular aus, Zeile leer, Speichern schreibt Schrift ' . var_export($im_formular_aus()['woocommerce_email_font_family'] ?? null, true) . ' als Eingabe');

alle_zeilen('');
foreach (MAIL_FELDER as $name) {
    remove_filter("pre_update_option_{$name}", 'lotzwoo_mail_wert_speichern', 10);
}
$schreibt = formular_speichern($felder, formular_absenden($felder));
foreach (MAIL_FELDER as $name) {
    add_filter("pre_update_option_{$name}", 'lotzwoo_mail_wert_speichern', 10, 3);
}
beleg('Filter an, ohne Speicher-Sperre, Speichern schreibt: ' . wp_json_encode(array_keys($schreibt), JSON_UNESCAPED_SLASHES) . ' (nur was Woos Bereinigung ändert)');

echo "\n5. Falle 3: Seiten-CSS\n";

$als_custom = new WP_Theme_JSON(['version' => 3, 'settings' => ['custom' => ['mail' => ['footerText' => $fuss]]]]);
$css_custom = $als_custom->get_stylesheet(['variables']);
beleg('settings.custom im CSS: ' . (str_contains($css_custom, '--wp--custom--mail--footer-text') ? 'ja, ' . strlen($css_custom) . ' Byte mit der ganzen Fußzeile' : 'nein'));

$kern = wp_strip_all_tags(explode('<br', $fuss)[0]);
$fusszeilen = array_map('trim', array_map('wp_strip_all_tags', preg_split('~<br\s*/?>~', $fuss)));
$im_text = static fn(string $text): bool => array_filter($fusszeilen, static fn(string $zeile): bool => str_contains($text, $zeile)) !== [];

pruefe('Kindtheme hat eine theme.json', is_file(get_stylesheet_directory() . '/theme.json'));
pruefe('Keine Fußzeilen-Zeile in theme.json des Kindthemes', !$im_text((string) file_get_contents(get_stylesheet_directory() . '/theme.json')));
// Liest das ausgelieferte Theme, nicht das Argument: erst nach dem Deploy eine Aussage.
pruefe('Keine Fußzeilen-Zeile im Seiten-CSS der laufenden Seite', !$im_text(wp_get_global_stylesheet()));

echo "\n6. Gerendert, wie Woo die Mail baut\n";

alle_zeilen('');
WC()->mailer();
$kopf = wc_get_template_html('emails/email-header.php', ['email_heading' => 'Prüfung', 'email' => null]);
$fusszeile = wc_get_template_html('emails/email-footer.php', ['email' => null]);
$stile = wc_get_template_html('emails/email-styles.php', ['email' => null]);

pruefe('Kopf zeigt das Logo des Kindthemes', str_contains($kopf, 'src="' . esc_url($logo) . '"'));
pruefe('Fuß zeigt die Fußzeile', str_contains(html_entity_decode($fusszeile, ENT_QUOTES), html_entity_decode($kern, ENT_QUOTES)));
pruefe('Stile tragen die Schrift', str_contains($stile, \Automattic\WooCommerce\Internal\Email\EmailFont::$font[$schrift] ?? '---'));
pruefe('Fuß enthält keinen offenen Platzhalter', !preg_match('/\{(site_title|site_url|store_address|store_email)\}/', $fusszeile));

echo "\n7. Ohne Datei und mit kaputter Datei\n";

$stilverzeichnis = $repo;
alle_zeilen('');
pruefe('Basis liefert selbst nichts', lotzwoo_mail_angaben() === []);
pruefe('Ohne Datei bleibt das Feld leer', get_option('woocommerce_email_footer_text') === '');
alle_zeilen(null);
pruefe('Ohne Datei bleibt Woos Vorgabe', get_option('woocommerce_email_font_family', 'Helvetica') === 'Helvetica');

$tmp = trailingslashit(sys_get_temp_dir()) . 'lotzwoo-mail-' . wp_generate_password(8, false);
$aufraeumen = [];

$fall = static function (string $inhalt, array $dateien = []) use ($tmp, &$stilverzeichnis, &$aufraeumen): array {
    $verzeichnis = $tmp . '-' . count($aufraeumen) . '/kind';
    mkdir($verzeichnis . '/assets', 0700, true);
    file_put_contents($verzeichnis . '/mail-angaben.json', $inhalt);
    $aufraeumen[] = $verzeichnis;

    foreach ($dateien as $datei) {
        file_put_contents($verzeichnis . '/' . $datei, 'x');
    }

    $stilverzeichnis = $verzeichnis;

    return @lotzwoo_mail_angaben();
};

$protokoll = (string) tempnam(sys_get_temp_dir(), 'lotzwoo-mail-log');
$protokoll_vorher = ini_set('error_log', $protokoll);

pruefe('Ungültiges JSON: keine Angaben, kein Abbruch', $fall('{kaputt') === []);
pruefe('Ungültiges JSON steht im Fehlerprotokoll, auch ohne WP_DEBUG', str_contains((string) file_get_contents($protokoll), 'kein gültiges JSON'));
pruefe('Kein Objekt: keine Angaben', $fall('"Text"') === []);
pruefe('Schrift außerhalb von Woos Auswahl fällt aus', $fall('{"font_family":"Open Sans"}') === []);
pruefe('Logo mit Leerzeichen im Namen: kein Logo', !isset($fall('{"header_image":"assets/mein logo.png"}', ['assets/mein logo.png'])['woocommerce_email_header_image']));
pruefe(
    'Falscher Typ: nur dieses Feld fällt aus',
    array_keys($fall('{"footer_text":["x"],"font_family":"Arial"}')) === ['woocommerce_email_font_family']
);
pruefe('Logodatei fehlt: kein Logo', !isset($fall('{"header_image":"assets/logo.png"}')['woocommerce_email_header_image']));
pruefe('Logo liegt da: URL im Kindtheme', isset($fall('{"header_image":"assets/logo.png"}', ['assets/logo.png'])['woocommerce_email_header_image']));
pruefe('Logo kein PNG: kein Logo', !isset($fall('{"header_image":"assets/logo.svg"}', ['assets/logo.svg'])['woocommerce_email_header_image']));
pruefe('Logo außerhalb des Themes: kein Logo', !isset($fall('{"header_image":"../logo.png"}', ['../logo.png'])['woocommerce_email_header_image']));

ini_set('error_log', (string) $protokoll_vorher);
unlink($protokoll);

foreach ($aufraeumen as $verzeichnis) {
    @unlink(dirname($verzeichnis) . '/logo.png');
    @unlink($verzeichnis . '/assets/logo.png');
    @unlink($verzeichnis . '/assets/mein logo.png');
    @unlink($verzeichnis . '/assets/logo.svg');
    @unlink($verzeichnis . '/mail-angaben.json');
    @rmdir($verzeichnis . '/assets');
    @rmdir($verzeichnis);
    @rmdir(dirname($verzeichnis));
}

echo "\n8. Die Datenbank\n";

// Die Sperre bleibt bis zum Prozessende, auch für die `shutdown`-Haken.
pruefe('Die drei Zeilen sind unverändert', db_zeilen(MAIL_FELDER) === $db_vorher);

echo "\n" . str_repeat('-', 70) . "\n";
printf("%d geprüft — %d Fehler\n", $GLOBALS['lotzwoo_pruefung']['geprueft'], count($GLOBALS['lotzwoo_pruefung']['fehler']));

if ($GLOBALS['lotzwoo_pruefung']['fehler'] === []) {
    exit(0);
}

foreach ($GLOBALS['lotzwoo_pruefung']['fehler'] as $text) {
    echo "  · {$text}\n";
}

exit(1);
