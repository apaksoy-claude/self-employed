<?php
declare(strict_types=1);

namespace AP\Modules\Money\Controllers;

use AP\Core\Request;
use AP\Core\Response;
use AP\Modules\Money\Gmail;
use AP\Modules\Money\Money;

/** Check Gmail for receipt emails now, and see what the last checks did. */
final class GmailController
{
    public function index(Request $request): Response
    {
        if (!Money::ready()) {
            return Money::notReady();
        }
        return view('money::gmail', ['title' => 'Email Import', 'breadcrumbs' => ['Money' => admin_url('money/invoices'), 'Email Import' => null],
            'configured' => Gmail::configured(), 'on' => Gmail::enabled(), 'book' => Gmail::book(), 'history' => Gmail::history(), 'unhandled' => Gmail::unhandled(), 'days' => (int) setting('money.gmail_days', 14), 'fetchDays' => (int) setting('money.gmail_fetch_days', 90), 'del' => (bool) setting('money.gmail_delete', true)]);
    }

    public function check(Request $request): Response
    {
        if (!Money::ready()) {
            return Money::notReady();
        }
        $r = Gmail::run();
        session()->toast($r['ok'] ? ($r['errors'] ? 'warning' : 'success') : 'danger', $r['message'] . ($r['errors'] ? ' ' . implode(' ', array_slice($r['errors'], 0, 3)) : ''));
        return redirect(admin_url('money/email-import'));
    }

    /** Look back over older mail with the rules as they are now (all of them, or just one). */
    public function fetch(Request $request): Response
    {
        if (!Money::ready()) {
            return Money::notReady();
        }
        $only = $request->string('rule') === '' ? null : $request->int('rule');
        $r = Gmail::run($request->int('days', (int) setting('money.gmail_fetch_days', 90)), true, $only);
        session()->toast($r['ok'] ? ($r['errors'] ? 'warning' : 'success') : 'danger', $r['message'] . ($r['errors'] ? ' ' . implode(' ', array_slice($r['errors'], 0, 3)) : ''));
        return redirect(admin_url('money/email-import'));
    }

    public function test(Request $request): Response
    {
        $msg = Gmail::test();
        session()->toast(str_starts_with($msg, 'Connected') ? 'success' : 'danger', $msg);
        return redirect(admin_url('money/email-import'));
    }

    /** The rule form: a new rule (optionally filled in from an email that was ignored or left for review) or an existing one. */
    public function ruleForm(Request $request, string $id = ''): Response
    {
        if (!Money::ready()) {
            return Money::notReady();
        }
        $rule = null;
        if ($id !== '') {
            foreach (Gmail::book() as $r) {
                if ($r['id'] === $id) {
                    $rule = $r;
                }
            }
            if (!$rule) {
                throw new \AP\Core\HttpException(404);
            }
        }
        $seen = $request->int('seen') > 0 ? Money::db()->table('money_mail_seen')->where('id', $request->int('seen'))->first() : null;
        $r = $rule ?? ($seen ? Gmail::ruleFrom($seen) : Gmail::DEFAULTS);
        $cats = Money::db()->table('money_categories')->where('kind', 'expense')->orderBy('name')->pluck('name');
        return Forms::page($request, 'money::_gmail_rule_form', ['rule' => $rule, 'r' => $r, 'seen' => $seen, 'categories' => $cats,
            'suppliers' => Money::db()->table('money_contacts')->orderBy('name')->pluck('name'), 'payWith' => array_values(Money::payOptions('money.paid_with', '')), 'streams' => Money::db()->table('money_streams')->orderBy('name')->pluck('name')],
            $rule ? 'Change This Rule' : 'Add An Email Rule', ['Money' => admin_url('money/invoices'), 'Email Import' => admin_url('money/email-import'), 'Rule' => null]);
    }

