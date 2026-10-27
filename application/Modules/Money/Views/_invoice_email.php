<?php declare(strict_types=1);
/** @var \AP\Core\View $this */
use AP\Modules\Money\Controllers\Forms;
use AP\Modules\Money\Fields;

$reminder = $kind === 'reminder';
?>
<form method="post" action="<?= e(admin_url('money/invoices/' . (int) $inv['id'] . '/email')) ?>" class="mn-form" novalidate><?= csrf_field() ?>
    <input type="hidden" name="kind" value="<?= e($kind) ?>">
    <h2 class="mn-form-title"><?= $reminder ? 'Send Reminder' : 'Email Invoice' ?> <small><?= e((string) $inv['number']) ?></small></h2>
    <div class="mn-fields">
        <?= Fields::text('to', 'Send to', $to, ['type' => 'email', 'required' => true, 'maxlength' => 190, 'placeholder' => 'client@example.com', 'help' => $inv['emailed_at'] ? 'Last emailed to ' . (string) $inv['emailed_to'] . ' on ' . date('d/m/Y H:i', (int) strtotime((string) $inv['emailed_at'] . ' UTC')) . '.' : 'Taken from the client. Change it if you need to.']) ?>
        <?= Fields::text('subject', 'Subject', $subject, ['required' => true, 'maxlength' => 150]) ?>
        <?= Fields::area('message', 'Your message', $message, ['rows' => 5, 'help' => 'Shown above the invoice summary. Leave a blank line between paragraphs.']) ?>
    </div>
    <label class="form-check form-switch mn-check"><input type="hidden" name="copy" value="0"><input class="form-check-input" type="checkbox" role="switch" name="copy" value="1"<?= $copy ? ' checked' : '' ?>> <span class="form-check-label">Send me a copy</span></label>
    <div class="mn-note small text-muted mt-2">
        <?php if ($online): ?><i class="fa-solid fa-link me-1"></i>The email has a button to the private pay page, where the client can pay by card or PayPal<?= $bank ? ' or see your bank details' : '' ?>. <a href="<?= e($link) ?>" target="_blank" rel="noopener">Open the pay page</a> · <button type="button" class="btn btn-link btn-sm p-0 align-baseline" data-copy="<?= e($link) ?>">Copy the link</button>
        <?php else: ?><i class="fa-solid fa-circle-info me-1"></i>Card and PayPal are not switched on yet, so the page only shows the invoice<?= $bank ? ' and your bank details' : '' ?>. Switch them on in <a href="<?= e(admin_url('settings/money_pay')) ?>">Settings &gt; Invoice Payments</a>. <a href="<?= e($link) ?>" target="_blank" rel="noopener">Open the page</a><?php endif; ?>
    </div>
    <div class="mn-actions"><button class="btn btn-primary mn-btn" type="submit" data-loading="Sending…"><i class="fa-solid fa-paper-plane me-1"></i><?= $reminder ? 'Send Reminder' : 'Send Invoice' ?></button>
        <?php if (!empty($modal)): ?><button class="btn btn-outline-secondary mn-btn" type="button" data-modal-close>Cancel</button><?php else: ?><a class="btn btn-outline-secondary mn-btn" href="<?= e(admin_url('money/invoices/' . (int) $inv['id'])) ?>">Cancel</a><?php endif; ?></div>
</form>
