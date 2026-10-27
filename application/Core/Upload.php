<?php
declare(strict_types=1);

namespace AP\Core;

use DOMDocument;
use DOMElement;

/**
 * Checks an uploaded file before it is kept. Every module that accepts files should use it:
 *
 *   $check = Upload::check($_FILES['file']);           // ['ok' => bool, 'error' => ?string, 'ext' => 'jpg', 'mime' => 'image/jpeg', 'kind' => 'image', ...]
 *   if ($check['ok']) { ... move $check['tmp'] somewhere ... }
 *
 * What it checks: PHP's upload error, real upload (not a path on the server), size limit, allowed extension
 * (Settings > Limits and Uploads), scripts and double extensions such as "photo.php.jpg", that the content
 * matches the extension (a "jpg" must really be an image), image size limits, and it cleans SVG drawings.
 */
final class Upload
{
    /** Never allowed, whatever the settings say. */
    public const BLOCKED = ['php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'phar', 'pht', 'phps', 'cgi', 'pl', 'py', 'rb', 'sh', 'bash', 'exe', 'com', 'bat', 'cmd', 'msi',
        'js', 'mjs', 'jsp', 'asp', 'aspx', 'htaccess', 'htpasswd', 'html', 'htm', 'shtml', 'xhtml', 'xml', 'svgz', 'hta', 'vbs', 'jar', 'dll', 'so', 'ini', 'env'];

