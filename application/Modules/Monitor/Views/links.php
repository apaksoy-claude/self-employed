<?php declare(strict_types=1);
/** @var \AP\Core\View $this */
use AP\Modules\Admin\Ui;

$this->extend('admin::layout');
$queue = count((array) ($state['links_queue'] ?? []));
?>
<?php $this->section('actions'); ?>
<form method="post" action="<?= e(admin_url('system/monitoring/run/links')) ?>"><?= csrf_field() ?><button class="btn btn-outline-secondary"><i class="fa-solid fa-magnifying-glass me-1"></i><?= $queue ? 'Read the next pages' : 'Look for broken links now' ?></button></form>
<?php $this->endSection(); ?>
<?php $this->section('content'); ?>
<?php $this->include('monitor::_tabs', ['tab' => $tab]); ?>
<p class="small text-muted"><?= $queue ? $queue . ' page(s) still to read in this round.' : (!empty($state['links_finished']) ? 'Last round finished ' . e(format_date(gmdate('Y-m-d H:i:s', (int) $state['links_finished']), 'd M H:i')) . '.' : 'No round has run yet.') ?></p>
<?php if (!$rows): ?>
    <div class="ap-section"><div class="ap-section-body"><?= Ui::emptyState('No broken links found.', 'Links are checked every week on the day in the settings.', null, null, 'fa-link') ?></div></div>
<?php else: ?>
<div class="ap-section"><div class="table-responsive"><table class="table mb-0 align-middle"><thead><tr><th>On page</th><th>Broken link</th><th>Answer</th><th>Found</th><th></th></tr></thead><tbody>
    <?php foreach ($rows as $r): ?><tr>
        <td class="ap-break small"><a href="<?= e((string) $r['page_url']) ?>" target="_blank" rel="noopener"><?= e((string) (parse_url((string) $r['page_url'], PHP_URL_PATH) ?: '/')) ?></a></td>
        <td class="ap-break small"><?php if ($r['link_text']): ?><strong><?= e((string) $r['link_text']) ?></strong><br><?php endif; ?><span class="text-muted"><?= e((string) $r['link_url']) ?></span><?= (int) $r['external'] ? ' <span class="ap-lock-pill">other website</span>' : '' ?></td>
        <td><span class="ap-status-pill is-failed"><?= (int) $r['status_code'] ?: 'no answer' ?></span></td>
        <td class="small text-muted text-nowrap"><?= e(format_date((string) $r['first_found'], 'd M')) ?></td>
        <td class="text-end text-nowrap"><?php if (!(int) $r['external'] && can('seo.redirects')): ?><a class="btn btn-sm btn-outline-primary" href="<?= e(admin_url('redirects') . '?from=' . rawurlencode((string) parse_url((string) $r['link_url'], PHP_URL_PATH)) . '#add') ?>">Redirect</a><?php endif; ?>
            <form class="d-inline" method="post" action="<?= e(admin_url('system/monitoring/links/' . (int) $r['id'] . '/delete')) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-outline-secondary" title="Remove from the list"><i class="fa-solid fa-xmark"></i></button></form></td>
    </tr><?php endforeach; ?></tbody></table></div></div>
<?php endif; ?>
<?php $this->endSection(); ?>
