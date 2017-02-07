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

class   UnoValidCardsTest
extends \PHPUnit\Framework\TestCase
{
    public function testRejectInvalidCards()
    {
        $cards = array(
            0,
            'r',
            '0',
            'rg',
            'x0',
            'xr',
            'xs',
            'x+2',
            'w+5',
            'r+4',
            'rrr',
            'rss',
            'r++2',
            'r+22',
        );
        foreach ($cards as $card) {
            $result = \Erebot\Module\Uno\Game::extractCard($card, NULL);
            $this->assertSame(NULL, $result, $card);
        }
        foreach ($cards as $card) {
            $result = \Erebot\Module\Uno\Game::extractCard($card, TRUE);
            $this->assertSame(NULL, $result, $card);
        }
        foreach ($cards as $card) {
            $result = \Erebot\Module\Uno\Game::extractCard($card, FALSE);
            $this->assertSame(NULL, $result, $card);
        }
    }

    public function testAcceptValidCards()
    {
        $cards = array(
            'r0',   // Red serie
            'r9',
            'rr',
            'rs',
            'r+2',
            'g0',   // Green serie
            'g9',
            'gr',
            'gs',
            'g+2',
            'b0',   // Blue serie
            'b9',
            'br',
            'bs',
            'b+2',
            'y0',   // Yellow serie
            'y9',
            'yr',
            'ys',
            'y+2',
            'w',    // Wild serie
            'w+4',
        );
        foreach ($cards as $card) {
            $result = \Erebot\Module\Uno\Game::extractCard($card, NULL);
            $this->assertNotSame(NULL, $result, $card);
        }
        foreach ($cards as $card) {
            $result = \Erebot\Module\Uno\Game::extractCard($card, FALSE);
            $this->assertNotSame(NULL, $result, $card);
        }
    }

    public function testValidCardsWithoutColors()
    {
        $cards = array(
            'w',
            'w+4',
        );
        foreach ($cards as $card) {
            $result = \Erebot\Module\Uno\Game::extractCard($card, NULL);
            $this->assertNotSame(NULL, $result, $card);
        }
        foreach ($cards as $card) {
            $result = \Erebot\Module\Uno\Game::extractCard($card, FALSE);
            $this->assertNotSame(NULL, $result, $card);
        }
        foreach ($cards as $card) {
            $result = \Erebot\Module\Uno\Game::extractCard($card, TRUE);
            $this->assertSame(NULL, $result, $card);
        }
    }

    public function testValidCardsWithColors()
    {
        $cards = array(
            'wr',
            'w+4r',
        );
        foreach ($cards as $card) {
            $result = \Erebot\Module\Uno\Game::extractCard($card, NULL);
            $this->assertNotSame(NULL, $result, $card);
        }
        foreach ($cards as $card) {
            $result = \Erebot\Module\Uno\Game::extractCard($card, TRUE);
            $this->assertNotSame(NULL, $result, $card);
        }
        foreach ($cards as $card) {
            $result = \Erebot\Module\Uno\Game::extractCard($card, FALSE);
            $this->assertSame(NULL, $result, $card);
        }
    }
}

