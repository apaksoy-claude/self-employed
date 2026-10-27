<?php declare(strict_types=1);
/** Money > Help Centre. @var \AP\Core\View $this */
use AP\Modules\Money\Help;

$this->extend('admin::layout');
?>
<?php $this->section('content'); ?>
<div class="mn-page hp-page">
    <div class="mn-menupage">
        <nav class="mn-sidenav hp-nav" aria-label="Help topics">
            <form method="get" action="<?= e(admin_url('money/help')) ?>" class="hp-search" role="search"><input type="search" class="form-control form-control-sm" name="q" value="<?= e($q) ?>" placeholder="Search the help" aria-label="Search the help" data-help-filter></form>
            <?php foreach ($groups as $g => $list): if (!$list) { continue; } ?>
                <div class="hp-group" data-help-group><span class="hp-group-name"><?= e(Help::GROUPS[$g] ?? $g) ?></span>
                <?php foreach ($list as $k => $item): ?>
                    <a class="<?= $k === $key ? 'is-on' : '' ?>" href="<?= e(admin_url('money/help/' . $k)) ?>" data-help-item="<?= e(mb_strtolower($item['title'] . ' ' . $item['keywords'])) ?>"<?= $k === $key ? ' aria-current="page"' : '' ?>><span><i class="fa-solid <?= e($item['icon']) ?> me-2" aria-hidden="true"></i><?= e($item['title']) ?></span></a>
                <?php endforeach; ?></div>
            <?php endforeach; ?>
        </nav>
        <div class="hp-main">
            <?php if ($q !== ''): ?>
            <section class="mn-card">
                <header><div><h2><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>Results For "<?= e($q) ?>"</h2></div></header>
                <?php if (!$found): ?><p class="text-muted mb-0">Nothing matches. Try one word, such as "mileage", "VAT" or "receipt".</p><?php else: ?>
                <ul class="hp-results"><?php foreach ($found as $f): ?><li><a href="<?= e(admin_url('money/help/' . $f['key'])) ?>"><i class="fa-solid <?= e($f['icon']) ?>" aria-hidden="true"></i><span><strong><?= e($f['title']) ?></strong><small><?= e($f['intro']) ?></small></span></a></li><?php endforeach; ?></ul>
                <?php endif; ?>
            </section>
            <?php endif; ?>
            <article class="mn-card hp-article">
                <header><div><h1><i class="fa-solid <?= e($a['icon']) ?>" aria-hidden="true"></i><?= e($a['title']) ?></h1><p><?= e($a['intro']) ?></p></div>
                    <?php if ($a['url'] !== ''): ?><a class="btn btn-primary mn-btn" href="<?= e(admin_url($a['url'])) ?>"><i class="fa-solid fa-arrow-right me-1" aria-hidden="true"></i>Open This Page</a><?php endif; ?></header>
                <div class="hp-body"><?= $a['body'] ?></div>
            </article>
            <section class="mn-card hp-more">
                <div><strong>New here?</strong> The getting-started tour explains each part of Money in a minute.</div>
                <form method="post" action="<?= e(admin_url('money/help/tour')) ?>"><?= csrf_field() ?><input type="hidden" name="done" value="0"><button class="btn btn-outline-primary mn-btn" type="submit"><i class="fa-solid fa-route me-1" aria-hidden="true"></i>Show The Tour Again</button></form>
            </section>
        </div>
    </div>
</div>
<?php $this->endSection(); ?>
