<?php
declare(strict_types=1);

namespace AP\Modules\Money\Controllers;

use AP\Core\Request;
use AP\Core\Response;
use AP\Core\View;

/** Shared by the Money forms: each form works as a page and as a pop-up, and every Save button can go to the list or stay. */
final class Forms
{
    /** A form partial shown on its own page, or as bare HTML for the pop-up (?modal=1). */
    public static function page(Request $request, string $partial, array $data, string $title, array $crumbs): Response
    {
        $html = View::render($partial, $data + ['modal' => !empty($request->query['modal'])]);
        if (!empty($request->query['modal'])) {
            return Response::html($html);
        }
        return view('money::page', ['title' => $title, 'breadcrumbs' => $crumbs, 'html' => $html, 'wide' => !empty($data['wide'])]);
    }

    /** @param array<string,list<string>> $errors */
    public static function fail(Request $request, array $errors, string $back): Response
    {
        if ($request->wantsJson()) {
            return Response::json(['ok' => false, 'errors' => $errors], 422);
        }
        return back_with_errors($errors, $request->post, $back);
    }

    /** Where to go after saving: the list (Save) or the record itself (Save and Stay). */
    public static function done(Request $request, string $listUrl, string $stayUrl, string $message): Response
    {
        session()->toast('success', $message);
        $to = $request->string('after') === 'stay' ? $stayUrl : $listUrl;
        return $request->wantsJson() ? Response::json(['ok' => true, 'redirect' => $to]) : redirect($to);
    }

    /** Save and Save and Stay buttons, the same on every form. */
    public static function buttons(string $cancelUrl, bool $modal, string $deleteUrl = '', string $deleteAsk = 'Delete this?'): string
    {
        $h = '<div class="mn-actions"><button class="btn btn-primary mn-btn" type="submit" name="after" value="list" data-loading="Saving…"><i class="fa-solid fa-check me-1"></i>Save</button>'
            . '<button class="btn btn-outline-primary mn-btn" type="submit" name="after" value="stay"><i class="fa-solid fa-floppy-disk me-1"></i>Save &amp; Stay</button>';
        $h .= $modal ? '<button class="btn btn-outline-secondary mn-btn" type="button" data-modal-close>Cancel</button>'
            : '<a class="btn btn-outline-secondary mn-btn" href="' . e($cancelUrl) . '">Cancel</a>';
        if ($deleteUrl !== '') {
            $h .= '<button class="btn btn-outline-danger mn-btn ms-auto" type="submit" formaction="' . e($deleteUrl) . '" formnovalidate data-confirm="' . e($deleteAsk) . '"><i class="fa-solid fa-trash me-1"></i>Delete</button>';
        }
        return $h . '</div>';
    }
}
