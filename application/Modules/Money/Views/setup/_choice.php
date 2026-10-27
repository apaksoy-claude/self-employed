<?php declare(strict_types=1);
/**
 * The questions shared by the installer's Your Business step and Money > Business Setup.
 * @var \AP\Core\View $this
 * @var array<string,mixed> $choice the answers to start from
 * @var string $planJson from Setup::planData()
 * @var string $context install or admin
 */
use AP\Modules\Money\Trades;

$context ??= 'admin';
$hasOld = old('mode', null) !== null;
$mode = (string) ($hasOld ? old('mode', 'ready') : ($choice['mode'] ?? 'ready'));
$picked = array_map('strval', (array) ($hasOld ? old('trades', []) : ($choice['trades'] ?? [])));
$flag = static fn(string $k): bool => $hasOld ? in_array((string) old($k, '0'), ['1', 'on'], true) : !empty($choice[$k]);
$tradeErr = form_errors('trades');
$layoutCustom ??= false;
?>
<div class="su" data-setup data-plan="<?= e($planJson) ?>" data-context="<?= e($context) ?>">
    <?php if ($context === 'install'): ?>
    <div class="su-modes" role="radiogroup" aria-label="How Money Starts">
        <?php foreach (['ready' => ['fa-wand-magic-sparkles', 'Ready For My Kind Of Work', 'Start with categories, income streams, ways of paying and dashboard cards that suit what you do.', 'Recommended'],
            'fresh' => ['fa-seedling', 'Fresh Start', 'Start empty and add your own categories and income streams as you go.', '']] as $k => [$icon, $label, $about, $tag]): ?>
            <label class="su-mode">
                <input type="radio" name="mode" value="<?= e($k) ?>"<?= $mode === $k ? ' checked' : '' ?>>
                <span class="su-mode-icon"><i class="fa-solid <?= e($icon) ?>" aria-hidden="true"></i></span>
                <span class="su-mode-text"><strong><?= e($label) ?></strong><small><?= e($about) ?></small></span>
                <?php if ($tag !== ''): ?><span class="su-tag"><?= e($tag) ?></span><?php endif; ?>
            </label>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <input type="hidden" name="mode" value="ready">
    <?php endif; ?>

    <section class="su-block" data-su-ready<?= $mode === 'fresh' ? ' hidden' : '' ?>>
        <div class="su-head"><h2>What Kind Of Work Do You Do?</h2><p>Pick one or more. Each one adds the costs and income streams that usually go with it.</p></div>
        <?php if ($tradeErr): ?><div class="su-error" role="alert"><?= e((string) (is_array($tradeErr) ? reset($tradeErr) : $tradeErr)) ?></div><?php endif; ?>
        <div class="su-trades">
            <?php foreach (Trades::PACKS as $k => $p): ?>
                <label class="su-trade">
                    <input type="checkbox" name="trades[]" value="<?= e($k) ?>"<?= in_array($k, $picked, true) ? ' checked' : '' ?> data-vehicle="<?= !empty($p['vehicle']) ? '1' : '0' ?>">
                    <span class="su-trade-icon"><i class="fa-solid <?= e($p['icon']) ?>" aria-hidden="true"></i></span>
                    <span class="su-trade-text"><strong><?= e($p['label']) ?></strong><small><?= e($p['about']) ?></small></span>
                    <span class="su-check" aria-hidden="true"><i class="fa-solid fa-check"></i></span>
                </label>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="su-block">
        <div class="su-head"><h2>How You Work</h2><p>You can change these later in Settings > Money.</p></div>
        <div class="su-switches">
            <label class="su-switch">
                <input type="hidden" name="vehicle" value="0"><input class="form-check-input" type="checkbox" role="switch" name="vehicle" value="1"<?= $flag('vehicle') ? ' checked' : '' ?> data-touched="<?= !empty($vehicleTouched) || $hasOld ? '1' : '0' ?>">
                <span><strong>I Use A Vehicle For Work</strong><small>Shows Vehicles and the Mileage Log, and adds running costs such as fuel, insurance and parking.</small></span>
            </label>
            <label class="su-switch">
                <input type="hidden" name="stock" value="0"><input class="form-check-input" type="checkbox" role="switch" name="stock" value="1"<?= $flag('stock') ? ' checked' : '' ?>>
                <span><strong>I Sell Goods I Keep In Stock</strong><small>Shows Money > Stock: what is left, what it cost, low stock and the stock count.</small></span>
            </label>
            <label class="su-switch">
                <input type="hidden" name="cis" value="0"><input class="form-check-input" type="checkbox" role="switch" name="cis" value="1"<?= $flag('cis') ? ' checked' : '' ?>>
                <span><strong>Contractors Pay Me Under CIS</strong><small>For building work under the Construction Industry Scheme: invoices take the deduction off labour, and it comes off your tax bill.</small></span>
            </label>
            <label class="su-switch" data-su-ready<?= $mode === 'fresh' ? ' hidden' : '' ?>>
                <input type="hidden" name="home" value="0"><input class="form-check-input" type="checkbox" role="switch" name="home" value="1"<?= $flag('home') ? ' checked' : '' ?>>
                <span><strong>I Work From Home</strong><small>Adds the business share of household bills, such as heating, broadband and council tax.</small></span>
            </label>
        </div>
    </section>

    <section class="su-block" data-su-ready<?= $mode === 'fresh' ? ' hidden' : '' ?>>
        <div class="su-head su-head-row"><div><h2>What Will Be Added</h2><p>Untick anything you do not want. You can rename, refile or delete any of it later.</p></div><span class="su-count" data-su-count></span></div>
        <div class="su-preview" data-su-preview aria-live="polite">
            <p class="su-empty">Pick a kind of work above to see what will be added.</p>
            <noscript><p class="su-empty">Everything that goes with the kinds of work you pick will be added.</p></noscript>
        </div>
        <label class="su-switch su-dash">
            <input type="hidden" name="dashboard" value="0"><input class="form-check-input" type="checkbox" role="switch" name="dashboard" value="1"<?= ($hasOld ? $flag('dashboard') : !empty($choice['dashboard'])) ? ' checked' : '' ?>>
            <span><strong>Set The Dashboard Cards For This Kind Of Work</strong><small>Puts the cards that matter most for your work first, with a card or two of its own, such as fuel this month for drivers.<?= $layoutCustom ? ' This replaces the cards you arranged yourself.' : '' ?></small></span>
        </label>
    </section>

    <div class="su-fresh" data-su-fresh<?= $mode === 'fresh' ? '' : ' hidden' ?>>
        <i class="fa-solid fa-seedling" aria-hidden="true"></i>
        <p><strong>Nothing will be added.</strong> Money starts empty, ready for your own categories and income streams. You can add a ready-made set at any time from Money > Business Setup.</p>
    </div>
</div>
