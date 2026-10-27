<?php
declare(strict_types=1);

namespace AP\Modules\Contact;

use AP\Core\DB;
use AP\Core\Kernel;
use AP\Core\Mailer;
use AP\Core\Request;
use AP\Modules\Spam\Spam;
use Throwable;

/** The contact form: shown wherever a page says [contact-form], checked for spam, kept on the Messages page and emailed. */
final class Contact
{
    public static function db(): DB
    {
        /** @var DB $db */
        $db = app(DB::class);
        return $db;
    }

    public static function ready(): bool
    {
        try {
            return Kernel::schemaCurrent() && self::db()->hasTable('contact_messages');
        } catch (Throwable) {
            return false;
        }
    }

    public static function boot(): void
    {
        events()->addFilter('pages.content', static fn(string $html): string => str_contains($html, '[contact-form]')
            ? (string) preg_replace('#(<p>\s*)?\[contact-form\](\s*</p>)?#', self::form(), $html, 1) : $html);
        events()->addFilter('editor.inserts', static fn(array $list): array => [...$list, ['label' => 'Insert the contact form', 'icon' => 'fa-envelope-open-text', 'html' => '<p>[contact-form]</p>']]);
    }

    public static function unread(): int
    {
        return self::ready() ? self::db()->table('contact_messages')->where('status', 'new')->count() : 0;
    }

    public static function to(): string
    {
        foreach (['contact_form.to', 'contact.email', 'general.admin_email'] as $k) {
            $v = trim((string) setting($k, ''));
            if ($v !== '' && filter_var($v, FILTER_VALIDATE_EMAIL)) {
                return $v;
            }
        }
        return '';
    }

    /** @return list<string> */
    public static function subjects(): array
    {
        return array_values(array_filter(array_map(static fn($r) => trim((string) (is_array($r) ? ($r['label'] ?? '') : $r)), (array) setting('contact_form.subjects', []))));
    }

