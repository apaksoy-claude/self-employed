<?php declare(strict_types=1);
/** @var \AP\Core\View $this */
$this->extend('admin::layout');
\AP\Core\View::inject('admin.body_end', '<script src="' . e(asset('js/access.js')) . '?v=' . e(AP_VERSION) . '"></script>');
$total = 0;
foreach ($areas as $list) {
    foreach ($list as $a) {
        $total += $a['permission'] !== null ? 1 : 0;
    }
}
$colours = ['#2563eb', '#16a34a', '#d97706', '#9333ea', '#0891b2', '#db2777'];
?>
<?php $this->section('content'); ?>
<div class="ap-hub">
    <div class="ap-hub-nav"><?php $this->include('users::roles._nav', ['tab' => $tab]); ?></div>
    <div class="ap-hub-body">
        <form method="post" action="<?= e(admin_url('roles/access')) ?>" data-access data-unsaved-warning>
            <?= csrf_field() ?>
            <div class="ap-role-cards">
                <div class="ap-role-card" style="--c:#0d1b3e"><span class="ap-role-mark">S</span><div><strong>Super admin</strong><span><?= (int) $supers ?> <?= $supers === 1 ? 'person' : 'people' ?> · every area</span></div></div>
                <?php foreach ($roles as $i => $r): $n = 0; foreach ($areas as $list) { foreach ($list as $a) { if ($a['permission'] !== null && isset($granted[(int) $r['id']][$a['permission']])) { $n++; } } } ?>
                    <div class="ap-role-card" style="--c:<?= e($colours[$i % count($colours)]) ?>"><span class="ap-role-mark"><?= e(mb_strtoupper(mb_substr((string) $r['name'], 0, 1))) ?></span>
                        <div><strong><?= e((string) $r['name']) ?></strong><span><?= (int) ($people[(int) $r['id']] ?? 0) ?> <?= ($people[(int) $r['id']] ?? 0) === 1 ? 'person' : 'people' ?> · <span data-role-count="<?= (int) $r['id'] ?>"><?= $n ?></span>/<?= $total ?> areas</span></div>
                        <a class="ms-auto" href="<?= e(admin_url('roles/' . (int) $r['id'] . '/edit')) ?>" title="Finer permissions" aria-label="Finer permissions for <?= e((string) $r['name']) ?>"><i class="fa-solid fa-sliders"></i></a></div>
                <?php endforeach; ?>
            </div>
            <div class="ap-tabs-search ap-access-find"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i><input type="search" class="form-control form-control-sm" placeholder="Find an area, for example pages, email or updates" aria-label="Find an area" data-access-find data-shortcut-search></div>
            <div class="ap-section ap-access">
                <table class="table mb-0 align-middle">
                    <?php foreach ($areas as $group => $list): $gid = 'g' . substr(md5($group), 0, 6); ?>
                        <tbody data-access-group="<?= e($gid) ?>">
                            <tr class="ap-access-head"><th scope="colgroup"><i class="fa-solid fa-folder" aria-hidden="true"></i><?= e((string) $group) ?> <span class="text-muted fw-normal">(<?= count($list) ?>)</span></th>
                                <th class="text-center">Super admin</th>
                                <?php foreach ($roles as $r): ?><th class="text-center"><?= e((string) $r['name']) ?>
                                    <?php if ($canEdit): ?><button type="button" class="ap-all-btn" data-all="<?= e($gid) ?>" data-role="<?= (int) $r['id'] ?>">All</button><?php endif; ?></th><?php endforeach; ?></tr>
                            <?php foreach ($list as $a): ?>
                                <tr data-access-row data-text="<?= e(mb_strtolower($a['label'] . ' ' . $group . ' ' . $a['url'] . ' ' . (string) $a['permission'])) ?>">
                                    <td><i class="fa-solid <?= e($a['icon']) ?> fa-fw text-muted me-2" aria-hidden="true"></i><?= e($a['label']) ?>
                                        <?php if ($a['admin_only']): ?><span class="ap-lock-pill"><i class="fa-solid fa-lock"></i> super admin only</span><?php endif; ?>
                                        <?php if ($a['permission'] === null): ?><span class="ap-lock-pill">everyone</span><?php endif; ?></td>
                                    <td class="text-center"><i class="fa-solid fa-circle-check text-success" aria-label="Yes"></i></td>
                                    <?php foreach ($roles as $r): $on = $a['permission'] === null || isset($granted[(int) $r['id']][$a['permission']]); ?>
                                        <td class="text-center">
                                            <?php if ($a['permission'] === null): ?><i class="fa-solid fa-circle-check text-success opacity-50" aria-label="Everyone"></i>
                                            <?php elseif ($a['admin_only']): ?><i class="fa-solid fa-lock text-muted" aria-label="<?= $on ? 'Yes' : 'No' ?> (super admin only)" title="Only super admins can change this, on the role page"></i>
                                            <?php else: ?>
                                                <input type="hidden" name="grant[<?= (int) $r['id'] ?>][<?= e($a['permission']) ?>]" value="0">
                                                <div class="form-check form-switch d-inline-block m-0"><input class="form-check-input" type="checkbox" role="switch" name="grant[<?= (int) $r['id'] ?>][<?= e($a['permission']) ?>]" value="1"<?= $on ? ' checked' : '' ?><?= $canEdit ? '' : ' disabled' ?>
                                                    data-role="<?= (int) $r['id'] ?>" data-perm="<?= e($a['permission']) ?>" aria-label="<?= e($r['name'] . ' can open ' . $a['label']) ?>"></div>
                                            <?php endif; ?>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    <?php endforeach; ?>
                </table>
            </div>
            <p class="ap-access-none text-muted small" data-access-none hidden>No area matches.</p>
            <div class="ap-savebar mt-3" data-savebar>
                <span class="ap-savebar-note"><i class="fa-solid fa-circle-info me-1"></i>A switched-on area shows in that role's menu and its pages open. Areas that share a switch change together. Finer permissions, such as editing or deleting, are on each role's page.</span>
                <?php if ($canEdit): ?><button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk me-1"></i>Save</button><?php endif; ?>
            </div>
        </form>
    </div>
</div>
<?php $this->endSection(); ?>
