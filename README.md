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
- Výsledok sa kešuje (APCu, inak súbor v `cache/`) na `cache_ttl` (default 10 min),
  zoznam shopov a ich routing config zvlášť na `shop_ttl` (default 1 h).

## Geolokácia a jazyk nadpisu

Cieľ: návštevník z Maďarska má vidieť názov produktu po maďarsky, nie po slovensky.
Názov ide z `pl.name` v hlavnom jazyku každého shopu, takže ho appka má pre všetky
shopy naraz — vyberá sa len, ktorý sa zobrazí.

- **Server** (`geo_country()` v `lib.php`) číta jedine hlavičku `HTTP_CF_IPCOUNTRY`.
  `ironaesthetics.eu` **nejde cez Cloudflare** (NS aj A záznam mieria na asdata /
  `185.66.200.100`), takže táto hlavička v produkcii nepríde a funkcia vždy vráti `null`.
  Vetva v `index.php`, ktorá podľa `$geo` prepína `$title`, sa preto reálne uplatní až
  keby web niekedy za Cloudflare išiel. Žiadne sieťové volanie z PHP sa nerobí — request
  by to len zdržalo.
- **Prehliadač** (JS v `views/product.php`) je preto hlavná cesta: zavolá
  `get.geojs.io/v1/ip/country.json`, podľa kódu krajiny nájde tlačidlo cez `data-cc`
  a z jeho `data-name` prepíše `<h1 id="product-title">`, `document.title` aj
  `<html lang>`; navyše to tlačidlo zvýrazní (`.rec` + odznak). Každé tlačidlo teda
  nesie názov produktu vo svojom jazyku v `data-name` — inak by JS nemal odkiaľ brať text.
- Kým fetch dobehne (rádovo stovky ms), je v nadpise názov z prvého shopu v poradí
  `config['databases']` (typicky SK). Krajina mimo SK/CZ/HU/RO alebo nedostupné geo API
  = nadpis aj tlačidlá ostanú v tomto východiskovom stave, nič sa nerozbije.
- Stránka je `noindex, nofollow`, takže jazyk „pre robota" neriešime.

## Lokálny beh

```bash
cp config.example.php config.php   # a doplň heslá (Monsta-Web/monsta/ironaesthetics-pristupy.txt)
php -S 127.0.0.1:8060 index.php
open http://127.0.0.1:8060/P2805A15382
```

Na produkcii stačí nahodiť obsah tejto zložky do webrootu — `.htaccess` presmeruje
všetko na `index.php`. Kód je kompatibilný s **PHP 7.4+** (overené `php -l` pod 7.4),
takže na verzii PHP na cieľovom serveri nezáleží.

## Nasadenie na Contabo (web11)

Nasadené 24. 8. 2026 na `ia.eu.iron.getdevbox.com` — ISPConfig web **web11** pod
klientom `client0` (nie je to náš `web8`, ten je pre zlúčený multistore).

| | |
|---|---|
| Docroot | `/var/www/clients/client0/web11/web` |
| Shell user | `default_iron_eu` |
| PHP | 7.4, pool `web11.sock` |

```bash
rsync -rlpt --exclude '.git' --exclude '.preview' --exclude '.DS_Store' \
  index.php lib.php .htaccess views img fonts \
  default_iron_eu@169.58.34.110:/var/www/clients/client0/web11/web/
```

`config.php` sa **nenahráva do docrootu**. Od 22. 9. 2026 leží v ISPConfig adresári
`/var/www/clients/client0/web11/private/config.php` (mimo `web/`, Apache ho nikdy
neservíruje), rovnako keš v `private/cache/`. `index.php` skúša najprv
`../private/config.php`, až potom `config.php` vedľa seba; `cache_dir()` to isté pre keš.
Zálohy configu tiež len do `private/` alebo do `~`, nikdy do `web/` — `.htaccess`
síce blokuje `config.*` aj `*.bak`, ale je to len poistka.

