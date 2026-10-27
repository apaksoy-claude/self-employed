<?php declare(strict_types=1);
/** @var \AP\Core\View $this */
use AP\Modules\Money\Controllers\Forms;
use AP\Modules\Money\Fields;
use AP\Modules\Money\Gmail;

$on = static fn(string $k, string $d = '1'): bool => (string) old($k, $r[$k] ?? $d) === '1';
// A choice list of what exists, plus the rule's own name if it is new (it is made when the rule first uses it).
$pick = static function (array $items, string $cur, string $madeNote): array {
    $o = [];
    foreach ($items as $i) {
        $o[(string) $i] = (string) $i;
    }
    if ($cur !== '' && !isset($o[$cur])) {
        $o[$cur] = $cur . ' (' . $madeNote . ')';
    }
    return $o;
};
$list = static fn(string $id, array $items): string => '<datalist id="' . e($id) . '">' . implode('', array_map(static fn($i) => '<option value="' . e((string) $i) . '">', $items)) . '</datalist>';
?>
<form method="post" action="<?= e(admin_url('money/email-import/rules/save')) ?>" class="mn-form" data-unsaved-warning novalidate><?= csrf_field() ?>
    <h2 class="mn-form-title"><?= $rule ? 'Change This Rule' : 'Add An Email Rule' ?></h2>
    <input type="hidden" name="id" value="<?= e((string) ($rule['id'] ?? '')) ?>">
    <?php if (!empty($seen)): ?><p class="text-muted small mb-3">Made from: <strong><?= e((string) $seen['subject']) ?></strong> from <?= e((string) $seen['sender']) ?>. Boxes you fill in must all fit for an email to be picked, so clear the subject if it changes every time.</p><?php endif; ?>
    <div class="mn-fields">
        <?= Fields::text('name', 'Rule Name', $r['name'], ['maxlength' => 120, 'help' => 'Only for you, to tell the rules apart.']) ?>

        <h3 class="mn-subhead">Which Emails</h3>
        <?= Fields::text('from', 'Sender Contains', $r['from'], ['col' => 'is-half', 'help' => 'Part of the sender address or name. Separate several with commas: any one is enough.']) ?>
        <?= Fields::text('subject', 'Subject Contains', $r['subject'], ['col' => 'is-half', 'help' => 'Leave empty if the subject changes or says nothing useful.']) ?>
        <?= Fields::text('body', 'Email Or PDF Contains', $r['body'], ['col' => 'is-half', 'help' => 'Words inside the email text or its PDF, such as INV or road tax.']) ?>
        <?= Fields::text('exclude', 'Leave Out If It Contains', $r['exclude'], ['col' => 'is-half', 'help' => 'Skip emails with any of these words, such as newsletter or insurance.']) ?>
        <?= Fields::select('attach', 'Attachment', Gmail::ATTACH, $r['attach'], ['help' => 'Pick "Must Have No Attachment" for receipts that are only in the email text.']) ?>

        <h3 class="mn-subhead">Reading The Amount</h3>
        <?= Fields::select('amount_from', 'Read The Amount From', Gmail::AMOUNT_FROM, $r['amount_from'], ['col' => 'is-half']) ?>
        <?= Fields::text('amount_after', 'Amount Comes After The Words', $r['amount_after'], ['col' => 'is-half', 'placeholder' => 'total due, payment amount', 'help' => 'The first £ figure after these words. Empty uses Total, Amount, Fee and similar.']) ?>
        <?= Fields::text('amount_fixed', 'Amount If None Is Found', $r['amount_fixed'], ['col' => 'is-half', 'inputmode' => 'decimal', 'placeholder' => '6.00', 'help' => 'For emails that never show a figure. Empty sends them to Needs A Look.']) ?>
        <?= Fields::text('ref_prefix', 'Reference Starts With', $r['ref_prefix'], ['col' => 'is-half', 'placeholder' => 'INV', 'maxlength' => 20, 'help' => 'Finds a reference such as INV-1926443 to use as {ref} in the title.']) ?>

        <h3 class="mn-subhead">What To Add</h3>
        <?= Fields::text('title', 'Expense Title', $r['title'], ['col' => 'is-half', 'placeholder' => 'Vehicle Rent {ref}', 'help' => 'You can use {subject}, {sender}, {ref}, {date} and {amount}. Empty uses the email subject.']) ?>
        <?= Fields::text('notes', 'Notes', $r['notes'], ['col' => 'is-half', 'placeholder' => 'Added from an email: {subject}', 'help' => 'Same words as the title can be used here.']) ?>
        <?= Fields::select('category', 'Cost Category', $pick($categories, (string) old('category', $r['category']), 'will be made'), $r['category'], ['col' => 'is-half', 'none' => 'No Category', 'none_value' => '']) ?>
        <?= Fields::text('category_new', 'Or Make A New Category', '', ['col' => 'is-half', 'maxlength' => 80, 'help' => 'Type a name here to use a new category. It is made for you.']) ?>
        <?= Fields::select('supplier', 'Supplier', $pick($suppliers, (string) old('supplier', $r['supplier']), 'will be made'), $r['supplier'], ['col' => 'is-half', 'none' => 'No Supplier', 'none_value' => '']) ?>
        <?= Fields::text('supplier_new', 'Or Make A New Supplier', '', ['col' => 'is-half', 'maxlength' => 120, 'help' => 'Type a name here to link a new supplier. It is made for you.']) ?>
        <?= Fields::select('stream', 'Income Stream', $pick($streams, (string) old('stream', $r['stream']), 'not found'), $r['stream'], ['col' => 'is-half', 'none' => 'No Stream', 'none_value' => '']) ?>
        <?= Fields::select('paid_by', 'Paid With', $pick($payWith, (string) old('paid_by', $r['paid_by']), 'not in your list'), $r['paid_by'], ['col' => 'is-half', 'none' => 'As Set In Email Import Settings', 'none_value' => '']) ?>
        <?= Fields::text('business', 'Business Share (%)', $r['business'], ['col' => 'is-half', 'inputmode' => 'numeric', 'help' => '100 means all of it is for the business.']) ?>

        <h3 class="mn-subhead">After Adding</h3>
        <?= Fields::select('bin', 'The Email', Gmail::BIN, $r['bin'], ['help' => 'What happens to the email in Gmail after the expense is made.']) ?>
        <div class="mn-switches">
            <label class="form-switch form-check"><input type="hidden" name="keep_email" value="0"><input class="form-check-input" type="checkbox" role="switch" name="keep_email" value="1"<?= $on('keep_email') ? ' checked' : '' ?>> <span class="form-check-label">Keep a copy of the email on the expense</span></label>
            <label class="form-switch form-check"><input type="hidden" name="keep_pdfs" value="0"><input class="form-check-input" type="checkbox" role="switch" name="keep_pdfs" value="1"<?= $on('keep_pdfs') ? ' checked' : '' ?>> <span class="form-check-label">Keep attached PDFs on the expense</span></label>
            <label class="form-switch form-check"><input type="hidden" name="on" value="0"><input class="form-check-input" type="checkbox" role="switch" name="on" value="1"<?= $on('on') ? ' checked' : '' ?>> <span class="form-check-label">This rule is switched on</span></label>
            <label class="form-switch form-check"><input type="hidden" name="recheck" value="0"><input class="form-check-input" type="checkbox" role="switch" name="recheck" value="1"<?= (string) old('recheck', !empty($seen) || !$rule ? '1' : '0') === '1' ? ' checked' : '' ?>> <span class="form-check-label">After saving, look again at past emails with this rule (as far back as Fetch Now goes)</span></label>
        </div>
    </div>
    <?= Forms::buttons(admin_url('money/email-import'), !empty($modal)) ?>
</form>
