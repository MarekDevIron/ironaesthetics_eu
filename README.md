# eu-prepinac — ironaesthetics.eu

Malý prepínač produktových liniek, ktorý nahrádza Laravel appku `ironaesthetics-eu`.
Žiadny framework, žiadny cron, žiadna vlastná databáza — pri requeste sa pozrie
priamo do živých DB shopov a vypíše tlačidlá na produkt pre každý shop, kde existuje.

## Ako funguje

- URL tvar `/P2805A15382` = **reference kombinácie** (`ps_product_attribute.reference`,
  formát `P{id_product}A{id_product_attribute}`).
- Prejde DB z configu; multistore SK/CZ je jedna DB → dva shop views (SK aj CZ),
  HU a RO majú vlastnú DB. Shop views sa čítajú z `ps_shop` + `ps_shop_url`, takže
  sa prispôsobia aj keď pribudne jazyk/shop.
- Produktová URL sa skladá rovnakou logikou ako v PS 1.6 (`Link::getProductLink`
  + `Dispatcher::createUrl`): route pravidlo si prečíta z `PS_ROUTE_product`
  daného shopu (fallback na default `{category:/}{id}-{rewrite}{-:ean13}.html`),
  doplní anchor kombinácie (`#/velikost-m`) ako `Product::getAnchor()`.
- Tlačidlo ukáže len pre shopy, kde kombinácia existuje a produkt je aktívny.
- Výsledok sa kešuje do súboru (`cache_ttl`, default 10 min).

## Lokálny beh

```bash
cp config.example.php config.php   # a doplň heslá (Monsta-Web/monsta/ironaesthetics-pristupy.txt)
php -S 127.0.0.1:8060 index.php
open http://127.0.0.1:8060/P2805A15382
```

Na produkcii (Apache + PHP 8.2) stačí nahodiť obsah tejto zložky do webrootu —
`.htaccess` presmeruje všetko na `index.php`.

## Rozdiely voči Laravel verzii

| | Laravel | eu-prepinac |
|---|---|---|
| Dáta | kópia cez cron import (`asdata_presta_api`) | priamy SELECT do live DB |
| Infrastruktúra | composer, node/vite build, MySQL, scheduler | 1 PHP súbor + vlajky |
| Shopy | vždy 4 natvralo z tabuľky | dynamicky z `ps_shop` |
| Neexistujúci produkt | 404 | 404, tlačidlá len pre aktívne shopy |

## Poznámky

- Jazykový prefix do URL nepridáva — každé tlačidlo cielí na hlavný jazyk
  daného shop view (rovnako ako webservice kontext v starej verzii).
- Heslá sú mimo gitu (`config.php` v `.gitignore`, `.htaccess` ho blokuje).
