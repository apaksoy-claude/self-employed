<?php
declare(strict_types=1);

namespace AP\Modules\Site;

use AP\Core\View;

/**
 * Everything the public layout gets from settings: tracking codes, your own code and CSS, the cookie notice and social links.
 * Code that needs cookie consent is printed as <script type="text/plain" data-ap-consent> and only switched on by
 * assets/js/site.js after the visitor accepts (see Consent).
 */
final class Site
{
    public const SOCIAL = [
        'facebook' => ['Facebook', 'fa-brands fa-facebook-f'], 'instagram' => ['Instagram', 'fa-brands fa-instagram'], 'x' => ['X', 'fa-brands fa-x-twitter'],
        'tiktok' => ['TikTok', 'fa-brands fa-tiktok'], 'linkedin' => ['LinkedIn', 'fa-brands fa-linkedin-in'], 'youtube' => ['YouTube', 'fa-brands fa-youtube'],
        'whatsapp' => ['WhatsApp', 'fa-brands fa-whatsapp'], 'telegram' => ['Telegram', 'fa-brands fa-telegram'], 'pinterest' => ['Pinterest', 'fa-brands fa-pinterest-p'],
        'snapchat' => ['Snapchat', 'fa-brands fa-snapchat'], 'threads' => ['Threads', 'fa-brands fa-threads'],
    ];

    /** Whether visitors are shown a blank page instead of the website. */
    public static function blank(): bool
    {
        return (bool) setting('site.blank', true);
    }

    /** An empty white page that search engines are asked to skip. */
    public static function blankResponse(int $status = 200): \AP\Core\Response
    {
        $r = new \AP\Core\Response('<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>' . e((string) setting('general.site_name', '')) . '</title><style>html,body{margin:0;background:#fff}</style></head><body></body></html>', $status);
        $r->headers['Content-Type'] = 'text/html; charset=utf-8';
        $r->headers['X-Robots-Tag'] = 'noindex, nofollow';
        return $r;
    }

    public static function boot(): void
    {
        events()->addFilter('csp.policy', static fn(string $csp, $request): string => self::csp($csp, $request));
        // An address nobody answers shows the same empty page as the front, so the site gives nothing away.
        events()->addFilter('http.not_found', static function ($alt, $request) {
            $path = trim((string) $request->path, '/');
            $admin = trim(admin_path(), '/');
            if ($alt === null && self::blank() && $path !== $admin && !str_starts_with($path, $admin . '/') && !str_starts_with($path, 'recovery')) {
                return self::blankResponse(404);
            }
            return $alt;
        }, 99);
        View::inject('head', static fn(): string => self::head(), 90);
        View::inject('body_start', static fn(): string => self::bodyStart(), 10);
        View::inject('body_end', static fn(): string => self::bodyEnd(), 90);
        View::inject('footer_social', static fn(): string => setting('social.footer', true) ? self::socialIcons() : '', 10);
        View::inject('footer_links', static fn(): string => self::cookieLink(), 90);
    }

    /** Whether the public pages run code from other websites (tracking codes, chat widgets, your own code). */
    public static function usesOutsideCode(): bool
    {
        foreach (['tracking.gtm_id', 'tracking.meta_pixel', 'tracking.clarity_id', 'tracking.chat_code', 'tracking.head_code', 'tracking.body_code', 'seo.ga4_id'] as $k) {
            if (trim((string) setting($k, '')) !== '') {
                return true;
            }
        }
        return (bool) filter('site.outside_code', false);
    }

    /**
     * Public pages with tracking or your own code need a wider security policy: scripts started by our own (nonce) scripts may
     * load others ('strict-dynamic'), and they may talk to and show frames from other websites. The admin keeps the strict one.
     */
    public static function csp(string $csp, $request): string
    {
        $rel = trim((string) ($request->path ?? ''), '/');
        $admin = trim(admin_path(), '/');
        if ($rel === $admin || str_starts_with($rel, $admin . '/') || !self::usesOutsideCode()) {
            return $csp;
        }
        $csp = (string) preg_replace("/script-src ([^;]*)/", "script-src $1 'strict-dynamic' https: 'unsafe-inline'", $csp, 1);
        $csp = (string) preg_replace("/connect-src 'self'/", "connect-src 'self' https: wss:", $csp, 1);
        $csp = (string) preg_replace("/style-src 'self' 'unsafe-inline'/", "style-src 'self' 'unsafe-inline' https:", $csp, 1);
        $csp = (string) preg_replace("/font-src 'self' data:/", "font-src 'self' data: https:", $csp, 1);
        return $csp . "; frame-src 'self' https:; media-src 'self' https: data: blob:; worker-src 'self' blob:";
    }

    /** Adds this page's nonce to every <script> tag, so pasted code is allowed by the security policy. */
    public static function withNonce(string $html): string
    {
        $nonce = csp_nonce();
        return (string) preg_replace_callback('/<script\b(?![^>]*\snonce=)/i', static fn(): string => '<script nonce="' . e($nonce) . '"', $html);
    }

