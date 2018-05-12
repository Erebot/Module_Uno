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

class RealGameTest
extends UnoGameTest
{
    public function testPenaltyDrawAtEndOfGame()
    {
        // Create a new game, whose rules & deck we control.
        $this->createFixedGame(
            'foo',  // Game creator
            0,      // Rules for the game
            'g6',   // First card dealt
            'g0', 'g1', 'g2', 'g3', 'g4', 'g5', 'w+4',  // 1st player's hand
            'g0', 'g1', 'g2', 'g3', 'g4', 'g5', 'w+4',  // 2nd player's hand
            'b0', 'b1', 'b2', 'b3'                      // Rest of the deck
        );

        // "foo" & "bar" join the game (making it start)
        // So as to not rely on luck, the person whose nick comes first
        // in alphabetical order (bar) gets to play first.
        $this->join('foo');
        $this->join('bar');

        // Each player discards his green cards, starting with "bar".
        foreach (range(0, 5) as $n) {
            $this->play('bar', "g$n");
            $this->play('foo', "g$n");
        }

        // Ending move for "bar" : "bar" wins
        // and forces "foo" to draw 4 cards.
        $this->play('bar', "w+4");

        // Retrieve the results and do some checks
        $last       = array_pop($this->messages);
        $beforeLast = array_pop($this->messages);

        // "foo" still has 56 points left in his hand
        // (w+4 + b0 + b1 + b2 + b3 = 50 + 0 + 1 + 2 + 3 = 56)
        $this->assertSame($beforeLast, "PRIVMSG #Erebot :foo still had 00,04 00,03W00,12i01,08l00,04d00,03 00,12+01,08400,04  00,12 Blue 0  00,12 Blue 1  00,12 Blue 2  00,12 Blue 3 ");
        $this->assertSame($last, "PRIVMSG #Erebot :bar wins with 56 points");
    }
}
