<?php declare(strict_types=1);
/** The installer's Your Business step. @var \AP\Core\View $this */
use AP\Core\Form;

$this->extend('installer::layout');
$biz = (array) ($choice['business'] ?? []);
?>
<?php $this->section('head'); ?><link rel="stylesheet" href="<?= e(asset('css/money-setup.css')) ?>?v=<?= e(AP_VERSION) ?>"><?php $this->endSection(); ?>
<?php $this->section('wide'); ?>1<?php $this->endSection(); ?>
<?php $this->section('content'); ?>
<h1 class="h4"><?= e($stepLabel ?? 'Your Business') ?></h1>
<p class="text-muted">Money can start empty, or ready for your kind of work. Everything can be changed later in the admin.</p>
<form method="post" action="<?= e($action) ?>" novalidate data-su-form><?= csrf_field() ?>
    <?php $this->include('money::setup/_choice', ['choice' => $choice, 'planJson' => $planJson, 'context' => 'install', 'vehicleTouched' => $vehicleTouched ?? false]); ?>
    <section class="su-block">
        <div class="su-head"><h2>About Your Business</h2><p>Printed on your invoices and emails. You can change it later in Settings > Money.</p></div>
        <div class="row">
            <?= Form::field(['name' => 'biz_name', 'label' => 'Business Name', 'value' => (string) ($biz['name'] ?? ''), 'col' => 'col-md-6', 'help' => 'Leave empty to use your site name.']) ?>
            <?= Form::field(['name' => 'biz_email', 'label' => 'Business Email', 'type' => 'email', 'value' => (string) ($biz['email'] ?? ''), 'col' => 'col-md-6', 'help' => 'Shown on invoices. Clients reply to it.']) ?>
        </div>
    </section>
    <div class="su-actions"><a class="btn btn-link" href="<?= e($backUrl) ?>">Back</a><button class="btn btn-primary su-go" type="submit">Continue<i class="fa-solid fa-arrow-right ms-2" aria-hidden="true"></i></button></div>
</form>
<?php $this->endSection(); ?>
<?php $this->section('scripts'); ?><script src="<?= e(asset('js/money-setup.js')) ?>?v=<?= e(AP_VERSION) ?>"></script><?php $this->endSection(); ?>
