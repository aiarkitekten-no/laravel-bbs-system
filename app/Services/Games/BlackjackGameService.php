<?php

namespace App\Services\Games;

use App\Models\User;

/**
 * Blackjack - Classic 21 card game
 */
class BlackjackGameService extends BaseGameService
{
    private array $cardValues = [
        '2' => 2, '3' => 3, '4' => 4, '5' => 5, '6' => 6, '7' => 7, '8' => 8, '9' => 9,
        '10' => 10, 'J' => 10, 'Q' => 10, 'K' => 10, 'A' => 11,
    ];

    public function start(User $user): array
    {
        $initialState = [
            'credits' => 1000,
            'bet' => 0,
            'player_hand' => [],
            'dealer_hand' => [],
            'deck' => [],
            'phase' => 'betting',
            'hands_played' => 0,
            'wins' => 0,
        ];

        $this->updateState($user, $initialState);

        return [
            'message' => 'Welcome to Blackjack! Place your bet.',
            'credits' => $initialState['credits'],
            'min_bet' => 10,
            'max_bet' => 500,
            'phase' => 'betting',
            'game_over' => false,
        ];
    }

    public function play(User $user, string $action, array $data): array
    {
        $state = $this->getPlayerState($user)->state;

        if ($state['credits'] <= 0) {
            return [
                'game_over' => true,
                'message' => 'Out of credits! Game over.',
                'score' => $state['wins'] * 100,
            ];
        }

        switch ($action) {
            case 'bet':
                return $this->placeBet($user, $state, $data);
            case 'hit':
                return $this->hit($user, $state);
            case 'stand':
                return $this->stand($user, $state);
            case 'double':
                return $this->doubleDown($user, $state);
            default:
                return ['error' => 'Unknown action', 'game_over' => false];
        }
    }

    private function placeBet(User $user, array $state, array $data): array
    {
        if ($state['phase'] !== 'betting') {
            return ['error' => 'Not in betting phase', 'game_over' => false];
        }

        $bet = (int)($data['amount'] ?? 10);
        $bet = max(10, min(500, $bet));

        if ($bet > $state['credits']) {
            return ['error' => 'Not enough credits', 'game_over' => false];
        }

        // Create deck (4 decks shuffled together)
        $deck = [];
        $suits = ['♠', '♥', '♦', '♣'];
        $ranks = array_keys($this->cardValues);

        for ($d = 0; $d < 4; $d++) {
            foreach ($suits as $suit) {
                foreach ($ranks as $rank) {
                    $deck[] = $rank . $suit;
                }
            }
        }
        shuffle($deck);

        // Deal cards
        $playerHand = [array_shift($deck), array_shift($deck)];
        $dealerHand = [array_shift($deck), array_shift($deck)];

        $state['bet'] = $bet;
        $state['credits'] -= $bet;
        $state['player_hand'] = $playerHand;
        $state['dealer_hand'] = $dealerHand;
        $state['deck'] = $deck;
        $state['phase'] = 'playing';

        $playerValue = $this->calculateHand($playerHand);
        $dealerUpcard = $dealerHand[0];

        // Check for blackjack
        if ($playerValue === 21) {
            return $this->resolveHand($user, $state, true);
        }

        $this->updateState($user, $state);

        return [
            'message' => 'Cards dealt!',
            'player_hand' => $playerHand,
            'player_value' => $playerValue,
            'dealer_upcard' => $dealerUpcard,
            'bet' => $bet,
            'credits' => $state['credits'],
            'phase' => 'playing',
            'can_double' => count($playerHand) === 2 && $state['credits'] >= $bet,
            'game_over' => false,
        ];
    }

    private function hit(User $user, array $state): array
    {
        if ($state['phase'] !== 'playing') {
            return ['error' => 'Not in playing phase', 'game_over' => false];
        }

        $deck = $state['deck'];
        $state['player_hand'][] = array_shift($deck);
        $state['deck'] = $deck;

        $playerValue = $this->calculateHand($state['player_hand']);

        if ($playerValue > 21) {
            // Bust
            $state['phase'] = 'betting';
            $state['hands_played']++;

            $this->updateState($user, $state);

            return [
                'message' => 'BUST! You went over 21.',
                'player_hand' => $state['player_hand'],
                'player_value' => $playerValue,
                'dealer_hand' => $state['dealer_hand'],
                'result' => 'lose',
                'credits' => $state['credits'],
                'phase' => 'betting',
                'game_over' => false,
            ];
        }

        $this->updateState($user, $state);

        return [
            'message' => 'Hit!',
            'player_hand' => $state['player_hand'],
            'player_value' => $playerValue,
            'dealer_upcard' => $state['dealer_hand'][0],
            'can_double' => false,
            'phase' => 'playing',
            'game_over' => false,
        ];
    }

