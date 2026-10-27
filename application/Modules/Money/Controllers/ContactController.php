<?php
declare(strict_types=1);

namespace AP\Modules\Money\Controllers;

use AP\Core\Activity;
use AP\Core\HttpException;
use AP\Core\Request;
use AP\Core\Response;
use AP\Modules\Money\Bulk;
use AP\Modules\Money\Money;

/** The people and companies you invoice or pay. A contact that is used by records is archived instead of removed, so old records keep their names. */
final class ContactController
{
    public const KINDS = ['client' => 'Client', 'supplier' => 'Supplier', 'other' => 'Other'];

    public function index(Request $request): Response
    {
        if (!Money::ready()) {
            return Money::notReady();
        }
        if ($again = Money::rememberFilters($request, admin_url('money/contacts'))) {
            return $again;
        }
        $db = Money::db();
        $term = trim($request->string('q'));
        $q = $db->table('money_contacts');
        if ($term !== '') {
            $q->search(['name', 'email', 'phone', 'city'], $term);
        }
        $rows = $q->orderBy('name')->get();
        $uses = [];
        foreach (['money_invoices' => 'invoices', 'money_income' => 'payments', 'money_expenses' => 'expenses'] as $table => $key) {
            foreach ($db->table($table)->whereNull('deleted_at')->where('contact_id', '>', 0)->select('contact_id')->get() as $r) {
                $uses[(int) $r['contact_id']][$key] = ($uses[(int) $r['contact_id']][$key] ?? 0) + 1;
            }
        }
        return view('money::contacts', ['title' => 'Clients and Suppliers', 'breadcrumbs' => ['Money' => admin_url('money/invoices'), 'Clients and Suppliers' => null], 'rows' => $rows, 'uses' => $uses, 'q' => $term, 'canEdit' => can('money.edit')]);
    }

    public function form(Request $request, string $id = '0'): Response
    {
        if (!Money::ready()) {
            return Money::notReady();
        }
        $row = (int) $id > 0 ? (Money::db()->table('money_contacts')->where('id', (int) $id)->first() ?? throw new HttpException(404)) : null;
        return Forms::page($request, 'money::_contact_form', ['c' => $row], $row ? 'Change Contact' : 'New Contact',
            ['Money' => admin_url('money/invoices'), 'Clients and Suppliers' => admin_url('money/contacts'), ($row ? 'Change' : 'New') => null]);
    }

