<?php
declare(strict_types=1);

namespace AP\Modules\Media\Controllers;

use AP\Core\HttpException;
use AP\Core\Request;
use AP\Core\Response;
use AP\Modules\Media\Galleries;

/** A published gallery on a page of its own at /gallery/the-name. */
final class PublicGalleryController
{
    public function show(Request $request, string $slug): Response
    {
        if (!Galleries::ready()) {
            throw new HttpException(404);
        }
        $g = Galleries::find($slug) ?? throw new HttpException(404);
        return view('media::public.gallery', ['title' => (string) $g['name'], 'g' => $g, 'html' => Galleries::render($g),
            'meta' => ['description' => mb_substr(trim(strip_tags((string) ($g['description'] ?? ''))), 0, 200), 'canonical' => url('gallery/' . $g['slug'])]]);
    }
}
