<?php declare(strict_types=1);
/** @var \AP\Core\View $this */
use AP\Modules\Admin\Ui;
use AP\Modules\Money\Fields;
use AP\Modules\Money\Money;

$this->extend('admin::layout');
?>
<?php $this->section('content'); ?>
<div class="mn-page">
<?php if ($canEdit): ?>
<form method="post" action="<?= e(admin_url('money/receipts/upload')) ?>" enctype="multipart/form-data" class="mn-card mn-snap" data-snap>
    <?= csrf_field() ?>
    <div class="ap-dropzone" data-dropzone tabindex="0">
        <i class="fa-solid fa-camera" aria-hidden="true"></i>
        <div><strong>Take a photo</strong> of a receipt, or drop files here, or <span class="ap-dz-pick">choose files</span></div>
        <input type="file" name="files[]" multiple class="visually-hidden" accept="image/*,.pdf" capture="environment" aria-label="Take or choose receipt photos">
        <ul class="ap-dz-list list-unstyled small mb-0"></ul>
    </div>
    <button class="btn btn-primary mn-btn mt-2" type="submit" data-loading="Saving…"><i class="fa-solid fa-cloud-arrow-up me-1"></i>Save Receipts</button>
</form>
<?php endif; ?>
<h2 class="mn-h2">To Sort (<?= count($rows) ?>)</h2>
<?php if (!$rows): ?>
    <div class="ap-section"><div class="ap-section-body"><?= Ui::emptyState('Nothing waiting.', 'Photos you save above wait here until you add the amount and category.', '', '', 'fa-receipt') ?></div></div>
<?php else: ?>
<div class="mn-receipts">
<?php foreach ($rows as $r): $fs = $files[(int) $r['id']] ?? []; ?>
    <section class="mn-card mn-receipt">
        <div class="mn-receipt-pic">
            <?php foreach ($fs as $f): $k = Money::fileKind((string) $f['original_name'], (string) $f['mime']); ?>
                <a href="<?= e(admin_url('money/files/' . (int) $f['id'])) ?>" target="_blank" rel="noopener"><?php if ($k[3] === 'image'): ?><img src="<?= e(admin_url('money/files/' . (int) $f['id'])) ?>" alt="Receipt" loading="lazy"><?php else: ?><i class="fa-solid <?= e($k[0]) ?>"></i><span><?= e((string) $f['original_name']) ?></span><?php endif; ?></a>
            <?php endforeach; ?>
        </div>
        <?php if ($canEdit): ?>
        <form method="post" action="<?= e(admin_url('money/receipts/' . (int) $r['id'] . '/sort')) ?>" class="mn-receipt-form">
            <?= csrf_field() ?>
            <?= Fields::text('title', 'What was it for', $r['title'] === 'Receipt To Sort' ? '' : $r['title'], ['required' => true, 'maxlength' => 255]) ?>
            <?= Fields::text('expense_date', 'Date', Money::showDate((string) $r['expense_date']), ['date' => true, 'col' => 'is-half']) ?>
            <?= Fields::text('amount', 'Amount (£)', '', ['col' => 'is-half', 'inputmode' => 'decimal', 'placeholder' => '0.00', 'required' => true]) ?>
            <?= Fields::select('category_id', 'Category', $cats, (int) $r['category_id'], ['none' => 'No category', 'col' => 'is-half']) ?>
            <?= Fields::text('payment_method', 'Paid with', (string) $r['payment_method'], ['col' => 'is-half']) ?>
            <div class="mn-actions"><button class="btn btn-primary mn-btn" type="submit"><i class="fa-solid fa-check me-1"></i>Save As Expense</button></div>
        </form>
        <form method="post" action="<?= e(admin_url('money/receipts/' . (int) $r['id'] . '/discard')) ?>" class="mn-receipt-x"><?= csrf_field() ?><button class="mn-icon" type="submit" data-confirm="Remove this receipt?" aria-label="Remove receipt" title="Remove"><i class="fa-solid fa-trash"></i></button></form>
        <?php endif; ?>
    </section>
<?php endforeach; ?>
</div>
<?php endif; ?>
</div>
<?php $this->endSection(); ?>
