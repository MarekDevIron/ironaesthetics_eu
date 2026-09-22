<?php
/**
 * Logika prepínača — zdieľaná medzi index.php (web) a test.php (CLI).
 */

/**
 * Prejde všetky DB z configu, v každej nájde aktívne shop views (multistore SK/CZ
 * tak dá dve položky) a pre každý skúsi nájsť kombináciu podľa reference.
 */
function lookup(array $config, string $code): array
{
    $buttons = [];
    $errors  = [];
    $title   = '';
    $shopTtl = (int)($config['shop_ttl'] ?? 3600);

    foreach ($config['databases'] as $dbConf) {
        try {
            $pdo = db($dbConf);
            $prefix = $dbConf['prefix'] ?? 'ps_';
            $shops  = discover_shops_cached($pdo, $prefix, $dbConf['name'], $shopTtl);

            foreach ($shops as $shop) {
                $row = find_combination($pdo, $prefix, $shop, $code);
                if ($row === null || (int)$row['active'] !== 1) {
                    continue;
                }
                $url = build_url($pdo, $prefix, $shop, $row);
                if ($url === null) {
                    continue;
                }
                // vlajka podľa domény (ironaesthetics.cz → cz.png) — jazykové kódy v DB nesedia s názvami súborov
                $flag = strtolower((string)preg_replace('~.*\.([a-z]{2})$~', '$1', $shop['domain']));
                // "Slovenčina (Slovak)" → "Slovak"
                $short = preg_match('/\(([^)]+)\)/', $shop['lang_name'], $m) ? $m[1] : $shop['lang_name'];

                $buttons[] = [
                    'lang_name' => $short,
                    'domain'    => preg_replace('~^www\.~', '', $shop['domain']),
                    'iso'       => $flag,
                    'url'       => $url,
                    'name'      => $row['name'], // názov produktu v jazyku shopu
                ];
                if ($title === '') {
                    $title = $row['name'];
                }
            }
        } catch (Throwable $e) {
            $errors[] = $dbConf['name'].': '.$e->getMessage();
            error_log('[eu-prepinac] '.$dbConf['name'].': '.$e->getMessage());
        }
    }

    return ['found' => $buttons !== [], 'buttons' => $buttons, 'title' => $title ?: $code, 'errors' => $errors];
}

