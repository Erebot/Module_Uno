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

class Erebot_Module_Uno_Hand
{
    protected $_player;
    protected $_cards;
    protected $_deck;

    public function __construct(&$player, &$deck, $cardsCount = 7)
    {
        $this->_player   =&  $player;
        $this->_cards    =   array();
        $this->_deck     =&  $deck;

        for ($i = 0; $i < $cardsCount; $i++)
            $this->draw();
    }

    public function & getPlayer()
    {
        return $this->_player;
    }

    public function getCards()
    {
        return $this->_cards;
    }

    public function getCardsCount()
    {
        return count($this->_cards);
    }

    public function draw()
    {
        $card = $this->_deck->draw();
        $this->_cards[] = $card;
        return $card;
    }

    public function discard($card)
    {
        $card   = $this->_deck->extractCard($card);
        $key    = array_search($card['card'], $this->_cards);
        if ($key === FALSE)
            throw new Exception();

        unset($this->_cards[$key]);
        $this->_deck->discard($card);
    }

    public function hasCard($card, $count)
    {
        if (is_array($card)) {
            if (!isset($card['card']))
                throw new Exception();
            $card = $card['card'];
        }

        $found  = array_keys($this->_cards, $card);
        return (count($found) >= $count);
    }

    public function getScore()
    {
        $score = 0;
        foreach ($this->_cards as $card) {
            if ($card == 'w' || $card == 'w+4')
                $score += 50;

            else if (!is_numeric($card[1]))
                $score += 20;

            else
                $score += (int) substr($card, 1);
        }
        return $score;
    }
}

