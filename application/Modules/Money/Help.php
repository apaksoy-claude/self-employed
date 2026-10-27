<?php
declare(strict_types=1);

namespace AP\Modules\Money;

/**
 * The Help Centre: one article for each part of Money, written for the person keeping the records. The same articles make the
 * getting-started tour and the printed manual (documentation/index.html, built with tools/build-docs.php), so all three always agree.
 * Article bodies are trusted HTML written here; "url" is the admin page an article is about.
 */
final class Help
{
    public const GROUPS = ['start' => 'Getting Started', 'money' => 'Money In and Out', 'tax' => 'Tax', 'tools' => 'Tools', 'settings' => 'Settings', 'help' => 'Questions and Answers'];

    /** The getting-started tour: the article each step explains, in order. */
    public const TOUR = ['welcome', 'dashboard', 'invoices', 'payments', 'expenses', 'bank-import', 'receipts', 'tax-estimate', 'reports', 'settings-money'];

    /** @return array<string,array{title:string,icon:string,group:string,url:string,keywords:string,intro:string,tour:string,body:string}> */
    public static function articles(): array
    {
        return [
            'welcome' => [
                'title' => 'Welcome To Money', 'icon' => 'fa-hand-sparkles', 'group' => 'start', 'url' => '', 'keywords' => 'start begin first login what is overview records only hmrc',
                'intro' => 'Money keeps the records of a self-employed business: what comes in, what goes out, and what that means for your tax.',
                'tour' => 'Money keeps your business records: invoices, payments, expenses, mileage and more. It works out your profit, an estimate of your tax and, if you are registered, your VAT. It never sends anything to HMRC: you file your returns yourself, with the figures it gives you.',
                'body' => <<<'HTML'
<p>Money is a record-keeping app for UK sole traders. Everything you type in stays in your own copy of the app, on your own website. Nobody else can see it.</p>
<h3>What Money Does</h3>
<ul>
<li>Keeps every <strong>invoice</strong>, <strong>payment</strong> and <strong>expense</strong>, with receipts attached.</li>
<li>Files each cost to the right box of the Self Assessment return, so your accountant (or you) can copy the totals across.</li>
<li>Works out your <strong>profit</strong>, an <strong>estimate of your Income Tax and National Insurance</strong>, and when to pay it.</li>
<li>Reads <strong>bank statements</strong> and <strong>receipt emails</strong>, so you type less.</li>
<li>Handles <strong>mileage</strong>, <strong>working from home</strong>, <strong>equipment</strong>, <strong>stock</strong>, <strong>VAT</strong> and the <strong>Construction Industry Scheme</strong> when they apply to you.</li>
</ul>
<h3>What Money Does Not Do</h3>
<ul>
<li>It does not send returns or payments to HMRC. You file your Self Assessment, VAT and CIS returns yourself, or your accountant does, using the figures here.</li>
<li>The tax figures are an estimate to plan with. They follow the rules of each tax year (see Settings &gt; Tax Rules), but your real bill depends on things Money cannot see, such as savings interest or student loans.</li>
</ul>
<h3>Where To Start</h3>
<ol class="steps">
<li><strong>Business Setup</strong> (Money &gt; Business Setup): pick your kind of work. Money adds categories, income streams and dashboard cards that suit it.</li>
<li><strong>Your details</strong> (Settings &gt; Money &gt; Your Business On Invoices): your business name, address and logo for invoices.</li>
<li><strong>Your tax</strong> (Settings &gt; Money &gt; Your Tax): where you pay Income Tax, and whether you use the cash basis or traditional accounting.</li>
<li>Start recording: send an invoice, add an expense, or load a bank statement.</li>
</ol>
<div class="tip">Want to look around first? Money &gt; Demo Data adds a year of made-up records, and one button takes them all away again.</div>
HTML,
            ],
            'business-setup' => [
                'title' => 'Business Setup', 'icon' => 'fa-wand-magic-sparkles', 'group' => 'start', 'url' => 'money/setup', 'keywords' => 'setup kind of work trade categories streams starter driver builder freelancer seller cis stock vehicle',
                'intro' => 'Ready-made categories, income streams, ways of paying and dashboard cards for your kind of work.',
                'tour' => '',
                'body' => <<<'HTML'
<p class="path">Money &gt; Business Setup</p>
<p>Business Setup gives you a starting set of records that suits what you do. It only ever adds: anything you already have with the same name is left exactly as it is, so you can run it again at any time, for example when you take on a second kind of work.</p>
<ol class="steps">
<li>Tick one or more kinds of work: Driver Or Courier, Tradesperson Or Builder, Freelancer Or Creative, Retail Or Online Seller, Consultant Or Professional, Hair and Beauty, Cleaning and Home Services, or Something Else.</li>
<li>Say how you work: whether you use a vehicle (this shows Vehicles and the Mileage Log), work from home, are paid under CIS, or sell goods from stock.</li>
<li>Look at <strong>What Will Be Added</strong> and untick anything you do not want.</li>
<li>Press <strong>Add To My Records</strong>.</li>
</ol>
<p>Every category is filed to a box of the Self Assessment return (for example "Car, van and travel expenses"). You can rename, refile or delete any of them later on Money &gt; Categories.</p>
HTML,
            ],
            'demo-data' => [
                'title' => 'Demo Data', 'icon' => 'fa-flask', 'group' => 'start', 'url' => 'money/demo', 'keywords' => 'demo sample example try trial delete clear',
                'intro' => 'A year of made-up records to try Money out, taken away again with one button.',
                'tour' => '',
                'body' => <<<'HTML'
<p class="path">Money &gt; Demo Data</p>
<p>Demo data fills every page with something to look at: clients and suppliers, invoices (some paid, some still open), payments, five to eight expenses a month, a vehicle with a mileage log, a laptop as equipment, and stock items if you sell goods.</p>
<ul>
<li>Made-up names end in "(Demo)" and demo invoices are numbered DEMO-0001 and on. Your own invoice numbers carry on as if they were not there.</li>
<li>While demo data is in use, the dashboard shows a banner with <strong>Delete All Demo Data</strong>.</li>
<li>Deleting takes away exactly what was added. If one of your own records uses a demo client, supplier, category or stream, that one is kept.</li>
</ul>
<div class="tip">Delete the demo data before you start keeping real records, so your totals are your own.</div>
HTML,
            ],
            'dashboard' => [
                'title' => 'The Dashboard', 'icon' => 'fa-gauge-high', 'group' => 'start', 'url' => '', 'keywords' => 'dashboard cards quarters months needs a look widgets arrange profit tax year',
                'intro' => 'Your year at a glance: money in, costs, profit, tax to put aside, and anything that needs a look.',
                'tour' => 'The dashboard shows the tax year at a glance: income, costs, profit and the tax to put aside, quarter by quarter and month by month. The Needs A Look card lists anything to tidy up, such as overdue invoices or expenses with no category. Press Customise to move, resize or add cards.',
                'body' => <<<'HTML'
<p class="path">Dashboard (the first page after signing in)</p>
<p>The dashboard shows one tax year at a time (6 April to 5 April). Change the year with the <strong>Tax year</strong> box at the top.</p>
<h3>The Cards</h3>
<ul>
<li><strong>The year</strong>: income, allowable costs, profit and the estimated tax, with where the year is heading at the current pace.</li>
<li><strong>Quarters</strong>: the four quarterly updates of Making Tax Digital. Mark a quarter as sent and add a note.</li>
<li><strong>Months</strong>: open a month to see every payment and cost behind it.</li>
<li><strong>Needs A Look</strong>: overdue invoices, expenses with no category or no receipt, VAT or CIS returns due, stock running low, and more.</li>
<li><strong>Invoices</strong>, <strong>latest payments and expenses</strong>, <strong>income streams</strong> and <strong>deadlines</strong>.</li>
</ul>
<h3>Arranging The Cards</h3>
<ol class="steps">
<li>Press <strong>Customise</strong>. Drag a card to move it, or use the arrows, and choose a width.</li>
<li>Press <strong>Add A Card</strong> to build your own: a number, a chart or a list, for any measure, period and category.</li>
<li>Changes save by themselves. <strong>Reset Layout</strong> puts the ready-made layout back.</li>
</ol>
HTML,
            ],
            'invoices' => [
                'title' => 'Invoices', 'icon' => 'fa-file-invoice', 'group' => 'money', 'url' => 'money/invoices', 'keywords' => 'invoice bill client items discount pdf email reminder pay link stripe paypal repeating recurring overdue part paid statement',
                'intro' => 'Create invoices, email them with a pay link, and see what is still owed.',
                'tour' => 'Invoices: create one, add the items, and email it with a private link where your client can pay by card, PayPal or bank transfer. Payments are counted against the invoice, so you always see what is still owed. Invoices can repeat by themselves.',
                'body' => <<<'HTML'
<p class="path">Money &gt; Invoices</p>
<h3>Making An Invoice</h3>
<ol class="steps">
<li>Press <strong>New Invoice</strong>. The number counts up by itself (change the start in Settings &gt; Money).</li>
<li>Choose the client (or type a new name), the bill date and the due date, and the income stream it belongs to.</li>
<li>Save, then press <strong>Add Item</strong> for each thing you charge for: a title, quantity, unit and rate. Drag items to reorder them.</li>
<li>Add a discount if you give one. With VAT or CIS, choose the rate on each line (see the VAT and CIS articles).</li>
</ol>
<h3>Sending It</h3>
<ul>
<li><strong>Actions &gt; Email Invoice</strong> sends it with a <strong>Pay Invoice</strong> button. The client's private page shows the invoice and lets them pay by card (Stripe), PayPal, or bank transfer with your details. Set these up in Settings &gt; Invoice Payments.</li>
<li><strong>View PDF</strong> and <strong>Save As PDF</strong> give a printable invoice. Its look (style, colour, font, wording) is in Settings &gt; Invoice PDF Look.</li>
<li><strong>Send Reminder</strong> appears once an invoice is overdue or was emailed and is still unpaid.</li>
</ul>
<h3>Statuses</h3>
<ul>
<li><span class="badge danger">Unpaid</span> nothing paid yet; <span class="badge dark">Overdue</span> unpaid after the due date; <span class="badge warning">Part Paid</span> some paid; <span class="badge success">Paid</span> paid in full.</li>
<li><strong>Draft</strong> invoices are not counted anywhere until you mark them as sent. <strong>Cancelled</strong> ones are kept for your records but never counted.</li>
</ul>
<h3>Repeating Invoices</h3>
<p>Tick <strong>Repeat this invoice automatically</strong> on the invoice form, choose how often and when to stop. Each new invoice gets the next number and a copy of the items, and can be emailed to the client by itself.</p>
<h3>Clients and Statements</h3>
<p>On Money &gt; Clients and Suppliers, each client has a <strong>statement</strong> of invoices and payments, which you can email, and a private <strong>portal link</strong> where they see all their invoices.</p>
HTML,
            ],
            'payments' => [
                'title' => 'Payments', 'icon' => 'fa-hand-holding-dollar', 'group' => 'money', 'url' => 'money/income', 'keywords' => 'payments income money in received takings payout invoice paid bank transfer card cash',
                'intro' => 'Every bit of money that comes in, whether it pays an invoice or not.',
                'tour' => 'Payments are the money that comes in: payments for invoices, card takings, platform payouts or cash. A payment can be counted against an invoice, and the invoice shows as paid.',
                'body' => <<<'HTML'
<p class="path">Money &gt; Payments</p>
<p>Record every payment you receive. Your income for tax is the total of your payments in the tax year (on the cash basis).</p>
<ol class="steps">
<li>Press <strong>New Payment</strong>.</li>
<li>If it pays an invoice, choose the invoice: the client, stream and amount are filled in for you, and the invoice's balance goes down.</li>
<li>Otherwise say where it came from (for example "Weekly platform payout"), the date, amount, stream and how it was paid.</li>
<li>Save. With VAT, CIS or Stock switched on, the extra boxes appear for a payment with no invoice.</li>
</ol>
<p>Payments made through the pay link (Stripe or PayPal) are added by themselves, once each.</p>
<p>Tick several payments in the list to change their stream or client together, or delete them.</p>
HTML,
            ],
            'expenses' => [
                'title' => 'Expenses', 'icon' => 'fa-receipt', 'group' => 'money', 'url' => 'money/expenses', 'keywords' => 'expenses costs receipts category business use percent allowable repeating vehicle supplier',
                'intro' => 'Your business costs, each filed to the right box of the tax return, with the receipt kept.',
                'tour' => 'Expenses are your business costs. Choose a category and each cost lands in the right box of your tax return. Attach the receipt, set the business share if something is partly personal, and let costs that come again repeat by themselves.',
                'body' => <<<'HTML'
<p class="path">Money &gt; Expenses</p>
<ol class="steps">
<li>Press <strong>New Expense</strong>, say what it was for, the date and the amount.</li>
<li>Choose a <strong>category</strong>. The category decides the box of the tax return, and whether the cost counts at all (pension payments and personal spending do not).</li>
<li>Set <strong>Business use (%)</strong> if it is partly personal, for example 60 for a phone used 60% for work.</li>
<li>Drop the receipt (photo or PDF) on the form.</li>
<li>Tick <strong>This cost comes again</strong> for rent, insurance or subscriptions: a copy is made each period until the date or number you choose.</li>
</ol>
<h3>Good To Know</h3>
<ul>
<li>Things you buy to keep (tools, a laptop, a van) go to a category filed to "Equipment and vehicles bought": they appear on Money &gt; Equipment and are claimed there.</li>
<li>Costs for a vehicle on the mileage rate are left out, because the rate covers them (parking and tolls still count).</li>
<li>The dashboard reminds you about expenses with no category, and receipts missing on costs over the amount you choose in Settings &gt; Money.</li>
<li>Tick several expenses to change their category, stream, vehicle, business share or VAT together.</li>
</ul>
HTML,
            ],
            'receipts' => [
                'title' => 'Receipt Inbox', 'icon' => 'fa-camera', 'group' => 'money', 'url' => 'money/receipts', 'keywords' => 'receipt inbox photo camera phone snap later',
                'intro' => 'Take photos of receipts on your phone now, and add the details later.',
                'tour' => 'The Receipt Inbox is for your phone: take a photo of a receipt the moment you get it. It waits there until you have time to add the amount and category.',
                'body' => <<<'HTML'
<p class="path">Money &gt; Receipt Inbox</p>
<ol class="steps">
<li>On your phone, open Receipt Inbox and press <strong>Take a photo</strong>. It saves as soon as the photo is taken.</li>
<li>Later, open each receipt and fill in the date, amount and category. It becomes an expense with the photo attached.</li>
</ol>
<div class="tip">Add the admin to your phone's home screen (Share &gt; Add to Home Screen) so the camera is one tap away.</div>
HTML,
            ],
            'bank-import' => [
                'title' => 'Bank Import', 'icon' => 'fa-building-columns', 'group' => 'money', 'url' => 'money/bank', 'keywords' => 'bank statement import csv ofx qfx layout columns rules review never twice take back',
                'intro' => 'Load a statement from your online banking and turn its lines into expenses and payments.',
                'tour' => 'Bank Import reads a statement downloaded from your online banking (CSV or OFX). You see every line before anything is added, rules pick the categories, and nothing is ever added twice, so overlapping statements are safe.',
                'body' => <<<'HTML'
<p class="path">Money &gt; Bank Import</p>
<h3>Loading A Statement</h3>
<ol class="steps">
<li>In your online banking, download a statement as <strong>CSV</strong> (sometimes called "spreadsheet" or "Excel") or <strong>OFX</strong>/<strong>QFX</strong> ("Money" or "Quicken").</li>
<li>Drop it on <strong>Load A Statement</strong> and say which account it is (use the same name each time).</li>
<li>For a CSV from a new bank, Money finds the date, description and amount by their names. If it cannot, it asks you once which column is which and how dates are written; save the layout and next time it is automatic.</li>
<li>Review every line: money out becomes an expense, money in a payment. Change titles and categories, or set the category of all ticked lines at once.</li>
<li>Press <strong>Add The Ticked Lines</strong>.</li>
</ol>
<h3>Never Twice</h3>
<ul>
<li>Each line gets a fingerprint. Lines added from an earlier statement show as <strong>Already Added</strong> and can never be added again.</li>
<li>A line with the same amount as something you typed in by hand, a few days either side, shows as <strong>Check</strong> and is left unticked. Change how many days in Settings &gt; Money &gt; Bank Import.</li>
<li>Money coming in that matches what is left on an open invoice is counted against that invoice.</li>
<li>Made a mistake? <strong>Recent Imports &gt; Take Back</strong> moves everything one import added to the bin.</li>
</ul>
<h3>Rules</h3>
<p>Add a rule for each regular payee: when a description contains your words (for example "SHELL"), the line gets that category, stream and title. Drag rules into order: the first that matches wins.</p>
HTML,
            ],
            'email-import' => [
                'title' => 'Email Import', 'icon' => 'fa-envelope-circle-check', 'group' => 'money', 'url' => 'money/email-import', 'keywords' => 'email import gmail yahoo icloud outlook imap app password receipt rules pdf supplier invoice',
                'intro' => 'Receipt and invoice emails become expenses by themselves, with the email or PDF kept.',
                'tour' => '',
                'body' => <<<'HTML'
<p class="path">Settings &gt; Email Import, then Money &gt; Email Import</p>
<h3>Connecting Your Mailbox</h3>
<ol class="steps">
<li>Open Settings &gt; Email Import and press the button for your provider (Gmail, Yahoo, iCloud, AOL, Zoho, Fastmail, IONOS), or type your provider's IMAP server, port and security.</li>
<li>Make an <strong>app password</strong> in your email account's security settings (Gmail: Google Account &gt; Security &gt; 2-Step Verification &gt; App passwords). Type your email address and that app password.</li>
<li>Press <strong>Test Connection</strong> on Money &gt; Email Import.</li>
<li>Switch on <strong>Check The Mailbox By Itself Every Hour</strong> if you want it to run alone.</li>
</ol>
<div class="tip">Outlook, Hotmail and Microsoft 365 no longer allow other apps to sign in with a password. Set a rule in Outlook that forwards receipts to another mailbox, and connect that one.</div>
<h3>Rules</h3>
<ul>
<li>A rule says which emails to take (sender, words in the subject, a PDF attached), where the amount is (after words like "Total"), and what the expense should be: title, category, supplier and how it was paid.</li>
<li>Press <strong>Add Example Rules</strong> for four starters: parking receipts, supplier invoices with a PDF, software subscriptions and fuel receipts.</li>
<li>Only emails that match a rule are touched. Matching emails can be moved to the bin after they are added.</li>
<li>Emails no rule took, and ones that need a look, wait in <strong>Emails Without A Rule</strong>; <strong>Create Rule</strong> turns one into a rule with its details filled in.</li>
</ul>
HTML,
            ],
            'clients' => [
                'title' => 'Clients and Suppliers', 'icon' => 'fa-address-book', 'group' => 'money', 'url' => 'money/contacts', 'keywords' => 'clients suppliers contacts address statement portal archive',
                'intro' => 'The people and businesses you work with, their statements and their private portal.',
                'tour' => '',
                'body' => <<<'HTML'
<p class="path">Money &gt; Clients and Suppliers</p>
<ul>
<li>Add a contact with its name, kind (client or supplier), email, phone and address. Invoices use the client's address.</li>
<li><strong>Statement</strong> lists a client's invoices and payments with what is still owed. Email one client's statement, or everyone who owes money at once.</li>
<li><strong>Portal</strong> gives the client a private link to see all their sent invoices and pay them.</li>
<li>Switch a contact off to archive it: it is kept on old records but no longer offered on new ones.</li>
<li>With CIS on, mark a client as a contractor (with the rate they take off) or a supplier as a subcontractor you pay.</li>
</ul>
HTML,
            ],
            'categories' => [
                'title' => 'Categories and Income Streams', 'icon' => 'fa-tags', 'group' => 'money', 'url' => 'money/categories', 'keywords' => 'categories streams boxes hmrc self assessment allowable reorder',
                'intro' => 'Categories decide where each cost goes on your tax return; streams split your income by kind of work.',
                'tour' => '',
                'body' => <<<'HTML'
<p class="path">Money &gt; Categories, and Money &gt; Income Streams</p>
<p>Each <strong>expense category</strong> is filed to one box of the Self Assessment return, such as "Car, van and travel expenses" or "Phone, fax, stationery and other office costs". Two boxes are never costs: pension payments and personal spending. "Equipment and vehicles bought" goes to Money &gt; Equipment.</p>
<p><strong>Income streams</strong> (for example "Client Projects" and "Royalties") show where your money comes from on the dashboard and in reports. Costs can belong to a stream too, so each stream shows its own profit.</p>
<p>Drag categories and streams into the order you want them offered.</p>
HTML,
            ],
            'equipment' => [
                'title' => 'Equipment', 'icon' => 'fa-toolbox', 'group' => 'tax', 'url' => 'money/equipment', 'keywords' => 'equipment capital allowances aia annual investment allowance first year pool writing down cash basis car van sold',
                'intro' => 'Things you buy to keep, and what each tax year can claim for them.',
                'tour' => '',
                'body' => <<<'HTML'
<p class="path">Money &gt; Equipment</p>
<p>Anything filed to "Equipment and vehicles bought" appears here by itself. You can also add equipment by hand. How it is claimed depends on <strong>How Your Accounts Are Kept</strong> (Settings &gt; Money &gt; Your Tax):</p>
<ul>
<li><strong>Cash basis</strong>: equipment is claimed in full, by its business share, in the year you buy it. Cars still get capital allowances.</li>
<li><strong>Traditional accounting</strong>: the Annual Investment Allowance covers most purchases in full; above it, new main-rate equipment gets the 40% first year allowance, and the rest goes into the main or special rate pool and is claimed a share each year.</li>
</ul>
<p>Cars are pooled by their CO2: new zero-emission cars get 100% in the first year, up to the limit the main pool, above it the special rate pool. A car used privately gets its own pool, claimed by the business share.</p>
<p>When you sell or give away something, enter the date and what you got: the sale comes off the pool (or counts as income on the cash basis).</p>
<div class="tip">A car or van that has had capital allowances can no longer use the mileage rate. Money stops it for you.</div>
HTML,
            ],
            'vehicles' => [
                'title' => 'Vehicles and Mileage', 'icon' => 'fa-car-side', 'group' => 'tax', 'url' => 'money/vehicles', 'keywords' => 'vehicles mileage rate pence per mile actual costs trips log van car motorcycle',
                'intro' => 'Claim the mileage rate or the actual running costs, and keep a log of business trips.',
                'tour' => '',
                'body' => <<<'HTML'
<p class="path">Money &gt; Vehicles, and Money &gt; Mileage Log</p>
<ol class="steps">
<li>Add each vehicle you use for work and choose <strong>Mileage rate</strong> or <strong>Actual costs</strong>. Once you choose the mileage rate for a vehicle, HMRC expects you to keep it for as long as you use that vehicle.</li>
<li>Log business trips on the Mileage Log: date, miles, from, to and why.</li>
<li>Each vehicle's page compares what the mileage rate gives with its actual costs.</li>
</ol>
<p>The mileage rates (per mile for the first 10,000 business miles and after) are in Settings &gt; Tax Rules for each year. On the mileage rate, fuel, insurance and repairs are covered by the rate and left out of costs; parking and tolls still count.</p>
<p>No vehicle for work? Switch off <strong>Vehicles and Mileage</strong> in Settings &gt; Money &gt; Features: the menu items go and nothing is deleted.</p>
HTML,
            ],
            'home' => [
                'title' => 'Working From Home', 'icon' => 'fa-house-laptop', 'group' => 'tax', 'url' => 'settings/money', 'keywords' => 'working from home use of home flat rate hours heating electricity broadband council tax',
                'intro' => 'Claim a share of your household bills, or HMRC\'s flat rate by the hours you work at home.',
                'tour' => '',
                'body' => <<<'HTML'
<p class="path">Settings &gt; Money &gt; Working From Home</p>
<ul>
<li><strong>Actual costs</strong>: file household bills to categories in the "Use of home" box and set the business share on each.</li>
<li><strong>Flat rate</strong>: HMRC's simplified expenses give a set amount a month by the hours you work at home (from 25 hours a month). Set your hours on the Tax Estimate page for each tax year. Bills filed to "Use of home" are then left out, as the flat rate covers them.</li>
</ul>
HTML,
            ],
            'stock' => [
                'title' => 'Stock', 'icon' => 'fa-boxes-stacked', 'group' => 'tools', 'url' => 'money/stock', 'keywords' => 'stock inventory items low stock count stocktake closing stock cost of goods seller',
                'intro' => 'For goods you buy to sell: what is left, what it cost, and the stock count.',
                'tour' => '',
                'body' => <<<'HTML'
<p class="path">Settings &gt; Money &gt; Features &gt; Stock, then Money &gt; Stock</p>
<ol class="steps">
<li>Add each item with its sell price, how many you have now and what each cost.</li>
<li>When you buy more, add <strong>Stock Bought</strong> lines to the expense: each item comes in at its cost each, or a share of the amount by quantity.</li>
<li>Invoice lines can come <strong>From Stock</strong> (picking the item fills in its name and price), and payments with no invoice have <strong>Sold From Stock</strong> lines.</li>
<li>Use <strong>Add Or Take Out</strong> for damaged, used or returned items, and <strong>Stock Count</strong> to put the records right from what is on the shelf.</li>
</ol>
<p>Costs follow the average of what you paid. With <strong>traditional accounting</strong>, count your stock on the last day of the tax year: the change in stock value over the year changes your cost of goods. On the <strong>cash basis</strong> goods count when you pay for them, so stock is for your own use.</p>
HTML,
            ],
            'tax-estimate' => [
                'title' => 'Tax Estimate', 'icon' => 'fa-landmark', 'group' => 'tax', 'url' => 'money/tax', 'keywords' => 'tax estimate income tax national insurance class 4 class 2 bands scotland trading allowance payments on account january july mtd making tax digital',
                'intro' => 'Income Tax and National Insurance worked out from your profit, and when to pay them.',
                'tour' => 'The Tax Estimate works out Income Tax and National Insurance from your profit, band by band, with when to pay: the January balance and payments on account. Add other income, such as a job, so the bands are right.',
                'body' => <<<'HTML'
<p class="path">Money &gt; Tax Estimate</p>
<ul>
<li><strong>Profit</strong>: your income less allowable costs, or less the £1,000 trading allowance if that leaves a lower profit (choose in Settings &gt; Money &gt; Your Tax).</li>
<li><strong>Income Tax</strong> band by band, with the rates of the tax year, for England, Wales and Northern Ireland or Scotland.</li>
<li><strong>National Insurance</strong>: Class 4 on profits above the lower limit. Class 2 is no longer charged, but you can add voluntary Class 2 when profits are below the small profits threshold, to keep the year counting for your State Pension.</li>
<li><strong>When To Pay</strong>: the balance on 31 January after the tax year, and payments on account (half of the bill each, in January and July) when HMRC asks for them.</li>
<li><strong>Making Tax Digital</strong>: whether it is likely to apply, from your income two tax years before.</li>
</ul>
<h3>Your Other Figures</h3>
<p>Add wages or a pension and the tax already taken off them, payments on account you have made, and your hours at home (flat rate). They save in the background and the estimate updates straight away.</p>
<div class="tip">This is a planning figure. Your Self Assessment return is the real thing; check it with your accountant.</div>
HTML,
            ],
            'vat' => [
                'title' => 'VAT', 'icon' => 'fa-percent', 'group' => 'tax', 'url' => 'money/vat', 'keywords' => 'vat registration return nine boxes flat rate scheme cash accounting periods threshold reverse charge',
                'intro' => 'VAT on invoices and costs, the nine boxes of each return, and a watch on the registration threshold.',
                'tour' => '',
                'body' => <<<'HTML'
<p class="path">Settings &gt; VAT, then Money &gt; VAT</p>
<h3>Not Registered</h3>
<p>Money &gt; VAT watches your takings over the last 12 months against the registration threshold, and the dashboard warns you at the point you choose (80% to start). You must register within 30 days of the end of a month in which your last 12 months go over the threshold, or straight away if you expect the next 30 days alone to go over it.</p>
<h3>Registered</h3>
<ol class="steps">
<li>In Settings &gt; VAT, tick <strong>I Am VAT Registered</strong>, add your VAT number and the date you registered, and choose your VAT periods.</li>
<li>Choose your scheme: <strong>Standard</strong> (pay the VAT you charge, less the VAT on your costs) or <strong>Flat Rate</strong> (a set percentage of your takings with VAT, no VAT back on costs except equipment of £2,000 or more). Tick cash accounting if you use it.</li>
<li>Invoice lines get a VAT rate; expenses and payments with no invoice get a rate and amount (left empty, the amount is worked out from the total).</li>
<li>Money &gt; VAT shows each period with its nine boxes, what to pay or reclaim, the due date, and every sale and cost behind the figures. Type the boxes into your VAT software, then press <strong>Mark As Filed</strong>.</li>
</ol>
<p>Profit leaves VAT out everywhere once you are registered. If records in a filed period change, the page shows what changed.</p>
<div class="tip">Construction work under CIS between VAT-registered businesses usually uses the domestic reverse charge: choose "Reverse Charge" on the line and the invoice says the customer accounts for the VAT.</div>
HTML,
            ],
            'cis' => [
                'title' => 'CIS For Builders', 'icon' => 'fa-helmet-safety', 'group' => 'tax', 'url' => 'money/cis', 'keywords' => 'cis construction industry scheme subcontractor contractor deduction 20% 30% gross payment statement monthly return labour materials',
                'intro' => 'The Construction Industry Scheme, for subcontractors who are paid under it and contractors who pay others.',
                'tour' => '',
                'body' => <<<'HTML'
<p class="path">Settings &gt; CIS, then Money &gt; CIS</p>
<h3>Paid Under CIS (Subcontractor)</h3>
<ol class="steps">
<li>Tick <strong>Contractors Pay Me Under CIS</strong> and choose the rate they take off: 20% (registered), 30% (not registered) or nothing (gross payment status).</li>
<li>Mark the client as a contractor on Clients and Suppliers. New invoices to them pick the rate.</li>
<li>On each invoice line, say <strong>Labour</strong> or <strong>Materials</strong>. The deduction is on labour only, never materials or VAT. The invoice shows it and asks for the total less the deduction.</li>
<li>For payments with no invoice, type the CIS taken off and the materials from the contractor's statement.</li>
<li>On Money &gt; CIS, tick each contractor's monthly statement when it arrives. Money checks the rate taken.</li>
</ol>
<p>Your income is the full amount before the deduction, and the deductions come off your tax on the Tax Estimate as tax already paid. If more was taken than you owe, HMRC pays it back after your return.</p>
<h3>Paying Subcontractors (Contractor)</h3>
<ol class="steps">
<li>Tick <strong>I Pay Subcontractors Under CIS</strong> and add your employer's reference.</li>
<li>Mark each subcontractor on Clients and Suppliers with the rate and their UTR (and verification number if they are taxed at 30%).</li>
<li>On the expense paying them, type what you paid, the CIS you took off and the materials. The cost is what you paid plus the deduction.</li>
<li>Money &gt; CIS shows each tax month (6th to 5th): print each subcontractor's payment and deduction statement, pay HMRC what you took off by the 22nd, file your monthly return by the 19th, and mark it as filed.</li>
</ol>
HTML,
            ],
            'reports' => [
                'title' => 'Reports', 'icon' => 'fa-chart-column', 'group' => 'tools', 'url' => 'money/reports', 'keywords' => 'reports csv download accountant pack zip summary scheduled email',
                'intro' => 'Totals for the tax year, downloads for your accountant, and reports emailed on a schedule.',
                'tour' => 'Reports show the year month by month and box by box, who owes you money and how the year compares with the last. Download any list as a CSV, or the whole Accountant Pack as one zip, and have reports emailed on a schedule.',
                'body' => <<<'HTML'
<p class="path">Money &gt; Reports, and Money &gt; Scheduled Reports</p>
<ul>
<li>Month by month income, costs and profit; costs by tax box and category; income by client; money still owed by age; this year against last.</li>
<li><strong>Downloads</strong>: payments, expenses, invoices, mileage, income by client and money owed, as CSV files that open in any spreadsheet. With VAT or CIS on, they include those columns.</li>
<li><strong>Accountant Pack</strong>: every download plus a summary page, in one zip, to email or hand over.</li>
<li><strong>Scheduled Reports</strong>: choose a report, how often and who receives it, as a PDF or CSV.</li>
</ul>
HTML,
            ],
            'custom-fields' => [
                'title' => 'Custom Fields', 'icon' => 'fa-sliders', 'group' => 'tools', 'url' => 'money/custom-fields', 'keywords' => 'custom fields extra boxes forms job number',
                'intro' => 'Add your own boxes to invoices, payments, expenses and contacts.',
                'tour' => '',
                'body' => <<<'HTML'
<p class="path">Money &gt; Custom Fields</p>
<p>Add a box such as "Job number" or "Purchase order" to any form: text, a number, a date, a yes or no, or a choice from a list. Choose whether it is needed, and whether it shows in lists and reports.</p>
HTML,
            ],
            'settings-money' => [
                'title' => 'Settings', 'icon' => 'fa-gear', 'group' => 'settings', 'url' => 'settings/money', 'keywords' => 'settings money business details tax rules features vat cis email import invoice payments pdf look',
                'intro' => 'Where everything is set up: your business, your tax, features, and each part of Money.',
                'tour' => 'Settings is where Money is set up: your business details for invoices, how your tax works, which features you use, and the rates and rules of each tax year. Every page of Money has its settings here, and the search at the top finds any of them.',
                'body' => <<<'HTML'
<p class="path">Settings</p>
<ul>
<li><strong>Money</strong>: your business on invoices, features (vehicles, stock), your tax (where you pay, cash or traditional, trading allowance, Class 2), working from home, quarters, the dashboard and bank import.</li>
<li><strong>Tax Rules</strong>: the rates and limits of each tax year. Years HMRC has published are built in; change any figure and it is kept for that year only. A new year uses the year before until you copy it in and check it.</li>
<li><strong>VAT</strong>, <strong>CIS</strong> and <strong>Email Import</strong>: see their own articles.</li>
<li><strong>Invoice Payments</strong>: Stripe, PayPal and bank details, emails and reminders.</li>
<li><strong>Invoice PDF Look</strong>: style, colour, font, wording and which parts show.</li>
</ul>
<div class="tip">Type any word in the search at the top of the admin (for example "mileage" or "VAT number") to jump to the right page or setting.</div>
HTML,
            ],
            'users' => [
                'title' => 'Users and Access', 'icon' => 'fa-users', 'group' => 'settings', 'url' => 'users', 'keywords' => 'users roles access permissions bookkeeper accountant staff password two step',
                'intro' => 'Let a bookkeeper or accountant in, with only the access they need.',
                'tour' => '',
                'body' => <<<'HTML'
<p class="path">Users &gt; Staff, and Users &gt; Roles and access</p>
<ul>
<li>Two switches decide what someone can do in Money: <strong>See income, expenses, invoices and receipts</strong>, and <strong>Add, change and delete</strong> them. Settings need their own permission.</li>
<li>Give an accountant a role with view only, or a bookkeeper view and edit.</li>
<li>Switch on two-step sign-in under your profile for extra safety.</li>
</ul>
HTML,
            ],
            'backups' => [
                'title' => 'Updates and Backups', 'icon' => 'fa-cloud-arrow-down', 'group' => 'settings', 'url' => 'system/updates', 'keywords' => 'update upgrade backup restore finish update version',
                'intro' => 'Keep Money up to date and your records safe.',
                'tour' => '',
                'body' => <<<'HTML'
<p class="path">System &gt; Updates</p>
<ol class="steps">
<li>Upload the update package on System &gt; Updates (or copy its files over your site).</li>
<li>When the yellow banner asks, press <strong>Finish update</strong>. It updates the database and keeps every record.</li>
</ol>
<p>Take a backup of the database and the <code>application/storage</code> folder (receipts and files) regularly, from your hosting control panel. Keep one copy away from your website.</p>
HTML,
            ],
            'faq' => [
                'title' => 'Questions and Answers', 'icon' => 'fa-circle-question', 'group' => 'help', 'url' => '', 'keywords' => 'faq questions problems help trouble why not working',
                'intro' => 'Answers to the questions people ask most.',
                'tour' => '',
                'body' => <<<'HTML'
<h3>Does Money send anything to HMRC?</h3>
<p>No. Money keeps your records and works out the figures. You file your returns yourself, or your accountant does.</p>
<h3>Why is my profit different from my bank balance?</h3>
<p>Profit counts only business income and allowable costs. Money you put in or take out for yourself, pension payments, personal spending and equipment (claimed separately) are not part of it.</p>
<h3>An invoice shows as overdue but it was paid.</h3>
<p>Add the payment on the invoice (Add Payment), or choose the invoice when you record the payment. A bank import can do it for you when the amount matches.</p>
<h3>The bank import added a line I had already typed in.</h3>
<p>Lines that look like records added by hand are marked Check and left unticked; if one was added anyway, delete either copy. Take Back on Recent Imports removes a whole import.</p>
<h3>Email Import says the sign-in was refused.</h3>
<p>Most providers need an app password, not your normal password, and some need IMAP switched on in their settings. Outlook, Hotmail and Microsoft 365 do not allow password sign-in at all: forward receipts to another mailbox instead.</p>
<h3>Next year's tax rates are not there.</h3>
<p>On Settings &gt; Tax Rules press <strong>Add</strong> for the new year: it copies the year before for you to check against gov.uk, and says so until you save it.</p>
<h3>Can I use Money for two businesses?</h3>
<p>Income streams split income and costs within one business. Two separate businesses are best kept in two copies of Money.</p>
HTML,
            ],
        ];
    }

