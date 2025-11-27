<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AchievementSeeder extends Seeder
{
    public function run(): void
    {
        $achievements = [
            // Message achievements
            ['slug' => 'first-post', 'name_en' => 'First Post!', 'name_no' => 'Første innlegg!', 'description_en' => 'Write your first message', 'description_no' => 'Skriv ditt første innlegg', 'icon' => '📝', 'points' => 10, 'category' => 'MESSAGES', 'requirements' => json_encode(['messages' => 1])],
            ['slug' => 'chatterbox', 'name_en' => 'Chatterbox', 'name_no' => 'Pratemaker', 'description_en' => 'Write 100 messages', 'description_no' => 'Skriv 100 meldinger', 'icon' => '💬', 'points' => 50, 'category' => 'MESSAGES', 'requirements' => json_encode(['messages' => 100])],
            ['slug' => 'keyboard-warrior', 'name_en' => 'Keyboard Warrior', 'name_no' => 'Tastaturkriger', 'description_en' => 'Write 1000 messages', 'description_no' => 'Skriv 1000 meldinger', 'icon' => '⌨️', 'points' => 200, 'category' => 'MESSAGES', 'requirements' => json_encode(['messages' => 1000])],
            
            // File achievements
            ['slug' => 'first-upload', 'name_en' => 'First Upload', 'name_no' => 'Første opplasting', 'description_en' => 'Upload your first file', 'description_no' => 'Last opp din første fil', 'icon' => '📤', 'points' => 20, 'category' => 'FILES', 'requirements' => json_encode(['uploads' => 1])],
            ['slug' => 'file-hoarder', 'name_en' => 'File Hoarder', 'name_no' => 'Filsamler', 'description_en' => 'Download 100 files', 'description_no' => 'Last ned 100 filer', 'icon' => '📥', 'points' => 50, 'category' => 'FILES', 'requirements' => json_encode(['downloads' => 100])],
            ['slug' => 'top-contributor', 'name_en' => 'Top Contributor', 'name_no' => 'Toppbidragsyter', 'description_en' => 'Upload 50 approved files', 'description_no' => 'Last opp 50 godkjente filer', 'icon' => '🏆', 'points' => 200, 'category' => 'FILES', 'requirements' => json_encode(['uploads' => 50])],
            
            // Game achievements  
            ['slug' => 'gamer', 'name_en' => 'Gamer', 'name_no' => 'Spiller', 'description_en' => 'Play any game', 'description_no' => 'Spill et spill', 'icon' => '🎮', 'points' => 10, 'category' => 'GAMES', 'requirements' => json_encode(['games_played' => 1])],
            ['slug' => 'trivia-master', 'name_en' => 'Trivia Master', 'name_no' => 'Trivia Mester', 'description_en' => 'Answer 100 trivia questions correctly', 'description_no' => 'Svar riktig på 100 trivia-spørsmål', 'icon' => '🧠', 'points' => 100, 'category' => 'GAMES', 'requirements' => json_encode(['trivia_correct' => 100])],
            ['slug' => 'high-roller', 'name_en' => 'High Roller', 'name_no' => 'Storspiller', 'description_en' => 'Win 10000 credits in casino games', 'description_no' => 'Vinn 10000 credits i kasinospill', 'icon' => '🎰', 'points' => 150, 'category' => 'GAMES', 'requirements' => json_encode(['casino_winnings' => 10000])],
            ['slug' => 'dragon-slayer', 'name_en' => 'Dragon Slayer', 'name_no' => 'Dragedreper', 'description_en' => 'Defeat the Red Dragon in LORD', 'description_no' => 'Beseir den Røde Dragen i LORD', 'icon' => '🐉', 'points' => 500, 'category' => 'GAMES', 'requirements' => json_encode(['lord_dragon_killed' => true]), 'is_secret' => true],
            
            // Social achievements
            ['slug' => 'newcomer', 'name_en' => 'Newcomer', 'name_no' => 'Nykommer', 'description_en' => 'Create your account', 'description_no' => 'Opprett din konto', 'icon' => '👋', 'points' => 5, 'category' => 'SOCIAL', 'requirements' => json_encode(['registered' => true])],
            ['slug' => 'social-butterfly', 'name_en' => 'Social Butterfly', 'name_no' => 'Sosial sommerfugl', 'description_en' => 'Send 50 private messages', 'description_no' => 'Send 50 private meldinger', 'icon' => '🦋', 'points' => 50, 'category' => 'SOCIAL', 'requirements' => json_encode(['private_messages' => 50])],
            ['slug' => 'club-founder', 'name_en' => 'Club Founder', 'name_no' => 'Klubbgrunnlegger', 'description_en' => 'Create a user club', 'description_no' => 'Opprett en brukerklubb', 'icon' => '🏛️', 'points' => 100, 'category' => 'SOCIAL', 'requirements' => json_encode(['clubs_founded' => 1])],
            
            // Time achievements
            ['slug' => 'regular', 'name_en' => 'Regular', 'name_no' => 'Stamgjest', 'description_en' => 'Log in 30 days in a row', 'description_no' => 'Logg inn 30 dager på rad', 'icon' => '📅', 'points' => 100, 'category' => 'TIME', 'requirements' => json_encode(['login_streak' => 30])],
            ['slug' => 'night-owl', 'name_en' => 'Night Owl', 'name_no' => 'Nattugle', 'description_en' => 'Log in between 2 AM and 5 AM', 'description_no' => 'Logg inn mellom 02:00 og 05:00', 'icon' => '🦉', 'points' => 25, 'category' => 'TIME', 'requirements' => json_encode(['late_login' => true])],
            ['slug' => 'veteran', 'name_en' => 'Veteran', 'name_no' => 'Veteran', 'description_en' => 'Be a member for 1 year', 'description_no' => 'Vær medlem i 1 år', 'icon' => '🎖️', 'points' => 250, 'category' => 'TIME', 'requirements' => json_encode(['member_days' => 365])],
            
            // Special achievements
            ['slug' => 'lucky-lottery', 'name_en' => 'Lucky Winner', 'name_no' => 'Heldig vinner', 'description_en' => 'Win the daily lottery jackpot', 'description_no' => 'Vinn den daglige lottojackpotten', 'icon' => '🍀', 'points' => 500, 'category' => 'SPECIAL', 'requirements' => json_encode(['lottery_jackpot' => true]), 'is_secret' => true],
            ['slug' => 'easter-egg', 'name_en' => 'Easter Egg Hunter', 'name_no' => 'Påskeegg-jeger', 'description_en' => 'Find a hidden easter egg', 'description_no' => 'Finn et skjult påskeegg', 'icon' => '🥚', 'points' => 100, 'category' => 'SPECIAL', 'requirements' => json_encode(['easter_egg' => true]), 'is_secret' => true],
            ['slug' => 'elite-status', 'name_en' => 'Elite Status', 'name_no' => 'Elite Status', 'description_en' => 'Get promoted to ELITE level', 'description_no' => 'Bli forfremmet til ELITE-nivå', 'icon' => '⭐', 'points' => 300, 'category' => 'SPECIAL', 'requirements' => json_encode(['level' => 'ELITE'])],
        ];

        foreach ($achievements as $achievement) {
            $isSecret = $achievement['is_secret'] ?? false;
            unset($achievement['is_secret']);
            
            DB::table('achievements')->insert(array_merge($achievement, [
                'is_secret' => $isSecret,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }
}
