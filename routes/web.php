<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Guest\HomeController;
use App\Http\Controllers\Guest\LabelStudioController;
use App\Http\Controllers\Printing\LabelPdfController;
use App\Http\Livewire\Aggregation\AggregationIndex;
use App\Http\Livewire\Aggregation\AggregationScanner;
use App\Http\Livewire\Catalog\ProductGroupIndex;
use App\Http\Livewire\Catalog\ProductIndex;
use App\Http\Livewire\Marking\ImportBatchIndex;
use App\Http\Livewire\Marking\MarkingCodeIndex;
use App\Http\Livewire\Offers\OfferIndex;
use App\Http\Livewire\Printing\LabelTemplateIndex;
use App\Http\Livewire\Printing\PrinterIndex;
use App\Http\Livewire\Settings\UserIndex;
use Illuminate\Support\Facades\Route;

/*
 * Three areas:
 *   /               home page (marketing, SEO)
 *   /label-design   public label studio — any spreadsheet, any design, nothing stored
 *   /aslbelgi   registered users (role `user`), login required — each sees only their own Asl Belgisi data
 *   /admin      super admin (role `admin`), sees all clients — label templates, users, reference data
 */

// Public label studio
Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/robots.txt', [HomeController::class, 'robots'])->name('robots');
Route::get('/sitemap.xml', [HomeController::class, 'sitemap'])->name('sitemap');
// for AI agents (Claude, ChatGPT, Perplexity …): plain Markdown summary of the site, ?lang=ru|zh
Route::get('/llms.txt', [HomeController::class, 'llms'])->name('llms');
Route::get('/llms-full.txt', [HomeController::class, 'llmsFull'])->name('llms.full');
// public guides (the same Markdown guides as inside the client area)
Route::get('/guide/{page?}', [HomeController::class, 'guide'])->name('guide');
// landing page for Chinese manufacturers / exporters marking goods for Uzbekistan
Route::view('/uzbekistan-marking', 'china')->name('china');
Route::view('/contact', 'contact')->name('contact');
Route::get('/asl-belgisi', [HomeController::class, 'aslbelgi'])->name('aslbelgi');
Route::get('/marketplace', [HomeController::class, 'marketplace'])->name('marketplace');
Route::get('/faq', [HomeController::class, 'faqIndex'])->name('faq');
Route::get('/drivers', [\App\Http\Controllers\Guest\DriverController::class, 'index'])->name('drivers');
Route::get('/drivers/{driver:slug}', [\App\Http\Controllers\Guest\DriverController::class, 'show'])->name('drivers.show');
Route::get('/news', [\App\Http\Controllers\Guest\NewsController::class, 'index'])->name('news');
Route::get('/news/{post:slug}', [\App\Http\Controllers\Guest\NewsController::class, 'show'])->name('news.show');
// public page of a Marketplace item
Route::get('/marketplace/{offer:slug}', [\App\Http\Controllers\Guest\MarketplaceController::class, 'show'])->name('marketplace.item');
Route::get('/label-image/{w}x{h}.svg', [\App\Http\Controllers\Guest\LabelImageController::class, 'show'])->whereNumber(['w', 'h'])->name('label-image');
Route::post('/telegram/webhook/{secret}', function (string $secret, \Illuminate\Http\Request $request, \App\Domain\Telegram\BotHandler $bot) {
    abort_unless(filled(config('telegram.webhook_secret')) && hash_equals(config('telegram.webhook_secret'), $secret), 404);
    $bot->handle($request->all());

    return response()->json(['ok' => true]);
})->name('telegram.webhook'); // CSRF exemption: bootstrap/app.php (preventRequestForgery except)
Route::post('/offer-request', [\App\Http\Controllers\Guest\OfferRequestController::class, 'store'])->middleware('throttle:6,1')->name('offer-request');
Route::prefix('label-design')->group(function () {
    Route::get('/', [LabelStudioController::class, 'index'])->name('studio');
    Route::post('/parse', [LabelStudioController::class, 'parse'])->middleware('throttle:20,1')->name('studio.parse');
    Route::get('/barcode.png', [LabelStudioController::class, 'barcode'])->middleware('throttle:300,1')->name('studio.barcode');
    Route::post('/generate', [LabelStudioController::class, 'generate'])->middleware('throttle:6,1')->name('studio.generate');
    Route::post('/print', [LabelStudioController::class, 'print'])->middleware('throttle:10,1')->name('studio.print');
});

