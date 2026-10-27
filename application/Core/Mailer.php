<?php
declare(strict_types=1);

namespace AP\Core;

use AP\Core\Mail\BrevoTransport;
use AP\Core\Mail\Message;
use AP\Core\Mail\PhpMailerTransport;
use AP\Core\Mail\SmtpTransport;
use Throwable;

/**
 * Sends email. Ways to send ("drivers"): "smtp" (built-in client), "phpmailer" (bundled PHPMailer, with DKIM),
 * "mail" (the web server's mail()), "brevo" (Brevo HTTP API) and "log" (only writes to the email log).
 * A backup way is tried automatically when the main one fails.
 * Modules register templates under "email_templates" in module.php; admins can edit them.
 */
final class Mailer
{
    public const DRIVERS = [
        'smtp' => 'SMTP server', 'phpmailer' => 'PHPMailer (SMTP)', 'mail' => 'Server mail', 'brevo' => 'Brevo API', 'log' => 'Log only',
    ];

    public function __construct(private DB $db, private ModuleManager $modules)
    {
    }

    // ---------------------------------------------------------------- templates

    /** @return array<string, array<string,mixed>> key => definition (with module) */
    public function definitions(): array
    {
        $out = [];
        foreach ($this->modules->collect('email_templates') as $key => $row) {
            $out[$key] = (array) $row['def'] + ['label' => $key, 'description' => '', 'subject' => '', 'body' => '', 'variables' => [], 'sample' => []];
            $out[$key]['module'] = $row['module'];
        }
        ksort($out);
        return $out;
    }

    /** The template as it will be used: the admin's edits if there are any, otherwise the default. */
    public function template(string $key): ?array
    {
        $def = $this->definitions()[$key] ?? null;
        if ($def === null) {
            return null;
        }
        $row = null;
        try {
            $row = $this->db->table('email_templates')->where('tkey', $key)->first();
        } catch (Throwable) {
        }
        $def['customised'] = $row !== null;
        $def['enabled'] = $row ? (bool) $row['enabled'] : true;
        $def['subject'] = $row ? (string) $row['subject'] : (string) $def['subject'];
        $def['body'] = $row ? (string) $row['body'] : (string) $def['body'];
        return $def;
    }

    /** Variables every template can use. */
    public function globals(): array
    {
        return [
            'site_name' => (string) setting('general.site_name', ''), 'site_url' => url('/'), 'year' => date('Y'),
            'admin_email' => (string) setting('general.admin_email', ''), 'contact_email' => (string) setting('contact.email', ''),
        ];
    }

    /**
     * {{name}} is escaped, {{{name}}} is inserted as-is (for links and HTML the system built itself),
     * and {{#name}}...{{/name}} is only kept when name has a value.
     */
    public static function fill(string $text, array $vars, bool $html): string
    {
        $text = preg_replace_callback('/\{\{#\s*(\w+)\s*\}\}(.*?)\{\{\/\s*\1\s*\}\}/s', static fn($m) => trim((string) ($vars[$m[1]] ?? '')) !== '' ? $m[2] : '', $text) ?? $text;
        $text = preg_replace_callback('/\{\{\{\s*(\w+)\s*\}\}\}/', static fn($m) => (string) ($vars[$m[1]] ?? ''), $text) ?? $text;
        return preg_replace_callback('/\{\{\s*(\w+)\s*\}\}/', static function ($m) use ($vars, $html): string {
            $v = (string) ($vars[$m[1]] ?? '');
            return $html ? htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : $v;
        }, $text) ?? $text;
    }

    /** @return array{subject:string, html:string, text:string}|null */
    public function render(string $key, array $vars = [], ?array $override = null): ?array
    {
        $t = $override ? $override + (array) $this->template($key) : $this->template($key);
        if ($t === null) {
            return null;
        }
        $vars += $this->globals();
        $subject = trim(str_replace(["\r", "\n"], ' ', self::fill((string) $t['subject'], $vars, false)));
        $inner = self::fill((string) $t['body'], $vars, true);
        $plain = new Message();
        $plain->html = $inner;
        return ['subject' => $subject, 'html' => $this->layout($inner, $subject), 'text' => $plain->textBody()];
    }

