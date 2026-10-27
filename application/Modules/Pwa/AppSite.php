<?php
declare(strict_types=1);

namespace AP\Modules\Pwa;

use AP\Core\View;
use AP\Modules\Media\Media;
use Throwable;

/** The website as an app: manifest, service worker, icons, the install button and the "add to home screen" box. */
final class AppSite
{
    public const SIZES = [192, 512];

    public static function boot(): void
    {
        View::inject('head', static fn(): string => self::head(), 30);
        View::inject('nav', static fn(): string => in_array((string) setting('push.button', 'footer'), ['menu', 'both'], true) ? Push::button('nav') : '', 50);
        View::inject('footer_links', static fn(): string => in_array((string) setting('push.button', 'footer'), ['footer', 'both'], true) ? Push::button('footer') : '', 50);
        View::inject('body_end', static fn(): string => self::bodyEnd(), 40);
        View::inject('settings.aside.app', static fn(): string => View::render('pwa::settings_aside', ['status' => self::status(), 'push' => Push::status()]), 10);
        events()->listen('settings.saved', static function (array $keys): void {
            if (array_intersect($keys, ['app.icon', 'app.theme_color', 'app.background_color'])) {
                self::clearIcons();
            }
        });
    }

    public static function enabled(): bool
    {
        return (bool) setting('app.enabled', false);
    }

    public static function name(): string
    {
        $n = trim((string) setting('app.name', ''));
        return $n !== '' ? $n : (string) setting('general.site_name', 'My Website');
    }

    public static function shortName(): string
    {
        $s = trim((string) setting('app.short_name', ''));
        return mb_substr($s !== '' ? $s : self::name(), 0, 12);
    }

    public static function head(): string
    {
        $out = '';
        if (self::enabled() || Push::ready()) {
            $out .= '<link rel="manifest" href="' . e(url('manifest.webmanifest')) . '">' . "\n";
            $out .= '<meta name="theme-color" content="' . e(self::color('app.theme_color', '#2563eb')) . '">' . "\n";
            $icons = self::icons();
            $apple = $icons[192] ?? null;
            if ($apple) {
                $out .= '<link rel="apple-touch-icon" href="' . e($apple) . '">' . "\n";
            }
            $out .= '<meta name="apple-mobile-web-app-capable" content="yes"><meta name="mobile-web-app-capable" content="yes">'
                . '<meta name="apple-mobile-web-app-title" content="' . e(self::shortName()) . '">' . "\n";
        }
        return $out;
    }

