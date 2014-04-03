<?php
/*
    This file is part of Erebot.

    Erebot is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    Erebot is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with Erebot.  If not, see <http://www.gnu.org/licenses/>.
*/

namespace Erebot\Module\Uno;

class Game
{
    const RULES_LOOSE_DRAW              = 0x01;
    const RULES_CHAINABLE_PENALTIES     = 0x02;
    const RULES_REVERSIBLE_PENALTIES    = 0x04;
    const RULES_SKIPPABLE_PENALTIES     = 0x08;
    const RULES_CANCELABLE_PENALTIES    = 0x18;
    const RULES_UNLIMITED_DECK          = 0x20;
    const RULES_MULTIPLE_CARDS          = 0x40;

    const RULES_PENALTIES_MASK          = 0x1E;

    protected $penalty;
    protected $lastPenaltyCard;
    protected $rules;
    protected $deck;
    protected $order;
    protected $players;
    protected $startTime;
    protected $creator;
    protected $challengeable;
    protected $legalMove;
    protected $drawnCard;

    public function __construct($creator, $rules = 0)
    {
        if (is_numeric($rules)) {
            $rules = intval($rules, 0);
        } elseif (is_string($rules)) {
            $rules = self::labelsToRules($rules);
        } else {
            $rules = 0;
        }

        $deckClass              = (
            ($rules & self::RULES_UNLIMITED_DECK) ?
            '\\Erebot\\Module\\Uno\\Deck\\Unlimited' :
            '\\Erebot\\Module\\Uno\\Deck\\Official'
        );

        $this->creator          =& $creator;
        $this->penalty          = 0;
        $this->drawnCard        = null;
        $this->lastPenaltyCard  = null;
        $this->rules            = $rules;
        $this->deck             = new $deckClass();
        $this->players          = array();
        $this->startTime        = null;
        $this->challengeable    = false;
        $this->legalMove        = false;
    }

    public function & join($token)
    {
        // Determine how many cards should
        // be dealt to that new player.
        $nbPlayers = count($this->players);
        if ($nbPlayers) {
            $cardsCount = 0;
            foreach ($this->players as &$player) {
                $cardsCount += $player->getCardsCount();
            }
            unset($player);
            $cardsCount = ceil($cardsCount / $nbPlayers);
        } else {
            $cardsCount = 7;
        }

        $this->players[] = new \Erebot\Module\Uno\Hand(
            $token,
            $this->deck,
            $cardsCount
        );
        $player = end($this->players);
        if (count($this->players) == 2) {
            $this->startTime = time();
            shuffle($this->players);
        }
        return $player;
    }

    public static function labelsToRules($labels)
    {
        if (!is_string($labels)) {
            throw new \Erebot\InvalidValueException('Invalid ruleset');
        }

        $rulesMapping   = array(
            'loose_draw'    => self::RULES_LOOSE_DRAW,
            'chainable'     => self::RULES_CHAINABLE_PENALTIES,
            'reversible'    => self::RULES_REVERSIBLE_PENALTIES,
            'skippable'     => self::RULES_SKIPPABLE_PENALTIES,
            // Both spellings are correct, but we prefer 'cancelable'.
            'cancelable'    => self::RULES_CANCELABLE_PENALTIES,
            'cancellable'   => self::RULES_CANCELABLE_PENALTIES,
            'unlimited'     => self::RULES_UNLIMITED_DECK,
            'multiple'      => self::RULES_MULTIPLE_CARDS,
        );

        $rules  = 0;
        $labels = strtolower($labels);
        $labels = explode(',', str_replace(' ', ',', $labels));

        foreach ($labels as $label) {
            $label = trim($label);
            if (isset($rulesMapping[$label])) {
                $rules |= $rulesMapping[$label];
            }
        }
        return $rules;
    }

