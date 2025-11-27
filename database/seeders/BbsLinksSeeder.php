<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BbsLinksSeeder extends Seeder
{
    public function run(): void
    {
        $links = [
            [
                'name' => 'Synchronet BBS',
                'url' => 'https://www.synchro.net/',
                'telnet' => 'telnet://vert.synchro.net',
                'description' => 'Synchronet BBS Software and multinode BBS',
                'sysop_name' => 'Digital Man',
                'location' => 'USA',
                'sort_order' => 1,
            ],
            [
                'name' => 'Level 29',
                'url' => 'https://www.level29.de/',
                'telnet' => 'telnet://bbs.level29.de',
                'description' => 'German Amiga BBS',
                'sysop_name' => null,
                'location' => 'Germany',
                'sort_order' => 2,
            ],
            [
                'name' => 'Particles BBS',
                'url' => null,
                'telnet' => 'telnet://particlesbbs.dyndns.org',
                'description' => 'Classic DOS BBS',
                'sysop_name' => null,
                'location' => 'USA',
                'sort_order' => 3,
            ],
            [
                'name' => 'BBS Corner',
                'url' => 'https://www.bbscorner.com/',
                'telnet' => null,
                'description' => 'BBS resources and directory',
                'sysop_name' => null,
                'location' => 'Internet',
                'sort_order' => 4,
            ],
            [
                'name' => 'Telnet BBS Guide',
                'url' => 'https://www.telnetbbsguide.com/',
                'telnet' => null,
                'description' => 'Comprehensive BBS directory',
                'sysop_name' => null,
                'location' => 'Internet',
                'sort_order' => 5,
            ],
        ];

        foreach ($links as $link) {
            DB::table('bbs_links')->insert(array_merge($link, [
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }
}
