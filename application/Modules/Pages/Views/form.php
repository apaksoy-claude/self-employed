<?php declare(strict_types=1);
/** @var \AP\Core\View $this @var array $page */
use AP\Modules\Pages\Pages;

$this->extend('admin::layout');
$id = (int) $page['id'];
$isNew = $id === 0;
$action = $isNew ? admin_url('pages') : admin_url('pages/' . $id);
$status = (string) old('status', $page['status']);
$err = static fn(string $k): string => implode(' ', (array) form_errors($k));
$val = static fn(string $k): string => (string) old($k, (string) ($page[$k] ?? ''));
$template = (string) old('template', $page['template']);
$parentSel = (string) old('parent_id', (string) ($page['parent_id'] ?? ''));
$mediaOn = app(\AP\Core\ModuleManager::class)->isLoaded('media') && can('media.view');
\AP\Core\View::inject('admin.body_end', '<script src="' . e(asset('js/editor.js')) . '?v=' . e(AP_VERSION) . '"></script><script src="' . e(asset('js/pages.js')) . '?v=' . e(AP_VERSION) . '"></script>');
?>
<?php $this->section('actions'); ?>
<?php if (!$isNew): ?>
    <a class="btn btn-outline-secondary" href="<?= e(admin_url('pages/' . $id . '/preview')) ?>" target="_blank" rel="noopener"><i class="fa-solid fa-eye me-1"></i>Preview</a>
    <?php if ($live): ?><a class="btn btn-outline-secondary" href="<?= e($publicUrl) ?>" target="_blank" rel="noopener"><i class="fa-solid fa-arrow-up-right-from-square me-1"></i>View Page</a><?php endif; ?>
