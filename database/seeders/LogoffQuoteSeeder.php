<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LogoffQuoteSeeder extends Seeder
{
    public function run(): void
    {
        $quotes = [
            ['quote' => 'Remember: You can\'t fix stupid, but you can document it.', 'author' => 'Anonymous SysOp'],
            ['quote' => 'In the beginning, there was the command line.', 'author' => 'Neal Stephenson'],
            ['quote' => 'There are only 10 types of people: those who understand binary and those who don\'t.', 'author' => null],
            ['quote' => 'Home is where the WiFi connects automatically.', 'author' => null],
            ['quote' => 'May your code compile on the first try.', 'author' => 'BBS Blessing'],
            ['quote' => 'The cloud is just someone else\'s computer.', 'author' => null],
            ['quote' => 'Have you tried turning it off and on again?', 'author' => 'IT Crowd'],
            ['quote' => 'It\'s not a bug, it\'s a feature.', 'author' => 'Every Developer Ever'],
            ['quote' => 'sudo make me a sandwich', 'author' => 'xkcd'],
            ['quote' => 'There\'s no place like 127.0.0.1', 'author' => null],
            ['quote' => 'I\'m not anti-social, I\'m just not user-friendly.', 'author' => null],
            ['quote' => 'Roses are #FF0000, Violets are #0000FF.', 'author' => null],
            ['quote' => 'Keep calm and clear your cache.', 'author' => null],
            ['quote' => 'To err is human; to really foul things up requires a computer.', 'author' => 'Bill Vaughan'],
            ['quote' => 'The Internet: Where men are men, women are men, and children are FBI agents.', 'author' => null],
            ['quote' => 'User: The word computer professionals use when they mean idiot.', 'author' => null],
            ['quote' => 'Software undergoes beta testing shortly before it\'s released.', 'author' => null],
            ['quote' => 'Algorithm: Word used by programmers when they don\'t want to explain what they did.', 'author' => null],
            ['quote' => 'Takk for besøket! Kom tilbake snart.', 'author' => 'PUNKTET'],
            ['quote' => 'Et liv uten WiFi er ikke verdt å leve.', 'author' => 'Moderne Ordtak'],
        ];

        foreach ($quotes as $quote) {
            DB::table('logoff_quotes')->insert(array_merge($quote, [
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }
}
