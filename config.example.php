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

    // Live databázy shopov (na serveri 185.66.200.100 sa pripájaš cez localhost).
    'databases' => [
        [
            'name' => 'SK/CZ',
            'host' => '185.66.200.100',
            'user' => 'c0ironaesthetics',
            'pass' => '…',
            'db'   => 'c0ironaesthetics',   // multistore SK + CZ — dva shop views v jednej DB
            'prefix' => 'ps_',
        ],
        [
            'name' => 'HU',
            'host' => '185.66.200.100',
            'user' => 'c0monstahu',
            'pass' => '…',
            'db'   => 'c0monstahu',
            'prefix' => 'ps_',
        ],
        [
            'name' => 'RO',
            'host' => '185.66.200.100',
            'user' => 'c0iron_ro',
            'pass' => '…',
            'db'   => 'c0ironaesthetics_ro',
            'prefix' => 'ps_',
        ],
    ],
];
