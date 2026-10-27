<?php
declare(strict_types=1);

use AP\Core\Middleware\Csrf;
use AP\Core\Router;
use AP\Modules\Money\Controllers\AssetController;
use AP\Modules\Money\Controllers\ButtonController;
use AP\Modules\Money\Controllers\CardStyleController;
use AP\Modules\Money\Controllers\CategoryController;
use AP\Modules\Money\Controllers\ContactController;
use AP\Modules\Money\Controllers\CustomFieldController;
use AP\Modules\Money\Controllers\StreamController;
use AP\Modules\Money\Controllers\DashboardController;
use AP\Modules\Money\Controllers\ExpenseController;
use AP\Modules\Money\Controllers\FileController;
use AP\Modules\Money\Controllers\GmailController;
use AP\Modules\Money\Controllers\IncomeController;
use AP\Modules\Money\Controllers\InvoiceController;
use AP\Modules\Money\Controllers\OverviewController;
use AP\Modules\Money\Controllers\PayController;
use AP\Modules\Money\Controllers\PortalController;
use AP\Modules\Money\Controllers\ReceiptController;
use AP\Modules\Money\Controllers\ReportController;
use AP\Modules\Money\Controllers\ScheduledReportController;
use AP\Modules\Money\Controllers\TaxController;
use AP\Modules\Money\Controllers\TaxRulesController;
use AP\Modules\Money\Controllers\SetupController;
use AP\Modules\Money\Controllers\TripController;
use AP\Modules\Money\Controllers\VehicleController;

