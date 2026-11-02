<?php

/**
 * Logo, Fußzeile und Schrift der Mails kommen aus dem aktiven Kindtheme.
 *
 * Ein Kindtheme hat kein PHP. Es legt `mail-angaben.json` neben seine
 * `theme.json`, diese Datei liest sie:
 *
 *     {
 *       "header_image": "assets/email/logo.png",
 *       "footer_text": "Firma<br />Adresse",
 *       "font_family": "Helvetica"
 *     }
 *
 * Rangfolge: was im Woo-Feld steht, gewinnt. Das Kindtheme füllt nur ein leeres
 * Feld. Die Basis selbst liefert nichts.
 *
 * ## Warum eine eigene Datei und nicht `settings.custom`
 *
 * `settings.custom` in `theme.json` wird zu CSS-Variablen. Die Fußzeile stünde
 * mit Firmenbuchnummer in jeder Seite. `scripts/check-mail-angaben.php` misst das.
 *
 * ## Warum drei Haken
 *
 * `option_*` feuert nicht, wenn die Zeile fehlt. Dafür `default_option_*`.
 *
 * `add_option()` fragt denselben Filter und hält die Zeile für nicht vorhanden,
 * wenn er dasselbe liefert wie `get_option()`. Ein ungeprüfter Filter ließe Woos
 * `create_options()` beim nächsten Update die Fußzeile mit Woos Standard
 * überschreiben. Deshalb füllt er nur, wenn die Zeile als fehlend gilt.
 *
 * `pre_update_option_*` hält das Formular sauber. Es zeigt den Wert des
 * Kindthemes, und „Änderungen speichern" schickt ihn zurück. Woos Bereinigung
 * macht aus `&` ein `&amp;`, damit greift `update_option()`s eigener Vergleich
 * nicht mehr, und der Wert läge als Eingabe in der Zeile. Wer dem Kindtheme
 * denselben Text zurückschickt, gibt deshalb nichts ein: die Zeile wird leer.
 *
 * Im Formular abschalten geht nicht: das Schriftfeld ist eine Auswahl ohne leere
 * Option. Bei leerer Zeile schickt der Browser die erste Option, und „Arial"
 * stünde als Eingabe da.
 */

if (!defined('ABSPATH')) {
    exit;
}

const LOTZWOO_MAIL_FELDER = [
    'woocommerce_email_header_image' => 'header_image',
    'woocommerce_email_footer_text' => 'footer_text',
    'woocommerce_email_font_family' => 'font_family',
];

/**
 * Die Angaben des aktiven Kindthemes, je Option. Leer, wenn es keine liefert.
 *
 * @return array<string, string>
 */
function lotzwoo_mail_angaben(): array
{
    static $je_verzeichnis = [];

    $verzeichnis = get_stylesheet_directory();

    return $je_verzeichnis[$verzeichnis] ??= lotzwoo_mail_angaben_lesen($verzeichnis);
}

/**
 * @return array<string, string>
 */
function lotzwoo_mail_angaben_lesen(string $verzeichnis): array
{
    $datei = $verzeichnis . '/mail-angaben.json';

    if (!is_file($datei)) {
        return [];
    }

    $daten = wp_json_file_decode($datei, ['associative' => true]);

    if (!is_array($daten)) {
        lotzwoo_mail_angaben_melden("{$datei} ist kein gültiges JSON-Objekt.");

        return [];
    }

    $angaben = [];

    foreach (LOTZWOO_MAIL_FELDER as $option => $schluessel) {
        $wert = $daten[$schluessel] ?? '';

        if (is_string($wert) && trim($wert) !== '') {
            $angaben[$option] = trim($wert);
        }
    }

    if (isset($angaben['woocommerce_email_header_image'])) {
        $logo = lotzwoo_mail_logo_url($verzeichnis, $angaben['woocommerce_email_header_image']);

        if ($logo === '') {
            unset($angaben['woocommerce_email_header_image']);
        } else {
            $angaben['woocommerce_email_header_image'] = $logo;
        }
    }

    if (isset($angaben['woocommerce_email_font_family']) && !lotzwoo_mail_schrift_gueltig($angaben['woocommerce_email_font_family'])) {
        lotzwoo_mail_angaben_melden("Schrift {$angaben['woocommerce_email_font_family']} steht nicht in Woos Auswahl.");
        unset($angaben['woocommerce_email_font_family']);
    }

    return $angaben;
}

