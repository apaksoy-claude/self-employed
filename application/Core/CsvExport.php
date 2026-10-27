<?php
declare(strict_types=1);

namespace AP\Core;

/** CSV download with a spreadsheet formula-injection guard. */
final class CsvExport
{
    public static function safe(mixed $cell): string
    {
        $s = (string) ($cell ?? '');
        if ($s !== '' && in_array($s[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'" . $s;
        }
        return $s;
    }

    /** @param string[] $headers @param iterable<array<int|string,mixed>> $rows */
    public static function response(string $filename, array $headers, iterable $rows): Response
    {
        $h = fopen('php://temp', 'r+');
        fwrite($h, "\xEF\xBB\xBF");
        fputcsv($h, array_map([self::class, 'safe'], $headers), ',', '"', '\\');
        foreach ($rows as $row) {
            fputcsv($h, array_map([self::class, 'safe'], array_values($row)), ',', '"', '\\');
        }
        rewind($h);
        $csv = (string) stream_get_contents($h);
        fclose($h);
        return Response::download($csv, $filename, 'text/csv; charset=utf-8');
    }
}
