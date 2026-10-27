<?php declare(strict_types=1);
use AP\Modules\Money\Money;
/** Edit (pencil) and delete (bin) at the end of a list row. Needs $edit, $delete, $ask and $canEdit. */
if (!$canEdit) { return; }
?>
<td class="text-end text-nowrap mn-rowact">
    <a class="mn-icon" href="<?= e($edit) ?>"<?= Money::modalAttr() ?> aria-label="Change" title="Change"><i class="fa-solid fa-pen"></i></a>
    <form method="post" action="<?= e($delete) ?>" class="d-inline"><?= csrf_field() ?><input type="hidden" name="return" value="<?= e((string) ($_SERVER['REQUEST_URI'] ?? '')) ?>"><button class="mn-icon" type="submit" data-confirm="<?= e($ask) ?>" aria-label="Delete" title="Delete"><i class="fa-solid fa-trash"></i></button></form>
</td>
