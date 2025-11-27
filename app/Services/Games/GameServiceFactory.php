<?php

namespace App\Services\Games;

use App\Models\Game;

class GameServiceFactory
{
    private static array $services = [
        'trivia' => TriviaGameService::class,
        'hangman' => HangmanGameService::class,
        'number-guess' => NumberGuessGameService::class,
        'tradewars' => TradeWarsGameService::class,
        'lord' => LordGameService::class,
        'bre' => BreGameService::class,
        'usurper' => UsurperGameService::class,
        'globalwar' => GlobalWarGameService::class,
        'poker' => PokerGameService::class,
        'blackjack' => BlackjackGameService::class,
        'lottery' => LotteryGameService::class,
    ];

    public static function create(Game $game): BaseGameService
    {
        $serviceClass = self::$services[$game->slug] ?? SimpleGameService::class;

        return new $serviceClass($game);
    }

    public static function register(string $slug, string $serviceClass): void
    {
        self::$services[$slug] = $serviceClass;
    }
}