    /** What each extension's content may look like (from the file itself, not from the browser). */
    private const MIMES = [
        'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'], 'png' => ['image/png'], 'gif' => ['image/gif'], 'webp' => ['image/webp'], 'avif' => ['image/avif'],
        'svg' => ['image/svg+xml', 'text/xml', 'application/xml', 'text/plain', 'text/html'], 'ico' => ['image/vnd.microsoft.icon', 'image/x-icon'],
        'pdf' => ['application/pdf'], 'txt' => ['text/plain'], 'csv' => ['text/csv', 'text/plain', 'application/csv'],
        'doc' => ['application/msword', 'application/vnd.ms-office', 'application/CDFV2'], 'xls' => ['application/vnd.ms-excel', 'application/vnd.ms-office', 'application/CDFV2'],
        'ppt' => ['application/vnd.ms-powerpoint', 'application/vnd.ms-office', 'application/CDFV2'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/octet-stream'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'application/octet-stream'],
        'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip', 'application/octet-stream'],
        'odt' => ['application/vnd.oasis.opendocument.text', 'application/zip'], 'ods' => ['application/vnd.oasis.opendocument.spreadsheet', 'application/zip'],
        'zip' => ['application/zip', 'application/x-zip-compressed'], 'mp3' => ['audio/mpeg'], 'mp4' => ['video/mp4'], 'webm' => ['video/webm', 'audio/webm'],
        'm4a' => ['audio/mp4', 'audio/x-m4a'], 'ogg' => ['audio/ogg', 'video/ogg'], 'wav' => ['audio/x-wav', 'audio/wav'],
        'woff' => ['font/woff', 'application/font-woff', 'application/octet-stream'], 'woff2' => ['font/woff2', 'application/octet-stream'],
    ];
    public const IMAGES = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'svg', 'ico'];
    private const MAX_PIXELS = 60_000_000;

    /** Allowed extensions from the settings (a list of rows, or old comma text), minus the blocked ones. @return list<string> */
    public static function allowed(): array
    {
        $raw = setting('uploads.allowed_extensions', 'jpg,jpeg,png,gif,webp,svg,pdf,doc,docx,xls,xlsx,zip');
        $list = [];
        if (is_array($raw)) {
            foreach ($raw as $row) {
                $list[] = is_array($row) ? (string) ($row['ext'] ?? reset($row)) : (string) $row;
            }
        } else {
            $list = preg_split('/[\s,]+/', (string) $raw) ?: [];
        }
        $list = array_map(static fn($e) => strtolower(ltrim(trim($e), '.')), $list);
        return array_values(array_unique(array_filter($list, static fn($e) => $e !== '' && preg_match('/^[a-z0-9]{1,10}$/', $e) && !in_array($e, self::BLOCKED, true))));
    }

    public static function maxBytes(): int
    {
        $mb = max(1, (int) setting('uploads.max_size_mb', 8));
        $server = min(self::iniBytes((string) ini_get('upload_max_filesize')), self::iniBytes((string) ini_get('post_max_size')));
        return $server > 0 ? min($mb * 1048576, $server) : $mb * 1048576;
    }

    /**
     * @param array{name?:string,tmp_name?:string,error?:int,size?:int} $file one entry of $_FILES
     * @param list<string>|null $only limit to these extensions (for example only images)
     * @param bool $fromRequest false in tests and imports, where the file was not posted by a browser
     * @return array{ok:bool,error:?string,name:string,ext:string,mime:string,kind:string,size:int,width:?int,height:?int,tmp:string}
     */
    public static function check(array $file, ?array $only = null, bool $fromRequest = true): array
    {
        $name = self::cleanName((string) ($file['name'] ?? 'file'));
        $tmp = (string) ($file['tmp_name'] ?? '');
        $out = ['ok' => false, 'error' => null, 'name' => $name, 'ext' => '', 'mime' => '', 'kind' => 'file', 'size' => (int) ($file['size'] ?? 0), 'width' => null, 'height' => null, 'tmp' => $tmp];
        $fail = static function (string $message) use (&$out): array {
            $out['error'] = $message;
            return $out;
        };
        $err = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($err !== UPLOAD_ERR_OK) {
            return $fail(match ($err) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'The file is bigger than your server accepts (' . format_bytes(self::maxBytes()) . ').',
                UPLOAD_ERR_PARTIAL => 'Only part of the file arrived. Please try again.',
                UPLOAD_ERR_NO_FILE => 'No file was chosen.',
                UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE => 'The server could not save the file. Ask your host to check the temporary folder.',
                default => 'The upload was stopped by the server.',
            });
        }
        if ($tmp === '' || !is_file($tmp) || ($fromRequest && !is_uploaded_file($tmp))) {
            return $fail('The file did not arrive as an upload.');
        }
        $size = (int) filesize($tmp);
        $out['size'] = $size;
        if ($size <= 0) {
            return $fail('The file is empty.');
        }
        if ($size > self::maxBytes()) {
            return $fail('The file is ' . format_bytes($size) . '. The largest allowed is ' . format_bytes(self::maxBytes()) . '.');
        }
        $parts = explode('.', strtolower($name));
        $ext = count($parts) > 1 ? (string) end($parts) : '';
        $out['ext'] = $ext;
        // Look at the name as it was sent: "shell.php.jpg" must not slip through as "shell-php.jpg".
        $sent = explode('.', strtolower(basename(str_replace('\\', '/', (string) ($file['name'] ?? '')))));
        foreach (array_slice($sent, 1) as $part) {
            if (in_array($part, self::BLOCKED, true)) {
                return $fail('Files with ".' . $part . '" in their name cannot be uploaded.');
            }
        }
        $allowed = self::allowed();
        if ($only !== null) {
            $allowed = array_values(array_intersect($allowed, $only));
        }
        if ($ext === '' || !in_array($ext, $allowed, true)) {
            return $fail('".' . ($ext ?: '?') . '" files are not allowed here. Allowed: ' . implode(', ', $allowed) . '.');
        }
        $mime = function_exists('finfo_open') ? (string) (new \finfo(FILEINFO_MIME_TYPE))->file($tmp) : '';
        $out['mime'] = $mime ?: 'application/octet-stream';
        $expected = self::MIMES[$ext] ?? null;
        if ($mime !== '' && $expected !== null && !in_array($mime, $expected, true)) {
            return $fail('The file says it is ".' . $ext . '" but its content is ' . $mime . '. Save it again in the right format.');
        }
        $head = (string) file_get_contents($tmp, false, null, 0, 4096);
        if ($ext !== 'svg' && preg_match('/<\?php|<script\b/i', $head) && !in_array($ext, ['txt', 'csv', 'zip', 'docx', 'xlsx', 'pptx', 'odt', 'ods'], true)) {
            return $fail('The file contains program code and cannot be uploaded.');
        }
        if (in_array($ext, self::IMAGES, true)) {
            $out['kind'] = 'image';
            if ($ext === 'svg') {
                $clean = self::cleanSvg((string) file_get_contents($tmp));
                if ($clean === null) {
                    return $fail('The SVG drawing could not be read.');
                }
                file_put_contents($tmp, $clean);
                $out['size'] = strlen($clean);
                $out['mime'] = 'image/svg+xml';
            } elseif ($ext !== 'ico') {
                $info = @getimagesize($tmp);
                if (!$info) {
                    return $fail('The image could not be read. It may be damaged.');
                }
                [$w, $h] = $info;
                if ($w * $h > self::MAX_PIXELS) {
                    return $fail('The image is ' . $w . ' × ' . $h . ' pixels, which is too big to work with. Make it smaller first.');
                }
                $out['width'] = (int) $w;
                $out['height'] = (int) $h;
            }
        } elseif (str_starts_with($out['mime'], 'video/')) {
            $out['kind'] = 'video';
        } elseif (str_starts_with($out['mime'], 'audio/')) {
            $out['kind'] = 'audio';
        } else {
            $out['kind'] = 'document';
        }
        $out['ok'] = true;
        return $out;
    }

    /** A safe, readable file name: "Summer Photo (1).JPG" → "summer-photo-1.jpg". */
    public static function cleanName(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $dot = strrpos($name, '.');
        $base = $dot === false ? $name : substr($name, 0, $dot);
        $ext = $dot === false ? '' : strtolower(substr($name, $dot + 1));
        $base = strtr(mb_strtolower($base), ['ç' => 'c', 'ğ' => 'g', 'ı' => 'i', 'ö' => 'o', 'ş' => 's', 'ü' => 'u', 'ä' => 'a', 'ß' => 'ss', 'é' => 'e', 'è' => 'e', 'á' => 'a', 'à' => 'a', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n']);
        $base = trim((string) preg_replace('/[^a-z0-9]+/', '-', $base), '-');
        $base = substr($base !== '' ? $base : 'file', 0, 80);
        $ext = (string) preg_replace('/[^a-z0-9]/', '', $ext);
        return $ext !== '' ? $base . '.' . $ext : $base;
    }

    /** SVG without scripts, event handlers, foreign content or outside links. Null when it is not a valid drawing. */
    public static function cleanSvg(string $svg): ?string
    {
        if (stripos($svg, '<!ENTITY') !== false || stripos($svg, '<!DOCTYPE') !== false) {
            return null;
        }
        $doc = new DOMDocument();
        $prev = libxml_use_internal_errors(true);
        $ok = $doc->loadXML($svg, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        if (!$ok || !$doc->documentElement || strtolower($doc->documentElement->localName) !== 'svg') {
            return null;
        }
        $bad = ['script', 'foreignobject', 'iframe', 'embed', 'object', 'handler', 'listener', 'animate', 'set', 'animatemotion', 'animatetransform'];
        $walk = static function (DOMElement $el) use (&$walk, $bad): void {
            foreach (iterator_to_array($el->childNodes) as $child) {
                if ($child instanceof DOMElement) {
                    if (in_array(strtolower($child->localName), $bad, true)) {
                        $el->removeChild($child);
                        continue;
                    }
                    $walk($child);
                } elseif ($child->nodeType === XML_PI_NODE) {
                    $el->removeChild($child);
                }
            }
            foreach (iterator_to_array($el->attributes) as $attr) {
                $n = strtolower($attr->localName);
                $v = strtolower(trim((string) preg_replace('/\s+/', '', $attr->value)));
                if (str_starts_with($n, 'on') || (in_array($n, ['href', 'src'], true) && $v !== '' && !str_starts_with($v, '#') && !str_starts_with($v, 'data:image/'))
                    || str_contains($v, 'javascript:') || ($n === 'style' && (str_contains($v, 'url(') || str_contains($v, 'expression(')))) {
                    $el->removeAttributeNode($attr);
                }
            }
        };
        $walk($doc->documentElement);
        return (string) $doc->saveXML($doc->documentElement);
    }

    private static function iniBytes(string $v): int
    {
        $v = trim($v);
        if ($v === '' || $v === '-1' || $v === '0') {
            return 0;
        }
        $n = (int) $v;
        return match (strtolower(substr($v, -1))) {
            'g' => $n * 1073741824, 'm' => $n * 1048576, 'k' => $n * 1024, default => $n,
        };
    }
}
