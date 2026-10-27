<?php
declare(strict_types=1);

namespace AP\Core\Mail;

use RuntimeException;

/** A small, dependency-free SMTP client (plain, STARTTLS or SSL; AUTH LOGIN / PLAIN). */
final class SmtpTransport
{
    /** @var resource|null */
    private $socket = null;
    /** @var string[] */
    private array $log = [];
    private array $capabilities = [];

    /** @param array{host:string,port:int,encryption:string,username:string,password:string,timeout:int,verify:bool,helo:string} $c */
    private bool $timedOut = false;

    public function __construct(private array $c)
    {
    }

    /** What the server said, for the "test connection" screen. @return string[] */
    public function transcript(): array
    {
        return $this->log;
    }

    public function connect(): void
    {
        $enc = $this->c['encryption'];
        $remote = ($enc === 'ssl' ? 'ssl://' : 'tcp://') . $this->c['host'] . ':' . $this->c['port'];
        $ctx = stream_context_create(['ssl' => [
            'verify_peer' => $this->c['verify'], 'verify_peer_name' => $this->c['verify'], 'allow_self_signed' => !$this->c['verify'],
            'peer_name' => $this->c['host'],
        ]]);
        $errno = 0;
        $errstr = '';
        $this->socket = @stream_socket_client($remote, $errno, $errstr, $this->c['timeout'], STREAM_CLIENT_CONNECT, $ctx);
        if (!is_resource($this->socket)) {
            throw new RuntimeException("Could not connect to {$this->c['host']}:{$this->c['port']} ({$errstr}). Check the host, port and encryption, and that your hosting allows outgoing SMTP.");
        }
        stream_set_timeout($this->socket, $this->c['timeout']);
        $this->expect($this->read(), [220], 'greeting');
        $this->ehlo();
        if ($enc === 'tls') {
            if (!isset($this->capabilities['STARTTLS'])) {
                throw new RuntimeException('The server does not offer STARTTLS. Choose another encryption setting.');
            }
            $this->command('STARTTLS', [220]);
            if (!@stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('Could not start a secure (TLS) connection. If the server uses a self-signed certificate, untick "Check the server certificate" in the email settings.');
            }
            $this->ehlo();
        }
        if ($this->c['username'] !== '') {
            $this->authenticate();
        }
    }

    private function ehlo(): void
    {
        $lines = $this->command('EHLO ' . $this->c['helo'], [250]);
        $this->capabilities = [];
        foreach ($lines as $i => $line) {
            if ($i === 0) {
                continue;
            }
            // Lines look like "250-AUTH PLAIN LOGIN" or "250 8BITMIME": drop the code first.
            $parts = explode(' ', strtoupper(trim(substr($line, 4))), 2);
            $this->capabilities[$parts[0]] = $parts[1] ?? '';
        }
    }

    private function authenticate(): void
    {
        $methods = explode(' ', $this->capabilities['AUTH'] ?? '');
        if (in_array('PLAIN', $methods, true)) {
            $this->command('AUTH PLAIN ' . base64_encode("\0" . $this->c['username'] . "\0" . $this->c['password']), [235], true);
        } elseif (in_array('LOGIN', $methods, true) || !$methods[0]) {
            $this->command('AUTH LOGIN', [334]);
            $this->command(base64_encode($this->c['username']), [334], true);
            $this->command(base64_encode($this->c['password']), [235], true);
        } else {
            throw new RuntimeException('The server does not support a login method this tool knows (it offers: ' . implode(', ', $methods) . ').');
        }
    }

    /** @param array<int,array{0:string,1:string}> $recipients */
    public function send(string $envelopeFrom, array $recipients, string $rawMessage): void
    {
        if ($this->socket === null) {
            $this->connect();
        }
        $this->command('MAIL FROM:<' . $envelopeFrom . '>', [250]);
        foreach ($recipients as $r) {
            $this->command('RCPT TO:<' . $r[0] . '>', [250, 251]);
        }
        $this->command('DATA', [354]);
        $data = preg_replace('/(?<!\r)\n/', "\r\n", $rawMessage) ?? $rawMessage;
        $data = preg_replace('/^\./m', '..', $data) ?? $data;
        $this->write($data . "\r\n.\r\n", true);
        $this->expect($this->read(), [250], 'message');
    }

    public function quit(): void
    {
        if (is_resource($this->socket)) {
            try {
                // Saying goodbye is only polite: never wait long for the answer, and not at all after a time-out.
                if (!$this->timedOut) {
                    stream_set_timeout($this->socket, 2);
                    $this->write("QUIT\r\n");
                    $this->read();
                }
            } catch (\Throwable) {
            }
            @fclose($this->socket);
        }
        $this->socket = null;
    }

    /** @return string[] response lines */
    private function command(string $line, array $expect, bool $hide = false): array
    {
        $this->write($line . "\r\n", false, $hide ? '(hidden)' : $line);
        return $this->expect($this->read(), $expect, $hide ? 'login' : strtok($line, ' '));
    }

    private function write(string $data, bool $quiet = false, ?string $logAs = null): void
    {
        if (!is_resource($this->socket)) {
            throw new RuntimeException('The connection to the mail server was lost.');
        }
        $this->log[] = 'C: ' . ($quiet ? '[message data]' : ($logAs ?? rtrim($data)));
        $len = strlen($data);
        for ($written = 0; $written < $len;) {
            $n = @fwrite($this->socket, substr($data, $written));
            if ($n === false || $n === 0) {
                throw new RuntimeException('The connection to the mail server was lost while sending.');
            }
            $written += $n;
        }
    }

    /** @return string[] */
    private function read(): array
    {
        $lines = [];
        // Inside a scheduler run, the whole conversation must finish before the run's time is up.
        $until = \AP\Core\Scheduler::$deadline;
        if ($until !== null && is_resource($this->socket)) {
            $left = $until - microtime(true);
            if ($left < 0.5) {
                $this->timedOut = true;
                throw new RuntimeException('The mail server did not answer in time (the scheduler run was out of time). The email will be tried again.');
            }
            stream_set_timeout($this->socket, (int) min($this->c['timeout'], max(1, floor($left))), 0);
        }
        while (is_resource($this->socket) && ($line = fgets($this->socket, 1024)) !== false) {
            $line = rtrim($line, "\r\n");
            $lines[] = $line;
            $this->log[] = 'S: ' . $line;
            if (strlen($line) < 4 || $line[3] !== '-') {
                break;
            }
        }
        if (!$lines) {
            $this->timedOut = true;
            throw new RuntimeException('The mail server did not answer (timed out).');
        }
        return $lines;
    }

    /** @param string[] $lines */
    private function expect(array $lines, array $codes, string $what): array
    {
        $code = (int) substr($lines[count($lines) - 1], 0, 3);
        if (!in_array($code, $codes, true)) {
            throw new RuntimeException('The mail server refused ' . $what . ': ' . implode(' ', array_map(static fn($l) => substr($l, 4), $lines)) . " (code {$code})");
        }
        return $lines;
    }
}
