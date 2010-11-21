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

class   Erebot_Module_Uno_Deck_Official
extends Erebot_Module_Uno_Deck_Abstract
{
    protected $cards;
    protected $discarded;
    protected $firstCard;

    public function __construct()
    {
        $colors             = str_split('rbgy');
        $this->discarded    = array();
        $this->cards        = array();

        // Add colored cards.
        foreach ($colors as $color) {
            $this->discarded[] = array('card' => $color.'0');
            for ($i = 0; $i < 2; $i++) {
                $this->discarded[] = array('card' => $color.'r');
                $this->discarded[] = array('card' => $color.'s');
                $this->discarded[] = array('card' => $color.'+2');
                for ($j = 1; $j <= 9; $j++)
                    $this->discarded[] = array('card' => $color.$j);
            }
        }

        // Add wilds.
        for ($i = 0; $i < 4; $i++) {
            $this->discarded[] = array('card' => 'w');
            $this->discarded[] = array('card' => 'w+4');
        }

        // Shuffle cards.
        $this->shuffle();

        $this->chooseFirstCard();
    }

    protected function chooseFirstCard()
    {
        // Find the first (playable) card.
        for ($this->firstCard = reset($this->cards);
            $this->firstCard[0] == 'w';
            $this->firstCard = next($this->cards))
            ;

        unset($this->cards[key($this->cards)]);
    }

    public function draw()
    {
        if (!count($this->cards))
            throw new Erebot_Module_Uno_EmptyDeckException();
        return array_shift($this->cards);
    }

    public function discard($card)
    {
        parent::discard($card);
        array_unshift($this->discarded, $this->extractCard($card));
    }

    static private function __getCard($a)
    {
        return $a['card'];
    }

    public function shuffle()
    {
        if (count($this->cards))
            throw new Erebot_Module_Uno_InternalErrorException();

        $this->cards        = array_map(
            array($this, '__getCard'),
            $this->discarded
        );
        shuffle($this->cards);
        $this->discarded    = array();
    }

    public function getLastDiscardedCard()
    {
        if (!count($this->discarded))
            return NULL;
        return $this->discarded[0];
    }

    public function getRemainingCardsCount()
    {
        return count($this->cards);
    }

    public function chooseColor($color)
    {
        parent::chooseColor($color);
        $last = $this->getLastDiscardedCard();
        $last['color']      = $color;
        $this->discarded[0] = $last;
    }
}

