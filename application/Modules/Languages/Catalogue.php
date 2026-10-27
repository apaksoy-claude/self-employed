<?php
declare(strict_types=1);

namespace AP\Modules\Languages;

/** Languages an admin can add with one click: code => [English name, native name, rtl]. */
final class Catalogue
{
    public const LANGUAGES = [
        'ar' => ['Arabic', 'العربية', true], 'bg' => ['Bulgarian', 'Български', false], 'bn' => ['Bengali', 'বাংলা', false],
        'ca' => ['Catalan', 'Català', false], 'cs' => ['Czech', 'Čeština', false], 'da' => ['Danish', 'Dansk', false],
        'de' => ['German', 'Deutsch', false], 'el' => ['Greek', 'Ελληνικά', false], 'en' => ['English', 'English', false],
        'es' => ['Spanish', 'Español', false], 'et' => ['Estonian', 'Eesti', false], 'fa' => ['Persian', 'فارسی', true],
        'fi' => ['Finnish', 'Suomi', false], 'fr' => ['French', 'Français', false], 'he' => ['Hebrew', 'עברית', true],
        'hi' => ['Hindi', 'हिन्दी', false], 'hr' => ['Croatian', 'Hrvatski', false], 'hu' => ['Hungarian', 'Magyar', false],
        'id' => ['Indonesian', 'Bahasa Indonesia', false], 'it' => ['Italian', 'Italiano', false], 'ja' => ['Japanese', '日本語', false],
        'ko' => ['Korean', '한국어', false], 'lt' => ['Lithuanian', 'Lietuvių', false], 'lv' => ['Latvian', 'Latviešu', false],
        'ms' => ['Malay', 'Bahasa Melayu', false], 'nl' => ['Dutch', 'Nederlands', false], 'no' => ['Norwegian', 'Norsk', false],
        'pl' => ['Polish', 'Polski', false], 'pt' => ['Portuguese', 'Português', false], 'ro' => ['Romanian', 'Română', false],
        'ru' => ['Russian', 'Русский', false], 'sk' => ['Slovak', 'Slovenčina', false], 'sl' => ['Slovenian', 'Slovenščina', false],
        'sr' => ['Serbian', 'Српски', false], 'sv' => ['Swedish', 'Svenska', false], 'sw' => ['Swahili', 'Kiswahili', false],
        'th' => ['Thai', 'ไทย', false], 'tr' => ['Turkish', 'Türkçe', false], 'uk' => ['Ukrainian', 'Українська', false],
        'ur' => ['Urdu', 'اردو', true], 'vi' => ['Vietnamese', 'Tiếng Việt', false], 'zh' => ['Chinese (Simplified)', '简体中文', false],
        'zh-TW' => ['Chinese (Traditional)', '繁體中文', false], 'pt-BR' => ['Portuguese (Brazil)', 'Português (Brasil)', false],
        'es-MX' => ['Spanish (Mexico)', 'Español (México)', false], 'en-US' => ['English (US)', 'English (US)', false],
    ];
}
