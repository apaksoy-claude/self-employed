<?php declare(strict_types=1);
/** The getting-started tour, shown on the dashboard the first time someone signs in. @var \AP\Core\View $this @var list<array<string,string>> $steps */
$count = count($steps);
?>
<div class="tu-back" data-tour data-done-url="<?= e(admin_url('money/help/tour')) ?>" data-token="<?= e(csrf_token()) ?>" role="dialog" aria-modal="true" aria-labelledby="tu-title-0">
    <div class="tu-card">
        <?php foreach ($steps as $i => $s): ?>
        <section class="tu-step" data-tour-step="<?= $i ?>"<?= $i === 0 ? '' : ' hidden' ?>>
            <span class="tu-icon"><i class="fa-solid <?= e($s['icon']) ?>" aria-hidden="true"></i></span>
            <p class="tu-count">Step <?= $i + 1 ?> of <?= $count ?></p>
            <h2 id="tu-title-<?= $i ?>"><?= e($s['title']) ?></h2>
            <p><?= e($s['text']) ?></p>
            <?php if ($s['url'] !== ''): ?><a class="tu-open" href="<?= e(admin_url($s['url'])) ?>" data-tour-open>Open <?= e($s['title']) ?> <i class="fa-solid fa-arrow-right ms-1" aria-hidden="true"></i></a><?php endif; ?>
        </section>
        <?php endforeach; ?>
        <div class="tu-dots" aria-hidden="true"><?php for ($i = 0; $i < $count; $i++): ?><span data-tour-dot="<?= $i ?>"<?= $i === 0 ? ' class="is-on"' : '' ?>></span><?php endfor; ?></div>
        <div class="tu-foot">
            <button type="button" class="btn btn-link px-0" data-tour-skip>Skip The Tour</button>
            <div class="tu-nav">
                <button type="button" class="btn btn-outline-secondary mn-btn" data-tour-back disabled><i class="fa-solid fa-arrow-left me-1" aria-hidden="true"></i>Back</button>
                <button type="button" class="btn btn-primary mn-btn" data-tour-next><span data-tour-next-label>Next</span> <i class="fa-solid fa-arrow-right ms-1" aria-hidden="true"></i></button>
            </div>
        </div>
    </div>
</div>
