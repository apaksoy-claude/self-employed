<?php
declare(strict_types=1);

namespace AP\Modules\Admin\Controllers;

use AP\Core\Request;
use AP\Core\Response;
use AP\Modules\Admin\Search;

final class SearchController
{
    /** Top-bar search: JSON for the live dropdown, a full page when JavaScript is off or Enter is pressed. */
    public function index(Request $request): Response
    {
        $term = $request->string('q');
        if ($request->wantsJson() || $request->isAjax()) {
            $rows = Search::run($term, 24);
            return Response::json(['term' => $term, 'results' => array_map(static fn($r) => array_diff_key($r, ['score' => 1]), $rows)]);
        }
        $rows = Search::run($term, 100);
        $grouped = [];
        foreach ($rows as $r) {
            $grouped[$r['group']][] = $r;
        }
        return view('admin::search', [
            'title' => 'Search', 'term' => $term, 'grouped' => $grouped, 'count' => count($rows),
            'about' => 'Finds pages, settings and records across the whole admin. Press Ctrl + K anywhere to search.',
            'breadcrumbs' => ['Search' => null],
        ]);
    }
}
