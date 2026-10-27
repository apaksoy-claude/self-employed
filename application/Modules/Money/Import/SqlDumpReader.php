<?php
declare(strict_types=1);

namespace AP\Modules\Money\Import;

use RuntimeException;

/**
 * Reads rows out of a phpMyAdmin / mysqldump .sql file without needing a database.
 * Only the tables you ask for are read; everything else in the file is skipped.
 */
final class SqlDumpReader
{
    private string $sql;

    public function __construct(string $path)
    {
        $data = is_file($path) ? file_get_contents($path) : false;
        if ($data === false || $data === '') {
            throw new RuntimeException('The database file could not be read.');
        }
        $this->sql = $data;
    }

    public static function fromString(string $sql): self
    {
        $r = (new \ReflectionClass(self::class))->newInstanceWithoutConstructor();
        $r->sql = $sql;
        return $r;
    }

    public function hasTable(string $table): bool
    {
        return str_contains($this->sql, 'CREATE TABLE `' . $table . '`');
    }

    /** @return list<array<string, string|int|float|null>> rows as column => value (integers and decimals become numbers, everything else text) */
    public function rows(string $table): array
    {
        if (!preg_match('/CREATE TABLE `' . preg_quote($table, '/') . '` \((.*?)\n\) ENGINE/s', $this->sql, $m)) {
            return [];
        }
        preg_match_all('/^\s+`(\w+)`/m', $m[1], $cols);
        $columns = $cols[1];
        $out = [];
        $pattern = '/INSERT INTO `' . preg_quote($table, '/') . '` \([^)]*\) VALUES\s*/';
        $offset = 0;
        while (preg_match($pattern, $this->sql, $im, PREG_OFFSET_CAPTURE, $offset)) {
            $i = $im[0][1] + strlen($im[0][0]);
            $n = strlen($this->sql);
            while ($i < $n) {
                while ($i < $n && ($this->sql[$i] === ' ' || $this->sql[$i] === "\n" || $this->sql[$i] === "\r" || $this->sql[$i] === ',')) {
                    $i++;
                }
                if ($i >= $n || $this->sql[$i] === ';') {
                    break;
                }
                if ($this->sql[$i] !== '(') {
                    throw new RuntimeException('The database file has an unexpected shape near table ' . $table . '.');
                }
                $i++;
                $row = [];
                while (true) {
                    while ($this->sql[$i] === ' ') {
                        $i++;
                    }
                    if ($this->sql[$i] === "'") {
                        [$value, $i] = $this->readString($i);
                        $row[] = $value;
                    } else {
                        $j = $i;
                        while ($this->sql[$j] !== ',' && $this->sql[$j] !== ')') {
                            $j++;
                        }
                        $tok = trim(substr($this->sql, $i, $j - $i));
                        $row[] = $tok === 'NULL' ? null : (is_numeric($tok) ? (preg_match('/^-?\d+$/', $tok) ? (int) $tok : (float) $tok) : $tok);
                        $i = $j;
                    }
                    if ($this->sql[$i] === ',') {
                        $i++;
                        continue;
                    }
                    $i++; // the closing bracket
                    break;
                }
                if (count($row) !== count($columns)) {
                    throw new RuntimeException('A row of table ' . $table . ' has ' . count($row) . ' values but the table has ' . count($columns) . ' columns.');
                }
                $out[] = array_combine($columns, $row);
            }
            $offset = $i;
        }
        return $out;
    }

    /** @return array{0:string,1:int} text and the position after the closing quote */
    private function readString(int $i): array
    {
        $buf = '';
        $i++;
        $n = strlen($this->sql);
        while ($i < $n) {
            $next = strcspn($this->sql, "'\\", $i);
            $buf .= substr($this->sql, $i, $next);
            $i += $next;
            $c = $this->sql[$i];
            if ($c === '\\') {
                $e = $this->sql[$i + 1];
                $buf .= match ($e) {
                    'n' => "\n", 'r' => "\r", 't' => "\t", '0' => "\0", 'Z' => "\x1a", 'b' => "\x08",
                    default => $e,
                };
                $i += 2;
            } elseif (($this->sql[$i + 1] ?? '') === "'") {
                $buf .= "'"; // a doubled quote means one quote inside the text
                $i += 2;
            } else {
                return [$buf, $i + 1];
            }
        }
        throw new RuntimeException('The database file ends in the middle of a text value.');
    }
}
