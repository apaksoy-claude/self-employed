<?php declare(strict_types=1);
/** Money > Business Setup. @var \AP\Core\View $this */
use AP\Modules\Money\Trades;

$this->extend('admin::layout');
?>
<?php $this->section('content'); ?>
<div class="mn-page su-page">
    <p class="text-muted mb-3">Add a ready-made set for your kind of work: expense categories filed to the right box on your tax return, income streams, ways of paying and dashboard cards. It only adds. Anything you already have with the same name is left exactly as it is.</p>
    <?php if (!empty($saved['trades'])): ?>
        <div class="su-last"><i class="fa-solid fa-circle-check" aria-hidden="true"></i><span>Set up so far for <strong><?= e(Trades::names((array) $saved['trades'])) ?></strong><?= !empty($saved['at']) ? ' on ' . e(\AP\Modules\Money\Money::showDate(substr((string) $saved['at'], 0, 10))) : '' ?>. Pick more below to add another kind of work.</span></div>
    <?php endif; ?>
    <form method="post" action="<?= e(admin_url('money/setup/apply')) ?>" data-su-form data-su-ajax novalidate><?= csrf_field() ?>
        <section class="ap-section su-card"><div class="ap-section-body">
            <?php $this->include('money::setup/_choice', ['choice' => $choice, 'planJson' => $planJson, 'context' => 'admin', 'vehicleTouched' => true, 'layoutCustom' => $layoutCustom]); ?>
        </div></section>
        <div class="su-result" data-su-result hidden role="status" tabindex="-1">
            <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
            <div><strong>Done.</strong> <span data-su-result-text></span>
                <div class="su-result-links">
                    <a href="<?= e(admin_url('money/categories')) ?>">Open Categories</a>
                    <a href="<?= e(admin_url('money/streams')) ?>">Open Income Streams</a>
                    <a href="<?= e(admin_url()) ?>">Open The Dashboard</a>
                </div>
            </div>
        </div>
        <div class="su-actions su-actions-admin">
            <button class="btn btn-primary mn-btn su-go" type="submit"><i class="fa-solid fa-wand-magic-sparkles me-1" aria-hidden="true"></i><span>Add To My Records</span></button>
            <a class="btn btn-outline-secondary mn-btn" href="<?= e(admin_url('settings/money')) ?>"><i class="fa-solid fa-gear me-1" aria-hidden="true"></i>Money Settings</a>
        </div>
    </form>
</div>
<?php $this->endSection(); ?>
