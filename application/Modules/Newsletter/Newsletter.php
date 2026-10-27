<?php
declare(strict_types=1);

namespace AP\Modules\Newsletter;

use AP\Core\DB;
use AP\Core\Kernel;
use AP\Core\Mailer;
use AP\Core\Request;
use AP\Modules\Spam\Spam;
use Throwable;

/** The newsletter sign-up box: [newsletter] on a page or the footer block, checked for spam and kept as subscribers. */
final class Newsletter
{
    private static int $count = 0;

    public static function db(): DB
    {
        /** @var DB $db */
        $db = app(DB::class);
        return $db;
    }

    public static function ready(): bool
    {
        try {
            return Kernel::schemaCurrent() && self::db()->hasTable('newsletter_subscribers');
        } catch (Throwable) {
            return false;
        }
    }

    public static function boot(): void
    {
        events()->addFilter('pages.content', static fn(string $html): string => str_contains($html, '[newsletter]')
            ? (string) preg_replace_callback('#(<p>\s*)?\[newsletter\](\s*</p>)?#', static fn(): string => self::form(), $html) : $html);
        events()->addFilter('editor.inserts', static fn(array $list): array => [...$list, ['label' => 'Insert the newsletter sign-up', 'icon' => 'fa-envelope-circle-check', 'html' => '<p>[newsletter]</p>']]);
        events()->addFilter('spam.forms', static fn(array $f): array => $f + ['newsletter' => 'Newsletter sign-up']);
        events()->addFilter('footer.blocks', static fn(array $b): array => $b + ['newsletter' => ['label' => 'Newsletter sign-up', 'icon' => 'fa-envelope-circle-check',
            'help' => 'An email box to sign up for your news. Wording in Settings > Newsletter Sign-up.', 'fields' => [
                ['key' => 'title', 'label' => 'Heading', 'type' => 'text', 'placeholder' => 'Empty: the heading from the settings', 'max' => 80],
                ['key' => 'text', 'label' => 'Text', 'type' => 'textarea', 'placeholder' => 'Empty: the text from the settings', 'max' => 300],
            ]]]);
        events()->addFilter('footer.block', static fn(string $html, array $b): string => ($b['type'] ?? '') === 'newsletter'
            ? self::form(['text' => (string) ($b['text'] ?? ''), 'compact' => true]) : $html);
        // In the footer builder the heading comes from the block; when it is empty, use the one from the settings.
        events()->addFilter('footer.block_title', static fn(string $t, array $b): string => ($b['type'] ?? '') === 'newsletter' && $t === '' ? (string) setting('newsletter.title') : $t);
    }

    public static function form(array $o = []): string
    {
        if (!self::ready()) {
            return '';
        }
        $n = ++self::$count;
        $id = 'nl' . $n;
        $compact = !empty($o['compact']);
        $done = session()->flashed('newsletter_done') && session()->flashed('newsletter_form') === $id;
        $title = $compact ? '' : trim((string) setting('newsletter.title'));
        $text = trim((string) ($o['text'] ?? '')) ?: trim((string) setting('newsletter.text'));
        $html = '<div class="ap-news' . ($compact ? ' is-compact' : '') . '" id="' . $id . '">';
        if ($title !== '') {
            $html .= '<h2 class="ap-news-title">' . e($title) . '</h2>';
        }
        if ($done) {
            return $html . '<p class="ap-news-thanks" role="status"><i class="fa-solid fa-circle-check me-2" aria-hidden="true"></i>' . e((string) setting('newsletter.thanks')) . '</p></div>';
        }
        if ($text !== '') {
            $html .= '<p class="ap-news-text">' . e($text) . '</p>';
        }
        $mine = session()->flashed('newsletter_form') === $id;
        $errors = $mine ? (array) form_errors('newsletter') : [];
        $html .= '<form method="post" action="' . e(url('newsletter/subscribe')) . '" class="ap-news-form" novalidate>' . csrf_field()
            . '<input type="hidden" name="form_id" value="' . $id . '"><input type="hidden" name="page" value="' . e(url(request()->path)) . '">';
        $html .= '<div class="ap-news-row">';
        if (setting('newsletter.ask_name')) {
            $html .= '<label class="visually-hidden" for="' . $id . 'n">' . e(__('newsletter.name')) . '</label><input class="form-control" type="text" id="' . $id . 'n" name="name" maxlength="80" autocomplete="given-name" placeholder="' . e(__('newsletter.name')) . '" value="' . e($mine ? (string) old('name', '') : '') . '">';
        }
        $html .= '<label class="visually-hidden" for="' . $id . 'e">' . e(__('newsletter.email')) . '</label><input class="form-control' . ($errors ? ' is-invalid' : '') . '" type="email" id="' . $id . 'e" name="email" required maxlength="190" autocomplete="email" placeholder="' . e(__('newsletter.email')) . '" value="' . e($mine ? (string) old('email', '') : '') . '"' . ($errors ? ' aria-describedby="' . $id . 'x"' : '') . '>'
            . '<button class="btn ap-news-btn" type="submit">' . e((string) setting('newsletter.button')) . '</button></div>';
        if ($errors) {
            $html .= '<div class="invalid-feedback d-block" id="' . $id . 'x">' . e(implode(' ', $errors)) . '</div>';
        }
        if (setting('newsletter.tick')) {
            $html .= '<div class="form-check mt-2"><input class="form-check-input" type="checkbox" name="agree" value="1" id="' . $id . 'a" required><label class="form-check-label small" for="' . $id . 'a">' . e(__('newsletter.agree')) . '</label></div>';
        }
        if (($c = trim((string) setting('newsletter.consent'))) !== '') {
            $html .= '<p class="ap-news-consent">' . e($c) . '</p>';
        }
        $html .= class_exists(Spam::class) ? Spam::fields('newsletter') : '';
        return $html . '</form></div>';
    }

