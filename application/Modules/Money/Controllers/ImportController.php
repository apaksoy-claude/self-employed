<?php
declare(strict_types=1);

namespace AP\Modules\Money\Controllers;

use AP\Core\Activity;
use AP\Core\Request;
use AP\Core\Response;
use AP\Modules\Money\Import\RiseImporter;
use AP\Modules\Money\Import\SqlDumpReader;
use AP\Modules\Money\Money;
use ZipArchive;

/**
 * Brings records over from RISE CRM. The SQL backup (and the zip of the timeline_files folder) can be uploaded here,
 * or put by FTP in application/storage/money/import when they are bigger than the server accepts.
 */
final class ImportController
{
    private const MAX_ZIP_FILES = 20000;
    private const MAX_ZIP_BYTES = 2147483648;

    public static function dropDir(): string
    {
        $dir = Money::filesDir() . '/import';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return $dir;
    }

    public function index(Request $request): Response
    {
        if (!Money::ready()) {
            return Money::notReady();
        }
        $drop = self::dropDir();
        $waiting = glob($drop . '/*.sql') ?: [];
        $already = Money::db()->table('money_import_map')->where('source', 'rise')->count();
        return view('money::import', ['title' => 'Import', 'breadcrumbs' => ['Money' => admin_url('money/invoices'), 'Import' => null], 'waiting' => array_map('basename', $waiting),
            'dropPath' => 'application/storage/money/import', 'already' => $already, 'report' => session()->flashed('money_report'), 'maxMb' => (int) round(\AP\Core\Upload::maxBytes() / 1048576)]);
    }

    public function run(Request $request): Response
    {
        @set_time_limit(0);
        @ini_set('memory_limit', '512M');
        $drop = self::dropDir();
        $tmp = null;
        $dumpPath = null;
        $up = $request->files['dump'] ?? null;
        if (is_array($up) && (int) ($up['error'] ?? 4) === UPLOAD_ERR_OK) {
            if (strtolower(pathinfo((string) $up['name'], PATHINFO_EXTENSION)) !== 'sql' || !is_uploaded_file((string) $up['tmp_name'])) {
                return $this->fail('Choose the .sql backup file made by phpMyAdmin.');
            }
            $dumpPath = (string) $up['tmp_name'];
        } elseif (($name = basename($request->string('server_dump'))) !== '' && is_file($drop . '/' . $name) && str_ends_with(strtolower($name), '.sql')) {
            $dumpPath = $drop . '/' . $name;
        }
        if ($dumpPath === null) {
            return $this->fail('Choose the .sql backup file, or put it in the import folder first.');
        }
        $attachDir = null;
        $zip = $request->files['attachments'] ?? null;
        if (is_array($zip) && (int) ($zip['error'] ?? 4) === UPLOAD_ERR_OK) {
            if (strtolower(pathinfo((string) $zip['name'], PATHINFO_EXTENSION)) !== 'zip' || !is_uploaded_file((string) $zip['tmp_name'])) {
                return $this->fail('The attachments must be a .zip file.');
            }
            $tmp = $drop . '/_unzip-' . bin2hex(random_bytes(4));
            $err = self::unzipFlat((string) $zip['tmp_name'], $tmp);
            if ($err !== null) {
                self::removeDir($tmp);
                return $this->fail($err);
            }
            $attachDir = $tmp;
        } elseif ($request->bool('use_server_files')) {
            $attachDir = $drop;
        }
        try {
            $dump = new SqlDumpReader($dumpPath);
            if (!$dump->hasTable('rise_expenses') && !$dump->hasTable('rise_invoices')) {
                return $this->fail('This does not look like a RISE CRM backup: there are no expenses or invoices in it.');
            }
            $report = (new RiseImporter(Money::db(), Money::filesDir()))->import($dump, $attachDir);
        } catch (\Throwable $e) {
            \AP\Core\Logger::warning('Money import failed: ' . $e->getMessage());
            return $this->fail('The import stopped: ' . $e->getMessage() . ' Nothing was lost; you can run it again.');
        } finally {
            if ($tmp !== null) {
                self::removeDir($tmp);
            }
        }
        Activity::log('money.import', 'Imported records from RISE CRM');
        session()->flash('money_report', $report);
        session()->toast('success', 'Import finished. Check the numbers below against RISE.');
        return redirect(admin_url('money/import'));
    }

    private function fail(string $message): Response
    {
        session()->toast('danger', $message);
        return redirect(admin_url('money/import'));
    }

    /** Unpacks every file into one folder using only the file name, so a hostile zip cannot write elsewhere. */
    public static function unzipFlat(string $zipPath, string $to): ?string
    {
        if (!class_exists(ZipArchive::class)) {
            return 'This server has no zip support (PHP "zip" extension). Ask your host to switch it on, or put the files in the import folder.';
        }
        $z = new ZipArchive();
        if ($z->open($zipPath) !== true) {
            return 'The zip file could not be opened.';
        }
        if ($z->numFiles > self::MAX_ZIP_FILES) {
            return 'The zip has too many files.';
        }
        @mkdir($to, 0775, true);
        $bytes = 0;
        for ($i = 0; $i < $z->numFiles; $i++) {
            $st = $z->statIndex($i);
            $name = basename(str_replace('\\', '/', (string) ($st['name'] ?? '')));
            if ($name === '' || str_ends_with((string) $st['name'], '/') || $name[0] === '.' || preg_match('/\.(php\d?|phtml|phar|htaccess)$/i', $name)) {
                continue;
            }
            $bytes += (int) $st['size'];
            if ($bytes > self::MAX_ZIP_BYTES) {
                return 'The zip is too big when unpacked.';
            }
            $in = $z->getStream((string) $st['name']);
            if ($in === false) {
                continue;
            }
            $out = fopen($to . '/' . $name, 'wb');
            if ($out !== false) {
                stream_copy_to_stream($in, $out);
                fclose($out);
            }
            fclose($in);
        }
        $z->close();
        return null;
    }

    public static function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) ?: [] as $f) {
            if ($f !== '.' && $f !== '..') {
                is_dir($dir . '/' . $f) ? self::removeDir($dir . '/' . $f) : @unlink($dir . '/' . $f);
            }
        }
        @rmdir($dir);
    }
}
