<?php
declare(strict_types=1);

namespace AP\Modules\Site;

/**
 * Cookie consent for the public site.
 *
 * Any module can print code that must wait for consent:
 *     echo \AP\Modules\Site\Consent::script('<script src="https://example.com/tag.js"></script>');
 * Each <script> in it is printed as type="text/plain" with data-ap-consent; site.js turns it into a real script once the
 * visitor accepts. Other HTML (a <noscript> pixel, a <div> for a widget) is left out until then, kept in a <template>.
 * The visitor's answer is kept in the "ap_consent" cookie ("yes" or "no").
 */
final class Consent
{
    private static bool $needed = false;

    /** Whether the notice is used at all ("Never" means code loads for everyone). */
    public static function active(): bool
    {
        return (string) setting('cookies.mode', 'auto') !== 'off';
    }

    /** true = accepted, false = declined, null = not answered yet. */
    public static function given(): ?bool
    {
        $v = $_COOKIE['ap_consent'] ?? null;
        return $v === 'yes' ? true : ($v === 'no' ? false : null);
    }

    public static function needed(): bool
    {
        return self::$needed;
    }

    /** Whether this page will ask for consent: known before the code itself is printed (for the footer link). */
    public static function expected(): bool
    {
        $mode = (string) setting('cookies.mode', 'auto');
        if ($mode === 'off') {
            return false;
        }
        if ($mode === 'always' || self::$needed) {
            return true;
        }
        $uses = (string) setting('tracking.gtm_id', '') !== '' || (string) setting('tracking.meta_pixel', '') !== '' || (string) setting('tracking.clarity_id', '') !== ''
            || (trim((string) setting('tracking.head_code', '')) !== '' && setting('tracking.head_consent', true))
            || (trim((string) setting('tracking.body_code', '')) !== '' && setting('tracking.body_consent', true))
            || (trim((string) setting('tracking.chat_code', '')) !== '' && setting('tracking.chat_consent', false));
        return (bool) filter('consent.expected', $uses);
    }

    public static function reset(): void
    {
        self::$needed = false;
    }

    /** Hold back the code until the visitor accepts cookies. */
    public static function script(string $html, string $category = 'analytics'): string
    {
        if (!self::active()) {
            return $html;
        }
        self::$needed = true;
        $cat = preg_replace('/[^a-z]/', '', strtolower($category)) ?: 'analytics';
        $scripts = '';
        $rest = (string) preg_replace_callback('#<script\b([^>]*)>(.*?)</script>#is', static function (array $m) use (&$scripts, $cat): string {
            $attrs = $m[1];
            $type = '';
            if (preg_match('/\stype\s*=\s*(["\']?)([^"\'\s>]+)\1/i', $attrs, $t)) {
                $type = $t[2];
                $attrs = (string) preg_replace('/\stype\s*=\s*(["\']?)[^"\'\s>]+\1/i', '', $attrs);
            }
            $scripts .= '<script type="text/plain" data-ap-consent="' . $cat . '"' . ($type !== '' ? ' data-type="' . e($type) . '"' : '') . $attrs . '>' . $m[2] . '</script>';
            return '';
        }, $html);
        $rest = trim($rest);
        return $scripts . ($rest !== '' ? '<template data-ap-consent="' . $cat . '">' . $rest . '</template>' : '');
    }

    /** The notice itself; printed hidden and shown by site.js when the visitor has not answered yet. */
    public static function notice(): string
    {
        $mode = (string) setting('cookies.mode', 'auto');
        if ($mode === 'off' || ($mode === 'auto' && !self::$needed)) {
            return '';
        }
        $policy = trim((string) setting('cookies.policy_url', ''));
        if ($policy !== '' && !preg_match('#^https?://#i', $policy)) {
            $policy = url(ltrim($policy, '/'));
        }
        $style = (string) setting('cookies.style', 'bar') === 'box' ? 'box' : 'bar';
        $days = max(1, (int) setting('cookies.days', 180));
        return '<div class="ap-consent ap-consent-' . $style . '" role="dialog" aria-live="polite" aria-label="' . e(__('site.cookie_title')) . '" data-ap-consent-notice data-days="' . $days . '" hidden>'
            . '<div class="ap-consent-inner"><p class="ap-consent-text">' . e((string) setting('cookies.text')) . ($policy !== '' ? ' <a href="' . e($policy) . '">' . e((string) setting('cookies.policy_label', 'Cookie policy')) . '</a>' : '') . '</p>'
            . '<div class="ap-consent-buttons"><button type="button" class="btn btn-sm btn-outline-secondary" data-ap-consent-answer="no">' . e((string) setting('cookies.decline', 'No thanks')) . '</button>'
            . '<button type="button" class="btn btn-sm btn-dark" data-ap-consent-answer="yes">' . e((string) setting('cookies.accept', 'Accept')) . '</button></div></div></div>';
    }
}
