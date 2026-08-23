<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>404 — ironaesthetics.eu</title>
<style>
<?= require __DIR__.'/style.php' ?>
</style>
</head>
<body>
<div class="wrap center">
    <img class="logo" src="/img/logo.png" alt="IRON AESTHETICS">
    <div class="big-code">404</div>
    <?php if (!empty($errors)): ?>
    <pre style="color:#f66;font-size:11px;text-align:left;max-width:700px;margin-top:24px;white-space:pre-wrap"><?php
        echo htmlspecialchars(implode("\n", $errors)); // DOCASNE — diagnostika
    ?></pre>
    <?php endif; ?>
</div>
<footer class="credit">ironaesthetics.eu</footer>
</body>
</html>
