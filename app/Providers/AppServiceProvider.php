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
