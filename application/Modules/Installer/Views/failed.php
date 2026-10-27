<?php declare(strict_types=1); /** @var \AP\Core\View $this */ $this->extend('installer::layout'); ?>
<?php $this->section('content'); ?>
<h1 class="h4 text-danger">The installation stopped</h1>
<p>Something went wrong while creating the tables. Nothing is locked, so you can fix the problem and try again; the steps are safe to repeat.</p>
<pre class="small bg-body-tertiary p-3 rounded"><?= e($message) ?></pre>
<form method="post" action="<?= e(url('install/finish')) ?>"><?= csrf_field() ?><button class="btn btn-primary">Try again</button> <a class="btn btn-link" href="<?= e(url('install/database')) ?>">Change database details</a></form>
<?php $this->endSection(); ?>