    /** Tracking codes and code for the page head. */
    public static function head(): string
    {
        $out = '';
        $gtm = (string) setting('tracking.gtm_id', '');
        if (preg_match('/^GTM-[A-Z0-9]{4,12}$/', $gtm)) {
            $out .= Consent::script("<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','" . $gtm . "');</script>") . "\n";
        }
        $pixel = (string) setting('tracking.meta_pixel', '');
        if (preg_match('/^[0-9]{6,20}$/', $pixel)) {
            $out .= Consent::script("<script>!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');fbq('init','" . $pixel . "');fbq('track','PageView');</script>") . "\n";
        }
        $clarity = (string) setting('tracking.clarity_id', '');
        if (preg_match('/^[a-z0-9]{6,20}$/', $clarity)) {
            $out .= Consent::script("<script>(function(c,l,a,r,i,t,y){c[a]=c[a]||function(){(c[a].q=c[a].q||[]).push(arguments)};t=l.createElement(r);t.async=1;t.src='https://www.clarity.ms/tag/'+i;y=l.getElementsByTagName(r)[0];y.parentNode.insertBefore(t,y);})(window,document,'clarity','script','" . $clarity . "');</script>") . "\n";
        }
        $code = trim((string) setting('tracking.head_code', ''));
        if ($code !== '') {
            $out .= (setting('tracking.head_consent', true) ? Consent::script($code) : $code) . "\n";
        }
        $out = self::withNonce($out);
        $css = trim((string) setting('tracking.css', ''));
        if ($css !== '') {
            $out .= '<style id="ap-extra-css">' . str_ireplace('</style', '<\/style', $css) . "</style>\n";
        }
        return $out;
    }

    public static function bodyStart(): string
    {
        $gtm = (string) setting('tracking.gtm_id', '');
        if (!preg_match('/^GTM-[A-Z0-9]{4,12}$/', $gtm) || (Consent::active() && Consent::given() !== true)) {
            return '';
        }
        return '<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=' . e($gtm) . '" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>';
    }

    public static function bodyEnd(): string
    {
        $out = '';
        $chat = trim((string) setting('tracking.chat_code', ''));
        if ($chat !== '') {
            $out .= (setting('tracking.chat_consent', false) ? Consent::script($chat, 'functional') : $chat) . "\n";
        }
        $code = trim((string) setting('tracking.body_code', ''));
        if ($code !== '') {
            $out .= (setting('tracking.body_consent', true) ? Consent::script($code) : $code) . "\n";
        }
        // The notice and the script are added last, once every module had the chance to ask for consent.
        return self::withNonce($out) . Consent::notice() . '<script nonce="' . e(csp_nonce()) . '" src="' . e(asset('js/site.js')) . '?v=' . e(AP_VERSION) . '" defer></script>';
    }

    /** @return list<array{key:string,label:string,icon:string,url:string}> social pages that have an address */
    public static function social(): array
    {
        $out = [];
        foreach (self::SOCIAL as $key => [$label, $icon]) {
            $v = trim((string) setting('social.' . $key, ''));
            if ($v === '') {
                continue;
            }
            if ($key === 'whatsapp' && !preg_match('#^https?://#i', $v)) {
                $digits = preg_replace('/\D+/', '', $v) ?? '';
                if ($digits === '') {
                    continue;
                }
                if (!str_starts_with(ltrim($v), '+') && str_starts_with($digits, '0')) {
                    // A local number: add the country code from the settings.
                    $digits = preg_replace('/\D+/', '', (string) setting('social.whatsapp_country', '44')) . substr($digits, 1);
                }
                $v = 'https://wa.me/' . $digits;
            }
            if (!preg_match('#^https?://#i', $v)) {
                continue;
            }
            $out[] = ['key' => $key, 'label' => $label, 'icon' => $icon, 'url' => $v];
        }
        return $out;
    }

    public static function socialIcons(string $class = 'ap-social'): string
    {
        $links = self::social();
        if (!$links) {
            return '';
        }
        $target = setting('social.new_tab', true) ? ' target="_blank" rel="noopener me"' : ' rel="me"';
        $html = '<ul class="' . e($class) . '">';
        foreach ($links as $l) {
            $html .= '<li><a href="' . e($l['url']) . '"' . $target . ' aria-label="' . e($l['label']) . '" title="' . e($l['label']) . '"><i class="' . e($l['icon']) . '" aria-hidden="true"></i></a></li>';
        }
        return $html . '</ul>';
    }

    public static function cookieLink(): string
    {
        if (!Consent::expected() || !setting('cookies.footer_link', true)) {
            return '';
        }
        return '<a href="#cookie-settings" data-ap-cookie-settings>' . e(__('site.cookie_settings')) . '</a>';
    }
}
