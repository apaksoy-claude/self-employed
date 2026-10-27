<?php
declare(strict_types=1);

namespace AP\Modules\Money\Controllers;

use AP\Core\Activity;
use AP\Core\HttpException;
use AP\Core\Request;
use AP\Core\Response;
use AP\Modules\Money\Money;

/** Receipts are kept outside the public folder and are only given to a signed-in person who may see money. */
final class FileController
{
    public function show(Request $request, string $id): Response
    {
        $f = Money::db()->table('money_files')->where('id', (int) $id)->first() ?? throw new HttpException(404);
        $path = Money::filesDir() . '/' . basename((string) $f['stored_name']);
        if (!is_file($path)) {
            throw new HttpException(404);
        }
        $mime = (string) ($f['mime'] ?: 'application/octet-stream');
        $inline = in_array($mime, ['application/pdf', 'image/jpeg', 'image/png', 'image/gif', 'image/webp'], true);
        if (strtolower(pathinfo((string) $f['original_name'], PATHINFO_EXTENSION)) === 'html') {
            // A saved email: shown as a page, but locked down so nothing in it can run, load or leave.
            return new Response((string) file_get_contents($path), 200, ['Content-Type' => 'text/html; charset=utf-8', 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, max-age=0',
                'Content-Security-Policy' => "default-src 'none'; img-src data:; style-src 'unsafe-inline'; frame-ancestors 'self'; sandbox allow-popups allow-popups-to-escape-sandbox", 'Referrer-Policy' => 'no-referrer']);
        }
        $name = preg_replace('/[^\w. -]+/u', '_', (string) $f['original_name']) ?: 'file';
        $res = new Response((string) file_get_contents($path), 200, [
            'Content-Type' => $inline ? $mime : 'application/octet-stream',
            'Content-Disposition' => ($inline ? 'inline' : 'attachment') . '; filename="' . $name . '"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=0',
            'Content-Security-Policy' => "default-src 'none'; img-src 'self' data:; style-src 'unsafe-inline'; sandbox",
        ]);
        if ($inline && $mime === 'application/pdf') {
            // Browsers will not show a PDF inside a sandbox; a PDF cannot run page scripts, and nosniff stays on.
            $res = new Response((string) file_get_contents($path), 200, ['Content-Type' => $mime, 'Content-Disposition' => 'inline; filename="' . $name . '"',
                'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, max-age=0']);
        }
        return $res;
    }

    public function delete(Request $request, string $id): Response
    {
        $f = Money::db()->table('money_files')->where('id', (int) $id)->first() ?? throw new HttpException(404);
        Money::db()->table('money_files')->where('id', (int) $id)->delete();
        // The same stored file may be shared by another row; only remove it when nobody else uses it.
        if (!Money::db()->table('money_files')->where('stored_name', $f['stored_name'])->exists()) {
            @unlink(Money::filesDir() . '/' . basename((string) $f['stored_name']));
        }
        Activity::log('money.file.delete', 'Removed the file ' . $f['original_name'], 'money_file', (int) $id);
        if ($request->wantsJson()) {
            return Response::json(['ok' => true, 'message' => 'File removed.']);
        }
        session()->toast('success', 'File removed.');
        return back(admin_url('money'));
    }
}
