<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'slug' => 'it-kaos',
                'name_en' => 'IT Chaos',
                'name_no' => 'IT-kaos',
                'description_en' => 'When computers decide to ruin your day',
                'description_no' => 'Når datamaskiner bestemmer seg for å ødelegge dagen din',
                'sort_order' => 1,
            ],
            [
                'slug' => 'juss-byrakrati',
                'name_en' => 'Law & Bureaucracy',
                'name_no' => 'Juss & byråkrati',
                'description_en' => 'Paperwork, regulations, and other nightmares',
                'description_no' => 'Papirarbeid, regler og andre mareritt',
                'sort_order' => 2,
            ],
            [
                'slug' => 'helse',
                'name_en' => 'Health & Small Panics',
                'name_no' => 'Helse & små panikker',
                'description_en' => 'WebMD says you have 3 days to live',
                'description_no' => 'WebMD sier du har 3 dager igjen å leve',
                'sort_order' => 3,
            ],
            [
                'slug' => 'kjopesenteret',
                'name_en' => 'The Shopping Mall',
                'name_no' => 'Kjøpesenteret',
                'description_en' => 'Retail therapy gone wrong',
                'description_no' => 'Når handleterapi går galt',
                'sort_order' => 4,
            ],
            [
                'slug' => 'naboen',
                'name_en' => 'The Neighbor',
                'name_no' => 'Naboen',
                'description_en' => 'Good fences make good neighbors... supposedly',
                'description_no' => 'Gode gjerder gir gode naboer... visstnok',
                'sort_order' => 5,
            ],
            [
                'slug' => 'kjaerlighet',
                'name_en' => 'Love',
                'name_no' => 'Kjærlighet',
                'description_en' => 'Romance, relationships, and romantic disasters',
                'description_no' => 'Romantikk, forhold og romantiske katastrofer',
                'sort_order' => 6,
            ],
            [
                'slug' => 'vinter',
                'name_en' => 'Winter',
                'name_no' => 'Vinter',
                'description_en' => 'Snow, ice, and freezing adventures',
                'description_no' => 'Snø, is og frosne eventyr',
                'sort_order' => 7,
            ],
        ];

        foreach ($categories as $category) {
            DB::table('categories')->insert(array_merge($category, [
                'message_count' => 0,
                'story_count' => 0,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }
}
