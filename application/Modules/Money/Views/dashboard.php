<?php declare(strict_types=1);
/** @var \AP\Core\View $this */
use AP\Modules\Money\Money;
use AP\Modules\Money\Widgets;

$layout = Widgets::layout();
?>
<div class="mn-page">
    <div class="mn-head">
        <div><h1>Today: <?= e((new DateTimeImmutable($today))->format('l, j F Y')) ?></h1><p>Your own records. Nothing here is sent to HMRC.</p></div>
        <div class="mn-head-tools">
        <?php if ($canEdit): ?>
            <button class="btn btn-outline-secondary mn-btn" type="button" data-board-edit aria-pressed="false"><i class="fa-solid fa-table-cells-large me-1"></i><span>Customise</span></button>
            <a class="btn btn-primary mn-btn" href="<?= e(admin_url('money/invoices/new')) ?>"<?= Money::modalAttr() ?>><i class="fa-solid fa-plus me-1"></i>New Invoice</a>
            <a class="btn btn-outline-secondary mn-btn" href="<?= e(admin_url('money/expenses/new')) ?>"<?= Money::modalAttr() ?>><i class="fa-solid fa-plus me-1"></i>New Expense</a>
        <?php endif; ?>
        <form method="get" action="<?= e(admin_url()) ?>" class="mn-yearpick"><label for="year">Tax year</label>
            <select class="form-select" id="year" name="year" data-autosubmit><?php foreach ($years as $y): ?><option value="<?= e($y) ?>"<?= $y === $year ? ' selected' : '' ?>><?= e($y) ?></option><?php endforeach; ?></select>
            <noscript><button class="btn btn-outline-secondary btn-sm" type="submit">Show</button></noscript></form>
        </div>
    </div>


    <?php if (!empty($tour)): ?><?php $this->include('money::_tour', ['steps' => $tour]); ?><?php endif; ?>

    <?php if (!empty($demoBanner)): ?>
    <div class="su-banner dm-banner">
        <span class="su-banner-icon"><i class="fa-solid fa-flask" aria-hidden="true"></i></span>
        <div class="su-banner-text"><strong>You Are Looking At Demo Data</strong><p>The clients, invoices, payments and expenses marked "(Demo)" are made up. Delete them all with one button before you start for real; your own records stay.</p></div>
        <div class="su-banner-actions">
            <form method="post" action="<?= e(admin_url('money/demo/clear')) ?>"><?= csrf_field() ?><input type="hidden" name="return" value="<?= e(admin_url()) ?>"><button class="btn btn-danger mn-btn" type="submit" data-confirm="Delete all demo data? Your own records stay as they are."><i class="fa-solid fa-trash me-1" aria-hidden="true"></i>Delete All Demo Data</button></form>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($setupBanner)): ?>
    <div class="su-banner" data-su-banner>
        <span class="su-banner-icon"><i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i></span>
        <div class="su-banner-text"><strong>Set Money Up For Your Kind Of Work</strong><p>Add ready-made categories, income streams and dashboard cards for drivers, trades, freelancers, sellers and more. Nothing you already have is changed.</p></div>
        <div class="su-banner-actions">
            <a class="btn btn-primary mn-btn" href="<?= e(admin_url('money/setup')) ?>"><i class="fa-solid fa-arrow-right me-1" aria-hidden="true"></i>Start Setup</a>
            <button class="btn btn-outline-secondary mn-btn" type="button" data-su-dismiss="<?= e(admin_url('money/setup/dismiss')) ?>" data-token="<?= e(csrf_token()) ?>">Not Now</button>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($canEdit): ?>
    <div class="mn-editbar" id="board-bar" hidden>
        <p><i class="fa-solid fa-hand" aria-hidden="true"></i> Drag a card to move it, or use the arrows. Pick a width on each card. Changes save by themselves.</p>
        <div>
            <a class="btn btn-primary mn-btn" href="<?= e(admin_url('money/dashboard/widgets/new')) ?>" data-money-modal><i class="fa-solid fa-plus me-1"></i>Add A Card</a>
            <form method="post" action="<?= e(admin_url('money/dashboard/reset')) ?>" class="d-inline" data-confirm="Put the dashboard back to the ready-made layout? Cards you built yourself will be removed."><?= csrf_field() ?>
                <button class="btn btn-outline-secondary mn-btn" type="submit"><i class="fa-solid fa-rotate-left me-1"></i>Reset Layout</button></form>
            <button class="btn btn-outline-secondary mn-btn" type="button" data-board-edit><i class="fa-solid fa-check me-1"></i>Done</button>
        </div>
    </div>
    <?php endif; ?>

    <div class="mn-board" id="board" data-save-url="<?= e(admin_url('money/dashboard/layout')) ?>" data-token="<?= e(csrf_token()) ?>">
        <?php foreach ($layout as $w): ?>
            <div class="mn-w mn-w-<?= (int) $w['w'] ?>" data-id="<?= e($w['id']) ?>" data-w="<?= (int) $w['w'] ?>">
                <?php if ($canEdit): ?>
                <div class="mn-w-bar">
                    <span class="mn-w-grip" title="Drag to move"><i class="fa-solid fa-grip-vertical" aria-hidden="true"></i></span>
                    <span class="mn-w-name"><?= e(Widgets::title($w)) ?></span>
                    <span class="mn-w-sizes" role="group" aria-label="Width">
                        <?php foreach (Widgets::WIDTHS as $cols => $name): ?><button type="button" class="<?= $cols === (int) $w['w'] ? 'is-on' : '' ?>" data-size="<?= (int) $cols ?>" title="<?= e($name) ?>"><?= ['3' => '¼', '4' => '⅓', '6' => '½', '8' => '⅔', '12' => 'Full'][$cols] ?></button><?php endforeach; ?>
                    </span>
                    <button type="button" class="mn-icon" data-move="-1" aria-label="Move earlier"><i class="fa-solid fa-arrow-up" aria-hidden="true"></i></button>
                    <button type="button" class="mn-icon" data-move="1" aria-label="Move later"><i class="fa-solid fa-arrow-down" aria-hidden="true"></i></button>
                    <?php if (!isset(Widgets::builtin()[$w['type']])): ?><a class="mn-icon" href="<?= e(admin_url('money/dashboard/widgets/' . $w['id'] . '/edit')) ?>" data-money-modal aria-label="Edit this card"><i class="fa-solid fa-pen" aria-hidden="true"></i></a><?php endif; ?>
                    <?php if (in_array($w['type'], Widgets::STYLED, true)): ?><a class="mn-icon" href="<?= e(admin_url('money/dashboard/cards/' . $w['id'] . '/style')) ?>" data-money-modal aria-label="Choose the style of these cards" title="Card style"><i class="fa-solid fa-paintbrush" aria-hidden="true"></i></a><?php endif; ?>
                    <button type="button" class="mn-icon" data-remove aria-label="Remove this card"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
                </div>
                <?php endif; ?>
                <div class="mn-w-body"><?= Widgets::render($w, get_defined_vars()) ?></div>
            </div>
        <?php endforeach; ?>
        <?php if (!$layout): ?><p class="mn-empty mn-w-12">The dashboard is empty. Choose Customise, then Add A Card.</p><?php endif; ?>
    </div>
</div>
