<?php
declare(strict_types=1);

namespace AP\Core;

/**
 * Renders Bootstrap form fields with labels, help text, errors and old input.
 * Types: text, email, url, number, textarea, select, timezone, segmented, checkbox (a switch),
 * code (a monospace text area for HTML, JavaScript or CSS), color, password (with a show button; "secret" adds the saved/encrypted state and a remove box)
 * and repeater (rows of inputs that can be added, removed and reordered).
 */
final class Form
{
    /**
     * @param array{name:string,label?:string,type?:string,value?:mixed,help?:string,options?:array,required?:bool,placeholder?:string,secret?:bool,attrs?:array,rows?:int,id?:string,col?:string,fields?:array,add_label?:string,max_rows?:int,min_rows?:int,saved?:bool,default?:mixed} $f
     */
    public static function field(array $f): string
    {
        $f += ['label' => '', 'type' => 'text', 'value' => '', 'help' => '', 'options' => [], 'required' => false, 'placeholder' => '', 'secret' => false,
            'attrs' => [], 'rows' => 4, 'col' => '', 'fields' => [], 'add_label' => 'Add a row', 'max_rows' => 100, 'min_rows' => 0, 'saved' => false];
        $name = $f['name'];
        $id = $f['id'] ?? self::id($name);
        $errors = form_errors($name);
        $value = old($name, $f['value']);
        $invalid = $errors ? ' is-invalid' : '';
        $describe = $f['help'] !== '' ? ' aria-describedby="' . e($id) . '_help"' : '';
        $attrs = self::attrs($f['attrs']);
        $required = $f['required'] ? ' required' : '';
        $type = $f['type'];
        $input = '';

        switch ($type) {
            case 'cards':
                // Radio choices shown as cards: options are 'value' => 'Label' or 'value' => ['label' => .., 'help' => .., 'badge' => .., 'badge_type' => 'success|warning|info']
                $input = '<div class="ap-cards' . ($errors ? ' is-invalid' : '') . '" role="radiogroup" aria-labelledby="' . e($id) . '_label"' . $describe . '>';
                $i = 0;
                foreach ($f['options'] as $k => $opt) {
                    $opt = is_array($opt) ? $opt + ['label' => (string) $k, 'help' => '', 'badge' => '', 'badge_type' => 'success'] : ['label' => (string) $opt, 'help' => '', 'badge' => '', 'badge_type' => 'success'];
                    $oid = $id . '_' . $i++;
                    $on = (string) $k === (string) (is_scalar($value) ? $value : '');
                    $input .= '<label class="ap-card-opt' . ($on ? ' is-checked' : '') . '" for="' . e($oid) . '">'
                        . '<input type="radio" name="' . e($name) . '" id="' . e($oid) . '" value="' . e($k) . '"' . ($on ? ' checked' : '') . $attrs . '>'
                        . '<span class="ap-card-opt-body"><span class="ap-card-opt-title">' . e($opt['label'])
                        . ($opt['badge'] !== '' ? ' <span class="ap-card-badge ap-card-badge-' . e($opt['badge_type']) . '">' . e($opt['badge']) . '</span>' : '') . '</span>'
                        . ($opt['help'] !== '' ? '<span class="ap-card-opt-help">' . e($opt['help']) . '</span>' : '') . '</span></label>';
                }
                $input .= '</div>';
                break;
            case 'image':
                // An image address with a preview, and Upload / Choose buttons when the media library is on.
                $val = is_scalar($value) ? (string) $value : '';
                $media = function_exists('can') && can('media.view') && class_exists(\AP\Modules\Media\Media::class) && \AP\Modules\Media\Media::ready();
                $input = '<div class="ap-image-field" data-image-field>'
                    . '<span class="ap-image-thumb" data-image-thumb>' . ($val !== '' ? '<img src="' . e($val) . '" alt="">' : '<i class="fa-regular fa-image"></i>') . '</span>'
                    . '<input type="text" class="form-control' . $invalid . '" id="' . e($id) . '" name="' . e($name) . '" value="' . e($val) . '" placeholder="' . e($f['placeholder'] ?: 'https://…') . '"' . $describe . $attrs . '>';
                if ($media && empty($f['attrs']['disabled'])) {
                    $input .= (can('media.upload') ? '<button type="button" class="btn btn-sm btn-dark" data-image-upload><i class="fa-solid fa-upload me-1"></i>Upload</button><input type="file" accept="image/*" class="d-none" data-image-file>' : '')
                        . '<button type="button" class="btn btn-sm btn-dark" data-media-pick="#' . e($id) . '" data-media-kind="image"><i class="fa-regular fa-images me-1"></i>Choose</button>';
                }
                $input .= '<button type="button" class="btn btn-sm btn-dark" data-image-clear title="Remove the image" aria-label="Remove the image"><i class="fa-solid fa-xmark"></i></button></div>';
                break;
            case 'code':
                // Code (HTML, JavaScript, CSS): monospace, no spell check, keeps tabs readable.
                $input = '<textarea class="form-control ap-code ap-code-area' . $invalid . '" id="' . e($id) . '" name="' . e($name) . '" rows="' . max(4, (int) $f['rows']) . '" spellcheck="false" autocomplete="off" autocapitalize="off"'
                    . ' placeholder="' . e($f['placeholder']) . '"' . $describe . $attrs . '>' . e(is_scalar($value) ? $value : '') . '</textarea>';
                break;
            case 'textarea':
                if ($f['secret']) {
                    $input = '<div class="ap-secret-area"><textarea class="form-control ap-code' . $invalid . '" id="' . e($id) . '" name="' . e($name) . '" rows="' . (int) $f['rows'] . '" autocomplete="off" spellcheck="false"'
                        . ' placeholder="' . e($f['saved'] ? __('core.secret_keep') : $f['placeholder']) . '"' . $describe . $attrs . ' data-secret-area></textarea>'
                        . '<button class="btn btn-sm btn-outline-secondary" type="button" data-reveal-area="' . e($id) . '" aria-label="Show or hide" title="Show or hide"><i class="fa-regular fa-eye"></i></button></div>';
                    if ($f['saved']) {
                        $input .= '<div class="form-check mt-2"><input class="form-check-input" type="checkbox" id="' . e($id) . '_remove" name="' . e($name) . '__remove" value="1"' . $attrs . '>'
                            . '<label class="form-check-label small" for="' . e($id) . '_remove">Remove the saved value</label></div>';
                    }
                    break;
                }
                $input = '<textarea class="form-control' . $invalid . '" id="' . e($id) . '" name="' . e($name) . '" rows="' . (int) $f['rows'] . '"'
                    . ' placeholder="' . e($f['placeholder']) . '"' . $required . $describe . $attrs . '>' . e(is_scalar($value) ? $value : '') . '</textarea>';
                break;
            case 'select':
            case 'timezone':
                $options = $type === 'timezone' ? array_combine(\DateTimeZone::listIdentifiers(), \DateTimeZone::listIdentifiers()) : $f['options'];
                $input = '<select class="form-select' . $invalid . '" id="' . e($id) . '" name="' . e($name) . '"' . $required . $describe . $attrs . '>';
                foreach ($options as $k => $label) {
                    $input .= '<option value="' . e($k) . '"' . ((string) $k === (string) (is_scalar($value) ? $value : '') ? ' selected' : '') . '>' . e($label) . '</option>';
                }
                $input .= '</select>';
                break;
            case 'segmented':
                $input = '<div class="ap-segmented' . ($errors ? ' is-invalid' : '') . '" role="radiogroup" aria-labelledby="' . e($id) . '_label"' . $describe . '>';
                $i = 0;
                foreach ($f['options'] as $k => $label) {
                    $oid = $id . '_' . $i++;
                    $input .= '<input type="radio" class="btn-check" name="' . e($name) . '" id="' . e($oid) . '" value="' . e($k) . '" autocomplete="off"'
                        . ((string) $k === (string) (is_scalar($value) ? $value : '') ? ' checked' : '') . $attrs . '>'
                        . '<label class="btn" for="' . e($oid) . '">' . e($label) . '</label>';
                }
                $input .= '</div>';
                break;
            case 'checkbox':
                $checked = in_array($value, [1, '1', true, 'on', 'true'], true) ? ' checked' : '';
                $html = '<div class="ap-toggle-row ' . e($f['col']) . '"><div class="ap-toggle-text">'
                    . '<label class="ap-toggle-label" for="' . e($id) . '">' . e($f['label']) . '</label>';
                if ($f['help'] !== '') {
                    $html .= '<div class="form-text mt-0" id="' . e($id) . '_help">' . e($f['help']) . '</div>';
                }
                foreach ($errors as $err) {
                    $html .= '<div class="invalid-feedback d-block">' . e($err) . '</div>';
                }
                return $html . '</div><div class="form-check form-switch m-0"><input type="hidden" name="' . e($name) . '" value="0">'
                    . '<input class="form-check-input' . $invalid . '" type="checkbox" role="switch" id="' . e($id) . '" name="' . e($name) . '" value="1"' . $checked . $describe . $attrs . '>'
                    . '</div></div>';
            case 'color':
                $color = is_string($value) && preg_match('/^#[0-9a-f]{6}$/i', $value) ? $value : '#000000';
                $input = '<div class="ap-color' . $invalid . '"><input type="color" class="form-control form-control-color" id="' . e($id) . '" value="' . e($color) . '" data-color-for="' . e($id) . '_hex" aria-label="' . e($f['label']) . '"' . $attrs . '>'
                    . '<input type="text" class="form-control ap-code" id="' . e($id) . '_hex" name="' . e($name) . '" value="' . e($color) . '" maxlength="7" pattern="#[0-9A-Fa-f]{6}" data-color-hex="' . e($id) . '"' . $describe . $attrs . '></div>';
                break;
            case 'password':
                $placeholder = $f['secret'] ? ($f['saved'] ? __('core.secret_keep') : '') : $f['placeholder'];
                $input = '<div class="input-group"><input type="password" class="form-control' . $invalid . '" id="' . e($id) . '" name="' . e($name) . '" value="" autocomplete="new-password"'
                    . ' placeholder="' . e($placeholder) . '"' . $required . $describe . $attrs . '>'
                    . '<button class="btn btn-outline-secondary" type="button" data-reveal="' . e($id) . '" aria-label="Show or hide" title="Show or hide"><i class="fa-regular fa-eye"></i></button></div>';
                if ($f['secret'] && $f['saved']) {
                    $input .= '<div class="form-check mt-2"><input class="form-check-input" type="checkbox" id="' . e($id) . '_remove" name="' . e($name) . '__remove" value="1"' . $attrs . '>'
                        . '<label class="form-check-label small" for="' . e($id) . '_remove">Remove the saved value</label></div>';
                }
                break;
            case 'repeater':
                $input = self::repeater($f, $id, is_array($value) ? $value : []);
                break;
            default:
                $input = '<input type="' . e($type) . '" class="form-control' . $invalid . '" id="' . e($id) . '" name="' . e($name) . '" value="' . e(is_scalar($value) ? $value : '') . '"'
                    . ' placeholder="' . e($f['placeholder']) . '"' . $required . $describe . $attrs . '>';
        }

        $html = '<div class="mb-3 ap-field ' . e($f['col']) . '" id="' . e($id) . '_wrap">';
        if ($f['label'] !== '') {
            $tag = in_array($type, ['segmented', 'repeater', 'cards'], true) ? 'div' : 'label';
            $html .= '<' . $tag . ($tag === 'label' ? ' for="' . e($id) . '"' : '') . ' id="' . e($id) . '_label" class="form-label">' . e($f['label']);
            if ($f['required']) {
                $html .= ' <span class="text-danger" aria-hidden="true">*</span>';
            }
            if ($f['secret']) {
                $html .= $f['saved']
                    ? ' <span class="ap-secret-state text-success"><i class="fa-solid fa-lock"></i> Saved, encrypted</span>'
                    : ' <span class="ap-secret-state text-muted"><i class="fa-solid fa-lock"></i> Not set</span>';
            }
            $html .= '</' . $tag . '>';
        }
        $html .= $input;
        foreach ($errors as $err) {
            $html .= '<div class="invalid-feedback d-block">' . e($err) . '</div>';
        }
        if ($f['help'] !== '') {
            $html .= '<div class="form-text" id="' . e($id) . '_help">' . e($f['help']) . '</div>';
        }
        return $html . '</div>';
    }