    public function save(Request $request): Response
    {
        $db = Money::db();
        $id = $request->int('id');
        $name = trim($request->string('name'));
        if ($name === '') {
            return Forms::fail($request, ['name' => ['Enter a name.']], admin_url('money/contacts/' . ($id ?: 'new')));
        }
        $email = trim($request->string('email'));
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return Forms::fail($request, ['email' => ['That email address does not look right.']], admin_url('money/contacts/' . ($id ?: 'new')));
        }
        if ($cf = \AP\Modules\Money\CustomFields::validate('contact', $request)) {
            return Forms::fail($request, $cf, admin_url('money/contacts/' . ($id ?: 'new')));
        }
        $kind = isset(self::KINDS[$request->string('kind')]) ? $request->string('kind') : 'client';
        $cut = static fn(string $k, int $n): ?string => mb_substr(trim($request->string($k)), 0, $n) ?: null;
        $data = ['kind' => $kind, 'name' => mb_substr($name, 0, 190), 'email' => $email ?: null, 'phone' => $cut('phone', 40), 'address' => $cut('address', 255), 'city' => $cut('city', 100),
            'postcode' => $cut('postcode', 20), 'country' => $cut('country', 100), 'website' => $cut('website', 190), 'note' => trim($request->string('note')) ?: null,
            'active' => $request->bool('active') || $id === 0 ? 1 : 0, 'updated_at' => gmdate('Y-m-d H:i:s')];
        if ($request->has('cis_rate')) {
            $data['cis_rate'] = isset(\AP\Modules\Money\Cis::RATES[$request->string('cis_rate')]) ? $request->string('cis_rate') : '';
            $data['cis_ref'] = $cut('cis_ref', 30);
        }
        if ($id > 0) {
            $db->table('money_contacts')->where('id', $id)->first() ?? throw new HttpException(404);
            $db->table('money_contacts')->where('id', $id)->update($data);
        } else {
            $data['created_at'] = $data['updated_at'];
            $id = (int) $db->table('money_contacts')->insertGetId($data);
        }
        \AP\Modules\Money\CustomFields::save('contact', (int) $id, $request);
        Activity::log('money.contact.save', 'Saved the contact ' . $name, 'money_contact', $id);
        return Forms::done($request, admin_url('money/contacts'), admin_url('money/contacts/' . $id), 'Contact saved.');
    }

    public function delete(Request $request, string $id): Response
    {
        $db = Money::db();
        $c = $db->table('money_contacts')->where('id', (int) $id)->first() ?? throw new HttpException(404);
        $used = false;
        foreach (['money_invoices', 'money_income', 'money_expenses'] as $t) {
            $used = $used || $db->table($t)->where('contact_id', (int) $id)->exists();
        }
        if ($used) {
            $db->table('money_contacts')->where('id', (int) $id)->update(['active' => 0, 'updated_at' => gmdate('Y-m-d H:i:s')]);
            session()->toast('warning', $c['name'] . ' is used by invoices or records, so it was archived instead of deleted. Old records keep the name.');
        } else {
            $db->table('money_contacts')->where('id', (int) $id)->delete();
            session()->toast('success', 'Contact deleted.');
        }
        Activity::log('money.contact.delete', 'Removed the contact ' . $c['name'], 'money_contact', (int) $id);
        return redirect(Money::returnTo($request, admin_url('money/contacts')));
    }

    /** Delete (or archive when used), archive, or bring back every ticked contact. */
    public function bulk(Request $request): Response
    {
        $db = Money::db();
        $back = Money::returnTo($request, admin_url('money/contacts'));
        $ids = Bulk::ids($request);
        $do = $request->string('do');
        if (!$ids) {
            session()->toast('warning', 'Tick some contacts first.');
            return redirect($back);
        }
        $now = gmdate('Y-m-d H:i:s');
        if ($do === 'archive' || $do === 'restore') {
            $n = $db->table('money_contacts')->whereIn('id', $ids)->update(['active' => $do === 'restore' ? 1 : 0, 'updated_at' => $now]);
            session()->toast('success', $n . ' contact(s) ' . ($do === 'restore' ? 'brought back.' : 'archived.'));
        } elseif ($do === 'delete') {
            $deleted = $archived = 0;
            foreach ($ids as $id) {
                $used = false;
                foreach (['money_invoices', 'money_income', 'money_expenses'] as $t) {
                    $used = $used || $db->table($t)->where('contact_id', $id)->exists();
                }
                if ($used) {
                    $archived += $db->table('money_contacts')->where('id', $id)->update(['active' => 0, 'updated_at' => $now]);
                } else {
                    $deleted += $db->table('money_contacts')->where('id', $id)->delete();
                }
            }
            session()->toast($archived ? 'warning' : 'success', $deleted . ' contact(s) deleted' . ($archived ? ', ' . $archived . ' archived because records use them.' : '.'));
        } else {
            session()->toast('warning', 'That action is not available.');
        }
        Activity::log('money.contact.bulk', 'Changed several contacts together (' . $do . ')', 'money_contact', implode(',', array_slice($ids, 0, 20)));
        return redirect($back);
    }

    /** Opens the client's private portal page, creating its link the first time. */
    public function portal(Request $request, string $id): Response
    {
        $c = Money::db()->table('money_contacts')->where('id', (int) $id)->first() ?? throw new \AP\Core\HttpException(404);
        if (!\AP\Modules\Money\Portal::on()) {
            session()->toast('warning', 'The client portal is switched off in Money settings.');
            return redirect(admin_url('money/contacts'));
        }
        return redirect(\AP\Modules\Money\Portal::url($c));
    }

    /** The client's statement, on its own page to print or save as a PDF. */
    public function statement(Request $request, string $id): Response
    {
        $c = Money::db()->table('money_contacts')->where('id', (int) $id)->first() ?? throw new \AP\Core\HttpException(404);
        $r = view('money::statement', \AP\Modules\Money\Pay::statementData($c, $request->string('period'), admin_url('money/contacts/' . (int) $id . '/statement'), admin_url('money/contacts')));
        $r->headers['Cache-Control'] = 'no-store, private';
        return $r;
    }

    public function statementEmailForm(Request $request, string $id): Response
    {
        $c = Money::db()->table('money_contacts')->where('id', (int) $id)->first() ?? throw new HttpException(404);
        return Forms::page($request, 'money::_statement_email', ['contact' => $c, 'to' => \AP\Modules\Money\StatementMail::email($c), 'subject' => \AP\Modules\Money\StatementMail::subject($c),
            'message' => \AP\Modules\Money\StatementMail::defaultMessage($c), 'copy' => (bool) setting('money.statement_copy', true)], 'Email Statement', ['Money' => admin_url('money/contacts'), 'Clients and Suppliers' => admin_url('money/contacts'), 'Email Statement' => null]);
    }

    public function statementEmailSend(Request $request, string $id): Response
    {
        $c = Money::db()->table('money_contacts')->where('id', (int) $id)->first() ?? throw new HttpException(404);
        $back = admin_url('money/contacts');
        $to = trim($request->string('to'));
        $errors = [];
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $errors['to'] = ['Enter a valid email address.'];
        }
        if (trim($request->string('subject')) === '') {
            $errors['subject'] = ['Write a subject.'];
        }
        if ($errors) {
            return Forms::fail($request, $errors, admin_url('money/contacts/' . (int) $id . '/statement/email'));
        }
        $r = \AP\Modules\Money\StatementMail::send($c, $to, (string) ($request->post['message'] ?? ''), $request->string('subject'), $request->bool('copy'));
        if ($r !== true) {
            return Forms::fail($request, ['to' => [$r]], admin_url('money/contacts/' . (int) $id . '/statement/email'));
        }
        return Forms::done($request, $back, $back, 'Statement sent to ' . $to . '.');
    }

    /** The list of clients who owe money, to tick and email. */
    public function statementsList(Request $request): Response
    {
        return view('money::statements_send', ['title' => 'Email Statements', 'breadcrumbs' => ['Money' => admin_url('money/invoices'), 'Clients and Suppliers' => admin_url('money/contacts'), 'Email Statements' => null],
            'rows' => \AP\Modules\Money\StatementMail::debtors()]);
    }

    public function statementsSend(Request $request): Response
    {
        $ids = array_values(array_filter(array_map('intval', (array) ($request->post['ids'] ?? []))));
        if (!$ids) {
            session()->toast('warning', 'Tick at least one client.');
            return redirect(admin_url('money/contacts/statements'));
        }
        $r = \AP\Modules\Money\StatementMail::sendMany($ids);
        if ($r['sent'] > 0) {
            session()->toast('success', $r['sent'] . ' statement(s) emailed.');
        }
        foreach (array_merge($r['skipped'], $r['failed']) as $p) {
            session()->toast('warning', $p);
        }
        return redirect(admin_url('money/contacts'));
    }
}