    /** Wrap content in the standard email layout (brand colour, header, footer). */
    public function layout(string $innerHtml, string $title = ''): string
    {
        $brand = preg_match('/^#[0-9a-fA-F]{6}$/', (string) setting('email.brand_color', '#0d6efd')) ? (string) setting('email.brand_color', '#0d6efd') : '#0d6efd';
        $site = htmlspecialchars((string) setting('general.site_name', ''), ENT_QUOTES, 'UTF-8');
        $logo = trim((string) setting('email.logo_url', '')) ?: trim((string) setting('admin.login_logo', ''));
        if ($logo !== '' && str_starts_with($logo, '/')) {
            $logo = url($logo);
        }
        $solid = (string) setting('email.header_style', 'light') === 'colour';
        $head = preg_match('#^https?://#i', $logo)
            ? '<img src="' . htmlspecialchars($logo, ENT_QUOTES, 'UTF-8') . '" alt="' . $site . '" height="46" style="display:block;margin:0 auto;max-height:46px;max-width:240px;width:auto;border:0;outline:none">'
            : '<span style="font-size:22px;font-weight:700;letter-spacing:-.2px;color:' . ($solid ? '#ffffff' : '#111827') . '">' . $site . '</span>';
        $button = preg_match('/^#[0-9a-fA-F]{6}$/', (string) setting('email.button_color', '#0d6efd')) ? (string) setting('email.button_color', '#0d6efd') : '#0d6efd';
        // Links get the button colour; links marked class="button" become real buttons.
        $innerHtml = (string) preg_replace_callback('/<a\b([^>]*)>/i', static function (array $m) use ($button): string {
            $attrs = $m[1];
            if (stripos($attrs, 'style=') !== false) {
                return $m[0];
            }
            $style = preg_match('/class="[^"]*\bbutton\b/i', $attrs)
                ? 'display:inline-block;background:' . $button . ';color:#ffffff;padding:13px 28px;border-radius:999px;text-decoration:none;font-weight:600;font-size:15px'
                : 'color:' . $button;
            return '<a' . $attrs . ' style="' . $style . '">';
        }, $innerHtml);
        $footer = self::fill((string) setting('email.footer_text', ''), $this->globals(), true);
        $font = "-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif";
        return '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width"><meta name="color-scheme" content="light"><title>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</title></head>'
            . '<body style="margin:0;padding:0;background:#f4f5f9;font-family:' . $font . ';color:#1f2937;-webkit-text-size-adjust:100%">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f5f9"><tr><td align="center" style="padding:32px 12px">'
            . '<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:16px;overflow:hidden;border:1px solid #e8eaf2">'
            . '<tr><td style="height:5px;line-height:5px;font-size:0;background:' . $brand . '">&nbsp;</td></tr>'
            . '<tr><td align="center" style="padding:30px 32px 22px;' . ($solid ? 'background:' . $brand . ';' : 'border-bottom:1px solid #eef0f6;') . '">' . $head . '</td></tr>'
            . '<tr><td style="padding:32px 36px 36px;font-size:15px;line-height:1.65;color:#374151">' . $innerHtml . '</td></tr>'
            . ($footer !== '' ? '<tr><td align="center" style="padding:20px 32px 26px;background:#fafbfd;border-top:1px solid #eef0f6;font-size:12px;line-height:1.6;color:#8a91a5">' . nl2br($footer) . '</td></tr>' : '')
            . '</table></td></tr></table></body></html>';
    }

    // ---------------------------------------------------------------- sending

    public function fromAddress(): array
    {
        $addr = trim((string) setting('email.from_address', ''));
        if ($addr === '' || !filter_var($addr, FILTER_VALIDATE_EMAIL)) {
            $host = preg_replace('/:\d+$/', '', (string) (app(Request::class)->host() ?? 'localhost'));
            $addr = 'noreply@' . (str_contains($host, '.') ? preg_replace('/^www\./', '', $host) : 'localhost.localdomain');
        }
        $name = trim((string) setting('email.from_name', '')) ?: (string) setting('general.site_name', '');
        return [$addr, $name];
    }

    private function domain(): string
    {
        return substr(strrchr($this->fromAddress()[0], '@') ?: '@localhost', 1);
    }

