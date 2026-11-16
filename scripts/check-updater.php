<?php
/**
 * Prüft `inc/updater.php` ohne WordPress: die Kernfunktionen sind hier
 * nachgebildet, GitHub antwortet aus einer Tabelle.
 *
 *     php scripts/check-updater.php
 *
 * Erst ohne Token (öffentliches Repo), dann mit (privates Repo). Eine Konstante
 * lässt sich nicht wieder entfernen, deshalb diese Reihenfolge.
 */
declare(strict_types=1);

const ABSPATH = __DIR__;
const HOUR_IN_SECONDS = 3600;

$GLOBALS['haken'] = [];
$GLOBALS['transienten'] = [];
$GLOBALS['abrufe'] = [];
$GLOBALS['antwort'] = null;

function add_filter(string $name, callable $f, int $prio = 10, int $args = 1): void
{
    $GLOBALS['haken'][$name] = $f;
}

function add_action(string $name, callable $f, int $prio = 10, int $args = 1): void
{
    $GLOBALS['haken'][$name] = $f;
}

function get_site_transient(string $key)
{
    return $GLOBALS['transienten'][$key] ?? false;
}

function set_site_transient(string $key, $wert, int $ttl): void
{
    $GLOBALS['transienten'][$key] = $wert;
}

function delete_site_transient(string $key): void
{
    unset($GLOBALS['transienten'][$key]);
}

function wp_remote_get(string $url, array $args): array
{
    $GLOBALS['abrufe'][] = [$url, $args];

    return $GLOBALS['antwort'];
}

function is_wp_error($x): bool
{
    return false;
}

function wp_remote_retrieve_response_code(array $r): int
{
    return $r['code'];
}

function wp_remote_retrieve_body(array $r): string
{
    return json_encode($r['body']);
}

function wp_get_theme(string $slug): object
{
    return new class {
        public function get(string $feld): string
        {
            return ['Version' => '0.4.0', 'RequiresWP' => '6.6', 'RequiresPHP' => '8.1'][$feld] ?? '';
        }
    };
}

require dirname(__DIR__) . '/inc/updater.php';

$bestanden = 0;

function pruefe(bool $ok, string $was): void
{
    global $bestanden;
    if (!$ok) {
        fwrite(STDERR, "FEHLER: {$was}\n");
        exit(1);
    }
    $bestanden++;
}

const REPO = 'https://github.com/LotzApp/LotzWooThemeBase';
const ASSET_API = 'https://api.github.com/repos/LotzApp/LotzWooThemeBase/releases/assets/123';
const DOWNLOAD = REPO . '/releases/download/0.5.0/lotzwoo-theme-base.zip';

function release(array $asset = []): array
{
    return ['code' => 200, 'body' => [
        'tag_name' => '0.5.0',
        'html_url' => REPO . '/releases/tag/0.5.0',
        'assets' => [array_merge(['name' => 'lotzwoo-theme-base.zip', 'url' => ASSET_API, 'browser_download_url' => DOWNLOAD], $asset)],
    ]];
}

function frage(string $uri = REPO, string $slug = 'lotzwoo-theme-base', string $version = '0.4.0')
{
    $GLOBALS['transienten'] = [];

    return $GLOBALS['haken']['update_themes_github.com'](false, ['UpdateURI' => $uri, 'Version' => $version], $slug);
}

// --- Öffentlich, ohne Token ------------------------------------------------
$GLOBALS['antwort'] = release();
$angebot = frage();
pruefe(is_array($angebot) && $angebot['package'] === DOWNLOAD, 'ohne Token: browser_download_url als Paket');
pruefe($angebot['version'] === '0.5.0' && $angebot['new_version'] === '0.5.0', 'version und new_version gesetzt');
pruefe($angebot['requires'] === '6.6' && $angebot['requires_php'] === '8.1', 'Anforderungen der installierten Fassung');
pruefe(!isset(end($GLOBALS['abrufe'])[1]['headers']['Authorization']), 'ohne Token: kein Authorization-Kopf');
pruefe(str_contains(end($GLOBALS['abrufe'])[0], '/repos/LotzApp/LotzWooThemeBase/releases/latest'), 'fragt LotzApp');