    /** Adds a new rule or changes one, then (if asked) looks again at past emails with it. */
    public function ruleSave(Request $request): Response
    {
        if (!Money::ready()) {
            return Money::notReady();
        }
        $in = [];
        foreach (Gmail::DEFAULTS as $k => $d) {
            $in[$k] = trim($request->string($k));
        }
        foreach (['category', 'supplier'] as $k) {
            if (trim($request->string($k . '_new')) !== '') {
                $in[$k] = trim($request->string($k . '_new'));
            }
        }
        $errors = [];
        if ($in['from'] === '' && $in['subject'] === '' && $in['body'] === '') {
            $errors['from'] = ['Fill in at least one of sender, subject or words in the email, so the rule knows which emails to pick.'];
        }
        if ($in['amount_fixed'] !== '' && Money::parseAmount($in['amount_fixed']) === null) {
            $errors['amount_fixed'] = ['Enter an amount such as 6.00, or leave it empty.'];
        }
        if ($errors) {
            return Forms::fail($request, $errors, admin_url('money/email-import/rules/' . ($in['id'] !== '' ? $in['id'] . '/edit' : 'new')));
        }
        $max = ['name' => 120, 'from' => 200, 'subject' => 200, 'body' => 200, 'exclude' => 200, 'amount_after' => 120, 'amount_fixed' => 20, 'ref_prefix' => 20, 'title' => 200, 'notes' => 500, 'category' => 80, 'supplier' => 120, 'stream' => 120, 'paid_by' => 60];
        foreach ($max as $k => $n) {
            $in[$k] = mb_substr($in[$k], 0, $n);
        }
        $in['on'] = $request->bool('on') ? '1' : '0';
        $in['keep_email'] = $request->bool('keep_email') ? '1' : '0';
        $in['keep_pdfs'] = $request->bool('keep_pdfs') ? '1' : '0';
        $in['business'] = (string) max(0, min(100, (float) ($in['business'] === '' ? 100 : $in['business'])));
        $in['name'] = $in['name'] !== '' ? $in['name'] : ($in['title'] !== '' ? $in['title'] : ($in['subject'] ?: ($in['from'] ?: $in['body'])));
        $book = Gmail::book();
        $found = false;
        foreach ($book as $i => $r) {
            if ($in['id'] !== '' && $r['id'] === $in['id']) {
                $book[$i] = $in;
                $found = true;
            }
        }
        if (!$found) {
            $in['id'] = bin2hex(random_bytes(4));
            $book[] = $in;
        }
        Gmail::saveBook($book);
        $msg = $found ? 'The rule was changed.' : 'The rule was added.';
        if ($request->bool('recheck') && $in['on'] === '1' && Gmail::configured()) {
            $idx = 0;
            foreach (Gmail::rules() as $i => $r) {
                if ($r['id'] === $in['id']) {
                    $idx = $i;
                }
            }
            $res = Gmail::run((int) setting('money.gmail_fetch_days', 90), true, $idx);
            $msg .= ' ' . $res['message'];
        }
        return Forms::done($request, admin_url('money/email-import'), admin_url('money/email-import/rules/' . $in['id'] . '/edit'), $msg);
    }

    public function ruleToggle(Request $request): Response
    {
        $book = Gmail::book();
        foreach ($book as $i => $r) {
            if ($r['id'] === $request->string('id')) {
                $book[$i]['on'] = $r['on'] === '1' ? '0' : '1';
            }
        }
        Gmail::saveBook($book);
        return redirect(admin_url('money/email-import'));
    }

    public function ruleDelete(Request $request): Response
    {
        Gmail::saveBook(array_values(array_filter(Gmail::book(), static fn($r) => $r['id'] !== $request->string('id'))));
        session()->toast('success', 'The rule was removed.');
        return redirect(admin_url('money/email-import'));
    }

    /** Drag and drop order: the first rule that fits an email wins. */
    public function ruleOrder(Request $request): Response
    {
        $by = [];
        foreach (Gmail::book() as $r) {
            $by[$r['id']] = $r;
        }
        $out = [];
        foreach ((array) $request->input('ids', []) as $id) {
            if (isset($by[(string) $id])) {
                $out[] = $by[(string) $id];
                unset($by[(string) $id]);
            }
        }
        Gmail::saveBook(array_merge($out, array_values($by)));
        return Response::json(['ok' => true, 'message' => 'Rule order saved']);
    }

    /** Adds the ready-made rules (Heathrow drop-off fee and Otto invoices) to change as you like. */
    public function ruleStarters(Request $request): Response
    {
        $book = Gmail::book();
        foreach (Gmail::starters() as $r) {
            $book[] = ['id' => bin2hex(random_bytes(4))] + $r + Gmail::DEFAULTS;
        }
        Gmail::saveBook($book);
        session()->toast('success', 'Four example rules were added. Open each one to change it.');
        return redirect(admin_url('money/email-import'));
    }

    /** Tidy the lists: rows are hidden (and still remembered, so an email is never added twice). */
    public function mailClear(Request $request): Response
    {
        $ids = \AP\Modules\Money\Bulk::ids($request);
        if (!$ids) {
            session()->toast('warning', 'Tick some emails first.');
        } else {
            $n = Money::db()->table('money_mail_seen')->whereIn('id', $ids)->update(['hidden' => 1]);
            session()->toast('success', $n . ' row(s) removed from the list. The expenses they made stay.');
        }
        return redirect(Money::returnTo($request, admin_url('money/email-import')));
    }
}
