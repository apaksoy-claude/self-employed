<?php
declare(strict_types=1);

/**
 * Builds documentation/index.html, the manual for Money, from the Help Centre articles (application/Modules/Money/Help.php),
 * the chapters below and CHANGELOG.md, so the manual and the Help Centre always say the same.
 *
 *   php tools/build-docs.php            writes documentation/index.html
 *   php tools/build-docs.php --check    exits 1 when documentation/index.html is out of date
 */

$root = dirname(__DIR__);
require_once $root . '/application/Modules/Money/Help.php';

use AP\Modules\Money\Help;

$version = trim((string) @file_get_contents($root . '/application/VERSION')) ?: '0.0.0';
$e = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

/** Chapters only the manual has: putting Money on a website, keeping it updated, and what it means for privacy. */
$chapters = [
    'install' => ['title' => 'Installing Money', 'icon' => 'download', 'group' => 'Setting Up', 'body' => <<<'HTML'
<p>Money runs on ordinary web hosting with cPanel. It needs no special software on the server.</p>
<h3>What The Hosting Needs</h3>
<ul>
<li>PHP 8.1 or newer (cPanel &gt; Select PHP Version), with the extensions pdo_mysql, mbstring, openssl, json, ctype and fileinfo. Also switch on gd (images), curl (card payments), zip (update packages) and intl. The installer checks each one and says what to switch on.</li>
<li>A MySQL or MariaDB database and a user for it (made in cPanel &gt; MySQL Databases).</li>
<li>An SSL certificate for the website (cPanel &gt; SSL/TLS Status, often free). Money should always be opened with https.</li>
</ul>
<h3>Step By Step</h3>
<ol class="steps">
<li>In cPanel, make the database and its user, and give the user all privileges on the database. Note the three names and the password.</li>
<li>Upload everything in the package into the folder your domain points to (often <code>public_html</code>): <code>index.php</code>, <code>.htaccess</code>, <code>assets</code> and <code>application</code> side by side.</li>
<li>Make <code>application/storage</code> and <code>application/config</code> writable (755 or 775 in cPanel &gt; File Manager &gt; Permissions).</li>
<li>Open the website. The installer starts by itself: it checks the server, asks for the database details, the site name and the first admin account.</li>
<li><strong>Your Business</strong>: pick the kinds of work, how you work (vehicle, working from home, CIS, stock) and, if you like, demo data to try it first. Choose Fresh Start for an empty Money.</li>
<li>Check the summary and press Install. Sign in with the admin account: the getting-started tour explains each part.</li>
</ol>
<div class="tip">The <code>.htaccess</code> file keeps the <code>application</code> folder away from the web. If the host lets you, you can move <code>application</code> one folder above the website: <code>index.php</code> finds it by itself.</div>
HTML],
    'update' => ['title' => 'Updating Money', 'icon' => 'arrows-rotate', 'group' => 'Setting Up', 'body' => <<<'HTML'
<p>Each new version comes as an update package (a zip). Updates keep every record.</p>
<ol class="steps">
<li>Take a backup first (see Updates and Backups).</li>
<li>In the admin, open <strong>System &gt; Updates</strong> and upload the package. It checks the package and installs only what changed.</li>
<li>If the yellow banner asks, press <strong>Finish update</strong>. It updates the database and gives the Administrator any new permissions.</li>
</ol>
<p>Updates can also be copied over the site by FTP: never delete <code>application/config</code> or <code>application/storage</code>. Then open the admin and press Finish update.</p>
<p>Install updates in order. Each package says which version it updates from.</p>
HTML],
    'schedule' => ['title' => 'Tasks That Run By Themselves', 'icon' => 'clock', 'group' => 'Setting Up', 'body' => <<<'HTML'
<p>Some things happen on a schedule: repeating invoices and expenses, invoice reminders, checking the mailbox for receipts, scheduled reports and the weekly digest.</p>
<ul>
<li>Nothing is required: the built-in scheduler runs due tasks after normal page views.</li>
<li>For reliable timing, add a cron job in cPanel &gt; Cron Jobs, once a minute: <code>/usr/local/bin/php /home/USER/public_html/ap schedule:run</code> (change the path to your site), or call the secret web-cron address shown on System &gt; Scheduled tasks from a free cron service.</li>
</ul>
<p>System &gt; Scheduled tasks lists each task, when it last ran and what it did.</p>
HTML],
    'privacy' => ['title' => 'Privacy and Security', 'icon' => 'shield-halved', 'group' => 'Setting Up', 'body' => <<<'HTML'
<ul>
<li>Your records live only in your own copy of Money, in your own database on your own hosting. Nothing is sent to anyone else, and no one else holds a copy.</li>
<li>Passwords for email, Stripe and PayPal are stored encrypted with a key kept in <code>application/config</code>.</li>
<li>Receipts and files are kept in <code>application/storage</code>, which the web cannot open directly; they are shown only to people signed in with permission.</li>
<li>Clients see only their own invoices, through private links that cannot be guessed.</li>
<li>Give each person their own account with the access they need (Users &gt; Roles and access), and switch on two-step sign-in.</li>
<li>Take backups of the database and <code>application/storage</code>, and keep one away from the website.</li>
</ul>
HTML],
    'glossary' => ['title' => 'Words Used', 'icon' => 'book', 'group' => 'More', 'body' => <<<'HTML'
<dl class="glossary">
<dt>Tax year</dt><dd>6 April to 5 April. 2026-27 runs from 6 April 2026 to 5 April 2027.</dd>
<dt>Allowable costs</dt><dd>Business costs that come off your income before tax. Personal spending, pension payments and equipment (claimed separately) are not among them.</dd>
<dt>Cash basis</dt><dd>Income and costs count when the money comes in or goes out. HMRC's starting choice for sole traders.</dd>
<dt>Traditional accounting</dt><dd>Income and costs count when they are earned or due, with capital allowances for equipment and stock counted at the year end.</dd>
<dt>Trading allowance</dt><dd>£1,000 a year you can take off your income instead of your real costs.</dd>
<dt>Class 4 National Insurance</dt><dd>Paid on profits above the lower limit, through Self Assessment.</dd>
<dt>Payments on account</dt><dd>Two advance payments towards next year's tax, each half of this year's bill, due 31 January and 31 July.</dd>
<dt>Making Tax Digital (MTD)</dt><dd>Quarterly updates to HMRC through compatible software, for those whose income is over the limit.</dd>
<dt>Capital allowances</dt><dd>What can be claimed for equipment and vehicles bought to keep: the Annual Investment Allowance, first year allowances and writing down allowances.</dd>
<dt>VAT registration threshold</dt><dd>The takings over 12 months at which you must register for VAT.</dd>
<dt>Flat Rate Scheme</dt><dd>A VAT scheme where you pay a set percentage of your takings with VAT, and do not reclaim VAT on most costs.</dd>
<dt>CIS</dt><dd>The Construction Industry Scheme: contractors take tax off what they pay subcontractors for building work and pay it to HMRC.</dd>
<dt>Domestic reverse charge</dt><dd>For CIS building work between VAT-registered businesses, the customer accounts for the VAT instead of the supplier.</dd>
<dt>IMAP</dt><dd>The way email programs read a mailbox. Money uses it to read receipt emails.</dd>
</dl>
HTML],
];

