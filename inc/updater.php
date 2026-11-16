<?php

/**
 * Updates über GitHub-Releases, ohne einen eigenen Updater mitzuschleppen.
 *
 * Seit WordPress 6.1 genügt der `Update URI`-Header plus ein Filter, dessen
 * Name aus dem Hostnamen dieses Headers gebildet wird. Der Kern übernimmt
 * damit Versionsvergleich, `no_update`-Pflege und den Kollisionsschutz.
 *
 * Der Kern ruft die URL im Header **nie** ab. Sie ist Kennung, nicht Endpunkt:
 * das Paket kommt aus `package` im Rückgabewert dieses Filters.
 *
 * ## Öffentliches oder privates Repo
 *
 * Ohne Token fragt das Theme die öffentliche API und lädt das Paket über
 * `browser_download_url`. Ist `LOTZWOO_GITHUB_TOKEN` in der `wp-config.php`
 * gesetzt (dieselbe Konstante wie im Plugin), geht der Abruf mit Token, und das
 * Paket kommt über die **API-Adresse des Assets**: `browser_download_url`
 * verlangt bei einem privaten Repo eine Anmeldung im Browser. Fehlt der Token
 * bei einem privaten Repo, gibt es still kein Update und keinen Fehler.
 *
 * Die Logik folgt dem `GitHub_Updater` des Plugins, ruft ihn aber nicht auf:
 * das Theme muss ohne Plugin laufen.
 */

if (!defined('ABSPATH')) {
    exit;
}

/** Besitzer und Name des Repos, aus dem die Releases kommen. */
const LOTZWOO_THEME_GITHUB_OWNER = 'LotzApp';
const LOTZWOO_THEME_GITHUB_REPO = 'lotzwoo-theme-base';

/** Der Name des Release-Assets. Er ist zugleich der Ordnername im ZIP. */
const LOTZWOO_THEME_ASSET = 'lotzwoo-theme-base.zip';

/** Zwischenspeicher für die Antwort von GitHub. */
const LOTZWOO_THEME_RELEASE_CACHE = 'lotzwoo_theme_base_release';

/** Die Adresse, die im `Update URI`-Header von `style.css` stehen muss. */
function lotzwoo_theme_repo_url(): string
{
    return 'https://github.com/' . LOTZWOO_THEME_GITHUB_OWNER . '/' . LOTZWOO_THEME_GITHUB_REPO;
}

/** Der Token aus der `wp-config.php`, oder `null`. */
function lotzwoo_theme_github_token(): ?string
{
    if (!defined('LOTZWOO_GITHUB_TOKEN')) {
        return null;
    }

    $token = constant('LOTZWOO_GITHUB_TOKEN');

    return is_string($token) && $token !== '' ? $token : null;
}

/**
 * Ist `$url` ein Release-Asset dieses Repos, angesprochen über die API?
 *
 * Nur das Präfix gefolgt von einer Asset-Nummer zählt. Alles andere im Rest —
 * ein zweiter Pfadteil, `..`, eine Abfrage — könnte mit Token und
 * Octet-Stream-Kopf einen anderen Endpunkt treffen.
 */
function lotzwoo_theme_ist_asset_api_url(string $url): bool
{
    $praefix = 'https://api.github.com/repos/' . LOTZWOO_THEME_GITHUB_OWNER . '/'
        . LOTZWOO_THEME_GITHUB_REPO . '/releases/assets/';

    return str_starts_with($url, $praefix) && ctype_digit(substr($url, strlen($praefix)));
}

/**
 * Ist `$url` ein öffentlicher Download aus den Releases dieses Repos?
 *
 * Die API liefert die Adresse, und ohne diese Prüfung böte das Theme an, was
 * immer dort steht. Host und Pfad müssen zu genau diesem Repo gehören.
 */
function lotzwoo_theme_ist_release_download(string $url): bool
{
    $praefix = lotzwoo_theme_repo_url() . '/releases/download/';

    return str_starts_with($url, $praefix) && !str_contains($url, '..') && !str_contains($url, '?');
}

/**
 * Die Paket-Adresse aus der Release-Antwort, oder leer.
 *
 * Nur das gebaute Asset zählt. Die Quell-Archive, die GitHub automatisch
 * beilegt, taugen nicht: ihr einziges Unterverzeichnis heißt
 * `lotzwoo-theme-base-<tag>`, und WordPress installierte das Theme unter
 * diesem Namen — womit jedes Kindtheme sein Elterntheme verlöre.
 *
 * @param array<string, mixed> $release
 */
