<?php declare(strict_types=1);
/** @var \AP\Core\View $this */
use AP\Modules\Admin\Ui;
use AP\Modules\Money\Money;

$this->extend('admin::layout');
?>
<?php $this->section('content'); ?>
<div class="mn-page">
    <div class="mn-head"><div><p>Emails that fit one of your rules become expenses by themselves, with the right category and details. The email or its PDF is saved on the expense.</p></div></div>
    <?php if (!$configured): ?>
        <section class="mn-card"><div class="ap-section-body"><?= Ui::emptyState('Gmail is not connected yet.', 'Add your Gmail address and an app password, then switch the import on.', admin_url('settings/money_gmail'), 'Open Email Import Settings', 'fa-envelope') ?></div></section>
    <?php else: ?>
        <section class="mn-card">
            <header><div><h2>Check Now</h2><p>Looks at emails from the last <?= (int) $days ?> days. <?= $on ? 'It also runs by itself on the schedule.' : 'Automatic checking is switched off in Settings.' ?> <?= $del ? 'Matching emails are moved to the bin.' : 'Matching emails stay in your inbox.' ?></p></div></header>
            <div class="mn-actions">
                <form method="post" action="<?= e(admin_url('money/email-import/check')) ?>" data-loading-form><?= csrf_field() ?><button class="btn btn-primary mn-btn" type="submit" data-loading="Checking…"><i class="fa-solid fa-envelope-circle-check me-1"></i>Check Mail Now</button></form>
                <form method="post" action="<?= e(admin_url('money/email-import/test')) ?>" data-loading-form><?= csrf_field() ?><button class="btn btn-outline-primary mn-btn" type="submit" data-loading="Testing…"><i class="fa-solid fa-plug me-1"></i>Test Connection</button></form>
                <form method="post" action="<?= e(admin_url('money/email-import/fetch')) ?>" data-loading-form><?= csrf_field() ?><input type="hidden" name="days" value="<?= (int) $fetchDays ?>"><button class="btn btn-outline-primary mn-btn" type="submit" data-loading="Fetching…" title="Look through the last <?= (int) $fetchDays ?> days with every rule"><i class="fa-solid fa-cloud-arrow-down me-1"></i>Fetch Now (<?= (int) $fetchDays ?> Days)</button></form>
                <a class="btn btn-outline-secondary mn-btn" href="<?= e(admin_url('settings/money_gmail')) ?>"><i class="fa-solid fa-gear me-1"></i>Settings</a>
            </div>
        </section>
    <?php endif; ?>
    <section class="mn-card">
        <header><div><h2>Email Rules</h2><p>A rule says which emails to pick up and what expense to make from them. The first rule that fits an email is used, so drag the most specific rules to the top.</p></div>
            <a class="btn btn-primary mn-btn" href="<?= e(admin_url('money/email-import/rules/new')) ?>" data-money-modal><i class="fa-solid fa-plus me-1"></i>Add A Rule</a></header>
        <?php if (!$book): ?>
            <p class="text-muted">No rules yet. Add your own, or start from four examples (the Heathrow drop-off fee and three Otto invoice rules) and change them to suit.</p>
            <form method="post" action="<?= e(admin_url('money/email-import/rules/starters')) ?>"><?= csrf_field() ?><button class="btn btn-outline-primary mn-btn" type="submit"><i class="fa-solid fa-wand-magic-sparkles me-1"></i>Add Example Rules</button></form>
        <?php else: $idx = 0; ?>
        <div class="mn-rules" data-sortable data-sort-url="<?= e(admin_url('money/email-import/rules/order')) ?>"><?php foreach ($book as $r): $my = $r['on'] === '1' ? $idx++ : null; ?>
            <div class="mn-rule<?= $r['on'] === '1' ? '' : ' is-off' ?>" data-sort-id="<?= e($r['id']) ?>">
                <span class="mn-rule-grip" data-sort-handle tabindex="0" role="button" title="Drag To Change The Order"><i class="fa-solid fa-grip-vertical" aria-hidden="true"></i></span>
                <div class="mn-rule-main">
                    <div class="mn-rule-name"><strong><?= e($r['name']) ?></strong> <?= $r['on'] === '1' ? '<span class="badge text-bg-success">On</span>' : '<span class="badge text-bg-secondary">Off</span>' ?></div>
                    <div class="mn-rule-chips"><?php foreach (\AP\Modules\Money\Gmail::chips($r) as [$l, $v]): ?><span class="mn-rchip"><b><?= e($l) ?></b><?= e($v) ?></span><?php endforeach; ?></div>
                    <div class="mn-rule-makes"><i class="fa-solid fa-arrow-right-long" aria-hidden="true"></i> <?= e(\AP\Modules\Money\Gmail::makes($r)) ?></div>
                </div>
                <div class="mn-rule-actions">
                    <?php if ($configured && $my !== null): ?><form method="post" action="<?= e(admin_url('money/email-import/fetch')) ?>" data-loading-form><?= csrf_field() ?><input type="hidden" name="rule" value="<?= (int) $my ?>"><input type="hidden" name="days" value="<?= (int) $fetchDays ?>"><button class="btn btn-sm btn-outline-primary mn-btn" type="submit" data-loading="Fetching…" title="Look through the last <?= (int) $fetchDays ?> days for emails this rule matches"><i class="fa-solid fa-cloud-arrow-down me-1"></i>Fetch Now</button></form><?php endif; ?>
                    <a class="mn-ibtn" href="<?= e(admin_url('money/email-import/rules/' . $r['id'] . '/edit')) ?>" data-money-modal title="Edit This Rule" aria-label="Edit This Rule"><i class="fa-solid fa-pen"></i></a>
                    <form method="post" action="<?= e(admin_url('money/email-import/rules/toggle')) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= e($r['id']) ?>"><button class="mn-ibtn" type="submit" title="<?= $r['on'] === '1' ? 'Switch This Rule Off' : 'Switch This Rule On' ?>" aria-label="Switch On Or Off"><i class="fa-solid <?= $r['on'] === '1' ? 'fa-toggle-on text-success' : 'fa-toggle-off' ?>"></i></button></form>
                    <form method="post" action="<?= e(admin_url('money/email-import/rules/delete')) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= e($r['id']) ?>"><button class="mn-ibtn is-danger" type="submit" title="Remove This Rule" aria-label="Remove This Rule" data-confirm="Remove the rule &quot;<?= e($r['name']) ?>&quot;? Expenses it already made stay."><i class="fa-solid fa-trash"></i></button></form>
                </div>
            </div>
        <?php endforeach; ?></div><?php endif; ?>
    </section>
    <?php if (!empty($unhandled)): ?>
    <section class="mn-card">
        <header><div><h2>Emails Without A Rule</h2><p>Emails that need a look come first. Make a rule from one, or remove the ones you do not care about.</p></div></header>
        <?= \AP\Modules\Money\Bulk::bar('bulk-unhandled', admin_url('money/email-import/clear'), [['op' => 'remove', 'label' => 'Remove From List', 'icon' => 'fa-trash', 'danger' => true, 'ask' => 'Remove the ticked emails from this list?']], 'emails') ?>
        <div class="table-responsive"><table class="table mb-0 align-middle"><thead><tr><?= \AP\Modules\Money\Bulk::head('bulk-unhandled') ?><th>Checked</th><th>Email</th><th>Result</th><th aria-label="Actions"></th></tr></thead><tbody>
        <?php foreach ($unhandled as $h): ?><tr>
            <?= \AP\Modules\Money\Bulk::cell('bulk-unhandled', (int) $h['id'], (string) $h['subject']) ?>
            <td class="text-nowrap small"><?= e(Money::showDate(substr((string) $h['created_at'], 0, 10))) ?></td>
            <td class="small"><strong><?= e((string) $h['subject']) ?></strong><br><span class="text-muted"><?= e((string) $h['sender']) ?></span></td>
            <td class="small"><?= $h['status'] === 'review' ? Ui::badge('Needs A Look', 'warning') : Ui::badge('No Rule', 'secondary') ?> <span class="text-muted"><?= e((string) $h['note']) ?></span></td>
            <td class="text-end text-nowrap"><a class="btn btn-sm btn-outline-primary mn-btn" href="<?= e(admin_url('money/email-import/rules/new?seen=' . (int) $h['id'])) ?>" data-money-modal><i class="fa-solid fa-wand-magic-sparkles me-1"></i>Create Rule</a>
                <form method="post" action="<?= e(admin_url('money/email-import/clear')) ?>" class="d-inline"><?= csrf_field() ?><input type="hidden" name="ids[]" value="<?= (int) $h['id'] ?>"><button class="mn-ibtn is-danger" type="submit" title="Remove From The List" aria-label="Remove From The List"><i class="fa-solid fa-xmark"></i></button></form></td>
        </tr><?php endforeach; ?></tbody></table></div>
    </section>
    <?php endif; ?>
    <section class="mn-card">
        <header><div><h2>What Was Added</h2><p>Emails that became expenses, or matched a rule but need a look.</p></div></header>
        <?php if (!$history): ?><p class="text-muted mb-0">Nothing yet.</p><?php else: ?>
        <?= \AP\Modules\Money\Bulk::bar('bulk-history', admin_url('money/email-import/clear'), [['op' => 'remove', 'label' => 'Remove From List', 'icon' => 'fa-trash', 'danger' => true, 'ask' => 'Remove the ticked rows from this list? The expenses stay.']], 'rows') ?>
        <div class="table-responsive"><table class="table mb-0 align-middle"><thead><tr><?= \AP\Modules\Money\Bulk::head('bulk-history') ?><th>Checked</th><th>Email</th><th>Result</th><th aria-label="Actions"></th></tr></thead><tbody>
        <?php foreach ($history as $h): ?><tr>
            <?= \AP\Modules\Money\Bulk::cell('bulk-history', (int) $h['id'], (string) $h['subject']) ?>
            <td class="text-nowrap small"><?= e(Money::showDate(substr((string) $h['created_at'], 0, 10))) ?></td>
            <td class="small"><strong><?= e((string) $h['subject']) ?></strong><br><span class="text-muted"><?= e((string) $h['sender']) ?></span></td>
            <td class="small"><?= $h['status'] === 'added' ? Ui::badge('Added', 'success') : Ui::badge('Needs A Look', 'warning') ?> <span class="text-muted"><?= e((string) $h['note']) ?></span></td>
            <td class="text-end text-nowrap"><?php if ((int) $h['expense_id'] > 0): ?><a class="btn btn-sm btn-outline-secondary mn-btn" href="<?= e(admin_url('money/expenses/' . (int) $h['expense_id'])) ?>" data-money-modal>Open Expense</a> <?php endif; ?>
                <form method="post" action="<?= e(admin_url('money/email-import/clear')) ?>" class="d-inline"><?= csrf_field() ?><input type="hidden" name="ids[]" value="<?= (int) $h['id'] ?>"><button class="mn-ibtn is-danger" type="submit" title="Remove From The List" aria-label="Remove From The List"><i class="fa-solid fa-xmark"></i></button></form></td>
        </tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
    </section>
</div>
<?php $this->endSection(); ?>