    /** A list of rows the user can add to, remove from and reorder. Posted as name[0][column]. */
    public static function repeater(array $f, string $id, array $rows): string
    {
        $name = (string) $f['name'];
        $fields = Settings::repeaterFields((array) ($f['fields'] ?? []));
        $rows = array_values(array_filter($rows, 'is_array'));
        $max = max(1, (int) ($f['max_rows'] ?? 100));
        $disabled = !empty($f['attrs']['disabled']);
        $addLabel = (string) ($f['add_label'] ?? 'Add a row');

        $html = '<div class="ap-repeater" data-repeater data-name="' . e($name) . '" data-max="' . $max . '" id="' . e($id) . '">';
        $html .= '<div class="ap-rep-head" aria-hidden="true"><span class="ap-rep-grip"></span>';
        foreach ($fields as $col) {
            if ($col['type'] === 'hidden') {
                continue;
            }
            $html .= '<span class="ap-rep-cell' . self::widthClass((string) $col['width']) . '">' . e($col['label']) . '</span>';
        }
        $html .= '<span class="ap-rep-actions"></span></div><div class="ap-rep-rows" data-rows>';
        foreach ($rows as $i => $row) {
            $html .= self::repeaterRow($name, $id, $fields, (string) $i, $row, $disabled);
        }
        $html .= '</div><div class="ap-rep-empty"' . ($rows ? ' hidden' : '') . ' data-empty>Nothing added yet.</div>';
        $html .= '<template data-row-template>' . self::repeaterRow($name, $id, $fields, '__i__', [], $disabled) . '</template>';
        if (!$disabled) {
            $html .= '<button type="button" class="btn btn-sm btn-outline-primary mt-2" data-rep-add' . (count($rows) >= $max ? ' disabled' : '') . '><i class="fa-solid fa-plus me-1"></i>' . e($addLabel) . '</button>';
        }
        return $html . '</div>';
    }

