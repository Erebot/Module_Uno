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
    protected $_cards;
    protected $_discarded;
    protected $_firstCard;

    public function __construct()
    {
        $colors             = str_split('rbgy');
        $this->_discarded   = array();
        $this->_cards       = array();

        // Add colored cards.
        foreach ($colors as $color) {
            $this->_discarded[] = array('card' => $color.'0');
            for ($i = 0; $i < 2; $i++) {
                $this->_discarded[] = array('card' => $color.'r');
                $this->_discarded[] = array('card' => $color.'s');
                $this->_discarded[] = array('card' => $color.'+2');
                for ($j = 1; $j <= 9; $j++)
                    $this->_discarded[] = array('card' => $color.$j);
            }
        }

        // Add wilds.
        for ($i = 0; $i < 4; $i++) {
            $this->_discarded[] = array('card' => 'w');
            $this->_discarded[] = array('card' => 'w+4');
        }

        // Shuffle cards.
        $this->shuffle();

        $this->chooseFirstCard();
    }

    protected function chooseFirstCard()
    {
        // Find the first (playable) card.
        for ($this->_firstCard = reset($this->_cards);
            $this->_firstCard[0] == 'w';
            $this->_firstCard = next($this->_cards))
            ;

        unset($this->_cards[key($this->_cards)]);
    }

    public function draw()
    {
        if (!count($this->_cards))
            throw new Erebot_Module_Uno_EmptyDeckException();
        return array_shift($this->_cards);
    }

    public function discard($card)
    {
        parent::discard($card);
        array_unshift($this->_discarded, $this->extractCard($card));
    }

    static private function __getCard($a)
    {
        return $a['card'];
    }

    public function shuffle()
    {
        if (count($this->_cards))
            throw new Erebot_Module_Uno_InternalErrorException();

        $this->_cards       = array_map(
            array($this, '__getCard'),
            $this->_discarded
        );
        shuffle($this->_cards);
        $this->_discarded    = array();
    }

    public function getLastDiscardedCard()
    {
        if (!count($this->_discarded))
            return NULL;
        return $this->_discarded[0];
    }

    public function getRemainingCardsCount()
    {
        return count($this->_cards);
    }

    public function chooseColor($color)
    {
        parent::chooseColor($color);
        $last = $this->getLastDiscardedCard();
        $last['color']          = $color;
        $this->_discarded[0]    = $last;
    }
}

