<?php declare(strict_types=1);
/** @var \AP\Core\View $this */
use AP\Modules\Money\Fields;
?>
<form method="post" action="<?= e(admin_url('money/reports/email')) ?>" class="mn-form" novalidate><?= csrf_field() ?>
    <input type="hidden" name="year" value="<?= e($year) ?>">
    <h2 class="mn-form-title">Email To My Accountant <small><?= e($year) ?></small></h2>
    <div class="mn-fields">
        <?= Fields::text('to', 'Send to', $to, ['type' => 'email', 'required' => true, 'maxlength' => 190, 'placeholder' => 'accountant@example.com', 'help' => $to === '' ? 'Save your accountant\'s email in Settings > Money > Reports to fill this in each time.' : '']) ?>
        <?= Fields::area('message', 'Your message', $message, ['rows' => 5, 'help' => 'The zip is attached: summary, spreadsheets, invoice copies and receipts.']) ?>
    </div>
    <label class="form-check form-switch mn-check"><input type="hidden" name="copy" value="0"><input class="form-check-input" type="checkbox" role="switch" name="copy" value="1"<?= $copy ? ' checked' : '' ?>> <span class="form-check-label">Send me a copy</span></label>
    <div class="mn-actions"><button class="btn btn-primary mn-btn" type="submit" data-loading="Building and sending…"><i class="fa-solid fa-paper-plane me-1"></i>Send Pack</button>
        <?php if (!empty($modal)): ?><button class="btn btn-outline-secondary mn-btn" type="button" data-modal-close>Cancel</button><?php else: ?><a class="btn btn-outline-secondary mn-btn" href="<?= e(admin_url('money/reports?year=' . $year)) ?>">Cancel</a><?php endif; ?></div>
</form>
