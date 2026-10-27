<?php
declare(strict_types=1);

namespace AP\Modules\Logs;

/** Plain-language "What it means" and "How to fix" for common errors. Modules add their own with the "errors.hints" filter. */
final class ErrorHints
{
    /** @return array{means:string, fix:string, link?:string, link_label?:string}|null */
    public static function explain(array $row): ?array
    {
        $msg = (string) ($row['message'] ?? '');
        $class = (string) ($row['class'] ?? '');
        $rules = [
            ['/SQLSTATE\[HY000\] \[(2002|2003|2006)\]|server has gone away|Connection refused/i', 'The website could not reach its database.', 'Check that the database server is running and that the details in application/config/db.php are right. On shared hosting, ask your host if MySQL is down.'],
            ['/SQLSTATE\[(HY000|28000)\] \[1045\]|Access denied for user/i', 'The database refused the user name or password.', 'Correct the database user and password in application/config/db.php (they are in cPanel > MySQL Databases).'],
            ['/(Base table or view not found|no such table|Unknown column|no such column|42S02|42S22)/i', 'A database table or column is missing, usually because an update was copied but not finished.', 'Press "Finish update" in System > Tools.', 'system/tools', 'Open Tools'],
            ['/Allowed memory size of \d+ bytes exhausted/i', 'PHP ran out of memory while making a page.', 'Ask your host to raise memory_limit to 256M (cPanel > Select PHP Version > Options). Very large pictures can also cause this.'],
            ['/Maximum execution time/i', 'A task took longer than the server allows.', 'Long work such as emails should run in the background: check System > Background Jobs, or ask your host to raise max_execution_time.', 'system/queue', 'Background jobs'],
            ['/Class "?[^" ]+"? not found|Failed opening required|failed to open stream: No such file/i', 'A program file is missing, often after an upload over FTP that did not finish.', 'Upload the files of the latest update package again (all of them), then press "Finish update".', 'system/updates', 'Open Updates'],
            ['/Call to undefined function (image|gd)/i', 'The PHP picture library (GD) is not switched on.', 'Switch on the "gd" extension in cPanel > Select PHP Version > Extensions.'],
            ['/Call to undefined function (mb_)/i', 'The PHP "mbstring" extension is missing.', 'Switch on "mbstring" in cPanel > Select PHP Version > Extensions.'],
            ['/Call to undefined function (curl_)/i', 'The PHP "curl" extension is missing, so the site cannot talk to other services.', 'Switch on "curl" in cPanel > Select PHP Version > Extensions.'],
            ['/Permission denied|is not writable|failed to open stream: Permission|Read-only file system/i', 'The website was not allowed to write a file.', 'In your file manager set folders to 755 (or 775) and files to 644, especially application/storage and assets/uploads.'],
            ['/(Could not resolve host|Operation timed out|Connection timed out|cURL error|SSL certificate problem)/i', 'An outside service (email, maps, payments, a robot check) could not be reached.', 'Usually temporary. If it keeps happening, check the address and keys of that service, and ask your host whether outgoing connections are blocked.'],
            ['/is not a valid email address/i', 'An email address in the settings is not valid, for example the sender address.', 'Type a full address such as info@yourdomain.co.uk in Email settings > Sender.', 'settings/email', 'Open Email settings'],
            ['/(SMTP|PHPMailer|authenticate|535|Brevo)/i', 'An email could not be sent.', 'Check the server, user name and password in Email settings, then press Test Connection.', 'settings/email', 'Open Email settings'],
            ['/(CSRF|Page expired|419)/i', 'A form was sent after its page had been open too long.', 'Nothing to fix: the person just needs to reload the page and send it again.'],
            ['/(Undefined (array key|variable|index|offset|property)|Trying to access array offset|on null|must be of type|Argument #\d)/i', 'A bug in the program code: it expected something that was not there.', 'Note the error ID and send it, with what you were doing, to whoever builds your website. "Mark fixed" once a fix is installed.'],
            ['/DivisionByZero|Division by zero/i', 'The program tried to divide by zero, usually because a number setting is 0.', 'Look for a setting set to 0 that should be at least 1, or send the error ID to your developer.'],
        ];
        foreach ($rules as $r) {
            if (preg_match($r[0], $msg . ' ' . $class)) {
                $out = ['means' => $r[1], 'fix' => $r[2]];
                if (isset($r[3])) {
                    $out['link'] = admin_url($r[3]);
                    $out['link_label'] = $r[4];
                }
                return filter('errors.hints', $out, $row);
            }
        }
        return filter('errors.hints', null, $row);
    }
}