    private static function repeaterRow(string $name, string $id, array $fields, string $i, array $row, bool $disabled): string
    {
        $dis = $disabled ? ' disabled' : '';
        $num = $i === '__i__' ? '__n__' : (string) ((int) $i + 1);
        $html = '<div class="ap-rep-row" data-row>';
        $html .= '<span class="ap-rep-grip" data-rep-handle title="Drag to reorder" aria-hidden="true"><i class="fa-solid fa-grip-vertical"></i></span>';
        foreach ($fields as $key => $col) {
            $fid = $id . '_' . $i . '_' . $key;
            $fname = $name . '[' . $i . '][' . $key . ']';
            $v = (string) ($row[$key] ?? ($i === '__i__' ? ($col['default'] ?? '') : ''));
            if ($col['type'] === 'hidden') {
                $html .= '<input type="hidden" name="' . e($fname) . '" value="' . e($v) . '"' . $dis . '>';
                continue;
            }
            $label = '<label class="visually-hidden" for="' . e($fid) . '">' . e($col['label']) . ', row ' . e($num) . '</label>';
            $cell = '<div class="ap-rep-cell' . self::widthClass((string) $col['width']) . '"><span class="ap-rep-mlabel" aria-hidden="true">' . e($col['label']) . '</span>' . $label;
            switch ($col['type']) {
                case 'select':
                    $cell .= '<select class="form-select form-select-sm" id="' . e($fid) . '" name="' . e($fname) . '"' . $dis . '>';
                    foreach ((array) $col['options'] as $k => $l) {
                        $cell .= '<option value="' . e($k) . '"' . ((string) $k === $v ? ' selected' : '') . '>' . e($l) . '</option>';
                    }
                    $cell .= '</select>';
                    break;
                case 'checkbox':
                    $cell .= '<input type="hidden" name="' . e($fname) . '" value="0"><div class="form-check form-switch m-0"><input class="form-check-input" type="checkbox" role="switch" id="' . e($fid) . '" name="' . e($fname) . '" value="1"' . ($v === '1' ? ' checked' : '') . $dis . '></div>';
                    break;
                default:
                    $t = in_array($col['type'], ['text', 'email', 'url', 'number', 'time', 'date', 'tel'], true) ? $col['type'] : 'text';
                    $cell .= '<input type="' . e($t) . '" class="form-control form-control-sm" id="' . e($fid) . '" name="' . e($fname) . '" value="' . e($v) . '" placeholder="' . e($col['placeholder']) . '"' . $dis . '>';
            }
            $html .= $cell . '</div>';
        }
        $html .= '<span class="ap-rep-actions">';
        if (!$disabled) {
            $html .= '<button type="button" class="btn btn-sm btn-light" data-rep-up aria-label="Move row ' . e($num) . ' up" title="Move up"><i class="fa-solid fa-arrow-up"></i></button>'
                . '<button type="button" class="btn btn-sm btn-light" data-rep-down aria-label="Move row ' . e($num) . ' down" title="Move down"><i class="fa-solid fa-arrow-down"></i></button>'
                . '<button type="button" class="btn btn-sm btn-light text-danger" data-rep-remove aria-label="Remove row ' . e($num) . '" title="Remove"><i class="fa-solid fa-xmark"></i></button>';
        }
        return $html . '</span></div>';
    }

