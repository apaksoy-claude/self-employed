# Changelog

## 1.47.0 - Help Centre, getting-started tour and manual
Update package: install from System > Updates. No database changes.
- New page: Money > Help Centre. An article for every part of Money in plain words, with the path to each page, numbered steps and tips: getting started, Business Setup, demo data, the dashboard, invoices, payments, expenses, the Receipt Inbox, bank and email import, clients and suppliers, categories and streams, equipment, vehicles and mileage, working from home, stock, the Tax Estimate, VAT, CIS, reports, custom fields, settings, users and access, updates and backups, and questions and answers. Topics are a list on the left that narrows as you type, and each article opens its page.
- The admin's top-bar search finds help articles too.
- Getting-started tour: the first time someone signs in, the dashboard walks them through each part of Money in ten short steps, with Back, Next and Skip. It is remembered for each person, can be shown again from the Help Centre, and can be switched off in Settings > Money > Getting Started.
- New manual: documentation/index.html, a single page to open in any browser or print, with installing, updating, tasks that run by themselves, privacy and security, every Help Centre article, the words used, and what is new in each version. It is built from the same articles as the Help Centre (php tools/build-docs.php), so the two always agree.
- Settings section names are now in Title Case, and the Invoice PDF Look tips point to the right place for your business details.
- Tests: Money 67, core 65.

## 1.46.0 - Demo data for trials
Update package: install from System > Updates, then press "Finish update" (adds one small table).
- New page: Money > Demo Data. Add about a year of made-up records to try Money out, made for the kind of work you choose: clients and suppliers, invoices (paid, part paid and still open) with payments, or weekly takings for drivers, sellers and salons, five to eight expenses a month across your own categories, a vehicle with a mileage log, a laptop as equipment, and stock items for sellers when Stock is on.
- Made-up names end in "(Demo)" and demo invoices are numbered DEMO-0001 and on, so your own invoice numbers carry on as if they were not there.
- Delete All Demo Data takes away exactly what was added, with one button on the Demo Data page or on the dashboard banner shown while demo data is in use. A demo client, supplier, category or stream that one of your own records now uses is kept.
- The installer's Your Business step can add demo data straight away.
- Tests: Money 66, core 65.

## 1.45.0 - Email import from any provider
Update package: install from System > Updates. No database changes.
- Email Import now works with any mailbox that offers IMAP, not just Gmail. Settings > Email Import has buttons that fill in the server, port, security and bin folder for Gmail, Yahoo, iCloud, AOL, Zoho, Fastmail and IONOS, or type in any other server.
- Both kinds of secure connection: SSL/TLS (usually port 993) and STARTTLS (usually port 143).
- The tips say where each provider makes an app password. Outlook, Hotmail and Microsoft 365 no longer let other apps sign in with a password, so the tips explain forwarding receipts to another mailbox instead.
- If the bin folder you name is not found, the folder the server marks as its bin is used.
- Clearer messages when the server cannot be reached or refuses the sign-in.
- All wording now speaks of your mailbox rather than Gmail.
- Tests: Money 65, core 65.

## 1.44.0 - Bank statement import
Update package: install from System > Updates, then press "Finish update" (adds tables for bank layouts and imports).
- New page: Money > Bank Import. Load a statement downloaded from online banking: a CSV from any bank, or an OFX or QFX file. Say which account it is for, so the same account is always matched.
- CSV columns are found by their names (date, description, amount, or paid in and paid out), even with lines above them. When a bank uses other names, choose the columns and the date style once and save the layout: statements from that bank are read by themselves after that.
- Review every line before anything is added. Money out becomes an expense and money in a payment, with the title and category to change. Lines already added from an earlier statement are marked Already Added and can never be added again, so overlapping statements are safe. A line with the same amount as an expense or payment you typed in yourself a few days either side is marked Check and left unticked (Settings > Money > Bank Import sets how many days). Money in that matches what is left on an open invoice is counted against that invoice.
- Rules, in rows you can reorder: when a description contains your words, the line gets that category, stream and title. The first rule that matches wins. Rules save in the background.
- Set the category of all ticked lines at once. Long statements are sent as one list so nothing is lost.
- Recent Imports: take back an import that went wrong, moving what it added to the bin.
- With VAT registered, imported lines get your starting VAT choices.
- Tests: Money 64, core 65.

