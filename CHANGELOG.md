# Changelog

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
