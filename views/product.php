<!DOCTYPE html>
<html lang="en">
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
<?php
// geolokácia: krajina návštevníka → zvýrazníme tlačidlo s textom v jeho jazyku
$geoLabels = [
    'SK' => 'Odporúčaný obchod',
    'CZ' => 'Doporučený obchod',
    'HU' => 'Ajánlott bolt',
    'RO' => 'Magazin recomandat',
];
$geo = strtoupper((string)($geo ?? ''));
?>
<div class="wrap">
    <img class="logo" src="/img/logo.png" alt="IRON AESTHETICS">
    <h1 class="title"><?= htmlspecialchars($title ?? '') ?></h1>
    <div class="grid">
        <?php foreach ($buttons as $b):
            $cc  = strtoupper((string)$b['iso']);
            $rec = ($cc === $geo && isset($geoLabels[$cc]));
        ?>
        <a class="btn<?= $rec ? ' rec' : '' ?>" data-cc="<?= htmlspecialchars($cc) ?>"
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
// fallback v prehliadači — keď server nevedel zistiť krajinu (lokálna IP, zlyhané API)
(function () {
    var labels = <?= json_encode($geoLabels) ?>;
    fetch('https://get.geojs.io/v1/ip/country.json', { cache: 'no-store' })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            var cc = (d && d.country || '').toUpperCase();
            var b = document.querySelector('.btn[data-cc="' + cc + '"]');
            if (!b || b.classList.contains('rec')) return;
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
