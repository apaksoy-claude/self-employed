<?php
declare(strict_types=1);

namespace AP\Modules\Money\Controllers;

use AP\Core\HttpException;
use AP\Core\Request;
use AP\Core\Response;
use AP\Modules\Money\Help;

/** Money > Help Centre: an article for each part of Money, a search, and the getting-started tour again. */
final class HelpController
{
    public function index(Request $request, string $key = ''): Response
    {
        $all = Help::articles();
        $key = $key !== '' ? $key : 'welcome';
        if (!isset($all[$key])) {
            throw new HttpException(404);
        }
        $q = trim($request->string('q'));
        return view('money::help', ['title' => 'Help Centre', 'breadcrumbs' => ['Money' => admin_url('money/invoices'), 'Help Centre' => null], 'key' => $key, 'a' => $all[$key],
            'groups' => Help::grouped(), 'q' => $q, 'found' => $q !== '' ? Help::search($q, 20) : []]);
    }

    /** The tour was finished or skipped (done=1), or is wanted again (done=0). Works in the background. */
    public function tour(Request $request): Response
    {
        $user = auth_user();
        if ($user) {
            Help::markTour((int) $user['id'], $request->string('done') !== '0');
        }
        if ($request->wantsJson()) {
            return Response::json(['ok' => true, 'message' => $request->string('done') === '0' ? 'The tour will show on the dashboard.' : 'Tour closed. Find it again in the Help Centre.']);
        }
        return redirect($request->string('done') === '0' ? admin_url() : admin_url('money/help'));
    }
}
