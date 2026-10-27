<?php
declare(strict_types=1);

namespace AP\Modules\Site\Controllers;

use AP\Core\Request;
use AP\Core\Response;

final class HomeController
{
    public function index(Request $request): Response
    {
        if (\AP\Modules\Site\Site::blank()) {
            return \AP\Modules\Site\Site::blankResponse();
        }
        // A module (Pages) can show its own home page instead of the built-in welcome page.
        $custom = filter('site.home', null, $request);
        if ($custom instanceof Response) {
            return $custom;
        }
        return view('site::home', ['title' => (string) setting('general.site_name', 'My Website')]);
    }
}
