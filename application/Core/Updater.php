<?php
declare(strict_types=1);

namespace AP\Core;

use ZipArchive;

/**
 * Installs update packages (zip files) safely.
 *
 * A package is a zip with an `ap-update.json` manifest listing every file with its SHA-256 checksum. The updater
 *   1. inspects the package without changing anything (structure, safe paths, checksums, versions, permissions),
 *   2. backs up the database and every file it is about to replace,
 *   3. writes the new files (each one checked again after writing) and puts the old files back if anything fails,
 *   4. leaves the database part (migrations) to a second request that runs the NEW code (see finish()).
 * Files that belong to the site (config, storage, uploads, .htaccess) are never touched.
 * Its own records are plain files in application/storage/updates so rolling back works even if the database does not.
 */
final class Updater
{
    public const MANIFEST = 'ap-update.json';
    public const MAX_FILES = 5000;
    public const MAX_BYTES = 268435456; // 256 MB once unpacked

    /** Top-level folders and files a package may contain. */
    private const ALLOWED_DIRS = ['application', 'assets', 'tools', 'docs'];
    private const ALLOWED_ROOT_FILES = ['index.php', 'ap', 'README.md', 'CHANGELOG.md'];
    /** Paths that belong to the site and are never replaced or deleted (a package that lists them just has them skipped). */
    private const PROTECTED = ['application/config/', 'application/storage/', 'uploads/', 'assets/uploads/', '.htaccess', '.env'];
    private const EXTENSIONS = ['php', 'js', 'css', 'json', 'md', 'txt', 'html', 'svg', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'ico', 'woff', 'woff2', 'ttf', 'eot', 'map', 'xml'];

    private string $base;
    private string $root;
    private string $storage;
    private string $version;

    public function __construct(private ?DB $db = null, ?string $base = null, ?string $root = null, ?string $storage = null, ?string $version = null)
    {
        $this->base = rtrim($base ?? (defined('AP_PUBLIC') ? AP_PUBLIC : AP_BASE), '/');
        $this->root = rtrim($root ?? AP_ROOT, '/');
        $this->storage = rtrim($storage ?? AP_STORAGE, '/');
        $this->version = $version ?? AP_VERSION;
    }

    public function version(): string
    {
        return $this->version;
    }

    /* ------------------------------------------------------------------ folders and records */