function db(array $c): PDO
{
    static $pool = [];
    $key = $c['host'].'/'.$c['db'];
    if (!isset($pool[$key])) {
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $c['host'], $c['db']);
        $pool[$key] = new PDO($dsn, $c['user'], $c['pass'], [
            PDO::ATTR_ERRMODE          => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT          => 5,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }
    return $pool[$key];
}

/**
 * discover_shops() zabalené v keši (súbor/APCu, `shop_ttl` z configu). Zoznam shopov
 * a ich routing config (pretty URL, anchor separátor) sa mení len pri zásahu v BO,
 * takže netreba naň platiť DB dotazom pri každom requeste — na rozdiel od samotného
 * product lookupu (kešovaný zvlášť, kratšie, per kód).
 */
function discover_shops_cached(PDO $pdo, string $prefix, string $dbName, int $ttl): array
{
    $key = 'shops_'.$dbName;
    if ($ttl > 0) {
        $cached = cache_get($key);
        if (is_array($cached)) {
            return $cached;
        }
    }
    $shops = discover_shops($pdo, $prefix);
    if ($ttl > 0 && $shops !== []) {
        cache_put($key, $shops, $ttl);
    }
    return $shops;
}

/**
 * Aktívne shop views v jednej DB — doména, hlavný jazyk a routing config
 * (pretty URL pravidlo + anchor separátor), nech ich build_url()/build_anchor()
 * nemusia dohľadávať zvlášť za behu.
 */
function discover_shops(PDO $pdo, string $prefix): array
{
    $shops = [];
    $rows = q($pdo,
        "SELECT s.id_shop, s.id_shop_group, su.domain, su.physical_uri, su.virtual_uri
         FROM {$prefix}shop s
         JOIN {$prefix}shop_url su ON su.id_shop = s.id_shop AND su.active = 1 AND su.main = 1
         WHERE s.active = 1
         ORDER BY s.id_shop ASC");

    foreach ($rows as $r) {
        $idShop  = (int)$r['id_shop'];
        $idGroup = (int)$r['id_shop_group'];

        $defaultLang = (int)(cfg($pdo, $prefix, 'PS_LANG_DEFAULT', $idGroup, $idShop) ?? 1);
        $lang = q($pdo,
            "SELECT iso_code, name FROM {$prefix}lang WHERE id_lang = ? AND active = 1",
            [$defaultLang]);
        if (!$lang) {
            continue;
        }
        $shops[] = [
            'id_shop'       => $idShop,
            'id_shop_group' => $idGroup,
            'domain'        => $r['domain'],
            'uri'           => rtrim($r['physical_uri'] ?? '', '/').'/'.ltrim($r['virtual_uri'] ?? '', '/'),
            'lang_id'       => $defaultLang,
            'lang_iso'      => strtolower($lang[0]['iso_code']),
            'lang_name'     => $lang[0]['name'],
            'rewriting'     => cfg($pdo, $prefix, 'PS_REWRITING_SETTINGS', $idGroup, $idShop),
            'route_product' => cfg($pdo, $prefix, 'PS_ROUTE_product', $idGroup, $idShop),
            'anchor_sep'    => cfg($pdo, $prefix, 'PS_ATTRIBUTE_ANCHOR_SEPARATOR', $idGroup, $idShop),
        ];
    }
    return $shops;
}

/** Configuration::get() — najprv hodnota špecifická pre shop, potom skupinu, napokon globálna. */
function cfg(PDO $pdo, string $prefix, string $name, int $idShopGroup, int $idShop): ?string
{
    $rows = q($pdo,
        "SELECT id_shop_group, id_shop, value FROM {$prefix}configuration WHERE name = ?",
        [$name]);
    foreach ($rows as $r) {
        if ((int)$r['id_shop'] === $idShop && $idShop > 0) {
            return $r['value'];
        }
    }
    foreach ($rows as $r) {
        if ((int)$r['id_shop_group'] === $idShopGroup && (int)$r['id_shop'] === 0 && $idShopGroup > 0) {
            return $r['value'];
        }
    }
    foreach ($rows as $r) {
        if ((int)$r['id_shop'] === 0 && (int)$r['id_shop_group'] === 0) {
            return $r['value'];
        }
    }
    return null;
}

/**
 * Nájde kombináciu podľa kódu. Kód je väčšinou P{id}A{ipa}, takže primárne
 * hľadáme priamo cez id (indexované); inak spätným dohľadom po reference.
 *
 * Pozor, reference sa generuje z ID master shopu (SK/CZ), takže na HU/RO ID
 * väčšinou NESEDIA a rozhoduje až fallback po reference — a tá nie je unikátna
 * (na HU aj RO je ~25 zdvojených, z toho 6 kódov má jednu kópiu aktívnu a druhú
 * nie). Preto ORDER BY: bez neho by LIMIT 1 mohol vrátiť neaktívnu kópiu
 * a tlačidlo daného shopu by bez príčiny zmizlo.
 */
function find_combination(PDO $pdo, string $prefix, array $shop, string $code): ?array
{
    $sql =
        "SELECT p.id_product, pa.id_product_attribute AS ipa, ps.active,
                pl.name, pl.link_rewrite, pl.meta_title, pl.meta_keywords,
                p.ean13, p.reference, cl.link_rewrite AS cat_rewrite
         FROM {$prefix}product_attribute pa
         JOIN {$prefix}product p ON p.id_product = pa.id_product
         JOIN {$prefix}product_shop ps ON ps.id_product = p.id_product AND ps.id_shop = :shop1
         JOIN {$prefix}product_lang pl ON pl.id_product = p.id_product AND pl.id_shop = :shop2 AND pl.id_lang = :lang1
         LEFT JOIN {$prefix}category_lang cl
              ON cl.id_category = p.id_category_default AND cl.id_shop = :shop3 AND cl.id_lang = :lang2
         WHERE %WHERE%
         ORDER BY ps.active DESC, pa.id_product_attribute ASC
         LIMIT 1";

    $base = [
        ':shop1' => $shop['id_shop'],
        ':shop2' => $shop['id_shop'],
        ':shop3' => $shop['id_shop'],
        ':lang1' => $shop['lang_id'],
        ':lang2' => $shop['lang_id'],
    ];

    if (preg_match('/^P(\d+)A(\d+)$/i', $code, $m)) {
        $stmt = $pdo->prepare(str_replace(
            '%WHERE%',
            'pa.id_product = :pid AND pa.id_product_attribute = :ipa AND pa.reference = :ref',
            $sql
        ));
        $stmt->execute($base + [':pid' => (int)$m[1], ':ipa' => (int)$m[2], ':ref' => $code]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row !== false) {
            return $row;
        }
    }

    // fallback: hľadanie čisto po reference (pre kódy v inom formáte)
    $stmt = $pdo->prepare(str_replace('%WHERE%', 'pa.reference = :ref', $sql));
    $stmt->execute($base + [':ref' => $code]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row !== false ? $row : null;
}

/**
 * Zostaví produktové URL rovnakou logikou ako PS 1.6 Link::getProductLink +
 * Dispatcher::createUrl (tokeny {rewrite}, {category:/}, {-:ean13} …)
 * + anchor kombinácie (#/velikost-m) ako getAnchor(). Routing config (rewriting,
 * route_product, anchor_sep) berie z $shop — naplnené a kešované v discover_shops().
 */
function build_url(PDO $pdo, string $prefix, array $shop, array $row): ?string
{
    $rewriting = $shop['rewriting'];
    $rule      = $shop['route_product'];

    if ($rule === null || $rule === '') {
        $rule = '{category:/}{id}-{rewrite}{-:ean13}.html'; // default product_rule z Dispatcheru
    }

    $params = [
        'id'            => (string)$row['id_product'],
        'rewrite'       => (string)$row['link_rewrite'],
        'ean13'         => (string)$row['ean13'],
        'reference'     => str2url((string)$row['reference']),
        'meta_title'    => str2url((string)$row['meta_title']),
        'meta_keywords' => str2url((string)$row['meta_keywords']),
        'category'      => (string)($row['cat_rewrite'] ?? ''),
        // tokeny, ktoré IRON shopy v routách nepoužívajú — nech zmiznú
        'categories'    => '',
        'manufacturer'  => '',
        'supplier'      => '',
        'price'         => '',
        'tags'          => '',
    ];

    if ((int)$rewriting === 1) {
        $path = preg_replace_callback(
            '~\{([^{}]*:)?([a-z0-9_]+)(:[^{}]*)?\}~',
            function ($m) use ($params) {
                $val = $params[$m[2]] ?? '';
                if ($val === '' || $val === null) {
                    return '';
                }
                // {category:/} → ap='/', {-:ean13} → pre='-', {:ipa} → ap='/'
                $pre = (isset($m[1]) && $m[1] !== '') ? substr($m[1], 0, -1) : '';
                $app = (isset($m[3]) && $m[3] !== '') ? substr($m[3], 1) : '';
                return $pre.$val.$app;
            },
            $rule
        );
        $path = preg_replace('~\{([^{}]*:)?[a-z0-9_]+?(:[^{}]*)?\}~', '', (string)$path);
        $url  = 'https://'.$shop['domain'].$shop['uri'].ltrim((string)$path, '/');
    } else {
        $url = 'https://'.$shop['domain'].$shop['uri']
            .'index.php?controller=product&id_product='.$row['id_product'].'&id_lang='.$shop['lang_id'];
        if ((int)$row['ipa'] > 0) {
            $url .= '&id_product_attribute='.$row['ipa'];
        }
    }

    $anchor = build_anchor($pdo, $prefix, $shop, (int)$row['id_product'], (int)$row['ipa']);

    return $url !== '' ? $url.$anchor : null;
}

/** Replika Product::getAnchor() — '#/skupina-hodnota/…', len bez blocklayered custom url_name. */
function build_anchor(PDO $pdo, string $prefix, array $shop, int $idProduct, int $ipa): string
{
    if ($ipa <= 0) {
        return '';
    }
    $sep = $shop['anchor_sep'] ?? null;
    if ($sep === null || $sep === '') {
        $sep = '-';
    }
    $sep = substr($sep, 0, 1);

    $attrs = q($pdo,
        "SELECT al.name, agl.name AS grp
         FROM {$prefix}product_attribute_combination pac
         JOIN {$prefix}product_attribute pa ON pa.id_product_attribute = pac.id_product_attribute
         JOIN {$prefix}attribute a ON a.id_attribute = pac.id_attribute
         LEFT JOIN {$prefix}attribute_lang al ON al.id_attribute = a.id_attribute AND al.id_lang = :lang1
         LEFT JOIN {$prefix}attribute_group_lang agl ON agl.id_attribute_group = a.id_attribute_group AND agl.id_lang = :lang2
         WHERE pa.id_product = :pid AND pac.id_product_attribute = :ipa",
        [':lang1' => $shop['lang_id'], ':lang2' => $shop['lang_id'], ':pid' => $idProduct, ':ipa' => $ipa]);

    if (!$attrs) {
        return '';
    }
    $anchor = '#';
    foreach ($attrs as $a) {
        $anchor .= '/'.str_replace($sep, '_', str2url((string)$a['grp']))
                 . $sep.str_replace($sep, '_', str2url((string)$a['name']));
    }
    return $anchor;
}

/** Zjednodušený Tools::str2url — translit, malé písmená, pomlčky. */
function str2url(string $s): string
{
    if ($s === '') {
        return '';
    }
    $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
    if ($t !== false) {
        $t = preg_replace("~^'|'$~", '', $t); // iconv občas nechá apostrof okolo znaku
        $s = $t;
    }
    $s = strtolower((string)$s);
    $s = preg_replace('~[^a-z0-9]+~', '-', $s);
    return trim((string)$s, '-');
}

function q(PDO $pdo, string $sql, array $args = []): array
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($args);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ---------- keš (APCu ak je, inak súbory vo vlastnom cache/) ----------

/**
 * Súborová keš: najprv <web>/private/cache (ISPConfig, mimo docroot), potom cache/
 * vedľa index.php. Zdieľaný sys_get_temp_dir() zámerne NIE — tam vie iný user na boxe
 * podvrhnúť súbor s predvídateľným názvom. Ak nie je zapisovateľný ani jeden, keš
 * sa len vypne (každý request = plný lookup) a raz sa to zaloguje.
 */
function cache_dir(): ?string
{
    static $dir = false;
    if ($dir !== false) {
        return $dir;
    }
    $private = dirname(__DIR__).'/private/cache';
    if (is_dir(dirname($private)) && (is_dir($private) || @mkdir($private, 0770)) && is_writable($private)) {
        return $dir = $private;
    }
    $local = __DIR__.'/cache';
    if ((is_dir($local) || @mkdir($local, 0770)) && is_writable($local)) {
        // keš leží vo webroote → vlastný zákaz prístupu ako druhá poistka
        // popri pravidle v hlavnom .htaccess (keby sa appka nasadila inam)
        if (!is_file($local.'/.htaccess')) {
            @file_put_contents(
                $local.'/.htaccess',
                "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n"
                ."<IfModule !mod_authz_core.c>\n    Deny from all\n</IfModule>\n"
            );
        }
        return $dir = $local;
    }
    error_log('[eu-prepinac] cache/ nie je zapisovateľný (skúšané '.$private.' a '.$local.') — keš vypnutá');
    return $dir = null;
}

function cache_get(string $key)
{
    if (function_exists('apcu_fetch')) {
        $v = apcu_fetch('eu-prepinac_'.$key, $ok);
        if ($ok) {
            return $v;
        }
    }
    $dir = cache_dir();
    if ($dir === null) {
        return null;
    }
    $f = $dir.'/eu-prepinac_'.md5($key).'.json';
    if (is_file($f)) {
        $c = json_decode((string)@file_get_contents($f), true);
        // torzo súboru (súbežný zápis) alebo iný formát → ber to ako miss, nie ako warning
        if (is_array($c) && isset($c['t'], $c['ttl'], $c['data'])
            && time() - (int)$c['t'] < (int)$c['ttl']) {
            return $c['data'];
        }
    }
    return null;
}

function cache_put(string $key, $data, int $ttl): void
{
    if (function_exists('apcu_store') && apcu_store('eu-prepinac_'.$key, $data, $ttl)) {
        return; // APCu je zdieľané naprieč php-fpm workermi, súborová keš navyše je zbytočná
    }
    $dir = cache_dir();
    if ($dir === null) {
        return;
    }
    $f = $dir.'/eu-prepinac_'.md5($key).'.json';
    // LOCK_EX: dvaja php-fpm workeri píšuci naraz by inak nechali polovičný súbor
    @file_put_contents($f, json_encode(['t' => time(), 'ttl' => $ttl, 'data' => $data]), LOCK_EX);
}

// ---------- geolokácia ----------

/**
 * Krajina návštevníka zo servera — len Cloudflare hlavička, žiadne sieťové volanie.
 * Pôvodná verzia skúšala aj ip-api.com/ipwho.is/geojs.io priamo z PHP (sekvenčne,
 * až 3× 2,5 s timeout) — to je zbytočné blokovanie requestu navyše, keď produktová
 * stránka (views/product.php) má presne na tento prípad JS fallback na geojs.io,
 * ktorý beží v prehliadači návštevníka, nie na našom serveri. Kým web nejde cez CF,
 * toto prakticky vždy vráti null a geo dorieši JS — čo je v poriadku, stránka sa
 * vykreslí okamžite a odznak sa dolepí o zlomok sekundy neskôr.
 */
function geo_country(): ?string
{
    $cc = $_SERVER['HTTP_CF_IPCOUNTRY'] ?? null;
    return (is_string($cc) && preg_match('/^[A-Za-z]{2}$/', $cc)) ? strtoupper($cc) : null;
}
