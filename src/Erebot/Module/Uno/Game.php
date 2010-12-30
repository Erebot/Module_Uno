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

class Erebot_Module_Uno_Game
{
    const RULES_LOOSE_DRAW              = 0x01;
    const RULES_CHAINABLE_PENALTIES     = 0x02;
    const RULES_REVERSIBLE_PENALTIES    = 0x04;
    const RULES_SKIPPABLE_PENALTIES     = 0x08;
    const RULES_CANCELABLE_PENALTIES    = 0x18;
    const RULES_UNLIMITED_DECK          = 0x20;
    const RULES_MULTIPLE_CARDS          = 0x40;

    protected $_penalty;
    protected $_lastPenaltyCard;
    protected $_rules;
    protected $_deck;
    protected $_order;
    protected $_players;
    protected $_startTime;
    protected $_creator;
    protected $_challengeable;
    protected $_legalMove;
    protected $_drawnCard;

    public function __construct($creator, $rules = 0)
    {
        if (is_numeric($rules))
            $rules = intval($rules, 0);
        else if (is_string($rules))
            $rules = self::labelsToRules($rules);
        else
            $rules = 0;

        $this->_creator         =&  $creator;
        $this->_penalty         =   0;
        $this->_drawnCard       =   NULL;
        $this->_lastPenaltyCard =   NULL;
        $this->_rules           =   $rules;
        $deckClass              =   (
                                        ($rules & self::RULES_UNLIMITED_DECK) ?
                                        'Erebot_Module_Uno_Deck_Unlimited' :
                                        'Erebot_Module_Uno_Deck_Official'
                                    );
        $this->_deck            =   new $deckClass();
        $this->_players         =   array();
        $this->_startTime       =   NULL;
        $this->_challengeable   =   FALSE;
        $this->_legalMove       =   FALSE;
    }

    public function __destruct()
    {
        
    }

    public function & join($token)
    {
        // Determine how many cards should
        // be dealt to that new player.
        $nbPlayers = count($this->_players);
        if ($nbPlayers) {
            $cardsCount = 0;
            foreach ($this->_players as &$player) {
                $cardsCount += $player->getCardsCount();
            }
            unset($player);
            $cardsCount = ceil($cardsCount / $nbPlayers);
        }
        else
            $cardsCount = 7;

        $this->_players[] = new Erebot_Module_Uno_Hand(
            $token,
            $this->_deck,
            $cardsCount
        );
        $player             = end($this->_players);
        if (count($this->_players) == 2) {
            $this->_startTime = time();
            shuffle($this->_players);
        }
        return $player;
    }

    public static function labelsToRules($labels)
    {
        if (!is_string($labels))
            throw new Erebot_InvalidValueException('Invalid ruleset');

        $rulesMapping   =   array(
                                'loose_draw'    => self::RULES_LOOSE_DRAW,
                                'chainable'     => self::RULES_CHAINABLE_PENALTIES,
                                'reversible'    => self::RULES_REVERSIBLE_PENALTIES,
                                'skippable'     => self::RULES_SKIPPABLE_PENALTIES,
                                'cancelable'    => self::RULES_CANCELABLE_PENALTIES,    // Both spellings are correct,
                                'cancellable'   => self::RULES_CANCELABLE_PENALTIES,    // but we prefer 'cancelable'.
                                'unlimited'     => self::RULES_UNLIMITED_DECK,
                                'multiple'      => self::RULES_MULTIPLE_CARDS,
                            );

        $rules  = 0;
        $labels = strtolower($labels);
        $labels = explode(',', str_replace(' ', ',', $labels));

        foreach ($labels as $label) {
            $label = trim($label);
            if (isset($rulesMapping[$label]))
                $rules |= $rulesMapping[$label];
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
            if (($rules & $mask) == $mask)
                $labels[] = $label;
        }

        // 'skippable' is a subcase of 'cancelable'
        // and is therefore treated separately.
        if (($rules & self::RULES_SKIPPABLE_PENALTIES) == self::RULES_SKIPPABLE_PENALTIES &&
            ($rules & self::RULES_CANCELABLE_PENALTIES) != self::RULES_CANCELABLE_PENALTIES)
            $labels[] = 'skippable';

        sort($labels);
        return $labels;
    }

