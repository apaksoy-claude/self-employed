<?php declare(strict_types=1);
/** @var \AP\Core\View $this */
use AP\Modules\Admin\Ui;

$this->extend('admin::layout');
$editing = $edit !== null;
$val = static fn(string $k, string $d = '') => (string) old($k, $edit[$k] ?? $d);
$fromVal = $editing ? $val('from_path') : (string) old('from_path', $prefill);
$err = static function (string $k): string {
    $out = '';
    foreach (form_errors($k) as $m) {
        $out .= '<div class="invalid-feedback d-block">' . e($m) . '</div>';
    }
    return $out;
};
$site = rtrim(url('/'), '/') . '/';
$qs = array_filter(['tab' => $tab === 'missing' ? 'missing' : null, 'q' => $q]);
?>
<?php $this->section('content'); ?>
<div class="ap-settings ap-two">
    <div class="ap-sections">
        <section class="ap-section" id="add">
            <div class="ap-section-head ap-section-head-static">
                <h2><i class="fa-solid fa-diamond-turn-right" aria-hidden="true"></i><?= $editing ? 'Change a redirect' : 'Add a redirect' ?></h2>
            </div>
            <div class="ap-section-body">
                <p class="ap-section-intro">Visitors (and search engines) who open the old address are sent to the new one. End the old address with * to move a whole part of the site, for example <code>blog/*</code> to <code>news/*</code>.</p>
                <form method="post" action="<?= e(admin_url($editing ? 'redirects/' . (int) $edit['id'] : 'redirects')) ?>" class="ap-redirect-form" novalidate>
                    <?= csrf_field() ?>
                    <div class="row g-3">
                        <div class="col-lg-5">
                            <label class="form-label" for="rFrom">Old address</label>
                            <div class="input-group"><span class="input-group-text ap-prefix" title="<?= e($site) ?>"><?= e(mb_strimwidth($site, 0, 34, '…')) ?></span>
                                <input type="text" class="form-control<?= form_errors('from_path') ? ' is-invalid' : '' ?>" id="rFrom" name="from_path" value="<?= e($fromVal) ?>" placeholder="old-page" required autocomplete="off"></div>
                            <?= $err('from_path') ?>
                        </div>
                        <div class="col-lg-5">
                            <label class="form-label" for="rTo">Send visitors to</label>
                            <input type="text" class="form-control<?= form_errors('to_url') ? ' is-invalid' : '' ?>" id="rTo" name="to_url" value="<?= e($val('to_url')) ?>" placeholder="new-page or https://…" required autocomplete="off" list="rPages">
                            <?= $err('to_url') ?>
                        </div>
                        <div class="col-lg-2">
                            <label class="form-label" for="rCode">Type</label>
                            <select class="form-select" id="rCode" name="code">
                                <option value="301"<?= $val('code', '301') === '301' ? ' selected' : '' ?>>Moved for good (301)</option>
                                <option value="302"<?= $val('code', '301') === '302' ? ' selected' : '' ?>>For now (302)</option>
                            </select>
                        </div>
                        <div class="col-lg-10">
                            <label class="form-label" for="rNote">Note <span class="text-muted fw-normal">(only you see it)</span></label>
                            <input type="text" class="form-control" id="rNote" name="note" value="<?= e($val('note')) ?>" maxlength="190" placeholder="Why this redirect exists">
                        </div>
                        <div class="col-lg-2 d-flex align-items-end gap-2">
                            <button class="btn btn-primary w-100" type="submit"><i class="fa-solid <?= $editing ? 'fa-floppy-disk' : 'fa-plus' ?> me-1"></i><?= $editing ? 'Save' : 'Add' ?></button>
                        </div>
                    </div>
                    <?php if ($editing): ?><a class="small d-inline-block mt-2" href="<?= e(admin_url('redirects')) ?>">Cancel and add a new one instead</a><?php endif; ?>
                </form>
                <?php if (class_exists(\AP\Modules\Pages\Pages::class) && \AP\Modules\Pages\Pages::ready()): ?>
                    <datalist id="rPages"><?php foreach (\AP\Modules\Pages\Pages::db()->table('pages')->whereNull('deleted_at')->where('status', 'published')->orderBy('path')->limit(300)->get() as $p): ?><option value="<?= e($p['path']) ?>"><?= e($p['title']) ?></option><?php endforeach; ?></datalist>
                <?php endif; ?>
            </div>
        </section>

        <div class="ap-tabs-bar">
            <nav class="nav ap-tabs" aria-label="Lists">
                <a class="nav-link<?= $tab === 'redirects' ? ' active' : '' ?>" href="<?= e(admin_url('redirects')) ?>"<?= $tab === 'redirects' ? ' aria-current="page"' : '' ?>><i class="fa-solid fa-diamond-turn-right me-1"></i>Redirects <span class="ap-tab-count"><?= (int) $counts['redirects'] ?></span></a>
                <a class="nav-link<?= $tab === 'missing' ? ' active' : '' ?>" href="<?= e(admin_url('redirects') . '?tab=missing') ?>"<?= $tab === 'missing' ? ' aria-current="page"' : '' ?>><i class="fa-solid fa-link-slash me-1"></i>Addresses not found <span class="ap-tab-count"><?= (int) $counts['missing'] ?></span></a>
            </nav>
            <form method="get" class="ap-tabs-search" role="search">
                <?php if ($tab === 'missing'): ?><input type="hidden" name="tab" value="missing"><?php endif; ?>
                <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                <input type="search" class="form-control form-control-sm" name="q" value="<?= e($q) ?>" placeholder="Find an address" aria-label="Find an address" data-shortcut-search>
            </form>
        </div>

        <?php if ($tab === 'redirects'): ?>
            <?php if (!$pager['data']): ?>
                <div class="ap-section"><div class="ap-section-body"><?= Ui::emptyState($q !== '' ? 'No redirect matches.' : 'No redirects yet.', $q !== '' ? 'Try another word.' : 'Add one above, or look at the addresses visitors could not find.', null, null, 'fa-diamond-turn-right') ?></div></div>
            <?php else: ?>
                <div class="ap-section"><div class="table-responsive"><table class="table mb-0 align-middle ap-redirects">
                    <thead><tr><th>Old address</th><th></th><th>Goes to</th><th>Type</th><th class="text-end">Visits</th><th>Last used</th><th>On</th><th></th></tr></thead><tbody>
                    <?php foreach ($pager['data'] as $r): $to = \AP\Modules\Seo\Seo::target($r + ['_rest' => '']); ?>
                        <tr class="<?= (int) $r['enabled'] ? '' : 'is-off' ?>">
                            <td><code class="ap-path">/<?= e($r['from_path']) ?></code><?php if ($r['note']): ?><div class="small text-muted"><?= e($r['note']) ?></div><?php endif; ?></td>
                            <td class="text-muted"><i class="fa-solid fa-arrow-right-long"></i></td>
                            <td class="ap-break"><a href="<?= e(str_ends_with((string) $r['to_url'], '*') ? '#' : $to) ?>" target="_blank" rel="noopener"><?= e(preg_match('#^https?://#i', (string) $r['to_url']) ? $r['to_url'] : '/' . $r['to_url']) ?></a></td>
                            <td><?= Ui::badge((int) $r['code'] === 302 ? 'For now' : 'For good', (int) $r['code'] === 302 ? 'warning' : 'info') ?></td>
                            <td class="text-end"><?= number_format((int) $r['hits']) ?></td>
                            <td class="small text-muted text-nowrap"><?= $r['last_hit'] ? e(format_date((string) $r['last_hit'])) : 'Not yet' ?></td>
                            <td><form method="post" action="<?= e(admin_url('redirects/' . (int) $r['id'] . '/toggle')) ?>"><?= csrf_field() ?>
                                <div class="form-check form-switch m-0"><input class="form-check-input" type="checkbox" role="switch" aria-label="Switch this redirect on or off"<?= (int) $r['enabled'] ? ' checked' : '' ?> data-autosubmit></div></form></td>
                            <td class="text-end text-nowrap">
                                <a class="btn btn-sm btn-outline-secondary" href="<?= e(admin_url('redirects') . '?edit=' . (int) $r['id'] . '#add') ?>" title="Change" aria-label="Change"><i class="fa-solid fa-pen"></i></a>
                                <form class="d-inline" method="post" action="<?= e(admin_url('redirects/' . (int) $r['id'] . '/delete')) ?>" data-confirm="Remove the redirect for /<?= e($r['from_path']) ?>?"><?= csrf_field() ?><button class="btn btn-sm btn-outline-danger" title="Remove" aria-label="Remove"><i class="fa-solid fa-trash-can"></i></button></form>
                            </td>
                        </tr>
                    <?php endforeach; ?></tbody></table></div></div>
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2"><div class="small text-muted"><?= e(__('core.page_of', ['page' => $pager['page'], 'last' => $pager['last_page'], 'total' => $pager['total']])) ?></div><?= Ui::pagination($pager, $qs) ?></div>
            <?php endif; ?>
        <?php else: ?>
            <?php if (!$logging): ?>
                <div class="alert alert-info mb-0"><i class="fa-solid fa-circle-info me-2"></i>Addresses not found are not being noted. Switch it on in <a href="<?= e(admin_url('settings/seo')) ?>">SEO and analytics settings</a>.</div>
            <?php endif; ?>
            <?php if (!$pager['data']): ?>
                <div class="ap-section"><div class="ap-section-body"><?= Ui::emptyState('Nothing here.', 'When visitors open an address that does not exist, it shows up here with how often it happened.', null, null, 'fa-face-smile') ?></div></div>
            <?php else: ?>
                <div class="ap-section"><div class="table-responsive"><table class="table mb-0 align-middle">
                    <thead><tr><th>Address</th><th class="text-end">Visits</th><th>Last time</th><th>Came from</th><th></th></tr></thead><tbody>
                    <?php foreach ($pager['data'] as $n): ?>
                        <tr>
                            <td class="ap-break"><code class="ap-path">/<?= e($n['path']) ?></code></td>
                            <td class="text-end fw-semibold"><?= number_format((int) $n['hits']) ?></td>
                            <td class="small text-muted text-nowrap"><?= e(format_date((string) $n['last_seen'])) ?></td>
                            <td class="small text-muted ap-break"><?= $n['referrer'] ? e(mb_strimwidth((string) $n['referrer'], 0, 70, '…')) : '—' ?></td>
                            <td class="text-end text-nowrap">
                                <a class="btn btn-sm btn-outline-primary" href="<?= e(admin_url('redirects') . '?from=' . rawurlencode((string) $n['path']) . '#add') ?>"><i class="fa-solid fa-diamond-turn-right me-1"></i>Redirect</a>
                                <form class="d-inline" method="post" action="<?= e(admin_url('redirects/not-found/' . (int) $n['id'] . '/delete')) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-outline-secondary" title="Remove from the list" aria-label="Remove from the list"><i class="fa-solid fa-xmark"></i></button></form>
                            </td>
                        </tr>
                    <?php endforeach; ?></tbody></table></div></div>
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2"><div class="small text-muted"><?= e(__('core.page_of', ['page' => $pager['page'], 'last' => $pager['last_page'], 'total' => $pager['total']])) ?></div><?= Ui::pagination($pager, $qs) ?></div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <aside class="ap-settings-aside">
        <div class="ap-aside-card"><h2><i class="fa-solid fa-route"></i>Where does it go?</h2>
            <form method="post" action="<?= e(admin_url('redirects/test')) ?>" class="d-flex gap-2"><?= csrf_field() ?>
                <input type="text" class="form-control form-control-sm" name="address" placeholder="/old-page" aria-label="Address to try" required>
                <button class="btn btn-sm btn-dark" type="submit">Try</button>
            </form>
            <?php if ($test): ?>
                <div class="ap-test-result <?= $test['to'] ? 'is-ok' : 'is-none' ?>">
                    <?php if ($test['to']): ?><strong><?= e($test['address']) ?></strong> goes to <a href="<?= e($test['to']) ?>" target="_blank" rel="noopener"><?= e($test['to']) ?></a> (<?= (int) $test['code'] ?>, rule <code><?= e($test['rule']) ?></code>).
                    <?php else: ?><strong><?= e($test['address']) ?></strong> has no redirect. If the page does not exist, visitors see "Page not found".<?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        <div class="ap-aside-card"><h2><i class="fa-regular fa-lightbulb"></i>Tips</h2><ul>
            <li>Use "Moved for good" when a page moved or was renamed; search engines then move its ranking to the new address.</li>
            <li>Redirects only take over addresses that do not exist, so a live page is never hidden by mistake.</li>
            <li>Robots often try addresses such as wp-login.php; those are never listed.</li>
        </ul>
        <?php if ($tab === 'missing' && $counts['missing'] > 0): ?>
            <form method="post" action="<?= e(admin_url('redirects/not-found/clear')) ?>" data-confirm="Empty the list of addresses not found?" class="mt-2"><?= csrf_field() ?><button class="btn btn-sm btn-outline-danger w-100"><i class="fa-solid fa-broom me-1"></i>Empty the list</button></form>
        <?php endif; ?>
        </div>
    </aside>
</div>
<?php $this->endSection(); ?>