/** A small part of Markdown, enough for the changelog: headings, lists, bold and code. */
$md = static function (string $text) use ($e): string {
    $html = '';
    $inList = false;
    foreach (preg_split('/\R/', $text) ?: [] as $line) {
        $inline = static function (string $s) use ($e): string {
            $s = $e($s);
            $s = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $s) ?? $s;
            return preg_replace('/`(.+?)`/', '<code>$1</code>', $s) ?? $s;
        };
        if (preg_match('/^- (.*)$/', $line, $m)) {
            if (!$inList) {
                $html .= "<ul>\n";
                $inList = true;
            }
            $html .= '<li>' . $inline($m[1]) . "</li>\n";
            continue;
        }
        if ($inList) {
            $html .= "</ul>\n";
            $inList = false;
        }
        if (preg_match('/^## (.*)$/', $line, $m)) {
            $html .= '<h3>' . $inline($m[1]) . "</h3>\n";
        } elseif (preg_match('/^# /', $line) || trim($line) === '') {
            continue;
        } else {
            $html .= '<p>' . $inline($line) . "</p>\n";
        }
    }
    return $html . ($inList ? "</ul>\n" : '');
};

$changelog = (string) @file_get_contents($root . '/CHANGELOG.md');
$chapters['changelog'] = ['title' => 'What Is New', 'icon' => 'list-check', 'group' => 'More', 'body' => $md($changelog)];