    public static function bodyEnd(): string
    {
        if (!self::enabled() && !Push::ready()) {
            return '';
        }
        $cfg = [
            'sw' => url('sw.js'), 'install' => self::enabled() && setting('app.install_button', true),
            'prompt' => self::enabled() && setting('app.prompt', false), 'promptVisits' => max(1, (int) setting('app.prompt_visits', 2)),
            'push' => Push::ready(), 'pushKey' => (string) setting('push.public_key', ''), 'pushUrl' => url('push/subscribe'), 'pushOff' => url('push/unsubscribe'),
            'text' => ['install' => __('pwa.install'), 'promptTitle' => __('pwa.prompt_title', ['name' => self::shortName()]), 'prompt' => (string) setting('app.prompt_text'),
                'ios' => __('pwa.ios_steps'), 'later' => __('pwa.later'), 'pushOn' => __('pwa.push_on'), 'pushOff' => __('pwa.push_off'), 'pushDenied' => __('pwa.push_denied'),
                'pushThanks' => __('pwa.push_thanks')],
        ];
        return '<script type="application/json" id="apAppData">' . json_encode($cfg, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>'
            . (self::enabled() && setting('app.install_button', true) ? '<button type="button" class="ap-install-btn" data-ap-install hidden><i class="fa-solid fa-download" aria-hidden="true"></i><span>' . e(__('pwa.install')) . '</span></button>' : '');
    }

    public static function color(string $key, string $default): string
    {
        $c = (string) setting($key, $default);
        return preg_match('/^#[0-9a-f]{6}$/i', $c) ? $c : $default;
    }

    /** @return array<string,mixed> */
    public static function manifest(): array
    {
        $icons = [];
        foreach (self::icons() as $size => $src) {
            $icons[] = ['src' => $src, 'sizes' => $size . 'x' . $size, 'type' => 'image/png', 'purpose' => 'any'];
        }
        if (isset(self::icons()[512])) {
            $icons[] = ['src' => self::icons()[512], 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'];
        }
        if (!$icons) {
            $icons[] = ['src' => url('app-icon.svg'), 'sizes' => 'any', 'type' => 'image/svg+xml', 'purpose' => 'any'];
        }
        $base = rtrim((string) parse_url(url('/'), PHP_URL_PATH), '/') . '/';
        return (array) filter('app.manifest', [
            'name' => self::name(), 'short_name' => self::shortName(), 'description' => (string) setting('general.tagline', ''),
            'start_url' => $base . '?source=app', 'scope' => $base, 'id' => $base, 'display' => (string) setting('app.display', 'standalone'),
            'background_color' => self::color('app.background_color', '#ffffff'), 'theme_color' => self::color('app.theme_color', '#2563eb'),
            'lang' => locale(), 'dir' => text_direction(), 'icons' => $icons,
        ]);
    }

    /** A plain icon (first letter on the top-bar colour) for when no icon was chosen. */
    public static function fallbackSvg(): string
    {
        $letter = mb_strtoupper(mb_substr(trim(self::name()), 0, 1)) ?: 'A';
        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><rect width="512" height="512" rx="96" fill="' . e(self::color('app.theme_color', '#2563eb')) . '"/>'
            . '<text x="256" y="256" dy=".35em" text-anchor="middle" font-family="Poppins, Arial, sans-serif" font-weight="600" font-size="280" fill="#fff">' . e($letter) . '</text></svg>';
    }

    /** Square PNG copies of the chosen icon, made once. @return array<int,string> size => address */
    public static function icons(): array
    {
        static $cache = [];
        $src = self::localIcon();
        $key = (string) $src . '|' . self::color('app.background_color', '#ffffff');
        if (isset($cache[$key])) {
            return $cache[$key];
        }
        $done = [];
        if ($src === null || !function_exists('imagecreatefromstring')) {
            return $cache[$key] = $done;
        }
        $dir = (class_exists(Media::class) ? Media::root() : AP_BASE . '/assets/uploads') . '/app';
        $stamp = substr(md5($src . '|' . (string) @filemtime($src) . '|' . self::color('app.background_color', '#ffffff')), 0, 8);
        foreach (self::SIZES as $size) {
            $file = $dir . '/icon-' . $size . '-' . $stamp . '.png';
            if (!is_file($file)) {
                try {
                    if (!is_dir($dir)) {
                        @mkdir($dir, 0755, true);
                    }
                    self::makeIcon($src, $file, $size);
                } catch (Throwable) {
                    continue;
                }
            }
            if (is_file($file)) {
                $done[$size] = url('assets/uploads/app/' . basename($file));
            }
        }
        return $cache[$key] = $done;
    }

    /** The icon setting as a file on this server, or null (an outside address cannot be resized). */
    private static function localIcon(): ?string
    {
        $v = trim((string) setting('app.icon', ''));
        if ($v === '') {
            return null;
        }
        $path = (string) parse_url($v, PHP_URL_PATH);
        $pos = strpos($path, '/assets/uploads/');
        if ($pos === false || str_contains($path, '..')) {
            return null;
        }
        $file = AP_BASE . substr($path, $pos);
        return is_file($file) ? $file : null;
    }

    private static function makeIcon(string $src, string $dest, int $size): void
    {
        $img = @imagecreatefromstring((string) file_get_contents($src));
        if (!$img) {
            throw new \RuntimeException('Not a picture.');
        }
        $w = imagesx($img);
        $h = imagesy($img);
        $side = min($w, $h);
        $out = imagecreatetruecolor($size, $size);
        imagealphablending($out, false);
        imagesavealpha($out, true);
        $bg = self::color('app.background_color', '#ffffff');
        $fill = imagecolorallocate($out, (int) hexdec(substr($bg, 1, 2)), (int) hexdec(substr($bg, 3, 2)), (int) hexdec(substr($bg, 5, 2)));
        imagefill($out, 0, 0, $fill);
        imagealphablending($out, true);
        imagecopyresampled($out, $img, 0, 0, (int) (($w - $side) / 2), (int) (($h - $side) / 2), $size, $size, $side, $side);
        imagepng($out, $dest, 6);
        imagedestroy($img);
        imagedestroy($out);
    }

    public static function clearIcons(): void
    {
        $dir = (class_exists(Media::class) ? Media::root() : AP_BASE . '/assets/uploads') . '/app';
        foreach (glob($dir . '/icon-*.png') ?: [] as $f) {
            @unlink($f);
        }
    }

    /** The service worker: offline page, push messages and what happens when one is clicked. */
    public static function serviceWorker(): string
    {
        $offline = setting('app.offline', true) ? url('offline') : '';
        $version = AP_VERSION . '-' . substr(md5((string) setting('app.icon', '') . $offline), 0, 6);
        return "/* AP Framework service worker " . $version . " */\n"
            . "const OFFLINE = " . json_encode($offline, JSON_UNESCAPED_SLASHES) . ";\nconst CACHE = 'ap-" . $version . "';\n"
            . <<<'JS'
self.addEventListener('install', (e) => {
  self.skipWaiting();
  if (OFFLINE) { e.waitUntil(caches.open(CACHE).then((c) => c.add(new Request(OFFLINE, { cache: 'reload' }))).catch(() => {})); }
});
self.addEventListener('activate', (e) => {
  e.waitUntil(caches.keys().then((keys) => Promise.all(keys.filter((k) => k.startsWith('ap-') && k !== CACHE).map((k) => caches.delete(k)))).then(() => self.clients.claim()));
});
self.addEventListener('fetch', (e) => {
  if (!OFFLINE || e.request.mode !== 'navigate') { return; }
  e.respondWith(fetch(e.request).catch(() => caches.match(OFFLINE).then((r) => r || new Response('You are offline.', { headers: { 'Content-Type': 'text/plain' } }))));
});
self.addEventListener('push', (e) => {
  let d = {};
  try { d = e.data ? e.data.json() : {}; } catch (err) { d = { title: e.data ? e.data.text() : '' }; }
  const opts = { body: d.body || '', icon: d.icon || undefined, badge: d.badge || undefined, image: d.image || undefined, data: { url: d.url || '/', click: d.click || '' }, tag: d.tag || undefined };
  e.waitUntil(self.registration.showNotification(d.title || 'New message', opts));
});
self.addEventListener('notificationclick', (e) => {
  e.notification.close();
  const data = e.notification.data || {};
  const go = data.url || '/';
  e.waitUntil((data.click ? fetch(data.click, { method: 'POST', credentials: 'omit', keepalive: true }).catch(() => {}) : Promise.resolve()).then(() =>
    self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((list) => {
      for (const c of list) { if (c.url === go && 'focus' in c) { return c.focus(); } }
      return self.clients.openWindow(go);
    })));
});
JS;
    }

    /** What the settings page shows on the right. @return array<string,mixed> */
    public static function status(): array
    {
        $https = str_starts_with(url('/'), 'https://') || in_array((string) parse_url(url('/'), PHP_URL_HOST), ['localhost', '127.0.0.1'], true);
        $icons = self::icons();
        $problems = [];
        if (!$https) {
            $problems[] = 'The site needs https before phones will install it.';
        }
        if (trim((string) setting('app.icon', '')) === '') {
            $problems[] = 'Choose an app icon (a square picture of at least 512 × 512 pixels).';
        } elseif (!$icons) {
            $problems[] = 'The icon must be a picture in the Media Library (an outside address cannot be resized).';
        }
        return ['enabled' => self::enabled(), 'https' => $https, 'icons' => $icons, 'problems' => $problems, 'manifest' => url('manifest.webmanifest')];
    }
}
