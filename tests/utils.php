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

require_once(
    dirname(__FILE__) .
    DIRECTORY_SEPARATOR . 'testenv' .
    DIRECTORY_SEPARATOR . 'bootstrap.php'
);

class   UnoStub
extends Erebot_Module_Uno_Game
{
    public function __construct($creator, $rules = 0)
    {
        parent::__construct($creator, $rules);
        unset($this->deck);
        $this->deck = new UnoDeckStub();
    }

    public function & join($player)
    {
        $this->players[]    = new UnoHandStub($player, $this->deck);
        $token              = end($this->players);
        if (count($this->players) == 2)
            $this->startTime = time();
        return $token;
    }
}

class   UnoStub2
extends Erebot_Module_Uno_Game
{}

class   UnoDeckStub
extends Erebot_Module_Uno_Deck_Official
{
    protected function chooseFirstCard()
    {
        $this->firstCard = NULL;
    }
}

class   UnoHandStub
extends Erebot_Module_Uno_Hand
{
    public function hasCard($card, $count)
    {
        return TRUE;
    }

    public function discard($card)
    {
        if (is_array($card)) {
            if (!isset($card['card']))
                throw new Exception();
            $card = $card['card'];
        }

        $this->deck->discard($card);
    }
}

?>
