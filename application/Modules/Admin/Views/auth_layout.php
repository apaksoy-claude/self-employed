<?php declare(strict_types=1);
/** @var \AP\Core\View $this */
$siteName = (string) setting('general.site_name', 'AP Framework');
$toasts = session()->toasts();
$font = (string) setting('admin.font', 'poppins');
$accent = (string) setting('admin.accent', '#2563eb');
$accent = preg_match('/^#[0-9a-f]{6}$/i', $accent) ? $accent : '#2563eb';
$dark = (string) setting('admin.login_style', 'dark') !== 'light';
$bg = (string) setting('admin.login_bg', '#222b3c');
$bg = preg_match('/^#[0-9a-f]{6}$/i', $bg) ? $bg : '#222b3c';
$logo = trim((string) setting('admin.login_logo', ''));
$logo = $logo !== '' && \AP\Core\HtmlSanitizer::safeUrl($logo, true) ? $logo : '';
$icon = (string) setting('admin.login_icon', 'fa-layer-group');
$icon = preg_match('/^fa-[a-z0-9-]+$/', $icon) ? $icon : '';
// The language choice (EN / TR) when the site offers several languages.
$langs = [];
if (setting('admin.login_languages', true) && class_exists(\AP\Modules\Languages\Locales::class)) {
    try {
        $langs = \AP\Modules\Languages\Locales::enabled();
    } catch (\Throwable) {
        $langs = [];
    }
}
$current = locale();
?>
<!doctype html>
<html lang="<?= e($current) ?>" dir="<?= e(text_direction()) ?>" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title><?= e($title ?? 'Log in') ?> · <?= e($siteName) ?></title>
    <?php if ($font === 'poppins'): ?><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&amp;display=swap"><?php endif; ?>
    <link rel="stylesheet" href="<?= e(vendor_url('bootstrap/css/bootstrap.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(vendor_url('fontawesome/css/all.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>?v=<?= e(AP_VERSION) ?>">
    <?= favicon_tag('admin.favicon', 'general.favicon') ?>
    <style>:root{--ap-accent:<?= e($accent) ?>;--ap-login-bg:<?= e($bg) ?>}</style>
</head>
<body class="ap-auth ap-auth-<?= $dark ? 'dark' : 'light' ?> ap-font-<?= e($font === 'system' ? 'system' : 'poppins') ?>">
<main class="ap-auth-wrap">
    <div class="card ap-card ap-auth-card w-100">
        <div class="card-body">
            <div class="ap-auth-top">
                <a class="ap-auth-logo" href="<?= e(url('/')) ?>" title="<?= e($siteName) ?>">
                    <?php if ($logo !== ''): ?><img src="<?= e(str_starts_with($logo, '/') && !str_starts_with($logo, '//') ? url($logo) : $logo) ?>" alt="<?= e($siteName) ?>">
                    <?php else: ?><?php if ($icon !== ''): ?><i class="fa-solid <?= e($icon) ?>" aria-hidden="true"></i><?php endif; ?><span><?= e($siteName) ?></span><?php endif; ?>
                </a>
                <?php if (count($langs) > 1): ?>
                    <nav class="ap-auth-langs" aria-label="Language">
                        <?php foreach ($langs as $code => $l): $short = strtoupper(explode('-', (string) $code)[0]); ?>
                            <a href="<?= e(url('lang/' . $code) . '?back=' . rawurlencode(request()->path)) ?>" hreflang="<?= e((string) $code) ?>" lang="<?= e((string) $code) ?>" title="<?= e((string) $l['native_name']) ?>"<?= $code === $current ? ' class="is-on" aria-current="true"' : '' ?>><?= e($short) ?></a>
                        <?php endforeach; ?>
                    </nav>
                <?php endif; ?>
            </div>
            <h1 class="ap-auth-title"><?= e($title ?? 'Log in') ?></h1>
            <?php if (!empty($subtitle)): ?><p class="ap-auth-sub"><?= e($subtitle) ?></p><?php endif; ?>
            <?php foreach ($toasts as $t): ?>
                <div class="alert alert-<?= e(in_array($t['type'], ['success', 'danger', 'warning', 'info'], true) ? $t['type'] : 'info') ?>" role="alert"><?= e($t['message']) ?></div>
            <?php endforeach; ?>
            <?php $this->show('content'); ?>
        </div>
    </div>
    <p class="ap-auth-foot">&copy; <?= e(date('Y')) ?> <?= e($siteName) ?></p>
</main>
<script src="<?= e(vendor_url('bootstrap/js/bootstrap.bundle.min.js')) ?>"></script>
<script src="<?= e(asset('js/auth.js')) ?>?v=<?= e(AP_VERSION) ?>"></script>
</body>
</html>
