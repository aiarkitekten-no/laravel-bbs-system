<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class NodeSeeder extends Seeder
{
    public function run(): void
    {
        for ($i = 1; $i <= 6; $i++) {
            DB::table('nodes')->insert([
                'node_number' => $i,
                'status' => 'ONLINE',
                'current_user_id' => null,
                'current_activity' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
