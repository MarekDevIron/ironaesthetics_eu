<?php
declare(strict_types=1);

/**
 * ironaesthetics.eu — malý prepínač produktových liniek (nahradzuje Laravel appku)
 *
 * URL tvar:  /P2805A15382   (= reference kombinácie, P{id_product}A{id_product_attribute})
 * Pri requeste sa pozrie priamo do živých DB shopov a vypíše tlačidlá
 * na daný produkt/kombináciu pre každý shop, kde existuje a je aktívny.
 * Výsledok sa krátko kešuje do súboru, aby sme DB nezatĺkali.
 * Logika je v lib.php, views v views/.
 */

if (PHP_SAPI === 'cli-server') {
    // php -S router mode: statické súbory servíruj rovno
    $file = __DIR__.$_SERVER['REQUEST_URI'];
    if (is_file($file)) {
        return false;
    }
}

require __DIR__.'/lib.php';

$config = require __DIR__.'/config.php';

// ---------- request ----------
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$code = trim($path, '/');
$code = preg_replace('~[^A-Za-z0-9._-]~', '', $code);

header('X-Robots-Tag: noindex, nofollow');

if ($code === '') {
    render('landing', []);
    exit;
}

$results = lookup_with_cache($config, $code);

if (!$results['found']) {
    http_response_code(404);
    render('notfound', ['code' => $code, 'errors' => $results['errors']]);
    exit;
}

// názov produktu v jazyku krajiny návštevníka (fallback: prvý nájdený shop)
$geo   = geo_with_cache(client_ip());
$title = $results['title'];
if ($geo !== null) {
    foreach ($results['buttons'] as $b) {
        if (!empty($b['name']) && strtoupper((string)$b['iso']) === strtoupper($geo)) {
            $title = $b['name'];
            break;
        }
    }
}

render('product', [
    'code'    => $code,
    'title'   => $title,
    'buttons' => $results['buttons'],
    'geo'     => $geo,
]);

// ---------- cache + výstup ----------

function lookup_with_cache(array $config, string $code): array
{
    $ttl = (int)($config['cache_ttl'] ?? 600);

    if ($ttl > 0) {
        $cached = cache_get('res_'.$code);
        if (is_array($cached) && isset($cached['found'])) {
            return $cached;
        }
    }

    $results = lookup($config, $code);

    // kešuj len úspešné lookupy — výpadok DB nemá na 10 minút zahodiť 404
    if ($ttl > 0 && $results['found'] && empty($results['errors'])) {
        cache_put('res_'.$code, $results, $ttl);
    }

    return $results;
}

function render(string $template, array $data): void
{
    extract($data, EXTR_SKIP);
    require __DIR__."/views/{$template}.php";
}
