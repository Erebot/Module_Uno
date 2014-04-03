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

abstract class AbstractDeck
{
    protected $firstCard = null;
    protected $waitingForColor = false;

    public function extractCard($card)
    {
        if (is_array($card)) {
            if (!isset($card['card'])) {
                throw new \Erebot\Module\Uno\InvalidMoveException();
            }
            return $card;
        }
        $card = \Erebot\Module\Uno\Game::extractCard($card, null);
        if ($card === null) {
            throw new \Erebot\Module\Uno\InvalidMoveException();
        }
        return $card;
    }

    final public function getFirstCard()
    {
        return $this->firstCard;
    }

    final public function isValidColor($color)
    {
        return  (strlen($color) == 1 && strpos('rgby', $color) !== false);
    }

    final public function isWaitingForColor()
    {
        return $this->waitingForColor;
    }

    abstract protected function chooseFirstCard();

    abstract public function draw();
    abstract public function shuffle();
    abstract public function getLastDiscardedCard();
    abstract public function getRemainingCardsCount();

    public function chooseColor($color)
    {
        $color = strtolower($color);
        if (!$this->isValidColor($color)) {
            throw new \Erebot\Module\Uno\InternalErrorException();
        }

        $last = $this->getLastDiscardedCard();
        if ($last === null) {
            throw new \Erebot\Module\Uno\InternalErrorException();
        }

        if ($last['card'][0] != 'w' || !empty($last['color'])) {
            throw new \Erebot\Module\Uno\InternalErrorException();
        }

        $this->waitingForColor = false;
    }

    public function discard($card)
    {
        if ($this->waitingForColor) {
            throw new \Erebot\Module\Uno\WaitingForColorException();
        }

        $card = $this->extractCard($card);
        if ($card['color'] === null) {
            $this->waitingForColor = true;
        }
    }
}
