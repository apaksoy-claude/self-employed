<?php declare(strict_types=1);
/** Money > Stock > Stock Count. @var \AP\Core\View $this */
$this->extend('admin::layout');
?>
<?php $this->section('content'); ?>
<?php $this->include('money::_stock_count_body', ['date' => $date, 'items' => $items, 'value' => $value, 'canEdit' => $canEdit, 'basis' => $basis, 'yearEnd' => $yearEnd]); ?>
<?php $this->endSection(); ?>
