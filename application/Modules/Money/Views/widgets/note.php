<?php declare(strict_types=1);
/** @var \AP\Core\View $this */
use AP\Modules\Money\Charts;
use AP\Modules\Money\Money;

require __DIR__ . '/_prelude.php';
?>
<section class="mn-card mn-note">
    <header><div><h2><i class="fa-solid fa-note-sticky me-2" aria-hidden="true"></i>Private Note</h2><p>Only you see this. For example: "Last Uber income entered: 23/10/2026".</p></div></header>
    <form method="post" action="<?= e(admin_url('money/note')) ?>"><?= csrf_field() ?>
        <textarea class="form-control" name="note" rows="3" maxlength="4000" aria-label="Private note"<?= $canEdit ? '' : ' readonly' ?>><?= e($note) ?></textarea>
        <?php if ($canEdit): ?><button class="btn btn-sm mn-ghost mt-2" type="submit"><i class="fa-solid fa-check me-1"></i>Save Note</button><?php endif; ?>
    </form>
</section>
