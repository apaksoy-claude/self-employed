<?php
declare(strict_types=1);

namespace AP\Modules\Seo\Controllers;

use AP\Core\HttpException;
use AP\Core\Response;
use AP\Modules\Seo\Seo;

final class PublicController
{
    public function sitemap(): Response
    {
        if (!setting('seo.sitemap', true)) {
            throw new HttpException(404);
        }
        $xml = \AP\Core\Cache::instance()->remember('sitemap', static fn(): string => Seo::sitemap(), 900, 'seo');
        return new Response((string) $xml, 200, ['Content-Type' => 'application/xml; charset=utf-8', 'X-Robots-Tag' => 'noindex']);
    }

    public function robots(): Response
    {
        return new Response(Seo::robots(), 200, ['Content-Type' => 'text/plain; charset=utf-8']);
    }
}