## 1.43.0 - Stock for sellers
Update package: install from System > Updates, then press "Finish update" (adds the stock tables).
- Settings > Money > Features: Stock, with the starting warning level for new items. Business Setup and the installer ask about it too.
- New page: Money > Stock. Your items with what is left, the cost each (the average of what you paid), the value, the sell price and the margin, marked In Stock, Low or Out Of Stock. Filters are remembered; ticked items can be archived, brought back, deleted or given a warning level. Each item shows every movement.
- New items start with how many you have now and what each cost. Add Or Take Out handles damaged, used, returned or found stock, with a reason.
- Expenses have Stock Bought lines (rows to add, remove and reorder): each item comes in at its cost each, or a share of the amount by quantity, without VAT you reclaim.
- Invoice lines can come from stock: picking an item fills in its name, price and unit, and the quantity goes out while the invoice is sent (not as a draft or cancelled). Payments with no invoice have Sold From Stock lines. Deleting a payment or an expense puts its stock back.
- Stock Count: type what is on the shelf for a day; differences are saved as a count, in the background.
- With traditional accounting, the change in stock value over each tax year changes your cost of goods (a rise lowers costs), in every profit figure. On the cash basis nothing changes, as goods count when you pay for them.
- The dashboard's Needs A Look card lists items that are low or out of stock.
- Repeating rows in forms now also work inside pop-up forms.
- Tests: Money 63, core 65.

## 1.42.0 - CIS for builders
Update package: install from System > Updates, then press "Finish update" (adds CIS columns and a table for monthly notes).
- New page: Settings > CIS. Say contractors pay you under the Construction Industry Scheme and at what rate (20%, 30% or gross payment status), and whether you pay subcontractors under CIS, with your employer's reference for their statements. Business Setup and the installer ask about CIS too.
- Clients and Suppliers: mark a client as a CIS contractor (with the rate they take off) or a supplier as a subcontractor you pay (with their UTR or verification number).
- Invoices: choose the CIS rate (picked for you from the client), and mark each line as labour or materials. The deduction is worked out on the labour after any discount, never on materials or VAT, and shown on the invoice page, the PDF and the pay page. The client is asked for the total less the deduction, and paying that settles the invoice. Statements to clients show the same.
- Payments with no invoice have CIS Taken Off and Materials boxes, filled from the contractor's statement.
- Your income now counts the full amount before CIS, and the deductions come off your tax on the Tax Estimate as tax already paid, with "Left To Pay After CIS" or the refund HMRC owes you, payments on account worked out after them, and the same on the dashboard and in the accountant pack.
- Paying subcontractors: expenses have CIS You Took Off and Materials boxes; the cost is what they were paid plus the deduction.
- New page: Money > CIS. The tax months of the year (6th to 5th) down the left. For each: what each contractor paid you and took off, with a check of the rate and a tick when their statement arrives; what you paid each subcontractor and took off, a payment and deduction statement to print for each, what to pay HMRC by the 22nd, and your monthly return to mark as filed by the 19th. A CSV of the whole year. The dashboard reminds you of each return from the first month you pay a subcontractor.
- VAT: the construction reverse charge is now a VAT choice for those under CIS. The invoice adds no VAT and says "Reverse charge: customer to account for VAT to HMRC"; reverse charge costs add the VAT to box 1 and box 4 of your return. A client paying under CIS pays the VAT in full, as it is never part of the deduction.
- Report downloads include CIS columns when you use CIS.
- Tests: Money 62, core 65.

