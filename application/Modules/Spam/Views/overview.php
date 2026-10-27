<?php declare(strict_types=1);
/** @var \AP\Core\View $this @var array $stats */
use AP\Core\Captcha;

$this->extend('admin::layout');
$robot = Captcha::enabled('forms');
?>
<?php $this->section('content'); ?>
<div class="ap-hub">
    <div class="ap-hub-nav"><?php $this->include('spam::_nav', ['tab' => $tab]); ?></div>
    <div class="ap-hub-body ap-sections">
        <div class="ap-stat-row">
            <a class="ap-stat-card" href="<?= e(admin_url('spam/log') . '?verdict=blocked') ?>"><i class="fa-solid fa-hand" aria-hidden="true"></i><div><strong><?= number_format((int) $stats['stopped']) ?></strong><span>Stopped in the last 30 days</span></div></a>
            <a class="ap-stat-card" href="<?= e(admin_url('spam/log') . '?verdict=held') ?>"><i class="fa-solid fa-hourglass-half" aria-hidden="true"></i><div><strong><?= number_format((int) $stats['held']) ?></strong><span>Held for a check</span></div></a>
            <a class="ap-stat-card" href="<?= e(admin_url('spam/blocked')) ?>"><i class="fa-solid fa-ban" aria-hidden="true"></i><div><strong><?= number_format((int) $stats['blocked']) ?></strong><span>Blocked addresses</span></div></a>
        </div>
        <div class="ap-two-cards">
            <section class="ap-section"><div class="ap-section-head ap-section-head-static"><h2><i class="fa-solid fa-layer-group" aria-hidden="true"></i>Where spam comes in</h2></div>
                <div class="ap-section-body pb-3">
                    <?php if (!$stats['by_form']): ?><p class="small text-muted mb-0">Nothing stopped yet.</p><?php else: $max = max($stats['by_form']); ?>
                        <?php foreach ($stats['by_form'] as $form => $n): ?>
                            <div class="ap-bar-row"><span><?= e($forms[$form] ?? ucfirst((string) $form)) ?></span><div class="ap-bar"><i style="width:<?= (int) round($n / $max * 100) ?>%"></i></div><strong><?= (int) $n ?></strong></div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div></section>
            <section class="ap-section"><div class="ap-section-head ap-section-head-static"><h2><i class="fa-solid fa-network-wired" aria-hidden="true"></i>Busiest addresses</h2></div>
                <div class="ap-section-body pb-3">
                    <?php if (!$stats['busiest']): ?><p class="small text-muted mb-0">Nothing yet.</p><?php else: ?>
                        <?php foreach ($stats['busiest'] as $ip => $n): $isBlocked = in_array((string) $ip, $blockedIps, true); ?>
                            <div class="ap-ip-row"><code class="ap-path"><?= e((string) $ip) ?></code><span class="text-muted small"><?= (int) $n ?> time(s)</span>
                                <?php if ($isBlocked): ?><span class="badge ap-pill ap-pill-grey ms-auto">Blocked</span>
                                <?php else: ?><form method="post" action="<?= e(admin_url('spam/blocked')) ?>" class="ms-auto"><?= csrf_field() ?><input type="hidden" name="ip" value="<?= e((string) $ip) ?>"><input type="hidden" name="note" value="Blocked from the spam overview"><button class="btn btn-sm btn-outline-danger"><i class="fa-solid fa-ban me-1"></i>Block</button></form><?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div></section>
        </div>
        <section class="ap-section"><div class="ap-section-head ap-section-head-static"><h2><i class="fa-solid fa-shield-halved" aria-hidden="true"></i>What is checked</h2></div>
            <div class="ap-section-body pb-3"><ul class="ap-check-list">
                <li class="<?= setting('spam.enabled', true) ? 'is-on' : '' ?>"><strong>Public forms</strong> (the contact form and others): stopped messages are not sent; held messages are saved and marked as spam so you can look at them.</li>
                <li class="<?= setting('spam.honeypot', true) ? 'is-on' : '' ?>"><strong>Hidden trap field:</strong> a box people never see; robots fill it in.</li>
                <li class="<?= (int) setting('spam.min_seconds', 3) > 0 ? 'is-on' : '' ?>"><strong>Timing:</strong> forms sent in under <?= (int) setting('spam.min_seconds', 3) ?> second(s), or without opening the page, are stopped.</li>
                <li class="<?= $robot ? 'is-on' : '' ?>"><strong>Robot check:</strong> <?= $robot ? e(Captcha::SERVICES[Captcha::service()]['label']) . ' is on.' : 'off. Switch it on in' ?> <?php if (!$robot): ?><a href="<?= e(admin_url('settings/security') . '#f_captcha_enabled_wrap') ?>">Security settings</a>.<?php endif; ?></li>
                <li class="is-on"><strong>Blocked addresses</strong> cannot send any form on the site. Staff who are signed in are never checked.</li>
            </ul></div></section>
    </div>
</div>
<?php $this->endSection(); ?>
