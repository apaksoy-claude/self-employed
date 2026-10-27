<?php
declare(strict_types=1);

namespace AP\Modules\Contact\Controllers;

use AP\Core\Request;
use AP\Core\Response;
use AP\Modules\Contact\Contact;

final class ContactController
{
    public function send(Request $request): Response
    {
        $page = $request->string('page');
        $back = str_starts_with($page, base_url()) ? $page : url('/');
        if (!Contact::ready()) {
            return redirect($back);
        }
        $r = Contact::receive($request);
        if (!$r['ok']) {
            $post = $request->post;
            unset($post['_token'], $post['ap_website'], $post['ap_ts']);
            return back_with_errors($r['errors'], $post, $back . '#contact-form');
        }
        // Held messages get the same thank-you, so spammers learn nothing.
        session()->flash('contact_sent', true);
        return redirect($back . '#contact-form');
    }
}
