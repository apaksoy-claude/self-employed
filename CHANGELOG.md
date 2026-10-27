# Changelog

## 1.36.5 - Report switch colours
Update package: install from System > Updates. No database changes.
- Scheduled Reports: the on/off switch now really is green when on and red when off (the site's own switch style was overriding it).

## 1.36.4 - Green and red switch, no doubled page names
Update package: install from System > Updates. No database changes.
- Scheduled Reports: the on/off switch is green when on and red when off.
- Scheduled Reports, Custom Fields and Email Import no longer repeat the page name under the title; the explanation stays.

## 1.36.3 - Tidier report cards
Update package: install from System > Updates. No database changes.
- Scheduled Reports: a switch at the top right of each card turns the report on or off (replaces the On label and the Pause button).
- The bottom row now holds Send Now and Preview on the left, with the change and delete icons together on the right.

## 1.36.2 - Report cards are a quarter of the page wide
Update package: install from System > Updates. No database changes.
- Scheduled Reports: each report is now a card a quarter of the page wide (four across on a large screen, two on a medium one, one on a phone) instead of one card across the whole page.

## 1.36.1 - Tidier Scheduled Reports form
Update package: install from System > Updates. No database changes.
- The New Report form is now split into clear sections (What To Report, Narrow It Down, Columns To Show, How It Is Laid Out, When To Send It, Who Gets It, Options), each with a short explanation.
- Fields line up in two even columns, the extra fields for weekly, monthly and every-few-days schedules sit in the same grid, and the duplicate heading is gone.

## 1.36.0 - Scheduled Reports
Update package: install from System > Updates, then press "Finish update" (adds two small tables).
- New page: Money > Scheduled Reports. Make as many reports as you like; each one is emailed as a PDF, with an optional spreadsheet (CSV), on its own schedule.
- Report on expenses, payments received, invoices, mileage, profit by month, or unpaid invoices. Narrow by category, client or supplier, income stream or vehicle, choose the columns, and add totals by category, supplier, client, month and more.
- Choose the dates: this or last week, this or last month, last quarter, last number of days, tax year so far, last tax year, last 12 months.
- Choose when: every day, every week on a weekday, every month on a day (or the last day), or every few days or weeks, at a time you pick.
- Send to as many email addresses as you like. Send Now, Preview PDF, pause and switch on, and a history of everything sent.
- "Skip it when there is nothing to report" avoids empty emails.
- Found with the top-bar search ("reports email weekly monthly pdf").

## 1.34.3 - Remove attachments without leaving the window
Update package: install from System > Updates. No database changes.
- Editing an expense: the bin icon beside an attachment removes it in the background. The edit window stays open, nothing reloads, and the row simply disappears.

## 1.34.2 - Remove an attachment with one click
Update package: install from System > Updates. No database changes.
- Editing an expense: the bin icon beside an attachment now removes it straight away. The confirmation pop-up, which opened behind the edit window, is gone.

## 1.34.1 - Total tax due shows on the tax page
Update package: install from System > Updates. No database changes.
- Tax Estimate: the fourth box was empty because its white text sat on a pale card. It now has a deep slate background and shows Total Tax Due (income tax plus Class 4), with the share of profit and the amount to keep from every £100 coming in.

## 1.34.0 - Card styles
Update package: install from System > Updates. No database changes.
- Ten new looks for the quarter and month cards on the dashboard: Quiet Line, Slate Header, Soft Panel, Profit First, Dark Footer, Sage Bands, Ledger, Slate Card, Edge Mark and Duo Tone.
- Settings > Card Style shows every look as a preview with a quarter and a month, so you can see what you are choosing. Dark Footer is the starting style.
- Each card can have its own style too: on the dashboard choose Customise, then the paintbrush on the Quarterly Updates or Recent Months card. The picker shows every design, with "Same As Everywhere" first.
- Card Style can be found with the admin top-bar search.

## 1.33.2 - New look for quarter and month cards
Update package: install from System > Updates. No database changes.
- Dashboard: the quarter and month cards are redesigned. A tinted title band with a blue top edge, and a solid deep blue footer that carries the Profit total and runs to the very bottom of the card.

## 1.33.1 - Light bands on the dashboard cards
Update package: install from System > Updates. No database changes.
- Dashboard: the quarter and month cards now have a light band behind the title and behind the Profit total at the bottom, so they are easier to scan.

## 1.33.0 - New drag and drop
Update package: install from System > Updates. No database changes.
- Drag and drop is rebuilt. When you pick something up, a copy follows your pointer and a dashed blue box shows where it will land, while the other items glide out of the way.
- The page scrolls by itself, quickly, as you hold an item near the top or bottom, and the mouse wheel works while you drag.
- It works the same for arranging settings pages, email rules, sortable lists, repeating rows and the pinned menu, and also on touch screens. Press Esc to cancel a move.
- Arrow keys on a handle still move items without the mouse.

## 1.32.10 - Faster scrolling and a dashed drop box
Update package: install from System > Updates. No database changes.
- Dragging: the page scrolls much faster near the top and bottom edges, and the mouse wheel scrolls while you hold an item.
- The item you are dragging now shows a dashed blue box, so you can see where it will land.

## 1.32.9 - Scroll while dragging
Update package: install from System > Updates. No database changes.
- Dragging cards and settings (and any other drag and drop list) now scrolls the page by itself when you hold the item near the top or bottom edge, so you can move things a long way.

## 1.32.8 - Deleted expenses can be read from email again
Update package: install from System > Updates. No database changes.
- Email Import: if an expense made from an email was later deleted, the email is looked at again on the next check, so it is no longer stuck as "already added".

## 1.32.7 - New rules look at old emails by themselves
Update package: install from System > Updates. No database changes.
- Email Import: after you add or change a rule, the next check looks again at emails that were left out before, so a new rule finds emails already in your inbox without ticking anything.
- Emails Without A Rule now always say why: how close it came to a rule, or that no rule's sender or subject fitted.

## 1.32.6 - Email rules read words more loosely
Update package: install from System > Updates. No database changes.
- Email Import: the words in a rule now match even when the email has them on two lines, with odd spaces, in different capitals or with curly dashes, as happens in HTML emails such as Heathrow's.

## 1.32.5 - Bulk changes work again
Update package: install from System > Updates. No database changes.
- Moving ticked rows to a category, stream, vehicle or business share no longer says "Action not available". The drop-down was losing its choice when the list also had a Delete button.

## 1.32.4 - Emails reach the bin, tidy rule form
Update package: install from System > Updates. No database changes.
- Email Import: when an email is added, it is now moved to the bin even if your bin folder name is not exactly right; the mailbox's own Trash folder is found and used.
- The Save buttons in the rule form no longer cover the fields underneath.

## 1.32.3 - Better PDF reading
Update package: install from System > Updates. No database changes.
- Email Import: invoice PDFs made by browsers and invoicing tools (such as Otto's) are now read even when the server has no PDF reader installed, so words like "Rent To Buy", the invoice number and the Balance Due amount can be found.

## 1.32.2 - Why an email was left out
Update package: install from System > Updates. No database changes.
- Email Import: when an email came close to a rule but was left out, the "Emails Without A Rule" list now says why, for example that the words in the rule were not found in the email or its PDF, or that the PDF text could not be read on the server.

## 1.32.1 - Tidier email rule form
Update package: install from System > Updates. No database changes.
- Email rule form: the switches sit in one tidy panel, each on its own line. Cost Category, Supplier, Income Stream and Paid With are now drop-down lists like "Read The Amount From". To use a new category or supplier, type its name in the "Or Make A New..." box beside the list and it is made for you.

## 1.32.0 - Tidier Email Import
Update package: install from System > Updates, then press "Finish update" (adds one small column).
- Email Import: the rules list is redesigned. Each rule is a tidy card with a drag handle, an On/Off label, small chips showing what it looks for (sender, subject, words, attachment), a line showing what it makes (title, category, supplier), and neat icon buttons for Fetch Now, Edit, switching on or off, and removing.
- Emails Without A Rule and What Was Added can be tidied: tick rows and Remove From List, or press the cross on one row. Removed rows leave the list but are still remembered, so an email is never added twice.

## 1.31.0 - Clearer status labels, bank and Uber imports removed
Update package: install from System > Updates. No database changes.
- Status labels: Unpaid is red, Part Paid is amber, Paid is green and Overdue is dark red. Labels with a dark background now always have white text, including the filter buttons on the notifications page and the Repeats label.
- Bank Statements and Uber Statements are removed, with their pages, menu entries, settings and tests. Payments, expenses and invoices already made from them stay.

## 1.30.0 - One generic email rule builder
Update package: install from System > Updates. No database changes.
- Email Import is now one generic rule builder. The built-in Heathrow and Otto checks and their settings are gone. On Money > Email Import, Add A Rule to say which emails to pick (sender, subject, words in the email or PDF, words to leave out, and whether it must have a PDF, have no attachment, or either), how to read the amount (from the email, the PDF or both, after words you choose, or a fixed amount when none is shown), and what expense to make: title and notes with {subject}, {sender}, {ref}, {date} and {amount}, cost category (made if new), supplier (made if new), income stream, paid with, business share, whether to keep the email and PDFs on the expense, and whether to move the email to the bin.
- Rules can be switched on or off, edited, removed and dragged into order (the first rule that fits wins). Each rule has its own Fetch Now.
- "Add Example Rules" puts in four ready-made rules (Heathrow drop-off fee and three Otto invoice rules) that you can change. Rules made before this update keep working.
- Emails with no rule are listed with "Create Rule From This Email", which opens the same form filled in from the email.

## 1.29.0 - Lists remember your filters
Update package: install from System > Updates. No database changes.
- Lists remember your filters and search: Expenses, Income, Invoices, Miles and Contacts open the way you left them. A Clear Filters button (shown whenever filters are on) forgets them, and removing the last filter chip does the same. Settings > Money > Lists has a switch to turn remembering off.

## 1.28.0 - Repeats can stop on a date or after a number of copies
Update package: install from System > Updates, then press "Finish update" (adds three small columns).
- Repeating costs: choose when they stop with "Stop On This Date" and/or "Stop After This Many Copies" (0 or empty means never; whichever comes first ends it). A finished repeat is switched off by itself and no longer shows in reminders.
- Repeating invoices: new "Stop On This Date" next to the existing "Stop after this many invoices".

## 1.27.1 - Add Item spacing
Update package: install from System > Updates. No database changes.
- Invoice page: a little space above the Add Item button.

## 1.27.0 - Otto fix, rules from emails, arrangeable settings
Update package: install from System > Updates. No database changes.
- Otto invoices: fetching now works with the real Otto emails (subject just "Otto", sent from the billing platform, invoice as a PDF). An email counts when it comes from a sender you list (Settings > Email Import > Otto Invoices, default "ottocar, ordwayplatform") and has an invoice number such as INV-1926443. The title includes the invoice number, and the charge type (road tax, rent, other) is worked out from the words in the email or PDF. Charge Types is now a list you can edit. The PDF is kept on the expense, and the amount is read from the email or the PDF; if it cannot be read the email waits for review.
- Email Import: new "Email Or PDF Contains" box on your own rules, so suppliers that use generic subjects can still be matched. Emails with no rule are now listed on Email Import (needs-a-look ones first) with a "Create Rule From This Email" button: the form opens filled in from the email, saving adds the rule and checks the email again straight away. Checking mail with no rules now lists the emails so you can make rules from them.
- Settings pages can be arranged: press Arrange on any settings page, drag cards and settings to where you want them, and switch each one between Full Width and Half Width. Everything stays one full-width column until you change it. It is saved for you only, and Reset Layout puts the page back.

## 1.26.0 - Repeating reminders and weekly digest
Update package: install from System > Updates. No database changes beyond 1.25.0.
- Settings pages are back to full width: the two-column cards from 1.22.0 and 1.25.0 are removed from Money, Invoice Payments, Email Import and Invoice PDF Look.
- Reminders: a note in the notification bell a few days before a repeating cost or repeating invoice is made, once each. Settings > Money > Reminders chooses whether, and how many days before.
- Weekly digest: an email on the weekday you choose with what changed in the last 7 days (money received, costs added, invoices sent), what is still owed and overdue, which invoices became overdue, what repeats in the next days (you choose how many) and how many receipt photos wait in the Receipt Inbox. Settings > Money > Weekly Digest has the switch, the day, where to send it, and "skip it in a quiet week". Reports has an Email Me This Week's Digest button to see it now. The wording is a template under Email Templates.

## 1.25.0 - Yearly accountant email, two-column settings
Update package: install from System > Updates, then press "Finish update" (adds a small table that remembers the yearly send).
- Settings > Money > Reports: optionally email last year's accountant pack to your accountant by itself, once, on a day in April you choose (after the tax year ends). The bell tells you when it was sent or why it could not be. It uses the accountant email saved there.
- Invoice Payments, Email Import and Invoice PDF Look settings now show their cards two to a row, like Money, with the fields stacked inside each card.

## 1.24.0 - Email the accountant pack
Update package: install from System > Updates. No database changes.
- Reports > Email To My Accountant: builds the accountant pack for the chosen tax year and emails it to your accountant with the zip attached, with your own message and an optional copy to you. If the pack is bigger than the limit it is sent without receipts and the email says so.
- Settings > Money > Reports: your accountant's name and email (filled in each time), and the largest pack to email. There is a new "Money: accountant pack" email template under Email Templates.

## 1.23.0 - Full accountant pack
Update package: install from System > Updates. No database changes.
- The Accountant Pack on the Reports page is now a complete zip for one tax year (or all years): a readable summary page (totals, estimated tax, month by month, costs by category), every CSV file in a csv folder, a copy of each invoice you can open in any browser, and your receipts and documents in folders by month.
- Settings > Money > Reports: choose whether to include invoice copies and receipts, and the most receipt data to include (MB). Anything left out because of the limit is listed in the pack.

## 1.22.0 - Email statements, Money settings in two columns
Update package: install from System > Updates, then press "Finish update" (adds the statement-sent date to contacts).
- Settings > Money: the cards now sit two to a row, with the fields inside each card stacked underneath each other at full width. On narrow screens they go back to one column.
- Email Statement: the envelope icon beside a client on the Contacts page emails them their statement (an amount owed box, the unpaid invoices, a button to open and save the statement as a PDF and pay online, and your bank details). You can change the subject and message first, and the page shows when it was last sent.
- Email Statements button on the Contacts page: lists every client who owes money, with what they owe, what is overdue and when they were last sent a statement. Everyone with an email address is ticked, untick any you want to skip, then send. Clients with no email address are shown greyed out.
- Optional monthly send (Settings > Money > Invoices): on a day you choose, every client who owes at least an amount you choose gets their statement, once at most every 20 days. The bell reports the result. There is also a new "Money: statement for a client" email template you can edit under Email Templates.

## 1.21.0 - Client statements, emailed repeating invoices, settings toggle fix
Update package: install from System > Updates, then press "Finish update" (adds the repeating invoice email column).
- Fixed: on settings pages the on/off switch was drawn over its "Default" reset link. The link now sits under the switch.
- Client statements: every invoice and payment for a client over a period, with a running balance, the balance brought forward, what is owed now and how late it is (not yet due, 1 to 30, 31 to 60, over 60 days). Open it from the statement icon on the Contacts page or Actions > Client Statement on an invoice. Choose 3, 6, 12 or 24 months, this tax year or all time, then Save As PDF Or Print. It uses your Invoice PDF Look. Clients see the same statement from their portal page.
- Repeating invoices can email themselves: a switch on the repeating invoice form sends each new invoice to the client with a button to open and pay it. The default, a copy to you and how far back statements start are settings (Settings > Money). The bell tells you when one was sent, or why it could not be.

## 1.20.0 - Invoice PDF look, Otto invoices from Gmail, Receipt Inbox
Update package: install from System > Updates, then press "Finish update" (adds the receipt inbox column).
- Settings > Invoice PDF Look: choose Modern, Classic or Minimal, the accent colour, font (sans, serif, rounded), logo height, paper size (A4 or Letter), the wording (heading, From, Bill To and Balance labels), an extra line at the bottom, and which parts show (status badge, payments, bank details, terms, note, footer, quantity and rate columns).
- Email Import now reads Otto invoices: vehicle rent, road tax and any other Otto charge (fines, damage, fees). Each becomes an expense with its own category (made for you if missing), the amount is read from the email or from the attached invoice PDF, and the PDF is kept on the expense with a safe copy of the email. The sender word and the three category names are settings.
- Receipt Inbox (Money > Receipt Inbox): on your phone press Take A Photo, or drop files on a computer. Each photo waits as a receipt to sort, then you add the title, date, amount and category and save it as an expense. Default category and paid-with are settings.

## 1.19.0 - Invoice PDF, stacked invoice page, test payments, client portal
Update package: install from System > Updates, then press "Finish update" (adds the client portal link column).
- Fixed: the invoice and vehicle pages had their right-hand cards side by side after 1.18.0. Client details are on top and Payments underneath again.
- New invoice document: View PDF and Save As PDF open only the invoice (logo, business and client details, items, totals, paid, balance due, bank details and terms) in a clean modern layout that prints on one A4 page. Save As PDF opens the print box straight away, choose "Save as PDF". The pay page links to the same document.
- Invoice page: Paid Invoice, Save As PDF and Email (Paid) Invoice moved into the Actions menu. View PDF added there.
- Test mode for Stripe and PayPal (Settings > Money > Online Payments): practice payments with Stripe test cards or the PayPal sandbox, optional separate test keys. A test payment is logged and shows in the bell, but is never added to income or the invoice balance. The pay page shows a Test Mode notice. A sk_test_ key or PayPal Sandbox mode is always treated as a test.
- Client portal: every client gets a private link listing their sent invoices, what is owed and paid, with View and Pay for each. Open it from the link icon on the Contacts page. It can be switched off in settings.

## 1.18.0 - Notification bell, custom fields, invoice search, better invoices, Money reports
Update package: install from System > Updates, then press "Finish update" (adds the notification and custom field tables). If the pay page ever shows a blank page, Finish update was not pressed yet.
- Notification bell in the top bar: errors and failed tasks, emails that could not be sent, new contact messages, payments received, repeating invoices and expenses made, invoices that became overdue, and receipt emails imported. Unread count, drop-down, full list with filters, mark read, clear. Settings > Notifications chooses what it tells you about, how long it keeps them and how often it looks.
- Custom Fields (Money > Custom Fields): add your own fields (short text, long text, number, amount, date, choice list, yes or no, web link, email) to expenses, payments, invoices, clients and suppliers, trips and vehicles. They appear on the form, are saved, can be needed, can be a column in the list, show on the invoice page, are copied to repeating items and are columns in the downloads.
- New payment form: pick the invoice from a searchable list (type a client, invoice number, date or amount). Choosing one fills in the client, stream, title and the amount still owed.
- Invoice page: Pay Invoice button while money is owed, Invoice Paid with Paid Invoice and Save As PDF once paid, Email Invoice always in view, and the Payments Add link is now a button. Emailing a paid invoice says it is paid.
- Invoices list has the same filter panel as expenses and payments: dates, status, client, income source, amount, money still owed, emailed, repeating and rows per page, with removable filter chips.
- Email Import: Fetch Now buttons (for all rules, or one rule) look back over older mail with the rules as they are now. Settings has how many days.
- Categories: the add form is at the top of the list.
- The income and costs pop-up has one close button, not two.
- A wrong or old payment link now says so instead of showing a blank page.
- Reports (1.18.0): Income By Client, Money Still Owed by how late, this tax year next to last year, and custom fields as columns in the downloads. Two new downloads (income by client, money still owed) are in the accountant pack.
- Tests: Money 38, core 63.

## 1.17.0 - Pay invoices online, Gmail import, stylish emails, breakdown pop-ups
Update package: install from System > Updates, then press "Finish update" (adds the payment log and email import tables).
- Invoices can be emailed (Email Invoice on the invoice page) with a button to a private pay page on your site. Clients pay by card (Stripe, with Apple Pay and Google Pay when their device has them), PayPal, or see your bank details. Part payments are optional. Each payment is checked with Stripe or PayPal and added to your income and the invoice exactly once, and a receipt goes to the client. Overdue reminders can be switched on. Settings > Invoice Payments.
- Gmail import (Money > Email Import): receipt emails become expenses. A built-in rule handles the Heathrow (APCOA) drop-off fee, and you can add your own rules. The email is saved on the expense as a safe copy of the page (a true screenshot cannot be taken on a web server), then moved to the Gmail bin. Check Mail Now, a connection test, and an optional hourly schedule. Uses a Gmail app password.
- Every email now has a clean modern look with your logo on top (header style in Settings > Email).
- Breakdown pop-ups for income and costs (name, amount, coloured bars, total), scrollable, also on the quarterly cards.
- Invoices list has an Income Source column. Dashboard Recent Months is oldest to newest with a choice of how many.
- "Paid by" and "Paid with" lists are set in Settings > Money and used in the add-payment and expense forms.
- Fixed the mixed-up icons for attached files in the expense pop-up, and toggles now sit fully left or right.
- The public front end is a completely blank page while Settings > Site > blank front end is on (every non-admin address).
- Removed Keyboard shortcuts from the top bar. All new pages are findable in the top-bar search.
- Tests: Money 33, core 63.

## 1.16.0 - Galleries, admin menu manager, bulk actions, admin favicon, dark mode fixes
Update package: install from System > Updates, then press "Finish update" (adds the gallery tables).
- Favicon: new Settings > Look and languages > Admin favicon (shown on admin and sign-in pages). The site favicon is now also set in General settings and used on the public site, and the admin falls back to it when no admin icon is chosen.
- Dark mode: Money pages, tables, cards, pop-ups, filters, chips, badges and form fields now follow the admin dark theme, so no table stays white.
- Admin Menu (Settings > Look and languages > Admin Menu): drag groups and pages into any order, move a page to another group, rename it, or switch it off with the eye. Hiding only removes the link; the page still opens and the top-bar search still finds it. Dashboard, Settings and System can never be hidden. Reset puts everything back.
- Galleries (Media > Galleries): albums made from library pictures. Drag to reorder, add captions, choose a cover, pick Grid, Masonry or Slider, columns, gap, picture shape, lightbox and captions. Each published gallery has its own page at /gallery/name and can be put in any page with [gallery slug="name"] (also in the editor's insert list). Galleries can be published, unpublished or deleted in bulk.
- Media Library: select pictures and add them to a gallery (or start a new one), or download the selection as one zip.
- Bulk actions: tick boxes and an action bar on Expenses (delete, set category, set business share), Payments (delete, set stream or client), Trips (business, private, set vehicle, delete), Invoices (draft, sent, cancel, delete) and Clients and Suppliers (archive, bring back, delete), and on Newsletter subscribers (mark as left, put back, remove). Pages, Messages, Users, Mail log, Error log and Queue already had them.
- Tests: Money 30, core 63.

## 1.15.0 - Edit and delete everywhere, richer Expenses filter, clickable attachments
Update package: install from System > Updates. Nothing to finish afterwards.
- Expenses: the paperclip is now one icon per file showing what it is (picture, PDF, Word, spreadsheet, CSV, text). Click one to look at the file in a viewer on the page (pictures and PDFs), with a button to open it in a new tab; other files download.
- Expenses filter rebuilt: tax year, ready-made periods (this month, last month, last 30 or 90 days, this calendar year) or your own dates with the date picker, plus a "More Filters" panel for category, income stream, vehicle, paid to, paid by, amount from-to, receipt (has a file or not), repeating, business share and rows per page. Active filters show as chips you can remove one by one, or clear all. Payments has the same bar (stream, client, amount, on an invoice or not).
- Every list row has an edit pencil and a delete bin at the right: expenses, payments, mileage trips, invoices, clients and suppliers. Vehicle cards have them too. Deleting from a filtered list brings you back to the same filtered list.
- Categories can be renamed and deleted (their expenses are kept, with no category). New Money > Clients and Suppliers (add, change, delete or archive) and Money > Income Streams (rename, colour, delete or archive). Anything already used by records is archived instead of deleted, so old records keep their names.
- Dashboard > Recent Months: the income and costs amounts open a pop-up showing what the month is made of (income by stream, costs by category) with a link to the entries.
- PDFs now show in the browser (they were blocked by a security setting meant for other file types). Menu and label wording uses "and" in lower case.
- 3 more Money tests (29 in total).

## 1.14.0 - Uber weekly statements and wider pop-ups
Update package: install from System > Updates. Nothing to finish afterwards.
- New Money > Uber Statements: upload one or more Uber weekly statement PDFs (read on your own server, no extra programs needed). Each week becomes an invoice to Uber with your trip payments, cancellation and wait fees, holiday entitlement, tips and reimbursements as lines (or one line for the week), with small deductions inside the earnings shown as a discount. A payment is recorded for each bank payout, and one for what Uber kept back, so the invoice is fully paid and your income is the full earnings.
- Instant Pay fees and the worker pension contribution become expenses on the last day of the week. The pension goes to your Pension category, so it stays out of your costs. Every figure is checked against the totals printed on the statement, and any difference is shown as a warning before you add anything.
- You check each week first. A week already loaded is marked "Already added" and cannot be added twice. The client, stream and categories you choose are remembered. Settings > Money > Uber statements chooses one line per kind of earning or one line per week.
- The New Invoice, New Expense and other pop-ups are wider, and Settings > Money > Form fields > Pop-up form width sets Standard, Wide, Wider or Widest.
- 1 more Money test (26 in total).

## 1.13.0 - Bank statement import
Update package: install from System > Updates, then press "Finish update" so the new bank tables are created.
- New Money > Bank Statements: upload the CSV from your bank. The columns (date, description, amount or paid in and paid out) are guessed from the headings and can be changed; credit cards that show spending as positive numbers can be swapped. Dates like 05/11/2026, 2026-11-05 and 5 Nov 2026 and amounts like (12.50) or 12.00- are understood.
- You check every row before anything is added: tick the ones you want, and choose an expense category or income stream for each. Rows already in your records are marked "Already added" and left out, so loading the same statement twice adds nothing.
- Rules choose categories for you: "if the description contains Shell, it is a Car And Travel cost". Rules are rows you can add, remove and drag into order; the first match wins.
- The uploaded file is deleted after adding, and anything left behind is cleared after a day. Spreadsheet-style formulas in descriptions stay plain text.
- 1 more Money test (25 in total).

## 1.12.0 - Reports, accountant pack and taller form fields
Update package: install from System > Updates. Nothing to finish afterwards.
- Form fields are taller with smaller corners everywhere in the admin. Settings > Money > Form fields changes the height (Normal, Tall, Taller) and the corners (Square, Slightly Rounded, Soft).
- New Money > Reports for any tax year: month by month income, costs and profit (adding up exactly to the year), costs by category with their tax box and share, and a short view of your return with the tax to put aside.
- Downloads as spreadsheet (CSV) files: summary by month, payments, expenses (with business share, category, tax box and vehicle), invoices (with paid and balance) and the mileage log. The Accountant Pack button gives all of them in one zip. Text that could run as a spreadsheet formula is made harmless.
- 1 more Money test (24 in total).

## 1.11.0 - Arrangeable dashboard, repeating invoices, new date picker and controls
Update package: install from System > Updates, then press "Finish update" so the new invoice columns are created.
- The dashboard is now yours to arrange. Choose Customise, then drag cards to move them (or use the arrows), pick a width on each card (quarter, third, half, two thirds, full) and take cards off. Cards in a row sit side by side, so moving and resizing makes rows and columns. Changes save by themselves; Reset Layout restores the ready-made one.
- Add A Card builds your own: a single figure (income, costs, profit, estimated tax or still owed, with a comparison to the period before), a chart over 3 to 24 months (income, costs and profit as bars or lines), a ring breakdown (by stream, category or tax box) or a list (latest or biggest income and expenses, unpaid invoices). Each can be limited to a period, an income stream and a cost category.
- Invoice numbers are now automatic and carry on from your RISE numbers. The number is no longer typed.
- Invoices can repeat (every N days, weeks, months or years, with an optional number of times). Each copy gets the next number, the items and the same due gap, and is added by the daily scheduled task. Settings > Money chooses whether copies are Sent or Draft.
- The date picker has months and years views, working arrows, Today and Clear.
- Tick boxes, radios, switches and all drop-downs are restyled; long lists get a search box.
- Settings > Money: the invoice logo is now uploaded instead of a web address, and phone numbers are no longer wrongly refused.
- Every New Invoice, New Expense and similar button now follows the pop-up or page setting. The dashboard header buttons no longer touch the tax year picker.
- 2 more Money tests (23 in total).

## 1.10.0 - Vehicles and mileage log
Update package: install from System > Updates, then press "Finish update" so the new mileage tables are created.
- New Money > Vehicles: add each car or van (owned, leased or hire purchase) and choose how you claim: actual costs or the HMRC mileage rate. Each vehicle has a page that puts both methods side by side for any tax year, with the business share of your miles.
- New Money > Mileage Log: log business and private miles per vehicle, with optional odometer readings. A day's total is fine.
- The mileage rate is built into your figures: for a vehicle on the mileage rate, its running and lease costs are left out and the claim is added to car and travel costs, using 55p for the first 10,000 business miles of cars and vans in a tax year (45p before 2026-27), 25p after, and 24p for motorcycles. Parking and tolls stay claimable (Categories > Claimable with mileage rate). The overview, dashboard, quarters and tax estimate all follow.
- HMRC's rules are enforced: a vehicle that has had capital allowances cannot use the mileage rate, and the way you claim cannot change once trips or costs exist (only when the vehicle is replaced). A button links your earlier car costs to a vehicle.
- Settings > Money > Mileage rates: all rates and the 10,000 mile step can be changed.
- The standard dashboard cards (health check, messages, pages and so on) are now hidden. Settings > Admin > Look > Standard dashboard cards brings them back.
- 3 more Money tests (21 in total).

## 1.9.0 - Invoices with items, pop-up forms, new look and button styles
Update package: install from System > Updates. Nothing to finish afterwards.
- Income is now added as an invoice, like RISE: create the invoice, then add items (title, description, quantity, unit, rate), a discount, and payments. Items can be dragged into order. The Actions menu has Edit, Print Or Save As PDF, Mark As Sent or Draft, Cancel and Delete. "Income" is now called Payments.
- Every Save button saves and goes back to the list, and a new Save & Stay button keeps you on the record.
- The overview is gone from the menu: its content is now at the top of the admin home page.
- All cards are white with a tiny shadow so they stand out. Grey outline buttons are white buttons.
- New Expense, New Invoice, items and payments can open as a pop-up or as a page: Settings > Money > Adding records.
- New tick boxes, radios, switches and selects across the admin.
- New Settings > Button Style: 12 button styles with a live preview of each.
- 3 more Money tests (18 in total).

## 1.8.0 - Money dashboard and tax estimate
Update package: install from System > Updates. Nothing to finish afterwards.
- The Money overview is now a full dashboard: previous and current tax year cards (income, costs, profit, where each £100 goes, estimated tax), quarterly update cards with due dates and a Mark As Sent button, recent months, 12-month income and costs chart, cost and stream charts, invoice overview, upcoming deadlines, a "needs a look" list, latest income and expenses, and a private note.
- New Tax Estimate page: Income Tax band by band and Class 4 National Insurance, with what to put aside. Rates are in Settings > Money and were checked for 2026-27. Optional pension tax relief widening.
- New Settings > Money: tax rates, calendar or standard quarters, time zone, month cards shown, receipt reminder amount.
- Money sits above Content in the left menu. All Money pages have a new, softer look with pill buttons.
- Search, quick actions and a dashboard widget for Money. The only framework file touched is the menu group order in the admin layout.
- 3 more Money tests (15 in total).

## 1.7.0 - Money: your own income and expense records
Update package: install from System > Updates, then press "Finish update" so the new Money tables are created.
- New Money section: Overview of the tax year (income, allowable costs by HMRC box, profit, what customers still owe), Expenses with receipts (drag and drop), Income, Invoices, and Categories where each cost is mapped to its HMRC box. Records only: nothing is sent to HMRC.
- Tax years run 6 April to 5 April. Dates are shown day first and picked with a styled date picker.
- Repeating expenses (weekly vehicle rent, monthly software) add their next copy automatically with a scheduled task.
- Import from RISE CRM: upload the phpMyAdmin backup and the zip of attached files. Clients, categories, expenses, invoices, payments and receipts come over. Invoice status is worked out from payments. It is safe to run twice. Imported categories are filed to HMRC boxes by name, and anything unsure is listed to check.
- Pension payments, personal spending and equipment are never taken off profit.
- Receipts are stored outside the public folder and only given to signed-in people who may see Money.
- Money has its own tests: `php application/Modules/Money/tests/run.php` (12 tests). The framework itself is unchanged.

## 1.6.2 - Show password on the installer
Update package: install from System > Updates. Nothing to finish afterwards.
- The eye button in the password boxes of the installation pages (the database password and your admin password) now shows and hides the password. Pressing Continue twice no longer sends the form twice.
- 1 new automated test (61 in total).

## 1.6.1 - Licence keys and Module Builder removed
Update package: install from System > Updates, then press "Finish update" if it asks. It works on 1.5.1 and on 1.6.0, so you can skip 1.6.0.
- The licence key system from 1.6.0 is removed: System > Licence, the activation screen, the scheduled licence check, the licence line in the Health Check and the licence note on the Updates page are gone. Updates install as before, without a key.
- The Module Builder from 1.5.0 is removed, together with `php ap make:crud`. Sections that were made with it (their menu item, folder, database table and records) are removed too, because they cannot work without it. A copy of the database is still saved before the update as usual.
- `php ap make:module Name` still starts a new module by hand.
- 60 automated tests (the Module Builder test was removed with it).

## 1.6.0 - Licence keys (Tulip Web)
Update package: install from System > Updates. Nothing to finish afterwards. Licence checks stay switched off in this version until the Tulip Web licence server is online again and its address and public key are added, so nothing changes on your sites yet.
- **System > Licence (new):** shows the licence for this domain: the key (hidden except the last 4 characters), the domain, when it was activated, until when updates and support are included, and when it was last checked. Buttons: "Check Now", "Remove From This Domain" (frees the key to use on a new domain) and "Use a different key".
- **Activate first:** on a real domain the admin opens only an "Activate this website" screen (in the sign-in page style) until a valid key is entered. Staff who are not administrators are asked to contact their administrator. The public website always stays online.
- **Regular check:** a new scheduled task asks Tulip Web every few days (as often as Tulip Web says) whether the key is still valid. If it is cancelled, refunded or used on another domain, only updates and support stop: the website and the admin keep working, and a note appears on the dashboard, on the Updates page and in the Health Check.
- **Safe by design:** every answer from the licence server must be signed (Ed25519) with Tulip Web's public key, and old answers are not accepted, so another server cannot pretend a key is valid. If the licence server cannot be reached, nothing changes. Test addresses (localhost, *.test, *.local, staging.*, dev.*) never need a key. The key is stored encrypted.
- Updates (from System > Updates and `php ap update:install`) need a valid key when licences are switched on. Going back to the previous version always works.
- Search "licence" in the top bar to find the page. Activations, refused keys and status changes are in the activity log. New permission: "Activate, check and move the licence key" (super admins always can).
- 1 new automated test (62 in total).

## 1.5.1 - Example module removed
Update package: install from System > Updates, then press "Finish update" (it tidies away what the Example module left in the database).
- The Example module, a sample that showed how modules work, is gone: its "Example page" menu item, its dashboard box, its greeting in the footer and its setting. Use the Module Builder or `php ap make:module Name` to start a new module instead.
- Updates now also remove the folders that removed files leave empty.

## 1.5.0 - Module Builder
Update package: install from System > Updates. Nothing to finish afterwards. One new part is added: the Module Builder (always on, only super admins can use it).
- **System > Module Builder (new):** make a new admin section, such as Products, Team Members or Projects, without writing code. Give it a name (the short name, the singular and the database table are filled in for you), pick an icon and the menu group, then add fields: click a field type to add it, or drag a type into the list, and drag fields into the order of the form. A live preview shows the menu item, the list columns and the form.
- **18 field types:** short text, long text, formatted text (with the editor), whole number, price or decimal (with £ or $ in front), yes / no switch, choice from a list (each choice with its own colour), link to another section (for example a product's category), date, date and time, time, email address, phone number, web address, picture (upload or Media Library), file, colour, and web address name (slug, made from the title and always unique).
- **Each field's choices:** needed or not, shown in the list, found by the search, a filter above the list, sortable, no two records the same, full or half width, help text, example text and a starting value.
- **What every section gets:** a menu item, a list with search, filters, sorting, a choice of columns and rows per page, yes / no switches that save straight away, ticking several to change them or delete them, CSV download, and (if you like) drag-and-drop order; an add / edit form with checks for every field, "Save and add another", "Save and go back to the list" and "Make a Copy"; permissions to see, add, change and delete in Roles and Access; results in the top-bar search; an "Add a …" action; and an optional dashboard box with the number of records and the latest ones. Every change is in the activity log.
- **Changing a section later:** open it in the Module Builder. New fields are added to the table; removed fields leave the form and the list, but their data stays; a field's type is locked once it holds data. "Delete the Module" removes its files, its table and its records, after you type its short name. "Lock the Module Builder" stops anyone changing modules on a finished site.
- **For developers:** `php ap make:crud Products name:text! price:decimal status:select=New|Sold category:relation=categories --group=Shop --icon=fa-box`. Each section is a folder in application/Modules whose module.php is a plain description (no generated code to maintain), so improvements to the framework reach every section. Hooks: the "crud.saving" filter and the "crud.saved" and "crud.deleted" events. Module routes can now be a function as well as a file.
- 1 new automated test (61 in total).

## 1.4.9 - Dashboard notice while no cron job is set up
Update package: install from System > Updates. Nothing to finish afterwards.
- **A notice at the top of the dashboard** says when background tasks are running manually: no cron job is set up, so queued emails, health checks and clean-ups run when someone opens a page, and that page can be slow. It shows the cron line with a Copy button, where to add it (Hostinger hPanel or cPanel > Advanced > Cron Jobs) and a "Set up the cron job" button that opens System > Scheduled Tasks.
- **"Remind me in 3 days"** hides it for a while and **"Dismiss"** hides it for good, each for the person who pressed it, without reloading the page. If the built-in scheduler is switched off and no cron job runs at all, a red notice says tasks are not running; dismissing that one only hides it for a day.
- The notice goes away by itself once a real cron job (or a web-cron service) has run in the last 15 minutes.
- **System > Scheduled Task Settings:** "Warn on the dashboard while no cron job is set up" (on by default) and how many days "Remind me later" hides it (3 by default). Search for "cron" in the top bar to find them.
- Only people who can see Scheduled Tasks get the notice.
- 1 new automated test (60 in total).

## 1.4.8 - New sign-in page
Update package: install from System > Updates. Nothing to finish afterwards.
- **The admin sign-in page has a new look:** a dark background with a white sign-in box. The site name sits at the top of the box in your accent colour with an icon (or your own logo), the language choice (for example EN / TR) is next to it when the site has more than one language, and a bold heading and a short line explain what to do. The boxes are light blue-grey, the eye to show the password sits inside the password box, the button says "Sign in", and a note underneath says what to do after forgetting the password (a link to reset it by email when emails are set up, otherwise that another administrator can set a new one). "Forgot your password?", two-step sign-in and new-password pages share the same look.
- **Settings > Admin Panel > Sign-in page (new):** dark or light background, the dark background colour, a logo, the icon next to the site name, the heading, the text under it, the forgotten-password note and the language choice. Search for "sign-in page" in the top bar to find it.
- Fixed: the eye button that shows the password did nothing on the sign-in pages. The sign-in button can no longer be pressed twice while signing in.

## 1.4.7 - Pages no longer wait for background work
Update package: install from System > Updates. Nothing to finish afterwards.
- **Fixed: some pages took 25 seconds or more.** About once a minute, the built-in scheduler runs waiting work (sending queued emails, the health check, monitoring) after a page is sent. On PHP-FPM servers the page was handed to the visitor first, but on LiteSpeed servers (Hostinger and many cPanel hosts) it was not, so whoever opened that page waited for all of it. The page is now handed over on LiteSpeed too (litespeed_finish_request), and on servers that can do neither the output is flushed and the background work is kept short.
- **New setting, System > Scheduled Task Settings: "On servers that cannot finish the page first, spend at most (seconds)"** (6 by default, 3 to 25). Servers that hand the page over first keep the normal 25 seconds. Search for "slow pages" in the top bar to find it.
- **Background work keeps to the scheduler's time limit:** the email queue no longer runs for its own 40 seconds inside a scheduler run, an email server that does not answer can only use the time that is left (and the email is tried again next time), push notifications stop and carry on in the next run, and monitoring leaves the rest of its pages and links for the next run instead of overrunning.
- **Outside web requests have a time budget:** map services, uptime checks, robot checks, push and Brevo now go through one place that cuts each request to the time left and makes no new request once the time is up. A check that was cut short is never reported as "website down" or "broken link".
- **Settings > Maps: "Longest an address search or key test may wait (seconds)"** (6 by default), so a slow map service can never make that page hang.
- Faster public pages: the footer no longer reads the list of menus on every page.
- Tested here with an email server that accepts connections but never answers: on a server that cannot hand the page over, the page that triggered the queue took 60 seconds before and 5 seconds after.
- For developers: AP\Core\Http::request() with Http::$deadline, Scheduler::$deadline, Scheduler::secondsLeft() and Scheduler::nearlyOutOfTime(), Kernel::finishRequest(), and $request->attributes['finished'] in "app.terminate" listeners.
- 1 new automated test (59 in total).

## 1.4.6 - Languages page, maps and a newsletter sign-up
Update package: install from System > Updates, then press "Finish update" (it adds the table for newsletter subscribers). Two new modules are switched on: Maps and Newsletter sign-up.
- **Settings > Languages, rearranged:** each language shows its short code, how much is translated, an on/off switch that works straight away and a star for the default (press an empty star to make that language the default). "Translate" sits on each row, and the "..." menu has Change name, Download the wording, Upload a language pack and Remove. Drag the rows into the order of the switcher. "Add a Language" is a card on the right ("Not in the list?" adds your own).
- **Language options on the same page:** choose the language from the visitor's browser, how long a choice is remembered and the admin language, plus new switcher choices: next to the menu, in the footer or both; showing the short code (EN), the name (English) or both; and whether it also shows in the admin top bar.
- **Settings > Maps (new):** a list of map services (CARTO Light, OpenStreetMap, MapTiler, Mapbox and Google Maps) to drag into order. The first one that is on and has its key shows the maps; if it cannot load in a visitor's browser, the next one takes over, and the list shows which is "Used first" and which are back-ups. Each key has a Test button and a "Get a key" link. Set the place by searching for an address, clicking the map or dragging the marker, with the zoom, the name on the marker, the height, the marker, mouse-wheel zoom and a "Get directions" link.
- **Maps on pages:** type [map] in a page or press the new map button in the editor; [map zoom="16" height="300"] or [map lat="..." lng="..." label="..."] changes one map. The footer builder has a Map block. Maps load only when they scroll into view. The map library (Leaflet 1.9.4) comes with the update, so no outside script is needed except for Google Maps, which the security policy allows on public pages only when it is switched on.
- **Newsletter sign-up (new):** a sign-up box in the footer (the "Newsletter sign-up" block) or on any page with [newsletter], with the wording, an optional first-name box, a consent line (kept with each subscriber), an optional "I agree" tick box and an email to you for each sign-up, in Settings > Newsletter Sign-up. Sign-ups are checked for spam. Marketing > Subscribers lists who is on the list and who left, with search and Download (a CSV file for Mailchimp, Brevo or any email tool, with each person's leave link).
- Fixed: addresses with a number in braces in their pattern did not work, so switching a language on or off, making it the default, editing, translating and removing a language, and deleting an update backup could fail with "Method not allowed" or "Page not found". The health check monitor address had the same problem.
- For developers: the router accepts patterns such as {code:[a-z]{2,3}}; Maps::render() and Maps::chain(); Newsletter::form(); the "footer.block_title" filter.
- 1 new automated test (58 in total).

## 1.4.5 - Footer builder and menu options
Update package: install from System > Updates, then press "Finish update" (it adds three columns for menu items). One new module is switched on: Footer builder.
- **Content > Footer (new):** build the bottom of every page from rows and columns. Each row is 12 parts wide: pick a ready layout (one column, two halves, three thirds, 4 + 8 and others) or set each column's width yourself. Blocks go in the columns: Logo and name, About text, Menu (any of your menus), Links (your own list, no menu needed), Social media, Contact details (from Settings > Contact Details, with opening hours if you like), Text or HTML, Button and, with several languages, Language choice.
- **Drag and drop everywhere on that page:** drag blocks from the list on the right into a column, drag blocks between columns, drag columns to another place or another row, drag rows up and down and drag links into order. Every move also has buttons, for the keyboard.
- **Bottom bar:** the copyright line ({year} and {site} fill themselves in), your own links, the links other parts add ("Cookie settings", "Get notifications"), a "Back to top" link and a small print line for company details. It can be switched off or centred.
- **Look:** light, soft grey, dark or your own colours, the width (same as the page, wide or edge to edge), the space around it and a line above it. On phones, columns go one under another or two side by side (per row), and blocks with a heading can fold, so visitors tap the heading to open them.
- **Live preview** next to the builder, as on a computer or on a phone, changing while you work. "Use this footer on the website" can be switched off to go back to the simple footer, and "Start Again" puts back the starting layout.
- **Menus:** each item can now show on all screens, big screens only or phones and tablets only, look like a button, and carry a small label such as "New". The list shows these as small tags. Pages, "Home page link" and "Heading" can be dragged from the left straight into place in the menu.
- Fixed: a warning in the admin search when a module had no name or description.
- For developers: the "footer.blocks" and "footer.block" filters add block types, Footer::render(), Menus::itemsOfMenu() and View::slotHtml().
- 1 new automated test (57 in total).

## 1.4.4 - Health check, monitoring and clearer logs
Update package: install from System > Updates, then press "Finish update" (it adds the tables for the health check history and monitoring). Two new modules are switched on: Health check and Monitoring.
- **System > Health check (new):** about 30 checks in one go, grouped into Security, Website and server, Database, Emails, Files, pictures and backups, Website and Connected services (HTTPS, debug mode, the setup folder, file permissions, PHP version and extensions, free disk space, the database, emails waiting or failing, the cron job, errors today, wrong passwords, the latest backup, log size and more). A coloured bar says "Everything is working", "Working, with things worth a look" or "Needs fixing", with a score and counts, and "Check everything now" (optionally with a real test email). Each row says what was found in plain words. Choose which checks run, when to warn (errors per day, disk space, backup age, emails waiting, wrong passwords, log size) and who gets alerts.
- **Automatic checks and alerts:** the health check can run on its own every hour, 6 hours or day, keeps a history, and emails you when a problem starts, as a reminder while it lasts, and when it is fixed. The menu shows how many problems are open, and the dashboard has a Health check box.
- **Outside uptime monitor:** switch on "Give a monitor address" to get a secret address for a free service such as UptimeRobot. It answers "OK" while all is well and an error when something needs fixing, so you hear about it even when the whole site is down.
- Staging mode and maintenance mode can be switched on and off from the Health check page, and the cron line to copy is shown there.
- **System > Monitoring (new):** Up and running (the home page checked every few minutes, with an email when it goes down and when it is back), Page speed (pages from the sitemap measured in turn, slowest first), Broken links (pages read on the day you choose, links to other websites too, with where each broken link was found) and Pictures (the biggest pictures in the media library). Each part can be run straight away, and has its own settings.
- **Logs, easier to read:** the Activity log, Email log and Error log have a filter card on the left (search, who, about what, when: today, 7 days, 30 days or your own dates). The Email log shows sent, waiting and failed counts and coloured status labels. The Error log shows each error as a card with a hint in plain words about what it means and what to do, also on the error's own page.
- For developers: the "health.checks" and "errors.hints" filters, QueryBuilder sum() and avg(), Ui::whenField(), data-when fields that show and hide with a choice, and the admin layout's "hubside" section.
- 1 new automated test (56 in total).

## 1.4.3 - Modules as cards and the Roles and Access grid
Update package: install from System > Updates. Nothing to finish afterwards.
- **System > Modules, redesigned:** every optional part is a card with an ON/OFF switch that works straight away (no page reload), grouped into Content, Marketing and Tools with "3 of 3 on" counts. Each card says what it does, what happens when it is off, and what is kept (for example "6 messages are kept" or "2 redirects would stop working while this is off"), with a Settings link and Details (version, tasks, jobs, email templates). Switching off asks first. Find a module by name, and filter All / On / Off (and Needs attention when a database update waits). The built-in parts are listed under "Always on".
- **Users > Roles and Access (new):** "Who can open what" lists every area of the admin, grouped like the menu (Main, Content, Marketing, Users, Settings, System), with a switch per role. "All" switches a whole group for a role, areas that share a permission move together, and "Find an area" narrows the list. Cards at the top show each role with how many people have it and how many areas it opens. Updates, Modules, Tools and Roles and Access stay with super admins. The left menu links to Roles (the list) and Two-step sign-in.
- Finer permissions (edit, delete, publish…) are still chosen on each role's page.
- For developers: module.php accepts 'icon', 'category', 'when_off' and 'kept' => fn(): string for the Modules page; menu items accept 'admin_only' => true.
- 1 new automated test (55 in total).

## 1.4.2 - Security settings, spam protection and a contact form
Update package: install from System > Updates, then press "Finish update" (it adds the tables for spam and messages). Two new modules are switched on: Spam protection and Contact form.
- **Settings > Security, rearranged like your screenshot:** Signing in (wrong passwords before lockout, lockout length), Admin sign-in (close the admin after minutes without use, and "Keep the admin open after closing the browser (days)", which adds "Keep me signed in" to the sign-in page), Passwords, Two-step sign-in, Browser protection, Password resets, Robot check and Updates.
- **Two-step sign-in can be required** for administrators or for all staff. Until they set it up, those people can only open their profile. "Trust a browser for (days)" adds "Do not ask again on this browser" after the code; trusted browsers are forgotten when the password changes or two-step sign-in is switched off.
- **Robot check:** Cloudflare Turnstile, Google reCAPTCHA v2 or hCaptcha, on the admin sign-in and "Forgot password" pages and/or on public forms. Secret keys are stored encrypted. If the service cannot be reached, people are let through. The recovery address skips it, so it can never lock you out.
- **"Ask for my password before installing updates":** when on, System > Updates asks for your password before installing or going back.
- **Content > Spam (new):** an overview (stopped in 30 days, held for a check, blocked addresses, where spam comes in, the busiest addresses with a Block button, and what is checked), the spam log with filters, Blocked addresses (single addresses or ranges such as 203.0.113.0/24, for a day, a week, a month, a year or always; you cannot block yourself by mistake) and Rules and words: a hidden trap field, a minimum time to fill in a form, most messages per hour from one address, most links, automatic blocking, blocked words and blocked email domains as lists you add rows to.
- **Contact form (new):** put it on any page by typing [contact-form] or pressing the new envelope button in the page editor. Settings > Contact Form: who gets the messages, phone and subject boxes (off, optional or required, with an optional list of subjects), a consent tick box, the button text, the thank-you text and a copy for the visitor. Messages are checked for spam, emailed to you (reply goes straight to the visitor) and kept on the new Messages page.
- **Messages (new, in Main):** Inbox, New, Answered and Spam tabs, search, tick several to change them, and a page per message with Reply by email, Call, Mark as answered, Spam, "Not spam, send it to me", Block this address and earlier messages from the same person. The menu shows the number of new messages, and the dashboard has a Messages box.
- Email templates can now have optional parts: {{#subject}}...{{/subject}} only shows when there is a subject.
- For developers: menu items accept 'badge' => fn(): int; the "pages.content" and "editor.inserts" filters; Spam::fields() and Spam::check() for any public form; Captcha::widget() and Captcha::check(); set_cookie(); settings forms can send "return" to come back to a module's own page; settings::_sections shows a settings group anywhere.
- 1 new automated test (54 in total).

## 1.4.1 - Code and tracking, cookie notice, SEO, social media, app and notifications
Update package: install from System > Updates, then press "Finish update" (it adds the tables for redirects and push notifications). Two new modules are switched on: Search engines and App and notifications.
- **Settings > Code and Tracking:** Google Tag Manager, Meta (Facebook) Pixel and Microsoft Clarity by their ID, a chat widget box, your own code for the page head and the end of the page, and extra CSS. For each box you choose whether it waits for cookie consent. Only people with the new "Add tracking codes" permission (and super admins) see this page.
- **Settings > Cookie Notice:** show it "Only when needed" (when tracking codes or code that waits for consent are used), "Always" or "Never"; a bar at the bottom or a box in the corner; your own text, button wording and cookie policy link; how long the answer is remembered; and a "Cookie settings" link in the footer so visitors can change their mind. Code that waits for consent really does not load until the visitor presses Accept.
- **Settings > SEO and Analytics:** default description and sharing picture for pages that have none, Google Search Console and Bing verification codes (pasting the whole tag works), Google Analytics 4 (waits for consent), the sitemap at /sitemap.xml with all public pages, robots.txt with your own extra lines, a staging mode that asks search engines not to list the site, and a list of addresses not found.
- **Content > Redirects (new):** send old addresses to new ones, "for good" (301) or "for now" (302), with notes, visit counts, an on/off switch and a "Where does it go?" tester. End an address with * to move a whole part of the site (blog/* to news/*). The "Addresses not found" tab lists what visitors could not find, with how often and where they came from, and a "Redirect" button for each. Robots trying addresses like wp-login.php are ignored. Redirects only take over addresses that do not exist.
- **Settings > Social Media:** Facebook, Instagram, X, TikTok, LinkedIn, YouTube, WhatsApp (a phone number works), Telegram, Pinterest, Snapchat and Threads, shown as icons in the footer.
- **Settings > App and Notifications:** visitors can add the website to their phone like an app: app name, short name, icon (the 192 and 512 pixel sizes are made for you), colours, how it opens, a friendly offline page, an "Install the app" button and a box that asks visitors to add the site to their home screen (with simple steps on iPhone), from the visit you choose. A status box shows what is still needed.
- **Marketing > Push Notifications (new):** press "Make the keys", switch notifications on, and visitors get a "Get notifications" button (in the footer, next to the menu, or both). Write a title, message, link and optional picture with a live preview, send yourself a test, then send to everyone. Sending happens in the background in batches; delivered and opened numbers are shown for each notification. Browsers that switched notifications off are removed automatically. Built without extra libraries (encryption checked against the official RFC 8291 example).
- **Safer public pages:** when tracking or your own code is used, the security policy of the public pages is widened just enough for it (the admin keeps the strict one), and every script gets the page's security code automatically.
- Lists with only one page no longer show "Page 1 of 1" twice. The Pages module no longer reads every page on each visit to the public site.
- For developers: the "code" field type, hidden settings, a "csp.policy" filter, "site.meta", "sitemap.urls" and "http.not_found" filters, the "body_start", "footer_social" and "footer_links" places in the site layout, and Consent::script() for any module that loads outside code.
- 4 new automated tests (53 in total).

## 1.4.0 - System menu tidy-up and the new search popup
Update package: install from System > Updates. Nothing to finish afterwards.
- **System pages live in the System menu:** Cache, Scheduled task, Background job, Log and Update settings moved out of Settings. Each one sits right under the page it belongs to (for example Cache, then Cache Settings), and their breadcrumbs say System. The Email log moved to System > Logs, next to the Error and Activity logs. The Modules shortcut left the Settings menu (Modules is in System > Maintenance).
- **Email status page folded in:** the old Email > Status and Test page is gone; its status line, connection test and test email are all on Settings > Email. Old links still work and open the Email settings.
- **New search popup:** click the search box at the top, press Ctrl + K or "/" and a search window opens with a short animation. Before you type it shows "Recently opened" and "Most used" pages (remembered for each person, on every device). As you type it finds pages, settings pages and single settings, quick actions, staff, website pages, media, menus, the email log, the error log, the activity log, modules, scheduled tasks, email templates and languages. Use the arrow keys (or Tab) to move, Enter to open, Esc to clear and Esc again to close. "See every result" opens the full results page. Pages you open often rank a little higher.
- **Quick actions in the search:** "Add a page", "Upload files", "Add a staff member", "Add a role", "Add a menu", "Add a language", "Send a test email", "Clear the cache", "Install an update", "Run the built-in tests", "Change my password" and "Set up two-step sign-in". Modules can add their own with 'actions' in module.php.
- **Drag and drop:** pinned menu items can be dragged into a new order (or moved with the arrow keys on the grip).
- For developers: setting groups accept 'hub' => 'system' and 'nav_label', and menu orders can be decimals (14.5 sits between 14 and 15). Search providers accept 'limit'.
- 1 new automated test (49 in total).

## 1.3.3 - Email settings and every way of sending
Update package: install from System > Updates and press "Finish update" when asked (it widens one column of the email log).
- **New email settings page:** a status line at the top shows how email is sent, to which server, the backup way and whether everything is ready ("Ready", "Needs set-up" or "Test mode"), with a "Test Connection" button. On the right: "Send a Test Email" and the email log numbers for the last 7 days (sent, waiting, failed) with a link to the log.
- **Five ways to send, chosen with cards:** SMTP server (recommended), PHPMailer (SMTP), Server mail, Brevo API, and Log only for testing. Sections that do not apply are hidden, so you only see the settings you need.
- **PHPMailer:** the PHPMailer 6.9.3 library is now included (application/vendor/PHPMailer, LGPL 2.1 licence). Uses the same SMTP details and can sign emails with **DKIM** (domain, selector and private key, the key stored encrypted).
- **Brevo API:** send through Brevo over the internet with an API key (stored encrypted). "Test Connection" checks the key without sending anything.
- **Backup way to send:** choose a second way that is tried automatically when the main one fails, and how long to wait for the server. The email log records which way sent each email, for example "phpmailer (backup)". If both fail, the error names both.
- **SMTP "Fill in for" buttons:** Hostinger, Gmail, Microsoft 365, Outlook.com, Brevo, Mailgun EU, SendGrid, Zoho EU, IONOS UK and your own cPanel host fill in the server, port and encryption in one click.
- **Look of emails:** new "Button and link colour" (links in emails, and links with class="button" become buttons), and the email logo can be uploaded or chosen from the Media Library with a preview.
- **For developers (any settings page):** new setting types "cards" and "image", secret text areas, "presets" (buttons that fill several fields), "show_if" (show a setting only when another has a certain value), "section_icon" and "section_help", and the slots "settings.top.<group>" and "settings.aside.<group>" for module content on a settings page.
- Tips on the email page for Hostinger, Gmail, SPF and DKIM. The email status page tests every way of sending, not only SMTP.
- 1 new automated test (48 in total).

## 1.3.2 - Media library
Update package: install from System > Updates. The new Media module is switched on and its tables are created by the update. Files you upload are kept in assets/uploads, which updates never touch.
- **Media library:** a new Content > Media Library page. Drop files anywhere on the page (or use "Upload Files") to upload several at once, each with its own progress bar and a clear message when a file is refused. Files show as a grid of thumbnails or a list; search by name or description, filter by type (images, documents, videos, audio) and sort by date, name or size.
- **Folders:** create folders and folders inside folders, rename and remove them (their files move up, nothing is deleted). Drag files onto a folder to move them, or tick several and use "Move to".
- **File details:** a side panel with a large preview, size and dimensions, the address with copy and open buttons, title, description for screen readers and search engines (alt text), caption, the pages that use the file, and delete. Changes save as you leave each field. Images without a description are marked "ALT" in the grid.
- **Pictures are prepared for the web:** photos wider than "Largest image width" are made smaller, turned upright and stripped of camera data such as location; a medium copy (1200 px) and a thumbnail (400 px) are made. All of this can be changed or switched off in Settings > Limits and Uploads.
- **Picker everywhere:** the page editor's image button has "Choose from the Media Library", which fills in the address, description and caption, and the sharing image of a page has a "Choose" button. You can upload straight from the picker.
- **Safer uploads (new Upload service for every module):** checks the size against both the setting and the server limit, the file ending against the allowed list, names such as "photo.php.jpg", that the content really matches the ending (a "jpg" must be an image), very large images, and cleans SVG drawings of scripts and outside links. Scripts, web pages and other dangerous files can never be uploaded, whatever the settings say. The uploads folder gets its own protection file so nothing in it can ever run.
- **Settings > Limits and Uploads:** "Allowed file types" is now a list of rows you can add to, remove and reorder; new options for shrinking photos, photo quality, medium and thumbnail widths, and how files are arranged on the server.
- **Dashboard:** a Media Library box with the number of files, space used and the latest images. Files are also found by the top-bar search.
- 1 new automated test (47 in total).

## 1.3.1 - Menus
Update package: install from System > Updates. The new Menus module is switched on and its tables are created by the update.
- **Menus:** a new Content > Menus page. Create as many menus as you like and choose where each one shows: the main menu at the top of every page or the footer menu at the bottom (themes can add more places). A place holds one menu at a time; giving it to another menu moves it.
- **Builder:** add pages (tick several at once, with a filter box), links (with "open in a new tab"), a home page link, or a heading without a link that groups the items under it. Drag items by their handle to reorder them, or drop one on the right half of another to put it underneath, up to three levels. Arrow buttons do the same from the keyboard. Each item opens to change its label, address, icon, CSS class and who sees it (everyone, only visitors who are not signed in, or only signed-in staff). Items with a problem are flagged ("Hidden until the page is published", "Needs an address").
- **On the site:** the main menu appears in the top bar with drop-downs for sub-items and the current page highlighted; it folds into a menu button on phones. The footer menu appears at the bottom. Pages that are drafts, scheduled or in the bin are left out automatically and come back when they go live.
- **Safety:** links must start with https://, /, # or mailto:, icon names are checked, and menus are saved only when every item is valid.
- Menus are kept in the cache and refreshed whenever a menu or a page changes. Menus are also found by the top-bar search.
- The welcome page now says how to replace it with your own home page.
- 1 new automated test (46 in total).

## 1.3.0 - Pages
Update package: install from System > Updates (or upload by FTP and press "Finish update"). The new Pages module is switched on and its tables are created by the update.
- **Pages:** a new Content > Pages part of the admin for the pages of your website. Tabs for all, published, drafts, scheduled and the bin, with counts; search by title or address; a tree view that shows sub-pages under their parent; bulk publish, change to draft, move to the bin, restore and delete; duplicate; preview; "Make It the Home Page".
- **Editor:** a visual editor with paragraph styles, bold, italic, underline, lists, alignment, links (with "open in a new tab"), images with a description and caption, tables, divider lines, undo and redo, an HTML mode, full screen, a word count and reading time. Text pasted from Word or other websites keeps its structure but loses its styling. Everything is cleaned when saved: scripts, frames, event handlers and unsafe links are removed.
- **Addresses:** made from the title as you type (Turkish and other letters are turned into plain ones), editable, with a live "free or taken" check and a copy button. Sub-pages get their parent's address in front (/services/web-design), and addresses of sub-pages follow when a parent is renamed or moved. Addresses the system uses (admin, install and so on) cannot be taken.
- **Publishing:** draft, published or scheduled for a date and time in your time zone; scheduled pages go live by themselves (a scheduled task checks every five minutes). People without the new "publish" permission can write and save, and someone else makes the page live.
- **Search engines and sharing:** search engine title and description with character counters, a live Google-style preview, a sharing image with a thumbnail, and "Hide this page from search engines". Pages print the description, canonical address and Open Graph tags.
- **Revisions:** the version before each save is kept (25 by default). Look at an older version in a safe preview and go back to it with one click; the text you replace is kept too.
- **Bin:** pages go to the bin first and can be restored as drafts; the bin empties itself after 30 days (adjustable). Sub-pages of a page in the bin move to the top level so they keep working.
- **Layouts:** Standard, Full width and No title (themes can add more).
- **Settings > Pages:** home page, browser tab title format, breadcrumbs, revisions to keep, bin days, and whether the editor opens in visual or HTML mode.
- **Dashboard:** a Pages box with counts, recently changed pages and "Add a Page". Pages are also found by the top-bar search.
- **Framework:** routes can be "fallback" routes that are tried after all others (used for page addresses), route patterns support non-English letters, a new HtmlSanitizer class cleans HTML from editors, and the site layout prints description, canonical and sharing tags when a page provides them.
- Fixed: System > System Info no longer fails when the database will not report its version or size.
- 3 new automated tests (45 in total).

## 1.2.1 - Update installer, Settings and System menus, drag and drop
Update package: this is the first version with the update installer, so upload these files over 1.2.0 by FTP once, open the admin and press "Finish update". Later versions install from System > Updates.
- **Install updates from the admin:** System > Updates takes an update zip (drag and drop or choose a file), checks it first (right product, newer version, PHP version, safe file paths, a checksum for every file, free disk space, files writable) and only then offers "Install". Before anything changes, the database is backed up and every file that will be replaced is copied. If a step fails, the old files are put back automatically. The database step then runs with the new code, and the result is shown with a full log.
- **Go back:** one button returns the files to the version before the last update. Update history with details, older backups with download (database copy) and delete, and a setting for how many backups to keep (Settings > Updates).
- **Never overwritten by an update:** configuration, storage, uploads, .htaccess and .env.
- **Command line:** `update:check`, `update:install`, `update:finish`, `update:rollback`, `update:history`, and `build:update` for developers to build a package from two versions.
- **Shorter main menu:** the menu on the left keeps Dashboard, Staff and Roles, and an Administration group with only Settings and System. Every page that belongs to them (email pages, languages, change history, updates, modules, tools, cache, scheduled tasks, background jobs, error and activity logs) is listed in a menu on the left of those pages, grouped into categories, with a filter box and open/collapse all. Pages in these menus can still be pinned to the top of the main menu with the pin icon.
- **Title Case:** menu labels, menu groups and search results are written in Title Case, except the word "and".
- **Drag and drop:** drop zones for update packages and language pack imports; drag languages by their handle (or use the arrow keys) to change their order in the switcher.
- **Bootstrap and Font Awesome:** a "Download Now" button (banner and System > Tools) saves a local copy so the admin works offline.
- Fixed: System > Modules failed to open because modules that add settings pages (Admin panel, Settings) broke the "What it adds" list.
- Fixed: the default email footer showed "&middot;" as text in the settings box; it is now a real dot.
- 6 new automated tests (42 in total).

## 1.2.0 - New admin look, search and settings pages
Update package: upload over 1.1.0, open the admin and press "Finish update" (or run `php ap migrate`).
- **New admin look:** navy top bar and menu, light pages, Poppins font, page titles with an info button and breadcrumbs, buttons for each page on the right of its title.
- **Menu:** "Find in menu" box, open and collapse all, groups that remember being collapsed, and a pin button to keep favourite pages at the top (saved per person).
- **Search the whole admin:** the box in the top bar (Ctrl + K) finds pages, settings pages, single settings and staff. Modules add their own results with a "search" entry in module.php.
- **Settings pages:** a searchable settings menu on the left, settings in collapsible cards, tips on the right, a save bar that stays at the bottom with "Undo changes", a show button and "Remove the saved value" for keys and passwords, a "Default" link per setting, and a jump straight to a setting from search.
- **Lists as rows:** a new "repeater" setting type for lists, with add, remove, move up and down and drag to reorder. Opening hours, maintenance IP addresses and the background job waits now use it; values saved by 1.1 are converted automatically.
- **New setting types:** segmented choices and colours with a code box.
- **Admin panel settings:** font, menu colour (navy or light) and accent colour.
- **Dashboard:** "Hello" greeting, number boxes for staff and errors, and a recent activity box.
- **Login page** in the new style.
- Fixed: lists that select "table.*" (such as the Staff page) could fail on MySQL because the star was quoted.
- Fixed: pressing Enter in a settings field could reset the first changed setting to its default.
- Validation: new "ip" rule.

## 1.1.0 - Phase 2
Update package: upload over 1.0.0, open the admin and press "Finish update" (or run `php ap migrate`).
- **Cache:** file cache with groups, admin page, expiry clean-up task, settings.
- **Scheduler:** tasks declared by modules, editable timing (presets or cron), run now, history, three triggers (cron job, web-cron URL, built-in fallback that needs no cron).
- **Background jobs (queue):** retries with back-off, failed jobs page, pause, "run immediately" mode, CLI `queue:work`.
- **Email:** SMTP client (TLS/SSL/none) or PHP mail(), editable templates with live preview and test send, email log with resend, SMTP connection test, queueing, hidden copy, branded layout.
- **Staff:** "Forgot password" by email, invitations when creating staff, optional password-changed notice.
- **Languages:** add languages, switcher for visitors and admin, browser detection, translation editor with progress, import/export packs, edit any English wording.
- **Module manager:** search, filters, details (permissions, tasks, jobs, templates, dependencies), per-module database updates.
- **Updates:** a banner and one-click "Finish update" apply migrations, new permissions and cache clearing after uploading new files.
- Logs are now purged automatically every night.
- Single-folder layout (see 1.0.0).

## 1.0.0 - Phase 1
- Core: autoloader, container, config, router with middleware, query builder, models, views, translator, validator, encryption, events.
- Installer, admin shell (dark mode, toasts, bulk actions, shortcuts).
- Users, roles, per-user permission overrides, 2FA (TOTP), login history, log out everywhere.
- Settings with history and encrypted secrets; error and activity logs with CSV export.
- Module manager with safe mode and recovery URL; CLI (`php ap`).
- Single-folder layout by default: works in the domain's root folder, no public_html required; `application` can optionally move above it.
- 24 automated tests.

Deferred: see README "Known limits".
