<?php
declare(strict_types=1);

namespace AP\Modules\Mail\Controllers;

use AP\Core\Activity;
use AP\Core\DB;
use AP\Core\Mailer;
use AP\Core\Request;
use AP\Core\Response;

final class MailController
{
    private function mailer(): Mailer
    {
        /** @var Mailer $m */
        $m = app(Mailer::class);
        return $m;
    }

    /** The status, connection test and test email now live on the Email settings page. */
    public function index(Request $request): Response
    {
        return redirect(admin_url('settings/email'));
    }

    public function testConnection(Request $request): Response
    {
        $driver = $request->string('driver') !== '' && isset(Mailer::DRIVERS[$request->string('driver')]) ? $request->string('driver') : Mailer::driver();
        $r = $this->mailer()->testConnection($driver);
        session()->flash('mail_test', ['ok' => $r['ok'], 'title' => Mailer::DRIVERS[$driver] . ' connection test', 'message' => $r['message'], 'transcript' => $r['transcript'] ?? []]);
        Activity::log('mail.test_connection', 'Tested ' . $driver . ' connection: ' . ($r['ok'] ? 'ok' : 'failed'));
        return back(admin_url('settings/email'));
    }

    public function sendTest(Request $request): Response
    {
        $to = trim($request->string('to'));
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            session()->flash('mail_test', ['ok' => false, 'title' => 'Test email', 'message' => 'Enter a valid email address.', 'transcript' => []]);
            return back(admin_url('settings/email'));
        }
        // Sent straight away (not queued) so the result is real.
        $r = $this->mailer()->sendTemplate('mail.test', $to, ['name' => explode(' ', (string) (auth_user()['name'] ?? 'there'))[0]], ['queue' => false]);
        Activity::log('mail.send_test', "Sent a test email to {$to}: " . ($r === true ? 'ok' : 'failed'));
        session()->flash('mail_test', $r === true
            ? ['ok' => true, 'title' => 'Test email', 'message' => "Handed over to the mail system for {$to}. Check the inbox (and the spam folder).", 'transcript' => []]
            : ['ok' => false, 'title' => 'Test email', 'message' => $r, 'transcript' => []]);
        return back(admin_url('settings/email'));
    }
}