    public static function form(): string
    {
        if (!self::ready()) {
            return '';
        }
        $sent = session()->flashed('contact_sent');
        $err = static function (string $k): string {
            $o = '';
            foreach (form_errors($k) as $m) {
                $o .= '<div class="invalid-feedback d-block">' . e($m) . '</div>';
            }
            return $o;
        };
        $inv = static fn(string $k): string => form_errors($k) ? ' is-invalid' : '';
        $opt = ' <span class="ap-cf-opt">(' . e(__('contact.optional')) . ')</span>';
        $html = '<div class="ap-contact" id="contact-form">';
        if ($sent) {
            return $html . '<div class="ap-cf-thanks" role="status"><i class="fa-solid fa-circle-check" aria-hidden="true"></i><p>' . e((string) setting('contact_form.thanks')) . '</p></div></div>';
        }
        if (($general = form_errors('form')) !== []) {
            $html .= '<div class="alert alert-danger" role="alert">' . e($general[0]) . '</div>';
        }
        $html .= '<form method="post" action="' . e(url('contact/send')) . '#contact-form" class="ap-cf" novalidate>' . csrf_field()
            . '<input type="hidden" name="page" value="' . e(url(ltrim((string) request()->path, '/'))) . '">'
            . '<div class="row g-3">'
            . '<div class="col-md-6"><label class="form-label" for="cfName">' . e(__('contact.name')) . '</label><input class="form-control' . $inv('name') . '" id="cfName" name="name" value="' . e((string) old('name', '')) . '" required maxlength="120" autocomplete="name">' . $err('name') . '</div>'
            . '<div class="col-md-6"><label class="form-label" for="cfEmail">' . e(__('contact.email')) . '</label><input type="email" class="form-control' . $inv('email') . '" id="cfEmail" name="email" value="' . e((string) old('email', '')) . '" required maxlength="190" autocomplete="email">' . $err('email') . '</div>';
        $phone = (string) setting('contact_form.phone', 'off');
        if ($phone !== 'off') {
            $html .= '<div class="col-md-6"><label class="form-label" for="cfPhone">' . e(__('contact.phone')) . ($phone === 'optional' ? $opt : '') . '</label><input type="tel" class="form-control' . $inv('phone') . '" id="cfPhone" name="phone" value="' . e((string) old('phone', '')) . '" maxlength="40" autocomplete="tel">' . $err('phone') . '</div>';
        }
        $subject = (string) setting('contact_form.subject', 'optional');
        if ($subject !== 'off') {
            $choices = self::subjects();
            $html .= '<div class="col-md-' . ($phone !== 'off' ? '6' : '12') . '"><label class="form-label" for="cfSubject">' . e(__('contact.subject')) . ($subject === 'optional' ? $opt : '') . '</label>';
            if ($choices) {
                $html .= '<select class="form-select' . $inv('subject') . '" id="cfSubject" name="subject"><option value="">—</option>';
                foreach ($choices as $c) {
                    $html .= '<option' . ((string) old('subject', '') === $c ? ' selected' : '') . '>' . e($c) . '</option>';
                }
                $html .= '</select>';
            } else {
                $html .= '<input class="form-control' . $inv('subject') . '" id="cfSubject" name="subject" value="' . e((string) old('subject', '')) . '" maxlength="190">';
            }
            $html .= $err('subject') . '</div>';
        }
        $html .= '<div class="col-12"><label class="form-label" for="cfMessage">' . e(__('contact.message')) . '</label><textarea class="form-control' . $inv('message') . '" id="cfMessage" name="message" rows="6" required maxlength="5000">' . e((string) old('message', '')) . '</textarea>' . $err('message') . '</div>';
        if (setting('contact_form.consent', false)) {
            $html .= '<div class="col-12"><div class="form-check"><input class="form-check-input' . $inv('consent') . '" type="checkbox" id="cfConsent" name="consent" value="1"' . (old('consent') ? ' checked' : '') . '><label class="form-check-label" for="cfConsent">' . e((string) setting('contact_form.consent_text')) . '</label>' . $err('consent') . '</div></div>';
        }
        $html .= '</div>' . (class_exists(Spam::class) ? Spam::fields('contact') : \AP\Core\Captcha::widget('forms'))
            . '<button class="btn btn-dark mt-3" type="submit"><i class="fa-regular fa-paper-plane me-1" aria-hidden="true"></i>' . e((string) setting('contact_form.button')) . '</button></form>';
        return $html . '</div>';
    }

