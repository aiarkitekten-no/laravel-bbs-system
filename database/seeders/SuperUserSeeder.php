<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class SuperUserSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('users')->insert([
            'handle' => 'TERJE',
            'name' => 'Terje SysOp',
            'email' => 'terje@smartesider.no',
            'email_verified_at' => now(),
            'password' => Hash::make('KlokkenTerje2025'),
            'level' => 'SYSOP',
            'locale' => 'no',
            'bio' => 'System Operator of PUNKTET BBS',
            'location' => 'Norway',
            'total_logins' => 0,
            'credits' => 999999,
            'daily_time_limit' => 86400, // unlimited (24h)
            'is_bot' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
