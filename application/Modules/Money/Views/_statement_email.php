<?php declare(strict_types=1);
/** @var \AP\Core\View $this */
use AP\Modules\Money\Fields;
use AP\Modules\Money\Money;
?>
<form method="post" action="<?= e(admin_url('money/contacts/' . (int) $contact['id'] . '/statement/email')) ?>" class="mn-form" novalidate><?= csrf_field() ?>
    <h2 class="mn-form-title">Email Statement <small><?= e((string) $contact['name']) ?></small></h2>
    <div class="mn-fields">
        <?= Fields::text('to', 'Send to', $to, ['type' => 'email', 'required' => true, 'maxlength' => 190, 'placeholder' => 'client@example.com',
            'help' => !empty($contact['statement_sent_at']) ? 'Last emailed on ' . date('d/m/Y H:i', (int) strtotime((string) $contact['statement_sent_at'] . ' UTC')) . '.' : 'Not emailed before.']) ?>
        <?= Fields::text('subject', 'Subject', $subject, ['required' => true, 'maxlength' => 150]) ?>
        <?= Fields::area('message', 'Your message', $message, ['rows' => 5, 'help' => 'Shown above the amount owed. Leave a blank line between paragraphs.']) ?>
    </div>
    <label class="form-check form-switch mn-check"><input type="hidden" name="copy" value="0"><input class="form-check-input" type="checkbox" role="switch" name="copy" value="1"<?= $copy ? ' checked' : '' ?>> <span class="form-check-label">Send me a copy</span></label>
    <div class="mn-actions"><button class="btn btn-primary mn-btn" type="submit" data-loading="Sending…"><i class="fa-solid fa-paper-plane me-1"></i>Send Statement</button>
        <?php if (!empty($modal)): ?><button class="btn btn-outline-secondary mn-btn" type="button" data-modal-close>Cancel</button><?php else: ?><a class="btn btn-outline-secondary mn-btn" href="<?= e(admin_url('money/contacts')) ?>">Cancel</a><?php endif; ?></div>
</form>
