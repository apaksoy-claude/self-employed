<?php
declare(strict_types=1);

namespace AP\Modules\Mail\Controllers;

use AP\Core\Activity;
use AP\Core\DB;
use AP\Core\HttpException;
use AP\Core\Mailer;
use AP\Core\Request;
use AP\Core\Response;
use AP\Core\Validator;

final class TemplateController
{
    private function mailer(): Mailer
    {
        /** @var Mailer $m */
        $m = app(Mailer::class);
        return $m;
    }

    private function db(): DB
    {
        /** @var DB $db */
        $db = app(DB::class);
        return $db;
    }

    public function index(Request $request): Response
    {
        $list = [];
        foreach (array_keys($this->mailer()->definitions()) as $key) {
            $list[] = ['key' => $key] + (array) $this->mailer()->template($key);
        }
        return view('mail::templates.index', ['title' => 'Email templates', 'breadcrumbs' => ['Email' => admin_url('settings/email'), 'Templates' => null], 'templates' => $list]);
    }

    public function edit(Request $request, string $key): Response
    {
        $t = $this->mailer()->template($key) ?? throw new HttpException(404);
        $default = $this->mailer()->definitions()[$key];
        return view('mail::templates.edit', [
            'title' => 'Edit: ' . $t['label'], 'breadcrumbs' => ['Email' => admin_url('settings/email'), 'Templates' => admin_url('mail/templates'), $t['label'] => null],
            'key' => $key, 't' => $t, 'default' => $default, 'me' => auth_user(),
            'globals' => ['site_name' => 'Your site name', 'site_url' => 'Your website address', 'year' => 'This year', 'admin_email' => 'Site owner email', 'contact_email' => 'Public contact email'],
        ]);
    }

    public function update(Request $request, string $key): Response
    {
        $this->mailer()->template($key) ?? throw new HttpException(404);
        $v = Validator::make($request->all(), ['subject' => 'required|string|max:300', 'body' => 'required|string|max:100000'], ['subject' => 'Subject', 'body' => 'Message']);
        if ($v->fails()) {
            return back_with_errors($v->errors(), $request->all(), admin_url('mail/templates/' . $key));
        }
        $before = $this->mailer()->template($key);
        $data = ['subject' => $request->string('subject'), 'body' => (string) $request->input('body'), 'enabled' => $request->bool('enabled') ? 1 : 0, 'updated_by' => auth_user()['id'] ?? null, 'updated_at' => gmdate('Y-m-d H:i:s')];
        $q = $this->db()->table('email_templates')->where('tkey', $key);
        if ($q->exists()) {
            $q->update($data);
        } else {
            $this->db()->table('email_templates')->insert($data + ['tkey' => $key]);
        }
        Activity::log('mail.template', 'Edited email template ' . $key, 'email_template', $key, ['subject' => $before['subject'], 'enabled' => $before['enabled']], ['subject' => $data['subject'], 'enabled' => (bool) $data['enabled']]);
        session()->toast('success', 'Template saved.');
        return $request->bool('stay') ? redirect(admin_url('mail/templates/' . $key)) : redirect(admin_url('mail/templates'));
    }

    /** JSON for the live preview. Uses what is in the form, with sample values. */
    public function preview(Request $request, string $key): Response
    {
        $t = $this->mailer()->template($key) ?? throw new HttpException(404);
        $r = $this->mailer()->render($key, (array) $t['sample'], ['subject' => $request->string('subject'), 'body' => (string) $request->input('body')]);
        return Response::json(['subject' => $r['subject'], 'html' => $r['html']]);
    }

    public function test(Request $request, string $key): Response
    {
        $t = $this->mailer()->template($key) ?? throw new HttpException(404);
        $to = trim($request->string('to')) ?: (string) (auth_user()['email'] ?? '');
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            session()->toast('danger', 'Enter a valid email address for the test.');
            return back(admin_url('mail/templates/' . $key));
        }
        // Unsaved edits in the form are tested too.
        $r = $this->mailer()->render($key, (array) $t['sample'], $request->has('body') ? ['subject' => $request->string('subject'), 'body' => (string) $request->input('body')] : null);
        $m = new \AP\Core\Mail\Message();
        $m->subject = '[Test] ' . $r['subject'];
        $m->html = $r['html'];
        $m->text = $r['text'];
        try {
            $m->to($to);
        } catch (\InvalidArgumentException $e) {
            session()->toast('danger', $e->getMessage());
            return back(admin_url('mail/templates/' . $key));
        }
        $res = $this->mailer()->send($m, false);
        session()->toast($res === true ? 'success' : 'danger', $res === true ? "Test sent to {$to}." : 'Could not send: ' . $res);
        return back(admin_url('mail/templates/' . $key));
    }

    public function reset(Request $request, string $key): Response
    {
        $this->mailer()->template($key) ?? throw new HttpException(404);
        $this->db()->table('email_templates')->where('tkey', $key)->delete();
        Activity::log('mail.template_reset', 'Reset email template ' . $key . ' to default', 'email_template', $key);
        session()->toast('success', 'Template reset to the original wording.');
        return redirect(admin_url('mail/templates/' . $key));
    }
}