return static function (Router $r): void {
    // The page a client opens from the invoice email. The private link is the key; nothing else on the site is reachable from it.
    // Stripe posts here on its own, with no form token, and proves who it is with a signature.
    Csrf::except('/pay/stripe-webhook');
    $r->post('/pay/stripe-webhook', [PayController::class, 'stripeWebhook'], 'pay.webhook', ['throttle:120,60']);
    $r->get('/pay/client/{token:[a-f0-9]{32}}', [PortalController::class, 'show'], 'pay.portal', ['throttle:60,60']);
    $r->get('/pay/client/{token:[a-f0-9]{32}}/statement', [PortalController::class, 'statement'], 'pay.portal.statement', ['throttle:60,60']);
    $r->get('/pay/{token:[a-f0-9]{32}}', [PayController::class, 'show'], 'pay.show', ['throttle:60,60']);
    $r->get('/pay/{token:[a-f0-9]{32}}/invoice', [PayController::class, 'document'], 'pay.document', ['throttle:60,60']);
    $r->post('/pay/{token:[a-f0-9]{32}}/start/{provider:[a-z]+}', [PayController::class, 'start'], 'pay.start', ['throttle:20,60']);
    $r->get('/pay/{token:[a-f0-9]{32}}/done/{provider:[a-z]+}', [PayController::class, 'done'], 'pay.done', ['throttle:30,60']);
    $r->group(['prefix' => admin_path() . '/money/tax-rules', 'middleware' => ['auth', 'can:settings.edit'], 'as' => 'admin.money.taxrules.'], static function (Router $r): void {
        $r->get('/', [TaxRulesController::class, 'index'], 'index');
        $r->post('/save', [TaxRulesController::class, 'save'], 'save');
        $r->post('/copy', [TaxRulesController::class, 'copy'], 'copy');
        $r->post('/restore', [TaxRulesController::class, 'restore'], 'restore');
    });
    $r->group(['prefix' => admin_path() . '/appearance', 'middleware' => ['auth', 'can:settings.edit'], 'as' => 'admin.appearance.'], static function (Router $r): void {
        $r->get('/buttons', [ButtonController::class, 'index'], 'buttons');
        $r->post('/buttons', [ButtonController::class, 'save'], 'buttons.save');
        $r->get('/cards', [CardStyleController::class, 'index'], 'cards');
        $r->post('/cards', [CardStyleController::class, 'save'], 'cards.save');
    });
    $r->group(['prefix' => admin_path() . '/money', 'middleware' => ['auth', 'can:money.view'], 'as' => 'admin.money.'], static function (Router $r): void {
        $r->get('/', [OverviewController::class, 'index'], 'index');
        $r->get('/tax', [TaxController::class, 'index'], 'tax');
        $r->get('/reports', [ReportController::class, 'index'], 'reports');
        $r->get('/reports/email', [ReportController::class, 'emailForm'], 'reports.email');
        $r->get('/reports/export/{what:[a-z]+}', [ReportController::class, 'export'], 'reports.export');
        $r->get('/receipts', [ReceiptController::class, 'index'], 'receipts');
        $r->get('/equipment', [AssetController::class, 'index'], 'equipment');
        $r->get('/files/{id:\d+}', [FileController::class, 'show'], 'file');

        $r->get('/expenses', [ExpenseController::class, 'index'], 'expenses');
        $r->get('/expenses/new', [ExpenseController::class, 'form'], 'expenses.new');
        $r->get('/expenses/{id:\d+}', [ExpenseController::class, 'form'], 'expenses.edit');

        $r->get('/income', [IncomeController::class, 'index'], 'income');
        $r->get('/income/new', [IncomeController::class, 'form'], 'income.new');
        $r->get('/income/{id:\d+}', [IncomeController::class, 'form'], 'income.edit');

        $r->get('/invoices', [InvoiceController::class, 'index'], 'invoices');
        $r->get('/invoices/new', [InvoiceController::class, 'form'], 'invoices.new');
        $r->get('/invoices/{id:\d+}', [InvoiceController::class, 'show'], 'invoices.show');
        $r->get('/invoices/{id:\d+}/pdf', [InvoiceController::class, 'document'], 'invoices.pdf');
        $r->get('/invoices/{id:\d+}/edit', [InvoiceController::class, 'form'], 'invoices.edit');
        $r->get('/invoices/{id:\d+}/items/new', [InvoiceController::class, 'itemForm'], 'invoices.items.new');
        $r->get('/invoices/{id:\d+}/items/{item:\d+}', [InvoiceController::class, 'itemForm'], 'invoices.items.edit');
        $r->get('/invoices/{id:\d+}/payments/new', [InvoiceController::class, 'paymentForm'], 'invoices.payments.new');

        $r->get('/scheduled-reports', [ScheduledReportController::class, 'index'], 'scheduled');
        $r->get('/scheduled-reports/new', [ScheduledReportController::class, 'form'], 'scheduled.new');
        $r->get('/scheduled-reports/{id:\d+}/edit', [ScheduledReportController::class, 'form'], 'scheduled.edit');
        $r->get('/scheduled-reports/{id:\d+}/download', [ScheduledReportController::class, 'download'], 'scheduled.download');

        $r->get('/vehicles', [VehicleController::class, 'index'], 'vehicles');
        $r->get('/vehicles/new', [VehicleController::class, 'form'], 'vehicles.new');
        $r->get('/vehicles/{id:\d+}', [VehicleController::class, 'show'], 'vehicles.show');
        $r->get('/vehicles/{id:\d+}/edit', [VehicleController::class, 'form'], 'vehicles.edit');
        $r->get('/trips', [TripController::class, 'index'], 'trips');
        $r->get('/trips/new', [TripController::class, 'form'], 'trips.new');
        $r->get('/trips/{id:\d+}', [TripController::class, 'form'], 'trips.edit');

        $r->get('/categories', [CategoryController::class, 'index'], 'categories');
        $r->get('/contacts', [ContactController::class, 'index'], 'contacts');
        $r->get('/contacts/new', [ContactController::class, 'form'], 'contacts.new');
        $r->get('/contacts/{id:\d+}', [ContactController::class, 'form'], 'contacts.edit');
        $r->get('/contacts/statements', [ContactController::class, 'statementsList'], 'contacts.statements');
        $r->get('/contacts/{id:\d+}/statement', [ContactController::class, 'statement'], 'contacts.statement');
        $r->get('/contacts/{id:\d+}/portal', [ContactController::class, 'portal'], 'contacts.portal');
        $r->get('/streams', [StreamController::class, 'index'], 'streams');
        $r->get('/breakdown', [DashboardController::class, 'breakdown'], 'breakdown');

        $r->group(['middleware' => ['can:money.edit']], static function (Router $r): void {
            $r->post('/invoices/save', [InvoiceController::class, 'save'], 'invoices.save');
            $r->post('/invoices/{id:\d+}/delete', [InvoiceController::class, 'delete'], 'invoices.delete');
            $r->get('/invoices/{id:\d+}/email', [InvoiceController::class, 'emailForm'], 'invoices.email');
            $r->post('/invoices/{id:\d+}/email', [InvoiceController::class, 'emailSend'], 'invoices.email.send');
            $r->post('/invoices/{id:\d+}/status', [InvoiceController::class, 'status'], 'invoices.status');
            $r->post('/invoices/{id:\d+}/discount', [InvoiceController::class, 'discount'], 'invoices.discount');
            $r->post('/invoices/{id:\d+}/items/save', [InvoiceController::class, 'itemSave'], 'invoices.items.save');
            $r->post('/invoices/{id:\d+}/items/order', [InvoiceController::class, 'itemOrder'], 'invoices.items.order');
            $r->post('/invoices/{id:\d+}/items/{item:\d+}/delete', [InvoiceController::class, 'itemDelete'], 'invoices.items.delete');
            $r->post('/invoices/{id:\d+}/payments/save', [InvoiceController::class, 'paymentSave'], 'invoices.payments.save');
            $r->get('/dashboard/widgets/new', [DashboardController::class, 'form'], 'dashboard.widgets.new');
            $r->get('/dashboard/widgets/{id:[a-z0-9_]+}/edit', [DashboardController::class, 'form'], 'dashboard.widgets.edit');
            $r->post('/dashboard/widgets/save', [DashboardController::class, 'save'], 'dashboard.widgets.save');
            $r->post('/dashboard/layout', [DashboardController::class, 'layout'], 'dashboard.layout');
            $r->get('/dashboard/cards/{id:[a-z0-9_]+}/style', [CardStyleController::class, 'cardForm'], 'dashboard.cards.style');
            $r->post('/dashboard/cards/style', [CardStyleController::class, 'cardSave'], 'dashboard.cards.style.save');
            $r->post('/dashboard/reset', [DashboardController::class, 'reset'], 'dashboard.reset');




            $r->post('/tax/figures', [TaxController::class, 'savePersonal'], 'tax.figures');
            $r->get('/equipment/new', [AssetController::class, 'form'], 'equipment.new');
            $r->get('/equipment/{id:\d+}', [AssetController::class, 'form'], 'equipment.edit');
            $r->post('/equipment/save', [AssetController::class, 'save'], 'equipment.save');
            $r->post('/equipment/bulk', [AssetController::class, 'bulk'], 'equipment.bulk');
            $r->post('/equipment/{id:\d+}/delete', [AssetController::class, 'delete'], 'equipment.delete');
            $r->get('/setup', [SetupController::class, 'index'], 'setup');
            $r->post('/setup/apply', [SetupController::class, 'apply'], 'setup.apply');
            $r->post('/setup/dismiss', [SetupController::class, 'dismiss'], 'setup.dismiss');
            $r->get('/fields', [CustomFieldController::class, 'index'], 'fields');
            $r->post('/fields/save', [CustomFieldController::class, 'save'], 'fields.save');
            $r->get('/email-import', [GmailController::class, 'index'], 'gmail');
            $r->post('/email-import/check', [GmailController::class, 'check'], 'gmail.check');
            $r->post('/email-import/fetch', [GmailController::class, 'fetch'], 'gmail.fetch');
            $r->get('/email-import/rules/new', [GmailController::class, 'ruleForm'], 'gmail.rule.new');
            $r->get('/email-import/rules/{id:[a-f0-9]{8}}/edit', [GmailController::class, 'ruleForm'], 'gmail.rule.edit');
            $r->post('/email-import/rules/save', [GmailController::class, 'ruleSave'], 'gmail.rule.save');
            $r->post('/email-import/rules/toggle', [GmailController::class, 'ruleToggle'], 'gmail.rule.toggle');
            $r->post('/email-import/rules/delete', [GmailController::class, 'ruleDelete'], 'gmail.rule.delete');
            $r->post('/email-import/rules/order', [GmailController::class, 'ruleOrder'], 'gmail.rule.order');
            $r->post('/email-import/rules/starters', [GmailController::class, 'ruleStarters'], 'gmail.rule.starters');
            $r->post('/email-import/clear', [GmailController::class, 'mailClear'], 'gmail.clear');
            $r->post('/email-import/test', [GmailController::class, 'test'], 'gmail.test');



            $r->post('/note', [OverviewController::class, 'note'], 'note');
            $r->post('/quarters/sent', [OverviewController::class, 'sent'], 'quarters.sent');
            $r->post('/expenses/bulk', [ExpenseController::class, 'bulk'], 'expenses.bulk');
            $r->post('/income/bulk', [IncomeController::class, 'bulk'], 'income.bulk');
            $r->post('/trips/bulk', [TripController::class, 'bulk'], 'trips.bulk');
            $r->post('/invoices/bulk', [InvoiceController::class, 'bulk'], 'invoices.bulk');
            $r->get('/contacts/{id:\d+}/statement/email', [ContactController::class, 'statementEmailForm'], 'contacts.statement.email');
            $r->post('/contacts/{id:\d+}/statement/email', [ContactController::class, 'statementEmailSend'], 'contacts.statement.email.send');
            $r->post('/contacts/statements/send', [ContactController::class, 'statementsSend'], 'contacts.statements.send');
            $r->post('/reports/digest', [ReportController::class, 'digestNow'], 'reports.digest');
            $r->post('/reports/email', [ReportController::class, 'emailSend'], 'reports.email.send');
            $r->post('/contacts/bulk', [ContactController::class, 'bulk'], 'contacts.bulk');
            $r->post('/receipts/upload', [ReceiptController::class, 'upload'], 'receipts.upload');
            $r->post('/receipts/{id:\d+}/sort', [ReceiptController::class, 'sort'], 'receipts.sort');
            $r->post('/receipts/{id:\d+}/discard', [ReceiptController::class, 'discard'], 'receipts.discard');
            $r->post('/expenses/save', [ExpenseController::class, 'save'], 'expenses.save');
            $r->post('/expenses/{id:\d+}/delete', [ExpenseController::class, 'delete'], 'expenses.delete');
            $r->post('/income/save', [IncomeController::class, 'save'], 'income.save');
            $r->post('/income/{id:\d+}/delete', [IncomeController::class, 'delete'], 'income.delete');
            $r->post('/scheduled-reports/save', [ScheduledReportController::class, 'save'], 'scheduled.save');
            $r->post('/scheduled-reports/{id:\d+}/send', [ScheduledReportController::class, 'sendNow'], 'scheduled.send');
            $r->post('/scheduled-reports/{id:\d+}/toggle', [ScheduledReportController::class, 'toggle'], 'scheduled.toggle');
            $r->post('/scheduled-reports/{id:\d+}/delete', [ScheduledReportController::class, 'delete'], 'scheduled.delete');
            $r->post('/vehicles/save', [VehicleController::class, 'save'], 'vehicles.save');
            $r->post('/vehicles/{id:\d+}/delete', [VehicleController::class, 'delete'], 'vehicles.delete');
            $r->post('/vehicles/{id:\d+}/link', [VehicleController::class, 'link'], 'vehicles.link');
            $r->post('/trips/save', [TripController::class, 'save'], 'trips.save');
            $r->post('/trips/{id:\d+}/delete', [TripController::class, 'delete'], 'trips.delete');
            $r->post('/categories/save', [CategoryController::class, 'save'], 'categories.save');
            $r->post('/contacts/save', [ContactController::class, 'save'], 'contacts.save');
            $r->post('/contacts/{id:\d+}/delete', [ContactController::class, 'delete'], 'contacts.delete');
            $r->post('/streams/save', [StreamController::class, 'save'], 'streams.save');
            $r->post('/streams/{id:\d+}/delete', [StreamController::class, 'delete'], 'streams.delete');
            $r->post('/categories/{id:\d+}/delete', [CategoryController::class, 'delete'], 'categories.delete');
            $r->post('/files/{id:\d+}/delete', [FileController::class, 'delete'], 'file.delete');
        });
    });
};