    private static function widthClass(string $width): string
    {
        return match ($width) {
            'xs' => ' w-xs', 'sm' => ' w-sm', 'md' => ' w-md', 'lg' => ' w-lg', default => '',
        };
    }

    public static function id(string $name): string
    {
        return 'f_' . preg_replace('/[^a-z0-9_]/i', '_', $name);
    }

    private static function attrs(array $attrs): string
    {
        $out = '';
        foreach ($attrs as $k => $v) {
            if ($v === false || $v === null) {
                continue;
            }
            $out .= ' ' . e($k) . ($v === true ? '' : '="' . e($v) . '"');
        }
        return $out;
    }

    /** "Save", "Save and stay", "Save and add another" button group. */
    public static function saveButtons(bool $showAdd = false, ?string $cancelUrl = null): string
    {
        $html = '<div class="d-flex flex-wrap gap-2 mt-3"><button type="submit" name="_after" value="save" class="btn btn-primary"><i class="fa-solid fa-floppy-disk me-1"></i>' . e(__('core.save')) . '</button>'
            . '<button type="submit" name="_after" value="stay" class="btn btn-outline-primary">' . e(__('core.save_stay')) . '</button>';
        if ($showAdd) {
            $html .= '<button type="submit" name="_after" value="add" class="btn btn-outline-primary">' . e(__('core.save_add')) . '</button>';
        }
        if ($cancelUrl) {
            $html .= '<a href="' . e($cancelUrl) . '" class="btn btn-link">' . e(__('core.cancel')) . '</a>';
        }
        return $html . '</div>';
    }

    /** The sticky bar at the bottom of settings forms: a note, "Undo changes" and "Save". */
    public static function saveBar(string $note = 'Changes take effect as soon as you save.'): string
    {
        return '<div class="ap-savebar" data-savebar><div class="ap-savebar-note"><i class="fa-solid fa-circle-info me-1"></i><span data-savebar-note>' . e($note) . '</span></div>'
            . '<div class="d-flex gap-2"><button type="button" class="btn btn-outline-secondary" data-undo>' . e(__('core.undo_changes')) . '</button>'
            . '<button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk me-1"></i>' . e(__('core.save')) . '</button></div></div>';
    }
}