function lotzwoo_theme_paket_url(array $release, bool $mit_token): string
{
    foreach ((array) ($release['assets'] ?? []) as $asset) {
        if (!is_array($asset) || ($asset['name'] ?? '') !== LOTZWOO_THEME_ASSET) {
            continue;
        }

        $api = (string) ($asset['url'] ?? '');
        $download = (string) ($asset['browser_download_url'] ?? '');

        if ($mit_token && lotzwoo_theme_ist_asset_api_url($api)) {
            return $api;
        }

        return lotzwoo_theme_ist_release_download($download) ? $download : '';
    }

    return '';
}

/**
 * Fragt GitHub nach dem letzten Release.
 *
 * @return array{version:string,package:string,url:string}|null
 */
function lotzwoo_theme_release_abrufen(?string $token): ?array
{
    $kopf = [
        'Accept' => 'application/vnd.github+json',
        'User-Agent' => 'lotzwoo-theme-base/' . wp_get_theme(LOTZWOO_THEME_GITHUB_REPO)->get('Version'),
    ];

    if ($token !== null) {
        $kopf['Authorization'] = 'token ' . $token;
    }

    $antwort = wp_remote_get(
        'https://api.github.com/repos/' . LOTZWOO_THEME_GITHUB_OWNER . '/' . LOTZWOO_THEME_GITHUB_REPO . '/releases/latest',
        ['timeout' => 10, 'headers' => $kopf]
    );

    if (is_wp_error($antwort) || wp_remote_retrieve_response_code($antwort) !== 200) {
        return null;
    }

    $release = json_decode(wp_remote_retrieve_body($antwort), true);

    if (!is_array($release) || empty($release['tag_name'])) {
        return null;
    }

    $seite = (string) ($release['html_url'] ?? '');

    return [
        'version' => ltrim((string) $release['tag_name'], 'v'),
        'package' => lotzwoo_theme_paket_url($release, $token !== null),
        'url' => str_starts_with($seite, lotzwoo_theme_repo_url() . '/') ? $seite : lotzwoo_theme_repo_url(),
    ];
}

/**
 * Das letzte Release, zwischengespeichert.
 *
 * Der Kern prüft auf vielen Admin-Seiten auf Updates, und ohne Token erlaubt
 * GitHub 60 Abrufe je Stunde und Adresse. Ein Ergebnis gilt sechs Stunden, ein
 * Fehlschlag eine Stunde. Wird der Token gesetzt oder entfernt, zählt der
 * Zwischenspeicher nicht mehr.
 *
 * @return array{version:string,package:string,url:string}|null
 */
function lotzwoo_theme_letztes_release(): ?array
{
    $token = lotzwoo_theme_github_token();
    $gespeichert = get_site_transient(LOTZWOO_THEME_RELEASE_CACHE);

    if (is_array($gespeichert) && ($gespeichert['token'] ?? null) === ($token !== null)) {
        return is_array($gespeichert['release'] ?? null) ? $gespeichert['release'] : null;
    }

    $release = lotzwoo_theme_release_abrufen($token);

    set_site_transient(
        LOTZWOO_THEME_RELEASE_CACHE,
        ['token' => $token !== null, 'release' => $release],
        $release === null ? HOUR_IN_SECONDS : 6 * HOUR_IN_SECONDS
    );

    return $release;
}

/**
 * Die Antwort auf `update_themes_github.com`.
 *
 * Der Filtername ist ein **geteilter** Namensraum: jedes Theme, das denselben
 * Weg geht, hängt daran. Der Callback prüft zuerst, ob er gemeint ist — Slug
 * und `Update URI` müssen genau stimmen —, und gibt sonst unverändert zurück.
 *
 * Der Rückgabewert ist ein **Array**: für Themes erwartet der Kern `theme`,
 * `url` und `package`, ein Objekt wird still verworfen. Entscheidend ist der
 * Schlüssel **`version`**; eine Antwort mit nur `new_version` fällt in
 * `wp_update_themes()` durch die Prüfung und wird ohne Fehler verworfen.
 * `new_version` steht trotzdem da, weil es das ist, was ankommt.
 *
 * `$theme_data` trägt nur acht Felder, `RequiresWP` und `RequiresPHP` sind
 * nicht darunter. Deshalb kommen die Anforderungen aus der **installierten**
 * Fassung. Das ist eine Näherung, aber besser als ein leerer Wert, mit dem die
 * Verträglichkeitsprüfung der Themes-Übersicht leer liefe.
 *
 * @param mixed $update
 * @param mixed $theme_data
 * @param mixed $theme_stylesheet
 * @return mixed
 */
