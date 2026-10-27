<?php
declare(strict_types=1);

namespace AP\Core;

use GdImage;

/**
 * Small image tools on top of PHP's GD extension (available on almost every host):
 * turn photos upright, shrink to a width, save smaller copies, and drop camera data (location, device).
 *
 *   Image::fit('/path/photo.jpg', 2400);                         // shrink in place if wider
 *   Image::variant('/path/photo.jpg', '/path/photo-400.jpg', 400); // a smaller copy
 */
final class Image
{
    public static function available(): bool
    {
        return function_exists('imagecreatetruecolor');
    }

    public static function supports(string $ext): bool
    {
        $ext = strtolower($ext);
        return self::available() && match ($ext) {
            'jpg', 'jpeg' => function_exists('imagecreatefromjpeg'),
            'png' => function_exists('imagecreatefrompng'),
            'webp' => function_exists('imagecreatefromwebp') && function_exists('imagewebp'),
            'gif' => function_exists('imagecreatefromgif'),
            default => false,
        };
    }

    /**
     * Rewrites the image: upright, no wider than $maxWidth, without camera data. Animated GIFs are left alone.
     * @return array{width:int,height:int,changed:bool}|null
     */
    public static function fit(string $path, int $maxWidth, int $quality = 82): ?array
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (!self::supports($ext) || ($ext === 'gif' && self::animated($path))) {
            $info = @getimagesize($path);
            return $info ? ['width' => (int) $info[0], 'height' => (int) $info[1], 'changed' => false] : null;
        }
        $img = self::load($path, $ext);
        if (!$img) {
            return null;
        }
        $img = self::upright($img, $path, $ext);
        $w = imagesx($img);
        $h = imagesy($img);
        if ($maxWidth > 0 && $w > $maxWidth) {
            $img = self::scale($img, $maxWidth);
            $w = imagesx($img);
            $h = imagesy($img);
        }
        $ok = self::save($img, $path, $ext, $quality);
        return $ok ? ['width' => $w, 'height' => $h, 'changed' => true] : null;
    }

    /** A smaller copy, only when the original is wider than $width. Returns the new size, or null when nothing was made. @return array{width:int,height:int}|null */
    public static function variant(string $source, string $target, int $width, int $quality = 80): ?array
    {
        $ext = strtolower(pathinfo($source, PATHINFO_EXTENSION));
        if (!self::supports($ext) || ($ext === 'gif' && self::animated($source))) {
            return null;
        }
        $info = @getimagesize($source);
        if (!$info || (int) $info[0] <= $width) {
            return null;
        }
        $img = self::load($source, $ext);
        if (!$img) {
            return null;
        }
        $img = self::scale($img, $width);
        if (!self::save($img, $target, $ext, $quality)) {
            return null;
        }
        return ['width' => imagesx($img), 'height' => imagesy($img)];
    }

    private static function load(string $path, string $ext): ?GdImage
    {
        $img = match ($ext) {
            'jpg', 'jpeg' => @imagecreatefromjpeg($path),
            'png' => @imagecreatefrompng($path),
            'webp' => @imagecreatefromwebp($path),
            'gif' => @imagecreatefromgif($path),
            default => false,
        };
        return $img instanceof GdImage ? $img : null;
    }

    private static function scale(GdImage $img, int $width): GdImage
    {
        $w = imagesx($img);
        $h = imagesy($img);
        $height = max(1, (int) round($h * $width / $w));
        $out = imagecreatetruecolor($width, $height);
        imagealphablending($out, false);
        imagesavealpha($out, true);
        imagefill($out, 0, 0, imagecolorallocatealpha($out, 0, 0, 0, 127));
        imagecopyresampled($out, $img, 0, 0, 0, 0, $width, $height, $w, $h);
        return $out;
    }

    /** Photos from phones are often stored sideways with an "orientation" note; turn them the right way. */
    private static function upright(GdImage $img, string $path, string $ext): GdImage
    {
        if (!in_array($ext, ['jpg', 'jpeg'], true) || !function_exists('exif_read_data')) {
            return $img;
        }
        $exif = @exif_read_data($path);
        $o = (int) ($exif['Orientation'] ?? 1);
        $rotated = match ($o) {
            3 => imagerotate($img, 180, 0),
            6 => imagerotate($img, -90, 0),
            8 => imagerotate($img, 90, 0),
            default => $img,
        };
        return $rotated instanceof GdImage ? $rotated : $img;
    }

    private static function save(GdImage $img, string $path, string $ext, int $quality): bool
    {
        $tmp = $path . '.tmp-' . bin2hex(random_bytes(3));
        $quality = max(30, min(100, $quality));
        $ok = match ($ext) {
            'jpg', 'jpeg' => imagejpeg($img, $tmp, $quality),
            'png' => imagepng($img, $tmp, 6),
            'webp' => imagewebp($img, $tmp, $quality),
            'gif' => imagegif($img, $tmp),
            default => false,
        };
        if (!$ok || !is_file($tmp) || filesize($tmp) === 0) {
            @unlink($tmp);
            return false;
        }
        return rename($tmp, $path);
    }

    private static function animated(string $path): bool
    {
        $data = (string) @file_get_contents($path);
        return preg_match_all('/\x00\x21\xF9\x04.{4}\x00[\x2C\x21]/s', $data) > 1;
    }
}
