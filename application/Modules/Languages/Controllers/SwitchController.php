<?php
declare(strict_types=1);

namespace AP\Modules\Languages\Controllers;

use AP\Core\Request;
use AP\Core\Response;
use AP\Modules\Languages\Locales;

final class SwitchController
{
    public function switch(Request $request, string $code): Response
    {
        $enabled = Locales::enabled();
        // Language codes are matched case-insensitively (fr-ca / fr-CA).
        foreach ($enabled as $known => $_) {
            if (strcasecmp($known, $code) === 0) {
                Locales::remember($known, $request);
                break;
            }
        }
        // Go back to the page the visitor was on, but only to a path on this site.
        $back = $request->string('back', '/');
        $back = (str_starts_with($back, '/') && !str_starts_with($back, '//') && !str_contains($back, '\\') && !preg_match('/[\r\n]/', $back)) ? $back : '/';
        return redirect($back);
    }
}
