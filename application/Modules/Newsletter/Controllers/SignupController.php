<?php
declare(strict_types=1);

namespace AP\Modules\Newsletter\Controllers;

use AP\Core\Request;
use AP\Core\Response;
use AP\Modules\Newsletter\Newsletter;

final class SignupController
{
    public function subscribe(Request $request): Response
    {
        $page = $request->string('page');
        $form = preg_match('/^nl\d{1,3}$/', $request->string('form_id')) ? $request->string('form_id') : 'nl1';
        $back = (str_starts_with($page, base_url()) ? $page : url('/')) . '#' . $form;
        if (!Newsletter::ready()) {
            return redirect($back);
        }
        $r = Newsletter::receive($request);
        session()->flash('newsletter_form', $form);
        if (!$r['ok']) {
            return back_with_errors(['newsletter' => [$r['error']]], ['email' => $request->string('email'), 'name' => $request->string('name')], $back);
        }
        session()->flash('newsletter_done', true);
        return redirect($back);
    }

    public function leave(Request $request, string $token): Response
    {
        $row = Newsletter::ready() ? Newsletter::db()->table('newsletter_subscribers')->where('token', $token)->first() : null;
        if ($row && $row['status'] === 'subscribed') {
            Newsletter::db()->table('newsletter_subscribers')->where('id', (int) $row['id'])->update(['status' => 'left', 'left_at' => gmdate('Y-m-d H:i:s')]);
        }
        return view('newsletter::left', ['title' => __('newsletter.left_title'), 'known' => $row !== null, 'meta' => ['robots' => 'noindex']]);
    }
}