    static public function extractCard($card, $withColor)
    {
        $card       = strtolower($card);

        $wildPattern   = '/^(w\\+4|w)(';
        if ($withColor !== FALSE)
            $wildPattern .= '[rbgy]';
        if ($withColor === NULL)
            $wildPattern .= '?';
        $wildPattern  .= ')$/';

        if (preg_match($wildPattern, $card, $matches)) {
            return array(
                'card'  => $matches[1],
                'color' => $matches[2],
                'count' => 1,
            );
        }

        if (preg_match('/^([rbgy])([0-9]|\\+2)(?:\\1\\2)*$/', $card, $matches)) {
            $count  = strlen($card) / (strlen($matches[1]) + strlen($matches[2]));
            return array(
                'card'  => $matches[1].$matches[2],
                'color' => $matches[1],
                'count' => $count,
            );
        }

        if (preg_match('/^([rbgy])([rs])(?:\\1\\2)*$/', $card, $matches)) {
            $count  = strlen($card) / (strlen($matches[1]) + strlen($matches[2]));
            return array(
                'card'  => $matches[1].$matches[2],
                'color' => $matches[1],
                'count' => $count,
            );
        }

        return NULL;
    }

    public function play($card)
    {
        if ($this->_deck->isWaitingForColor())
            throw new Erebot_Module_Uno_WaitingForColorException();

        $card       = strtolower($card);
        $savedCard  = $card;
        $card       = self::extractCard($card, NULL);
        $player     = $this->getCurrentPlayer();

        if ($card === NULL) {
            throw new Erebot_Module_Uno_InvalidMoveException('Not a valid card');
        }

        $figure = substr($card['card'], 1);

        // Trying to play multiple reverse/skip
        // at once in a non 1-vs-1 game.
        if (strlen($card['card']) == 2 && count($this->_players) != 2 &&
            strpos($figure, 'rs') !== FALSE && $card['count'] > 1)
            throw new Erebot_Module_Uno_MoveNotAllowedException(
                'You cannot play multiple reverses/skips in a non 1vs1 game', 1);

        // Trying to play multiple cards at once.
        if (!($this->_rules & self::RULES_MULTIPLE_CARDS) && $card['count'] > 1)
            throw new Erebot_Module_Uno_MoveNotAllowedException(
                'You cannot play multiple cards', 2);

        if (!($this->_rules & self::RULES_LOOSE_DRAW) &&
            $this->_drawnCard !== NULL && $card['card'] != $this->_drawnCard)
            throw new Erebot_Module_Uno_MoveNotAllowedException(
                'You may only play the card you just drew', 3);

        $discard = $this->_deck->getLastDiscardedCard();
        if ($discard !== NULL &&
            !$player->hasCard($card['card'], $card['count']))
            throw new Erebot_Module_Uno_MissingCardsException();

        do {
            // No card has been played yet,
            // so anything is acceptable.
            if ($discard === NULL)
                break;

            if ($this->_penalty) {
                $colors     = str_split('bryg');
                $allowed    = array();
                $discFig    = substr($discard['card'], 1);
                $penFig     = substr($this->_lastPenaltyCard['card'], 1);

                if ($discFig == 'r') {
                    if ($this->_rules & self::RULES_REVERSIBLE_PENALTIES)
                        foreach ($colors as $color)
                            $allowed[] = $color.'r';

                    // Also takes care of self::RULES_CANCELABLE_PENALTIES.
                    if ($this->_rules & self::RULES_SKIPPABLE_PENALTIES)
                        $allowed[] = $this->_lastPenaltyCard['color'].'s';
                }

                else if ($discFig == 's') {
                    // Also takes care of self::RULES_CANCELABLE_PENALTIES.
                    if ($this->_rules & self::RULES_SKIPPABLE_PENALTIES)
                        foreach ($colors as $color)
                            $allowed[] = $color.'s';

                    if ($this->_rules & self::RULES_REVERSIBLE_PENALTIES)
                        $allowed[] = $this->_lastPenaltyCard['color'].'r';
                }

                else if (!strcmp($penFig, '+2')) {
                    if ($this->_rules & self::RULES_CHAINABLE_PENALTIES) {
                        $allowed[] = 'w+4';
                        foreach ($colors as $color)
                            $allowed[] = $color.'+2';
                    }

                    if ($this->_rules & self::RULES_REVERSIBLE_PENALTIES)
                        $allowed[] = $this->_lastPenaltyCard['color'].'r';

                    // Also takes care of self::RULES_SKIPPABLE_PENALTIES.
                    if ($this->_rules & self::RULES_CANCELABLE_PENALTIES)
                        $allowed[] = $this->_lastPenaltyCard['color'].'s';
                }

                else if (!strcmp($penFig, '+4')) {
                    if ($this->_rules & self::RULES_CHAINABLE_PENALTIES)
                        $allowed[] = 'w+4';

                    if ($this->_rules & self::RULES_REVERSIBLE_PENALTIES)
                        $allowed[] = $this->_lastPenaltyCard['color'].'r';

                    // Also takes care of self::RULES_SKIPPABLE_PENALTIES.
                    if ($this->_rules & self::RULES_CANCELABLE_PENALTIES)
                        $allowed[] = $this->_lastPenaltyCard['color'].'s';
                }

                if (!in_array($card['card'], $allowed))
                    throw new Erebot_Module_Uno_MoveNotAllowedException(
                        'You may not play that move now', 4, $allowed);
            }

            if ($card['card'][0] == 'w')
                break;  // Wilds.

            if ($card['color'] == $discard['color'])
                break;  // Same color.

            if ($figure == substr($discard['card'], 1))
                break;  // Same figure.

            throw new Erebot_Module_Uno_MoveNotAllowedException('This move is not allowed', 3);
        } while (0);

        // Remember last played penalty card.
        $this->_challengeable = FALSE;
        if ($card['card'] == 'w+4') {
            $this->_lastPenaltyCard = $card;
            $this->_penalty += 4;
            if ($this->_penalty == 4)
                $this->_challengeable = TRUE;
        }
        else if (!strcmp($figure, '+2')) {
            $this->_lastPenaltyCard = $card;
            $this->_penalty += 2 * $card['count'];
        }

        // If at least one card was played before.
        if ($discard !== NULL) {
            $this->_legalMove = self::isLegalMove(
                $discard['color'],
                $player->getCards()
            );

            // Remove those cards from the player's hand.
            for ($i = 0; $i < $card['count']; $i++)
                $player->discard($savedCard);
        }
        // No card has been played yet, anything is acceptable.
        else
            $this->_deck->discard($savedCard);

        $changePlayer = TRUE;
        $skippedPlayer = NULL;
        if ($figure == 'r') {
            if (count($this->_players) > 2 || ($this->_penalty &&
                    ($this->_rules & self::RULES_REVERSIBLE_PENALTIES)))
                $this->_players = array_reverse($this->_players);
            else
                $skippedPlayer = $this->getLastPlayer();
            $changePlayer = FALSE;
        }

        else if ($figure == 's') {
            if ($this->_penalty) {
                if (($this->_rules & self::RULES_CANCELABLE_PENALTIES) ==
                        self::RULES_CANCELABLE_PENALTIES) {
                    // The penalty gets canceled.
                    $this->_penalty = 0;
                }
            }
            // Regular skip.
            else {
                $this->endTurn(TRUE);
                $skippedPlayer = $this->getCurrentPlayer();
            }
        }

        else if ($card['card'][0] == 'w' && empty($card['color']))
            throw new Erebot_Module_Uno_WaitingForColorException();

        $this->endTurn($changePlayer);
        return $skippedPlayer;
    }

