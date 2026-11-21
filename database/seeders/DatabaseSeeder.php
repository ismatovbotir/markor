<?php

namespace Database\Seeders;

use App\Domain\Companies\Company;
use App\Domain\Printing\Models\Printer;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Dev data for `php artisan migrate:fresh --seed`. Reference data the app needs in every
 * environment (the three product categories, the default 60×40 label template) is created by the
 * migrations, not here.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Reference data and the owner's account: dev and production
        $this->call(SuperAdminSeeder::class);
        $this->call(ProductGroupSeeder::class);
        $this->call(OfferSeeder::class);
        $this->call(SettingsSeeder::class);

        if (app()->isProduction()) {
            return; // demo client, company and printers are for development only
        }

        // Demo client with a registered company (owner)
        $company = Company::create([
            'tin'           => '300000001',
            'name'          => 'Demo Savdo MChJ',
            'legal_address' => 'Toshkent sh., Yunusobod tumani',
            'director'      => 'Demo Direktor',
            'phone'         => '+998901234567',
            'email'         => 'client@aslbelgi.local',
            'source'        => 'manual',
        ]);

        User::create([
            'company_id'   => $company->id,
            'company_role' => 'owner',
            'name'         => 'Test Client',
            'email'        => 'client@aslbelgi.local',
            'password'     => 'client123',
            'role'         => 'user',
            'locale'       => 'uz',
            'qr_token'     => Str::random(32),
            'phone'        => '+998901234567',
            'is_active'    => true,
        ]);

        // Printers
        Printer::create([
            'name'            => 'Browser PDF (Default)',
            'type'            => 'pdf_browser',
            'connection_mode' => 'browser',
            'is_active'       => true,
        ]);

        Printer::create([
            'name'            => 'Zebra ZD220 (Network)',
            'type'            => 'zpl_zebra',
            'connection_mode' => 'network',
            'host'            => '192.168.1.100',
            'port'            => 9100,
            'is_active'       => false,
        ]);
    }
}
