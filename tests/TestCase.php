<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /** Asl Belgisi categories (parser structures) are reference data every test needs. */
    protected bool $seed = true;
    protected string $seeder = \Database\Seeders\ProductGroupSeeder::class;

    /** Fake answer of the GTIN classifier: a response, or a closure returning one. */
    protected mixed $classifier = null;

    protected function setUp(): void
    {
        parent::setUp();

        // Billing is tested in its own tests; elsewhere loading codes is free so batches never lock.
        config(['billing.discount_percent' => 100]);

        // No real HTTP calls from tests. The GTIN classifier answers "not found" unless a test sets $this->classifier.
        \Illuminate\Support\Facades\Http::preventStrayRequests();
        \Illuminate\Support\Facades\Http::fake(function ($request) {
            if (!str_contains($request->url(), 'tasnif.soliq.uz')) {
                return null;
            }

            return $this->classifier
                ? value($this->classifier, $request)
                : \Illuminate\Support\Facades\Http::response(['success' => true, 'data' => [], 'recordTotal' => 0]);
        });
    }

    /** Runs the scheduled batch processor once (what happens after an upload in production). */
    protected function processBatches(): void
    {
        $this->artisan('batches:process')->assertSuccessful();
    }
}