    /**
     * Send a template to one or more people.
     * @param string|array $to address, [address => name] or a list
     * @param array{queue?:bool, attachments?:array, reply_to?:string, cc?:string|array, bcc?:string|array, subject?:string} $opts
     * @return true|string true when sent (or queued), otherwise a readable error
     */
    public function sendTemplate(string $key, string|array $to, array $vars = [], array $opts = []): true|string
    {
        $t = $this->template($key);
        if ($t === null) {
            return "Unknown email template \"{$key}\".";
        }
        if (!$t['enabled']) {
            $this->log($key, is_array($to) ? implode(', ', array_map(static fn($k, $v) => is_int($k) ? (string) $v : (string) $k, array_keys($to), $to)) : $to, (string) $t['label'], 'skipped', 'Template is switched off.', null);
            return true;
        }
        $r = $this->render($key, $vars, !empty($opts['subject']) ? ['subject' => (string) $opts['subject']] : null);
        $message = new Message();
        $message->subject = $r['subject'];
        $message->html = $r['html'];
        $message->text = $r['text'];
        try {
            $message->to($to);
            if (!empty($opts['cc'])) {
                $message->cc($opts['cc']);
            }
            if (!empty($opts['bcc'])) {
                $message->bcc($opts['bcc']);
            }
            foreach ((array) ($opts['attachments'] ?? []) as $path => $name) {
                is_int($path) ? $message->attach((string) $name) : $message->attach((string) $path, (string) $name);
            }
            if (!empty($opts['reply_to'])) {
                $message->replyTo((string) $opts['reply_to']);
            }
        } catch (\InvalidArgumentException $e) {
            return $e->getMessage();
        }
        return $this->dispatch($message, $key, $opts['queue'] ?? null);
    }

    /** Send a ready-made message (no template). */
    public function send(Message $message, ?bool $queue = null): true|string
    {
        return $this->dispatch($message, '', $queue);
    }

    private function dispatch(Message $message, string $template, ?bool $queue): true|string
    {
        [$addr, $name] = $this->fromAddress();
        if ($message->from[0] === '') {
            $message->from($addr, $name);
        }
        if (!$message->replyTo && ($rt = trim((string) setting('email.reply_to', ''))) !== '' && filter_var($rt, FILTER_VALIDATE_EMAIL)) {
            $message->replyTo($rt);
        }
        if (($copy = trim((string) setting('email.copy_to', ''))) !== '' && filter_var($copy, FILTER_VALIDATE_EMAIL)) {
            $message->bcc($copy);
        }
        $toText = implode(', ', array_map(static fn($a) => $a[0], $message->to));
        $useQueue = $queue ?? ((bool) setting('email.use_queue', true) && App::instance()->has(Queue::class));
        if ($useQueue && self::driver() !== 'log') {
            $logId = $this->log($template, $toText, $message->subject, 'queued', null, $message);
            try {
                App::instance()->make(Queue::class)->push('mail.send', ['message' => $message->toArray(), 'log_id' => $logId, 'template' => $template], 0, 'mail', max(1, (int) setting('queue.max_attempts', 3)));
                return true;
            } catch (Throwable $e) {
                // Queue unavailable: send directly instead.
                Logger::warning('Mail queue failed, sending directly: ' . $e->getMessage());
            }
        }
        $logId = $this->log($template, $toText, $message->subject, 'sending', null, $message);
        return $this->deliverLogged($message, $logId);
    }

    /** Used by the queue worker. Throws on failure so the job is retried. */
    public function deliverFromJob(array $payload): void
    {
        $message = Message::fromArray((array) ($payload['message'] ?? []));
        $logId = (int) ($payload['log_id'] ?? 0);
        $result = $this->deliverLogged($message, $logId, true);
        if ($result !== true) {
            throw new \RuntimeException($result);
        }
    }

    private function deliverLogged(Message $message, int $logId, bool $fromQueue = false): true|string
    {
        try {
            $driver = $this->deliver($message);
            $this->updateLog($logId, 'sent', null, $driver);
            return true;
        } catch (Throwable $e) {
            $this->updateLog($logId, $fromQueue ? 'retrying' : 'failed', $e->getMessage(), null);
            Logger::warning('Email to ' . ($message->to[0][0] ?? '?') . ' failed: ' . $e->getMessage());
            if (!$fromQueue) {
                try {
                    events()->dispatch('mail.failed', (string) ($message->to[0][0] ?? '?'), $e->getMessage());
                } catch (Throwable) {
                }
            }
            return $e->getMessage();
        }
    }

