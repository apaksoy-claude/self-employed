<?php
declare(strict_types=1);

use AP\Modules\Contact\Contact;

return [
    'name' => 'contact',
    'label' => 'Contact form',
    'version' => '1.4.3',
    'description' => 'A contact form for any page (type [contact-form] or use the editor button), with spam protection, emails to you and a Messages page.',
    'icon' => 'fa-envelope-open-text',
    'kept' => static fn(): string => ($n = app(\AP\Core\DB::class)->table('contact_messages')->count()) ? $n . ' message(s) are kept.' : '',
    'default_enabled' => true,
    'routes' => 'routes.php',
    'category' => 'Tools',
    'when_off' => 'The contact form disappears from pages and the Messages page is hidden. Messages already received are kept.',
    'permissions' => [
        'contact.messages' => 'Read and answer messages sent with the contact form',
    ],
    'menu' => [
        ['label' => 'Messages', 'icon' => 'fa-envelope-open-text', 'url' => 'messages', 'group' => 'Main', 'order' => 5, 'permission' => 'contact.messages',
            'info' => 'Messages visitors sent with the contact form.', 'keywords' => 'contact form inbox enquiries messages', 'badge' => static fn(): int => Contact::unread()],
    ],
    'setting_groups' => [
        'contact_form' => ['label' => 'Contact form', 'icon' => 'fa-envelope-open-text', 'category' => 'Site', 'order' => 2.5,
            'description' => 'Who gets the messages, which boxes the form has and what visitors see after sending.', 'keywords' => 'contact form enquiry email message auto reply',
            'tips' => ['Put the form on any page: type [contact-form] in the page, or press the envelope button in the editor.', 'Spam rules are in Content > Spam; the robot check is in Settings > Security.']],
    ],
    'settings' => [
        'contact_form.to' => ['group' => 'contact_form', 'section' => 'Where messages go', 'section_icon' => 'fa-inbox', 'width' => 'half', 'label' => 'Send messages to', 'type' => 'email', 'default' => '', 'rules' => 'nullable|email',
            'help' => 'Empty: the public email in Contact details, or the site owner email.', 'order' => 1],
        'contact_form.email_me' => ['group' => 'contact_form', 'section' => 'Where messages go', 'label' => 'Email me each message', 'type' => 'checkbox', 'default' => true,
            'help' => 'Messages are always kept on the Messages page too. Reply in your email and it goes straight to the visitor.', 'order' => 2],
        'contact_form.phone' => ['group' => 'contact_form', 'section' => 'The form', 'section_icon' => 'fa-list-check', 'width' => 'half', 'label' => 'Phone number box', 'type' => 'segmented', 'default' => 'off',
            'options' => ['off' => 'No', 'optional' => 'Optional', 'required' => 'Required'], 'rules' => 'required|in:off,optional,required', 'order' => 10],
        'contact_form.subject' => ['group' => 'contact_form', 'section' => 'The form', 'width' => 'half', 'label' => 'Subject box', 'type' => 'segmented', 'default' => 'optional',
            'options' => ['off' => 'No', 'optional' => 'Optional', 'required' => 'Required'], 'rules' => 'required|in:off,optional,required', 'order' => 11],
        'contact_form.subjects' => ['group' => 'contact_form', 'section' => 'The form', 'label' => 'Choices for the subject', 'type' => 'repeater', 'default' => [],
            'add_label' => 'Add a choice', 'max_rows' => 20, 'fields' => ['label' => ['label' => 'Choice', 'placeholder' => 'A question about an order']],
            'help' => 'With choices the subject becomes a drop-down list. Empty: visitors type their own subject.', 'order' => 12],
        'contact_form.consent' => ['group' => 'contact_form', 'section' => 'The form', 'label' => 'Ask visitors to agree to how their details are used', 'type' => 'checkbox', 'default' => false, 'order' => 13],
        'contact_form.consent_text' => ['group' => 'contact_form', 'section' => 'The form', 'label' => 'Text next to the tick box', 'type' => 'text',
            'default' => 'I agree that my details are used to answer my message.', 'rules' => 'required|string|max:300', 'show_if' => ['contact_form.consent' => '1'], 'order' => 14],
        'contact_form.button' => ['group' => 'contact_form', 'section' => 'The form', 'width' => 'half', 'label' => 'Button text', 'type' => 'text', 'default' => 'Send message', 'rules' => 'required|string|max:40', 'order' => 15],
        'contact_form.thanks' => ['group' => 'contact_form', 'section' => 'After sending', 'section_icon' => 'fa-circle-check', 'label' => 'Thank-you message', 'type' => 'textarea', 'rows' => 2,
            'default' => 'Thank you! Your message has been sent. We will get back to you soon.', 'rules' => 'required|string|max:500', 'order' => 20],
        'contact_form.autoreply' => ['group' => 'contact_form', 'section' => 'After sending', 'label' => 'Send the visitor a copy ("We got your message")', 'type' => 'checkbox', 'default' => false,
            'help' => 'The wording is in Email Templates > Contact form: copy for the visitor.', 'order' => 21],
    ],
    'email_templates' => [
        'contact.message' => [
            'label' => 'Contact form: new message', 'description' => 'Sent to you when a visitor uses the contact form.',
            'subject' => 'New message from {{name}}{{#subject}}: {{subject}}{{/subject}}',
            'body' => "<h2 style=\"margin-top:0\">New message from the website</h2>\n<p><strong>{{name}}</strong> &lt;{{email}}&gt;{{#phone}}<br>Phone: {{phone}}{{/phone}}</p>\n{{#subject}}<p><strong>Subject:</strong> {{subject}}</p>{{/subject}}\n<div style=\"background:#F5F6FA;border-radius:8px;padding:14px 16px;white-space:pre-wrap\">{{message}}</div>\n<p style=\"font-size:13px;color:#6b7280\">Reply to this email to answer {{name}}. Sent from {{page}}.</p>",
            'variables' => ['name' => 'Visitor name', 'email' => 'Visitor email', 'phone' => 'Phone (if asked)', 'subject' => 'Subject (if asked)', 'message' => 'The message', 'page' => 'Page the form was on'],
            'sample' => ['name' => 'Ayse Demir', 'email' => 'ayse@example.com', 'phone' => '07700 900123', 'subject' => 'Opening hours', 'message' => "Hello,\nAre you open on Sunday?", 'page' => 'https://example.com/contact'],
        ],
        'contact.autoreply' => [
            'label' => 'Contact form: copy for the visitor', 'description' => 'Sent to the visitor when "Send the visitor a copy" is on.',
            'subject' => 'We got your message',
            'body' => "<h2 style=\"margin-top:0\">Thank you, {{name}}</h2>\n<p>We have received your message and will get back to you soon.</p>\n<div style=\"background:#F5F6FA;border-radius:8px;padding:14px 16px;white-space:pre-wrap\">{{message}}</div>",
            'variables' => ['name' => 'Visitor name', 'message' => 'Their message'],
            'sample' => ['name' => 'Ayse', 'message' => "Hello,\nAre you open on Sunday?"],
        ],
    ],
    'widgets' => [
        ['id' => 'contact.messages', 'title' => 'Messages', 'icon' => 'fa-envelope-open-text', 'order' => 15, 'permission' => 'contact.messages', 'render' => static fn(): string => Contact::widget()],
    ],
    'search' => [
        'messages' => ['label' => 'Messages', 'icon' => 'fa-envelope-open-text', 'permission' => 'contact.messages', 'limit' => 5,
            'handler' => static function (string $term, int $limit): array {
                $rows = app(\AP\Core\DB::class)->table('contact_messages')->search(['name', 'email', 'subject', 'message'], $term)->orderBy('id', 'desc')->limit($limit)->get();
                return array_map(static fn($m) => ['title' => (string) $m['name'] . ($m['subject'] ? ': ' . $m['subject'] : ''), 'subtitle' => $m['email'] . ' · ' . format_date((string) $m['created_at']),
                    'url' => admin_url('messages/' . (int) $m['id']), 'icon' => 'fa-envelope', 'score' => 4], $rows);
            }],
    ],
    'boot' => static function ($app): void {
        Contact::boot();
    },
];
