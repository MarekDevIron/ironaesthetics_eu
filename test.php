<?php
/**
 * CLI test: php test.php [kod]
 * Vypíše nájdené shop views a výsledok lookupu pre daný kód.
 */
declare(strict_types=1);

require __DIR__.'/lib.php';

$config = require __DIR__.'/config.php';
$code = isset($argv[1]) ? preg_replace('~[^A-Za-z0-9._-]~', '', $argv[1]) : '';

foreach ($config['databases'] as $dbConf) {
    try {
        $pdo = db($dbConf);
        echo "=== {$dbConf['name']} ({$dbConf['db']}) ===\n";
        foreach (discover_shops($pdo, $dbConf['prefix'] ?? 'ps_') as $s) {
            printf(
                "  shop #%d group #%d  %-28s uri='%s' lang=%s(%d) '%s'\n",
                $s['id_shop'], $s['id_shop_group'], $s['domain'], $s['uri'],
                $s['lang_iso'], $s['lang_id'], $s['lang_name']
            );
            printf(
                "    route_product=%s rewriting=%s anchor_sep=%s\n",
                var_export(cfg($pdo, $dbConf['prefix'] ?? 'ps_', 'PS_ROUTE_product', $s['id_shop_group'], $s['id_shop']), true),
                var_export(cfg($pdo, $dbConf['prefix'] ?? 'ps_', 'PS_REWRITING_SETTINGS', $s['id_shop_group'], $s['id_shop']), true),
                var_export(cfg($pdo, $dbConf['prefix'] ?? 'ps_', 'PS_ATTRIBUTE_ANCHOR_SEPARATOR', $s['id_shop_group'], $s['id_shop']), true)
            );
        }
    } catch (Throwable $e) {
        echo "=== {$dbConf['name']} — CHYBA: ".$e->getMessage()."\n";
    }
}

if ($code !== '') {
    echo "\n### lookup('$code')\n";
    $res = lookup($config, $code);
    if ($res['errors']) {
        echo "errors:\n  ".implode("\n  ", $res['errors'])."\n";
    }
    if (!$res['found']) {
        echo "NIČ NENÁJDENÉ\n";
        exit(1);
    }
    echo "title: {$res['title']}\n";
    foreach ($res['buttons'] as $b) {
        echo "  [{$b['iso']}] {$b['lang_name']} ({$b['domain']})\n    -> {$b['url']}\n";
    }
}