    public function dir(string $sub = ''): string
    {
        $dir = $this->storage . '/updates' . ($sub !== '' ? '/' . $sub : '');
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new \RuntimeException('Could not create ' . $dir . '. Check that the storage folder is writable.');
        }
        return $dir;
    }

    /** @return array<int, array<string,mixed>> newest first */
    public function history(): array
    {
        $file = $this->storage . '/updates/history.json';
        $data = is_file($file) ? json_decode((string) file_get_contents($file), true) : [];
        return is_array($data) ? $data : [];
    }

    private function saveHistory(array $rows): void
    {
        $this->dir();
        file_put_contents($this->storage . '/updates/history.json', json_encode(array_slice(array_values($rows), 0, 500), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), LOCK_EX);
    }

    private function addHistory(array $entry): void
    {
        $rows = $this->history();
        array_unshift($rows, $entry);
        $this->saveHistory($rows);
    }

    private function updateHistory(string $id, array $changes): void
    {
        $rows = $this->history();
        foreach ($rows as &$r) {
            if (($r['id'] ?? '') === $id) {
                $r = $changes + $r;
            }
        }
        unset($r);
        $this->saveHistory($rows);
    }

    /* ------------------------------------------------------------------ inspecting a package */

    /**
     * Look inside a package and report whether it can be installed. Changes nothing.
     * @return array{ok:bool,checks:list<array{ok:bool,label:string,detail:string}>,errors:list<string>,manifest:array,plan:array,summary:array}
     */
    public function inspect(string $zipPath): array
    {
        $checks = [];
        $errors = [];
        $plan = ['write' => [], 'delete' => [], 'keep' => []];
        $manifest = [];
        $summary = ['from' => $this->version, 'to' => '', 'add' => 0, 'replace' => 0, 'delete' => 0, 'keep' => 0, 'bytes' => 0];
        $check = function (bool $ok, string $label, string $detail = '', bool $fatal = true) use (&$checks, &$errors): bool {
            $checks[] = ['ok' => $ok, 'label' => $label, 'detail' => $detail];
            if (!$ok && $fatal) {
                $errors[] = $detail !== '' ? $detail : $label;
            }
            return $ok;
        };
        $result = static function () use (&$checks, &$errors, &$manifest, &$plan, &$summary): array {
            return ['ok' => !$errors, 'checks' => $checks, 'errors' => $errors, 'manifest' => $manifest, 'plan' => $plan, 'summary' => $summary];
        };

        if (!$check(class_exists(ZipArchive::class), 'Zip support', 'This server has no zip support (PHP "zip" extension). Ask your host to switch it on.')) {
            return $result();
        }
        $zip = new ZipArchive();
        $opened = is_file($zipPath) ? $zip->open($zipPath) : -1;
        if (!$check($opened === true, 'Readable zip file', 'That file is not a valid zip file.')) {
            return $result();
        }
        try {
            $count = $zip->numFiles;
            $total = 0;
            for ($i = 0; $i < $count; $i++) {
                $total += (int) ($zip->statIndex($i)['size'] ?? 0);
            }
            if (!$check($count > 0 && $count <= self::MAX_FILES && $total <= self::MAX_BYTES, 'Sensible size', 'The package is empty or unusually large (' . $count . ' files, ' . format_bytes($total) . ').')) {
                return $result();
            }
            $summary['bytes'] = $total;

            $raw = $zip->getFromName(self::MANIFEST);
            $m = $raw !== false ? json_decode($raw, true) : null;
            if (!$check(is_array($m), 'Update information (' . self::MANIFEST . ')', 'This is not an AP Framework update package (the file ' . self::MANIFEST . ' is missing or damaged).')) {
                return $result();
            }
            $manifest = $m;
            $version = (string) ($m['version'] ?? '');
            $files = $m['files'] ?? null;
            $okShape = ($m['format'] ?? '') === 'ap-update' && ($m['product'] ?? '') === 'ap-framework' && preg_match('/^\d+\.\d+\.\d+$/', $version) === 1
                && is_array($files) && $files !== [] && is_array($m['delete'] ?? []);
            if (!$check($okShape, 'Package is for AP Framework', 'The update information is incomplete or belongs to a different product.')) {
                return $result();
            }
            $summary['to'] = $version;

            // Versions
            $newer = version_compare($version, $this->version, '>');
            $check($newer, 'Newer than the installed version', $version === $this->version
                ? 'Version ' . $version . ' is already installed.'
                : 'This package (' . $version . ') is older than the installed version (' . $this->version . ').');
            $minFrom = (string) ($m['min_from'] ?? '');
            if ($minFrom !== '') {
                $check(version_compare($this->version, $minFrom, '>='), 'Fits your current version', 'This package can only be installed on version ' . $minFrom . ' or newer. Install the earlier update first.');
            }
            $minPhp = (string) ($m['min_php'] ?? '');
            if ($minPhp !== '') {
                $check(version_compare(PHP_VERSION, $minPhp, '>='), 'PHP version', 'This update needs PHP ' . $minPhp . ' or newer (this server runs ' . PHP_VERSION . ').');
            }

            // Paths and checksums
            $listed = [];
            $badPaths = [];
            foreach ($files as $path => $sha) {
                $path = (string) $path;
                if (!is_string($sha) || preg_match('/^[a-f0-9]{64}$/', $sha) !== 1 || !self::safePath($path)) {
                    $badPaths[] = $path;
                    continue;
                }
                $listed[$path] = $sha;
            }
            foreach ((array) ($m['delete'] ?? []) as $path) {
                if (!is_string($path) || !self::safePath($path)) {
                    $badPaths[] = (string) $path;
                }
            }
            $check(!$badPaths, 'Safe file paths', 'The package lists files it is not allowed to touch: ' . implode(', ', array_slice($badPaths, 0, 5)) . (count($badPaths) > 5 ? '…' : ''));
            if ($badPaths) {
                return $result();
            }
            $extra = [];
            for ($i = 0; $i < $count; $i++) {
                $name = (string) $zip->getNameIndex($i);
                if ($name === self::MANIFEST || str_ends_with($name, '/')) {
                    continue;
                }
                if (!isset($listed[$name])) {
                    $extra[] = $name;
                }
            }
            $missing = [];
            foreach ($listed as $path => $sha) {
                if ($zip->locateName($path) === false) {
                    $missing[] = $path;
                }
            }
            $check(!$extra && !$missing, 'Every file is accounted for', $extra ? 'The package contains files that are not in its list: ' . implode(', ', array_slice($extra, 0, 5)) : 'Files listed in the package are missing from it: ' . implode(', ', array_slice($missing, 0, 5)));
            if ($extra || $missing) {
                return $result();
            }

            $bad = [];
            foreach ($listed as $path => $sha) {
                if (self::hashEntry($zip, $path) !== $sha) {
                    $bad[] = $path;
                }
            }
            $check(!$bad, 'Checksums match (' . count($listed) . ' files)', 'These files are damaged or have been changed: ' . implode(', ', array_slice($bad, 0, 5)) . '. Download the package again.');
            if ($bad) {
                return $result();
            }

            $versionFile = $zip->getFromName('application/VERSION');
            $check($versionFile !== false && trim($versionFile) === $version, 'Version file matches', 'The package does not carry its own version number correctly.');

            // What would change
            foreach ($listed as $path => $sha) {
                if (self::isProtected($path)) {
                    $plan['keep'][] = $path;
                    continue;
                }
                $target = $this->target($path);
                if (is_file($target)) {
                    if (hash_file('sha256', $target) !== $sha) {
                        $plan['write'][$path] = $sha;
                        $summary['replace']++;
                    }
                } else {
                    $plan['write'][$path] = $sha;
                    $summary['add']++;
                }
            }
            foreach ((array) ($m['delete'] ?? []) as $path) {
                if (self::isProtected((string) $path)) {
                    continue;
                }
                if (is_file($this->target((string) $path))) {
                    $plan['delete'][] = (string) $path;
                }
            }
            $summary['delete'] = count($plan['delete']);
            $summary['keep'] = count($plan['keep']);
            // The version file is always written last, even when the rest looks identical.
            $plan['write']['application/VERSION'] = $listed['application/VERSION'] ?? '';

            // Can we write?
            $blocked = [];
            foreach (array_merge(array_keys($plan['write']), $plan['delete']) as $path) {
                $target = $this->target($path);
                $probe = is_file($target) ? $target : $this->nearestDir($target);
                if ($probe === null || !is_writable($probe)) {
                    $blocked[] = $path;
                }
            }
            $check(!$blocked, 'Files can be replaced', 'The server does not allow changing: ' . implode(', ', array_slice($blocked, 0, 4)) . (count($blocked) > 4 ? '…' : '') . '. Fix the file permissions in your hosting panel.');

            $free = @disk_free_space($this->storage);
            if ($free !== false) {
                $check($free > $total * 3, 'Enough disk space', 'There is not enough free disk space (' . format_bytes($free) . ' left, about ' . format_bytes($total * 3) . ' needed for the update and its backup).');
            }
        } finally {
            $zip->close();
        }
        return $result();
    }

    /** Move an uploaded file into place, inspect it and remember the result. @return array{id:string,result:array} */
    public function stage(string $tmpFile, bool $isUpload = true): array
    {
        $in = $this->dir('incoming');
        foreach (glob($in . '/*') ?: [] as $old) {
            if (is_file($old) && filemtime($old) < time() - 86400) {
                @unlink($old);
            }
        }
        $id = bin2hex(random_bytes(8));
        $dest = $in . '/' . $id . '.zip';
        $moved = $isUpload ? move_uploaded_file($tmpFile, $dest) : copy($tmpFile, $dest);
        if (!$moved) {
            throw new \RuntimeException('Could not store the uploaded file. Check that the storage folder is writable.');
        }
        $result = $this->inspect($dest);
        file_put_contents($in . '/' . $id . '.json', json_encode(['at' => gmdate('c'), 'ok' => $result['ok'], 'summary' => $result['summary'], 'name' => $result['manifest']['title'] ?? ''], JSON_UNESCAPED_UNICODE));
        if (!$result['ok']) {
            @unlink($dest);
        }
        return ['id' => $id, 'result' => $result];
    }

    public function stagedPath(string $id): ?string
    {
        if (preg_match('/^[a-f0-9]{16}$/', $id) !== 1) {
            return null;
        }
        $file = $this->dir('incoming') . '/' . $id . '.zip';
        return is_file($file) ? $file : null;
    }

    /* ------------------------------------------------------------------ installing */

    /**
     * Phase 1: back up, then replace the files. The database part is done by finish(), in a fresh request,
     * so the migrations run with the new code.
     * @return array{ok:bool,error?:string,log:list<string>,id?:string,from?:string,to?:string}
     */
    public function install(string $stagedId, string $userName = ''): array
    {
        $zipPath = $this->stagedPath($stagedId);
        if ($zipPath === null) {
            return ['ok' => false, 'error' => 'That package is no longer on the server. Upload it again.', 'log' => []];
        }
        $lock = fopen($this->dir() . '/updater.lock', 'c');
        if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
            return ['ok' => false, 'error' => 'Another update is running right now. Wait a minute and try again.', 'log' => []];
        }
        ignore_user_abort(true);
        @set_time_limit(300);
        $log = [];
        $started = microtime(true);
        try {
            $inspection = $this->inspect($zipPath);
            if (!$inspection['ok']) {
                return ['ok' => false, 'error' => $inspection['errors'][0] ?? 'The package did not pass the checks.', 'log' => $log];
            }
            $m = $inspection['manifest'];
            $plan = $inspection['plan'];
            $from = $this->version;
            $to = (string) $m['version'];
            $stamp = gmdate('Ymd-His');
            $log[] = 'Update ' . $from . ' → ' . $to . ' started.';

            // 1. Database backup
            $dbFile = null;
            if ($this->db !== null && $this->wants('updates.backup_database', true)) {
                try {
                    $dir = $this->storage . '/backups';
                    if (!is_dir($dir)) {
                        mkdir($dir, 0755, true);
                    }
                    $dbFile = 'db-' . $stamp . '-v' . $from . '.sql.gz';
                    $size = DbBackup::dump($this->db, $dir . '/' . $dbFile);
                    $log[] = 'Database backed up (' . format_bytes($size) . ').';
                } catch (\Throwable $e) {
                    $dbFile = null;
                    $log[] = 'Database backup was skipped: ' . $e->getMessage();
                    Logger::warning('Update: database backup failed: ' . $e->getMessage());
                }
            }

            // 2. File backup
            $backupName = $stamp . '-v' . $from;
            $backupDir = $this->dir('backups') . '/' . $backupName;
            mkdir($backupDir . '/files', 0755, true);
            $replaced = [];
            $added = [];
            foreach (array_merge(array_keys($plan['write']), $plan['delete']) as $path) {
                $target = $this->target($path);
                if (is_file($target)) {
                    $copy = $backupDir . '/files/' . $path;
                    if (!is_dir(dirname($copy))) {
                        mkdir(dirname($copy), 0755, true);
                    }
                    if (!copy($target, $copy)) {
                        throw new \RuntimeException('Could not back up ' . $path . '.');
                    }
                    $replaced[] = $path;
                } else {
                    $added[] = $path;
                }
            }
            $meta = ['name' => $backupName, 'from' => $from, 'to' => $to, 'created' => gmdate('c'), 'replaced' => $replaced, 'added' => $added,
                'deleted' => $plan['delete'], 'db_backup' => $dbFile, 'status' => 'in_progress'];
            file_put_contents($backupDir . '/meta.json', json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $log[] = count($replaced) . ' existing file(s) copied to a backup.';

            // 3. Replace the files
            $zip = new ZipArchive();
            $zip->open($zipPath);
            $written = [];
            try {
                $writes = $plan['write'];
                $versionSha = $writes['application/VERSION'] ?? null;
                unset($writes['application/VERSION']);
                foreach ($writes as $path => $sha) {
                    $this->writeFile($zip, $path, $sha);
                    $written[] = $path;
                }
                foreach ($plan['delete'] as $path) {
                    $target = $this->target($path);
                    if (is_file($target) && !@unlink($target)) {
                        throw new \RuntimeException('Could not remove ' . $path . '.');
                    }
                    $this->removeEmptyFolders($path);
                }
                if ($versionSha) {
                    $this->writeFile($zip, 'application/VERSION', $versionSha);
                }
            } catch (\Throwable $e) {
                $zip->close();
                $this->restoreFiles($backupDir, $meta);
                $meta['status'] = 'failed';
                file_put_contents($backupDir . '/meta.json', json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                $log[] = 'A step failed (' . $e->getMessage() . '). The old files were put back.';
                $this->addHistory($this->entry($stagedId, $from, $to, 'failed', $userName, $m, count($added), count($plan['write']) - count($added), $plan, $backupName, $dbFile, $log, $started));
                return ['ok' => false, 'error' => $e->getMessage() . ' The old files were put back, so your site is as it was.', 'log' => $log];
            }
            $zip->close();
            $meta['status'] = 'files_done';
            file_put_contents($backupDir . '/meta.json', json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $log[] = count($written) + 1 . ' file(s) written and checked.';
            if (function_exists('opcache_reset')) {
                @opcache_reset();
            }
            $entry = $this->entry($stagedId, $from, $to, 'files_done', $userName, $m, count($added), count($plan['write']) - count($added), $plan, $backupName, $dbFile, $log, $started);
            $this->addHistory($entry);
            file_put_contents($this->dir() . '/pending-finish.json', json_encode(['history' => $stagedId, 'from' => $from, 'to' => $to, 'backup' => $backupName, 'kind' => 'install']));
            return ['ok' => true, 'log' => $log, 'id' => $stagedId, 'from' => $from, 'to' => $to];
        } catch (\Throwable $e) {
            Logger::warning('Update failed: ' . $e->getMessage());
            return ['ok' => false, 'error' => $e->getMessage(), 'log' => $log];
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /** True when files were replaced but the database step has not run yet. */
    public function pending(): ?array
    {
        $file = $this->storage . '/updates/pending-finish.json';
        $data = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
        return is_array($data) ? $data : null;
    }

    /**
     * Phase 2 (a new request, so the new code is loaded): run migrations, permissions and cache clearing.
     * @return array{ok:bool,error?:string,log:list<string>,version:string,migrations?:int}
     */
    public function finish(callable $migrate): array
    {
        $pending = $this->pending();
        $log = [];
        try {
            $done = $migrate();
            $log[] = 'Database updated (' . count($done) . ' change(s)).';
            $log[] = 'Caches cleared. Now running version ' . $this->version . '.';
            if ($pending) {
                $row = null;
                foreach ($this->history() as $r) {
                    if (($r['id'] ?? '') === ($pending['history'] ?? '')) {
                        $row = $r;
                    }
                }
                $status = ($pending['kind'] ?? '') === 'rollback' ? 'rolled_back' : 'installed';
                $this->updateHistory((string) $pending['history'], ['status' => $status, 'finished' => gmdate('c'), 'log' => array_merge($row['log'] ?? [], $log)]);
                $meta = $this->backupMeta((string) ($pending['backup'] ?? ''));
                if ($meta && ($pending['kind'] ?? '') === 'install') {
                    $meta['status'] = 'installed';
                    $this->writeBackupMeta($meta);
                }
                @unlink($this->storage . '/updates/pending-finish.json');
                if (($pending['kind'] ?? '') === 'install') {
                    $zip = $this->stagedPath((string) $pending['history']);
                    if ($zip) {
                        @unlink($zip);
                    }
                    $this->prune();
                }
            }
            return ['ok' => true, 'log' => $log, 'version' => $this->version, 'migrations' => count($done)];
        } catch (\Throwable $e) {
            Logger::warning('Update finish failed: ' . $e->getMessage());
            if ($pending) {
                $this->updateHistory((string) $pending['history'], ['status' => 'failed_after_files', 'error' => $e->getMessage()]);
            }
            return ['ok' => false, 'error' => $e->getMessage(), 'log' => $log, 'version' => $this->version];
        }
    }

    /* ------------------------------------------------------------------ going back */

    /** The backup that "Go back" would restore: the newest successful install that matches the running version. */
    public function rollbackTarget(): ?array
    {
        foreach ($this->backups() as $b) {
            if (in_array($b['status'] ?? '', ['installed', 'files_done'], true)) {
                return ($b['to'] ?? '') === $this->version ? $b : null;
            }
        }
        return null;
    }

    /** @return array{ok:bool,error?:string,log:list<string>,from?:string,to?:string} */
    public function rollback(string $backupName, string $userName = ''): array
    {
        $meta = $this->backupMeta($backupName);
        if (!$meta || !in_array($meta['status'] ?? '', ['installed', 'files_done'], true)) {
            return ['ok' => false, 'error' => 'There is no backup to go back to.', 'log' => []];
        }
        $lock = fopen($this->dir() . '/updater.lock', 'c');
        if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
            return ['ok' => false, 'error' => 'Another update is running right now. Wait a minute and try again.', 'log' => []];
        }
        ignore_user_abort(true);
        @set_time_limit(300);
        $started = microtime(true);
        try {
            $current = $this->version;
            $log = ['Going back from ' . $current . ' to ' . $meta['from'] . '.'];
            $this->restoreFiles($this->dir('backups') . '/' . $backupName, $meta);
            $log[] = count($meta['replaced']) . ' file(s) restored, ' . count($meta['added']) . ' new file(s) removed.';
            $meta['status'] = 'restored';
            $this->writeBackupMeta($meta);
            if (function_exists('opcache_reset')) {
                @opcache_reset();
            }
            $id = bin2hex(random_bytes(8));
            $this->addHistory(['id' => $id, 'time' => gmdate('c'), 'from' => $current, 'to' => (string) $meta['from'], 'status' => 'rolling_back', 'by' => $userName, 'title' => 'Went back to ' . $meta['from'],
                'notes' => '', 'files' => ['added' => 0, 'replaced' => count($meta['replaced']), 'deleted' => count($meta['added']), 'kept' => 0], 'backup' => $backupName, 'db_backup' => $meta['db_backup'] ?? null,
                'log' => $log, 'seconds' => round(microtime(true) - $started, 1)]);
            file_put_contents($this->dir() . '/pending-finish.json', json_encode(['history' => $id, 'from' => $current, 'to' => $meta['from'], 'backup' => $backupName, 'kind' => 'rollback']));
            return ['ok' => true, 'log' => $log, 'from' => $current, 'to' => (string) $meta['from']];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage(), 'log' => []];
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /* ------------------------------------------------------------------ backups */

    /** @return list<array<string,mixed>> newest first */
    public function backups(): array
    {
        $dir = $this->storage . '/updates/backups';
        $out = [];
        foreach (glob($dir . '/*/meta.json') ?: [] as $file) {
            $meta = json_decode((string) file_get_contents($file), true);
            if (is_array($meta) && isset($meta['name'])) {
                $meta['size'] = $this->dirSize(dirname($file));
                $meta['db_size'] = !empty($meta['db_backup']) && is_file($this->storage . '/backups/' . $meta['db_backup']) ? (int) filesize($this->storage . '/backups/' . $meta['db_backup']) : 0;
                $out[] = $meta;
            }
        }
        usort($out, static fn($a, $b) => strcmp((string) $b['name'], (string) $a['name']));
        return $out;
    }

    public function backupMeta(string $name): ?array
    {
        if (preg_match('/^\d{8}-\d{6}-v\d+\.\d+\.\d+$/', $name) !== 1) {
            return null;
        }
        $file = $this->storage . '/updates/backups/' . $name . '/meta.json';
        $meta = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
        return is_array($meta) ? $meta : null;
    }

    private function writeBackupMeta(array $meta): void
    {
        file_put_contents($this->storage . '/updates/backups/' . $meta['name'] . '/meta.json', json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    public function deleteBackup(string $name): bool
    {
        $meta = $this->backupMeta($name);
        $keepThis = $this->rollbackTarget();
        if (!$meta || ($keepThis !== null && $keepThis['name'] === $name)) {
            return false;
        }
        $this->removeTree($this->storage . '/updates/backups/' . $name);
        if (!empty($meta['db_backup'])) {
            @unlink($this->storage . '/backups/' . basename((string) $meta['db_backup']));
        }
        return true;
    }

    /** After a file is removed, remove the folders it leaves empty, never going above a folder such as application/Modules or assets/js. */
    private function removeEmptyFolders(string $path): void
    {
        $parts = explode('/', trim($path, '/'));
        array_pop($parts);
        while (count($parts) >= 3) {
            $dir = $this->target(implode('/', $parts));
            if (!is_dir($dir) || (new \FilesystemIterator($dir))->valid() || !@rmdir($dir)) {
                return;
            }
            array_pop($parts);
        }
    }

    /** Keep the newest N file backups and database backups (the one "Go back" needs is always kept). */
    public function prune(): int
    {
        $keep = max(1, (int) $this->wants('updates.keep_backups', 5, true));
        $removed = 0;
        $target = $this->rollbackTarget()['name'] ?? '';
        foreach (array_slice($this->backups(), $keep) as $b) {
            if ($b['name'] !== $target && $this->deleteBackup((string) $b['name'])) {
                $removed++;
            }
        }
        return $removed;
    }

    /* ------------------------------------------------------------------ building packages (developer tool) */

    /**
     * Build an update package from the files of this install, comparing with a previous full copy.
     * @param string $previous folder or full-install zip of the earlier version
     * @return array{file:string,files:int,deleted:int,version:string}
     */
    public function build(string $previous, string $outFile, string $title = '', string $notes = ''): array
    {
        $cleanup = null;
        if (is_file($previous) && str_ends_with(strtolower($previous), '.zip')) {
            $tmp = sys_get_temp_dir() . '/ap-prev-' . bin2hex(random_bytes(4));
            mkdir($tmp, 0755, true);
            $z = new ZipArchive();
            if ($z->open($previous) !== true) {
                throw new \RuntimeException('Cannot open ' . $previous);
            }
            $z->extractTo($tmp);
            $z->close();
            $entries = glob($tmp . '/*') ?: [];
            $previous = (is_file($tmp . '/application/VERSION') || count($entries) !== 1 || !is_dir($entries[0])) ? $tmp : $entries[0];
            $cleanup = $tmp;
        }
        $previous = rtrim($previous, '/');
        if (!is_file($previous . '/application/VERSION')) {
            throw new \RuntimeException('The previous version folder has no application/VERSION file.');
        }
        $prevVersion = trim((string) file_get_contents($previous . '/application/VERSION'));
        $new = $this->collectTree($this->base, $this->root);
        $old = $this->collectTree($previous, $previous . '/application');
        $files = [];
        foreach ($new as $path => $abs) {
            $hash = hash_file('sha256', $abs);
            if (!isset($old[$path]) || hash_file('sha256', $old[$path]) !== $hash || $path === 'application/VERSION') {
                $files[$path] = $hash;
            }
        }
        $delete = array_values(array_diff(array_keys($old), array_keys($new)));
        if (!$files) {
            throw new \RuntimeException('Nothing has changed since the previous version.');
        }
        $manifest = [
            'format' => 'ap-update', 'product' => 'ap-framework', 'version' => $this->version, 'min_from' => $prevVersion,
            'min_php' => '8.1.0', 'title' => $title !== '' ? $title : 'AP Framework ' . $this->version, 'notes' => $notes,
            'created' => gmdate('c'), 'files' => $files, 'delete' => $delete,
        ];
        if (is_file($outFile)) {
            unlink($outFile);
        }
        $zip = new ZipArchive();
        if ($zip->open($outFile, ZipArchive::CREATE) !== true) {
            throw new \RuntimeException('Cannot write ' . $outFile);
        }
        $zip->addFromString(self::MANIFEST, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        foreach (array_keys($files) as $path) {
            $zip->addFile($new[$path], $path);
        }
        $zip->close();
        if ($cleanup) {
            $this->removeTree($cleanup);
        }
        return ['file' => $outFile, 'files' => count($files), 'deleted' => count($delete), 'version' => $this->version];
    }

    /** @return array<string,string> package path => absolute file, for everything that belongs in a package */
    private function collectTree(string $base, string $appRoot): array
    {
        $out = [];
        $add = function (string $dir, string $prefix) use (&$out, &$add): void {
            foreach (scandir($dir) ?: [] as $name) {
                if ($name === '.' || $name === '..') {
                    continue;
                }
                $abs = $dir . '/' . $name;
                $rel = ($prefix === '' ? '' : $prefix . '/') . $name;
                if (is_dir($abs)) {
                    if (!self::isProtected($rel . '/') && !in_array($name, ['.git', 'node_modules'], true)) {
                        $add($abs, $rel);
                    }
                } elseif (self::safePath($rel) && !self::isProtected($rel)) {
                    $out[$rel] = $abs;
                }
            }
        };
        $add($appRoot, 'application');
        foreach (self::ALLOWED_DIRS as $d) {
            if ($d !== 'application' && is_dir($base . '/' . $d)) {
                $add($base . '/' . $d, $d);
            }
        }
        foreach (self::ALLOWED_ROOT_FILES as $f) {
            if (is_file($base . '/' . $f)) {
                $out[$f] = $base . '/' . $f;
            }
        }
        ksort($out);
        return $out;
    }

    /* ------------------------------------------------------------------ helpers */

    /** True when a package path is allowed at all (a protected path counts as allowed: it is simply skipped). */
    public static function safePath(string $path): bool
    {
        if ($path === '' || strlen($path) > 200 || str_contains($path, "\0") || str_contains($path, '\\') || $path[0] === '/' || str_contains($path, '//')) {
            return false;
        }
        $parts = explode('/', $path);
        foreach ($parts as $p) {
            if ($p === '' || $p === '.' || $p === '..' || preg_match('/^[A-Za-z0-9_.\-]+$/', $p) !== 1) {
                return false;
            }
        }
        $name = end($parts);
        if ($name[0] === '.' && !in_array($name, ['.htaccess', '.gitkeep'], true)) {
            return false;
        }
        if (count($parts) === 1) {
            return in_array($path, self::ALLOWED_ROOT_FILES, true);
        }
        if (!in_array($parts[0], self::ALLOWED_DIRS, true)) {
            return false;
        }
        if ($name === 'VERSION' || $name === '.htaccess' || $name === '.gitkeep') {
            return true;
        }
        $ext = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));
        if (!in_array($ext, self::EXTENSIONS, true)) {
            return false;
        }
        // PHP only lives in the application, the tools folder and the front controller; never among public assets.
        return !($ext === 'php' && !in_array($parts[0], ['application', 'tools'], true));
    }

    public static function isProtected(string $path): bool
    {
        foreach (self::PROTECTED as $p) {
            if ($path === rtrim($p, '/') || str_starts_with($path, $p)) {
                return true;
            }
        }
        return false;
    }

    /** Where a package path lives on this server (the application folder may sit outside the public folder). */
    private function target(string $path): string
    {
        return str_starts_with($path, 'application/') ? $this->root . '/' . substr($path, 12) : $this->base . '/' . $path;
    }

    private function nearestDir(string $file): ?string
    {
        $dir = dirname($file);
        while ($dir !== '' && $dir !== '/' && !is_dir($dir)) {
            $dir = dirname($dir);
        }
        return is_dir($dir) ? $dir : null;
    }

    private static function hashEntry(ZipArchive $zip, string $name): ?string
    {
        $stream = $zip->getStream($name);
        if (!$stream) {
            return null;
        }
        $ctx = hash_init('sha256');
        hash_update_stream($ctx, $stream);
        fclose($stream);
        return hash_final($ctx);
    }

    private function writeFile(ZipArchive $zip, string $path, string $sha): void
    {
        $target = $this->target($path);
        $dir = dirname($target);
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new \RuntimeException('Could not create the folder for ' . $path . '.');
        }
        $stream = $zip->getStream($path);
        if (!$stream) {
            throw new \RuntimeException('Could not read ' . $path . ' from the package.');
        }
        $tmp = $dir . '/.ap-new-' . bin2hex(random_bytes(4));
        $out = fopen($tmp, 'wb');
        if (!$out) {
            fclose($stream);
            throw new \RuntimeException('Could not write ' . $path . '.');
        }
        stream_copy_to_stream($stream, $out);
        fclose($stream);
        fclose($out);
        if (hash_file('sha256', $tmp) !== $sha) {
            @unlink($tmp);
            throw new \RuntimeException('The file ' . $path . ' did not copy correctly.');
        }
        @chmod($tmp, 0644);
        if (!@rename($tmp, $target)) {
            if (!@copy($tmp, $target)) {
                @unlink($tmp);
                throw new \RuntimeException('Could not replace ' . $path . '.');
            }
            @unlink($tmp);
        }
    }

    /** Put back every file saved in a backup and remove the files the update had added. */
    private function restoreFiles(string $backupDir, array $meta): void
    {
        foreach ((array) $meta['replaced'] as $path) {
            $src = $backupDir . '/files/' . $path;
            if (!is_file($src)) {
                continue;
            }
            $target = $this->target($path);
            if (!is_dir(dirname($target))) {
                mkdir(dirname($target), 0755, true);
            }
            if (!@copy($src, $target)) {
                throw new \RuntimeException('Could not put back ' . $path . '. The saved copy is in ' . $backupDir . '/files.');
            }
        }
        foreach ((array) $meta['added'] as $path) {
            if (self::safePath((string) $path) && !self::isProtected((string) $path)) {
                @unlink($this->target((string) $path));
            }
        }
    }

    private function entry(string $id, string $from, string $to, string $status, string $user, array $manifest, int $added, int $replaced, array $plan, string $backup, ?string $dbFile, array $log, float $started): array
    {
        return [
            'id' => $id, 'time' => gmdate('c'), 'from' => $from, 'to' => $to, 'status' => $status, 'by' => $user,
            'title' => (string) ($manifest['title'] ?? ''), 'notes' => (string) ($manifest['notes'] ?? ''),
            'files' => ['added' => $added, 'replaced' => max(0, $replaced), 'deleted' => count($plan['delete']), 'kept' => count($plan['keep'])],
            'backup' => $backup, 'db_backup' => $dbFile, 'log' => $log, 'seconds' => round(microtime(true) - $started, 1),
        ];
    }

    private function wants(string $key, mixed $default, bool $asInt = false): mixed
    {
        try {
            $v = setting($key, $default);
        } catch (\Throwable) {
            $v = $default;
        }
        return $asInt ? (int) $v : (bool) $v;
    }

    private function dirSize(string $dir): int
    {
        $size = 0;
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            $size += $f->isFile() ? $f->getSize() : 0;
        }
        return $size;
    }

    private function removeTree(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $f) {
            $f->isDir() && !$f->isLink() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        }
        @rmdir($dir);
    }
}
