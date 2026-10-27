<?php
declare(strict_types=1);

namespace AP\Modules\Money\Controllers;

use AP\Core\Activity;
use AP\Core\Request;
use AP\Core\Response;

/** Choose how every button in the admin looks. The style is a set of CSS rules in css/ui.css, switched by <html data-btn="…">. */
final class ButtonController
{
    /** @var array<string,array{0:string,1:string}> name => [label, one line about it] */
    public const STYLES = [
        'classic' => ['Classic', 'Plain and familiar, gently rounded.'],
        'pill' => ['Pill', 'Fully rounded ends, friendly and light.'],
        'halo' => ['Halo', 'Soft rounded with a quiet glow around the main button.'],
        'ink' => ['Ink', 'Deep black fill with crisp outlines.'],
        'stamp' => ['Stamp', 'Square, bold capitals with a hard offset shadow.'],
        'keycap' => ['Keycap', 'Chunky like a keyboard key, presses down when clicked.'],
        'softgel' => ['Soft Gel', 'Glossy gradient with a bright top edge.'],
        'slide' => ['Slide', 'A darker colour sweeps across when you point at it.'],
        'duo' => ['Duo', 'Tinted background that fills solid on hover.'],
        'sky' => ['Sky', 'Pill with a blue-to-cyan gradient.'],
        'sharp' => ['Sharp', 'Tight corners, small capitals, very tidy.'],
        'ribbon' => ['Ribbon', 'White buttons with a coloured bar on the left.'],
    ];

    public function index(Request $request): Response
    {
        $current = (string) setting('money.button_style', 'classic');
        return view('money::buttons', ['title' => 'Button Style', 'breadcrumbs' => ['Settings' => admin_url('settings'), 'Button Style' => null],
            'styles' => self::STYLES, 'current' => isset(self::STYLES[$current]) ? $current : 'classic']);
    }

    public function save(Request $request): Response
    {
        $style = $request->string('style');
        if (!isset(self::STYLES[$style])) {
            session()->toast('danger', 'Choose one of the styles.');
            return redirect(admin_url('appearance/buttons'));
        }
        app(\AP\Core\Settings::class)->set('money.button_style', $style, auth_user() ? (int) auth_user()['id'] : null);
        Activity::log('money.button_style', 'Changed the button style to ' . self::STYLES[$style][0]);
        session()->toast('success', 'Button style changed to ' . self::STYLES[$style][0] . '.');
        return redirect(admin_url($request->string('after') === 'stay' ? 'appearance/buttons' : 'settings'));
    }
}
