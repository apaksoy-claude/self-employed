<?php
declare(strict_types=1);

namespace AP\Modules\System\Controllers;

use AP\Core\Activity;
use AP\Core\DB;
use AP\Core\HttpException;
use AP\Core\Kernel;
use AP\Core\Request;
use AP\Core\Response;
use AP\Core\Updater;

/** System > Updates: upload a package, check it, install it, go back, and see what happened before. */
final class UpdateController
{
    private function updater(): Updater
    {
        return new Updater(app(DB::class));
    }

    public function index(Request $request): Response
    {
        $u = $this->updater();
        $history = $u->history();
        $last = null;
        foreach ($history as $h) {
            if (($h['status'] ?? '') === 'installed') {
                $last = $h;
                break;
            }
        }
        $limit = min(self::bytes((string) ini_get('upload_max_filesize')), self::bytes((string) ini_get('post_max_size')));
        return view('system::updates', [
            'title' => 'Updates', 'breadcrumbs' => ['System' => admin_url('system'), 'Updates' => null],
            'version' => AP_VERSION, 'last' => $last, 'history' => array_slice($history, 0, 100), 'historyTotal' => count($history),
            'backups' => $u->backups(), 'target' => $u->rollbackTarget(), 'pending' => $u->pending(),
            'limit' => $limit > 0 ? format_bytes($limit) : 'unlimited', 'zip' => class_exists(\ZipArchive::class),
            'keep' => (int) setting('updates.keep_backups', 5),
        ]);
    }

    /** Step 1: receive the zip and check it. Nothing is changed on the site. */
    public function upload(Request $request): Response
    {
        $file = $request->files['package'] ?? null;
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $why = match ((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE)) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'The file is bigger than your server allows (' . min(self::bytes((string) ini_get('upload_max_filesize')), self::bytes((string) ini_get('post_max_size'))) / 1048576 . ' MB). Ask your host to raise "upload_max_filesize" and "post_max_size".',
                UPLOAD_ERR_NO_FILE => 'Choose the update package (a .zip file) first.',
                default => 'The upload did not finish. Please try again.',
            };
            return Response::json(['ok' => false, 'errors' => [$why], 'checks' => []]);
        }
        if (!str_ends_with(strtolower((string) ($file['name'] ?? '')), '.zip')) {
            return Response::json(['ok' => false, 'errors' => ['The update package must be a .zip file.'], 'checks' => []]);
        }
        try {
            $staged = $this->updater()->stage((string) $file['tmp_name']);
        } catch (\Throwable $e) {
            return Response::json(['ok' => false, 'errors' => [$e->getMessage()], 'checks' => []]);
        }
        $r = $staged['result'];
        return Response::json([
            'ok' => $r['ok'], 'id' => $r['ok'] ? $staged['id'] : null, 'errors' => $r['errors'], 'checks' => $r['checks'], 'summary' => $r['summary'],
            'title' => (string) ($r['manifest']['title'] ?? ''), 'notes' => (string) ($r['manifest']['notes'] ?? ''), 'file' => (string) $file['name'],
        ]);
    }

    /** Step 2: back up and replace the files. The database part follows in finish(). */
    /** "Ask for my password before installing updates" (Settings > Security). */
    private function passwordProblem(Request $request): ?Response
    {
        if (!setting('updates.confirm_password', false)) {
            return null;
        }
        $hash = (string) app(\AP\Core\DB::class)->table('users')->where('id', (int) auth_user()['id'])->value('password');
        if ($request->string('password') === '' || !password_verify((string) $request->input('password'), $hash)) {
            return Response::json(['ok' => false, 'error' => 'Type your password in the box at the top of the page first. It did not match.', 'log' => []]);
        }
        return null;
    }

    public function install(Request $request): Response
    {
        if ($bad = $this->passwordProblem($request)) {
            return $bad;
        }
        $result = $this->updater()->install($request->string('id'), (string) (auth_user()['name'] ?? ''));
        return Response::json($result);
    }

    /** Step 3 (a fresh request, running the new code): database updates, permissions, caches. */
    public function finish(Request $request): Response
    {
        $u = $this->updater();
        $pending = $u->pending();
        $result = $u->finish(static fn(): array => Kernel::finishUpdate(app()));
        if ($result['ok'] && $pending) {
            Activity::log(($pending['kind'] ?? '') === 'rollback' ? 'system.update.rollback' : 'system.update', ($pending['kind'] ?? '') === 'rollback'
                ? 'Went back to version ' . $pending['to'] : 'Updated from ' . $pending['from'] . ' to ' . $pending['to']);
        }
        return Response::json($result);
    }

    public function rollback(Request $request): Response
    {
        if ($bad = $this->passwordProblem($request)) {
            return $bad;
        }
        $u = $this->updater();
        $target = $u->rollbackTarget();
        if ($target === null || $target['name'] !== $request->string('backup')) {
            return Response::json(['ok' => false, 'error' => 'There is no earlier version to go back to.', 'log' => []]);
        }
        return Response::json($u->rollback((string) $target['name'], (string) (auth_user()['name'] ?? '')));
    }

    public function deleteBackup(Request $request, string $name): Response
    {
        $ok = $this->updater()->deleteBackup($name);
        if ($ok) {
            Activity::log('system.update.backup_delete', 'Deleted the update backup ' . $name);
        }
        session()->toast($ok ? 'success' : 'danger', $ok ? 'Backup deleted.' : 'That backup cannot be deleted (it is the one "Go back" needs, or it no longer exists).');
        return redirect(admin_url('system/updates'));
    }

    public function pruneBackups(Request $request): Response
    {
        $n = $this->updater()->prune();
        session()->toast('success', $n ? $n . ' older backup(s) deleted.' : 'Nothing to delete.');
        return redirect(admin_url('system/updates'));
    }

    public function downloadDb(Request $request, string $file): Response
    {
        $path = AP_STORAGE . '/backups/' . basename($file);
        if (preg_match('/^db-[0-9-]+-v[0-9.]+\.sql\.gz$/', $file) !== 1 || !is_file($path)) {
            throw new HttpException(404);
        }
        Activity::log('system.update.db_download', 'Downloaded the database backup ' . $file);
        return Response::download((string) file_get_contents($path), $file, 'application/gzip');
    }

    private static function bytes(string $v): int
    {
        $v = trim($v);
        if ($v === '' || $v === '-1') {
            return 0;
        }
        $n = (int) $v;
        return match (strtolower(substr($v, -1))) {
            'g' => $n * 1073741824, 'm' => $n * 1048576, 'k' => $n * 1024, default => $n,
        };
    }
}