pruefe(frage(REPO . '-gabel') === false, 'Update URI mit Anhängsel wird nicht bedient');
pruefe(frage('https://github.com/LotzApp/lotzwoo-theme-base') === false, 'alte Update URI mit Slug statt Repo-Name wird nicht bedient');
pruefe(frage(REPO, 'anderes-theme') === false, 'fremder Slug wird nicht bedient');
pruefe(frage(REPO, 'lotzwoo-theme-base', '0.5.0') === false, 'gleiche Version: kein Angebot');

$GLOBALS['antwort'] = release(['browser_download_url' => 'https://example.com/lotzwoo-theme-base.zip']);
pruefe(frage() === false, 'fremder Host als Paket wird abgelehnt');
$GLOBALS['antwort'] = release(['browser_download_url' => 'https://github.com/Andere/lotzwoo-theme-base/releases/download/0.5.0/lotzwoo-theme-base.zip']);
pruefe(frage() === false, 'fremdes Repo als Paket wird abgelehnt');
$GLOBALS['antwort'] = release(['name' => 'quelle.zip']);
pruefe(frage() === false, 'ohne gebautes Asset kein Angebot');

$GLOBALS['antwort'] = ['code' => 404, 'body' => []];
pruefe(frage() === false, 'privates Repo ohne Token: still kein Update');

$args = $GLOBALS['haken']['http_request_args'](['headers' => []], ASSET_API);
pruefe(!isset($args['headers']['Authorization']), 'ohne Token: Download bleibt ohne Kopf');

// Zwischenspeicher: ein zweiter Aufruf fragt GitHub nicht noch einmal.
$GLOBALS['antwort'] = release();
$GLOBALS['transienten'] = [];
$vorher = count($GLOBALS['abrufe']);
lotzwoo_theme_letztes_release();
lotzwoo_theme_letztes_release();
pruefe(count($GLOBALS['abrufe']) === $vorher + 1, 'Ergebnis wird zwischengespeichert');

// --- Privat, mit Token -----------------------------------------------------
define('LOTZWOO_GITHUB_TOKEN', 'geheim');
pruefe(lotzwoo_theme_letztes_release() !== null && count($GLOBALS['abrufe']) === $vorher + 2, 'neuer Token macht den Zwischenspeicher ungültig');

$angebot = frage();
pruefe(is_array($angebot) && $angebot['package'] === ASSET_API, 'mit Token: API-Adresse des Assets als Paket');
pruefe((end($GLOBALS['abrufe'])[1]['headers']['Authorization'] ?? '') === 'token geheim', 'mit Token: API-Abruf autorisiert');

$args = $GLOBALS['haken']['http_request_args'](['headers' => []], ASSET_API);
pruefe($args['headers']['Authorization'] === 'token geheim', 'Download des Assets bekommt den Token');
pruefe($args['headers']['Accept'] === 'application/octet-stream', 'Download des Assets verlangt die Datei');

foreach ([
    'https://api.github.com/repos/LotzApp/LotzWooThemeBase/releases/assets/123/../../x',
    'https://api.github.com/repos/LotzApp/LotzWooThemeBase/releases/assets/123?x=1',
    'https://api.github.com/repos/Andere/repo/releases/assets/1',
    'https://example.com/?u=' . ASSET_API,
] as $fremd) {
    $args = $GLOBALS['haken']['http_request_args'](['headers' => []], $fremd);
    pruefe(!isset($args['headers']['Authorization']), "kein Token für {$fremd}");
}

$ziel = 'https://objects.githubusercontent.com/signiert';
$koepfe = ['Authorization' => 'token geheim', 'X-Andere' => 'bleibt'];
$GLOBALS['haken']['requests-requests.before_redirect']($ziel, $koepfe);
pruefe(!isset($koepfe['Authorization']) && $koepfe['X-Andere'] === 'bleibt', 'Weiterleitung zum Speicher ohne Token');

$koepfe = ['Authorization' => 'token fremd'];
$GLOBALS['haken']['requests-requests.before_redirect']($ziel, $koepfe);
pruefe(isset($koepfe['Authorization']), 'fremde Zugangsdaten bleiben unberührt');

$GLOBALS['transienten'][LOTZWOO_THEME_RELEASE_CACHE] = ['x'];
$GLOBALS['haken']['upgrader_process_complete'](null, ['type' => 'theme', 'themes' => ['lotzwoo-theme-base']]);
pruefe(!isset($GLOBALS['transienten'][LOTZWOO_THEME_RELEASE_CACHE]), 'nach dem Update wird der Zwischenspeicher geleert');

echo "Updater: {$bestanden} Prüfungen bestanden.\n";
