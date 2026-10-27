<?php
declare(strict_types=1);

// A tiny one-connection SMTP server used only by the test suite.
// Usage: php fake_smtp.php <output-file> [reject-rcpt]
[, $out, $mode] = $argv + [null, '', ''];
$server = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
if (!$server) {
    fwrite(STDERR, "cannot listen: {$errstr}\n");
    exit(1);
}
$port = (int) substr(strrchr((string) stream_socket_get_name($server, false), ':'), 1);
echo $port . "\n";
fflush(STDOUT);
stream_set_blocking($server, true);
$conn = @stream_socket_accept($server, 10);
if (!$conn) {
    exit(2);
}
$log = [];
$send = static function (string $line) use ($conn, &$log): void {
    fwrite($conn, $line . "\r\n");
    $log[] = 'S: ' . $line;
};
$send('220 fake.test ESMTP ready');
$data = '';
while (($line = fgets($conn)) !== false) {
    $cmd = rtrim($line, "\r\n");
    $log[] = 'C: ' . (str_starts_with(strtoupper($cmd), 'AUTH') ? 'AUTH ...' : $cmd);
    $upper = strtoupper($cmd);
    if (str_starts_with($upper, 'EHLO')) {
        fwrite($conn, "250-fake.test\r\n250-AUTH PLAIN LOGIN\r\n250 8BITMIME\r\n");
    } elseif (str_starts_with($upper, 'AUTH PLAIN')) {
        $creds = base64_decode(substr($cmd, 11));
        $send($creds === "\0user@test\0secret" ? '235 2.7.0 Authentication successful' : '535 5.7.8 Bad credentials');
    } elseif (str_starts_with($upper, 'AUTH LOGIN')) {
        $send('334 VXNlcm5hbWU6');
        $user = base64_decode(rtrim((string) fgets($conn), "\r\n"));
        $send('334 UGFzc3dvcmQ6');
        $pass = base64_decode(rtrim((string) fgets($conn), "\r\n"));
        $send($user === 'user@test' && $pass === 'secret' ? '235 2.7.0 Authentication successful' : '535 5.7.8 Bad credentials');
    } elseif (str_starts_with($upper, 'MAIL FROM')) {
        $send('250 OK');
    } elseif (str_starts_with($upper, 'RCPT TO')) {
        $send($mode === 'reject-rcpt' ? '550 5.1.1 No such user here' : '250 OK');
    } elseif ($upper === 'DATA') {
        $send('354 End data with <CR><LF>.<CR><LF>');
        while (($d = fgets($conn)) !== false) {
            if ($d === ".\r\n") {
                break;
            }
            $data .= $d;
        }
        $send('250 OK queued');
    } elseif ($upper === 'QUIT') {
        $send('221 Bye');
        break;
    } else {
        $send('500 Unknown command');
    }
}
file_put_contents($out, json_encode(['data' => $data, 'log' => $log]));
