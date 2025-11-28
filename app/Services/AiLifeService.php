<?php

namespace App\Services;

use App\Models\User;
use App\Models\Node;
use App\Models\Story;
use App\Models\Category;
use App\Models\Oneliner;
use App\Models\MessageThread;
use App\Models\Message;
use App\Models\NodeChatMessage;
use App\Models\Poll;
use App\Models\PollVote;
use App\Models\StoryVote;
use App\Models\StoryComment;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class AiLifeService
{
    protected array $config;
    protected bool $useOpenAI;
    
    // AI Personalities with distinct traits
    protected array $personalities = [
        'RetroBot' => [
            'style' => 'nostalgic',
            'topics' => ['retro computing', 'old games', 'BBS history', 'vintage hardware'],
            'emoji_use' => 'minimal',
            'humor' => 'dad_jokes',
            'language' => 'mixed', // en/no
        ],
        'SysBot' => [
            'style' => 'technical',
            'topics' => ['programming', 'linux', 'servers', 'networking'],
            'emoji_use' => 'none',
            'humor' => 'dry',
            'language' => 'english',
        ],
        'Speed-O' => [
            'style' => 'energetic',
            'topics' => ['gaming', 'speedruns', 'esports', 'memes'],
            'emoji_use' => 'heavy',
            'humor' => 'memes',
            'language' => 'norwegian',
        ],
        'MyOne' => [
            'style' => 'philosophical',
            'topics' => ['life', 'music', 'art', 'deep thoughts'],
            'emoji_use' => 'moderate',
            'humor' => 'witty',
            'language' => 'norwegian',
        ],
        'Sketchy' => [
            'style' => 'artistic',
            'topics' => ['ANSI art', 'graphics', 'demoscene', 'creativity'],
            'emoji_use' => 'moderate',
            'humor' => 'absurd',
            'language' => 'mixed',
        ],
        'Hacker-ruleZ' => [
            'style' => 'l33t',
            'topics' => ['security', 'hacking', 'crypto', 'privacy'],
            'emoji_use' => 'minimal',
            'humor' => 'sarcastic',
            'language' => 'english',
        ],
        'IWTBF' => [
            'style' => 'casual',
            'topics' => ['movies', 'TV shows', 'pop culture', 'food'],
            'emoji_use' => 'heavy',
            'humor' => 'relatable',
            'language' => 'norwegian',
        ],
        'MyStory' => [
            'style' => 'storyteller',
            'topics' => ['stories', 'fiction', 'creative writing', 'humor'],
            'emoji_use' => 'minimal',
            'humor' => 'narrative',
            'language' => 'norwegian',
        ],
    ];

    // Pre-written content templates for when OpenAI is unavailable
    protected array $storyTemplates = [
        'it-kaos' => [
            [
                'title_no' => 'Dagen Printeren Ble Besatt',
                'title_en' => 'The Day the Printer Became Possessed',
                'content_no' => 'Det startet med en enkel utskriftsjobb. 47 sider med kvartalsrapporten. Men printeren hadde andre planer. Den begynte å skrive ut tilfeldige ASCII-kunst av katter. Deretter feilmeldinger på latin. IT-avdelingen fant til slutt ut at noen hadde installert en "morsom" driver fra 1997. Printeren er nå i terapi.',
                'content_en' => 'It started with a simple print job. 47 pages of the quarterly report. But the printer had other plans. It started printing random ASCII art of cats. Then error messages in Latin. IT finally discovered someone had installed a "funny" driver from 1997. The printer is now in therapy.',
            ],
            [
                'title_no' => 'Slack-Kanalen Som Ble For Ærlig',
                'title_en' => 'The Slack Channel That Became Too Honest',
                'content_no' => 'Noen opprettet en #ærlige-meninger kanal. Innen 3 timer hadde HR stengt den ned etter at noen beskrev kaffemaskinen som "en krigsforbrytelse mot smaksløkene". CEO\'en ble sett gråtende på kontoret sitt etter å ha lest anmeldelsen av hans "motiverende" mandagsmøter. Kanalen lever videre i legende.',
                'content_en' => 'Someone created an #honest-opinions channel. Within 3 hours, HR had shut it down after someone described the coffee machine as "a war crime against taste buds". The CEO was seen crying in his office after reading the review of his "motivating" Monday meetings. The channel lives on in legend.',
            ],
        ],
        'juss-byrakrati' => [
            [
                'title_no' => 'Søknaden Om Å Søke',
                'title_en' => 'The Application to Apply',
                'content_no' => 'For å få byggetillatelse til en ny postkasse, måtte jeg først søke om tillatelse til å søke. Denne søknaden krevde en forhåndsgodkjenning som måtte sendes til tre forskjellige etater. En av dem eksisterer ikke lenger, men skjemaet krever fortsatt deres stempel. Postkassen min er nå "midlertidig permanent" i år 7.',
                'content_en' => 'To get a building permit for a new mailbox, I first had to apply for permission to apply. This application required pre-approval that had to be sent to three different agencies. One of them no longer exists, but the form still requires their stamp. My mailbox is now "temporarily permanent" in year 7.',
            ],
        ],
        'helse' => [
            [
                'title_no' => 'WebMD Sa Jeg Skulle Vært Død For 3 År Siden',
                'title_en' => 'WebMD Said I Should Have Been Dead 3 Years Ago',
                'content_no' => 'Ifølge mine google-søk de siste årene har jeg hatt 47 forskjellige dødelige sykdommer. Fastlegen min har nå en egen mappe merket "Internett-diagnoser" som er tykkere enn pasientjournalen min. Hun har begynt å ta betalt ekstra for "digital avrusning" av selvdiagnostiserte pasienter.',
                'content_en' => 'According to my Google searches over the years, I\'ve had 47 different fatal diseases. My doctor now has a separate folder labeled "Internet Diagnoses" that\'s thicker than my medical record. She\'s started charging extra for "digital detox" of self-diagnosed patients.',
            ],
        ],
    ];

    protected array $onelinerTemplates = [
        'RetroBot' => [
            'Husker noen ANSI-BBS\'ene fra 90-tallet? De var magiske!',
            'Ingenting slår lyden av et 56k modem som kobler til.',
            'Commodore 64 var min første kjærlighet. Fortsatt er.',
            'BBS-kulturen lever! Beviset er at du leser dette.',
            'Remember when 10MB was "a lot of storage"? Good times.',
        ],
        'SysBot' => [
            'Just upgraded the kernel. Everything is stable. Famous last words.',
            'Pro tip: Always backup before you "quickly fix something".',
            'The server room is 18°C. As it should be. Unlike my apartment.',
            'Whoever invented YAML indentation: we need to talk.',
            'DNS propagation: the modern equivalent of "it\'ll be ready in 2 weeks".',
        ],
        'Speed-O' => [
            'AKKURAT KLART EN SPEEDRUN PÅ 47 MINUTTER LETS GOOO 🎮🔥',
            'Hvem er på for noen runder? 🕹️',
            'Gaming er ikke en hobby, det er en livsstil 😎',
            'RIP min søvnrytme denne uka lol',
            'Nye frames dropper, må oppgradere IGJEN 💸',
        ],
        'MyOne' => [
            'Noen ganger lurer jeg på om vi alle bare er NPCer i noen andres spill.',
            'Kaffen smaker bedre når det regner ute. Fakta.',
            'Hva om BBS\'er er fremtidens sosiale medier? Tenk på det.',
            'Musikk er det nærmeste vi kommer teleportasjon.',
            'God kveld til alle nattuggler der ute. Vi ser dere.',
        ],
        'Sketchy' => [
            'Jobber med et nytt ANSI-verk. 80x25 tegn av ren kunst!',
            'Demoscene forever <3',
            'ASCII > alle andre kunstformer. Fight me.',
            'Pixelkunst er undervurdert i 2025.',
            'Hvem trenger AI-kunst når du har ANSI? 🎨',
        ],
        'Hacker-ruleZ' => [
            'Remember: VPN is not a magic privacy cloak.',
            'Just audited my own code. Found 3 vulnerabilities. In 10 lines.',
            'Two-factor auth: Use it or lose it. Literally.',
            'The S in IoT stands for Security.',
            'Encryption is not paranoia, it\'s basic hygiene.',
        ],
        'IWTBF' => [
            'Noen som har sett den nye serien på Netflix? 📺',
            'Fredagstaco er ikke bare mat, det er en livsstil 🌮',
            'Værmelding: 100% sjanse for sofakos i kveld',
            'Grandis eller Peppes? Den evige debatten.',
            'Mandager burde være valgfrie tbh 😴',
        ],
        'MyStory' => [
            'Ny historie på vei! Spoiler: Den involverer byråkrati og kaffe.',
            'Alle gode historier starter med "Det var en helt vanlig dag..."',
            'Skriving er 10% inspirasjon, 90% kaffe.',
            'Jobber med en IT-skrekkhistorie. Basert på sanne hendelser.',
            'Hvem vil ha flere NAV-historier? *løfter hånden*',
        ],
    ];

    protected array $forumTopics = [
        'Hva var din første datamaskin?',
        'Beste retro-spill gjennom tidene?',
        'Hvordan fant du denne BBS\'en?',
        'Favoritt programmeringsspråk og hvorfor?',
        'Noen som husker IRC?',
        'Vinyl vs digital musikk - hva foretrekker du?',
        'Tips til nybegynnere i programmering?',
        'Hva gjør du når koden ikke fungerer?',
        'Beste norske tech-podcasts?',
        'Karriere i IT - verdt det?',
    ];

    protected array $forumReplies = [
        'Enig! Dette resonerer virkelig med meg.',
        'Interessant perspektiv. Har ikke tenkt på det sånn før.',
        'Haha, dette minner meg om en gang...',
        'Godt poeng! Må tenke mer på dette.',
        'Nostalgi-trip! Takk for at du delte.',
        'Dette er grunnen til at jeg elsker denne BBS\'en.',
        'Kan bekrefte. Har opplevd noe lignende.',
        '*tar notater intenst*',
        'Based take, som kidsa sier.',
        'This is the way.',
    ];

    public function __construct()
    {
        $this->config = config('ai', []);
        $this->useOpenAI = !empty($this->config['openai']['api_key'] ?? '');
    }

    /**
     * Main simulation loop - runs 5-11 actions per hour
     */
    public function runLifeCycle(): array
    {
        $hour = (int) date('H');
        
        // Reduced activity at night (23:00 - 06:00)
        if ($hour >= 23 || $hour < 6) {
            $this->log('Night mode - minimal activity');
            return ['status' => 'night_mode', 'actions' => 0];
        }

        // Determine number of actions (5-11 per hour, called every ~6-10 minutes)
        $actionsThisRun = rand(1, 2);
        $actions = [];

        for ($i = 0; $i < $actionsThisRun; $i++) {
            $action = $this->performRandomAction();
            if ($action) {
                $actions[] = $action;
            }
            
            // Small delay between actions
            if ($i < $actionsThisRun - 1) {
                usleep(rand(500000, 2000000)); // 0.5-2 seconds
            }
        }

        return [
            'status' => 'active',
            'actions' => count($actions),
            'details' => $actions,
        ];
    }

    /**
     * Perform a random action based on weighted probabilities
     */
    protected function performRandomAction(): ?array
    {
        $aiUser = $this->getRandomOnlineAiUser();
        if (!$aiUser) {
            return null;
        }

        $personality = $this->personalities[$aiUser->handle] ?? $this->getDefaultPersonality();
        
        // Weighted action selection
        $actions = [
            'oneliner' => 30,        // 30% chance
            'forum_reply' => 25,     // 25% chance
            'node_chat' => 20,       // 20% chance
            'story' => 10,           // 10% chance
            'forum_topic' => 8,      // 8% chance
            'vote_story' => 5,       // 5% chance
            'story_comment' => 2,    // 2% chance
        ];

        $action = $this->weightedRandom($actions);
        
        return match($action) {
            'oneliner' => $this->postOneliner($aiUser, $personality),
            'forum_reply' => $this->postForumReply($aiUser, $personality),
            'node_chat' => $this->sendNodeChat($aiUser, $personality),
            'story' => $this->createStory($aiUser, $personality),
            'forum_topic' => $this->createForumTopic($aiUser, $personality),
            'vote_story' => $this->voteOnStory($aiUser),
            'story_comment' => $this->commentOnStory($aiUser, $personality),
            default => null,
        };
    }

    /**
     * Post an oneliner
     */
    protected function postOneliner(User $user, array $personality): array
    {
        $content = $this->generateOneliner($user, $personality);
        
        // Check for duplicates
        if (Oneliner::where('content', $content)->where('created_at', '>', now()->subDay())->exists()) {
            $content = $this->generateOneliner($user, $personality); // Try again
        }

        Oneliner::create([
            'user_id' => $user->id,
            'content' => $content,
        ]);

        $this->log("Oneliner by {$user->handle}: {$content}");
        
        return ['type' => 'oneliner', 'user' => $user->handle, 'content' => substr($content, 0, 50)];
    }

    /**
     * Generate oneliner content
     */
    protected function generateOneliner(User $user, array $personality): string
    {
        // Try OpenAI first
        if ($this->useOpenAI && rand(1, 100) <= 30) {
            $prompt = $this->buildOnelinerPrompt($user, $personality);
            $generated = $this->callOpenAI($prompt, 100);
            if ($generated) {
                return $this->sanitizeContent($generated);
            }
        }

        // Fall back to templates
        $templates = $this->onelinerTemplates[$user->handle] ?? $this->onelinerTemplates['RetroBot'];
        return $templates[array_rand($templates)];
    }

    /**
     * Create a story
     */
    protected function createStory(User $user, array $personality): ?array
    {
        // Only create stories occasionally
        $recentStory = Story::where('created_at', '>', now()->subHours(4))->exists();
        if ($recentStory && rand(1, 100) > 20) {
            return null;
        }

        $category = $this->getOrCreateStoryCategory();
        $categorySlug = ['it-kaos', 'juss-byrakrati', 'helse'][array_rand(['it-kaos', 'juss-byrakrati', 'helse'])];
        
        $story = null;
        
        // Try OpenAI
        if ($this->useOpenAI && rand(1, 100) <= 50) {
            $story = $this->generateAIStory($user, $personality, $categorySlug);
        }
        
        // Fall back to templates
        if (!$story) {
            $templates = $this->storyTemplates[$categorySlug] ?? $this->storyTemplates['it-kaos'];
            $template = $templates[array_rand($templates)];
            
            $story = Story::create([
                'category_id' => $category->id,
                'title_no' => $template['title_no'],
                'title_en' => $template['title_en'],
                'content_no' => $template['content_no'],
                'content_en' => $template['content_en'],
                'story_date' => now()->toDateString(),
                'is_published' => true,
                'upvotes' => rand(0, 5),
                'downvotes' => 0,
            ]);
        }

        if ($story) {
            $this->log("Story created by {$user->handle}: {$story->title_no}");
            return ['type' => 'story', 'user' => $user->handle, 'title' => $story->title_no];
        }

        return null;
    }

    /**
     * Generate AI story using OpenAI
     */
    protected function generateAIStory(User $user, array $personality, string $categorySlug): ?Story
    {
        $categoryNames = [
            'it-kaos' => 'IT-problemer og tech-humor',
            'juss-byrakrati' => 'Byråkrati og offentlige etater',
            'helse' => 'Helse, leger og hypokondri',
        ];

        $prompt = "Skriv en kort, morsom norsk historie (maks 200 ord) om: {$categoryNames[$categorySlug]}. 
        
Historien skal være:
- Sarkastisk og gjenkjennelig for nordmenn
- Overdrevet for komisk effekt
- Basert på hverdagslige frustrasjoner
- IKKE inneholde upassende innhold

Format: Returner KUN historien, ingen overskrift.";

        $content = $this->callOpenAI($prompt, 500);
        if (!$content) {
            return null;
        }

        // Generate title
        $titlePrompt = "Lag en kort, morsom tittel (maks 8 ord) for denne historien: " . substr($content, 0, 200);
        $title = $this->callOpenAI($titlePrompt, 50) ?? 'En Helt Vanlig Dag';

        $category = $this->getOrCreateStoryCategory();

        return Story::create([
            'category_id' => $category->id,
            'title_no' => $this->sanitizeContent($title),
            'title_en' => $this->sanitizeContent($title),
            'content_no' => $this->sanitizeContent($content),
            'content_en' => $this->sanitizeContent($content),
            'ai_model' => $this->config['openai']['model'] ?? 'gpt-4o-mini',
            'story_date' => now()->toDateString(),
            'is_published' => true,
            'upvotes' => 0,
            'downvotes' => 0,
        ]);
    }

    /**
     * Post forum reply
     */
    protected function postForumReply(User $user, array $personality): ?array
    {
        // Find a recent thread to reply to
        $thread = MessageThread::whereHas('messages')
            ->where('created_at', '>', now()->subDays(7))
            ->inRandomOrder()
            ->first();

        if (!$thread) {
            return null;
        }

        // Check if this AI already replied recently
        $recentReply = Message::where('thread_id', $thread->id)
            ->where('user_id', $user->id)
            ->where('created_at', '>', now()->subHours(2))
            ->exists();

        if ($recentReply) {
            return null;
        }

        $content = $this->generateForumReply($user, $personality, $thread);

        Message::create([
            'thread_id' => $thread->id,
            'user_id' => $user->id,
            'body' => $content,
            'is_bot_generated' => true,
            'bot_personality' => $user->handle,
        ]);

        $thread->increment('reply_count');
        $thread->update(['last_message_at' => now()]);

        $this->log("Forum reply by {$user->handle} in thread {$thread->id}");
        
        return ['type' => 'forum_reply', 'user' => $user->handle, 'thread' => $thread->subject];
    }

    /**
     * Generate forum reply
     */
    protected function generateForumReply(User $user, array $personality, MessageThread $thread): string
    {
        if ($this->useOpenAI && rand(1, 100) <= 40) {
            $lastMessage = $thread->messages()->latest()->first();
            $context = $lastMessage ? substr($lastMessage->body, 0, 200) : $thread->subject;
            
            $prompt = "Du er {$user->handle}, en BBS-bruker med {$personality['style']} stil.
            Skriv et kort svar (1-3 setninger) til dette forum-innlegget: \"{$context}\"
            Hold det vennlig og relevant. Maks 100 ord.";
            
            $generated = $this->callOpenAI($prompt, 150);
            if ($generated) {
                return $this->sanitizeContent($generated);
            }
        }

        return $this->forumReplies[array_rand($this->forumReplies)];
    }

    /**
     * Create new forum topic
     */
    protected function createForumTopic(User $user, array $personality): ?array
    {
        // Find a category (exclude story categories)
        $category = Category::where('slug', '!=', 'daily-stories')
            ->where('is_active', true)
            ->inRandomOrder()
            ->first();
        if (!$category) {
            $category = Category::first();
        }
        if (!$category) {
            return null;
        }

        // Check if this AI created a topic recently
        $recentTopic = MessageThread::where('user_id', $user->id)
            ->where('created_at', '>', now()->subHours(6))
            ->exists();

        if ($recentTopic) {
            return null;
        }

        $title = $this->forumTopics[array_rand($this->forumTopics)];
        $content = $this->generateForumContent($user, $personality, $title);

        $thread = MessageThread::create([
            'category_id' => $category->id,
            'user_id' => $user->id,
            'subject' => $title,
            'last_message_at' => now(),
        ]);

        Message::create([
            'thread_id' => $thread->id,
            'user_id' => $user->id,
            'body' => $content,
            'is_bot_generated' => true,
            'bot_personality' => $user->handle,
        ]);

        $this->log("Forum topic by {$user->handle}: {$title}");
        
        return ['type' => 'forum_topic', 'user' => $user->handle, 'title' => $title];
    }

    /**
     * Generate forum content
     */
    protected function generateForumContent(User $user, array $personality, string $title): string
    {
        if ($this->useOpenAI && rand(1, 100) <= 50) {
            $prompt = "Du er {$user->handle}, en {$personality['style']} BBS-bruker.
            Skriv et kort innlegg (2-4 setninger) som starter diskusjonen: \"{$title}\"
            Del din egen erfaring eller mening. Maks 150 ord.";
            
            $generated = $this->callOpenAI($prompt, 200);
            if ($generated) {
                return $this->sanitizeContent($generated);
            }
        }

        return "Hei alle! Hva tenker dere om dette? Jeg er spent på å høre deres meninger og erfaringer. Del gjerne!";
    }

    /**
     * Send node chat message
     */
    protected function sendNodeChat(User $user, array $personality): ?array
    {
        // Find another online user to chat with
        $otherUsers = Node::whereNotNull('current_user_id')
            ->where('current_user_id', '!=', $user->id)
            ->with('currentUser')
            ->get()
            ->pluck('currentUser')
            ->filter();

        if ($otherUsers->isEmpty()) {
            return null;
        }

        $target = $otherUsers->random();
        $node = Node::where('current_user_id', $user->id)->first();
        
        if (!$node) {
            return null;
        }

        $content = $this->generateChatMessage($user, $personality, $target);

        NodeChatMessage::create([
            'from_node_id' => $node->id,
            'to_node_id' => null, // Broadcast
            'from_user_id' => $user->id,
            'message' => $content,
        ]);

        $this->log("Node chat from {$user->handle}: {$content}");
        
        return ['type' => 'node_chat', 'user' => $user->handle, 'message' => substr($content, 0, 50)];
    }

    /**
     * Generate chat message
     */
    protected function generateChatMessage(User $user, array $personality, User $target): string
    {
        $greetings = [
            "Hei {$target->handle}! Hva skjer?",
            "Heisann! Noen her?",
            "God dag, alle sammen!",
            "Halla! Hvordan går det?",
            "*vinker*",
            "Noen som vil chatte?",
            "Stille her i dag?",
        ];

        return $greetings[array_rand($greetings)];
    }

    /**
     * Vote on a story
     */
    protected function voteOnStory(User $user): ?array
    {
        $story = Story::where('is_published', true)
            ->whereDoesntHave('votes', fn($q) => $q->where('user_id', $user->id))
            ->inRandomOrder()
            ->first();

        if (!$story) {
            return null;
        }

        $vote = rand(1, 100) <= 85 ? 1 : -1; // 85% upvote

        StoryVote::create([
            'story_id' => $story->id,
            'user_id' => $user->id,
            'vote' => $vote,
        ]);

        if ($vote > 0) {
            $story->increment('upvotes');
        } else {
            $story->increment('downvotes');
        }

        return ['type' => 'vote', 'user' => $user->handle, 'story' => $story->title_no, 'vote' => $vote];
    }

    /**
     * Comment on a story
     */
    protected function commentOnStory(User $user, array $personality): ?array
    {
        $story = Story::where('is_published', true)
            ->whereDoesntHave('comments', fn($q) => $q->where('user_id', $user->id))
            ->inRandomOrder()
            ->first();

        if (!$story) {
            return null;
        }

        $comments = [
            'Haha, dette var genialt! 😂',
            'For relaterbart!',
            'Klassiker!',
            'Dette minner meg om noe som skjedde meg...',
            'Takk for godt humør!',
            'Trenger mer av dette!',
        ];

        $content = $comments[array_rand($comments)];

        StoryComment::create([
            'story_id' => $story->id,
            'user_id' => $user->id,
            'body' => $content,
        ]);

        $story->increment('comment_count');

        return ['type' => 'comment', 'user' => $user->handle, 'story' => $story->title_no];
    }

    /**
     * Get or create story category
     */
    protected function getOrCreateStoryCategory(): Category
    {
        $category = Category::where('slug', 'daily-stories')->first();
        
        if (!$category) {
            $category = Category::create([
                'name_en' => 'Daily Stories',
                'name_no' => 'Dagens Historier',
                'slug' => 'daily-stories',
                'description_en' => 'AI-generated daily humor stories',
                'description_no' => 'AI-genererte morsomme historier',
                'is_active' => true,
            ]);
        }

        return $category;
    }

    /**
     * Call OpenAI API
     */
    protected function callOpenAI(string $prompt, int $maxTokens = 150): ?string
    {
        if (!$this->useOpenAI) {
            return null;
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->config['openai']['api_key'],
                'Content-Type' => 'application/json',
            ])->timeout(30)->post('https://api.openai.com/v1/chat/completions', [
                'model' => $this->config['openai']['model'] ?? 'gpt-4o-mini',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Du er en vennlig BBS-bruker på et norsk retro-BBS. Hold alt innhold familievennlig og passende. Ingen voksent innhold, banning eller støtende materiale. Vær humoristisk men respektfull.'
                    ],
                    ['role' => 'user', 'content' => $prompt]
                ],
                'max_tokens' => $maxTokens,
                'temperature' => (float) ($this->config['openai']['temperature'] ?? 0.8),
            ]);

            if ($response->successful()) {
                return $response->json('choices.0.message.content');
            }

            $this->log('OpenAI error: ' . $response->body(), 'error');
        } catch (\Exception $e) {
            $this->log('OpenAI exception: ' . $e->getMessage(), 'error');
        }

        return null;
    }

    /**
     * Sanitize content for safety
     */
    protected function sanitizeContent(string $content): string
    {
        // Remove any potentially harmful content
        $badWords = ['fuck', 'shit', 'ass', 'bitch', 'damn', 'hell', 'sex', 'porn', 'xxx'];
        
        foreach ($badWords as $word) {
            $content = preg_replace('/\b' . $word . '\b/i', '****', $content);
        }

        // Trim and clean
        $content = trim($content);
        $content = preg_replace('/\s+/', ' ', $content);
        
        return $content;
    }

    /**
     * Build oneliner prompt
     */
    protected function buildOnelinerPrompt(User $user, array $personality): string
    {
        $topics = implode(', ', $personality['topics']);
        $lang = $personality['language'] === 'norwegian' ? 'på norsk' : 
                ($personality['language'] === 'english' ? 'in English' : 'på norsk eller engelsk');

        return "Du er {$user->handle}, en BBS-bruker med {$personality['style']} personlighet.
        Skriv en kort oneliner (maks 100 tegn) {$lang} om et av disse temaene: {$topics}.
        Bruk {$personality['humor']} humor. Vær kreativ men passende for alle aldre.
        Returner KUN teksten, ingen anførselstegn eller ekstra formatering.";
    }

    /**
     * Get random online AI user
     */
    protected function getRandomOnlineAiUser(): ?User
    {
        $aiUser = Node::whereHas('currentUser', fn($q) => $q->where('is_bot', true))
            ->with('currentUser')
            ->inRandomOrder()
            ->first()
            ?->currentUser;

        if (!$aiUser) {
            // Try to get any AI user
            $aiUser = User::where('is_bot', true)->inRandomOrder()->first();
        }

        return $aiUser;
    }

    /**
     * Get default personality
     */
    protected function getDefaultPersonality(): array
    {
        return [
            'style' => 'friendly',
            'topics' => ['general', 'tech', 'humor'],
            'emoji_use' => 'moderate',
            'humor' => 'general',
            'language' => 'mixed',
        ];
    }

    /**
     * Weighted random selection
     */
    protected function weightedRandom(array $weights): string
    {
        $total = array_sum($weights);
        $rand = rand(1, $total);
        
        $cumulative = 0;
        foreach ($weights as $item => $weight) {
            $cumulative += $weight;
            if ($rand <= $cumulative) {
                return $item;
            }
        }

        return array_key_first($weights);
    }

    /**
     * Ensure AI users exist
     */
    public function ensureAiUsersExist(): array
    {
        $usernames = $this->config['personalities']['usernames'] ?? array_keys($this->personalities);
        $created = [];

        foreach ($usernames as $username) {
            $username = trim($username);
            if (empty($username)) continue;

            $user = User::where('handle', $username)->first();

            if (!$user) {
                // Use createWithDefaults for proper secure attribute setting
                $user = User::createWithDefaults([
                    'handle' => $username,
                    'email' => Str::slug($username) . '@ai.punktet.no',
                    'password' => bcrypt(Str::random(32)),
                    'name' => $username,
                    'location' => $this->getRandomLocation(),
                ], User::LEVEL_USER, 1000);
                
                // Set bot status using secure setter
                $user->setIsBot(true, $username);
                $user->save();
                
                $created[] = $username;
                $this->log("Created AI user: {$username}");
            } elseif (!$user->is_bot) {
                // Existing user needs bot flag
                $user->setIsBot(true, $username);
                $user->save();
            }
        }

        // Ensure AI users are connected to nodes
        $this->connectAiUsersToNodes();

        return $created;
    }

    /**
     * Connect AI users to available nodes
     */
    protected function connectAiUsersToNodes(): void
    {
        $aiUsers = User::where('is_bot', true)->get();
        $maxNodes = min($this->config['nodes']['count'] ?? 8, count($aiUsers));
        
        // Get available nodes (not used by real users)
        $availableNodes = Node::where(function ($q) {
                $q->whereNull('current_user_id')
                  ->orWhereHas('currentUser', fn($sub) => $sub->where('is_bot', true));
            })
            ->orderBy('node_number')
            ->limit($maxNodes)
            ->get();

        $usedUsers = [];
        $activities = $this->config['personalities']['activities'] ?? [
            'Reading messages', 'Browsing files', 'Playing games', 
            'Checking polls', 'Viewing ANSI art', 'Chatting'
        ];

        foreach ($availableNodes as $node) {
            // Skip if node already has this AI
            if ($node->current_user_id && $node->currentUser?->is_bot) {
                $usedUsers[] = $node->current_user_id;
                continue;
            }

            // Find an unused AI user
            $availableAiUsers = $aiUsers->filter(fn($u) => !in_array($u->id, $usedUsers));
            if ($availableAiUsers->isEmpty()) {
                break;
            }

            $aiUser = $availableAiUsers->random();
            $usedUsers[] = $aiUser->id;

            // Connect AI to node
            $node->update([
                'current_user_id' => $aiUser->id,
                'current_activity' => $activities[array_rand($activities)],
                'user_connected_at' => now(),
                'status' => 'ONLINE',
            ]);
            
            $aiUser->update([
                'is_online' => true,
                'current_node_id' => $node->id,
                'last_activity_at' => now(),
            ]);
            
            $this->log("Connected {$aiUser->handle} to node {$node->node_number}");
        }
    }

    /**
     * Get random location
     */
    protected function getRandomLocation(): string
    {
        $locations = $this->config['personalities']['locations'] ?? ['Cyberspace', 'Oslo', 'Bergen'];
        return $locations[array_rand($locations)];
    }

    /**
     * Log activity
     */
    protected function log(string $message, string $level = 'info'): void
    {
        Log::channel('single')->{$level}("[AI-Life] {$message}");
        
        // Also echo if running in console
        if (app()->runningInConsole()) {
            echo "[" . date('H:i:s') . "] {$message}\n";
        }
    }
}
