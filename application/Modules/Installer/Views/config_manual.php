<?php declare(strict_types=1); /** @var \AP\Core\View $this */ $this->extend('installer::layout'); ?>
<?php $this->section('content'); ?>
<h1 class="h4">One manual step</h1>
<div class="alert alert-warning">We could not write <code>application/config/local.php</code>; the folder is not writable. Either make <code>application/config</code> writable (permissions 755/775) and press the button again, or create the file yourself with exactly this content:</div>
<textarea class="form-control ap-code mb-3" rows="16" readonly><?= e($contents) ?></textarea>
<form method="post" action="<?= e(url('install/finish')) ?>"><?= csrf_field() ?><button class="btn btn-primary">I have done this, continue</button></form>
<?php $this->endSection(); ?>
