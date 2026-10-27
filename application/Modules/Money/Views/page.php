<?php declare(strict_types=1);
/** @var \AP\Core\View $this @var string $html */
$this->extend('admin::layout');
?>
<?php $this->section('content'); ?>
<div class="mn-page"><section class="mn-card mn-formcard<?= !empty($wide) ? ' is-wide' : '' ?>"><?= $html /* built by Money views, already escaped */ ?></section></div>
<?php $this->endSection(); ?>
