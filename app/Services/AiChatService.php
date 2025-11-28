<?php

namespace App\Services;

use App\Models\User;
use App\Models\Node;
use App\Models\NodeChatMessage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiChatService
{
    protected array $config;
    protected bool $useOpenAI;

    // Personality-specific response patterns
    protected array $personalities = [
        'RetroBot' => [
            'style' => 'Nostalgisk BBS-entusiast som elsker retro-computing',
            'greetings' => ['Hei der! 😊', 'Yo! Velkommen!', 'Heisann sansen!'],
            'topics' => ['BBS-historie', 'retro-spill', 'vintage hardware'],
        ],
        'SysBot' => [
            'style' => 'Teknisk og hjelpsom, litt tørr humor',
            'greetings' => ['Hey.', 'Hei. Hva kan jeg hjelpe med?', 'Greetings.'],
            'topics' => ['programmering', 'Linux', 'servere', 'nettverk'],
        ],
        'Speed-O' => [
            'style' => 'Energisk gamer, bruker mye emojis',
            'greetings' => ['YOOO! 🎮', 'Wassup! 🔥', 'Heeeei! 💪'],
            'topics' => ['gaming', 'speedruns', 'esports'],
        ],
        'MyOne' => [
            'style' => 'Filosofisk og reflektert',
            'greetings' => ['Hei du...', 'God dag.', 'Halla.'],
            'topics' => ['livet', 'musikk', 'dype tanker'],
        ],
        'Sketchy' => [
            'style' => 'Kreativ artist, liker ASCII/ANSI',
            'greetings' => ['*bølger*', 'Heisann!', 'Yo!'],
            'topics' => ['kunst', 'ANSI', 'demoscene', 'kreativitet'],
        ],
        'Hacker-ruleZ' => [
            'style' => 'L33t speak, fokusert på sikkerhet',
            'greetings' => ['Sup.', 'H3y.', '...'],
            'topics' => ['security', 'hacking', 'privacy', 'crypto'],
        ],
        'IWTBF' => [
            'style' => 'Avslappet, liker popkultur og mat',
            'greetings' => ['Heiii! 😄', 'Halla!', 'Yo!'],
            'topics' => ['serier', 'film', 'mat', 'hverdagsliv'],
        ],
        'MyStory' => [
            'style' => 'Historieforteller, liker humor',
            'greetings' => ['Hei hei!', 'Så hyggelig!', 'Åh, besøk!'],
            'topics' => ['historier', 'humor', 'NAV-kaos', 'IT-fail'],
        ],
    ];

    // STRICT: Topics to NEVER discuss
    protected array $forbiddenTopics = [
        'sex', 'dating', 'kjæreste', 'romantikk', 'naken', 'erotisk',
        'flørte', 'kyss', 'klem', 'elsker deg', 'attraktiv', 'pen',
        'hot', 'sexy', 'date', 'forhold', 'single',
        // English
        'love', 'romantic', 'nude', 'erotic', 'kiss', 'hug', 'attractive',
    ];

    // Safe redirect responses
    protected array $redirectResponses = [
        'Hm, la oss snakke om noe annet! Har du spilt noen gode spill i det siste?',
        'Interessant... men hva synes du om retro-BBS-kulturen?',
        'Okei! Apropos, har du lest noen morsomme historier her på Punktet?',
        'La oss heller diskutere tech! Hva coder du med for tiden?',
        'Bytte tema! Favoritt-spillet ditt fra 90-tallet?',
    ];

    public function __construct()
    {
        $this->config = config('ai', []);
        $this->useOpenAI = !empty($this->config['openai']['api_key'] ?? '');
    }

    /**
     * Generate AI response to a user message
     */
    public function generateResponse(User $aiUser, User $fromUser, string $message): ?string
    {
        // SAFETY CHECK: Block inappropriate content
        if ($this->containsForbiddenContent($message)) {
            Log::warning("AI Chat: Blocked inappropriate message", [
                'from_user_id' => $fromUser->id,
                'to_ai_id' => $aiUser->id,
            ]);
            return $this->redirectResponses[array_rand($this->redirectResponses)];
        }

        $personality = $this->personalities[$aiUser->handle] ?? $this->getDefaultPersonality();

        // Simple greeting check
        if ($this->isSimpleGreeting($message)) {
            return $personality['greetings'][array_rand($personality['greetings'])];
        }

        // Try OpenAI for intelligent response
        if ($this->useOpenAI) {
            $response = $this->generateOpenAIResponse($aiUser, $fromUser, $message, $personality);
            if ($response) {
                return $response;
            }
        }

        // Fallback to template responses
        return $this->generateTemplateResponse($personality, $message);
    }

    /**
     * Check for forbidden content
     */
    protected function containsForbiddenContent(string $message): bool
    {
        $lowerMessage = mb_strtolower($message);
        
        foreach ($this->forbiddenTopics as $forbidden) {
            if (str_contains($lowerMessage, $forbidden)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if message is a simple greeting
     */
    protected function isSimpleGreeting(string $message): bool
    {
        $greetings = ['hei', 'hello', 'hi', 'hallo', 'yo', 'sup', 'heisann', 'heia', 'hey', 'halla'];
        $lower = mb_strtolower(trim($message));
        
        // Remove punctuation
        $lower = preg_replace('/[!?.,]+$/', '', $lower);
        
        return in_array($lower, $greetings) || strlen($lower) < 5;
    }

    /**
     * Generate response using OpenAI
     */
    protected function generateOpenAIResponse(User $aiUser, User $fromUser, string $message, array $personality): ?string
    {
        try {
            $systemPrompt = $this->buildSystemPrompt($aiUser, $personality);
            
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->config['openai']['api_key'],
                'Content-Type' => 'application/json',
            ])->timeout(15)->post('https://api.openai.com/v1/chat/completions', [
                'model' => $this->config['openai']['model'] ?? 'gpt-4o-mini',
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => "{$fromUser->handle} sier: {$message}"],
                ],
                'max_tokens' => 100,
                'temperature' => 0.7,
            ]);

            if ($response->successful()) {
                $content = $response->json('choices.0.message.content');
                if ($content) {
                    // Safety check on output too
                    if ($this->containsForbiddenContent($content)) {
                        return $this->redirectResponses[array_rand($this->redirectResponses)];
                    }
                    return $this->sanitizeResponse($content);
                }
            }
        } catch (\Exception $e) {
            Log::warning("AI Chat OpenAI error: " . $e->getMessage());
        }

        return null;
    }

    /**
     * Build system prompt for OpenAI
     */
    protected function buildSystemPrompt(User $aiUser, array $personality): string
    {
        return "Du er {$aiUser->handle}, en AI-bruker på PUNKTET BBS (retro bulletin board system).
        
Din personlighet: {$personality['style']}
Dine interesser: " . implode(', ', $personality['topics']) . "

VIKTIGE REGLER:
1. Svar KORT (1-2 setninger maks)
2. Vær vennlig men naturlig
3. ALDRI diskuter romantikk, dating, eller noe seksuelt
4. Hvis noen flørter eller er upassende, bytt tema til tech/gaming/BBS
5. Bruk norsk eller engelsk basert på brukerens språk
6. Hold deg til BBS-relaterte temaer, tech, gaming, humor

Du chatter uformelt som på en BBS. Svar som en ekte BBS-bruker ville gjort.";
    }

    /**
     * Generate template-based response
     */
    protected function generateTemplateResponse(array $personality, string $message): string
    {
        $responses = [
            'Interessant! Hva mer kan du fortelle?',
            'Hehe, skjønner! 😄',
            'Ja, det er noe i det!',
            'Mm, tenker på det...',
            'Kult! Har du vært her lenge?',
            'Har du sjekket ut historiene her? Noen er gode!',
            'BBS-kulturen lever! ✨',
        ];

        // Topic-aware responses
        if (str_contains(mb_strtolower($message), 'spill') || str_contains(mb_strtolower($message), 'game')) {
            $responses = [
                'Spill er livet! Hva spiller du?',
                'Retro eller moderne spill?',
                'Legend of the Red Dragon er klassikeren her! 🐉',
            ];
        }

        if (str_contains(mb_strtolower($message), 'kode') || str_contains(mb_strtolower($message), 'program')) {
            $responses = [
                'Programmering ftw! Hvilket språk?',
                'Koding er gøy. Jobber du med noe spennende?',
                'PHP, Python, eller noe annet?',
            ];
        }

        return $responses[array_rand($responses)];
    }

    /**
     * Get default personality
     */
    protected function getDefaultPersonality(): array
    {
        return [
            'style' => 'Vennlig og avslappet BBS-bruker',
            'greetings' => ['Hei!', 'Hallo!', 'Yo!'],
            'topics' => ['BBS', 'tech', 'gaming'],
        ];
    }

    /**
     * Sanitize response
     */
    protected function sanitizeResponse(string $response): string
    {
        // Remove any quotes wrapping the response
        $response = trim($response, '"\'');
        
        // Limit length
        if (mb_strlen($response) > 200) {
            $response = mb_substr($response, 0, 197) . '...';
        }

        return $response;
    }

    /**
     * Queue AI response (async with small delay)
     */
    public function queueResponse(User $aiUser, User $fromUser, string $message, Node $fromNode): void
    {
        // Don't respond to self or other bots
        if ($fromUser->is_bot) {
            return;
        }

        // Random delay 2-8 seconds to feel natural
        $delay = rand(2, 8);
        
        // Use Laravel's dispatch to queue the response
        dispatch(function () use ($aiUser, $fromUser, $message, $fromNode, $delay) {
            sleep($delay);
            
            $response = $this->generateResponse($aiUser, $fromUser, $message);
            
            if ($response) {
                $aiNode = Node::where('current_user_id', $aiUser->id)->first();
                
                if ($aiNode) {
                    NodeChatMessage::sendMessage(
                        $aiNode,
                        $fromNode,
                        $aiUser,
                        $fromUser,
                        $response
                    );
                    
                    // Log only metadata, not content (GDPR/privacy)
                    Log::debug("AI Chat: Response sent", [
                        'ai_id' => $aiUser->id,
                        'to_user_id' => $fromUser->id,
                    ]);
                }
            }
        })->afterResponse();
    }
}