/** Die Fußzeile trägt Pflichtangaben: ein Fehler hier darf nicht nur unter WP_DEBUG auffallen. */
function lotzwoo_mail_angaben_melden(string $text): void
{
    error_log("lotzwoo-theme-base mail-angaben: {$text}");
}

/**
 * Eine Schrift außerhalb der Auswahl hätte im Formular keine gewählte Option, und
 * der Browser schickte beim Speichern still die erste. Ohne Woo gibt es nichts zu prüfen.
 */
function lotzwoo_mail_schrift_gueltig(string $schrift): bool
{
    $auswahl = \Automattic\WooCommerce\Internal\Email\EmailFont::class;

    return !class_exists($auswahl) || isset($auswahl::$font[$schrift]);
}

/**
 * Ein Logo muss ein PNG im Kindtheme sein. Fehlt es, setzt Woo den Shopnamen ein,
 * das ist besser als ein gebrochenes Bild in jeder Mail. Der Zeichenvorrat hält die
 * URL unverändert durch Woos `sanitize_url()`, sonst würde sie beim Speichern zur Eingabe.
 */
function lotzwoo_mail_logo_url(string $verzeichnis, string $pfad): string
{
    $form_ok = validate_file($pfad) === 0 && preg_match('~^[A-Za-z0-9_-][A-Za-z0-9._/-]*\.png$~', $pfad) === 1;

    if (!$form_ok || !is_file($verzeichnis . '/' . $pfad)) {
        lotzwoo_mail_angaben_melden("Logo {$pfad} ist kein PNG im Kindtheme.");

        return '';
    }

    // Ohne HTTPS-Anfrage (CLI, Cron) käme die URL sonst als http:// in die Mail.
    return set_url_scheme(
        get_stylesheet_directory_uri() . '/' . $pfad,
        (string) wp_parse_url((string) get_option('home'), PHP_URL_SCHEME)
    );
}

/** Zeile vorhanden: ihr Text gewinnt, außer sie ist leer. */
function lotzwoo_mail_wert_fuellen($wert, string $option)
{
    if (is_string($wert) && trim($wert) !== '') {
        return $wert;
    }

    return lotzwoo_mail_angaben()[$option] ?? $wert;
}

/**
 * Zeile fehlt: das Kindtheme gewinnt auch gegen die Vorgabe des Aufrufers.
 *
 * `get_option()` trägt die fehlende Zeile in `notoptions` ein, bevor es diesen
 * Filter ruft. `add_option()` ruft ihn nur, wenn sie dort nicht steht.
 */
function lotzwoo_mail_wert_vorgabe($vorgabe, string $option)
{
    $fehlend = wp_cache_get('notoptions', 'options');

    if (!is_array($fehlend) || !isset($fehlend[$option])) {
        return $vorgabe;
    }

    return lotzwoo_mail_angaben()[$option] ?? $vorgabe;
}

/** Der Wert des Kindthemes wird nie zur Eingabe. */
function lotzwoo_mail_wert_speichern($wert, $alt, string $option)
{
    $angabe = lotzwoo_mail_angaben()[$option] ?? null;

    if ($angabe === null || !is_string($wert)) {
        return $wert;
    }

    return lotzwoo_mail_wert_vergleichbar($wert) === lotzwoo_mail_wert_vergleichbar($angabe) ? '' : $wert;
}

/** Was Woos Bereinigung und das Textfeld des Browsers am Wert ändern, zählt nicht. */
function lotzwoo_mail_wert_vergleichbar(string $wert): string
{
    return wp_kses_post(trim(str_replace("\r\n", "\n", $wert)));
}

foreach (array_keys(LOTZWOO_MAIL_FELDER) as $lotzwoo_mail_option) {
    add_filter("option_{$lotzwoo_mail_option}", 'lotzwoo_mail_wert_fuellen', 10, 2);
    add_filter("default_option_{$lotzwoo_mail_option}", 'lotzwoo_mail_wert_vorgabe', 10, 2);
    add_filter("pre_update_option_{$lotzwoo_mail_option}", 'lotzwoo_mail_wert_speichern', 10, 3);
}

unset($lotzwoo_mail_option);
