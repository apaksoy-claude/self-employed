<?php declare(strict_types=1);
/** @var \AP\Core\View $this */
use AP\Modules\Admin\Ui;

$this->extend('admin::layout');
\AP\Core\View::inject('admin.body_end', '<script src="' . e(asset('js/updates.js')) . '"></script>');
$badge = static fn(string $s): string => match ($s) {
    'installed' => '<span class="badge ap-pill ap-pill-ok">Success</span>',
    'failed' => '<span class="badge ap-pill ap-pill-bad">Failed</span>',
    'failed_after_files' => '<span class="badge ap-pill ap-pill-warn">Needs attention</span>',
    'files_done', 'rolling_back' => '<span class="badge ap-pill ap-pill-warn">Unfinished</span>',
    'rolled_back' => '<span class="badge ap-pill ap-pill-grey">Went back</span>',
    default => '<span class="badge ap-pill ap-pill-grey">' . e($s) . '</span>',
};
?>
<?php $this->section('content'); ?>

<div class="ap-up" data-updates
     data-upload-url="<?= e(admin_url('system/updates/upload')) ?>" data-install-url="<?= e(admin_url('system/updates/install')) ?>"
     data-finish-url="<?= e(admin_url('system/updates/finish')) ?>" data-rollback-url="<?= e(admin_url('system/updates/rollback')) ?>">
<?php if (setting('updates.confirm_password', false)): ?>
    <div class="ap-up-password mb-3">
        <label class="form-label small mb-1" for="upPassword"><i class="fa-solid fa-lock me-1"></i>Your password</label>
        <input type="password" class="form-control form-control-sm" id="upPassword" autocomplete="current-password" data-up-password placeholder="Needed to install an update or go back">
        <div class="form-text">Asked because of Settings &gt; Security &gt; Updates.</div>
    </div>
<?php endif; ?>
<?php if ($pending): ?>
    <div class="alert alert-warning d-flex flex-wrap align-items-center justify-content-between gap-2" data-up-pending>
        <span><i class="fa-solid fa-triangle-exclamation me-2"></i>An update to <strong><?= e((string) $pending['to']) ?></strong> copied its files but the database step has not run yet. Finish it now.</span>
        <button type="button" class="btn btn-sm btn-dark" data-up-finish><i class="fa-solid fa-flag-checkered me-1"></i>Finish update</button>
    </div>