    public function chooseColor($color)
    {
        $this->_deck->chooseColor($color);
        $this->endTurn(TRUE);
    }

    public function draw()
    {
        if ($this->_deck->isWaitingForColor())
            throw new Erebot_Module_Uno_WaitingForColorException();

        if ($this->_drawnCard !== NULL)
            throw new Erebot_Module_Uno_AlreadyDrewException();

        // Draw = pass when a penalty is at stake.
        if ($this->_penalty) {
            $this->_drawnCard = TRUE;
            return $this->pass();
        }

        // Otherwise, it's a normal card draw.
        else {
            $player = $this->getCurrentPlayer();
            $this->_drawnCard = $player->draw();
            return array($this->_drawnCard);
        }
    }

    public function pass()
    {
        if ($this->_deck->isWaitingForColor())
            throw new Erebot_Module_Uno_WaitingForColorException();

        if ($this->_drawnCard === NULL && !$this->_penalty)
            throw new Erebot_Module_Uno_MustDrawBeforePassException();

        // Draw the penalty.
        $player = $this->getCurrentPlayer();
        $drawnCards = array();
        for (; $this->_penalty > 0; $this->_penalty--)
            $drawnCards[] = $player->draw();
        unset($player);

        $this->endTurn(TRUE);
        return $drawnCards;
    }

