<?php declare(strict_types=1);
/** @var \AP\Core\View $this */
$this->extend('admin::layout');
?>
<?php $this->section('actions'); ?>
<?php if ($canEdit): ?><a class="btn btn-outline-secondary mn-btn" href="<?= e(admin_url('money/tax-rules?year=' . $year)) ?>"><i class="fa-solid fa-scale-balanced me-1"></i>Tax Rules</a><?php endif; ?>
<?php $this->endSection(); ?>
<?php $this->section('content'); ?>
<?php $this->include('money::_tax_body', compact('year', 'years', 't', 'est', 'rates', 'personal', 'mtd', 'relief', 'from', 'to', 'canEdit', 'canSave', 'homeFlat', 'homeMonthly', 'homeClaim')); ?>
<?php $this->endSection(); ?>