    public static function rulesToLabels($rules)
    {
        $labels         =   array();
        $rulesMapping   =   array(
            'loose_draw'    => self::RULES_LOOSE_DRAW,
            'chainable'     => self::RULES_CHAINABLE_PENALTIES,
            'reversible'    => self::RULES_REVERSIBLE_PENALTIES,
            'cancelable'    => self::RULES_CANCELABLE_PENALTIES,
            'unlimited'     => self::RULES_UNLIMITED_DECK,
            'multiple'      => self::RULES_MULTIPLE_CARDS,
        );

        foreach ($rulesMapping as $label => $mask) {
            if (($rules & $mask) == $mask) {
                $labels[] = $label;
            }
        }

        // 'skippable' is a subcase of 'cancelable'
        // and is therefore treated separately.
        $skippable = self::RULES_SKIPPABLE_PENALTIES;
        $cancelable = self::RULES_CANCELABLE_PENALTIES;
        if (($rules & $skippable) == $skippable &&
            ($rules & $cancelable) != $cancelable) {
            $labels[] = 'skippable';
        }

        sort($labels);
        return $labels;
    }

    public static function extractCard($card, $withColor)
    {
        $card           = strtolower($card);
        $wildPattern    = '/^(w\\+4|w)(';
        if ($withColor !== false) {
            $wildPattern .= '[rbgy]';
        }
        if ($withColor === null) {
            $wildPattern .= '?';
        }
        $wildPattern  .= ')$/';

        if (preg_match($wildPattern, $card, $matches)) {
            return array(
                'card'  => $matches[1],
                'color' => $matches[2],
                'count' => 1,
            );
        }

        if (preg_match('/^([rbgy])([0-9]|\\+2)(?:\\1\\2)*$/', $card, $matches)) {
            $count = strlen($card) / (strlen($matches[1]) + strlen($matches[2]));
            return array(
                'card'  => $matches[1].$matches[2],
                'color' => $matches[1],
                'count' => $count,
            );
        }

        if (preg_match('/^([rbgy])([rs])(?:\\1\\2)*$/', $card, $matches)) {
            $count = strlen($card) / (strlen($matches[1]) + strlen($matches[2]));
            return array(
                'card'  => $matches[1].$matches[2],
                'color' => $matches[1],
                'count' => $count,
            );
        }

        return null;
    }