<?php endif; ?>
<?php $this->endSection(); ?>
<?php $this->section('content'); ?>
<form method="post" action="<?= e($action) ?>" class="ap-page-form" data-unsaved-warning data-page-form novalidate
      data-slug-url="<?= e(admin_url('pages/slug')) ?>" data-page-id="<?= $id ?>" data-base-url="<?= e($baseUrl) ?>" data-site-name="<?= e((string) setting('general.site_name', '')) ?>"
      data-title-format="<?= e((string) setting('pages.title_format', '{title} | {site}')) ?>">
    <?= csrf_field() ?>
    <button type="submit" class="visually-hidden" tabindex="-1" aria-hidden="true">Save</button>
    <?php if (form_errors()): ?>
        <div class="alert alert-danger"><i class="fa-solid fa-circle-exclamation me-2"></i>The page was not saved. Please check the fields marked below.</div>
    <?php endif; ?>
    <div class="row g-3">
        <div class="col-xl-8">
            <section class="ap-section mb-3"><div class="ap-section-body pt-3 pb-3 border-0">
                <label class="form-label" for="pg_title">Title <span class="text-danger" aria-hidden="true">*</span></label>
                <input type="text" class="form-control form-control-lg ap-title-input<?= $err('title') ? ' is-invalid' : '' ?>" id="pg_title" name="title" value="<?= e($val('title')) ?>" maxlength="190" required placeholder="For example: About us" data-page-title autofocus>
                <?php if ($err('title')): ?><div class="invalid-feedback d-block"><?= e($err('title')) ?></div><?php endif; ?>

                <div class="ap-slug mt-2" data-slug-box>
                    <span class="ap-slug-label"><i class="fa-solid fa-link me-1"></i>Address:</span>
                    <span class="ap-slug-base"><?= e($baseUrl) ?><span data-slug-parent></span></span>
                    <input type="text" class="form-control form-control-sm ap-slug-input<?= $err('slug') ? ' is-invalid' : '' ?>" name="slug" value="<?= e($val('slug')) ?>" maxlength="190" aria-label="Address of the page"
                           placeholder="made from the title" data-page-slug<?= $isNew ? '' : ' data-slug-locked' ?>>
                    <button type="button" class="btn btn-sm btn-link px-1" data-copy-url title="Copy the full address" aria-label="Copy the full address"><i class="fa-regular fa-copy"></i></button>
                    <span class="small" data-slug-note aria-live="polite"></span>
                </div>
                <div class="form-text">Letters, numbers and hyphens. Leave it empty to make it from the title.<?= $isHome ? ' This page is your home page, so visitors see it at the site address.' : '' ?></div>
            </div></section>

            <section class="ap-section mb-3" data-editor data-editor-mode="<?= e($editorMode) ?>">
                <div class="ap-editor-toolbar" role="toolbar" aria-label="Text formatting">
                    <select class="form-select form-select-sm ap-ed-block" data-ed-block aria-label="Paragraph style">
                        <option value="p">Paragraph</option><option value="h2">Heading 2</option><option value="h3">Heading 3</option><option value="h4">Heading 4</option><option value="blockquote">Quote</option><option value="pre">Code block</option>
                    </select>
                    <span class="ap-ed-sep"></span>
                    <button type="button" data-ed="bold" title="Bold (Ctrl+B)" aria-label="Bold"><i class="fa-solid fa-bold"></i></button>
                    <button type="button" data-ed="italic" title="Italic (Ctrl+I)" aria-label="Italic"><i class="fa-solid fa-italic"></i></button>
                    <button type="button" data-ed="underline" title="Underline (Ctrl+U)" aria-label="Underline"><i class="fa-solid fa-underline"></i></button>
                    <button type="button" data-ed="strikeThrough" title="Strike through" aria-label="Strike through"><i class="fa-solid fa-strikethrough"></i></button>
                    <span class="ap-ed-sep"></span>
                    <button type="button" data-ed="insertUnorderedList" title="Bulleted list" aria-label="Bulleted list"><i class="fa-solid fa-list-ul"></i></button>
                    <button type="button" data-ed="insertOrderedList" title="Numbered list" aria-label="Numbered list"><i class="fa-solid fa-list-ol"></i></button>
                    <span class="ap-ed-sep"></span>
                    <button type="button" data-ed="justifyLeft" title="Align left" aria-label="Align left"><i class="fa-solid fa-align-left"></i></button>
                    <button type="button" data-ed="justifyCenter" title="Centre" aria-label="Centre"><i class="fa-solid fa-align-center"></i></button>
                    <button type="button" data-ed="justifyRight" title="Align right" aria-label="Align right"><i class="fa-solid fa-align-right"></i></button>
                    <span class="ap-ed-sep"></span>
                    <button type="button" data-ed-link title="Link (Ctrl+K)" aria-label="Insert a link"><i class="fa-solid fa-link"></i></button>
                    <button type="button" data-ed="unlink" title="Remove link" aria-label="Remove link"><i class="fa-solid fa-link-slash"></i></button>
                    <button type="button" data-ed-image title="Image" aria-label="Insert an image"><i class="fa-regular fa-image"></i></button>
                    <button type="button" data-ed-table title="Table" aria-label="Insert a table"><i class="fa-solid fa-table"></i></button>
                    <button type="button" data-ed="insertHorizontalRule" title="Divider line" aria-label="Divider line"><i class="fa-solid fa-minus"></i></button>
                    <?php foreach ((array) filter('editor.inserts', []) as $ins): ?>
                        <button type="button" data-ed-insert="<?= e((string) $ins['html']) ?>" title="<?= e((string) $ins['label']) ?>" aria-label="<?= e((string) $ins['label']) ?>"><i class="fa-solid <?= e((string) ($ins['icon'] ?? 'fa-puzzle-piece')) ?>"></i></button>
                    <?php endforeach; ?>
                    <span class="ap-ed-sep"></span>
                    <button type="button" data-ed="removeFormat" title="Clear formatting" aria-label="Clear formatting"><i class="fa-solid fa-text-slash"></i></button>
                    <button type="button" data-ed="undo" title="Undo (Ctrl+Z)" aria-label="Undo"><i class="fa-solid fa-rotate-left"></i></button>
                    <button type="button" data-ed="redo" title="Redo (Ctrl+Y)" aria-label="Redo"><i class="fa-solid fa-rotate-right"></i></button>
                    <span class="ms-auto d-flex gap-1">
                        <button type="button" class="ap-ed-mode" data-ed-mode title="Switch between the visual editor and HTML"><i class="fa-solid fa-code me-1"></i><span data-ed-mode-label>HTML</span></button>
                        <button type="button" data-ed-full title="Full screen" aria-label="Full screen"><i class="fa-solid fa-expand"></i></button>
                    </span>
                </div>
                <div class="ap-editor-area ap-page-content" contenteditable="true" role="textbox" aria-multiline="true" aria-label="Page content" data-ed-area data-placeholder="Start writing your page here…"></div>
                <textarea class="form-control ap-editor-source" name="content" data-ed-source aria-label="Page content as HTML" spellcheck="false"><?= e($val('content')) ?></textarea>
                <div class="ap-editor-status"><span data-ed-words>0 words</span><span class="text-muted">Scripts and unsafe code are removed when you save.</span></div>
            </section>

            <section class="ap-section mb-3" data-card id="card-page-summary">
                <button type="button" class="ap-section-head" data-card-toggle aria-expanded="true" aria-controls="card-page-summary-body"><h2><i class="fa-solid fa-align-left" aria-hidden="true"></i>Summary</h2><i class="fa-solid fa-chevron-down ap-chev" aria-hidden="true"></i></button>
                <div class="ap-section-body pb-3" id="card-page-summary-body">
                    <label class="form-label" for="pg_excerpt">Short summary <span class="text-muted small">(optional)</span></label>
                    <textarea class="form-control<?= $err('excerpt') ? ' is-invalid' : '' ?>" id="pg_excerpt" name="excerpt" rows="2" maxlength="500" data-count="500"><?= e($val('excerpt')) ?></textarea>
                    <div class="form-text d-flex justify-content-between"><span>Used in lists of pages and as the description when the search engine one is empty.</span><span data-count-for="pg_excerpt"></span></div>
                </div>
            </section>

            <section class="ap-section mb-3" data-card id="card-page-seo">
                <button type="button" class="ap-section-head" data-card-toggle aria-expanded="true" aria-controls="card-page-seo-body"><h2><i class="fa-brands fa-google" aria-hidden="true"></i>Search Engines and Sharing</h2><i class="fa-solid fa-chevron-down ap-chev" aria-hidden="true"></i></button>
                <div class="ap-section-body pb-3" id="card-page-seo-body">
                    <div class="ap-serp mb-3" aria-label="How the page may look in Google">
                        <div class="ap-serp-url" data-serp-url><?= e($baseUrl . $page['path']) ?></div>
                        <div class="ap-serp-title" data-serp-title></div>
                        <div class="ap-serp-desc" data-serp-desc></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="pg_seo_title">Search engine title</label>
                        <input type="text" class="form-control<?= $err('seo_title') ? ' is-invalid' : '' ?>" id="pg_seo_title" name="seo_title" value="<?= e($val('seo_title')) ?>" maxlength="190" data-count="60" data-count-soft placeholder="Made from the title when empty">
                        <div class="form-text d-flex justify-content-between"><span>Shown as the blue link in search results. Around 50 to 60 characters works best.</span><span data-count-for="pg_seo_title"></span></div>
                        <?php if ($err('seo_title')): ?><div class="invalid-feedback d-block"><?= e($err('seo_title')) ?></div><?php endif; ?>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="pg_seo_description">Search engine description</label>
                        <textarea class="form-control<?= $err('seo_description') ? ' is-invalid' : '' ?>" id="pg_seo_description" name="seo_description" rows="2" maxlength="300" data-count="160" data-count-soft placeholder="Made from the summary or the start of the text when empty"><?= e($val('seo_description')) ?></textarea>
                        <div class="form-text d-flex justify-content-between"><span>One or two sentences that make people want to click. Around 150 to 160 characters.</span><span data-count-for="pg_seo_description"></span></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="pg_social_image">Sharing image</label>
                        <div class="d-flex gap-2 align-items-start">
                            <div class="flex-grow-1">
                                <div class="input-group">
                                    <input type="text" class="form-control<?= $err('social_image') ? ' is-invalid' : '' ?>" id="pg_social_image" name="social_image" value="<?= e($val('social_image')) ?>" maxlength="500" placeholder="https://… or /assets/uploads/…" data-social-image>
                                    <?php if ($mediaOn): ?><button type="button" class="btn btn-outline-secondary" data-media-pick="#pg_social_image" data-media-kind="image"><i class="fa-regular fa-images me-1"></i>Choose</button><?php endif; ?>
                                </div>
                                <?php if ($err('social_image')): ?><div class="invalid-feedback d-block"><?= e($err('social_image')) ?></div><?php endif; ?>
                                <div class="form-text">Shown when the page is shared on Facebook, LinkedIn, WhatsApp and others. 1200 × 630 pixels is ideal.</div>
                            </div>
                            <div class="ap-social-thumb" data-social-thumb aria-hidden="true"><i class="fa-regular fa-image"></i></div>
                        </div>
                    </div>
                    <div class="ap-toggle-row"><div class="ap-toggle-text"><label class="ap-toggle-label" for="pg_noindex">Hide this page from search engines</label><div class="form-text mt-0">Visitors can still open it with its address. Good for thank-you pages and private offers.</div></div>
                        <div class="form-check form-switch m-0"><input type="hidden" name="noindex" value="0"><input class="form-check-input" type="checkbox" role="switch" id="pg_noindex" name="noindex" value="1"<?= (int) old('noindex', (string) $page['noindex']) ? ' checked' : '' ?>></div></div>
                </div>
            </section>
        </div>

        <div class="col-xl-4">
            <div class="ap-page-side">
            <section class="ap-section mb-3">
                <div class="ap-section-head"><h2><i class="fa-solid fa-paper-plane" aria-hidden="true"></i>Publish</h2>
                    <span class="badge text-bg-<?= e(Pages::STATUSES[$page['status']]['badge'] ?? 'secondary') ?>"><?= $isNew ? 'New' : e(Pages::STATUSES[$page['status']]['label'] ?? '') ?></span></div>
                <div class="ap-section-body pb-3">
                    <?php if ($canPublish): ?>
                        <div class="ap-segmented w-100 mb-2" role="radiogroup" aria-label="Status">
                            <?php foreach (Pages::STATUSES as $k => $s): ?>
                                <input type="radio" class="btn-check" name="status" id="pg_status_<?= e($k) ?>" value="<?= e($k) ?>"<?= $status === $k ? ' checked' : '' ?> data-page-status>
                                <label class="btn flex-fill" for="pg_status_<?= e($k) ?>"><i class="fa-solid <?= e($s['icon']) ?> me-1"></i><?= e($s['label']) ?></label>
                            <?php endforeach; ?>
                        </div>
                        <div class="mb-2" data-schedule-box<?= $status === 'scheduled' ? '' : ' hidden' ?>>
                            <label class="form-label small mb-1" for="pg_publish_at">Go live on</label>
                            <input type="datetime-local" class="form-control<?= $err('publish_at') ? ' is-invalid' : '' ?>" id="pg_publish_at" name="publish_at" value="<?= e((string) old('publish_at', $publishLocal)) ?>">
                            <div class="form-text">Time zone: <?= e($timezone) ?>. The page appears by itself at that time.</div>
                            <?php if ($err('publish_at')): ?><div class="invalid-feedback d-block"><?= e($err('publish_at')) ?></div><?php endif; ?>
                        </div>
                        <p class="small text-muted mb-3" data-status-help></p>
                    <?php else: ?>
                        <input type="hidden" name="status" value="<?= e($isNew ? 'draft' : $page['status']) ?>">
                        <p class="small text-muted"><i class="fa-solid fa-lock me-1"></i>You can write and save this page. Someone with publishing rights makes it live.</p>
                    <?php endif; ?>
                    <button type="submit" class="btn btn-primary w-100" data-save-label><i class="fa-solid fa-floppy-disk me-1"></i><span><?= $isNew ? 'Save Draft' : 'Save Changes' ?></span></button>
                    <?php if (!$isNew): ?>
                        <dl class="ap-page-facts mt-3 mb-0">
                            <dt>Added</dt><dd><?= e(format_date((string) $page['created_at'])) ?><?= $author ? ' by ' . e((string) $author) : '' ?></dd>
                            <dt>Last saved</dt><dd><?= e(format_date((string) $page['updated_at'])) ?></dd>
                            <?php if ($page['status'] === 'published' && $page['publish_at']): ?><dt>Live since</dt><dd><?= e(format_date((string) $page['publish_at'])) ?></dd><?php endif; ?>
                        </dl>
                    <?php endif; ?>
                </div>
            </section>

            <section class="ap-section mb-3">
                <div class="ap-section-head"><h2><i class="fa-solid fa-sitemap" aria-hidden="true"></i>Page Settings</h2></div>
                <div class="ap-section-body pb-3">
                    <div class="mb-3">
                        <label class="form-label" for="pg_parent">Sits under</label>
                        <select class="form-select<?= $err('parent_id') ? ' is-invalid' : '' ?>" id="pg_parent" name="parent_id" data-page-parent>
                            <option value="">Nothing (top level)</option>
                            <?php foreach ($parents as $pid => $plabel): ?><option value="<?= (int) $pid ?>"<?= $parentSel === (string) $pid ? ' selected' : '' ?>><?= e($plabel) ?></option><?php endforeach; ?>
                        </select>
                        <?php if ($err('parent_id')): ?><div class="invalid-feedback d-block"><?= e($err('parent_id')) ?></div><?php endif; ?>
                        <div class="form-text">A sub-page's address starts with its parent's, for example /services/design.</div>
                    </div>
                    <div class="form-label mb-1" id="pg_template_label">Layout</div>
                    <div class="ap-choice-list" role="radiogroup" aria-labelledby="pg_template_label">
                        <?php foreach ($templates as $k => $t): ?>
                            <label class="ap-choice<?= $template === $k ? ' is-checked' : '' ?>">
                                <input type="radio" name="template" value="<?= e($k) ?>"<?= $template === $k ? ' checked' : '' ?>>
                                <span><strong><?= e($t['label']) ?></strong><small><?= e($t['help']) ?></small></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>

            <?php if (!$isNew): ?>
            <section class="ap-section mb-3" data-card id="card-page-revisions">
                <button type="button" class="ap-section-head" data-card-toggle aria-expanded="true" aria-controls="card-page-revisions-body"><h2><i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i>Revisions <span class="badge text-bg-light ms-1"><?= count($revisions) ?></span></h2><i class="fa-solid fa-chevron-down ap-chev" aria-hidden="true"></i></button>
                <div class="ap-section-body pb-2" id="card-page-revisions-body">
                    <?php if (!$revisions): ?>
                        <p class="small text-muted">Each time you save changes, the version before is kept here so you can go back.</p>
                    <?php else: ?>
                        <ul class="ap-rev-list">
                            <?php foreach ($revisions as $r): ?>
                                <li>
                                    <div class="small"><strong><?= e(format_date((string) $r['created_at'])) ?></strong><div class="text-muted"><?= e($r['user_name'] ?: 'Someone') ?><?= $r['note'] ? ' · ' . e($r['note']) : '' ?></div></div>
                                    <div class="d-flex gap-1">
                                        <button type="button" class="btn btn-sm btn-outline-secondary" data-rev-view="<?= e(admin_url('pages/' . $id . '/revisions/' . (int) $r['id'])) ?>" title="Look at this version" aria-label="Look at this version"><i class="fa-solid fa-eye"></i></button>
                                        <button type="submit" class="btn btn-sm btn-outline-secondary" formaction="<?= e(admin_url('pages/' . $id . '/revisions/' . (int) $r['id'] . '/restore')) ?>" formnovalidate
                                                data-confirm="Go back to the version of <?= e(format_date((string) $r['created_at'])) ?>? The current text is kept as a revision." title="Go back to this version" aria-label="Go back to this version"><i class="fa-solid fa-rotate-left"></i></button>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </section>

            <section class="ap-section mb-3">
                <div class="ap-section-head"><h2><i class="fa-solid fa-ellipsis" aria-hidden="true"></i>More</h2></div>
                <div class="ap-section-body pb-3 d-grid gap-2">
                    <button type="submit" class="btn btn-outline-secondary" formaction="<?= e(admin_url('pages/' . $id . '/duplicate')) ?>" formnovalidate><i class="fa-regular fa-copy me-1"></i>Duplicate as a Draft</button>
                    <a class="btn btn-outline-secondary" href="<?= e(admin_url('pages/create') . '?parent=' . $id) ?>"><i class="fa-solid fa-diagram-project me-1"></i>Add a Sub-Page</a>
                    <?php if ($canPublish && $live && !$isHome): ?><button type="submit" class="btn btn-outline-secondary" formaction="<?= e(admin_url('pages/' . $id . '/home')) ?>" formnovalidate><i class="fa-solid fa-house me-1"></i>Make It the Home Page</button><?php endif; ?>
                    <?php if (can('pages.delete')): ?><button type="submit" class="btn btn-outline-danger" formaction="<?= e(admin_url('pages/' . $id . '/trash')) ?>" formnovalidate data-confirm="Move this page to the bin? You can restore it later."><i class="fa-solid fa-trash-can me-1"></i>Move to the Bin</button><?php endif; ?>
                </div>
            </section>
            <?php endif; ?>
            </div>
        </div>
    </div>