    /** Hand the message to the chosen way of sending, then to the backup way if that fails. Returns the driver used. */
    private function deliver(Message $message): string
    {
        $driver = self::driver();
        if ($driver === 'log') {
            return 'log';
        }
        try {
            $this->deliverWith($driver, $message);
            return $driver;
        } catch (Throwable $e) {
            $backup = (string) setting('email.backup_driver', '');
            if ($backup === '' || $backup === $driver || !isset(self::DRIVERS[$backup]) || $backup === 'log') {
                throw $e;
            }
            Logger::warning('Email via ' . $driver . ' failed (' . $e->getMessage() . '), trying the backup way: ' . $backup);
            try {
                $this->deliverWith($backup, $message);
            } catch (Throwable $e2) {
                throw new \RuntimeException(self::DRIVERS[$driver] . ': ' . $e->getMessage() . ' Backup (' . self::DRIVERS[$backup] . '): ' . $e2->getMessage());
            }
            return $backup . ' (backup)';
        }
    }

    public static function driver(): string
    {
        $d = (string) setting('email.driver', 'mail');
        return isset(self::DRIVERS[$d]) ? $d : 'mail';
    }

    /** Send with one specific way. Throws a readable error when it fails. */
    public function deliverWith(string $driver, Message $message): void
    {
        switch ($driver) {
            case 'log':
                return;
            case 'smtp':
                $smtp = new SmtpTransport($this->smtpConfig());
                try {
                    $smtp->send($message->from[0], $message->recipients(), $message->raw($this->domain()));
                } finally {
                    $smtp->quit();
                }
                return;
            case 'phpmailer':
                PhpMailerTransport::send($message, $this->smtpConfig(), $this->dkimConfig());
                return;
            case 'brevo':
                BrevoTransport::send($message, (string) setting('email.brevo_key', ''), max(3, (int) setting('email.smtp_timeout', 15)));
                return;
        }
        if (!function_exists('mail')) {
            throw new \RuntimeException('PHP mail() is not available on this server. Use SMTP in the email settings.');
        }
        $built = $message->build($this->domain());
        $headers = $built['headers'];
        $to = $headers['To'] ?? $message->recipients()[0][0];
        $subject = $headers['Subject'];
        unset($headers['To'], $headers['Subject']);
        $headerText = implode("\r\n", array_map(static fn($k, $v) => "{$k}: {$v}", array_keys($headers), $headers));
        $ok = @mail($to, $subject, $built['body'], $headerText, '-f' . self::safeEnvelope($message->from[0]));
        if (!$ok) {
            throw new \RuntimeException('PHP mail() refused the message. Ask your host whether mail() is enabled, or use SMTP in the email settings.');
        }
    }

    /** @return array{domain:string,selector:string,key:string} */
    public function dkimConfig(): array
    {
        return ['domain' => trim((string) setting('email.dkim_domain', '')), 'selector' => trim((string) setting('email.dkim_selector', 'default')), 'key' => trim((string) setting('email.dkim_private_key', ''))];
    }

    /**
     * What the settings page shows at the top: the way of sending, where to, the backup, and whether it is ready.
     * @return array{driver:string,label:string,target:string,backup:string,ready:bool,state:string,problems:list<string>}
     */
    public function status(): array
    {
        $d = self::driver();
        $problems = [];
        $target = '';
        if (in_array($d, ['smtp', 'phpmailer'], true)) {
            $host = trim((string) setting('email.smtp_host', ''));
            $target = $host !== '' ? $host . ':' . (int) setting('email.smtp_port', 587) : '';
            if ($host === '') {
                $problems[] = 'Enter the SMTP server.';
            }
            if ($d === 'phpmailer' && !is_file(AP_ROOT . '/vendor/PHPMailer/PHPMailer.php')) {
                $problems[] = 'The PHPMailer library is missing from application/vendor/PHPMailer.';
            }
        } elseif ($d === 'brevo') {
            $target = 'api.brevo.com';
            if (!settings()->hasValue('email.brevo_key')) {
                $problems[] = 'Save a Brevo API key.';
            }
        } elseif ($d === 'mail') {
            $target = 'this web server';
            if (!function_exists('mail')) {
                $problems[] = 'PHP mail() is switched off on this server.';
            }
        }
        if (trim((string) setting('email.from_address', '')) === '') {
            $problems[] = 'Set a sender email address (now guessed as ' . $this->fromAddress()[0] . ').';
        }
        $backup = (string) setting('email.backup_driver', '');
        if ($backup === 'brevo' && $d !== 'brevo' && !settings()->hasValue('email.brevo_key')) {
            $problems[] = 'The backup way (Brevo) needs an API key.';
        }
        if (in_array($backup, ['smtp', 'phpmailer'], true) && !in_array($d, ['smtp', 'phpmailer'], true) && trim((string) setting('email.smtp_host', '')) === '') {
            $problems[] = 'The backup way needs an SMTP server.';
        }
        return [
            'driver' => $d, 'label' => self::DRIVERS[$d], 'target' => $target,
            'backup' => isset(self::DRIVERS[$backup]) && $backup !== $d ? self::DRIVERS[$backup] : '',
            'ready' => $problems === [] && $d !== 'log', 'state' => $d === 'log' ? 'testing' : ($problems ? 'setup' : 'ready'), 'problems' => $problems,
        ];
    }