// Interface language (uz / ru / zh)
Route::get('/lang/{locale}', function (string $locale) {
    abort_unless(isset(\App\Http\Middleware\SetLocale::LOCALES[$locale]), 404);
    session(['locale' => $locale]);
    auth()->user()?->update(['locale' => $locale]);

    return back();
})->name('locale');

// Development only: switch between the super admin and a demo client (see config app.dev_role_switch)
Route::get('/dev/as/{role}', function (string $role) {
    abort_unless(config('app.dev_role_switch') && app()->isLocal(), 404);

    $user = $role === 'admin'
        ? \App\Models\User::where('role', 'admin')->where('is_active', true)->orderBy('id')->firstOrFail()
        : \App\Models\User::firstOrCreate(
            ['email' => 'demo-client@markor.local'],
            ['name' => 'Demo mijoz', 'password' => \Illuminate\Support\Str::random(32), 'role' => 'user', 'locale' => app()->getLocale(), 'is_active' => true]
        );

    auth()->login($user);
    session()->regenerate();

    return redirect()->route($user->isAdmin() ? 'admin.clients' : 'import-batches.index');
})->whereIn('role', ['admin', 'client'])->name('dev.as');

// Auth
Route::get('/login', [LoginController::class, 'showLogin'])->name('login');
Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:20,1')->name('login.post');
Route::post('/login/qr', [LoginController::class, 'loginByQr'])->middleware('throttle:10,1')->name('login.qr');
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
Route::get('/register', [RegisterController::class, 'show'])->name('register');
Route::post('/register', [RegisterController::class, 'store'])->middleware('throttle:5,1')->name('register.post');

// Clients: Asl Belgisi marking codes
Route::middleware('auth')->prefix('aslbelgi')->group(function () {
    // available before the company is registered
    Route::get('/company', \App\Http\Livewire\Account\CompanySetup::class)->middleware('client.only:admin.clients')->name('company.setup');
    Route::get('/profile', \App\Http\Livewire\Account\ProfilePage::class)->name('profile');
    Route::get('/help/{page?}', \App\Http\Livewire\Account\HelpPage::class)->name('help');
});

Route::middleware(['auth', 'company'])->prefix('aslbelgi')->group(function () {
    Route::get('/', ImportBatchIndex::class)->middleware('client.only:admin.clients')->name('import-batches.index');
    Route::get('/imports/{batch}/labels.pdf', [LabelPdfController::class, 'batch'])->name('import-batches.labels');
    Route::get('/imports/{batch}/parts', [LabelPdfController::class, 'parts'])->name('import-batches.parts');
    Route::get('/imports/{batch}/print', [LabelPdfController::class, 'print'])->name('import-batches.print');
    Route::get('/pdf-reader', \App\Http\Livewire\Marking\PdfCodeReader::class)->middleware('client.only:admin.clients')->name('import-batches.pdf-reader');
    Route::get('/codes', MarkingCodeIndex::class)->name('marking-codes.index');
    // users are a section of the company page now; the old address keeps working
    Route::get('/users', fn () => redirect(route('company.setup') . '#users'))->middleware('client.only:admin.clients')->name('company.users');
    Route::get('/balance', \App\Http\Livewire\Account\BalancePage::class)->middleware('client.only:admin.payments')->name('balance');
    Route::get('/products', ProductIndex::class)->name('products.index');
    Route::get('/products/{product}/label.pdf', [\App\Http\Controllers\Printing\ProductLabelController::class, 'pdf'])->name('products.label');
    Route::get('/products/{product}/label/print', [\App\Http\Controllers\Printing\ProductLabelController::class, 'print'])->name('products.label.print');
    Route::get('/offers', OfferIndex::class)->name('offers.index');
    Route::get('/templates', \App\Http\Livewire\Printing\TemplateCatalog::class)->middleware('client.only:label-templates.index')->name('templates.catalog');
    Route::get('/templates/mine', \App\Http\Livewire\Printing\MyTemplates::class)->middleware('client.only:label-templates.index')->name('templates.mine');
    Route::get('/templates/{template}/preview.pdf', [LabelPdfController::class, 'preview'])->name('templates.preview');
});

