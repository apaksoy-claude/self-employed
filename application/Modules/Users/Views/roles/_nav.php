<?php declare(strict_types=1);
/** @var string $tab */
try {
    $roleCount = (int) app(\AP\Core\DB::class)->table('roles')->count();
} catch (\Throwable) {
    $roleCount = 0;
}
$twoStep = (string) setting('security.two_factor_required', 'none');
$items = [
    'access' => ['Who can open what', 'fa-table-cells', admin_url('roles/access'), ''],
    'roles' => ['Roles', 'fa-user-shield', admin_url('roles'), (string) $roleCount],
    'twostep' => ['Two-step sign-in', 'fa-shield-halved', admin_url('settings/security') . '#f_security_two_factor_required_wrap', $twoStep === 'none' ? 'Optional' : ($twoStep === 'all' ? 'Everyone' : 'Admins')],
];
?>
<nav class="ap-settings-nav" aria-label="Roles and access pages">
    <div class="ap-snav-title"><i class="fa-solid fa-user-shield" aria-hidden="true"></i>Roles and Access</div>
    <div class="ap-snav-scroll"><div class="ap-snav-cat"><ul>
        <?php foreach ($items as $key => [$label, $icon, $href, $count]): $on = $key === $tab; ?>
            <li><a class="<?= $on ? 'active' : '' ?>" href="<?= e($href) ?>"<?= $on ? ' aria-current="page"' : '' ?>><i class="fa-solid <?= e($icon) ?>" aria-hidden="true"></i><span><?= e($label) ?></span><?php if ($count !== ''): ?><span class="ap-snav-count"><?= e($count) ?></span><?php endif; ?></a></li>
        <?php endforeach; ?>
    </ul></div></div>
</nav>