function lotzwoo_theme_update_angebot($update, $theme_data, $theme_stylesheet)
{
    if ($theme_stylesheet !== LOTZWOO_THEME_GITHUB_REPO || !is_array($theme_data)) {
        return $update;
    }

    if (($theme_data['UpdateURI'] ?? '') !== lotzwoo_theme_repo_url()) {
        return $update;
    }

    $release = lotzwoo_theme_letztes_release();

    // Der Kern vergleicht selbst noch einmal. Die Prüfung hier spart nur den
    // Fall, in dem ein Paket angeboten wird, das keines ist.
    if ($release === null || $release['package'] === ''
        || version_compare($release['version'], (string) ($theme_data['Version'] ?? '0'), '<=')) {
        return $update;
    }

    $installiert = wp_get_theme($theme_stylesheet);

    return [
        'theme' => LOTZWOO_THEME_GITHUB_REPO,
        'version' => $release['version'],
        'new_version' => $release['version'],
        'url' => $release['url'],
        'package' => $release['package'],
        'requires' => (string) $installiert->get('RequiresWP'),
        'requires_php' => (string) $installiert->get('RequiresPHP'),
    ];
}
add_filter('update_themes_github.com', 'lotzwoo_theme_update_angebot', 10, 3);

/**
 * Den Token an den Download des Pakets hängen.
 *
 * Der Kern lädt `package` mit `download_url()`, ohne eigene Köpfe. Ohne
 * Octet-Stream-Kopf antwortet der Asset-Endpunkt mit der JSON-Beschreibung des
 * Assets, und WordPress speicherte sie als ZIP.
 *
 * Nur für genau die Asset-Adressen dieses Repos: der Filter sieht jede
 * HTTP-Anfrage der Website. Keine Typangaben, weil andere Plugins hier
 * ungeprüft durchreichen, was sie wollen.
 *
 * @param mixed $args
 * @param mixed $url
 * @return mixed
 */
function lotzwoo_theme_download_autorisieren($args, $url = '')
{
    $token = lotzwoo_theme_github_token();

    if ($token === null || !is_array($args) || !is_string($url) || !lotzwoo_theme_ist_asset_api_url($url)) {
        return $args;
    }

    // WordPress nimmt Köpfe auch als eine Zeichenkette; dann gibt es keinen Schlüssel.
    if (isset($args['headers']) && !is_array($args['headers'])) {
        return $args;
    }

    $args['headers']['Authorization'] = 'token ' . $token;
    $args['headers']['Accept'] = 'application/octet-stream';

    return $args;
}
add_filter('http_request_args', 'lotzwoo_theme_download_autorisieren', 15, 2);

/**
 * Den Token bei der Weiterleitung abnehmen.
 *
 * Der Asset-Endpunkt leitet auf eine signierte Adresse beim Speicher von GitHub
 * weiter. Die HTTP-Bibliothek folgt mit denselben Köpfen; der Token verließe
 * damit die API, und der Speicher lehnt eine Anfrage mit Signatur **und**
 * `Authorization` ab. Entfernt wird nur der Kopf mit diesem Token.
 *
 * @param mixed $location
 * @param mixed $headers
 */
function lotzwoo_theme_token_bei_weiterleitung_entfernen(&$location = null, &$headers = null): void
{
    $token = lotzwoo_theme_github_token();

    if ($token === null || !is_string($location) || !is_array($headers) || lotzwoo_theme_ist_asset_api_url($location)) {
        return;
    }

    foreach ($headers as $name => $wert) {
        if (is_string($name) && strtolower($name) === 'authorization' && $wert === 'token ' . $token) {
            unset($headers[$name]);
        }
    }
}
add_action('requests-requests.before_redirect', 'lotzwoo_theme_token_bei_weiterleitung_entfernen', 10, 2);

/**
 * Nach einem Update des Themes den Zwischenspeicher leeren, damit die nächste
 * Prüfung nicht das alte Release für neu hält.
 *
 * @param mixed $upgrader
 * @param mixed $extra
 */
function lotzwoo_theme_release_cache_leeren($upgrader = null, $extra = []): void
{
    if (is_array($extra) && ($extra['type'] ?? '') === 'theme'
        && in_array(LOTZWOO_THEME_GITHUB_REPO, (array) ($extra['themes'] ?? []), true)) {
        delete_site_transient(LOTZWOO_THEME_RELEASE_CACHE);
    }
}
add_action('upgrader_process_complete', 'lotzwoo_theme_release_cache_leeren', 10, 2);
