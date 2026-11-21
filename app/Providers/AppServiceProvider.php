<?php

namespace App\Providers;

use App\Domain\Aggregation\Models\Aggregation;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductGroup;
use App\Domain\Marking\Models\ImportBatch;
use App\Domain\Marking\Models\MarkingCode;
use App\Domain\Printing\Models\LabelTemplate;
use App\Domain\Printing\Models\Printer;
use App\Models\User;
use App\Policies\AggregationPolicy;
use App\Policies\ImportBatchPolicy;
use App\Policies\LabelTemplatePolicy;
use App\Policies\MarkingCodePolicy;
use App\Policies\PrinterPolicy;
use App\Policies\ProductGroupPolicy;
use App\Policies\ProductPolicy;
use App\Policies\UserPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        // route middleware re-checked on every Livewire call (a blocked company can't keep working in an open page)
        \Livewire\Livewire::addPersistentMiddleware([\App\Http\Middleware\CompanyRequired::class]);

        // the cached home page shows Marketplace items and label sizes: refresh it when they change
        foreach ([\App\Domain\Offers\Models\Offer::class, \App\Domain\Offers\Models\OfferCategory::class, \App\Domain\Printing\Models\LabelTemplate::class] as $model) {
            $model::saved(fn () => \App\Support\PageCache::bump());
            $model::deleted(fn () => \App\Support\PageCache::bump());
        }

        \App\Domain\Settings\Setting::applyToConfig();

        Gate::define('admin', fn (User $user) => $user->isAdmin());

        Gate::policy(ProductGroup::class, ProductGroupPolicy::class);
        Gate::policy(Product::class, ProductPolicy::class);
        Gate::policy(ImportBatch::class, ImportBatchPolicy::class);
        Gate::policy(MarkingCode::class, MarkingCodePolicy::class);
        Gate::policy(Aggregation::class, AggregationPolicy::class);
        Gate::policy(LabelTemplate::class, LabelTemplatePolicy::class);
        Gate::policy(Printer::class, PrinterPolicy::class);
        Gate::policy(User::class, UserPolicy::class);
    }
}
