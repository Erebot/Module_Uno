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

class   Erebot_Module_Uno_Deck_Unlimited
extends Erebot_Module_Uno_Deck_Abstract
{
    protected $_discarded;
    protected $_firstCard;

    public function __construct()
    {
        $this->_discarded = NULL;
        $this->chooseFirstCard();
    }

    protected function chooseFirstCard()
    {
        // Find the first (playable) card.
        for ($this->_firstCard = $this->draw();
            $this->_firstCard[0] == 'w';
            )
            ;
    }

    public function draw()
    {
        $card   = mt_rand(0, 108);

        if ($card >= 104)
            return 'w+4';
        if ($card >= 100)
            return 'w';

        $colors = array('r', 'g', 'b', 'y');
        $perCol = 18    // cards from 1 to 9 (2 each)
                + 1     // 0-card
                + 2     // draw two
                + 2     // reverse
                + 2;    // skip
        $color  = $colors[(int) $card / $perCol];
        $card  %= $perCol;

        if ($card < 18)
            return $color.(($card % 9) + 1);
        if ($card < 19)
            return $color.'0';
        if ($card < 21)
            return $color.'+2';
        if ($card < 23)
            return $color.'r';
        if ($card < 25)
            return $color.'s';

        throw new Erebot_Module_Uno_InternalErrorException();
    }

    public function discard($card)
    {
        parent::discard($card);
        $this->_discarded = $this->extractCard($card);
    }

    public function shuffle()
    {
        // Nothing to do.
    }

    public function getLastDiscardedCard()
    {
        return $this->_discarded;
    }

    public function getRemainingCardsCount()
    {
        return NULL;
    }

    public function chooseColor($color)
    {
        parent::chooseColor($color);
        $last = $this->getLastDiscardedCard();
        $last['color']      = $color;
        $this->_discarded   = $last;
    }
}