// The order of the manual: setting up, then the Help Centre's groups, then the rest.
$toc = [];
$sections = [];
$add = static function (string $id, string $title, string $icon, string $group, string $intro, string $body, string $path = '') use (&$toc, &$sections, $e): void {
    $toc[$group][] = [$id, $title];
    $sections[] = '<section id="' . $e($id) . '" data-doc data-words="' . $e(mb_strtolower($title . ' ' . strip_tags($body))) . '">'
        . '<h2><span class="ic">' . $e(mb_strtoupper(mb_substr($title, 0, 1))) . '</span>' . $e($title) . '</h2>'
        . ($intro !== '' ? '<p class="lead">' . $e($intro) . '</p>' : '') . $body . '</section>';
};
$articles = Help::articles();
$add('welcome', $articles['welcome']['title'], $articles['welcome']['icon'], 'Introduction', $articles['welcome']['intro'], $articles['welcome']['body']);
foreach (['install', 'update', 'schedule', 'privacy'] as $k) {
    $add($k, $chapters[$k]['title'], $chapters[$k]['icon'], $chapters[$k]['group'], '', $chapters[$k]['body']);
}
foreach (Help::grouped() as $g => $list) {
    foreach ($list as $k => $a) {
        if ($k === 'welcome') {
            continue;
        }
        $add($k, $a['title'], $a['icon'], Help::GROUPS[$g] ?? $g, $a['intro'], $a['body']);
    }
}
foreach (['glossary', 'changelog'] as $k) {
    $add($k, $chapters[$k]['title'], $chapters[$k]['icon'], $chapters[$k]['group'], '', $chapters[$k]['body']);
}

$nav = '';
foreach ($toc as $group => $items) {
    $nav .= '<div class="grp" data-grp><span>' . $e($group) . '</span>';
    foreach ($items as [$id, $title]) {
        $nav .= '<a href="#' . $e($id) . '" data-nav="' . $e($id) . '">' . $e($title) . '</a>';
    }
    $nav .= '</div>';
}

