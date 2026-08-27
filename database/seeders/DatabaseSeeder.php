<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // بيانات ضخمة شاملة لجميع حالات النظام (تستدعي FaqSeeder داخلياً).
        // للتفاصيل والضبط راجع SEEDING.md في جذر مشروع الباك-إند.
        $this->call([
            MassiveDataSeeder::class,
        ]);

    }
}
