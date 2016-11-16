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

require_once(dirname(__FILE__).'/utils.php');

class UnoModuleStub extends \Erebot\Module\Uno
{
    protected function parseString($param, $default = null)
    {
        return $default;
    }

    public function & getGame($chan)
    {
        if (isset($this->chans[$chan]))
            return $this->chans[$chan];

        $res = null;
        return $res;
    }
}

class DeckStub extends \Erebot\Module\Uno\Deck\Official
{
    public function __construct(array $cards)
    {
        $this->discarded    = array();
        $this->firstCard    = array_shift($cards);
        $this->cards        = $cards;
    }
}

class GameStub extends \Erebot\Module\Uno\Game
{
    public function & join($token)
    {
        $res =& parent::join($token);
        sort($this->players);
        return $res;
    }
}


class   RealGameTest
extends Erebot_Testenv_Module_TestCase
{
    private $messages;

    public function setUp()
    {
        parent::setUp();

        $this->messages = array();

        $this->_connection
            ->expects($this->any())
            ->method('getIO')
            ->will($this->returnValue($this));

        $this->_translator
            ->expects($this->any())
            ->method('getLocale')
            ->will($this->returnValue('en_US'));

        $registry = new \Erebot\Module\TriggerRegistry(null);
        $tracker  = new \Erebot\Module\IrcTracker(null);
        $registry->reloadModule($this->_connection, \Erebot\Module\Base::RELOAD_ALL);
        $tracker->reloadModule($this->_connection, \Erebot\Module\Base::RELOAD_ALL);

        $this->_modules['\\Erebot\\Module\\TriggerRegistry'] = $registry;
        $this->_modules['\\Erebot\\Module\\IrcTracker'] = $tracker;

        $this->_module = new UnoModuleStub(NULL);
        $this->_module->reloadModule($this->_connection, \Erebot\Module\Base::RELOAD_ALL);

        // Create a few players
        foreach (array('foo', 'bar', 'baz', 'qux') as $nick) {
            $this->_modules['\\Erebot\\Module\\IrcTracker']->handleJoin(
                $this->_eventHandler,
                new \Erebot\Event\Join($this->_connection, '#erebot', "$nick!$nick@$nick")
            );
        }
    }

    public function push($msg)
    {
        $msg = filter_var($msg, FILTER_UNSAFE_RAW, FILTER_FLAG_STRIP_LOW);
        $this->messages[] = $msg;
    }

    protected function setDeck($card /* , ... */)
    {
        $cards = func_get_args();
        $deck = new DeckStub($cards);
        $game =& $this->_module->getGame('#Erebot');
        $game['game'] = new GameStub($game['game']->getCreator(), 0, $deck);
    }

    public function testPenaltyDrawAtEndOfGame()
    {
        // "foo" creates a new game
        $this->_module->handleCreate($this->_eventHandler, new \Erebot\Event\ChanText($this->_connection, '#Erebot', 'foo', '!uno'));

        // Prepare the deck for a two-players game where the first card dealt
        // is g6 and both players have exactly the same hand (g0-g5 & w+4).
        // Additional (penalty) cards are added to the mix.
        $this->setDeck(
            'g6',
            'g0', 'g1', 'g2', 'g3', 'g4', 'g5', 'w+4',
            'g0', 'g1', 'g2', 'g3', 'g4', 'g5', 'w+4',
            'b0', 'b1', 'b2', 'b3'
        );

        // "foo" & "bar" join the game (making it start)
        $this->_module->handleJoin($this->_eventHandler, new \Erebot\Event\ChanText($this->_connection, '#Erebot', 'foo', 'jp'));
        $this->_module->handleJoin($this->_eventHandler, new \Erebot\Event\ChanText($this->_connection, '#Erebot', 'bar', 'jo'));

        // Discard unnecessary cards, starting with "bar".
        foreach (range(0, 5) as $n) {
            $this->_module->handlePlay($this->_eventHandler, new \Erebot\Event\ChanText($this->_connection, '#Erebot', 'bar', "pl g$n"));
            $this->_module->handlePlay($this->_eventHandler, new \Erebot\Event\ChanText($this->_connection, '#Erebot', 'foo', "pl g$n"));
        }

        // Ending move for "bar"
        $this->_module->handlePlay($this->_eventHandler, new \Erebot\Event\ChanText($this->_connection, '#Erebot', 'bar', "pl w+4"));

        // Retrieve the results and do some checks
        $last       = array_pop($this->messages);
        $beforeLast = array_pop($this->messages);

        $this->assertSame($beforeLast, "PRIVMSG #Erebot :foo still had 00,04 00,03W00,12i01,08l00,04d00,03 00,12+01,08400,04  00,12 Blue 0  00,12 Blue 1  00,12 Blue 2  00,12 Blue 3 ");
        $this->assertSame($last, "PRIVMSG #Erebot :bar wins with 56 points");
    }
}