    /** @return array{ok:bool, error:string, status:string} */
    public static function receive(Request $request): array
    {
        $email = mb_strtolower(trim($request->string('email')));
        $name = mb_substr(trim($request->string('name')), 0, 80);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) {
            return ['ok' => false, 'error' => __('newsletter.bad_email'), 'status' => 'invalid'];
        }
        if (setting('newsletter.tick') && $request->string('agree') !== '1') {
            return ['ok' => false, 'error' => __('newsletter.tick'), 'status' => 'invalid'];
        }
        $spamOn = class_exists(Spam::class) && app(\AP\Core\ModuleManager::class)->isEnabled('spam');
        $verdict = $spamOn ? Spam::check($request, 'newsletter', [$name], $email) : ['verdict' => \AP\Core\Captcha::passes($request) ? 'ok' : 'blocked', 'reasons' => []];
        if ($verdict['verdict'] === 'blocked') {
            return ['ok' => false, 'error' => __('newsletter.blocked'), 'status' => 'blocked'];
        }
        if ($verdict['verdict'] === 'held') {
            // Looks like a robot: say thank you, keep nothing.
            return ['ok' => true, 'error' => '', 'status' => 'held'];
        }
        $page = $request->string('page');
        $now = gmdate('Y-m-d H:i:s');
        $row = self::db()->table('newsletter_subscribers')->where('email', $email)->first();
        if ($row) {
            if ($row['status'] !== 'subscribed') {
                self::db()->table('newsletter_subscribers')->where('id', (int) $row['id'])->update(['status' => 'subscribed', 'left_at' => null, 'created_at' => $now, 'name' => $name !== '' ? $name : $row['name'],
                    'consent' => mb_substr((string) setting('newsletter.consent'), 0, 300) ?: null]);
                self::notify($email, $name);
                return ['ok' => true, 'error' => '', 'status' => 'back'];
            }
            return ['ok' => true, 'error' => '', 'status' => 'already'];
        }
        self::db()->table('newsletter_subscribers')->insert(['email' => $email, 'name' => $name !== '' ? $name : null, 'status' => 'subscribed',
            'source' => str_starts_with($page, base_url()) ? mb_substr($page, 0, 500) : null, 'consent' => mb_substr((string) setting('newsletter.consent'), 0, 300) ?: null,
            'ip' => $request->ip(), 'token' => bin2hex(random_bytes(16)), 'created_at' => $now]);
        self::notify($email, $name);
        event('newsletter.subscribed', $email);
        return ['ok' => true, 'error' => '', 'status' => 'new'];
    }

    private static function notify(string $email, string $name): void
    {
        if (!setting('newsletter.email_me') || !app()->has(Mailer::class) || ($to = trim((string) setting('general.admin_email'))) === '') {
            return;
        }
        try {
            app(Mailer::class)->sendTemplate('newsletter.new', $to, ['email' => $email, 'name' => $name, 'link' => admin_url('subscribers')]);
        } catch (Throwable $e) {
            \AP\Core\Logger::exception($e);
        }
    }

    public static function leaveUrl(array $row): string
    {
        return url('newsletter/leave/' . $row['token']);
    }
}