## 1.41.0 - VAT
Update package: install from System > Updates, then press "Finish update" (adds VAT columns and a table for filed returns).
- New page: Settings > VAT. Say you are registered, your VAT number (printed on invoices), the date you registered from (records before it have no VAT), your VAT periods (three quarter patterns or monthly), your scheme (standard or Flat Rate, with your flat rate, the limited cost trader rate and 1% off in the first year), cash accounting, and the starting VAT for new invoice lines and new expenses. Only the settings that apply are shown.
- Invoices: each line has a VAT rate (standard, reduced, zero, exempt or outside the scope). The invoice works out the VAT, takes a discount's share off it, and shows the rate on each line, the VAT total and your VAT number on the invoice page, the PDF and the client's pay page. Changing the bill date works the VAT out again.
- Expenses and payments with no invoice have a VAT rate and amount. Leave the amount empty and it is worked out from the total; type it when the receipt shows a different figure. Ticked expenses can have their VAT set together.
- Profit now leaves VAT out everywhere: the dashboard, cards, tax estimate, reports and the accountant pack. On the Flat Rate Scheme takings count less your flat rate, and VAT on costs is not reclaimed except on equipment of £2,000 or more. Equipment is counted without VAT you reclaim.
- New page: Money > VAT. Your VAT periods are a list on the left, marked Now, To File, Late or Filed. Each shows the nine boxes worked out from your records (by invoice date, or by money in and out with cash accounting), what you pay HMRC or HMRC owes you, the due date, every sale and cost behind the figures, a CSV download and a print view. Mark a return as filed in the background with the date and HMRC reference; a copy of the figures is kept, and the page points out any records changed after filing.
- Not registered: Money > VAT watches your last 12 months' takings against the registration threshold, with a warning point you choose (80% to start), and explains when you must register.
- The dashboard's Needs A Look card says when a VAT return is due or late, or when your takings are near the threshold.
- Report downloads include VAT columns when you are registered.
- Settings now have a date box type that opens the styled date picker.
- Tests: Money 61, core 65.

