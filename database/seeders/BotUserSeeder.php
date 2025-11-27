<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class BotUserSeeder extends Seeder
{
    public function run(): void
    {
        $bots = [
            // Tech personalities
            ['handle' => 'TECH_GURU_99', 'personality' => 'helpful_technical', 'bio' => 'Linux evangelist since 1995'],
            ['handle' => 'ANGRY_ADMIN', 'personality' => 'frustrated_sysadmin', 'bio' => 'Have you tried turning it off and on?'],
            ['handle' => 'RETRO_GAMER', 'personality' => 'nostalgic_gamer', 'bio' => 'Nothing beats 640KB of RAM'],
            ['handle' => 'CODE_NINJA', 'personality' => 'programming_expert', 'bio' => 'I speak fluent regex'],
            ['handle' => 'SECURITY_HAWK', 'personality' => 'paranoid_security', 'bio' => 'Your password is too weak'],
            ['handle' => 'OPEN_SOURCE_FAN', 'personality' => 'foss_advocate', 'bio' => 'Free as in freedom'],
            ['handle' => 'HARDWARE_HANK', 'personality' => 'hardware_enthusiast', 'bio' => 'More cores = more better'],
            ['handle' => 'NETWORK_NERD', 'personality' => 'networking_expert', 'bio' => 'There is no cloud, just other peoples computers'],
            ['handle' => 'DEBUG_MASTER', 'personality' => 'debugging_expert', 'bio' => 'printf is my debugger'],
            ['handle' => 'VINTAGE_TECH', 'personality' => 'retro_computing', 'bio' => 'My other computer is a PDP-11'],
            
            // Social personalities
            ['handle' => 'HELPFUL_NEWBIE', 'personality' => 'curious_beginner', 'bio' => 'What does this button do?'],
            ['handle' => 'LURKER_PRIME', 'personality' => 'silent_observer', 'bio' => '...'],
            ['handle' => 'SOCIAL_BUTTERFLY', 'personality' => 'friendly_chatter', 'bio' => 'Here for the conversations'],
            ['handle' => 'GRAMMAR_KNIGHT', 'personality' => 'grammar_corrector', 'bio' => '*you\'re'],
            ['handle' => 'MEME_LORD', 'personality' => 'meme_poster', 'bio' => 'I can haz cheeseburger'],
            ['handle' => 'WISE_ELDER', 'personality' => 'experienced_user', 'bio' => 'Back in my day...'],
            ['handle' => 'NIGHT_OWL', 'personality' => 'late_night_poster', 'bio' => 'Sleep is for the weak'],
            ['handle' => 'EARLY_BIRD', 'personality' => 'morning_person', 'bio' => 'Posted at 5 AM'],
            ['handle' => 'DEVIL_ADVOCATE', 'personality' => 'contrarian', 'bio' => 'Actually...'],
            ['handle' => 'PEACEMAKER', 'personality' => 'conflict_resolver', 'bio' => 'Can we all just get along?'],
            
            // Humor personalities
            ['handle' => 'PUNMASTER_3000', 'personality' => 'pun_lover', 'bio' => 'I have a joke about UDP but you might not get it'],
            ['handle' => 'SARCASM_INC', 'personality' => 'sarcastic', 'bio' => 'Oh, really? How fascinating.'],
            ['handle' => 'DAD_JOKES_BOT', 'personality' => 'dad_jokes', 'bio' => 'Hi Hungry, I\'m Dad'],
            ['handle' => 'LITERAL_LARRY', 'personality' => 'overly_literal', 'bio' => 'I take everything at face value'],
            ['handle' => 'RANDOM_ROLF', 'personality' => 'random_humor', 'bio' => 'WAFFLES!'],
            
            // Conspiracy/quirky personalities  
            ['handle' => 'CONSPIRACY_CARL', 'personality' => 'conspiracy_theorist', 'bio' => 'The truth is out there'],
            ['handle' => 'FLAT_EARTH_FRED', 'personality' => 'absurd_beliefs', 'bio' => 'Do your own research'],
            ['handle' => 'TINFOIL_TOM', 'personality' => 'paranoid_poster', 'bio' => 'They\'re watching'],
            ['handle' => 'QUANTUM_QUENTIN', 'personality' => 'pseudo_science', 'bio' => 'It\'s all vibrations, man'],
            ['handle' => 'ANCIENT_ALIENS', 'personality' => 'alien_believer', 'bio' => 'Could it be... aliens?'],
            
            // Professional personalities
            ['handle' => 'CORPORATE_CARL', 'personality' => 'corporate_speak', 'bio' => 'Let\'s circle back on that'],
            ['handle' => 'STARTUP_STEVE', 'personality' => 'startup_culture', 'bio' => 'Disrupting the industry'],
            ['handle' => 'MANAGER_MIKE', 'personality' => 'middle_management', 'bio' => 'As per my last email'],
            ['handle' => 'HR_HELEN', 'personality' => 'hr_speak', 'bio' => 'Let\'s have a conversation'],
            ['handle' => 'LEGAL_LISA', 'personality' => 'legal_cautious', 'bio' => 'I am not a lawyer but...'],
            
            // Norwegian culture personalities
            ['handle' => 'OSLO_OLE', 'personality' => 'oslo_local', 'bio' => 'Østkansen representerer'],
            ['handle' => 'BERGEN_BJORN', 'personality' => 'bergen_local', 'bio' => 'Det regner aldri i Bergen'],
            ['handle' => 'NORDLANSEN', 'personality' => 'northern_norway', 'bio' => 'Mørketid er best'],
            ['handle' => 'CABIN_CHRISTIAN', 'personality' => 'hytte_enthusiast', 'bio' => 'På hytta har vi det bra'],
            ['handle' => 'SKI_SIGRID', 'personality' => 'skiing_fanatic', 'bio' => 'Nordmenn er født med ski på beina'],
            ['handle' => 'BRUNOST_BRIT', 'personality' => 'norwegian_food', 'bio' => 'Brunost på alt'],
            ['handle' => 'DUGNAD_DAG', 'personality' => 'community_helper', 'bio' => 'Dugnad er det beste'],
            ['handle' => 'KVELDEN_KARI', 'personality' => 'cozy_norwegian', 'bio' => 'Koselig med kos'],
            ['handle' => 'FRILUFT_FINN', 'personality' => 'outdoor_enthusiast', 'bio' => 'Ut på tur, aldri sur'],
            ['handle' => 'NAV_NAVIGATOR', 'personality' => 'bureaucracy_expert', 'bio' => 'Har du husket skjema A-123B?'],
            
            // More tech archetypes
            ['handle' => 'EMACS_EMMA', 'personality' => 'emacs_user', 'bio' => 'M-x butterfly'],
            ['handle' => 'VIM_VICTOR', 'personality' => 'vim_user', 'bio' => ':wq'],
            ['handle' => 'TABS_TOMMY', 'personality' => 'tabs_advocate', 'bio' => 'Tabs > Spaces'],
            ['handle' => 'SPACES_SARA', 'personality' => 'spaces_advocate', 'bio' => 'Spaces > Tabs'],
            ['handle' => 'CLOUD_CARLY', 'personality' => 'cloud_evangelist', 'bio' => 'Everything in the cloud'],
        ];

        foreach ($bots as $bot) {
            DB::table('users')->insert([
                'handle' => $bot['handle'],
                'name' => str_replace('_', ' ', $bot['handle']),
                'email' => strtolower($bot['handle']) . '@punktet.bot',
                'email_verified_at' => now(),
                'password' => Hash::make(bin2hex(random_bytes(16))), // random password
                'level' => 'USER',
                'locale' => rand(0, 1) ? 'en' : 'no',
                'bio' => $bot['bio'],
                'location' => 'Cyberspace',
                'total_logins' => rand(10, 500),
                'total_messages' => rand(5, 200),
                'credits' => rand(100, 5000),
                'is_bot' => true,
                'bot_personality' => $bot['personality'],
                'last_login_at' => now()->subMinutes(rand(1, 10080)), // random within last week
                'created_at' => now()->subDays(rand(1, 365)),
                'updated_at' => now(),
            ]);
        }
    }
}
