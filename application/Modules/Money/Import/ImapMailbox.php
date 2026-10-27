<?php
declare(strict_types=1);

namespace AP\Modules\Money\Import;

/** A small IMAP client over TLS, enough to read and bin mail in Gmail with an app password. */
final class ImapMailbox implements Mailbox
{
    /** @var resource */
    private $fp;
    private int $n = 0;

    private function __construct($fp)
    {
        $this->fp = $fp;
    }

    /** @return self|string a connected mailbox, or the reason it failed */
    public static function open(string $host, int $port, string $user, string $pass, string $folder, string $bin): self|string
    {
        $fp = @stream_socket_client('ssl://' . $host . ':' . $port, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]));
        if (!$fp) {
            return 'Could not reach ' . $host . ' (' . $errstr . ').';
        }
        stream_set_timeout($fp, 30);
        $m = new self($fp);
        $m->bin = $bin;
        if (!str_starts_with((string) fgets($fp, 2048), '* OK')) {
            return 'The mail server did not say hello.';
        }
        [$ok, $why] = $m->cmd('LOGIN ' . self::q($user) . ' ' . self::q($pass));
        if (!$ok) {
            return 'Gmail refused the sign-in. Use an app password (Google Account > Security > App passwords) and check that IMAP is switched on in Gmail settings.';
        }
        [$ok] = $m->cmd('SELECT ' . self::q($folder));
        return $ok ? $m : 'The folder "' . $folder . '" was not found.';
    }

    private string $bin = '[Gmail]/Bin';

    private static function q(string $s): string
    {
        return '"' . addcslashes(str_replace(["\r", "\n"], '', $s), "\"\\") . '"';
    }

    /** @return array{0:bool,1:string,2:string} ok, status line, untagged data */
    private function cmd(string $line): array
    {
        $tag = 'A' . ++$this->n;
        fwrite($this->fp, $tag . ' ' . $line . "\r\n");
        $data = '';
        while (($l = fgets($this->fp, 65536)) !== false) {
            if (str_starts_with($l, $tag . ' ')) {
                return [str_starts_with($l, $tag . ' OK'), trim($l), $data];
            }
            $data .= $l;
            if (preg_match('/\{(\d+)\}\r?\n$/', $l, $m)) {
                $need = (int) $m[1];
                $buf = '';
                while (strlen($buf) < $need && ($c = fread($this->fp, $need - strlen($buf))) !== false && $c !== '') {
                    $buf .= $c;
                }
                $data .= $buf;
            }
        }
        return [false, 'Connection closed.', $data];
    }

    public function recent(int $days, int $limit): array
    {
        $since = date('j-M-Y', time() - max(1, $days) * 86400);
        [$ok, , $d] = $this->cmd('UID SEARCH SINCE ' . $since);
        if (!$ok || !preg_match('/^\* SEARCH ([\d ]+)/m', $d, $m)) {
            return [];
        }
        $ids = array_map('intval', preg_split('/\s+/', trim($m[1])) ?: []);
        return array_slice($ids, -$limit);
    }

    private function literal(string $data): string
    {
        return preg_match('/\{(\d+)\}\r?\n/', $data, $m, PREG_OFFSET_CAPTURE) ? substr($data, $m[0][1] + strlen($m[0][0]), (int) $m[1][0]) : '';
    }

    public function header(int $id): string
    {
        [$ok, , $d] = $this->cmd('UID FETCH ' . $id . ' (BODY.PEEK[HEADER.FIELDS (FROM SUBJECT DATE MESSAGE-ID)])');
        return $ok ? $this->literal($d) : '';
    }

    public function raw(int $id): string
    {
        [$ok, , $d] = $this->cmd('UID FETCH ' . $id . ' (BODY.PEEK[])');
        return $ok ? $this->literal($d) : '';
    }

    /** The folder Gmail calls the bin in this account: the one you named, else whichever folder is marked as the bin (Trash). */
    private function binFolders(): array
    {
        $out = [$this->bin];
        [$ok, , $d] = $this->cmd('LIST "" "*"');
        if ($ok && preg_match_all('/^\* LIST \(([^)]*)\)\s+"[^"]*"\s+(?:"([^"]*)"|(\S+))/mi', $d, $mm, PREG_SET_ORDER)) {
            foreach ($mm as $f) {
                if (stripos($f[1], '\\Trash') !== false) {
                    $out[] = $f[2] !== '' ? $f[2] : $f[3];
                }
            }
        }
        return array_values(array_unique(array_filter($out, 'strlen')));
    }

    public function trash(int $id): bool
    {
        $done = false;
        foreach ($this->binFolders() as $folder) {
            [$ok] = $this->cmd('UID COPY ' . $id . ' ' . self::q($folder));
            if ($ok) {
                $done = true;
                break;
            }
        }
        if (!$done) {
            return false;
        }
        $this->cmd('UID STORE ' . $id . ' +FLAGS.SILENT (\\Deleted)');
        $this->cmd('EXPUNGE');
        return true;
    }

    public function close(): void
    {
        @fwrite($this->fp, 'A' . ++$this->n . " LOGOUT\r\n");
        @fclose($this->fp);
    }
}