<?php endif; ?>
<div class="ap-settings">
    <div class="ap-sections">
        <section class="ap-section">
            <div class="ap-section-head"><h2><i class="fa-solid fa-cloud-arrow-up" aria-hidden="true"></i>Install an Update</h2></div>
            <div class="ap-section-body pb-3">
                <p class="small text-muted">Upload the update package (a zip file) of a new version. It is checked first, then you confirm. A database backup and a copy of every file that is replaced are made before anything changes. Your settings, users, uploads and backups are never overwritten.</p>
                <?php if (!$zip): ?>
                    <div class="alert alert-danger small mb-0">This server has no zip support (the PHP "zip" extension), so updates cannot be installed here. Ask your host to switch it on.</div>
                <?php else: ?>
                <div data-up-form>
                    <label class="ap-drop" data-dropzone>
                        <input type="file" name="package" accept=".zip,application/zip" class="visually-hidden" data-up-file>
                        <i class="fa-solid fa-file-zipper ap-drop-icon" aria-hidden="true"></i>
                        <span class="ap-drop-text"><strong>Drag and drop the update zip here</strong><br><span class="text-muted">or click to choose a file</span></span>
                        <span class="ap-drop-name" data-drop-name></span>
                    </label>
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mt-2">
                        <span class="small text-muted">Up to <?= e($limit) ?> (your server's upload limit).</span>
                        <button type="button" class="btn btn-primary" data-up-check disabled><i class="fa-solid fa-magnifying-glass me-1"></i>Upload and Check</button>
                    </div>
                </div>
                <div class="mt-3" data-up-result aria-live="polite"></div>
                <?php endif; ?>
            </div>
        </section>

        <section class="ap-section">
            <div class="ap-section-head"><h2><i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i>Update History</h2></div>
            <div class="ap-section-body p-0">
                <?php if (!$history): ?>
                    <div class="p-3"><?= Ui::emptyState('No updates yet', 'Updates you install here are listed with what happened.', null, null, 'fa-clock-rotate-left') ?></div>
                <?php else: ?>
                <div class="table-responsive"><table class="table ap-up-table mb-0 align-middle">
                    <thead><tr><th>Date</th><th>Version</th><th>Result</th><th class="text-end"></th></tr></thead>
                    <tbody>
                    <?php foreach ($history as $i => $h): ?>
                        <tr>
                            <td class="text-nowrap"><?= e(format_date((string) gmdate('Y-m-d H:i:s', (int) strtotime((string) $h['time'])), 'd M Y, H:i')) ?></td>
                            <td class="text-nowrap"><span class="text-muted"><?= e((string) $h['from']) ?></span> → <strong><?= e((string) $h['to']) ?></strong></td>
                            <td><?= $badge((string) $h['status']) ?></td>
                            <td class="text-end"><button type="button" class="btn btn-sm btn-outline-secondary" data-up-details="<?= (int) $i ?>">Details</button></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody></table></div>
                    <?php if ($historyTotal > count($history)): ?><div class="p-2 small text-muted text-center">Showing the latest <?= count($history) ?> of <?= (int) $historyTotal ?>.</div><?php endif; ?>
                <?php endif; ?>
            </div>
        </section>
    </div>

    <aside class="ap-settings-aside">
        <div class="ap-aside-card">
            <h2><i class="fa-solid fa-code-branch" aria-hidden="true"></i>Installed Version</h2>
            <div class="ap-up-version">v<?= e($version) ?></div>
            <div class="small text-muted"><?= $last ? 'Last update: ' . e(format_date(gmdate('Y-m-d H:i:s', (int) strtotime((string) $last['time'])), 'd M Y, H:i')) : 'No update has been installed here yet.' ?></div>
        </div>

        <div class="ap-aside-card">
            <h2><i class="fa-solid fa-rotate-left" aria-hidden="true"></i>Go Back to the Previous Version</h2>
            <?php if ($target): ?>
                <p class="small text-muted mb-2">Puts the files of version <?= e((string) $target['from']) ?> back.</p>
                <div class="ap-up-note">Your listings, users, settings and everything else you added stay. Database updates are not undone; the older version simply ignores the new parts.</div>
                <button type="button" class="btn btn-sm btn-outline-secondary w-100 mt-2" data-up-rollback="<?= e((string) $target['name']) ?>" data-to="<?= e((string) $target['from']) ?>">Go back to <?= e((string) $target['from']) ?></button>
            <?php else: ?>
                <p class="small text-muted mb-0">Nothing to go back to yet. After you install an update, you can return to the version before it here.</p>
            <?php endif; ?>
            <div class="mt-2" data-up-rollback-result aria-live="polite"></div>
        </div>

        <div class="ap-aside-card">
            <h2><i class="fa-solid fa-shield-halved" aria-hidden="true"></i>How Your Data Stays Safe</h2>
            <ul>
                <li>The package is checked first: it must be a real AP Framework update and every file must match its checksum.</li>
                <li>Files that belong to your site (configuration, storage, uploads, .htaccess) are never replaced.</li>
                <li>If a step fails, the old files are put back automatically.</li>
                <li><?= setting('updates.backup_database', true) ? 'A database backup is made before every update and kept with your file backups.' : 'The database backup is switched off.' ?> <?php if (can('settings.view')): ?><a href="<?= e(admin_url('settings/updates')) ?>">Change this</a><?php endif; ?></li>
            </ul>
        </div>

        <div class="ap-aside-card">
            <details class="ap-up-backups">
                <summary><h2 class="mb-0"><i class="fa-solid fa-box-archive" aria-hidden="true"></i>Older File Backups (<?= count($backups) ?>)</h2></summary>
                <?php if (!$backups): ?>
                    <p class="small text-muted mt-2 mb-0">Backups appear here after your first update.</p>
                <?php else: ?>
                    <ul class="list-unstyled mt-2 mb-2 ap-up-backlist">
                    <?php foreach ($backups as $b): $protected = $target && $target['name'] === $b['name']; ?>
                        <li>
                            <div class="small"><strong>v<?= e((string) $b['from']) ?></strong> <span class="text-muted"><?= e(format_date(gmdate('Y-m-d H:i:s', (int) strtotime((string) $b['created'])), 'd M Y, H:i')) ?> · <?= e(format_bytes((int) $b['size'])) ?></span></div>
                            <div class="d-flex gap-1 mt-1">
                                <?php if (!empty($b['db_backup']) && $b['db_size']): ?><a class="btn btn-sm btn-outline-secondary" href="<?= e(admin_url('system/updates/database/' . $b['db_backup'])) ?>" title="Download the database backup"><i class="fa-solid fa-database me-1"></i><?= e(format_bytes((int) $b['db_size'])) ?></a><?php endif; ?>
                                <?php if (!$protected): ?>
                                    <form method="post" action="<?= e(admin_url('system/updates/backups/' . $b['name'] . '/delete')) ?>" data-confirm="Delete this backup? You will not be able to go back to it."><?= csrf_field() ?><button class="btn btn-sm btn-outline-danger" aria-label="Delete this backup"><i class="fa-solid fa-trash"></i></button></form>
                                <?php else: ?><span class="small text-muted align-self-center">Needed for "Go back"</span><?php endif; ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                    </ul>
                    <?php if (count($backups) > $keep): ?>
                    <form method="post" action="<?= e(admin_url('system/updates/backups/prune')) ?>" data-confirm="Delete all but the newest <?= (int) $keep ?> backups?"><?= csrf_field() ?><button class="btn btn-sm btn-outline-secondary w-100">Delete all but the newest <?= (int) $keep ?></button></form>
                    <?php endif; ?>
                <?php endif; ?>
            </details>
        </div>
    </aside>
</div>
</div>

<script type="application/json" id="apUpdateHistory"><?= json_encode($history, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
<div class="modal fade" id="updateDetails" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable"><div class="modal-content">
    <div class="modal-header"><h2 class="modal-title h5" data-ud-title>Update details</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
    <div class="modal-body" data-ud-body></div>
</div></div></div>
<?php $this->endSection(); ?>