    private function stand(User $user, array $state): array
    {
        if ($state['phase'] !== 'playing') {
            return ['error' => 'Not in playing phase', 'game_over' => false];
        }

        return $this->resolveHand($user, $state, false);
    }

    private function doubleDown(User $user, array $state): array
    {
        if ($state['phase'] !== 'playing') {
            return ['error' => 'Not in playing phase', 'game_over' => false];
        }

        if (count($state['player_hand']) !== 2) {
            return ['error' => 'Can only double on first two cards', 'game_over' => false];
        }

        if ($state['credits'] < $state['bet']) {
            return ['error' => 'Not enough credits to double', 'game_over' => false];
        }

        $state['credits'] -= $state['bet'];
        $state['bet'] *= 2;

        // Take exactly one more card
        $deck = $state['deck'];
        $state['player_hand'][] = array_shift($deck);
        $state['deck'] = $deck;

        $playerValue = $this->calculateHand($state['player_hand']);

        if ($playerValue > 21) {
            $state['phase'] = 'betting';
            $state['hands_played']++;

            $this->updateState($user, $state);

            return [
                'message' => 'Double down BUST!',
                'player_hand' => $state['player_hand'],
                'player_value' => $playerValue,
                'dealer_hand' => $state['dealer_hand'],
                'result' => 'lose',
                'bet_lost' => $state['bet'],
                'credits' => $state['credits'],
                'phase' => 'betting',
                'game_over' => false,
            ];
        }

        return $this->resolveHand($user, $state, false);
    }

    private function resolveHand(User $user, array $state, bool $playerBlackjack): array
    {
        // Dealer plays
        $deck = $state['deck'];
        $dealerHand = $state['dealer_hand'];

        while ($this->calculateHand($dealerHand) < 17) {
            $dealerHand[] = array_shift($deck);
        }

        $state['dealer_hand'] = $dealerHand;
        $state['deck'] = $deck;

        $playerValue = $this->calculateHand($state['player_hand']);
        $dealerValue = $this->calculateHand($dealerHand);

        $result = '';
        $winnings = 0;

        if ($playerBlackjack && count($state['player_hand']) === 2) {
            if ($dealerValue === 21 && count($dealerHand) === 2) {
                $result = 'push';
                $winnings = $state['bet']; // Return bet
            } else {
                $result = 'blackjack';
                $winnings = (int)($state['bet'] * 2.5); // 3:2 payout
                $state['wins']++;
            }
        } elseif ($dealerValue > 21) {
            $result = 'win';
            $winnings = $state['bet'] * 2;
            $state['wins']++;
        } elseif ($playerValue > $dealerValue) {
            $result = 'win';
            $winnings = $state['bet'] * 2;
            $state['wins']++;
        } elseif ($playerValue === $dealerValue) {
            $result = 'push';
            $winnings = $state['bet'];
        } else {
            $result = 'lose';
            $winnings = 0;
        }

        $state['credits'] += $winnings;
        $state['phase'] = 'betting';
        $state['hands_played']++;
        $state['player_hand'] = [];
        $state['dealer_hand'] = [];
        $state['bet'] = 0;

        $this->updateState($user, $state);

        $messages = [
            'blackjack' => 'BLACKJACK! You win 3:2!',
            'win' => 'You win!',
            'push' => 'Push - bet returned.',
            'lose' => 'Dealer wins.',
        ];

        return [
            'message' => $messages[$result],
            'player_hand' => $state['player_hand'] ?: $this->getPlayerState($user)->state['player_hand'] ?? [],
            'player_value' => $playerValue,
            'dealer_hand' => $dealerHand,
            'dealer_value' => $dealerValue,
            'result' => $result,
            'winnings' => $winnings,
            'credits' => $state['credits'],
            'wins' => $state['wins'],
            'phase' => 'betting',
            'game_over' => false,
        ];
    }

    private function calculateHand(array $hand): int
    {
        $value = 0;
        $aces = 0;

        foreach ($hand as $card) {
            $rank = substr($card, 0, -1);
            $value += $this->cardValues[$rank];
            if ($rank === 'A') {
                $aces++;
            }
        }

        // Adjust for aces
        while ($value > 21 && $aces > 0) {
            $value -= 10;
            $aces--;
        }

        return $value;
    }
}