    public function play($card)
    {
        if ($this->deck->isWaitingForColor()) {
            throw new \Erebot\Module\Uno\WaitingForColorException();
        }

        $card       = strtolower($card);
        $savedCard  = $card;
        $card       = self::extractCard($card, null);
        $player     = $this->getCurrentPlayer();

        if ($card === null) {
            throw new \Erebot\Module\Uno\InvalidMoveException('Not a valid card');
        }

        $figure = substr($card['card'], 1);

        // Trying to play multiple reverse/skip
        // at once in a non 1-vs-1 game.
        if (strlen($card['card']) == 2 && count($this->players) != 2 &&
            strpos($figure, 'rs') !== false && $card['count'] > 1) {
            throw new \Erebot\Module\Uno\MoveNotAllowedException(
                'You cannot play multiple reverses/skips in a non 1vs1 game',
                \Erebot\Module\Uno\MoveNotAllowedException::MULTIPLE_1VS1
            );
        }

        // Trying to play multiple cards at once.
        if (!($this->rules & self::RULES_MULTIPLE_CARDS) && $card['count'] > 1) {
            throw new \Erebot\Module\Uno\MoveNotAllowedException(
                'You cannot play multiple cards',
                \Erebot\Module\Uno\MoveNotAllowedException::MULTIPLE_CARDS
            );
        }

        if (!($this->rules & self::RULES_LOOSE_DRAW) &&
            $this->drawnCard !== null && $card['card'] != $this->drawnCard) {
            throw new \Erebot\Module\Uno\MoveNotAllowedException(
                'You may only play the card you just drew',
                \Erebot\Module\Uno\MoveNotAllowedException::ONLY_DRAWN
            );
        }

        $discard = $this->deck->getLastDiscardedCard();
        if ($discard !== null && !$player->hasCard($card['card'], $card['count'])) {
            throw new \Erebot\Module\Uno\MissingCardsException();
        }

        do {
            // No card has been played yet,
            // so anything is acceptable.
            if ($discard === null) {
                break;
            }

            if ($this->penalty) {
                $colors     = str_split('bryg');
                $allowed    = array();
                $discFig    = substr($discard['card'], 1);
                $penFig     = substr($this->lastPenaltyCard['card'], 1);

                if ($discFig == 'r') {
                    if ($this->rules & self::RULES_REVERSIBLE_PENALTIES) {
                        foreach ($colors as $color) {
                            $allowed[] = $color.'r';
                        }
                    }

                    // Also takes care of self::RULES_CANCELABLE_PENALTIES.
                    if ($this->rules & self::RULES_SKIPPABLE_PENALTIES) {
                        $allowed[] = $this->lastPenaltyCard['color'].'s';
                    }
                } elseif ($discFig == 's') {
                    // Also takes care of self::RULES_CANCELABLE_PENALTIES.
                    if ($this->rules & self::RULES_SKIPPABLE_PENALTIES) {
                        foreach ($colors as $color) {
                            $allowed[] = $color.'s';
                        }
                    }

                    if ($this->rules & self::RULES_REVERSIBLE_PENALTIES) {
                        $allowed[] = $this->lastPenaltyCard['color'].'r';
                    }
                } elseif (!strcmp($penFig, '+2')) {
                    if ($this->rules & self::RULES_CHAINABLE_PENALTIES) {
                        $allowed[] = 'w+4';
                        foreach ($colors as $color) {
                            $allowed[] = $color.'+2';
                        }
                    }

                    if ($this->rules & self::RULES_REVERSIBLE_PENALTIES) {
                        $allowed[] = $this->lastPenaltyCard['color'].'r';
                    }

                    // Also takes care of self::RULES_SKIPPABLE_PENALTIES.
                    if ($this->rules & self::RULES_CANCELABLE_PENALTIES) {
                        $allowed[] = $this->lastPenaltyCard['color'].'s';
                    }
                } elseif (!strcmp($penFig, '+4')) {
                    if ($this->rules & self::RULES_CHAINABLE_PENALTIES) {
                        $allowed[] = 'w+4';
                    }

                    if ($this->rules & self::RULES_REVERSIBLE_PENALTIES) {
                        $allowed[] = $this->lastPenaltyCard['color'].'r';
                    }

                    // Also takes care of self::RULES_SKIPPABLE_PENALTIES.
                    if ($this->rules & self::RULES_CANCELABLE_PENALTIES) {
                        $allowed[] = $this->lastPenaltyCard['color'].'s';
                    }
                }

                if (!in_array($card['card'], $allowed)) {
                    throw new \Erebot\Module\Uno\MoveNotAllowedException(
                        'You may not play that move now',
                        \Erebot\Module\Uno\MoveNotAllowedException::NOT_PLAYABLE,
                        $allowed
                    );
                }
            }

            if ($card['card'][0] == 'w') {
                break;  // Wilds.
            }

            if ($card['color'] == $discard['color']) {
                break;  // Same color.
            }

            if ($figure == substr($discard['card'], 1)) {
                break;  // Same figure.
            }

            throw new \Erebot\Module\Uno\MoveNotAllowedException(
                'This move is not allowed',
                \Erebot\Module\Uno\MoveNotAllowedException::NOT_PLAYABLE
            );
        } while (0);

        // Remember last played penalty card.
        $this->challengeable = false;
        if ($card['card'] == 'w+4') {
            $this->lastPenaltyCard = $card;
            $this->penalty += 4;
            if ($this->penalty == 4) {
                $this->challengeable = true;
            }
        } elseif (!strcmp($figure, '+2')) {
            $this->lastPenaltyCard = $card;
            $this->penalty += 2 * $card['count'];
        }

        // If at least one card was played before.
        if ($discard !== null) {
            $this->legalMove = self::isLegalMove(
                $discard['color'],
                $player->getCards()
            );

            // Remove those cards from the player's hand.
            for ($i = 0; $i < $card['count']; $i++) {
                $player->discard($savedCard);
            }
        } else {
            // No card has been played yet, anything is acceptable.
            $this->deck->discard($savedCard);
        }

        $changePlayer = true;
        $skippedPlayer = null;
        if ($figure == 'r') {
            if (count($this->players) > 2 || ($this->penalty &&
                ($this->rules & self::RULES_REVERSIBLE_PENALTIES))) {
                $this->players = array_reverse($this->players);
            } else {
                $skippedPlayer = $this->getLastPlayer();
            }
            $changePlayer = false;
        } elseif ($figure == 's') {
            if ($this->penalty) {
                if (($this->rules & self::RULES_CANCELABLE_PENALTIES) ==
                    self::RULES_CANCELABLE_PENALTIES) {
                    // The penalty gets canceled.
                    $this->penalty = 0;
                }
            } else {
                // Regular skip.
                $this->endTurn(true);
                $skippedPlayer = $this->getCurrentPlayer();
            }
        } elseif ($card['card'][0] == 'w' && empty($card['color'])) {
            throw new \Erebot\Module\Uno\WaitingForColorException();
        }

        $this->endTurn($changePlayer);
        return $skippedPlayer;
    }

