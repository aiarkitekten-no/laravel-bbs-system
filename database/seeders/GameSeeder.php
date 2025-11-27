<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class GameSeeder extends Seeder
{
    public function run(): void
    {
        $games = [
            [
                'slug' => 'trivia',
                'name_en' => 'Trivia Quiz',
                'name_no' => 'Trivia Quiz',
                'description_en' => 'Test your knowledge across various categories',
                'description_no' => 'Test kunnskapen din på tvers av ulike kategorier',
                'type' => 'SIMPLE',
                'config' => json_encode(['questions_per_round' => 10, 'time_per_question' => 30]),
            ],
            [
                'slug' => 'hangman',
                'name_en' => 'Hangman',
                'name_no' => 'Henging',
                'description_en' => 'Guess the word before the man hangs',
                'description_no' => 'Gjett ordet før mannen henger',
                'type' => 'SIMPLE',
                'config' => json_encode(['max_wrong_guesses' => 6]),
            ],
            [
                'slug' => 'number-guess',
                'name_en' => 'Number Guess',
                'name_no' => 'Gjett Tallet',
                'description_en' => 'Guess the secret number between 1 and 100',
                'description_no' => 'Gjett det hemmelige tallet mellom 1 og 100',
                'type' => 'SIMPLE',
                'config' => json_encode(['min' => 1, 'max' => 100, 'max_guesses' => 7]),
            ],
            [
                'slug' => 'tradewars',
                'name_en' => 'Trade Wars',
                'name_no' => 'Handelskrigene',
                'description_en' => 'Classic space trading and combat game',
                'description_no' => 'Klassisk romhandel og kampspill',
                'type' => 'DOOR',
                'config' => json_encode(['turns_per_day' => 100, 'starting_credits' => 1000]),
            ],
            [
                'slug' => 'lord',
                'name_en' => 'Legend of the Red Dragon',
                'name_no' => 'Legenden om den Røde Dragen',
                'description_en' => 'Slay the dragon and become a legend',
                'description_no' => 'Drep dragen og bli en legende',
                'type' => 'DOOR',
                'config' => json_encode(['turns_per_day' => 50, 'forest_fights' => 15]),
            ],
            [
                'slug' => 'bre',
                'name_en' => 'Barren Realms Elite',
                'name_no' => 'Barren Realms Elite',
                'description_en' => 'Build your empire in a post-apocalyptic world',
                'description_no' => 'Bygg ditt imperium i en post-apokalyptisk verden',
                'type' => 'DOOR',
                'config' => json_encode(['turns_per_day' => 200]),
            ],
            [
                'slug' => 'usurper',
                'name_en' => 'Usurper',
                'name_no' => 'Usurper',
                'description_en' => 'Medieval fantasy adventure game',
                'description_no' => 'Middelalder fantasy eventyrspill',
                'type' => 'DOOR',
                'config' => json_encode(['turns_per_day' => 75]),
            ],
            [
                'slug' => 'global-war',
                'name_en' => 'Global War',
                'name_no' => 'Global Krig',
                'description_en' => 'Conquer the world in this strategic war game',
                'description_no' => 'Erobre verden i dette strategiske krigsspillet',
                'type' => 'DOOR',
                'config' => json_encode(['turns_per_day' => 100]),
            ],
            [
                'slug' => 'poker',
                'name_en' => 'Video Poker',
                'name_no' => 'Video Poker',
                'description_en' => 'Classic 5-card draw video poker',
                'description_no' => 'Klassisk 5-kort video poker',
                'type' => 'SIMPLE',
                'config' => json_encode(['min_bet' => 10, 'max_bet' => 1000]),
            ],
            [
                'slug' => 'blackjack',
                'name_en' => 'Blackjack',
                'name_no' => 'Blackjack',
                'description_en' => 'Try to get 21 without going bust',
                'description_no' => 'Prøv å få 21 uten å sprenge',
                'type' => 'SIMPLE',
                'config' => json_encode(['min_bet' => 10, 'max_bet' => 500, 'decks' => 6]),
            ],
            [
                'slug' => 'lottery',
                'name_en' => 'Daily Lottery',
                'name_no' => 'Daglig Lotteri',
                'description_en' => 'Pick your numbers and win big!',
                'description_no' => 'Velg dine tall og vinn stort!',
                'type' => 'DAILY',
                'config' => json_encode(['numbers_to_pick' => 5, 'max_number' => 30, 'ticket_cost' => 10]),
            ],
        ];

        foreach ($games as $game) {
            DB::table('games')->insert(array_merge($game, [
                'is_active' => true,
                'plays_today' => 0,
                'plays_total' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }
}
