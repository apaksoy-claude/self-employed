<?php declare(strict_types=1);
/** @var \AP\Core\View $this */
use AP\Modules\Admin\Ui;
use AP\Modules\Money\Money;

$this->extend('admin::layout');
?>
<?php $this->section('content'); ?>
<div class="mn-page">
<?php if (!$rows): ?>
    <div class="ap-section"><div class="ap-section-body"><?= Ui::emptyState('Nobody owes you money.', 'Clients with unpaid invoices appear here so you can email them a statement.', admin_url('money/contacts'), 'Back To Contacts', 'fa-circle-check') ?></div></div>
<?php else: ?>
<form method="post" action="<?= e(admin_url('money/contacts/statements/send')) ?>"><?= csrf_field() ?>
    <p class="text-muted">These clients owe money. Tick the ones to email a statement to. Each gets the same email as "Email Statement", with a button to open the statement and pay online.</p>
    <div class="ap-section"><div class="table-responsive"><table class="table mb-0 align-middle">
        <thead><tr><th style="width:2.2rem"></th><th>Client</th><th>Email</th><th class="text-end">Invoices</th><th class="text-end">Owed</th><th class="text-end">Overdue</th><th>Last Sent</th></tr></thead><tbody>
        <?php foreach ($rows as $d): $c = $d['contact']; $ok = filter_var(trim((string) $c['email']), FILTER_VALIDATE_EMAIL) !== false; ?>
            <tr<?= $ok ? '' : ' class="text-muted"' ?>>
                <td><input class="form-check-input" type="checkbox" name="ids[]" value="<?= (int) $c['id'] ?>"<?= $ok ? ' checked' : ' disabled' ?> aria-label="Send to <?= e((string) $c['name']) ?>"></td>
                <td><a class="fw-semibold text-decoration-none" href="<?= e(admin_url('money/contacts/' . (int) $c['id'] . '/statement')) ?>" target="_blank" rel="noopener"><?= e((string) $c['name']) ?></a></td>
                <td class="small"><?= $ok ? e((string) $c['email']) : 'No email address' ?></td>
                <td class="text-end"><?= (int) $d['count'] ?></td><td class="text-end"><?= e(Money::gbp($d['owed'])) ?></td><td class="text-end<?= $d['late'] > 0 ? ' mn-neg' : '' ?>"><?= e(Money::gbp($d['late'])) ?></td>
                <td class="small"><?= !empty($c['statement_sent_at']) ? e(date('d/m/Y', (int) strtotime((string) $c['statement_sent_at']))) : 'Never' ?></td>
            </tr>
        <?php endforeach; ?></tbody></table></div></div>
    <div class="mn-actions mt-3"><button class="btn btn-primary mn-btn" type="submit" data-confirm="Email a statement to every ticked client?" data-loading="Sending…"><i class="fa-solid fa-paper-plane me-1"></i>Email Statements</button>
        <a class="btn btn-outline-secondary mn-btn" href="<?= e(admin_url('money/contacts')) ?>">Cancel</a></div>
</form>
<?php endif; ?>
</div>
<?php $this->endSection(); ?>
