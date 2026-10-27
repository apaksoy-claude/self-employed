<?php
declare(strict_types=1);

use AP\Core\DB;
use AP\Core\Mailer;
use AP\Core\ModuleManager;

return [
    'name' => 'mail',
    'label' => 'Email',
    'version' => '1.4.7',
    'description' => 'Sends email through SMTP, PHPMailer, server mail or the Brevo API with a backup way, editable email templates, a send log and connection tests.',
    'core' => true,
    'routes' => 'routes.php',
    'permissions' => [
        'mail.manage' => 'See email status and send test emails',
        'mail.templates' => 'Edit email templates',
        'mail.log' => 'See the email log',
    ],
    'menu' => [
        ['label' => 'Email templates', 'icon' => 'fa-file-lines', 'url' => 'mail/templates', 'hub' => 'settings', 'category' => 'Email', 'group' => 'Email', 'order' => 21, 'permission' => 'mail.templates', 'info' => 'Edit the wording of every email the website sends.', 'keywords' => 'emails wording messages'],
        ['label' => 'Email log', 'icon' => 'fa-inbox', 'url' => 'mail/log', 'hub' => 'system', 'category' => 'Logs', 'group' => 'Email', 'order' => 33, 'permission' => 'mail.log', 'info' => 'Every email the website sent or tried to send, with its result.', 'keywords' => 'sent emails history'],
    ],
    'settings' => [
        // Send with
        'email.driver' => ['group' => 'email', 'section' => 'Send with', 'section_icon' => 'fa-paper-plane', 'label' => 'How to send email', 'type' => 'cards', 'default' => 'mail',
            'options' => [
                'smtp' => ['label' => 'SMTP server', 'badge' => 'Recommended', 'help' => 'Sends through your email account, for example Hostinger email. The most reliable choice.'],
                'phpmailer' => ['label' => 'PHPMailer (SMTP)', 'help' => 'Sends through the same SMTP server with the well-known PHPMailer library. Very compatible, and can sign emails with DKIM.'],
                'mail' => ['label' => 'Server mail', 'help' => 'Uses the web server\'s own mail function. No set-up, but emails land in spam more often.'],
                'brevo' => ['label' => 'Brevo API', 'help' => 'Sends through Brevo over the internet. Good for newsletters and high volumes.'],
                'log' => ['label' => 'Log only', 'badge' => 'Testing', 'badge_type' => 'warning', 'help' => 'Writes emails to the email log instead of sending them.'],
            ],
            'rules' => 'required|in:smtp,phpmailer,mail,brevo,log', 'help' => 'SMTP is recommended: use the mailbox details your host or email provider gave you.', 'order' => 10],
        // SMTP server (used by "SMTP server" and "PHPMailer")
        'email.smtp_host' => ['group' => 'email', 'section' => 'SMTP server', 'section_icon' => 'fa-server', 'width' => 'half', 'label' => 'SMTP server', 'type' => 'text', 'default' => '', 'rules' => 'nullable|string|max:190', 'placeholder' => 'smtp.yourdomain.com',
            'help' => 'For example mail.yourdomain.com or smtp.gmail.com.', 'order' => 11, 'show_if' => [['email.driver' => ['smtp', 'phpmailer']], ['email.backup_driver' => ['smtp', 'phpmailer']]],
            'presets_label' => 'Fill in for', 'presets_help' => 'Fills in the server, port and encryption. You still type your own username and password.', 'presets' => [
                'Hostinger' => ['email.smtp_host' => 'smtp.hostinger.com', 'email.smtp_port' => '465', 'email.smtp_encryption' => 'ssl'],
                'Gmail' => ['email.smtp_host' => 'smtp.gmail.com', 'email.smtp_port' => '587', 'email.smtp_encryption' => 'tls'],
                'Microsoft 365' => ['email.smtp_host' => 'smtp.office365.com', 'email.smtp_port' => '587', 'email.smtp_encryption' => 'tls'],
                'Outlook.com' => ['email.smtp_host' => 'smtp-mail.outlook.com', 'email.smtp_port' => '587', 'email.smtp_encryption' => 'tls'],
                'Brevo' => ['email.smtp_host' => 'smtp-relay.brevo.com', 'email.smtp_port' => '587', 'email.smtp_encryption' => 'tls'],
                'Mailgun EU' => ['email.smtp_host' => 'smtp.eu.mailgun.org', 'email.smtp_port' => '587', 'email.smtp_encryption' => 'tls'],
                'SendGrid' => ['email.smtp_host' => 'smtp.sendgrid.net', 'email.smtp_port' => '587', 'email.smtp_encryption' => 'tls', 'email.smtp_username' => 'apikey'],
                'Zoho EU' => ['email.smtp_host' => 'smtp.zoho.eu', 'email.smtp_port' => '465', 'email.smtp_encryption' => 'ssl'],
                'IONOS UK' => ['email.smtp_host' => 'smtp.ionos.co.uk', 'email.smtp_port' => '587', 'email.smtp_encryption' => 'tls'],
                'cPanel host' => ['email.smtp_host' => 'mail.' . (preg_replace('/^www\\./', '', (string) ($_SERVER['HTTP_HOST'] ?? 'yourdomain.com')) ?: 'yourdomain.com'), 'email.smtp_port' => '465', 'email.smtp_encryption' => 'ssl'],
            ]],
        'email.smtp_port' => ['group' => 'email', 'section' => 'SMTP server', 'width' => 'half', 'label' => 'SMTP port', 'type' => 'number', 'default' => 587, 'rules' => 'integer|min:1|max:65535', 'help' => 'Usually 587 (TLS) or 465 (SSL).', 'order' => 12, 'show_if' => [['email.driver' => ['smtp', 'phpmailer']], ['email.backup_driver' => ['smtp', 'phpmailer']]]],
        'email.smtp_encryption' => ['group' => 'email', 'section' => 'SMTP server', 'width' => 'half', 'label' => 'Encryption', 'type' => 'select', 'default' => 'tls', 'options' => ['ssl' => 'SSL (port 465)', 'tls' => 'TLS / STARTTLS (port 587)', 'none' => 'None (port 25, not recommended)'], 'rules' => 'required|in:tls,ssl,none', 'order' => 13, 'show_if' => [['email.driver' => ['smtp', 'phpmailer']], ['email.backup_driver' => ['smtp', 'phpmailer']]]],
        'email.smtp_username' => ['group' => 'email', 'section' => 'SMTP server', 'width' => 'half', 'label' => 'SMTP username', 'type' => 'text', 'default' => '', 'rules' => 'nullable|string|max:190', 'help' => 'Often the full email address. Leave blank if the server needs no login.', 'order' => 14, 'show_if' => [['email.driver' => ['smtp', 'phpmailer']], ['email.backup_driver' => ['smtp', 'phpmailer']]]],
        'email.smtp_password' => ['group' => 'email', 'section' => 'SMTP server', 'width' => 'half', 'label' => 'SMTP password', 'type' => 'password', 'secret' => true, 'default' => '', 'rules' => 'nullable|string|max:300', 'help' => 'Stored encrypted. Leave blank to keep the current password.', 'order' => 15, 'show_if' => [['email.driver' => ['smtp', 'phpmailer']], ['email.backup_driver' => ['smtp', 'phpmailer']]]],
        'email.smtp_verify_cert' => ['group' => 'email', 'section' => 'SMTP server', 'label' => 'Check the server certificate', 'type' => 'checkbox', 'default' => true, 'help' => 'Leave on. Only switch off if your mail server uses a self-signed certificate and you trust it.', 'order' => 16, 'show_if' => [['email.driver' => ['smtp', 'phpmailer']], ['email.backup_driver' => ['smtp', 'phpmailer']]]],
        // Brevo
        'email.brevo_key' => ['group' => 'email', 'section' => 'Brevo API', 'section_icon' => 'fa-cloud', 'label' => 'Brevo API key', 'type' => 'password', 'secret' => true, 'default' => '', 'rules' => 'nullable|string|max:300',
            'help' => 'From app.brevo.com > SMTP & API > API keys. Your sender address must be added and checked in Brevo.', 'order' => 18, 'show_if' => [['email.driver' => ['brevo']], ['email.backup_driver' => ['brevo']]]],
        // DKIM
        'email.dkim_domain' => ['group' => 'email', 'section' => 'DKIM signing (PHPMailer)', 'section_icon' => 'fa-signature', 'section_help' => 'Only needed if your email provider asks you to sign emails yourself. Most hosts sign them for you.',
            'width' => 'half', 'label' => 'Domain', 'type' => 'text', 'default' => '', 'rules' => 'nullable|string|max:190', 'placeholder' => 'yourdomain.co.uk', 'help' => 'Only used with PHPMailer.', 'order' => 19, 'show_if' => [['email.driver' => ['phpmailer']], ['email.backup_driver' => ['phpmailer']]]],
        'email.dkim_selector' => ['group' => 'email', 'section' => 'DKIM signing (PHPMailer)', 'width' => 'half', 'label' => 'Selector', 'type' => 'text', 'default' => 'default', 'rules' => 'nullable|string|max:63', 'placeholder' => 'default',
            'help' => 'The name in front of ._domainkey in your DNS record.', 'order' => 20, 'show_if' => [['email.driver' => ['phpmailer']], ['email.backup_driver' => ['phpmailer']]]],
        'email.dkim_private_key' => ['group' => 'email', 'section' => 'DKIM signing (PHPMailer)', 'label' => 'Private key', 'type' => 'textarea', 'secret' => true, 'default' => '', 'rules' => 'nullable|string|max:8000',
            'placeholder' => "-----BEGIN PRIVATE KEY-----\n…", 'help' => 'Paste the whole key, including the BEGIN and END lines. Stored encrypted.', 'order' => 21, 'show_if' => [['email.driver' => ['phpmailer']], ['email.backup_driver' => ['phpmailer']]]],
        // Backup
        'email.backup_driver' => ['group' => 'email', 'section' => 'Backup way to send', 'section_icon' => 'fa-life-ring', 'width' => 'half', 'label' => 'Backup way to send', 'type' => 'select', 'default' => '',
            'options' => ['' => 'No backup', 'smtp' => 'SMTP server', 'phpmailer' => 'PHPMailer (SMTP)', 'mail' => 'Server mail', 'brevo' => 'Brevo API'], 'rules' => 'nullable|in:smtp,phpmailer,mail,brevo',
            'help' => 'Tried automatically when the main way fails.', 'order' => 22],
        'email.smtp_timeout' => ['group' => 'email', 'section' => 'Backup way to send', 'width' => 'half', 'label' => 'Wait for the server (seconds)', 'type' => 'number', 'default' => 15, 'rules' => 'integer|min:3|max:120',
            'help' => 'How long to wait before giving up and trying the backup.', 'order' => 23],
        // Sender (name, address and reply-to come from the Settings module)
        'email.copy_to' => ['group' => 'email', 'section' => 'Sender', 'width' => 'half', 'label' => 'Send a hidden copy of every email to', 'type' => 'email', 'default' => '', 'rules' => 'nullable|email', 'help' => 'Handy for keeping a record. Leave blank for none.', 'order' => 33],
        // Queue
        'email.use_queue' => ['group' => 'email', 'section' => 'Sending queue', 'section_icon' => 'fa-list-check', 'label' => 'Send in the background', 'type' => 'checkbox', 'default' => true, 'help' => 'Pages load faster and failed emails are retried automatically. Needs the scheduler (or "Immediately" mode under Background jobs).', 'order' => 40],
        // Look
        'email.brand_color' => ['group' => 'email', 'section' => 'Look of emails', 'section_icon' => 'fa-palette', 'width' => 'half', 'label' => 'Email header colour', 'type' => 'color', 'default' => '#0d6efd', 'rules' => 'required', 'help' => 'The strip at the top of every email, behind the logo.', 'order' => 50],
        'email.button_color' => ['group' => 'email', 'section' => 'Look of emails', 'width' => 'half', 'label' => 'Button and link colour', 'type' => 'color', 'default' => '#0d6efd', 'rules' => 'required', 'help' => 'Links in emails, and buttons (links with class="button").', 'order' => 51],
        'email.header_style' => ['group' => 'email', 'section' => 'Look of emails', 'width' => 'half', 'label' => 'Header style', 'type' => 'segmented', 'default' => 'light',
            'options' => ['light' => 'Light (logo on white)', 'colour' => 'Coloured strip'], 'rules' => 'required|in:light,colour', 'help' => 'Light puts the logo on white with a thin colour line above. Coloured fills the header with the header colour.', 'order' => 49.5],
        'email.logo_url' => ['group' => 'email', 'section' => 'Look of emails', 'label' => 'Email logo', 'type' => 'image', 'default' => '', 'rules' => 'nullable|string|max:500', 'help' => 'Upload or choose a logo, or paste a full https:// address. Leave blank to show the site name instead. Around 300 pixels wide works best.', 'order' => 52],
        'email.footer_text' => ['group' => 'email', 'section' => 'Look of emails', 'label' => 'Email footer text', 'type' => 'textarea', 'default' => '{{site_name}} · {{site_url}}', 'rules' => 'nullable|string|max:1000', 'help' => 'Shown at the bottom of every email. You can use {{site_name}}, {{site_url}} and {{year}}.', 'order' => 53],
        // Log
        'email.log_enabled' => ['group' => 'email', 'section' => 'Email log', 'section_icon' => 'fa-inbox', 'label' => 'Keep an email log', 'type' => 'checkbox', 'default' => true, 'order' => 60],
        'email.log_body' => ['group' => 'email', 'section' => 'Email log', 'label' => 'Keep the message text in the log', 'type' => 'checkbox', 'default' => true, 'help' => 'Lets you read what was sent. Turn off if emails contain private details.', 'order' => 61],
        'email.log_days' => ['group' => 'email', 'section' => 'Email log', 'width' => 'half', 'label' => 'Keep log entries for (days)', 'type' => 'number', 'default' => 60, 'rules' => 'integer|min:1|max:3650', 'order' => 62],
    ],
    'email_templates' => [
        'mail.test' => [
            'label' => 'Test email', 'description' => 'Sent when you press "Send test email".',
            'subject' => 'Test email from {{site_name}}',
            'body' => "<h2 style=\"margin-top:0\">It works!</h2>\n<p>Hello {{name}},</p>\n<p>This is a test email from <strong>{{site_name}}</strong>. If you can read this, your email settings are correct.</p>",
            'variables' => ['name' => 'Name of the person receiving the test'], 'sample' => ['name' => 'Alex'],
        ],
    ],
    'jobs' => [
        'mail.send' => static function (array $payload): void {
            app(Mailer::class)->deliverFromJob($payload);
        },
    ],
    'schedule' => [
        'mail.log_prune' => ['label' => 'Remove old email log entries', 'description' => 'Deletes email log entries older than the retention time in email settings.', 'cron' => '10 4 * * *',
            'handler' => static function (): string {
                $n = app(DB::class)->table('mail_log')->where('created_at', '<', gmdate('Y-m-d H:i:s', time() - 86400 * max(1, (int) setting('email.log_days', 60))))->delete();
                return "{$n} email log entr" . ($n === 1 ? 'y' : 'ies') . ' removed.';
            }],
    ],
    'widgets' => [
        ['id' => 'mail.status', 'title' => 'Email', 'icon' => 'fa-envelope', 'order' => 27, 'permission' => 'mail.manage',
            'render' => static function (): string {
                $db = app(DB::class);
                $day = gmdate('Y-m-d H:i:s', time() - 86400);
                $sent = $db->table('mail_log')->where('status', 'sent')->where('created_at', '>', $day)->count();
                $failed = $db->table('mail_log')->where('status', 'failed')->where('created_at', '>', $day)->count();
                $driver = ['mail' => 'PHP mail()', 'smtp' => 'SMTP', 'log' => 'Test mode (not sending)'][(string) setting('email.driver', 'mail')] ?? '';
                return '<div class="small text-muted mb-2">' . e($driver) . '</div><div class="d-flex gap-4"><div><div class="h3 mb-0">' . $sent . '</div><div class="small text-muted">sent today</div></div>'
                    . '<div><div class="h3 mb-0 ' . ($failed ? 'text-danger' : '') . '">' . $failed . '</div><div class="small text-muted">failed</div></div></div>'
                    . '<a class="btn btn-sm btn-outline-primary mt-3" href="' . e(admin_url('settings/email')) . '">Email settings</a>';
            }],
    ],
    'boot' => static function ($app): void {
        $app->bind(Mailer::class, static fn($a) => new Mailer($a->make(DB::class), $a->make(ModuleManager::class)));
        // Email settings page: a status line at the top, a test box and log numbers on the right.
        \AP\Core\View::inject('settings.top.email', static function (): string {
            try {
                return \AP\Core\View::render('mail::settings.top', ['status' => app(Mailer::class)->status(), 'result' => session()->flashed('mail_test')]);
            } catch (\Throwable) {
                return '';
            }
        });
        \AP\Core\View::inject('settings.aside.email', static function (): string {
            try {
                $db = app(DB::class);
                $since = gmdate('Y-m-d H:i:s', time() - 7 * 86400);
                $stats = ['sent' => 0, 'waiting' => 0, 'failed' => 0];
                foreach ($db->table('mail_log')->select('status')->where('created_at', '>', $since)->get() as $r) {
                    $k = match ($r['status']) { 'sent' => 'sent', 'failed' => 'failed', 'queued', 'sending', 'retrying' => 'waiting', default => null };
                    if ($k) {
                        $stats[$k]++;
                    }
                }
                return \AP\Core\View::render('mail::settings.aside', ['stats' => $stats]);
            } catch (\Throwable) {
                return '';
            }
        });
    },
    'actions' => [
        'mail.test' => ['label' => 'Send a test email', 'icon' => 'fa-paper-plane', 'url' => 'settings/email', 'permission' => 'mail.manage', 'keywords' => 'test email smtp check', 'info' => 'Email settings'],
    ],
    'search' => [
        'mail_log' => ['label' => 'Email log', 'icon' => 'fa-inbox', 'permission' => 'mail.log', 'limit' => 5,
            'handler' => static function (string $term, int $limit): array {
                $rows = app(\AP\Core\DB::class)->table('mail_log')->search(['to_address', 'subject'], $term)->orderBy('id', 'desc')->limit($limit)->get();
                return array_map(static fn($m) => ['title' => (string) ($m['subject'] ?: '(no subject)'), 'subtitle' => $m['to_address'] . ' · ' . $m['status'] . ' · ' . format_date((string) $m['created_at']),
                    'url' => admin_url('mail/log/' . (int) $m['id']), 'icon' => $m['status'] === 'failed' ? 'fa-circle-xmark' : 'fa-envelope', 'score' => 3], $rows);
            }],
        'email_templates' => ['label' => 'Email templates', 'icon' => 'fa-file-lines', 'permission' => 'mail.templates', 'limit' => 5,
            'handler' => static function (string $term, int $limit): array {
                $out = [];
                foreach (app(\AP\Core\ModuleManager::class)->collect('email_templates') as $key => ['def' => $t]) {
                    $t = (array) $t;
                    $hay = mb_strtolower(($t['label'] ?? $key) . ' ' . ($t['description'] ?? '') . ' ' . ($t['subject'] ?? ''));
                    if (str_contains($hay, mb_strtolower($term))) {
                        $out[] = ['title' => (string) ($t['label'] ?? $key), 'subtitle' => 'Email template · ' . ($t['description'] ?? ''), 'url' => admin_url('mail/templates/' . $key), 'score' => 4];
                    }
                }
                return array_slice($out, 0, $limit);
            }],
    ],
];
