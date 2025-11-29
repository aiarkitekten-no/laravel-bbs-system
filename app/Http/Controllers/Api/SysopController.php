<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Story;
use App\Models\MessageThread;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SysopController extends Controller
{
    /**
     * Get SysOp dashboard stats
     */
    public function dashboard(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'categories' => Category::count(),
                'stories' => Story::count(),
                'threads' => MessageThread::count(),
                'messages' => Message::count(),
                'users' => User::count(),
            ]
        ]);
    }

    /**
     * List all categories for forum post generation
     */
    public function listCategories(): JsonResponse
    {
        $categories = Category::orderBy('sort_order')->get(['id', 'slug', 'name_no', 'name_en', 'message_count']);
        
        return response()->json([
            'success' => true,
            'categories' => $categories->map(fn($c) => [
                'id' => $c->id,
                'name' => $c->name_no ?: $c->name_en,
                'slug' => $c->slug,
                'messages' => $c->message_count,
            ]),
        ]);
    }

    /**
     * Generate 15 new categories (Norwegian or English)
     */
    public function generateCategories(Request $request): JsonResponse
    {
        $language = $request->input('language', 'no'); // 'no' or 'en'
        $existingCount = Category::count();
        
        // Get existing category slugs to avoid duplicates
        $existingSlugs = Category::pluck('slug')->toArray();
        
        // Predefined category templates for BBS forums
        $norwegianCategories = [
            ['slug' => 'no-alt-debatt', 'name_no' => 'no.alt.debatt', 'name_en' => 'Norwegian Debate', 'desc_no' => 'Generell debatt om alt mellom himmel og jord', 'desc_en' => 'General debate about everything'],
            ['slug' => 'no-politikk', 'name_no' => 'no.politikk', 'name_en' => 'Norwegian Politics', 'desc_no' => 'Politiske diskusjoner og meningsutveksling', 'desc_en' => 'Political discussions'],
            ['slug' => 'no-data-pc', 'name_no' => 'no.data.pc', 'name_en' => 'PC & Computing', 'desc_no' => 'Alt om PC-er, hardware og software', 'desc_en' => 'PC hardware and software'],
            ['slug' => 'no-data-internett', 'name_no' => 'no.data.internett', 'name_en' => 'Internet Discussion', 'desc_no' => 'Internett, nettverk og online tjenester', 'desc_en' => 'Internet and networking'],
            ['slug' => 'no-spill', 'name_no' => 'no.spill', 'name_en' => 'Gaming', 'desc_no' => 'Dataspill, brettspill og rollespill', 'desc_en' => 'Video games, board games, RPGs'],
            ['slug' => 'no-musikk', 'name_no' => 'no.musikk', 'name_en' => 'Music', 'desc_no' => 'Musikk, band, konserter og låter', 'desc_en' => 'Music, bands, concerts'],
            ['slug' => 'no-film-tv', 'name_no' => 'no.film.tv', 'name_en' => 'Film & TV', 'desc_no' => 'Film, TV-serier og streaming', 'desc_en' => 'Movies and TV shows'],
            ['slug' => 'no-humor', 'name_no' => 'no.humor', 'name_en' => 'Humor', 'desc_no' => 'Vitser, morsomheter og lattermilde historier', 'desc_en' => 'Jokes and funny stories'],
            ['slug' => 'no-bil-motor', 'name_no' => 'no.bil.motor', 'name_en' => 'Cars & Motors', 'desc_no' => 'Biler, motorsykler og mekanikk', 'desc_en' => 'Cars, motorcycles, mechanics'],
            ['slug' => 'no-sport', 'name_no' => 'no.sport', 'name_en' => 'Sports', 'desc_no' => 'Fotball, ski, håndball og annen sport', 'desc_en' => 'Football, skiing, sports'],
            ['slug' => 'no-vitenskap', 'name_no' => 'no.vitenskap', 'name_en' => 'Science', 'desc_no' => 'Vitenskap, forskning og teknologi', 'desc_en' => 'Science and research'],
            ['slug' => 'no-boker-lesing', 'name_no' => 'no.bøker', 'name_en' => 'Books & Reading', 'desc_no' => 'Litteratur, bokdiskusjoner og anmeldelser', 'desc_en' => 'Literature and book reviews'],
            ['slug' => 'no-mat-drikke', 'name_no' => 'no.mat.drikke', 'name_en' => 'Food & Drinks', 'desc_no' => 'Matlaging, oppskrifter og restauranter', 'desc_en' => 'Cooking and restaurants'],
            ['slug' => 'no-reise', 'name_no' => 'no.reise', 'name_en' => 'Travel', 'desc_no' => 'Reiser, ferie og destinasjoner', 'desc_en' => 'Travel and vacations'],
            ['slug' => 'no-foto', 'name_no' => 'no.foto', 'name_en' => 'Photography', 'desc_no' => 'Fotografering, kamera og bildebehandling', 'desc_en' => 'Photography and cameras'],
            ['slug' => 'no-helse-trening', 'name_no' => 'no.helse.trening', 'name_en' => 'Health & Fitness', 'desc_no' => 'Trening, kosthold og helse', 'desc_en' => 'Fitness and health'],
            ['slug' => 'no-jobb-karriere', 'name_no' => 'no.jobb', 'name_en' => 'Jobs & Career', 'desc_no' => 'Arbeidslivet, jobbsøking og karriere', 'desc_en' => 'Work and career'],
            ['slug' => 'no-hjem-hage', 'name_no' => 'no.hjem.hage', 'name_en' => 'Home & Garden', 'desc_no' => 'Bolig, interiør og hagearbeid', 'desc_en' => 'Home improvement and gardening'],
            ['slug' => 'no-dyr', 'name_no' => 'no.dyr', 'name_en' => 'Pets & Animals', 'desc_no' => 'Kjæledyr, husdyr og dyreliv', 'desc_en' => 'Pets and animals'],
            ['slug' => 'no-okonomi', 'name_no' => 'no.økonomi', 'name_en' => 'Economy & Finance', 'desc_no' => 'Økonomi, aksjer og personlig finans', 'desc_en' => 'Economy and finance'],
        ];

        $englishCategories = [
            ['slug' => 'en-alt-flame', 'name_no' => 'en.alt.flame', 'name_en' => 'en.alt.flame', 'desc_no' => 'Hete diskusjoner og meningsutveksling', 'desc_en' => 'Heated debates and flame wars'],
            ['slug' => 'en-alt-rant', 'name_no' => 'en.alt.rant', 'name_en' => 'en.alt.rant', 'desc_no' => 'Frustrasjon og klaging', 'desc_en' => 'Venting and ranting about life'],
            ['slug' => 'en-comp-sys', 'name_no' => 'en.comp.sys', 'name_en' => 'en.comp.sys', 'desc_no' => 'Datasystemer og operativsystemer', 'desc_en' => 'Computer systems and OS'],
            ['slug' => 'en-comp-programming', 'name_no' => 'en.comp.programming', 'name_en' => 'en.comp.programming', 'desc_no' => 'Programmering og koding', 'desc_en' => 'Programming and coding'],
            ['slug' => 'en-rec-games', 'name_no' => 'en.rec.games', 'name_en' => 'en.rec.games', 'desc_no' => 'Spill og gaming', 'desc_en' => 'Video games and gaming culture'],
            ['slug' => 'en-rec-music', 'name_no' => 'en.rec.music', 'name_en' => 'en.rec.music', 'desc_no' => 'Musikk og artister', 'desc_en' => 'Music discussion and artists'],
            ['slug' => 'en-rec-movies', 'name_no' => 'en.rec.movies', 'name_en' => 'en.rec.movies', 'desc_no' => 'Film og TV', 'desc_en' => 'Movies and TV shows'],
            ['slug' => 'en-sci-tech', 'name_no' => 'en.sci.tech', 'name_en' => 'en.sci.tech', 'desc_no' => 'Vitenskap og teknologi', 'desc_en' => 'Science and technology'],
            ['slug' => 'en-talk-politics', 'name_no' => 'en.talk.politics', 'name_en' => 'en.talk.politics', 'desc_no' => 'Politisk debatt', 'desc_en' => 'Political debates and opinions'],
            ['slug' => 'en-alt-conspiracy', 'name_no' => 'en.alt.conspiracy', 'name_en' => 'en.alt.conspiracy', 'desc_no' => 'Konspirasjonsteorier og mysterier', 'desc_en' => 'Conspiracy theories and mysteries'],
            ['slug' => 'en-misc-jobs', 'name_no' => 'en.misc.jobs', 'name_en' => 'en.misc.jobs', 'desc_no' => 'Jobb og karriere', 'desc_en' => 'Jobs and career talk'],
            ['slug' => 'en-alt-humor', 'name_no' => 'en.alt.humor', 'name_en' => 'en.alt.humor', 'desc_no' => 'Humor og moro', 'desc_en' => 'Humor and jokes'],
            ['slug' => 'en-rec-autos', 'name_no' => 'en.rec.autos', 'name_en' => 'en.rec.autos', 'desc_no' => 'Biler og motorsport', 'desc_en' => 'Cars and motorsports'],
            ['slug' => 'en-rec-sports', 'name_no' => 'en.rec.sports', 'name_en' => 'en.rec.sports', 'desc_no' => 'Sport og idrett', 'desc_en' => 'Sports discussion'],
            ['slug' => 'en-alt-paranormal', 'name_no' => 'en.alt.paranormal', 'name_en' => 'en.alt.paranormal', 'desc_no' => 'Det paranormale og overnaturlige', 'desc_en' => 'Paranormal and supernatural'],
            ['slug' => 'en-sci-space', 'name_no' => 'en.sci.space', 'name_en' => 'en.sci.space', 'desc_no' => 'Romfart og astronomi', 'desc_en' => 'Space and astronomy'],
            ['slug' => 'en-rec-travel', 'name_no' => 'en.rec.travel', 'name_en' => 'en.rec.travel', 'desc_no' => 'Reiser og turisme', 'desc_en' => 'Travel and tourism'],
            ['slug' => 'en-alt-lifestyle', 'name_no' => 'en.alt.lifestyle', 'name_en' => 'en.alt.lifestyle', 'desc_no' => 'Livsstil og hverdagsliv', 'desc_en' => 'Lifestyle and daily life'],
            ['slug' => 'en-rec-food', 'name_no' => 'en.rec.food', 'name_en' => 'en.rec.food', 'desc_no' => 'Mat og drikke', 'desc_en' => 'Food and cooking'],
            ['slug' => 'en-misc-general', 'name_no' => 'en.misc.general', 'name_en' => 'en.misc.general', 'desc_no' => 'Generell diskusjon', 'desc_en' => 'General discussion'],
        ];

        $categories = $language === 'no' ? $norwegianCategories : $englishCategories;
        
        // Filter out existing categories
        $newCategories = array_filter($categories, fn($cat) => !in_array($cat['slug'], $existingSlugs));
        
        // Take only 15
        $toCreate = array_slice($newCategories, 0, 15);
        
        if (empty($toCreate)) {
            return response()->json([
                'success' => true,
                'message' => 'Alle kategorier for dette språket eksisterer allerede',
                'created' => 0,
            ]);
        }

        $created = 0;
        $maxOrder = Category::max('sort_order') ?? 0;

        foreach ($toCreate as $cat) {
            Category::create([
                'slug' => $cat['slug'],
                'name_no' => $cat['name_no'],
                'name_en' => $cat['name_en'],
                'description_no' => $cat['desc_no'],
                'description_en' => $cat['desc_en'],
                'sort_order' => ++$maxOrder,
                'is_active' => true,
            ]);
            $created++;
        }

        return response()->json([
            'success' => true,
            'message' => "Opprettet {$created} nye kategorier",
            'created' => $created,
            'total' => Category::count(),
        ]);
    }

    /**
     * Generate AI Story
     */
    public function generateStory(Request $request): JsonResponse
    {
        $request->validate([
            'category_id' => 'nullable|exists:categories,id',
            'topic' => 'nullable|string|max:200',
        ]);

        $category = $request->category_id 
            ? Category::find($request->category_id) 
            : Category::inRandomOrder()->first();

        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'Ingen kategorier funnet. Opprett kategorier først.',
            ], 400);
        }

        $topic = $request->input('topic', $category->name);

        $prompt = $this->getStoryPrompt($category, $topic);

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . config('services.openai.api_key'),
                'Content-Type' => 'application/json',
            ])->timeout(60)->post('https://api.openai.com/v1/chat/completions', [
                'model' => config('services.openai.model', 'gpt-4o-mini'),
                'messages' => [
                    ['role' => 'system', 'content' => 'Du er en kreativ forfatter som skriver korte, engasjerende historier på norsk. Historiene skal være 200-400 ord.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'max_tokens' => 800,
                'temperature' => 0.9,
            ]);

            if (!$response->successful()) {
                throw new \Exception('OpenAI API feil: ' . $response->body());
            }

            $content = $response->json()['choices'][0]['message']['content'];
            
            // Parse title and content
            $lines = explode("\n", trim($content));
            $title = trim(str_replace(['#', '*'], '', $lines[0]));
            $body = trim(implode("\n", array_slice($lines, 1)));

            $story = Story::create([
                'category_id' => $category->id,
                'title_no' => $title,
                'title_en' => $title,
                'content_no' => $body,
                'content_en' => $body,
                'author_name' => 'AI Forfatter',
                'publish_date' => now(),
                'is_ai_generated' => true,
            ]);

            $category->increment('story_count');

            return response()->json([
                'success' => true,
                'message' => 'Historie generert!',
                'story' => [
                    'id' => $story->id,
                    'title' => $title,
                    'category' => $category->name,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Story generation failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Feil ved generering: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generate AI Forum Post with replies
     */
    public function generateForumPost(Request $request): JsonResponse
    {
        $request->validate([
            'category_id' => 'nullable|exists:categories,id',
            'topic' => 'nullable|string|max:200',
        ]);

        $category = $request->category_id 
            ? Category::find($request->category_id) 
            : Category::inRandomOrder()->first();

        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'Ingen kategorier funnet.',
            ], 400);
        }

        // AI personalities for forum posts
        $personalities = [
            ['name' => 'RetroGuru', 'style' => 'Nostalgisk og erfaren, refererer ofte til "gamle dager" og 90-tallet'],
            ['name' => 'TechWizard', 'style' => 'Teknisk og fakta-orientert, liker å korrigere andre'],
            ['name' => 'AngryNerd', 'style' => 'Irritabel og sarkastisk, starter ofte flame wars'],
            ['name' => 'NiceGuy92', 'style' => 'Vennlig og forsonende, prøver å holde freden'],
            ['name' => 'Skeptiker', 'style' => 'Kritisk og spørrende, tviler på alt'],
            ['name' => 'Newbie2000', 'style' => 'Uerfaren men entusiastisk, stiller mange spørsmål'],
            ['name' => 'OldTimer', 'style' => 'Gammel traver, husker alt fra Fidonet-tiden'],
            ['name' => 'FlameKing', 'style' => 'Provoserende og konfronterende, elsker debatt'],
            ['name' => 'Lurker99', 'style' => 'Vanligvis stille, men kommer med gode poeng'],
            ['name' => 'PhiloSopher', 'style' => 'Filosofisk og dyptenkende, går alltid dypere'],
        ];

        $topic = $request->input('topic') ?: $this->generateRandomTopic($category);

        try {
            // Generate main post
            $mainAuthor = $personalities[array_rand($personalities)];
            $mainPost = $this->generateForumPostContent($category, $topic, $mainAuthor, true);

            // Create thread (using correct field names from model)
            $thread = MessageThread::create([
                'category_id' => $category->id,
                'user_id' => 1, // SysOp user ID
                'subject' => $mainPost['title'],
                'reply_count' => 0,
                'view_count' => rand(10, 100),
                'is_sticky' => false,
                'is_locked' => false,
                'last_message_at' => now(),
            ]);

            // Create main message
            $mainMessage = Message::create([
                'thread_id' => $thread->id,
                'user_id' => 1,
                'body' => $mainPost['content'],
            ]);

            // Generate 5-14 replies
            $replyCount = rand(5, 14);
            $usedPersonalities = [$mainAuthor['name']];

            for ($i = 0; $i < $replyCount; $i++) {
                // Select a personality (can repeat some)
                $replyAuthor = $personalities[array_rand($personalities)];
                
                // Sometimes reply to specific user
                $replyTo = $i > 0 && rand(0, 1) ? $usedPersonalities[array_rand($usedPersonalities)] : null;
                
                $reply = $this->generateReplyContent($category, $topic, $replyAuthor, $mainPost['content'], $replyTo);
                
                Message::create([
                    'thread_id' => $thread->id,
                    'user_id' => 1,
                    'body' => $reply,
                ]);

                $usedPersonalities[] = $replyAuthor['name'];
                
                // Small delay to spread timestamps
                usleep(100000); // 0.1 second
            }

            $thread->update(['reply_count' => $replyCount, 'last_message_at' => now()]);
            $category->increment('message_count', $replyCount + 1);

            return response()->json([
                'success' => true,
                'message' => "Forum-tråd opprettet med {$replyCount} svar!",
                'thread' => [
                    'id' => $thread->id,
                    'title' => $thread->subject,
                    'category' => $category->name_no ?: $category->name_en,
                    'replies' => $replyCount,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Forum post generation failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Feil ved generering: ' . $e->getMessage(),
            ], 500);
        }
    }

    // Helper methods

    private function getStoryPrompt($category, $topic): string
    {
        return "Skriv en kort, engasjerende historie om \"{$topic}\" for kategorien \"{$category->name}\". 
        
Historien skal:
- Være 200-400 ord
- Ha en fengende tittel på første linje
- Være underholdende og interessant
- Passe for et BBS forum publikum (tech-interesserte, nostalgi-elskere)

Begynn med tittelen, deretter historien.";
    }

    private function generateRandomTopic($category): string
    {
        $topics = [
            'no-alt-debatt' => ['Er Norge for dyrt?', 'Innvandring - for eller mot?', 'Bør vi kutte i bistand?', 'Strømpriser ut av kontroll'],
            'no-politikk' => ['Valget 2025 - hvem vinner?', 'NAV skandaler', 'Helseforetakene svikter', 'Skattepolitikk'],
            'no-data-pc' => ['Windows vs Linux', 'Beste PC bygget i 2025', 'RTX 5090 - verdt prisen?', 'Mekaniske tastaturer'],
            'en-alt-flame' => ['PC vs Console - The eternal war', 'Tabs vs Spaces', 'Vim vs Emacs', 'Apple is overpriced'],
            'en-comp-programming' => ['Is AI replacing programmers?', 'Best programming language 2025', 'Clean code is overrated', 'Microservices vs Monolith'],
        ];

        $categoryTopics = $topics[$category->slug] ?? ['Generell diskusjon', 'Hva synes dere?', 'Noen som har erfaring med dette?'];
        return $categoryTopics[array_rand($categoryTopics)];
    }

    private function generateForumPostContent($category, $topic, $author, $isMain): array
    {
        $prompt = "Du er {$author['name']} på et BBS forum. Din personlighet: {$author['style']}.

Skriv et innlegg om \"{$topic}\" i kategorien \"{$category->name}\".

Krav:
- Minst 400 ord
- Engasjerende og personlig stil som passer din personlighet
- Bruk BBS/90-talls referanser av og til
- Inviter til diskusjon
- Skriv på norsk (med noen engelske tech-uttrykk er OK)

Format:
Første linje: En fengende tittel for tråden
Resten: Selve innlegget";

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . config('services.openai.api_key'),
            'Content-Type' => 'application/json',
        ])->timeout(60)->post('https://api.openai.com/v1/chat/completions', [
            'model' => config('services.openai.model', 'gpt-4o-mini'),
            'messages' => [
                ['role' => 'user', 'content' => $prompt],
            ],
            'max_tokens' => 1000,
            'temperature' => 0.9,
        ]);

        $content = $response->json()['choices'][0]['message']['content'] ?? '';
        $lines = explode("\n", trim($content));
        $title = trim(str_replace(['#', '*'], '', $lines[0]));
        $body = trim(implode("\n", array_slice($lines, 1)));

        return [
            'title' => $title,
            'content' => $body,
        ];
    }

    private function generateReplyContent($category, $topic, $author, $originalPost, $replyTo = null): string
    {
        $replyContext = $replyTo ? "Du svarer direkte til {$replyTo}." : "Du svarer på hovedinnlegget.";
        
        $tones = ['saklig', 'sarkastisk', 'entusiastisk', 'kritisk', 'humoristisk', 'provoserende'];
        $tone = $tones[array_rand($tones)];

        $prompt = "Du er {$author['name']} på et BBS forum. Din personlighet: {$author['style']}.

{$replyContext}

Originalinnlegget handler om \"{$topic}\".

Skriv et svar som er:
- 50-200 ord
- Tone: {$tone}
- Personlig og engasjert
- Kan være enig, uenig, eller komme med nytt perspektiv
- Bruk av og til BBS-slang eller 90-talls referanser

Skriv KUN svaret, ingen tittel.";

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . config('services.openai.api_key'),
            'Content-Type' => 'application/json',
        ])->timeout(30)->post('https://api.openai.com/v1/chat/completions', [
            'model' => config('services.openai.model', 'gpt-4o-mini'),
            'messages' => [
                ['role' => 'user', 'content' => $prompt],
            ],
            'max_tokens' => 400,
            'temperature' => 0.95,
        ]);

        return $response->json()['choices'][0]['message']['content'] ?? 'Interessant poeng!';
    }
}
