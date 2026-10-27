<?php declare(strict_types=1);
/** @var \AP\Core\View $this */
use AP\Modules\Money\Controllers\ContactController;
use AP\Modules\Money\Controllers\Forms;
use AP\Modules\Money\Fields;

$c ??= null;
?>
<form method="post" action="<?= e(admin_url('money/contacts/save')) ?>" class="mn-form" data-unsaved-warning novalidate><?= csrf_field() ?>
    <h2 class="mn-form-title"><?= $c ? 'Change Contact' : 'New Contact' ?></h2>
    <input type="hidden" name="id" value="<?= (int) ($c['id'] ?? 0) ?>">
    <div class="mn-fields">
        <?= Fields::text('name', 'Name', $c['name'] ?? '', ['required' => true, 'maxlength' => 190, 'col' => 'is-half']) ?>
        <?= Fields::select('kind', 'Kind', ContactController::KINDS, $c['kind'] ?? 'client', ['col' => 'is-half']) ?>
        <?= Fields::text('email', 'Email', $c['email'] ?? '', ['maxlength' => 190, 'col' => 'is-half']) ?>
        <?= Fields::text('phone', 'Phone', $c['phone'] ?? '', ['maxlength' => 40, 'col' => 'is-half']) ?>
        <?= Fields::text('address', 'Address', $c['address'] ?? '', ['maxlength' => 255]) ?>
        <?= Fields::text('city', 'Town Or City', $c['city'] ?? '', ['maxlength' => 100, 'col' => 'is-third']) ?>
        <?= Fields::text('postcode', 'Postcode', $c['postcode'] ?? '', ['maxlength' => 20, 'col' => 'is-third']) ?>
        <?= Fields::text('country', 'Country', $c['country'] ?? '', ['maxlength' => 100, 'col' => 'is-third']) ?>
        <?= Fields::text('website', 'Website', $c['website'] ?? '', ['maxlength' => 190]) ?>
        <?php if (\AP\Modules\Money\Cis::on() || (string) ($c['cis_rate'] ?? '') !== ''): ?>
            <?= Fields::select('cis_rate', 'Construction Industry Scheme', \AP\Modules\Money\Cis::RATES, $c['cis_rate'] ?? '', ['none' => 'Not Under CIS', 'none_value' => '', 'col' => 'is-half',
                'help' => 'A client who is a contractor: the rate they take off you, used for new invoices. A subcontractor you pay: the rate you take off them.']) ?>
            <?= Fields::text('cis_ref', 'UTR Or Verification Number', $c['cis_ref'] ?? '', ['maxlength' => 30, 'col' => 'is-half', 'help' => 'For a subcontractor: their UTR, and the verification number HMRC gave you if they are taxed at 30%.']) ?>
        <?php endif; ?>
        <?= Fields::area('note', 'Notes', $c['note'] ?? '') ?>
        <?= \AP\Modules\Money\CustomFields::form('contact', (int) ($c['id'] ?? 0)) ?>
    </div>
    <?php if ($c): ?><div class="mn-repeat"><label class="form-switch form-check"><input type="checkbox" role="switch" class="form-check-input" name="active" value="1"<?= !empty($c['active']) ? ' checked' : '' ?>> <span class="form-check-label">Offer this contact when adding new records (switch off to archive it)</span></label></div><?php endif; ?>
    <?= Forms::buttons(admin_url('money/contacts'), !empty($modal), $c ? admin_url('money/contacts/' . (int) $c['id'] . '/delete') : '', 'Delete this contact? If records use it, it is archived instead.') ?>
</form>
