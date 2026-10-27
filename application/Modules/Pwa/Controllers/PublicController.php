<?php
declare(strict_types=1);

namespace AP\Modules\Pwa\Controllers;

use AP\Core\HttpException;
use AP\Core\Request;
use AP\Core\Response;
use AP\Modules\Pwa\AppSite;
use AP\Modules\Pwa\Push;

final class PublicController
{
    private function available(): void
    {
        if (!AppSite::enabled() && !Push::ready()) {
            throw new HttpException(404);
        }
    }

    public function manifest(): Response
    {
        $this->available();
        return new Response((string) json_encode(AppSite::manifest(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), 200,
            ['Content-Type' => 'application/manifest+json; charset=utf-8', 'Cache-Control' => 'public, max-age=3600']);
    }

    public function worker(): Response
    {
        $this->available();
        return new Response(AppSite::serviceWorker(), 200, ['Content-Type' => 'application/javascript; charset=utf-8', 'Cache-Control' => 'no-cache']);
    }

    public function icon(): Response
    {
        return new Response(AppSite::fallbackSvg(), 200, ['Content-Type' => 'image/svg+xml', 'Cache-Control' => 'public, max-age=86400']);
    }

    public function offline(): Response
    {
        $this->available();
        return view('pwa::offline', ['title' => __('pwa.offline_title'), 'meta' => ['robots' => 'noindex']]);
    }

    public function subscribe(Request $request): Response
    {
        if (!Push::ready()) {
            return Response::json(['ok' => false, 'message' => 'Notifications are not switched on.'], 409);
        }
        $ok = Push::subscribe($request->string('endpoint'), $request->string('p256dh'), $request->string('auth'), auth_user() ? (int) auth_user()['id'] : null,
            locale(), (string) ($request->server['HTTP_USER_AGENT'] ?? ''));
        return Response::json(['ok' => $ok, 'message' => $ok ? __('pwa.push_thanks') : 'That browser key could not be used.'], $ok ? 200 : 422);
    }

    public function unsubscribe(Request $request): Response
    {
        if (Push::tablesReady()) {
            Push::unsubscribe($request->string('endpoint'));
        }
        return Response::json(['ok' => true]);
    }

    public function click(Request $request, string $id): Response
    {
        if (Push::tablesReady()) {
            Push::db()->table('push_messages')->where('id', (int) $id)->increment('clicks');
        }
        return new Response('', 204);
    }
}