    protected function endTurn($changePlayer)
    {
        if ($changePlayer) {
            $last = array_shift($this->_players);
            $this->_players[] =& $last;
        }

        $this->_drawnCard = NULL;
    }

    public function challenge()
    {
        if (!$this->_challengeable)
            throw new Erebot_Module_Uno_UnchallengeableException();

        $target = $this->getLastPlayer();
        if (!$target)
            throw new Erebot_Module_Uno_UnchallengeableException();

        $hand   = $target->getCards();
        $legal  = $this->_legalMove;
        if ($legal) {
            $target = $this->getCurrentPlayer();
            $this->_penalty += 2;
            $this->endTurn(TRUE);
        }

        $drawnCards = array();
        for (; $this->_penalty > 0; $this->_penalty--)
            $drawnCards[] = $target->draw();

        $this->_challengeable = FALSE;

        return array(
            'legal' => $legal,
            'cards' => $drawnCards,
            'hand'  => $hand,
        );
    }

    static protected function isLegalMove($color, $cards)
    {
        foreach ($cards as &$card) {
            $infos = self::extractCard($card, FALSE);
            if ($infos['color'] == $color && is_numeric($infos['card'][1]))
                return FALSE;
        }
        unset($card);
        return TRUE;
    }

    public function getCurrentPlayer()
    {
        return reset($this->_players);
    }

    public function getLastPlayer()
    {
        if (!$this->getLastPlayedCard())
            return NULL;

        return end($this->_players);
    }

    public function getRules($asText)
    {
        if ($asText)
            return $this->rulesToLabels($this->_rules);
        return $this->_rules;
    }

    public function & getCreator()
    {
        return $this->_creator;
    }

    public function getElapsedTime()
    {
        if ($this->_startTime === NULL)
            return NULL;

        return time() - $this->_startTime;
    }

    public function getPenalty()
    {
        return $this->_penalty;
    }

    public function getLastPlayedCard()
    {
        return $this->_deck->getLastDiscardedCard();
    }

    public function & getPlayers()
    {
        return $this->_players;
    }

    public function getFirstCard()
    {
        return $this->_deck->getFirstCard();
    }

    public function getRemainingCardsCount()
    {
        $count = $this->_deck->getRemainingCardsCount();
        if (!is_int($count) || $count < 0)
            return NULL;
        return $count;
    }
}

# vim: et ts=4 sts=4 sw=4
