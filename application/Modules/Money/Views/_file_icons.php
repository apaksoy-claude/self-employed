<?php declare(strict_types=1);
use AP\Modules\Money\Money;
/** Small icons for the files of a record, each showing its kind and opening the file. Needs $list (money_files rows). */
$max = 3;
foreach (array_slice($list, 0, $max) as $f):
    [$icon, $kind, $cls, $preview] = Money::fileKind((string) $f['original_name'], $f['mime'] ?? null);
    $url = admin_url('money/files/' . (int) $f['id']);
    ?><a class="mn-file <?= e($cls) ?>" href="<?= e($url) ?>" target="_blank" rel="noopener"<?= $preview ? ' data-file-preview="' . e($preview) . '"' : '' ?> data-name="<?= e((string) $f['original_name']) ?>" title="<?= e($kind . ': ' . $f['original_name']) ?>"><i class="fa-solid <?= e($icon) ?>" aria-hidden="true"></i><span class="visually-hidden"><?= e($kind . ': ' . $f['original_name']) ?></span></a><?php
endforeach;
if (count($list) > $max): ?><span class="small text-muted" title="<?= e(implode(', ', array_map(static fn($f) => (string) $f['original_name'], array_slice($list, $max)))) ?>">+<?= count($list) - $max ?></span><?php endif;
if (!$list): ?><span class="text-muted small">—</span><?php endif;