$css = <<<'CSS'
*{box-sizing:border-box}
:root{--accent:#2563eb;--ink:#1f2937;--sub:#6b7280;--line:#e3e6ee;--card:#F5F6FA}
html{scroll-behavior:smooth;scroll-padding-top:20px}
body{margin:0;background:#fff;color:var(--ink);font:16px/1.65 system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif}
a{color:var(--accent)}
.wrap{display:grid;grid-template-columns:290px minmax(0,1fr);min-height:100vh}
aside{position:sticky;top:0;height:100vh;overflow:auto;border-right:1px solid var(--line);padding:22px 16px;background:#fff}
.brand{display:flex;align-items:center;gap:10px;margin-bottom:4px}
.brand b{display:inline-flex;align-items:center;justify-content:center;width:38px;height:38px;border-radius:11px;background:var(--accent);color:#fff;font-size:20px}
.brand strong{font-size:17px;line-height:1.2}
.ver{color:var(--sub);font-size:13px;margin:0 0 14px 48px}
.find{width:100%;padding:9px 12px;border:1px solid var(--line);border-radius:10px;font:inherit;font-size:14px;margin-bottom:12px;background:var(--card)}
.grp{margin-bottom:10px}
.grp>span{display:block;padding:6px 10px 3px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--sub)}
.grp a{display:block;padding:6px 10px;border-radius:8px;color:#374151;text-decoration:none;font-size:14px}
.grp a:hover{background:var(--card)}
.grp a.on{background:#eaf1ff;color:#1d4ed8;font-weight:600}
main{padding:38px 54px 80px;max-width:980px}
.hero{padding:26px 28px;border-radius:18px;background:var(--card);border:1px solid var(--line);margin-bottom:34px}
.hero h1{margin:0 0 6px;font-size:30px;letter-spacing:-.01em}
.hero p{margin:0;color:#374151}
section{padding:6px 0 26px;border-bottom:1px solid var(--line);margin-bottom:26px}
section h2{display:flex;align-items:center;gap:12px;font-size:23px;margin:0 0 6px;letter-spacing:-.01em}
.ic{display:inline-flex;align-items:center;justify-content:center;width:34px;height:34px;border-radius:10px;background:#eaf1ff;color:var(--accent);font-size:16px;font-weight:700}
.lead{color:var(--sub);font-size:17px;margin-top:0}
h3{font-size:17px;margin:22px 0 8px}
.path{display:inline-block;font-size:13px;font-weight:600;color:#1d4ed8;background:#eaf1ff;border-radius:999px;padding:3px 12px}
ol.steps{list-style:none;counter-reset:s;padding:0}
ol.steps li{counter-increment:s;position:relative;padding:10px 14px 10px 52px;border-radius:12px;background:var(--card);margin:8px 0}
ol.steps li::before{content:counter(s);position:absolute;left:14px;top:10px;width:26px;height:26px;border-radius:8px;background:var(--accent);color:#fff;font-weight:700;font-size:14px;display:flex;align-items:center;justify-content:center}
ul li{margin:4px 0}
.tip{margin:16px 0;padding:12px 16px;border-radius:12px;background:#fff6e2;border:1px solid #f6dfa8;color:#7a4d00}
code{font:13.5px/1.4 ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;background:var(--card);border:1px solid var(--line);border-radius:6px;padding:1px 6px}
.badge{display:inline-block;padding:1px 9px;border-radius:999px;font-size:13px;font-weight:600}
.badge.danger{background:#dc3545;color:#fff}.badge.warning{background:#f59e0b;color:#fff}.badge.success{background:#16a34a;color:#fff}.badge.dark{background:#1f2937;color:#fff}
dl.glossary dt{font-weight:700;margin-top:12px}
dl.glossary dd{margin:2px 0 0;color:#374151}
.none{display:none;color:var(--sub)}
.foot{color:var(--sub);font-size:13px}
.menu-btn{display:none}
@media (max-width:900px){.wrap{grid-template-columns:1fr}aside{position:static;height:auto;border-right:0;border-bottom:1px solid var(--line)}main{padding:24px 16px 60px}.menu-btn{display:inline-block;margin-bottom:10px;padding:8px 14px;border-radius:10px;border:1px solid var(--line);background:var(--card);font:inherit}aside nav.shut{display:none}}
@media print{aside{display:none}.wrap{display:block}main{padding:0;max-width:none}section{break-inside:avoid-page}}
CSS;

$js = <<<'JS'
(function(){
  var find=document.querySelector('[data-find]'),secs=[].slice.call(document.querySelectorAll('[data-doc]')),links=[].slice.call(document.querySelectorAll('[data-nav]')),none=document.querySelector('[data-none]');
  if(find){find.addEventListener('input',function(){var w=find.value.toLowerCase().trim().split(/\s+/).filter(Boolean),shown=0;
    secs.forEach(function(s){var ok=w.every(function(x){return s.getAttribute('data-words').indexOf(x)!==-1;});s.hidden=!ok;if(ok){shown++;}});
    links.forEach(function(a){var s=document.getElementById(a.getAttribute('data-nav'));a.hidden=s?s.hidden:false;});
    document.querySelectorAll('[data-grp]').forEach(function(g){g.hidden=!g.querySelector('a:not([hidden])');});
    if(none){none.style.display=shown?'none':'block';}});}
  function mark(){var y=window.scrollY+40,cur=null;secs.forEach(function(s){if(!s.hidden&&s.offsetTop<=y){cur=s.id;}});links.forEach(function(a){a.classList.toggle('on',a.getAttribute('data-nav')===cur);});}
  window.addEventListener('scroll',mark,{passive:true});mark();
  var b=document.querySelector('[data-menu]'),nav=document.querySelector('aside nav');
  if(b&&nav){b.addEventListener('click',function(){nav.classList.toggle('shut');});if(window.innerWidth<900){nav.classList.add('shut');}}
})();
JS;

$html = '<!doctype html>
<html lang="en-GB">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Money ' . $e($version) . ' · Manual</title>
<style>' . $css . '</style>
</head>
<body>
<div class="wrap">
<aside>
<div class="brand"><b>£</b><strong>Money<br><small style="font-weight:500;color:#6b7280">For The Self-Employed</small></strong></div>
<p class="ver">Version ' . $e($version) . ' · Manual</p>
<button class="menu-btn" type="button" data-menu>Contents</button>
<nav aria-label="Contents">
<input class="find" type="search" placeholder="Search the manual" aria-label="Search the manual" data-find>
' . $nav . '
</nav>
</aside>
<main>
<div class="hero"><h1>Money: The Manual</h1><p>How to install Money, set it up, and keep the records of a self-employed business, part by part. The same help is inside the app: Money &gt; Help Centre.</p></div>
<p class="none" data-none>Nothing in the manual matches. Try one word, such as "mileage" or "VAT".</p>
' . implode("\n", $sections) . '
<p class="foot">Money ' . $e($version) . ' manual. Tax rules change each year: check the figures in Settings &gt; Tax Rules against gov.uk every April.</p>
</main>
</div>
<script>' . $js . '</script>
</body>
</html>
';

$out = $root . '/documentation/index.html';
if (in_array('--check', $argv ?? [], true)) {
    $same = is_file($out) && (string) file_get_contents($out) === $html;
    echo $same ? "documentation/index.html is up to date.\n" : "documentation/index.html is out of date: run php tools/build-docs.php\n";
    exit($same ? 0 : 1);
}
is_dir(dirname($out)) || mkdir(dirname($out), 0775, true);
file_put_contents($out, $html);
echo 'Wrote documentation/index.html (' . count($sections) . " sections).\n";