    public function chooseColor($color)
    {
        $this->deck->chooseColor($color);
        $this->endTurn(true);
    }

    public function draw()
    {
        if ($this->deck->isWaitingForColor()) {
            throw new \Erebot\Module\Uno\WaitingForColorException();
        }

        if ($this->drawnCard !== null) {
            throw new \Erebot\Module\Uno\AlreadyDrewException();
        }

        // Draw = pass when a penalty is at stake.
        if ($this->penalty) {
            $this->drawnCard = true;
            return $this->pass();
        } else {
            // Otherwise, it's a normal card draw.
            $player = $this->getCurrentPlayer();
            $this->drawnCard = $player->draw();
            return array($this->drawnCard);
        }
    }

    public function pass()
    {
        if ($this->deck->isWaitingForColor()) {
            throw new \Erebot\Module\Uno\WaitingForColorException();
        }

        if ($this->drawnCard === null && !$this->penalty) {
            throw new \Erebot\Module\Uno\MustDrawBeforePassException();
        }

        // Draw the penalty.
        $player = $this->getCurrentPlayer();
        $drawnCards = array();
        for (; $this->penalty > 0; $this->penalty--) {
            $drawnCards[] = $player->draw();
        }
        unset($player);

        $this->endTurn(true);
        return $drawnCards;
    }

    protected function endTurn($changePlayer)
    {
        if ($changePlayer) {
            $last = array_shift($this->players);
            $this->players[] =& $last;
        }

        $this->drawnCard = null;
    }

    public function challenge()
    {
        if (!$this->challengeable) {
            throw new \Erebot\Module\Uno\UnchallengeableException();
        }

        $target = $this->getLastPlayer();
        if (!$target) {
            throw new \Erebot\Module\Uno\UnchallengeableException();
        }

        $hand   = $target->getCards();
        $legal  = $this->legalMove;
        if ($legal) {
            $target = $this->getCurrentPlayer();
            $this->penalty += 2;
            $this->endTurn(true);
        }

        $drawnCards = array();
        for (; $this->penalty > 0; $this->penalty--) {
            $drawnCards[] = $target->draw();
        }

        $this->challengeable = false;

        return array(
            'legal' => $legal,
            'cards' => $drawnCards,
            'hand'  => $hand,
        );
    }

    protected static function isLegalMove($color, $cards)
    {
        foreach ($cards as &$card) {
            $infos = self::extractCard($card, false);
            if ($infos['color'] == $color && is_numeric($infos['card'][1])) {
                return false;
            }
        }
        unset($card);
        return true;
    }

    public function getCurrentPlayer()
    {
        return reset($this->players);
    }

    public function getLastPlayer()
    {
        if (!$this->getLastPlayedCard()) {
            return null;
        }
        return end($this->players);
    }

    public function getRules($asText)
    {
        if ($asText) {
            return $this->rulesToLabels($this->rules);
        }
        return $this->rules;
    }

    public function & getCreator()
    {
        return $this->creator;
    }

    public function getElapsedTime()
    {
        if ($this->startTime === null) {
            return null;
        }
        return time() - $this->startTime;
    }

    public function getPenalty()
    {
        return $this->penalty;
    }

    public function getLastPlayedCard()
    {
        return $this->deck->getLastDiscardedCard();
    }

    public function & getPlayers()
    {
        return $this->players;
    }

    public function getFirstCard()
    {
        return $this->deck->getFirstCard();
    }

    public function getRemainingCardsCount()
    {
        $count = $this->deck->getRemainingCardsCount();
        if (!is_int($count) || $count < 0) {
            return null;
        }
        return $count;
    }
}
