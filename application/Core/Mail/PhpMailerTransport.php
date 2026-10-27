<?php
declare(strict_types=1);

namespace AP\Core\Mail;

use PHPMailer\PHPMailer\PHPMailer;
use RuntimeException;

/**
 * Sends through an SMTP server with the bundled PHPMailer library (application/vendor/PHPMailer, LGPL 2.1).
 * Adds DKIM signing when a domain, selector and private key are set.
 */
final class PhpMailerTransport
{
    public static function load(): void
    {
        if (!class_exists(PHPMailer::class, false)) {
            $dir = AP_ROOT . '/vendor/PHPMailer';
            require_once $dir . '/Exception.php';
            require_once $dir . '/PHPMailer.php';
            require_once $dir . '/SMTP.php';
        }
    }

    /**
     * @param array{host:string,port:int,encryption:string,username:string,password:string,timeout:int,verify:bool,helo:string} $smtp
     * @param array{domain:string,selector:string,key:string} $dkim
     * @param list<string> $debug filled with the conversation with the server
     */
    public static function send(Message $m, array $smtp, array $dkim = [], array &$debug = []): void
    {
        self::load();
        if (trim($smtp['host']) === '') {
            throw new RuntimeException('PHPMailer is selected but no SMTP server is entered.');
        }
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->CharSet = PHPMailer::CHARSET_UTF8;
        $mail->Host = $smtp['host'];
        $mail->Port = $smtp['port'];
        $mail->Timeout = $smtp['timeout'];
        $mail->Hostname = $smtp['helo'];
        $mail->SMTPAutoTLS = $smtp['encryption'] !== 'none';
        $mail->SMTPSecure = match ($smtp['encryption']) {
            'ssl' => PHPMailer::ENCRYPTION_SMTPS,
            'tls' => PHPMailer::ENCRYPTION_STARTTLS,
            default => '',
        };
        if ($smtp['username'] !== '') {
            $mail->SMTPAuth = true;
            $mail->Username = $smtp['username'];
            $mail->Password = $smtp['password'];
        }
        if (!$smtp['verify']) {
            $mail->SMTPOptions = ['ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]];
        }
        $mail->SMTPDebug = 2;
        $mail->Debugoutput = static function (string $line) use (&$debug, $smtp): void {
            $line = trim($line);
            if ($smtp['password'] !== '') {
                $line = str_replace([$smtp['password'], base64_encode($smtp['password'])], '********', $line);
            }
            if ($line !== '' && count($debug) < 200) {
                $debug[] = $line;
            }
        };
        $mail->setFrom($m->from[0], $m->from[1], false);
        $mail->Sender = $m->from[0];
        foreach ($m->to as [$a, $n]) {
            $mail->addAddress($a, $n);
        }
        foreach ($m->cc as [$a, $n]) {
            $mail->addCC($a, $n);
        }
        foreach ($m->bcc as [$a, $n]) {
            $mail->addBCC($a, $n);
        }
        foreach ($m->replyTo as [$a, $n]) {
            $mail->addReplyTo($a, $n);
        }
        $mail->Subject = $m->subject;
        if ($m->html !== '') {
            $mail->isHTML(true);
            $mail->Body = $m->html;
            $mail->AltBody = $m->textBody();
        } else {
            $mail->Body = $m->text;
        }
        foreach ($m->attachments as $att) {
            $mail->addAttachment($att['path'], $att['name'], PHPMailer::ENCODING_BASE64, $att['mime']);
        }
        foreach ($m->headers as $k => $v) {
            $mail->addCustomHeader((string) $k, (string) $v);
        }
        if (($dkim['domain'] ?? '') !== '' && ($dkim['key'] ?? '') !== '') {
            $mail->DKIM_domain = $dkim['domain'];
            $mail->DKIM_selector = $dkim['selector'] !== '' ? $dkim['selector'] : 'default';
            $mail->DKIM_private_string = $dkim['key'];
            $mail->DKIM_identity = $m->from[0];
        }
        try {
            $mail->send();
        } catch (\Throwable $e) {
            throw new RuntimeException('PHPMailer: ' . ($mail->ErrorInfo ?: $e->getMessage()));
        }
    }
}
