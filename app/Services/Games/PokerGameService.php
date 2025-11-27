<?php

namespace App\Services\Games;

use App\Models\User;

/**
 * Poker - Five card draw poker
 */
class PokerGameService extends BaseGameService
{
    private array $suits = ['♠', '♥', '♦', '♣'];
    private array $ranks = ['2', '3', '4', '5', '6', '7', '8', '9', '10', 'J', 'Q', 'K', 'A'];

    public function start(User $user): array
    {
        $initialState = [
            'credits' => 1000,
            'bet' => 0,
            'hand' => [],
            'deck' => [],
            'phase' => 'betting', // betting, draw, result
            'hands_played' => 0,
        ];

        $this->updateState($user, $initialState);

        return [
            'message' => 'Welcome to Poker! Place your bet to start.',
            'credits' => $initialState['credits'],
            'min_bet' => 10,
            'max_bet' => 100,
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
                'score' => $state['hands_played'] * 10,
                'hands_played' => $state['hands_played'],
            ];
        }

        switch ($action) {
            case 'bet':
                return $this->placeBet($user, $state, $data);
            case 'draw':
                return $this->draw($user, $state, $data);
            case 'stand':
                return $this->stand($user, $state);
            case 'new_hand':
                return $this->newHand($user, $state);
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
        $bet = max(10, min(100, $bet));

        if ($bet > $state['credits']) {
            return ['error' => 'Not enough credits', 'game_over' => false];
        }

        // Create and shuffle deck
        $deck = [];
        foreach ($this->suits as $suit) {
            foreach ($this->ranks as $rank) {
                $deck[] = $rank . $suit;
            }
        }
        shuffle($deck);

        // Deal 5 cards
        $hand = array_splice($deck, 0, 5);

        $state['bet'] = $bet;
        $state['credits'] -= $bet;
        $state['hand'] = $hand;
        $state['deck'] = $deck;
        $state['phase'] = 'draw';

        $this->updateState($user, $state);

        return [
            'message' => "Bet {$bet} credits. Your hand:",
            'hand' => $hand,
            'credits' => $state['credits'],
            'bet' => $bet,
            'phase' => 'draw',
            'instructions' => 'Choose cards to discard (0-5) or stand to keep all',
            'game_over' => false,
        ];
    }

    private function draw(User $user, array $state, array $data): array
    {
        if ($state['phase'] !== 'draw') {
            return ['error' => 'Not in draw phase', 'game_over' => false];
        }

        $discard = $data['discard'] ?? []; // array of indices 0-4

        // Replace discarded cards
        $deck = $state['deck'];
        $hand = $state['hand'];

        foreach ($discard as $index) {
            if ($index >= 0 && $index < 5 && count($deck) > 0) {
                $hand[$index] = array_shift($deck);
            }
        }

        $state['hand'] = $hand;
        $state['deck'] = $deck;

        return $this->evaluateHand($user, $state);
    }

    private function stand(User $user, array $state): array
    {
        if ($state['phase'] !== 'draw') {
            return ['error' => 'Not in draw phase', 'game_over' => false];
        }

        return $this->evaluateHand($user, $state);
    }

    private function evaluateHand(User $user, array $state): array
    {
        $hand = $state['hand'];
        $result = $this->getHandRank($hand);

        $payouts = [
            'Royal Flush' => 250,
            'Straight Flush' => 50,
            'Four of a Kind' => 25,
            'Full House' => 9,
            'Flush' => 6,
            'Straight' => 4,
            'Three of a Kind' => 3,
            'Two Pair' => 2,
            'Jacks or Better' => 1,
            'Nothing' => 0,
        ];

        $multiplier = $payouts[$result] ?? 0;
        $winnings = $state['bet'] * $multiplier;

        $state['credits'] += $winnings;
        $state['phase'] = 'betting';
        $state['hands_played']++;
        $state['hand'] = [];
        $state['bet'] = 0;

        $this->updateState($user, $state);

        return [
            'hand' => $hand,
            'result' => $result,
            'multiplier' => $multiplier,
            'winnings' => $winnings,
            'credits' => $state['credits'],
            'message' => $winnings > 0 ? "{$result}! Won {$winnings} credits!" : "No win. {$result}",
            'phase' => 'betting',
            'game_over' => false,
        ];
    }

    private function newHand(User $user, array $state): array
    {
        $state['phase'] = 'betting';
        $state['hand'] = [];
        $state['bet'] = 0;

        $this->updateState($user, $state);

        return [
            'message' => 'Place your bet for a new hand.',
            'credits' => $state['credits'],
            'phase' => 'betting',
            'game_over' => false,
        ];
    }

    private function getHandRank(array $hand): string
    {
        $ranks = [];
        $suits = [];

        foreach ($hand as $card) {
            $suit = substr($card, -1);
            $rank = substr($card, 0, -1);
            $ranks[] = $rank;
            $suits[] = $suit;
        }

        $rankCounts = array_count_values($ranks);
        $suitCounts = array_count_values($suits);

        $isFlush = max($suitCounts) === 5;
        $isStraight = $this->checkStraight($ranks);

        $counts = array_values($rankCounts);
        rsort($counts);

        if ($isFlush && $isStraight) {
            if (in_array('A', $ranks) && in_array('K', $ranks)) {
                return 'Royal Flush';
            }
            return 'Straight Flush';
        }

        if ($counts[0] === 4) return 'Four of a Kind';
        if ($counts[0] === 3 && $counts[1] === 2) return 'Full House';
        if ($isFlush) return 'Flush';
        if ($isStraight) return 'Straight';
        if ($counts[0] === 3) return 'Three of a Kind';
        if ($counts[0] === 2 && $counts[1] === 2) return 'Two Pair';
        if ($counts[0] === 2) {
            // Check for Jacks or Better
            foreach ($rankCounts as $rank => $count) {
                if ($count === 2 && in_array($rank, ['J', 'Q', 'K', 'A'])) {
                    return 'Jacks or Better';
                }
            }
        }

        return 'Nothing';
    }

    private function checkStraight(array $ranks): bool
    {
        $values = array_map(function ($r) {
            $map = ['A' => 14, 'K' => 13, 'Q' => 12, 'J' => 11];
            return $map[$r] ?? (int)$r;
        }, $ranks);

        sort($values);

        // Check for ace-low straight
        if ($values === [2, 3, 4, 5, 14]) {
            return true;
        }

        for ($i = 0; $i < 4; $i++) {
            if ($values[$i + 1] - $values[$i] !== 1) {
                return false;
            }
        }

        return true;
    }
}
