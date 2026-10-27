<?php
declare(strict_types=1);

namespace AP\Modules\Money;

use AP\Core\Request;

/** Tick several rows, then do one thing to all of them. The tick boxes live in the table; the buttons live in a bar above it. */
final class Bulk
{
    /** @return list<int> ids ticked (at most 500) */
    public static function ids(Request $request): array
    {
        return array_slice(array_values(array_unique(array_filter(array_map('intval', (array) ($request->post['ids'] ?? []))))), 0, 500);
    }

    /** The tick box at the head of the column: ticks every row on the page. */
    public static function head(string $form): string
    {
        return '<th style="width:1%"><input class="form-check-input" type="checkbox" data-bulk-all="' . e($form) . '" aria-label="Tick every row on this page"></th>';
    }

    public static function cell(string $form, int $id, string $label = ''): string
    {
        return '<td><input class="form-check-input" type="checkbox" name="ids[]" value="' . $id . '" form="' . e($form) . '" data-bulk-one aria-label="Tick ' . e($label !== '' ? $label : 'this row') . '"></td>';
    }

    /**
     * The bar that appears when something is ticked.
     * @param list<array{op:string,label:string,icon?:string,danger?:bool,ask?:string,options?:array<string|int,string>,placeholder?:string,input?:string}> $ops
     *   a plain operation is a button; with "options" it is a drop-down that acts as soon as one is chosen; with "input" it is a number box with a button
     */
    public static function bar(string $form, string $action, array $ops, string $noun = 'rows'): string
    {
        $h = '<form id="' . e($form) . '" method="post" action="' . e($action) . '" class="mn-bulk" data-bulk-bar hidden>' . csrf_field()
            . '<input type="hidden" name="return" value="' . e((string) ($_SERVER['REQUEST_URI'] ?? '')) . '"><input type="hidden" name="do" value=""><input type="hidden" name="value" value="">'
            . '<span class="mn-bulk-n"><strong data-bulk-n>0</strong> ' . e($noun) . ' ticked</span>'
            . '<button type="button" class="btn btn-sm btn-link" data-bulk-clear>Clear</button><span class="mn-bulk-ops">';
        foreach ($ops as $o) {
            if (isset($o['options'])) {
                $h .= '<select class="form-select form-select-sm w-auto" data-bulk-op="' . e($o['op']) . '" aria-label="' . e($o['label']) . '"><option value="">' . e($o['placeholder'] ?? $o['label']) . '</option>';
                foreach ($o['options'] as $v => $label) {
                    $h .= '<option value="' . e((string) $v) . '">' . e((string) $label) . '</option>';
                }
                $h .= '</select>';
            } elseif (isset($o['input'])) {
                $h .= '<span class="input-group input-group-sm w-auto"><input class="form-control" type="text" inputmode="decimal" size="5" placeholder="' . e($o['input']) . '" data-bulk-input="' . e($o['op']) . '" aria-label="' . e($o['label']) . '">'
                    . '<button class="btn btn-outline-secondary" type="button" data-bulk-go="' . e($o['op']) . '">' . e($o['label']) . '</button></span>';
            } else {
                $h .= '<button class="btn btn-sm ' . (!empty($o['danger']) ? 'btn-outline-danger' : 'btn-outline-secondary') . '" type="submit" name="do" value="' . e($o['op']) . '"'
                    . (!empty($o['ask']) ? ' data-confirm="' . e($o['ask']) . '"' : '') . '>' . (!empty($o['icon']) ? '<i class="fa-solid ' . e($o['icon']) . ' me-1"></i>' : '') . e($o['label']) . '</button>';
            }
        }
        return $h . '</span></form>';
    }
}