    /** Articles in their groups, for the menu down the left. @return array<string,array<string,array<string,string>>> */
    public static function grouped(): array
    {
        $out = [];
        foreach (self::articles() as $k => $a) {
            $out[$a['group']][$k] = $a;
        }
        return array_replace(array_fill_keys(array_keys(self::GROUPS), []), $out);
    }

    /** Articles whose title, words or text contain every word typed. @return list<array{key:string,title:string,intro:string,icon:string,score:int}> */
    public static function search(string $term, int $limit = 6): array
    {
        $words = array_values(array_filter(preg_split('/\s+/', mb_strtolower(trim($term))) ?: []));
        if (!$words) {
            return [];
        }
        $out = [];
        foreach (self::articles() as $k => $a) {
            $title = mb_strtolower($a['title']);
            $hay = $title . ' ' . mb_strtolower($a['keywords'] . ' ' . $a['intro'] . ' ' . strip_tags($a['body']));
            $score = 0;
            foreach ($words as $w) {
                if (!str_contains($hay, $w)) {
                    continue 2;
                }
                $score += str_contains($title, $w) ? 3 : (str_contains(mb_strtolower($a['keywords']), $w) ? 2 : 1);
            }
            $out[] = ['key' => $k, 'title' => $a['title'], 'intro' => $a['intro'], 'icon' => $a['icon'], 'score' => $score];
        }
        usort($out, static fn($x, $y) => $y['score'] <=> $x['score']);
        return array_slice($out, 0, $limit);
    }

    /** The steps of the getting-started tour. @return list<array{key:string,title:string,icon:string,text:string,url:string}> */
    public static function tour(): array
    {
        $all = self::articles();
        $out = [];
        foreach (self::TOUR as $k) {
            if (isset($all[$k])) {
                $out[] = ['key' => $k, 'title' => $all[$k]['title'], 'icon' => $all[$k]['icon'], 'text' => $all[$k]['tour'] ?: $all[$k]['intro'], 'url' => $all[$k]['url']];
            }
        }
        return $out;
    }

    /** Whether this person has finished (or skipped) the tour. */
    public static function tourDone(int $userId): bool
    {
        return in_array($userId, array_map('intval', (array) json_decode((string) setting('money.tour_done', '[]'), true)), true);
    }

    public static function markTour(int $userId, bool $done): void
    {
        $list = array_map('intval', (array) json_decode((string) setting('money.tour_done', '[]'), true));
        $list = $done ? array_values(array_unique([...$list, $userId])) : array_values(array_diff($list, [$userId]));
        app(\AP\Core\Settings::class)->set('money.tour_done', json_encode($list), $userId);
    }
}
