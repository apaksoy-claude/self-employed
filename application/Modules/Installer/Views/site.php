<?php declare(strict_types=1); /** @var \AP\Core\View $this */ use AP\Core\Form; $this->extend('installer::layout'); ?>
<?php $this->section('content'); ?>
<h1 class="h4">About your site</h1>
<form method="post" action="<?= e(url('install/site')) ?>" novalidate><?= csrf_field() ?>
    <?= Form::field(['name' => 'name', 'label' => 'Site name', 'value' => $site['name'], 'required' => true, 'help' => 'Shown in the browser tab, emails and the admin panel.']) ?>
    <?= Form::field(['name' => 'url', 'label' => 'Site address', 'type' => 'url', 'value' => $site['url'], 'required' => true, 'help' => 'The full address of the site, without a slash at the end. We filled in what we detected.']) ?>
    <div class="row">
        <?= Form::field(['name' => 'timezone', 'label' => 'Timezone', 'type' => 'timezone', 'value' => $site['timezone'], 'col' => 'col-md-6', 'help' => 'Dates are shown in this timezone.']) ?>
        <?= Form::field(['name' => 'date_format', 'label' => 'Date format', 'type' => 'select', 'value' => $site['date_format'], 'col' => 'col-md-6', 'options' => ['d M Y' => '16 Oct 2026', 'd/m/Y' => '16/10/2026', 'm/d/Y' => '10/16/2026', 'Y-m-d' => '2026-10-16', 'j F Y' => '16 October 2026']]) ?>
    </div>
    <a class="btn btn-link" href="<?= e(url('install/database')) ?>">Back</a><button class="btn btn-primary" type="submit">Continue</button>
</form>
<?php $this->endSection(); ?>
