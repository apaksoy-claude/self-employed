<?php declare(strict_types=1);
/**
 * The cards of one settings group. Used by the settings pages and by modules that show their settings on their own pages.
 * @var \AP\Core\View $this @var string $group @var array $sections @var array $meta @var bool $canEdit
 */
use AP\Core\Form;

$sectionIcons = ['Sender' => 'fa-user-pen', 'SMTP server' => 'fa-server', 'Send with' => 'fa-paper-plane', 'Opening hours' => 'fa-clock', 'Signing in' => 'fa-right-to-bracket',
    'Passwords' => 'fa-key', 'Two-step sign-in' => 'fa-shield-halved', 'Look' => 'fa-palette', 'Email log' => 'fa-inbox', 'Look of emails' => 'fa-palette',
    'Maintenance mode' => 'fa-person-digging', 'Who can still see the site' => 'fa-user-check', 'Retries' => 'fa-rotate', 'Language' => 'fa-language', 'Calendar' => 'fa-calendar'];
?>
        <?php
        // The person's own arrangement (see "Arrange"): card order, which cards are half width, and the order and width of fields.
        $lay = \AP\Modules\Settings\Layout::for($group);
        $cardIndex = array_flip(array_keys($sections));
        $sections = \AP\Modules\Settings\Layout::order($sections, $lay);
        ?>
        <div class="ap-cards-grid" data-arrange-cards>
        <?php foreach ($sections as $sectionTitle => $defs): $sid = 'card-' . $group . '-' . $cardIndex[$sectionTitle];
            $cardKey = \AP\Modules\Settings\Layout::cardKey((string) $sectionTitle);
            $cols = (int) ($lay['cards'][$cardKey] ?? 1);
            $defs = \AP\Modules\Settings\Layout::orderFields($defs, $lay, $cardKey);
            $firstDef = reset($defs);
            $secIcon = '';
            $secHelp = '';
            foreach ($defs as $d) {
                $secIcon = $secIcon ?: (string) ($d['section_icon'] ?? '');
                $secHelp = $secHelp ?: (string) ($d['section_help'] ?? '');
            }
            // A section shows only when one of its fields may show (see "show_if").
            $secShow = [];
            foreach ($defs as $d) {
                if (empty($d['show_if'])) { $secShow = []; break; }
                $secShow[] = $d['show_if'];
            }
            ?>
            <section class="ap-section" id="<?= e($sid) ?>" data-card data-card-key="<?= e($cardKey) ?>" data-cols="<?= $cols ?>"<?= $secShow ? ' data-show-if="' . e((string) json_encode(\AP\Modules\Settings\ShowIf::normalise($secShow))) . '"' : '' ?>>
                <div class="ap-arr-bar" data-arr-bar><span class="ap-arr-handle" data-arr-handle tabindex="0" role="button" title="Drag To Move This Card"><i class="fa-solid fa-grip-vertical" aria-hidden="true"></i></span><span class="ap-arr-label">Move This Card</span>
                    <button type="button" class="ap-arr-width" data-arr-width title="Switch Between Full Width And Half Width"><i class="fa-solid fa-table-columns" aria-hidden="true"></i><span><?= $cols === 2 ? 'Half Width' : 'Full Width' ?></span></button></div>
                <button type="button" class="ap-section-head" data-card-toggle aria-expanded="true" aria-controls="<?= e($sid) ?>-body">
                    <h2><i class="fa-solid <?= e($secIcon ?: ($sectionIcons[$sectionTitle] ?? $meta['icon'])) ?>" aria-hidden="true"></i><?= e((string) $sectionTitle) ?></h2>
                    <i class="fa-solid fa-chevron-down ap-chev" aria-hidden="true"></i>
                </button>
                <div class="ap-section-body" id="<?= e($sid) ?>-body">
                    <?php if ($secHelp !== ''): ?><p class="ap-section-intro"><?= e($secHelp) ?></p><?php endif; ?>
                    <div class="ap-fields" data-arrange-fields>
                    <?php foreach ($defs as $key => $def):
                        $field = str_replace('.', '_', $key);
                        $isSecret = (bool) ($def['secret'] ?? false);
                        $current = $isSecret ? '' : settings()->get($key);
                        $cmpCur = is_bool($current) ? (int) $current : (is_array($current) ? json_encode($current) : (string) $current);
                        $cmpDef = is_bool($def['default']) ? (int) $def['default'] : (is_array($def['default']) ? json_encode($def['default']) : (string) $def['default']);
                        $isDefault = $isSecret || (string) $cmpCur === (string) $cmpDef;
                        $full = $def['width'] !== 'half' || in_array($def['type'], ['checkbox', 'repeater', 'textarea', 'cards', 'code'], true);
                        if (isset($lay['fields'][$key])) {
                            $full = $lay['fields'][$key] === 'full';
                        }
                        $showIf = !empty($def['show_if']) ? ' data-show-if="' . e((string) json_encode(\AP\Modules\Settings\ShowIf::normalise([$def['show_if']]))) . '"' : '';
                        ?>
                            <?php if (!empty($def['presets']) && $canEdit): ?>
                                <div class="ap-field-wrap ap-field-full"<?= $showIf ?>><div class="ap-presets"><span class="ap-presets-label"><?= e($def['presets_label']) ?></span>
                                    <?php foreach ((array) $def['presets'] as $pLabel => $pValues): $fill = [];
                                        foreach ((array) $pValues as $pk => $pv) { $fill[str_replace('.', '_', (string) $pk)] = $pv; } ?>
                                        <button type="button" class="ap-preset" data-preset="<?= e((string) json_encode($fill)) ?>"><?= e((string) $pLabel) ?></button>
                                    <?php endforeach; ?>
                                </div><?php if (($def['presets_help'] ?? '') !== ''): ?><div class="form-text mt-0 mb-1"><?= e((string) $def['presets_help']) ?></div><?php endif; ?></div>
                            <?php endif; ?>
                        <div class="ap-field-wrap<?= $full ? ' ap-field-full' : '' ?>" data-field-key="<?= e((string) $key) ?>"<?= $showIf ?>>
                            <div class="ap-arr-field" data-arr-bar><span class="ap-arr-handle" data-arr-handle tabindex="0" role="button" title="Drag To Move This Setting"><i class="fa-solid fa-grip-vertical" aria-hidden="true"></i></span>
                                <button type="button" class="ap-arr-width" data-arr-width title="Switch Between Full Width And Half Width"><i class="fa-solid fa-table-columns" aria-hidden="true"></i><span><?= $full ? 'Full Width' : 'Half Width' ?></span></button></div>
                            <?= Form::field([
                                'name' => $field, 'label' => $def['label'], 'type' => $isSecret && $def['type'] !== 'textarea' ? 'password' : $def['type'], 'secret' => $isSecret,
                                'saved' => $isSecret && settings()->hasValue($key),
                                'value' => is_bool($current) ? (int) $current : $current, 'help' => $def['help'], 'options' => $def['options'], 'placeholder' => $def['placeholder'],
                                'fields' => $def['fields'], 'add_label' => $def['add_label'], 'max_rows' => $def['max_rows'], 'rows' => (int) ($def['rows'] ?? 4),
                                'attrs' => $canEdit ? [] : ['disabled' => true],
                            ]) ?>
                            <?php if ($canEdit && !$isDefault): ?>
                                <div class="ap-field-tools"><button class="btn btn-link btn-sm" type="submit" formaction="<?= e(admin_url('settings/reset')) ?>" formnovalidate name="key" value="<?= e($key) ?>" data-confirm="Put &quot;<?= e($def['label']) ?>&quot; back to how it was when the site was installed?" title="Back to the default"><i class="fa-solid fa-rotate-left me-1"></i>Default</button></div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    </div>
                </div>
            </section>
        <?php endforeach; ?>
        </div>
