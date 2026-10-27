<?php declare(strict_types=1);
/** @var \AP\Core\View $this */
use AP\Core\Form;
use AP\Modules\Money\CustomFields;

$this->extend('admin::layout');
?>
<?php $this->section('content'); ?>
<div class="mn-page">
    <div class="mn-head"><div><p>Add your own fields to any form, for example a job number on expenses or a purchase order on invoices. They appear on the form, are saved with the record, and can be shown as a column in the list.</p></div></div>
    <div class="mn-menupage">
        <nav class="mn-sidenav" aria-label="Which form">
            <?php foreach (CustomFields::ENTITIES as $k => $e): ?>
                <a class="<?= $entity === $k ? 'is-on' : '' ?>" href="<?= e(admin_url('money/fields?for=' . $k)) ?>"><span><?= e($e[0]) ?></span><?php if ($counts[$k] > 0): ?><em><?= (int) $counts[$k] ?></em><?php endif; ?></a>
            <?php endforeach; ?>
        </nav>
        <section class="mn-card">
            <header><div><h2><?= e(CustomFields::ENTITIES[$entity][0]) ?></h2><p>Fields are shown at the bottom of the form, in this order. Drag a row to move it. Removing a row removes what was typed into it.</p></div></header>
            <form method="post" action="<?= e(admin_url('money/fields/save')) ?>" class="mn-form" data-unsaved-warning><?= csrf_field() ?>
                <input type="hidden" name="entity" value="<?= e($entity) ?>">
                <?= Form::repeater(['name' => 'fields', 'add_label' => 'Add A Field', 'max_rows' => 40, 'fields' => [
                    'key' => ['label' => 'Key', 'type' => 'hidden'],
                    'label' => ['label' => 'Name', 'type' => 'text', 'placeholder' => 'Job number'],
                    'type' => ['label' => 'Kind', 'type' => 'select', 'width' => 'md', 'options' => CustomFields::TYPES],
                    'choices' => ['label' => 'Choices (separated by commas)', 'type' => 'text', 'placeholder' => 'Only for a choice list'],
                    'help' => ['label' => 'Hint under the field', 'type' => 'text', 'placeholder' => 'Optional'],
                    'required' => ['label' => 'Needed', 'type' => 'checkbox', 'width' => 'xs'],
                    'in_list' => ['label' => 'In list', 'type' => 'checkbox', 'width' => 'xs'],
                ]], 'cf_rep', $rows) ?>
                <?php if ($canEdit): ?><div class="mn-actions"><button class="btn btn-primary mn-btn" type="submit" data-loading="Saving…"><i class="fa-solid fa-check me-1"></i>Save Fields</button></div><?php endif; ?>
            </form>
        </section>
    </div>
</div>
<?php $this->endSection(); ?>