```bash
# prvé nasadenie
P=/var/www/clients/client0/web11/private
install -m 600 config.php $P/config.php      # alebo scp + chmod 600
mkdir -p $P/cache && chmod 770 $P/cache      # PHP pool beží ako user web11, nie ako shell user
cd /var/www/clients/client0/web11/web && mv standard_index.html standard_index.html.povodny
```

Ak `private/cache` nie je pre PHP zapisovateľný, appka skúsi `web/cache`; ak ani ten,
keš sa **vypne** a raz to zaloguje (`error_log`). Do `/tmp` nikdy nepadá — je zdieľaný
a názvy súborov sú predvídateľné.

Overené po nasadení: `config.php`, `cache/` aj `.htaccess` vracajú **403**, `lib.php`
sa vykoná a vráti prázdne telo (zdroják nie je vidno), hlavička `X-Robots-Tag` sedí.
Živé DB sú z Contaba dosiahnuteľné — appka tam beží rovnako ako na starom hostingu
(prvý request ~1,7 s, ďalšie z keše ~0,15 s).

### Po zlúčení shopov: jeden záznam v configu (hotové 22. 9. 2026)

Od 22. 9. 2026 appka na web11 číta zlúčený multistore (`web8`, DB `c1all` na tom istom
boxe) — `databases` má **jeden** záznam, nekešovaný lookup klesol z ~0,6 s na ~0,1 s
(odpadli tri spojenia na asdata). Prepnutie robí `tools/contabo/web11/prepni-config-c1all.sh`
v koreni projektu IRON. Odkazy na HU/RO sú odvtedy bez `www` (hlavná doména v
`ps_shop_url` web8).

```php
'databases' => [
    [
        'name'   => 'ALL',
        'host'   => 'localhost',      // DB je na tom istom boxe
        'user'   => '…',              // ideálne vyhradený SELECT-only user
        'pass'   => '…',
        'db'     => 'c1all',
        'prefix' => 'ps_',
    ],
],
```

V kóde sa meniť nemusí nič — appka si štyri shop views načíta sama z `ps_shop`
a `ps_shop_url`, presne tak, ako dnes rozlišuje SK a CZ v jednej SKCZ databáze.

Tým **zmizne aj to, že na tomto boxe ležia prihlásenia do živých databáz**: appka
bude čítať lokálnu DB a k živým shopom už nepotrebuje prístup vôbec.

Zmení sa aj správanie lookupu: po zlúčení sú kombinácie spárované cez referenciu,
takže jedna `P…A…` vráti **jeden produkt so štyrmi shopmi** namiesto troch
samostatných hľadaní v troch databázach.

### Bezpečnostné poznámky

- DB useri v configu sú dnes **vlastníci tých databáz, nie read-only** — appka pritom
  robí výhradne `SELECT`. Kým sa nepoužije `c1all`, patrí sem vyhradený user
  s právom `SELECT` na `ps_product*`, `ps_shop*`, `ps_configuration`.
- Na 404 sa každý neznámy kód dopytuje DB (kešujú sa len nálezy) — bežný bot scan
  to unesie, pri agresívnom by pomohol rate limit v Apache.
- `ia.eu.iron.getdevbox.com` je verejne dostupná **bez basic auth** (na rozdiel od
  `.all` domén). Stránka je `noindex, nofollow` a názvy produktov aj URL sú verejné,
  ale ak má byť dev doména zavretá, treba doplniť `.htpasswd`.
- Do webrootu nikdy nenasadzovať `test.php`, `dev.sh`, `README.md` ani `.git` —
  `.htaccess` ich síce blokuje, ale najistejšie je ich tam nemať (viď `rsync` vyššie).

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
- Keď na produkcii treba vidieť, prečo lookup zlyhal, dočasne `'debug' => true`
  v `config.php` — inak sa chyby DB píšu len do error_logu, nie na stránku.