// Service owner
Route::middleware(['auth', 'can:admin'])->prefix('admin')->group(function () {
    Route::redirect('/', '/admin/companies')->name('admin');
    Route::get('/companies', \App\Http\Livewire\Admin\CompanyIndex::class)->name('admin.clients');
    Route::get('/companies/{company}', \App\Http\Livewire\Admin\CompanyShow::class)->name('admin.companies.show');
    Route::get('/batches', \App\Http\Livewire\Admin\BatchIndex::class)->name('admin.batches');
    Route::get('/storage', \App\Http\Livewire\Admin\StorageIndex::class)->name('admin.storage');
    Route::get('/system', \App\Http\Livewire\Admin\SystemPage::class)->name('admin.system');
    Route::get('/offers/{category:slug}', \App\Http\Livewire\Admin\OfferCategoryManager::class)->name('admin.offers');
    Route::get('/offers/{category:slug}/items/create', \App\Http\Livewire\Admin\OfferForm::class)->name('admin.offers.create');
    Route::get('/offers/{category:slug}/items/{offer}/edit', \App\Http\Livewire\Admin\OfferForm::class)->name('admin.offers.edit');
    Route::get('/offer-requests', \App\Http\Livewire\Admin\OfferRequestIndex::class)->name('admin.offer-requests');
    Route::get('/template-requests', \App\Http\Livewire\Admin\TemplateRequestIndex::class)->name('admin.template-requests');
    Route::get('/settings', \App\Http\Livewire\Admin\SettingsPage::class)->name('admin.settings');
    Route::get('/seo', \App\Http\Livewire\Admin\SeoPage::class)->name('admin.seo');
    Route::get('/banners', \App\Http\Livewire\Admin\BannerIndex::class)->name('admin.banners');
    Route::get('/banners/create', \App\Http\Livewire\Admin\BannerForm::class)->name('admin.banners.create');
    Route::get('/banners/{banner}/edit', \App\Http\Livewire\Admin\BannerForm::class)->name('admin.banners.edit');
    Route::get('/drivers', \App\Http\Livewire\Admin\DriverIndex::class)->name('admin.drivers');
    Route::get('/drivers/create', \App\Http\Livewire\Admin\DriverForm::class)->name('admin.drivers.create');
    Route::get('/drivers/{driver}/edit', \App\Http\Livewire\Admin\DriverForm::class)->name('admin.drivers.edit');
    Route::get('/news', \App\Http\Livewire\Admin\NewsIndex::class)->name('admin.news');
    Route::get('/news/create', \App\Http\Livewire\Admin\NewsForm::class)->name('admin.news.create');
    Route::get('/news/{post}/edit', \App\Http\Livewire\Admin\NewsForm::class)->name('admin.news.edit');
    Route::get('/stats', \App\Http\Livewire\Admin\StatsPage::class)->name('admin.stats');
    Route::get('/telegram', \App\Http\Livewire\Admin\TelegramPage::class)->name('admin.telegram');
    Route::get('/payments', \App\Http\Livewire\Admin\PaymentIndex::class)->name('admin.payments');
    Route::get('/templates', LabelTemplateIndex::class)->name('label-templates.index');
    Route::get('/templates/{template}/preview.pdf', [LabelPdfController::class, 'preview'])->name('label-templates.preview');
    Route::get('/product-groups', ProductGroupIndex::class)->name('product-groups.index');
    Route::get('/users', UserIndex::class)->name('users.index');
    Route::get('/printers', PrinterIndex::class)->name('printers.index');
    Route::get('/aggregations', AggregationIndex::class)->name('aggregations.index');
    Route::get('/aggregations/scanner', AggregationScanner::class)->name('aggregations.scanner');
});
