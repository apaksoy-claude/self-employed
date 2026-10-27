<?php declare(strict_types=1); /** @var \AP\Core\View $this */ use AP\Core\Form; $this->extend('installer::layout'); ?>
<?php $this->section('content'); ?>
<h1 class="h4">Database</h1>
<p class="text-muted">Create an empty MySQL or MariaDB database first (in cPanel: "MySQL Databases"), then enter its details. We test the connection before going on.</p>
<form method="post" action="<?= e(url('install/database')) ?>" novalidate><?= csrf_field() ?>
    <div class="row">
        <?= Form::field(['name' => 'host', 'label' => 'Database host', 'value' => $db['host'], 'required' => true, 'help' => 'Usually "localhost" on shared hosting.', 'col' => 'col-md-8']) ?>
        <?= Form::field(['name' => 'port', 'label' => 'Port', 'type' => 'number', 'value' => $db['port'], 'required' => true, 'col' => 'col-md-4']) ?>
    </div>
    <?= Form::field(['name' => 'database', 'label' => 'Database name', 'value' => $db['database'], 'required' => true, 'help' => 'On cPanel this often starts with your account name, such as "myacct_site".']) ?>
    <div class="row">
        <?= Form::field(['name' => 'username', 'label' => 'Database username', 'value' => $db['username'], 'required' => true, 'col' => 'col-md-6']) ?>
        <?= Form::field(['name' => 'password', 'label' => 'Database password', 'type' => 'password', 'col' => 'col-md-6', 'attrs' => ['autocomplete' => 'off']]) ?>
    </div>
    <?= Form::field(['name' => 'prefix', 'label' => 'Table prefix', 'value' => $db['prefix'], 'help' => 'Added to the start of every table name. Change it if several sites share one database.']) ?>
    <a class="btn btn-link" href="<?= e(url('install')) ?>">Back</a><button class="btn btn-primary" type="submit"><i class="fa-solid fa-plug me-1"></i>Test connection and continue</button>
</form>
<?php $this->endSection(); ?>
