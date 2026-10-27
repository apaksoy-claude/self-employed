<?php
declare(strict_types=1);

namespace AP\Modules\Money\Controllers;

use AP\Core\Activity;
use AP\Core\Request;
use AP\Core\Response;
use AP\Modules\Money\CustomFields;
use AP\Modules\Money\Money;

/** Money > Custom Fields: add your own fields to any form. */
final class CustomFieldController
{
    public function index(Request $request): Response
    {
        if (!Money::ready()) {
            return Money::notReady();
        }
        if (!CustomFields::ready()) {
            session()->toast('warning', 'Press "Finish update" first, then open Custom Fields again.');
            return redirect(admin_url('money'));
        }
        $entity = isset(CustomFields::ENTITIES[$request->string('for')]) ? $request->string('for') : 'expense';
        $counts = [];
        foreach (CustomFields::ENTITIES as $k => $e) {
            $counts[$k] = count(CustomFields::fields($k));
        }
        $rows = array_map(static fn($f) => ['key' => (string) $f['field_key'], 'label' => (string) $f['label'], 'type' => (string) $f['type'], 'choices' => (string) $f['choices'], 'required' => (string) (int) $f['required'],
            'in_list' => (string) (int) $f['in_list'], 'help' => (string) $f['help']], CustomFields::fields($entity));
        return view('money::custom_fields', ['title' => 'Custom Fields', 'breadcrumbs' => ['Money' => admin_url('money/invoices'), 'Custom Fields' => null], 'entity' => $entity, 'counts' => $counts, 'rows' => $rows, 'canEdit' => can('money.edit')]);
    }

    public function save(Request $request): Response
    {
        $entity = $request->string('entity');
        if (!isset(CustomFields::ENTITIES[$entity]) || !CustomFields::ready()) {
            return redirect(admin_url('money/fields'));
        }
        $db = Money::db();
        $now = gmdate('Y-m-d H:i:s');
        $existing = [];
        foreach (CustomFields::fields($entity) as $f) {
            $existing[(string) $f['field_key']] = $f;
        }
        $keep = [];
        $order = 0;
        $rows = (array) ($request->post['fields'] ?? []);
        foreach ($rows as $r) {
            if (!is_array($r)) {
                continue;
            }
            $label = mb_substr(trim((string) ($r['label'] ?? '')), 0, 120);
            if ($label === '') {
                continue;
            }
            $type = isset(CustomFields::TYPES[(string) ($r['type'] ?? '')]) ? (string) $r['type'] : 'text';
            $choices = $type === 'select' ? implode(', ', array_values(array_unique(array_filter(array_map(static fn($c) => trim(str_replace(',', ' ', $c)), explode(',', (string) ($r['choices'] ?? '')))))) ) : '';
            $data = ['label' => $label, 'type' => $type, 'choices' => $choices ?: null, 'required' => ((string) ($r['required'] ?? '0')) === '1' ? 1 : 0, 'in_list' => ((string) ($r['in_list'] ?? '0')) === '1' ? 1 : 0,
                'help' => mb_substr(trim((string) ($r['help'] ?? '')), 0, 255) ?: null, 'sort_order' => ++$order, 'updated_at' => $now];
            $key = (string) ($r['key'] ?? '');
            if ($key !== '' && isset($existing[$key])) {
                $db->table('money_custom_fields')->where('id', (int) $existing[$key]['id'])->update($data);
                $keep[$key] = true;
            } else {
                $k = CustomFields::key($entity, $label);
                $db->table('money_custom_fields')->insert($data + ['entity' => $entity, 'field_key' => $k, 'created_at' => $now]);
                $keep[$k] = true;
                CustomFields::reset();
            }
        }
        $gone = 0;
        foreach ($existing as $k => $f) {
            if (!isset($keep[$k])) {
                $db->table('money_custom_values')->where('field_id', (int) $f['id'])->delete();
                $db->table('money_custom_fields')->where('id', (int) $f['id'])->delete();
                $gone++;
            }
        }
        CustomFields::reset();
        Activity::log('money.fields.save', 'Saved custom fields for ' . CustomFields::ENTITIES[$entity][0], 'money_custom_fields', 0);
        session()->toast('success', 'Custom fields saved.' . ($gone ? ' ' . $gone . ' removed, with what was typed into them.' : ''));
        return redirect(admin_url('money/fields?for=' . $entity));
    }
}
