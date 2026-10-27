<?php
declare(strict_types=1);

namespace AP\Modules\Settings;

/**
 * "Show this setting only when..." rules, checked live in the browser.
 *
 *   'show_if' => ['email.driver' => ['smtp', 'phpmailer']]                        // one rule
 *   'show_if' => [['email.driver' => ['smtp']], ['email.backup_driver' => ['smtp']]]  // any of these rules
 *
 * Inside one rule every setting must match. Values may be a single value or a list. Hidden fields are still posted and saved.
 */
final class ShowIf
{
    /** @param list<array> $rules several "show_if" values; the result shows when any of them matches. @return list<array<string,list<string>>> */
    public static function normalise(array $rules): array
    {
        $out = [];
        foreach ($rules as $r) {
            $r = (array) $r;
            $isList = array_is_list($r) && $r !== [] && is_array($r[0]);
            foreach ($isList ? $r : [$r] as $rule) {
                $clean = [];
                foreach ((array) $rule as $key => $values) {
                    $clean[str_replace('.', '_', (string) $key)] = array_map('strval', (array) $values);
                }
                if ($clean) {
                    $out[] = $clean;
                }
            }
        }
        return $out;
    }
}