## 1.40.0 - Equipment and capital allowances
Update package: install from System > Updates, then press "Finish update" (adds the equipment table).
- New page: Money > Equipment. A register of tools, computers, machinery, furniture, vans, motorcycles and cars bought to keep. Every expense filed to "Equipment and vehicles bought" appears there by itself and follows its expense, so nothing is claimed twice. You can also add things by hand, with the date, cost, business share, whether it was new, CO2 for cars, the vehicle it belongs to, and when it was sold and for how much.
- Settings > Money > Your Tax: How Your Accounts Are Kept. Cash basis (the starting choice for most sole traders) claims equipment in full in the year it is bought, by its business share, and a sale later counts as income. Traditional accounting uses the Annual Investment Allowance up to the yearly limit, the 40% first year allowance on new equipment above it (from 1 January 2026), and the main and special rate pools. Cars always get capital allowances: by CO2 into the main or special rate pool, 100% for new zero-emission cars, and their own pool when used privately. Sales come off the pools, with balancing allowances and charges.
- The claims count everywhere profit does: the dashboard, quarter and month cards, the tax estimate, reports and the accountant pack (as "Equipment Claims").
- The Equipment page shows each tax year's claim, how it adds up, and what is left in each pool, with filters that are remembered, ticked rows that can change kind, and edit and delete on every row.
- A car or van linked to a vehicle in the mileage log counts as having had allowances, so it can no longer use the mileage rate (HMRC's rule), and the vehicle comparison uses the real allowance.
- Settings > Tax Rules has a new Equipment and Capital Allowances card for each year: the Annual Investment Allowance, the main and special pool rates (main pool 14% from 2026-27, 18% before), first year allowances, the car CO2 limit and the small pool limit.
- Tests: Money 60, core 65.

## 1.39.0 - Tax rules for each tax year
Update package: install from System > Updates, then press "Finish update" (adds one small table; any rates you changed before are kept for this tax year).
- New page: Settings > Tax Rules. Each tax year has its own rates: Income Tax for England, Wales and Northern Ireland, Scottish bands, Class 4 and Class 2 National Insurance, the trading allowance, payments on account, the Making Tax Digital limit, mileage rates and the working-from-home flat rate. 2024-25, 2025-26 and 2026-27 are built in. The years are a list on the left; lists such as the Scottish bands are rows you can add, remove and drag. Save works in the background. Add The Next Year copies the newest year to check, and Back To Built-In undoes your changes. A year with no rates of its own uses the year before and says so.
- Settings > Money > Your Tax: where you pay Income Tax (England, Wales Or Northern Ireland, or Scotland), expenses or the £1,000 trading allowance (or whichever leaves the lower profit), and voluntary Class 2 when profits are below the small profits threshold.
- Settings > Money > Working From Home: claim the business share of your bills, or HMRC's flat rate a month. With the flat rate, set the hours you work at home on the Tax Estimate page; bills filed to "Use of home" are then left out because the flat rate covers them.
- Tax Estimate: Scottish rates, other income (wages or a pension) and the tax already taken off it, the trading allowance compared with your costs, When To Pay (the January balance with payments on account, and July), a note on Class 2 and your State Pension, and whether Making Tax Digital is likely to apply (from your income two years before). Your figures save in the background and the estimate updates without reloading.
- The dashboard, reports, accountant pack and mileage all use the rates of the right tax year.
- Tests: Money 57, core 65.

## 1.38.0 - A clean start for every business
Update package: install from System > Updates. No database changes.
- The import from another invoicing program is removed, with its page, menu entry and search words. Anything already imported stays.
- Every example, placeholder, search word and email sample now uses neutral names, such as "Northwind Ltd" for a client and "Your Business" for you.
- Email Import's Add Example Rules now gives four rules that suit any business: parking receipts, supplier invoices with a PDF, software subscriptions and fuel receipts. Each is meant to be changed to suit.
- The changelog before 1.37.0 is now a short summary of what the app does.
- Tests: Money 53, core 65.

## 1.37.0 - Set up for your kind of work
Update package: install from System > Updates, then press "Finish update".
- New installs: the installer has a Your Business step after the admin account. Choose Ready For My Kind Of Work or Fresh Start. Pick one or more kinds of work (Driver Or Courier, Tradesperson Or Builder, Freelancer Or Creative, Retail Or Online Seller, Consultant Or Professional, Hair and Beauty, Cleaning and Home Services, Something Else), say whether you use a vehicle for work and whether you work from home, and type your business name and email.
- Before anything is added it shows exactly what will be added: expense categories with the box each goes in on the tax return, income streams, ways of getting paid and ways of paying, each with a tick box to leave it out. The dashboard cards can be set to suit the work (for example Fuel This Month for drivers, Materials This Tax Year for trades).
- New page: Money > Business Setup does the same at any time, for sites already in use or after a Fresh Start. It only adds: anything with the same name is left exactly as it is, and running it again adds nothing twice. It works in the background without reloading, and remembers which kinds of work were set up. Found with the top-bar search ("setup trade driver freelancer seller").
- A banner on the dashboard offers Business Setup until it has been run, with Not Now to hide it for good.
- Settings > Money > Features: Vehicles and Mileage can be switched off. Vehicles and the Mileage Log leave the menu, and the vehicle box leaves expenses, the expense filter, scheduled reports and custom fields. Nothing is deleted, and an expense that already has a vehicle keeps its box.
- New sites start with an empty business name (the site name is used until one is typed) and a plain "Paid by" list.
- For module authors: a module can add its own installer step ('install_step' in module.php), and a menu item can hide while a feature is off ('when' => fn(): bool).
- The installer's Options page now starts with the modules that are on by default ticked.
- Tests: Money 57, core 65.

## 1.36.5 and earlier - The foundation
Everything the app could do before the Business Setup, in short:
- Money: invoices with items, discounts, repeating invoices and automatic numbers; payments, with part payments and a searchable invoice picker; expenses with receipts, business share, repeating costs and bulk changes; clients and suppliers with statements and a private client portal; income streams; categories filed to the boxes of the Self Assessment self-employment pages.
- Pay online: invoices emailed with a private pay page for card (Stripe, with Apple Pay and Google Pay), PayPal or bank transfer, test modes, receipts and overdue reminders.
- Vehicles and a mileage log, with the HMRC mileage rate or actual costs compared side by side.
- Tax estimate (Income Tax and Class 4 National Insurance), quarterly update cards, an arrangeable dashboard with ready-made and home-made cards and ten card styles.
- Reports, CSV downloads, an accountant pack (by download or email, also once a year by itself), scheduled reports emailed as PDFs, a weekly digest and reminders in the notification bell.
- Email Import rules that turn receipt and invoice emails into expenses, a Receipt Inbox for phone photos, and custom fields on every form.
- Records only: nothing is ever sent to HMRC.
- The framework underneath: installer and one-click updates with roll back, admin with search, settings and system menus, roles and access, two-step sign-in, email sending and templates, scheduled tasks, health check and monitoring, logs, media library, pages, menus, footer builder, maps, newsletter sign-up, languages, SEO, cookie notice, spam protection and an installable app.
