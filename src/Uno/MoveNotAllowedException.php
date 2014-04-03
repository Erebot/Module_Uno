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

class MoveNotAllowedException extends \Erebot\Module\Uno\Exception
{
    const NOT_ALLOWED = 0;
    const MULTIPLE_1VS1 = 1;
    const MULTIPLE_CARDS = 2;
    const ONLY_DRAWN = 3;
    const NOT_PLAYABLE = 4;

    protected $allowed;

    public function __construct($message, $code, $allowed = null)
    {
        parent::__construct($message, $code);
        $this->allowed = $allowed;
    }

    public function getAllowedCards()
    {
        return $this->allowed;
    }
}