</form>

<div class="modal fade" id="edLinkModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content">
    <div class="modal-header"><h2 class="modal-title h5">Insert a Link</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
    <div class="modal-body">
        <label class="form-label" for="edLinkUrl">Address</label>
        <input type="text" class="form-control" id="edLinkUrl" placeholder="https://… or /contact or mailto:…">
        <label class="form-label mt-3" for="edLinkText">Text <span class="text-muted small">(when nothing is selected)</span></label>
        <input type="text" class="form-control" id="edLinkText">
        <div class="form-check mt-3"><input class="form-check-input" type="checkbox" id="edLinkNew"><label class="form-check-label" for="edLinkNew">Open in a new tab</label></div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button type="button" class="btn btn-primary" id="edLinkOk">Insert Link</button></div>
</div></div></div>

<div class="modal fade" id="edImageModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content">
    <div class="modal-header"><h2 class="modal-title h5">Insert an Image</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
    <div class="modal-body">
        <?php if ($mediaOn): ?><button type="button" class="btn btn-outline-primary w-100 mb-3" data-ed-pick><i class="fa-regular fa-images me-1"></i>Choose from the Media Library</button><?php endif; ?>
        <label class="form-label" for="edImgUrl">Image address</label>
        <input type="text" class="form-control" id="edImgUrl" placeholder="https://… or /assets/uploads/…">
        <label class="form-label mt-3" for="edImgAlt">Description for screen readers and search engines</label>
        <input type="text" class="form-control" id="edImgAlt" placeholder="A team photo in our office">
        <label class="form-label mt-3" for="edImgCaption">Caption <span class="text-muted small">(optional)</span></label>
        <input type="text" class="form-control" id="edImgCaption">
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button type="button" class="btn btn-primary" id="edImgOk">Insert Image</button></div>
</div></div></div>

<div class="modal fade" id="revModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable"><div class="modal-content">
    <div class="modal-header"><h2 class="modal-title h5" data-rev-title>Revision</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
    <div class="modal-body"><div class="small text-muted mb-2" data-rev-meta></div><iframe class="ap-rev-frame" sandbox="" title="Content of this revision" data-rev-frame></iframe></div>
</div></div></div>
<?php $this->endSection(); ?>
