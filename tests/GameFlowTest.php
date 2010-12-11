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

include_once(dirname(__FILE__).'/utils.php');

class   UnoGameFlowTest
extends PHPUnit_Framework_TestCase
{
    public function testPassingSwitchesControl()
    {
        $uno = new UnoStub('Clicky');
        $uno->join('foo');
        $uno->join('bar');
        $uno->join('baz');

        $player = $uno->getCurrentPlayer();
        $this->assertEquals('foo', $player->getPlayer());
        $uno->draw();
        $uno->pass();

        $player = $uno->getCurrentPlayer();
        $this->assertEquals('bar', $player->getPlayer());
        $uno->draw();
        $uno->pass();

        $player = $uno->getCurrentPlayer();
        $this->assertEquals('baz', $player->getPlayer());
        $uno->draw();
        $uno->pass();

        $player = $uno->getCurrentPlayer();
        $this->assertEquals('foo', $player->getPlayer());
    }

    public function testPlayingSwitchesControl()
    {
        $uno = new UnoStub('Clicky');
        $uno->join('foo');
        $uno->join('bar');
        $uno->join('baz');

        $player = $uno->getCurrentPlayer();
        $this->assertEquals('foo', $player->getPlayer());
        $uno->play('wb');

        $player = $uno->getCurrentPlayer();
        $this->assertEquals('bar', $player->getPlayer());
        $uno->play('wb');

        $player = $uno->getCurrentPlayer();
        $this->assertEquals('baz', $player->getPlayer());
        $uno->play('wb');

        $player = $uno->getCurrentPlayer();
        $this->assertEquals('foo', $player->getPlayer());
    }

    public function testPlayingSwitchesControl2()
    {
        $uno = new UnoStub(
            'Clicky',
            Erebot_Module_Uno_Game::RULES_REVERSIBLE_PENALTIES
        );
        $uno->join('foo');
        $uno->join('bar');

        $player = $uno->getCurrentPlayer();
        $this->assertEquals('foo', $player->getPlayer());
        $uno->play('rr');

        $player = $uno->getCurrentPlayer();
        $this->assertEquals('foo', $player->getPlayer());
        $uno->play('rr');

        $player = $uno->getCurrentPlayer();
        $this->assertEquals('foo', $player->getPlayer());
    }

    public function testPlayingSwitchesControl3()
    {
        $uno = new UnoStub(
            'Clicky',
            Erebot_Module_Uno_Game::RULES_REVERSIBLE_PENALTIES
        );
        $uno->join('foo');
        $uno->join('bar');
        $uno->join('baz');

        $player = $uno->getCurrentPlayer();
        $this->assertEquals('foo', $player->getPlayer());
        $uno->play('rr');

        $player = $uno->getCurrentPlayer();
        $this->assertEquals('baz', $player->getPlayer());
        $uno->play('rr');

        $player = $uno->getCurrentPlayer();
        $this->assertEquals('foo', $player->getPlayer());
    }

    public function testPlayingSwitchesControl4()
    {
        $uno = new UnoStub(
            'Clicky',
            Erebot_Module_Uno_Game::RULES_REVERSIBLE_PENALTIES
        );
        $uno->join('foo');
        $uno->join('bar');

        $player = $uno->getCurrentPlayer();
        $this->assertEquals('foo', $player->getPlayer());
        $uno->play('rr');

        $player = $uno->getCurrentPlayer();
        $this->assertEquals('foo', $player->getPlayer());
        $uno->play('rs');

        $player = $uno->getCurrentPlayer();
        $this->assertEquals('foo', $player->getPlayer());
    }

    public function test2vs2PenaltyAndReverse()
    {
        $uno = new UnoStub(
            'Clicky',
            Erebot_Module_Uno_Game::RULES_REVERSIBLE_PENALTIES
        );
        $uno->join('foo');
        $uno->join('bar');

        $player = $uno->getCurrentPlayer();
        $this->assertEquals('foo', $player->getPlayer());
        $uno->play('r+2');

        $player = $uno->getCurrentPlayer();
        $this->assertEquals('bar', $player->getPlayer());
        $uno->play('rr');

        $player = $uno->getCurrentPlayer();
        $this->assertEquals('foo', $player->getPlayer());
    }

    public function testCardCounts()
    {
        $uno = new UnoStub2('Clicky');
        $uno->join('foo');
        $uno->join('bar');
        $uno->join('baz');

        $player = $uno->getCurrentPlayer();
        $this->assertEquals(7, $player->getCardsCount());
        $uno->draw();
        $this->assertEquals(8, $player->getCardsCount());
    }
}

