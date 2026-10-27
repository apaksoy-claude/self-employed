<?php declare(strict_types=1);
/** @var string $tab */
$items = ['overview' => ['Overview', 'fa-gauge', ''], 'uptime' => ['Up and running', 'fa-heart-pulse', '/uptime'], 'speed' => ['Page speed', 'fa-gauge-high', '/speed'],
    'links' => ['Broken links', 'fa-link-slash', '/links'], 'pictures' => ['Pictures', 'fa-images', '/pictures'], 'errors' => ['Errors', 'fa-bug', ''], 'settings' => ['Settings', 'fa-gear', '/settings']];
?>
<div class="ap-tabs-bar mb-3"><nav class="nav ap-tabs" aria-label="Monitoring">
    <?php foreach ($items as $k => [$l, $i, $p]): $href = $k === 'errors' ? admin_url('logs/errors') : admin_url('system/monitoring' . $p); ?>
        <a class="nav-link<?= $tab === $k ? ' active' : '' ?>" href="<?= e($href) ?>"<?= $tab === $k ? ' aria-current="page"' : '' ?>><i class="fa-solid <?= $i ?> me-1"></i><?= e($l) ?></a>
    <?php endforeach; ?>
</nav></div>