    /**
     * Checks and keeps one message. @return array{ok:bool, errors:array<string,list<string>>, status:string}
     */
    public static function receive(Request $request): array
    {
        $d = [
            'name' => trim($request->string('name')), 'email' => mb_strtolower(trim($request->string('email'))), 'phone' => trim($request->string('phone')),
            'subject' => trim($request->string('subject')), 'message' => trim(str_replace("\r\n", "\n", (string) $request->input('message', ''))),
        ];
        $errors = [];
        if ($d['name'] === '' || mb_strlen($d['name']) > 120) {
            $errors['name'][] = 'Please type your name.';
        }
        if (!filter_var($d['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($d['email']) > 190) {
            $errors['email'][] = 'Please type a valid email address so we can answer.';
        }
        $phone = (string) setting('contact_form.phone', 'off');
        if ($phone === 'off') {
            $d['phone'] = '';
        } elseif (($phone === 'required' && $d['phone'] === '') || ($d['phone'] !== '' && !preg_match('/^[0-9 +().\-]{6,40}$/', $d['phone']))) {
            $errors['phone'][] = 'Please type a phone number.';
        }
        $subject = (string) setting('contact_form.subject', 'optional');
        if ($subject === 'off') {
            $d['subject'] = '';
        } elseif ($subject === 'required' && $d['subject'] === '') {
            $errors['subject'][] = 'Please choose or type a subject.';
        } elseif (($choices = self::subjects()) && $d['subject'] !== '' && !in_array($d['subject'], $choices, true)) {
            $errors['subject'][] = 'Please choose a subject from the list.';
        }
        $d['subject'] = mb_substr($d['subject'], 0, 190);
        if (mb_strlen($d['message']) < 2 || mb_strlen($d['message']) > 5000) {
            $errors['message'][] = 'Please write your message (up to 5,000 letters).';
        }
        if (setting('contact_form.consent', false) && $request->string('consent') !== '1') {
            $errors['consent'][] = 'Please tick the box to agree.';
        }
        if ($errors) {
            return ['ok' => false, 'errors' => $errors, 'status' => 'invalid'];
        }
        $verdict = class_exists(Spam::class) && app(\AP\Core\ModuleManager::class)->isEnabled('spam')
            ? Spam::check($request, 'contact', [$d['subject'], $d['message'], $d['name']], $d['email'])
            : ['verdict' => \AP\Core\Captcha::passes($request) ? 'ok' : 'blocked', 'reasons' => ['robot check']];
        if ($verdict['verdict'] === 'blocked') {
            return ['ok' => false, 'errors' => ['form' => [__('contact.blocked')]], 'status' => 'blocked'];
        }
        $page = $request->string('page');
        $status = $verdict['verdict'] === 'held' ? 'spam' : 'new';
        $id = self::db()->table('contact_messages')->insertGetId($d + [
            'phone' => $d['phone'] ?: null, 'status' => $status, 'spam_reason' => $status === 'spam' ? mb_substr(implode(', ', $verdict['reasons']), 0, 300) : null,
            'page_url' => str_starts_with($page, base_url()) ? mb_substr($page, 0, 500) : null, 'ip' => $request->ip(), 'user_agent' => mb_substr($request->userAgent(), 0, 255),
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);
        if ($status === 'new') {
            self::notify($id);
        }
        event('contact.received', $id, $status);
        return ['ok' => true, 'errors' => [], 'status' => $status];
    }

    public static function notify(int $id): void
    {
        $m = self::db()->table('contact_messages')->where('id', $id)->first();
        if (!$m || !app()->has(Mailer::class)) {
            return;
        }
        $vars = ['name' => (string) $m['name'], 'email' => (string) $m['email'], 'phone' => (string) ($m['phone'] ?? ''), 'subject' => (string) ($m['subject'] ?? ''),
            'message' => (string) $m['message'], 'page' => (string) ($m['page_url'] ?? url('/'))];
        try {
            if (setting('contact_form.email_me', true) && ($to = self::to()) !== '') {
                app(Mailer::class)->sendTemplate('contact.message', $to, $vars, ['reply_to' => (string) $m['email']]);
            }
            if (setting('contact_form.autoreply', false)) {
                app(Mailer::class)->sendTemplate('contact.autoreply', [(string) $m['email'] => (string) $m['name']], ['name' => explode(' ', (string) $m['name'])[0], 'message' => (string) $m['message']]);
            }
        } catch (Throwable $e) {
            \AP\Core\Logger::exception($e);
        }
    }

    public static function widget(): string
    {
        if (!self::ready()) {
            return '<p class="text-muted small mb-0">Press "Finish update" to start receiving messages.</p>';
        }
        $new = self::unread();
        $latest = self::db()->table('contact_messages')->where('status', '!=', 'spam')->orderBy('id', 'desc')->limit(3)->get();
        $html = '<div class="d-flex align-items-baseline gap-2 mb-2"><span class="fs-3 fw-semibold">' . $new . '</span><span class="text-muted small">new message' . ($new === 1 ? '' : 's') . '</span></div>';
        foreach ($latest as $m) {
            $html .= '<a class="d-block small text-decoration-none py-1 border-top" href="' . e(admin_url('messages/' . (int) $m['id'])) . '"><strong>' . e((string) $m['name']) . '</strong> '
                . '<span class="text-muted">' . e(mb_strimwidth((string) ($m['subject'] ?: $m['message']), 0, 60, '…')) . '</span></a>';
        }
        return $html . '<a class="btn btn-sm btn-outline-primary mt-2" href="' . e(admin_url('messages')) . '">Open Messages</a>';
    }
}
