<?php declare(strict_types=1);
/** @var string $tab */
$items = [
    'overview' => ['Overview', 'fa-chart-simple', admin_url('spam')],
    'log' => ['Log', 'fa-list', admin_url('spam/log')],
    'blocked' => ['Blocked addresses', 'fa-ban', admin_url('spam/blocked')],
    'rules' => ['Rules and words', 'fa-filter', admin_url('spam/rules')],
    'robot' => ['Robot check', 'fa-robot', admin_url('settings/security') . '#f_captcha_enabled_wrap'],
];
?>
<nav class="ap-settings-nav" aria-label="Spam pages">
    <div class="ap-snav-title"><i class="fa-solid fa-ban" aria-hidden="true"></i>Spam</div>
    <div class="ap-snav-scroll"><div class="ap-snav-cat"><ul>
        <?php foreach ($items as $key => [$label, $icon, $href]): $on = $key === $tab; ?>
            <li><a class="<?= $on ? 'active' : '' ?>" href="<?= e($href) ?>"<?= $on ? ' aria-current="page"' : '' ?>><i class="fa-solid <?= e($icon) ?>" aria-hidden="true"></i><span><?= e($label) ?></span>
                <?php if ($key === 'robot'): ?><i class="fa-solid fa-arrow-up-right-from-square ap-snav-ext" aria-hidden="true" title="Opens Security settings"></i><?php endif; ?></a></li>
        <?php endforeach; ?>
    </ul></div></div>
</nav>
