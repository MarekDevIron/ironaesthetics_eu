<?php
// geolokácia: krajina návštevníka → zvýrazníme tlačidlo s textom v jeho jazyku
// a názov produktu ukážeme v jazyku toho shopu (nadpis, <title> aj <html lang>).
$geoLabels = [
    'SK' => 'Odporúčaný obchod',
    'CZ' => 'Doporučený obchod',
    'HU' => 'Ajánlott bolt',
    'RO' => 'Magazin recomandat',
];
// jazyk pre <html lang> podľa krajiny (BCP 47 — pre CZ je to 'cs', nie 'cz',
// ktoré má PrestaShop v ps_lang.iso_code)
$geoLangs = ['SK' => 'sk', 'CZ' => 'cs', 'HU' => 'hu', 'RO' => 'ro'];

$geo = strtoupper((string)($geo ?? ''));
// Keď krajinu vieme už na serveri, $title je z jej shopu (viď index.php) — nech
// tomu zodpovedá aj lang dokumentu. Bez Cloudflare je $geo prázdne, vtedy ostáva
// 'en' a oboje prepíše JS fallback nižšie.
$htmlLang = $geoLangs[$geo] ?? 'en';
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($htmlLang) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?= htmlspecialchars($title ?? $code) ?></title>
<style>
<?= require __DIR__.'/style.php' ?>
</style>
</head>
<body>
<div class="wrap">
    <img class="logo" src="/img/logo.png" alt="IRON AESTHETICS">
    <h1 class="title" id="product-title"><?= htmlspecialchars($title ?? '') ?></h1>
    <div class="grid">
        <?php foreach ($buttons as $b):
            $cc  = strtoupper((string)$b['iso']);
            $rec = ($cc === $geo && isset($geoLabels[$cc]));
        ?>
        <a class="btn<?= $rec ? ' rec' : '' ?>" data-cc="<?= htmlspecialchars($cc) ?>"
           data-name="<?= htmlspecialchars((string)($b['name'] ?? '')) ?>"
           href="<?= htmlspecialchars($b['url']) ?>" target="_blank" rel="noopener">
            <img class="flag" src="/img/flags/<?= htmlspecialchars($b['iso']) ?>.png" alt="" width="32">
            <span class="txt">
                <span class="country"><?= htmlspecialchars($b['lang_name']) ?></span>
                <span class="domain"><?= htmlspecialchars($b['domain']) ?></span>
            </span>
            <?php if ($rec): ?>
            <span class="chip"><?= htmlspecialchars($geoLabels[$cc]) ?></span>
            <?php else: ?>
            <span class="arrow">&#8594;</span>
            <?php endif; ?>
        </a>
        <?php endforeach; ?>
    </div>
</div>
<footer class="credit">ironaesthetics.eu</footer>

<?php if ($geo === '' || !isset($geoLabels[$geo])): ?>
<script>
// Fallback v prehliadači — server krajinu nevie (ironaesthetics.eu nejde cez
// Cloudflare, takže HTTP_CF_IPCOUNTRY nikdy nepríde a geo_country() vracia null).
// Robí dve veci: prepíše nadpis/<title> na názov produktu v jazyku návštevníka
// (berie ho z data-name príslušného tlačidla) a zvýrazní to tlačidlo.
(function () {
    var labels = <?= json_encode($geoLabels) ?>;
    var langs  = <?= json_encode($geoLangs) ?>;
    fetch('https://get.geojs.io/v1/ip/country.json', { cache: 'no-store' })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            var cc = (d && d.country || '').toUpperCase();
            // ISO 3166-1 alpha-2; čokoľvek iné by rozbilo CSS selektor nižšie
            if (!/^[A-Z]{2}$/.test(cc)) return;
            var b = document.querySelector('.btn[data-cc="' + cc + '"]');
            if (!b) return;

            // názov produktu v jazyku shopu danej krajiny
            var name = b.getAttribute('data-name');
            if (name) {
                var h = document.getElementById('product-title');
                if (h) h.textContent = name;
                document.title = name;
                if (langs[cc]) document.documentElement.lang = langs[cc];
            }

            if (b.classList.contains('rec')) return;
            b.classList.add('rec');
            var a = b.querySelector('.arrow');
            if (a) a.remove();
            var chip = document.createElement('span');
            chip.className = 'chip';
            chip.textContent = labels[cc] || 'Recommended';
            b.appendChild(chip);
        })
        .catch(function () {});
})();
</script>
<?php endif; ?>
</body>
</html>