    public function smtpConfig(): array
    {
        return [
            'host' => trim((string) setting('email.smtp_host', '')), 'port' => (int) setting('email.smtp_port', 587),
            'encryption' => in_array(setting('email.smtp_encryption', 'tls'), ['tls', 'ssl', 'none'], true) ? (string) setting('email.smtp_encryption', 'tls') : 'tls',
            'username' => (string) setting('email.smtp_username', ''), 'password' => (string) setting('email.smtp_password', ''),
            // Inside a scheduler run, an email server that does not answer may only use the time the run has left.
            'timeout' => max(3, Scheduler::secondsLeft(max(3, (int) setting('email.smtp_timeout', 15)))), 'verify' => (bool) setting('email.smtp_verify_cert', true),
            'helo' => $this->domain() === 'localhost' ? 'localhost' : $this->domain(),
        ];
    }

    /** Connect and log in without sending anything. @return array{ok:bool, message:string, transcript:string[]} */
    public function testConnection(?string $driver = null): array
    {
        $driver ??= self::driver();
        if ($driver === 'brevo') {
            $r = BrevoTransport::test((string) setting('email.brevo_key', ''), max(3, (int) setting('email.smtp_timeout', 15)));
            return $r + ['transcript' => []];
        }
        if ($driver === 'mail') {
            return ['ok' => function_exists('mail'), 'message' => function_exists('mail') ? 'PHP mail() is available. Send a test email to be sure it arrives.' : 'PHP mail() is switched off on this server.', 'transcript' => []];
        }
        if ($driver === 'log') {
            return ['ok' => true, 'message' => 'Log only: nothing is sent, so there is nothing to connect to.', 'transcript' => []];
        }
        $cfg = $this->smtpConfig();
        if ($cfg['host'] === '') {
            return ['ok' => false, 'message' => 'Enter the SMTP server name first.', 'transcript' => []];
        }
        $smtp = new SmtpTransport($cfg);
        try {
            $smtp->connect();
            $smtp->quit();
            return ['ok' => true, 'message' => 'Connected' . ($cfg['username'] !== '' ? ' and logged in' : '') . ' successfully.', 'transcript' => $smtp->transcript()];
        } catch (Throwable $e) {
            $smtp->quit();
            return ['ok' => false, 'message' => $e->getMessage(), 'transcript' => $smtp->transcript()];
        }
    }

    // ---------------------------------------------------------------- log

    private function log(string $template, string $to, string $subject, string $status, ?string $error, ?Message $message): int
    {
        if (!(bool) setting('email.log_enabled', true)) {
            return 0;
        }
        try {
            $body = null;
            if ($message !== null && (bool) setting('email.log_body', true)) {
                $body = mb_substr($message->html !== '' ? $message->html : $message->text, 0, 200000);
            }
            return $this->db->table('mail_log')->insertGetId([
                'template' => $template ?: null, 'to_address' => mb_substr($to, 0, 500), 'subject' => mb_substr($subject, 0, 300), 'status' => $status,
                'error' => $error, 'driver' => self::driver(), 'body' => $body, 'created_at' => gmdate('Y-m-d H:i:s'),
            ]);
        } catch (Throwable $e) {
            Logger::warning('Mail log failed: ' . $e->getMessage());
            return 0;
        }
    }

    private function updateLog(int $id, string $status, ?string $error, ?string $driver): void
    {
        if ($id <= 0) {
            return;
        }
        try {
            $data = ['status' => $status, 'error' => $error === null ? null : mb_substr($error, 0, 1000)];
            if ($driver !== null) {
                $data['driver'] = $driver;
                $data['sent_at'] = gmdate('Y-m-d H:i:s');
            }
            $this->db->table('mail_log')->where('id', $id)->update($data);
        } catch (Throwable) {
        }
    }

    /** Only characters that are safe in an envelope sender argument. */
    private static function safeEnvelope(string $address): string
    {
        return preg_replace('/[^A-Za-z0-9@._+\-]/', '', $address) ?? '';
    }
}
