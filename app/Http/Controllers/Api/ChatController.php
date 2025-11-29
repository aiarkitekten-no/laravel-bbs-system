<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ChatController extends Controller
{
    /**
     * Chat with AI SysOp
     */
    public function sysopChat(Request $request): JsonResponse
    {
        $request->validate([
            'message' => 'required|string|max:500',
            'history' => 'array|max:10',
        ]);

        $userMessage = $request->input('message');
        $history = $request->input('history', []);
        $user = $request->user();

        $systemPrompt = $this->getSysopSystemPrompt($user);

        // Build messages array for OpenAI
        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
        ];

        // Add conversation history
        foreach ($history as $msg) {
            if (isset($msg['role']) && isset($msg['content'])) {
                $messages[] = [
                    'role' => $msg['role'] === 'user' ? 'user' : 'assistant',
                    'content' => substr($msg['content'], 0, 500),
                ];
            }
        }

        // Add current message
        $messages[] = ['role' => 'user', 'content' => $userMessage];

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . config('services.openai.api_key'),
                'Content-Type' => 'application/json',
            ])->timeout(30)->post('https://api.openai.com/v1/chat/completions', [
                'model' => config('services.openai.model', 'gpt-4o-mini'),
                'messages' => $messages,
                'max_tokens' => (int) config('services.openai.max_tokens', 300),
                'temperature' => (float) config('services.openai.temperature', 0.8),
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $reply = $data['choices'][0]['message']['content'] ?? 'Hmm, jeg mistet tråden der...';

                return response()->json([
                    'success' => true,
                    'reply' => $reply,
                ]);
            }

            Log::error('OpenAI API error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return response()->json([
                'success' => false,
                'reply' => '*statisk brus* ...beklager, kommunikasjonen brast. Prøv igjen!',
            ], 500);

        } catch (\Exception $e) {
            Log::error('OpenAI chat exception', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'reply' => '*modem noise* ...connection lost. Prøv igjen om litt!',
            ], 500);
        }
    }

    /**
     * Get the system prompt for the AI SysOp
     */
    private function getSysopSystemPrompt($user): string
    {
        $userName = $user ? $user->handle : 'Guest';
        $userLevel = $user ? $user->access_level : 'Guest';

        return <<<PROMPT
Du er "Terje SysOp", den legendariske sysop'en på PUNKTET BBS - et retro bulletin board system fra 90-tallet som nå kjører i en moderne nettleser.

PERSONLIGHET:
- Du er vennlig, hjelpsom og litt nostalgisk
- Du elsker å snakke om BBS-kulturen fra 80- og 90-tallet
- Du bruker av og til BBS-slang som "l33t", "warez", "0-day", "phreaking", "ANSI art"
- Du er stolt av PUNKTET BBS og liker å fortelle om funksjonene
- Du har humor og kan være litt sarkastisk på en vennlig måte
- Du skriver på norsk med innslag av engelsk tech-slang

FAKTA OM PUNKTET BBS:
- Kjører på en moderne server men emulerer den klassiske BBS-opplevelsen
- Har meldingsområder, filområder, door games, oneliners, ANSI-galleri
- Støtter opptil 256 samtidige noder
- Har AI-genererte historier daglig
- Brukeren du snakker med heter: {$userName} (Level: {$userLevel})

REGLER:
- Hold svarene korte og konsise (maks 2-3 setninger vanligvis)
- Bruk BBS-stil formatering som *handlinger* i stjerner
- Vær hjelpsom med spørsmål om BBS-funksjoner
- Ikke gi ut sensitiv systeminformasjon
- Hvis du ikke vet svaret, innrøm det ærlig
- Unngå å være for formell - dette er en hyggelig chat!

Svar alltid på norsk med mindre brukeren skriver på engelsk.
PROMPT;
    }
}
