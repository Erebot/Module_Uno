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

class   UnoStub
extends \Erebot\Module\Uno\Game
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
extends \Erebot\Module\Uno\Game
{}

class   UnoDeckStub
extends \Erebot\Module\Uno\Deck\Official
{
    protected function chooseFirstCard()
    {
        $this->firstCard = NULL;
    }
}

class   UnoHandStub
extends \Erebot\Module\Uno\Hand
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

    public static function labelsToRules($labels)
    {
        throw new \RuntimeException();
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

abstract class UnoGameTest
extends Erebot_Testenv_Module_TestCase
{
    protected $messages;

    public function setUp()
    {
        $mock = $this->getMockBuilder('\\Erebot\\Interfaces\\Event\\ChanText')->getMock();
        $this->_factory['!Event\\ChanText'] = get_class($mock);

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
        $tracker->reloadModule($this->_connection, \Erebot\Module\Base::RELOAD_MEMBERS);

        $this->_modules['\\Erebot\\Module\\TriggerRegistry'] = $this->_module = $registry;
        self::_injectStubs();
        $this->_modules['\\Erebot\\Module\\IrcTracker'] = $this->_module = $tracker;
        self::_injectStubs();

        $this->_module = new UnoModuleStub(NULL);
        $this->_module->reloadModule($this->_connection, \Erebot\Module\Base::RELOAD_MEMBERS);
        self::_injectStubs();
    }

    public function push($msg)
    {
        $msg = filter_var($msg, FILTER_UNSAFE_RAW, FILTER_FLAG_STRIP_LOW);
        $this->messages[] = $msg;
    }

    public function createFixedGame($creator, $rules = 0, ...$cards)
    {
        try {
            $this->trackNick($creator);
            $this->_module->handleCreate($this->_eventHandler, $this->makeEvent($creator, '!uno'));
        } catch (\RuntimeException $e) {
        }
        $token = $this->_modules['\\Erebot\\Module\\IrcTracker']->startTracking($creator);

        $deck = new DeckStub($cards);
        $game =& $this->_module->getGame('#Erebot');
        $game['handlers'] = array();
        $game['game'] = new GameStub($token, $rules, $deck);
    }

    private function makeIdentity($nick)
    {
        $identity = $this->getMockBuilder('\\Erebot\\Interfaces\\Identity')->getMock();
        $identity
            ->expects($this->any())
            ->method('getNick')
            ->will($this->returnValue($nick));
        $identity
            ->expects($this->any())
            ->method('getIdent')
            ->will($this->returnValue($nick));
        $identity
            ->expects($this->any())
            ->method('getHost')
            ->will($this->returnValue($nick));
        $identity
            ->expects($this->any())
            ->method('__toString')
            ->will($this->returnValue($nick));
        return $identity;
    }

    private function makeEvent($nick, $msg)
    {
        $wrapper = $this->getMockBuilder('\\Erebot\\Interfaces\\TextWrapper')->getMock();
        $text = explode(" ", $msg);
        $wrapper
            ->expects($this->any())
            ->method('getTokens')
            ->will($this->returnCallback( function ($index) use ($text) { return isset($text[$index]) ? $text[$index] : ''; } ));

        $event = $this->getMockBuilder('\\Erebot\\Interfaces\\Event\\ChanText')->getMock();
        $event
            ->expects($this->any())
            ->method('getSource')
            ->will($this->returnValue($this->makeIdentity($nick)));
        $event
            ->expects($this->any())
            ->method('getChan')
            ->will($this->returnValue('#Erebot'));
        $event
            ->expects($this->any())
            ->method('getText')
            ->will($this->returnValue($wrapper));
        $event
            ->expects($this->any())
            ->method('getConnection')
            ->will($this->returnValue($this->_connection));
        return $event;
    }

    private function trackNick($nick)
    {
        $identity = $this->getMockBuilder('\\Erebot\\Interfaces\\Identity')->getMock();
        $identity
            ->expects($this->any())
            ->method('getNick')
            ->will($this->returnValue($nick));
        $identity
            ->expects($this->any())
            ->method('getIdent')
            ->will($this->returnValue($nick));
        $identity
            ->expects($this->any())
            ->method('getHost')
            ->will($this->returnValue($nick));

        $player = $this->getMockBuilder('\\Erebot\\Interfaces\\Event\\Join')->getMock();
        $player
            ->expects($this->any())
            ->method('getSource')
            ->will($this->returnValue($identity));
        $player
            ->expects($this->any())
            ->method('getChan')
            ->will($this->returnValue('#erebot'));

        $this->_modules['\\Erebot\\Module\\IrcTracker']->handleJoin(
            $this->_eventHandler,
            $player
        );
    }

    public function join($nick)
    {
        $this->trackNick($nick);
        $this->_module->handleJoin($this->_eventHandler, $this->makeEvent($nick, 'jo'));
    }

    public function play($nick, $card)
    {
        $this->_module->handlePlay($this->_eventHandler, $this->makeEvent($nick, "pl $card"));
    }
}
