<?php declare(strict_types=1);
/** @var \AP\Core\View $this */
use AP\Modules\Admin\Ui;

$this->extend('admin::layout');
$big = (int) setting('monitor.big_kb', 350) * 1024;
?>
<?php $this->section('content'); ?>
<?php $this->include('monitor::_tabs', ['tab' => $tab]); ?>
<?php if (!$rows): ?>
    <div class="ap-section"><div class="ap-section-body"><?= Ui::emptyState('All pictures look good.', 'None is bigger than ' . (int) setting('monitor.big_kb', 350) . ' KB and every one has a description.', null, null, 'fa-images') ?></div></div>
<?php else: ?>
<div class="ap-section"><div class="table-responsive"><table class="table mb-0 align-middle"><thead><tr><th style="width:64px"></th><th>Picture</th><th class="text-end">Size</th><th>To look at</th><th></th></tr></thead><tbody>
    <?php foreach ($rows as $m): $p = \AP\Modules\Media\Media::present($m); ?><tr>
        <td><img src="<?= e((string) ($p['thumb'] ?? $p['url'])) ?>" alt="" width="52" height="52" class="rounded object-fit-cover"></td>
        <td><strong><?= e($p['title'] ?: $p['name']) ?></strong><div class="small text-muted"><?= e($p['name']) ?><?= $p['width'] ? ' · ' . (int) $p['width'] . ' × ' . (int) $p['height'] : '' ?></div></td>
        <td class="text-end<?= (int) $m['size'] > $big ? ' text-danger fw-semibold' : '' ?>"><?= e($p['size_text']) ?></td>
        <td class="small"><?= (int) $m['size'] > $big ? 'Big: makes pages slow. ' : '' ?><?= $p['alt'] === '' ? 'No description for screen readers and Google.' : '' ?></td>
        <td class="text-end"><a class="btn btn-sm btn-outline-secondary" href="<?= e(admin_url('media') . '?open=' . (int) $m['id']) ?>">Open</a></td>
    </tr><?php endforeach; ?></tbody></table></div></div>
<p class="small text-muted mt-2">New pictures are made smaller when they are uploaded (Settings &gt; Limits and uploads). Upload a big picture again to shrink it.</p>
<?php endif; ?>
<?php $this->endSection(); ?>
