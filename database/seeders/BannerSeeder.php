<?php

namespace Database\Seeders;

use App\Domain\Content\Banner;
use Illuminate\Database\Seeder;

/** The default home banner (the built-in hero). Safe to run again: created only if missing. */
class BannerSeeder extends Seeder
{
    public function run(): void
    {
        Banner::firstOrCreate(['is_default' => true], ['is_active' => true, 'sort_order' => 0, 'style' => 'light']);
    }
}
