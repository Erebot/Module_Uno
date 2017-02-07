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

#class   UnoScoringTest
#extends \PHPUnit\Framework\TestCase
#{
#    public function scoringProvider()
#    {
#        return array(
#            array('r0',     0),
#            array('g1',     1),
#            array('b2',     2),
#            array('r3',     3),
#            array('g4',     4),
#            array('b5',     5),
#            array('y6',     6),
#            array('r7',     7),
#            array('g8',     8),
#            array('y9',     9),
#            array('rr',    20),
#            array('rs',    20),
#            array('r+2',   20),
#            array('w',     50),
#            array('w+4',   50),
#            array(array('r0', 'r2', 'b7', 'w', 'yr', 'g+2'),    99),
#        );
#    }

#    /**
#     * @dataProvider    scoringProvider
#     */
#    public function testScoringFunction($card, $score)
#    {
#        $result = Erebot_Module_Uno_Game::getScore($card);
#        $this->assertEquals($score, $result);
#    }
#}

