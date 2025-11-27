<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            SuperUserSeeder::class,
            NodeSeeder::class,
            CategorySeeder::class,
            BotUserSeeder::class,
            GameSeeder::class,
            SystemSettingsSeeder::class,
            LogoffQuoteSeeder::class,
            AchievementSeeder::class,
            BbsLinksSeeder::class,
        ]);
    }
}
