<?php
declare(strict_types=1);

namespace AP\Modules\Money;

/** Small form field builders so every Money form looks and behaves the same (including errors in pop-ups). */
final class Fields
{
    private static function wrap(string $name, string $label, string $control, string $help = '', string $col = ''): string
    {
        $err = form_errors($name);
        $msg = $err ? e((string) (is_array($err) ? reset($err) : $err)) : '';
        return '<div class="mn-field ' . e($col) . '" data-field="' . e($name) . '"><label class="form-label" for="f_' . e($name) . '">' . e($label) . '</label>' . $control
            . '<div class="invalid-feedback" data-error' . ($msg !== '' ? ' style="display:block"' : '') . '>' . $msg . '</div>'
            . ($help !== '' ? '<div class="form-text">' . e($help) . '</div>' : '') . '</div>';
    }

    public static function text(string $name, string $label, mixed $value = '', array $o = []): string
    {
        $v = old($name, $value);
        $attrs = '';
        foreach (['placeholder', 'maxlength', 'inputmode', 'autocomplete', 'list'] as $a) {
            if (isset($o[$a])) {
                $attrs .= ' ' . $a . '="' . e((string) $o[$a]) . '"';
            }
        }
        if (!empty($o['date'])) {
            $attrs .= ' data-date autocomplete="off" placeholder="27/10/2026"';
        }
        $cls = 'form-control' . (form_errors($name) ? ' is-invalid' : '');
        return self::wrap($name, $label, '<input class="' . $cls . '" type="text" id="f_' . e($name) . '" name="' . e($name) . '" value="' . e((string) (is_scalar($v) ? $v : '')) . '"' . $attrs . (!empty($o['required']) ? ' required' : '') . '>', $o['help'] ?? '', $o['col'] ?? '');
    }

    public static function area(string $name, string $label, mixed $value = '', array $o = []): string
    {
        $v = old($name, $value);
        return self::wrap($name, $label, '<textarea class="form-control' . (form_errors($name) ? ' is-invalid' : '') . '" id="f_' . e($name) . '" name="' . e($name) . '" rows="' . (int) ($o['rows'] ?? 3) . '">' . e((string) (is_scalar($v) ? $v : '')) . '</textarea>', $o['help'] ?? '', $o['col'] ?? '');
    }

    /** @param array<int|string,string> $options */
    public static function select(string $name, string $label, array $options, mixed $value = '', array $o = []): string
    {
        $v = (string) old($name, $value);
        $h = '';
        if (isset($o['none'])) {
            $h .= '<option value="' . e((string) ($o['none_value'] ?? '0')) . '">' . e((string) $o['none']) . '</option>';
        }
        foreach ($options as $k => $l) {
            $h .= '<option value="' . e((string) $k) . '"' . ((string) $k === $v ? ' selected' : '') . '>' . e((string) $l) . '</option>';
        }
        return self::wrap($name, $label, '<select class="form-select' . (form_errors($name) ? ' is-invalid' : '') . '" id="f_' . e($name) . '" name="' . e($name) . '">' . $h . '</select>', $o['help'] ?? '', $o['col'] ?? '');
    }

    /**
     * A choice box you can search: type part of anything (client, number, date, amount) to narrow the list.
     * @param list<array{id:int|string,label:string,sub?:string,search?:string,data?:array<string,string>}> $items
     */
    public static function picker(string $name, string $label, array $items, mixed $value = '', array $o = []): string
    {
        $v = (string) old($name, $value);
        $none = (string) ($o['none'] ?? 'Nothing chosen');
        $cur = $none;
        $list = '<button type="button" class="mn-pick-item is-none" data-value="0" data-label="' . e($none) . '"><span>' . e($none) . '</span></button>';
        foreach ($items as $it) {
            $id = (string) $it['id'];
            $attrs = '';
            foreach ((array) ($it['data'] ?? []) as $k => $d) {
                $attrs .= ' data-' . e((string) $k) . '="' . e((string) $d) . '"';
            }
            if ($id === $v) {
                $cur = (string) $it['label'];
            }
            $list .= '<button type="button" class="mn-pick-item' . ($id === $v ? ' is-on' : '') . '" data-value="' . e($id) . '" data-label="' . e((string) $it['label']) . '" data-search="' . e(mb_strtolower((string) ($it['search'] ?? $it['label'] . ' ' . ($it['sub'] ?? '')))) . '"' . $attrs . '>'
                . '<span>' . e((string) $it['label']) . '</span>' . (!empty($it['sub']) ? '<small>' . e((string) $it['sub']) . '</small>' : '') . '</button>';
        }
        $ctl = '<div class="mn-pick" data-pick><input type="hidden" id="f_' . e($name) . '" name="' . e($name) . '" value="' . e($v === '' ? '0' : $v) . '">'
            . '<button type="button" class="form-select mn-pick-btn" data-pick-open aria-haspopup="listbox" aria-expanded="false"><span data-pick-text>' . e($cur) . '</span></button>'
            . '<div class="mn-pick-panel" hidden><input type="search" class="form-control form-control-sm mn-pick-q" data-pick-q placeholder="' . e((string) ($o['search'] ?? 'Search')) . '" autocomplete="off" aria-label="Search">'
            . '<div class="mn-pick-list" role="listbox">' . $list . '<div class="mn-pick-empty" hidden>Nothing matches.</div></div></div></div>';
        return self::wrap($name, $label, $ctl, $o['help'] ?? '', $o['col'] ?? '');
    }
}
