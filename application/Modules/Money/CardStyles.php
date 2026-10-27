<?php
declare(strict_types=1);

namespace AP\Modules\Money;

/**
 * The look of the quarter and month cards on the dashboard. One style is chosen for everything (Settings > Card Style) and any
 * card can choose its own. The looks are CSS rules in css/ui.css (.cs-a to .cs-j); this class builds the markup they style.
 */
final class CardStyles
{
    public const SETTING = 'money.card_style';

    /** @var array<string,array{0:string,1:string}> key => [name, one line about it] */
    public const STYLES = [
        'a' => ['Quiet Line', 'White with a hairline and a plain total row.'],
        'b' => ['Slate Header', 'A muted slate title band over a white body.'],
        'c' => ['Soft Panel', 'A grey tray holding a white panel.'],
        'd' => ['Profit First', 'The profit leads, income and costs sit in small tiles.'],
        'e' => ['Dark Footer', 'White card with a slate footer carrying the profit.'],
        'f' => ['Sage Bands', 'Soft green-grey bands for the title and the total.'],
        'g' => ['Ledger', 'Accountant style with dotted lines and a double underline.'],
        'h' => ['Slate Card', 'The whole card in deep slate.'],
        'i' => ['Edge Mark', 'A slim side mark and a pale total band.'],
        'j' => ['Duo Tone', 'A pale tinted body over a white total.'],
    ];

    public const DEFAULT = 'e';

    /** The style everything uses unless a card chooses its own. */
    public static function current(): string
    {
        $s = (string) setting(self::SETTING, self::DEFAULT);
        return isset(self::STYLES[$s]) ? $s : self::DEFAULT;
    }

    /** The style for one card: its own choice if it has one, else the common one. */
    public static function pick(?string $own): string
    {
        return $own !== null && isset(self::STYLES[$own]) ? $own : self::current();
    }

    /**
     * @param array{title:string,sub?:string,chip?:array{0:string,1:string},rows:list<array{0:string,1:string,2:string}>,profit:string,extra?:string,class?:string} $d
     *   rows are [label, value as safe html, "p" for green or "n" for red]; every text is already escaped by the caller
     */
    public static function render(string $style, array $d): string
    {
        $style = isset(self::STYLES[$style]) ? $style : self::DEFAULT;
        $chip = !empty($d['chip']) ? '<span class="mn-chip ' . $d['chip'][1] . '">' . $d['chip'][0] . '</span>' : '';
        $head = '<div class="cs-hd"><h3>' . $d['title'] . (!empty($d['sub']) ? '<small>' . $d['sub'] . '</small>' : '') . '</h3>' . $chip . '</div>';
        $rows = '';
        foreach ($d['rows'] as [$label, $value, $tone]) {
            $rows .= '<div class="cs-r"><span>' . $label . '</span><b class="cs-' . ($tone === 'n' ? 'n' : 'p') . '">' . $value . '</b></div>';
        }
        $tot = '<div class="cs-tot"><span>Profit</span><strong>' . $d['profit'] . '</strong></div>';
        $extra = $d['extra'] ?? '';
        $open = '<section class="cs cs-' . $style . (!empty($d['class']) ? ' ' . $d['class'] : '') . '">';
        if ($style === 'c') {
            return $open . $head . '<div class="cs-in">' . $rows . $extra . $tot . '</div></section>';
        }
        if ($style === 'd') {
            $two = '<div class="cs-two">';
            foreach ($d['rows'] as [$label, $value]) {
                $two .= '<div><small>' . $label . '</small><b>' . $value . '</b></div>';
            }
            return $open . $head . '<div class="cs-big"><span>Profit</span><strong>' . $d['profit'] . '</strong></div>' . $two . '</div>' . $extra . '</section>';
        }
        if ($style === 'h') {
            return $open . $head . '<div class="cs-bd">' . $rows . $extra . '</div>' . $tot . '</section>';
        }
        return $open . $head . '<div class="cs-bd">' . $rows . $extra . '</div>' . $tot . '</section>';
    }

    /** A made-up quarter and month for showing a style. */
    public static function sample(string $style): string
    {
        $q = self::render($style, ['title' => 'Quarter 2', 'sub' => '6 Jul to 5 Oct', 'chip' => ['Due in 12 days', 'mn-chip-info'],
            'rows' => [['Income', '£4,820.50', 'p'], ['Costs', '£1,236.20', 'n']], 'profit' => '£3,584.30']);
        $m = self::render($style, ['title' => 'October 2026',
            'rows' => [['Income', '£1,640.00', 'p'], ['Costs', '£412.75', 'n']], 'profit' => '£1,227.25']);
        return '<div class="cs-pair">' . $q . $m . '</div>';
    }
}
