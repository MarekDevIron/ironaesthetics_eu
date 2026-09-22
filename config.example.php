<?php
/**
 * Skopíruj na config.php a doplň heslá. config.php je v .gitignore.
 * Databázy sú prechádzané v tomto poradí — tak istom, ako idú tlačidlá na stránke.
 */
return [
    // Ako dlho kešovať výsledok lookupu (sekundy). 0 = vypnúť cache.
    'cache_ttl' => 600,

    // Ako dlho kešovať zoznam shop views + ich URL/routing config (sekundy).
    // Toto sa mení len keď niekto zapne/vypne shop alebo prehodí pretty URL nastavenia
    // v BO — pokojne dlhšie ako cache_ttl, šetrí to DB pri KAŽDOM (aj nekešovanom) requeste.
    'shop_ttl' => 3600,

    // Diagnostika. true = na 404 stránke sa vypíšu chyby DB a zapnú sa PHP hlášky.
    // Na produkcii nechaj false — inak sa návštevníkovi ukáže host, meno DB usera
    // a schéma. Chyby idú tak či tak vždy do error_logu.
    'debug' => false,

    // Zlúčený multistore c1all na tom istom boxe (web8) — jeden záznam, štyri shop views
    // si appka načíta z ps_shop + ps_shop_url. User má plné práva na DB (SELECT-only
    // user nie je k dispozícii), preto config patrí do ../private/, nie do docrootu.
    'databases' => [
        [
            'name'   => 'ALL',
            'host'   => 'localhost',
            'user'   => '',
            'pass'   => '',
            'db'     => 'c1all',
            'prefix' => 'ps_',
        ],
    ],
];
