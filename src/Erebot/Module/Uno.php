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

class   Erebot_Module_Uno
extends Erebot_Module_Base
{
    protected $_chans;
    protected $_db;
    protected $_creator;

    const COLOR_RED                     = '00,04';
    const COLOR_GREEN                   = '00,03';
    const COLOR_BLUE                    = '00,12';
    const COLOR_YELLOW                  = '01,08';

    public function install()
    {
        $this->_db->createDatabase();
        $import     = new Doctrine_Import_Schema();
        $builder    = new Doctrine_Import_Builder();
        $array      = $import->buildSchema(
            array(dirname(__FILE__).'/model.yml'),
            'yml'
        );
        foreach ($array as $table => $schema) {
            $models = $builder->buildDefinition($schema);
            eval($models);
        }
        $exporter = new Doctrine_Export();
        $exporter->exportClasses(array_keys($array));
    }

    public function uninstall()
    {
        try {
            $this->_db->dropDatabase();
        }
        catch (Doctrine_Export_Exception $e) {
        }
    }

    public function _reload($flags)
    {
        if ($flags & self::RELOAD_MEMBERS) {
            $this->_chans = array();
###     $this->_db = Doctrine_Manager::connection("sqlite:////tmp/uno.sqlite");
###     $this->_db->setAttribute(Doctrine_Core::ATTR_QUOTE_IDENTIFIER, true);

#            $this->uninstall();
#            $this->install();
        }

        if ($flags & self::RELOAD_HANDLERS) {
            $this->_db = array();

            $registry   = $this->_connection->getModule(
                'Erebot_Module_TriggerRegistry'
            );
            $matchAny = Erebot_Utils::getVStatic($registry, 'MATCH_ANY');

            if (!($flags & self::RELOAD_INIT)) {
                $this->_connection->removeEventHandler(
                    $this->_creator['handler']
                );
                $registry->freeTriggers($this->creator['trigger'], $matchAny);
            }

            $triggerCreate = $this->parseString('trigger_create', 'uno');
            $this->_creator['trigger']  = $registry->registerTriggers(
                $triggerCreate,
                $matchAny
            );
            if ($this->_creator['trigger'] === NULL) {
                $fmt = $this->getFormatter(FALSE);
                throw new Exception(
                    $fmt->_(
                        'Could not register UNO creation trigger'
                    )
                );
            }

            $this->_creator['handler']  = new Erebot_EventHandler(
                new Erebot_Callable(array($this, 'handleCreate')),
                new Erebot_Event_Match_All(
                    new Erebot_Event_Match_InstanceOf(
                        'Erebot_Interface_Event_ChanText'
                    ),
                    new Erebot_Event_Match_Any(
                        new Erebot_Event_Match_TextStatic($triggerCreate, TRUE),
                        new Erebot_Event_Match_TextWildcard(
                            $triggerCreate.' *',
                            TRUE
                        )
                    )
                )
            );
            $this->_connection->addEventHandler($this->_creator['handler']);
            $this->registerHelpMethod(array($this, 'getHelp'));
        }
    }

    protected function _unload()
    {
    }

    public function getHelp(
        Erebot_Interface_Event_Base_TextMessage $event,
                                                $words
    )
    {
        if ($event instanceof Erebot_Interface_Event_Base_Private) {
            $target = $event->getSource();
            $chan   = NULL;
        }
        else
            $target = $chan = $event->getChan();

        $fmt            = $this->getFormatter($chan);
        $triggerCreate  = $this->parseString('trigger_create', 'uno');

        $commands   =   array(
            'challenge'    => $this->parseString('trigger_challenge', 'ch'),
            'choose'       => $this->parseString('trigger_choose', 'co'),
            'draw'         => $this->parseString('trigger_draw', 'pe'),
            'join'         => $this->parseString('trigger_join', 'jo'),
            'pass'         => $this->parseString('trigger_pass', 'pa'),
            'play'         => $this->parseString('trigger_play', 'pl'),
            'show_cards'   => $this->parseString('trigger_show_cards', 'ca'),
            'show_discard' => $this->parseString('trigger_show_discard', 'cd'),
            'show_order'   => $this->parseString('trigger_show_order', 'od'),
            'show_time'    => $this->parseString('trigger_show_time', 'ti'),
            'show_turn'    => $this->parseString('trigger_show_turn', 'tu'),
        );

        $bot        = $this->_connection->getBot();
        $moduleName = get_class();
        $nbArgs     = count($words);

        if ($nbArgs == 1 && $words[0] == strtolower($moduleName)) {
            $msg = $fmt->_(
                'Provides the <b><var name="trigger_create"/></b> command '.
                'which starts a new Uno game. Once a game has been created, '.
                'other commands become available to interact with the bot '.
                '(<for item="command" from="commands"><b><var '.
                'name="command"/></b></for>). Use "!help <var '.
                'name="module"/>&lt;<u>command</u>&gt;" to get help '.
                'on some &lt;<u>command</u>&gt;.',
                array(
                    'trigger_create' => $triggerCreate,
                    'commands'=> $commands,
                    'module' => $moduleName,
                )
            );
            $this->sendMessage($target, $msg);
            return TRUE;
        }

        else if (($words[0] == $moduleName || isset($this->_chans[$chan])) &&
                $nbArgs > 1) {

            $reflector = new ReflectionObject($formatter);
            $resetCode = $reflector->getConstant('CODE_RESET');
            $vars = array(
                'w'     => $this->getCardText('w'),
                'w+4'   => $this->getCardText('w+4'),
                'r1'    => $this->getCardText('r1'),
                'r+2'   => $this->getCardText('r+2'),
                'rs'    => $this->getCardText('rs'),
                'rr'    => $this->getCardText('rr'),
                'logo'  => $this->getLogo(),
                'reset' => $resetCode,
            );
            foreach ($commands as $cmd => $trigger)
                $vars[$cmd] = $trigger;

            foreach ($commands as $cmd => $trigger) {
                if (!strcasecmp($trigger, $words[1])) {
                    switch ($cmd) {
                        case 'challenge':
                            $msg = $fmt->_(
                                'You may only use this command after someone '.
                                'played a <var name="w+4"/> and no other '.
                                'penalty had been played before. It shows you '.
                                'the hand of the player you challenged. '.
                                'If that person played a <var name="w+4"/> '.
                                'while he or she had a card of the proper '.
                                'color (except for special cards like +2, '.
                                'Skip or Reverse), that player must draw 4 '.
                                'cards. Otherwise, you must draw the 4 '.
                                'initial cards, plus 2 additional cards.',
                                $vars
                            );
                            break;

                        case 'choose':
                            $msg = $fmt->_(
                                'Select the new color after you played a '.
                                '<var name="w"/> or <var name="w+4"/>, eg. '.
                                '"<var name="choose"/> &lt;<u>color</u>&gt;". '.
                                'Valid &lt;<u>color</u>&gt;s: <b>r</b> (red), '.
                                '<b>b</b> (blue), <b>g</b> (green) &amp; '.
                                '<b>y</b> (yellow). The new color may also '.
                                'be selected directly when playing the card, '.
                                'eg. "<var name="play"/> w+4b".',
                                $vars
                            );
                            break;

                        case 'draw':
                            $msg = $fmt->_(
                                'Draw a new card. You may choose to play the '.
                                'card you just drew afterwards, using the '.
                                '"<var name="play"/>" command. This command '.
                                'can also be used to draw penalty cards and '.
                                'pass your turn.',
                                $vars
                            );
                            break;

                        case 'join':
                            $msg = $fmt->_(
                                'Join the current <var name="logo"/> game. '.
                                'The bot will send you the list of your cards '.
                                'in a separate query.',
                                $vars
                            );
                            break;

                        case 'pass':
                            $msg = $fmt->_(
                                'Pass your turn. Note that you must first '.
                                'draw a card with <var name="draw"/> before '.
                                'you pass. This command can also be used '.
                                'to draw penalty cards and pass your turn.',
                                $vars
                            );
                            break;

                        case 'play':
                            $msg = $fmt->_(
                                'Play a card. The card must be described '.
                                'using its mnemonic. Eg. '.
                                '"<var name="play"/> r1" to play '.
                                '<var name="r1"/><var name="reset"/>, '.
                                '"<var name="play"/> r+2" to play '.
                                '<var name="r+2"/><var name="reset"/>, '.
                                '"<var name="play"/> rs" to play '.
                                '<var name="rs"/><var name="reset"/>, '.
                                '"<var name="play"/> rr" to play '.
                                '<var name="rr"/><var name="reset"/>, '.
                                '"<var name="play"/> w" to play '.
                                '<var name="w"/><var name="reset"/> and '.
                                '"<var name="play"/> w+4" to play '.
                                '<var name="w+4"/><var name="reset"/>.',
                                $vars
                            );
                            break;

                        case 'show_cards':
                            $msg = $fmt->_(
                                'Displays the number of cards in '.
                                'each players\'s hand. Also displays '.
                                'your hand in a separate query.',
                                $vars
                            );
                            break;

                        case 'show_discard':
                            $msg = $fmt->_(
                                'Displays the top card of the discard.',
                                $vars
                            );
                            break;

                        case 'show_order':
                            $msg = $fmt->_(
                                'Displays the order in which players '.
                                'take turns to play.',
                                $vars
                            );
                            break;

                        case 'show_time':
                            $msg = $fmt->_(
                                'Displays how much time has elapsed '.
                                'since the beginning of the game.',
                                $vars
                            );
                            break;

                        case 'show_turn':
                            $msg = $fmt->_(
                                'Displays the nickname of the player '.
                                'whose turn it is to play',
                                $vars
                            );
                            break;

                        default:
                            throw new Erebot_InvalidValueException(
                                'Unknown command'
                            );
                    }
                    $this->sendMessage($target, $msg);
                    return TRUE;
                }
            }
        }
    }

    protected function getLogo()
    {
        return  Erebot_Styling::CODE_BOLD.
                Erebot_Styling::CODE_COLOR.'04U'.
                Erebot_Styling::CODE_COLOR.'03N'.
                Erebot_Styling::CODE_COLOR.'12O'.
                Erebot_Styling::CODE_COLOR.'08!'.
                Erebot_Styling::CODE_COLOR.
                Erebot_Styling::CODE_BOLD;
    }

    protected function getColoredCard($color, $text)
    {
        $text       = ' '.$text.' ';
        $colorCodes =   array(
                            'r' => self::COLOR_RED,
                            'g' => self::COLOR_GREEN,
                            'b' => self::COLOR_BLUE,
                            'y' => self::COLOR_YELLOW,
                        );

        if (!isset($colorCodes[$color])) {
            throw new Exception(
                sprintf('Unknown color! (%s, %s)', $color, $text)
            );
        }

        return  Erebot_Styling::CODE_COLOR.$colorCodes[$color].
                Erebot_Styling::CODE_BOLD.$text.
                Erebot_Styling::CODE_BOLD.
                Erebot_Styling::CODE_COLOR;
    }

    protected function wildify($text)
    {
        $order  =   array(
                        self::COLOR_RED,
                        self::COLOR_GREEN,
                        self::COLOR_BLUE,
                        self::COLOR_YELLOW,
                    );
        $text   = ' '.$text.' ';
        $len    = strlen($text);
        $output = Erebot_Styling::CODE_BOLD;
        $nbCol  = count($order);

        for ($i = 0; $i < $len; $i++)
            $output .=  Erebot_Styling::CODE_COLOR.
                        $order[$i % $nbCol].
                        $text[$i];
        $output .=  Erebot_Styling::CODE_COLOR.
                    Erebot_Styling::CODE_BOLD;
        return $output;
    }

    protected function getCardText($card)
    {
        if ($card[0] == 'w') {
            $text = 'Wild'.(substr($card, 1, 1) == '+' ? ' +4' : '');
            return $this->wildify($text);
        }

        $colors = array(
            'r' => 'Red',
            'g' => 'Green',
            'b' => 'Blue',
            'y' => 'Yellow',
        );

        $words  = array(
            '+' => '+2',
            'r' => 'Reverse',
            's' => 'Skip',
        );

        if (!isset($card[1]))
            return $this->getColoredCard($card[0], $colors[$card[0]]);

        if (isset($words[$card[1]]))
            return $this->getColoredCard(
                $card[0],
                $colors[$card[0]].' '.$words[$card[1]]
            );

        return $this->getColoredCard($card[0], $colors[$card[0]].' '.$card[1]);
    }

    protected function getCurrentPlayer($chan)
    {
        if (!isset($this->_chans[$chan]['game']))
            return NULL;
        if (count($this->_chans[$chan]['game']->getPlayers()) < 2)
            return NULL;
        return $this->_chans[$chan]['game']->getCurrentPlayer();
    }

    protected function showTurn(Erebot_Interface_Event_ChanText $event)
    {
        $synEvent = new Erebot_Event_ChanText(
            $event->getConnection(),
            $event->getChan(),
            '',
            ''
        );
        $this->handleShowTurn($synEvent);
    }

    public function handleCreate(
        Erebot_Interface_EventHandler   $handler,
        Erebot_Interface_Event_ChanText $event
    )
    {
        $nick       =   $event->getSource();
        $chan       =   $event->getChan();
        $rules      =   strtolower($event->getText()->getTokens(1));
        $fmt        =   $this->getFormatter($chan);

        if (isset($this->_chans[$chan])) {
            $infos      =&  $this->_chans[$chan];
            $creator    =   $infos['game']->getCreator();
            $msg        =   $fmt->_(
                '<var name="logo"/> A game '.
                'is already running, managed by <var name="'.
                'creator"/>. The following rules apply: <for '.
                'from="rules" item="rule"><var name="rule"/>'.
                '</for>. Say "<b><var name="trigger"/></b>" '.
                'to join it.',
                array(
                    'logo' => $this->getLogo(),
                    'creator' => (string) $creator,
                    'rules' => $infos['game']->getRules(TRUE),
                    'trigger' => $infos['triggers']['join'],
                )
            );
            $this->sendMessage($chan, $msg);
            return $event->preventDefault(TRUE);
        }

        $registry   =   $this->_connection->getModule(
            'Erebot_Module_TriggerRegistry'
        );
        $triggers   =   array(
            'challenge'    => $this->parseString('trigger_challenge', 'ch'),
            'choose'       => $this->parseString('trigger_choose', 'co'),
            'draw'         => $this->parseString('trigger_draw', 'pe'),
            'join'         => $this->parseString('trigger_join', 'jo'),
            'pass'         => $this->parseString('trigger_pass', 'pa'),
            'play'         => $this->parseString('trigger_play', 'pl'),
            'show_cards'   => $this->parseString('trigger_show_cards', 'ca'),
            'show_discard' => $this->parseString('trigger_show_discard', 'cd'),
            'show_order'   => $this->parseString('trigger_show_order', 'od'),
            'show_time'    => $this->parseString('trigger_show_time', 'ti'),
            'show_turn'    => $this->parseString('trigger_show_turn', 'tu'),
        );
        $token  = $registry->registerTriggers($triggers, $chan);
        if ($token === NULL) {
            $msg = $fmt->_(
                'Unable to register triggers for '.
                '<var name="logo"/> game!',
                array('logo' => $this->getLogo())
            );
            $this->sendMessage($chan, $msg);
            return $event->preventDefault(TRUE);
        }

        $this->_chans[$chan] = array();
        $infos  =&  $this->_chans[$chan];

        if (trim($rules) == '')
            $rules = $this->parseString('default_rules', '');

        $tracker = $this->_connection->getModule('Erebot_Module_IrcTracker');
        $creator                    = $tracker->startTracking($nick);
        $infos['triggers_token']    = $token;
        $infos['triggers']          =& $triggers;
        $infos['game']              = new Erebot_Module_Uno_Game(
            $creator,
            $rules
        );

        $infos['handlers']['challenge']     = new Erebot_EventHandler(
            new Erebot_Callable(array($this, 'handleChallenge')),
            new Erebot_Event_Match_All(
                new Erebot_Event_Match_InstanceOf(
                    'Erebot_Interface_Event_ChanText'
                ),
                new Erebot_Event_Match_TextStatic($triggers['challenge'], NULL),
                new Erebot_Event_Match_Chan($chan)
            )
        );

        $infos['handlers']['choose']        = new Erebot_EventHandler(
            new Erebot_Callable(array($this, 'handleChoose')),
            new Erebot_Event_Match_All(
                new Erebot_Event_Match_InstanceOf(
                    'Erebot_Interface_Event_ChanText'
                ),
                new Erebot_Event_Match_TextWildcard(
                    $triggers['choose'].' *',
                    NULL
                ),
                new Erebot_Event_Match_Chan($chan)
            )
        );

        $infos['handlers']['draw']          = new Erebot_EventHandler(
            new Erebot_Callable(array($this, 'handleDraw')),
            new Erebot_Event_Match_All(
                new Erebot_Event_Match_InstanceOf(
                    'Erebot_Interface_Event_ChanText'
                ),
                new Erebot_Event_Match_TextStatic($triggers['draw'], NULL),
                new Erebot_Event_Match_Chan($chan)
            )
        );

        $infos['handlers']['join']          = new Erebot_EventHandler(
            new Erebot_Callable(array($this, 'handleJoin')),
            new Erebot_Event_Match_All(
                new Erebot_Event_Match_InstanceOf(
                    'Erebot_Interface_Event_ChanText'
                ),
                new Erebot_Event_Match_TextStatic($triggers['join'], NULL),
                new Erebot_Event_Match_Chan($chan)
            )
        );

        $infos['handlers']['pass']          = new Erebot_EventHandler(
            new Erebot_Callable(array($this, 'handlePass')),
            new Erebot_Event_Match_All(
                new Erebot_Event_Match_InstanceOf(
                    'Erebot_Interface_Event_ChanText'
                ),
                new Erebot_Event_Match_TextStatic($triggers['pass'], NULL),
                new Erebot_Event_Match_Chan($chan)
            )
        );

        $infos['handlers']['play']          = new Erebot_EventHandler(
            new Erebot_Callable(array($this, 'handlePlay')),
            new Erebot_Event_Match_All(
                new Erebot_Event_Match_InstanceOf(
                    'Erebot_Interface_Event_ChanText'
                ),
                new Erebot_Event_Match_TextWildcard(
                    $triggers['play'].' *',
                    NULL
                ),
                new Erebot_Event_Match_Chan($chan)
            )
        );

        $infos['handlers']['show_cards']    = new Erebot_EventHandler(
            new Erebot_Callable(array($this, 'handleShowCardsCount')),
            new Erebot_Event_Match_All(
                new Erebot_Event_Match_InstanceOf(
                    'Erebot_Interface_Event_ChanText'
                ),
                new Erebot_Event_Match_TextStatic(
                    $triggers['show_cards'],
                    NULL
                ),
                new Erebot_Event_Match_Chan($chan)
            )
        );

        $infos['handlers']['show_discard']  = new Erebot_EventHandler(
            new Erebot_Callable(array($this, 'handleShowDiscard')),
            new Erebot_Event_Match_All(
                new Erebot_Event_Match_InstanceOf(
                    'Erebot_Interface_Event_ChanText'
                ),
                new Erebot_Event_Match_TextStatic(
                    $triggers['show_discard'],
                    NULL
                ),
                new Erebot_Event_Match_Chan($chan)
            )
        );

        $infos['handlers']['show_order']    = new Erebot_EventHandler(
            new Erebot_Callable(array($this, 'handleShowOrder')),
            new Erebot_Event_Match_All(
                new Erebot_Event_Match_InstanceOf(
                    'Erebot_Interface_Event_ChanText'
                ),
                new Erebot_Event_Match_TextStatic(
                    $triggers['show_order'],
                    NULL
                ),
                new Erebot_Event_Match_Chan($chan)
            )
        );

        $infos['handlers']['show_time']     = new Erebot_EventHandler(
            new Erebot_Callable(array($this, 'handleShowTime')),
            new Erebot_Event_Match_All(
                new Erebot_Event_Match_InstanceOf(
                    'Erebot_Interface_Event_ChanText'
                ),
                new Erebot_Event_Match_TextStatic($triggers['show_time'], NULL),
                new Erebot_Event_Match_Chan($chan)
            )
        );

        $infos['handlers']['show_turn']     = new Erebot_EventHandler(
            new Erebot_Callable(array($this, 'handleShowTurn')),
            new Erebot_Event_Match_All(
                new Erebot_Event_Match_InstanceOf(
                    'Erebot_Interface_Event_ChanText'
                ),
                new Erebot_Event_Match_TextStatic($triggers['show_turn'], NULL),
                new Erebot_Event_Match_Chan($chan)
            )
        );

        foreach ($infos['handlers'] as $handler)
            $this->_connection->addEventHandler($handler);

        $msg = $fmt->_(
            '<var name="logo"/> A new game has been '.
            'created in <var name="chan"/>. The following rules '.
            'apply: <for from="rules" item="rule"><var '.
            'name="rule"/></for>. Say "<b><var name="trigger"/>'.
            '</b>" to join it.',
            array(
                'logo'      => $this->getLogo(),
                'chan'      => $chan,
                'rules'     => $infos['game']->getRules(TRUE),
                'trigger'   => $infos['triggers']['join'],
            )
        );
        $this->sendMessage($chan, $msg);
        return $event->preventDefault(TRUE);
    }

    public function handleChallenge(
        Erebot_Interface_EventHandler   $handler,
        Erebot_Interface_Event_ChanText $event
    )
    {
        $chan       =   $event->getChan();
        $nick       =   $event->getSource();
        $current    =   $this->getCurrentPlayer($chan);
        $game       =&  $this->_chans[$chan]['game'];
        $fmt        =   $this->getFormatter($chan);

        if ($current === NULL) return;
        $currentNick    =   (string) $current->getPlayer();
        if (strcasecmp($nick, $currentNick)) return;

        // We must fetch the last player's entry before calling challenge()
        // because challenge() may change the current player.
        $lastPlayer = $game->getLastPlayer();
        try {
            $challenge = $game->challenge();
        }
        catch (Erebot_Module_Uno_UnchallengeableException $e) {
            $msg = $fmt->_(
                '<var name="logo"/> Previous move cannot be challenged!',
                array('logo' => $this->getLogo())
            );
            $this->sendMessage($chan, $msg);
            return $event->preventDefault();
        }

        $lastNick   = (string) $lastPlayer->getPlayer();
        $msg = $fmt->_(
            '<var name="logo"/> <b><var name="nick"/></b> challenges '.
            '<b><var name="last_nick"/></b>\'s <var name="card"/>.',
            array(
                'logo'      => $this->getLogo(),
                'nick'      => $nick,
                'last_nick' => $lastNick,
                'card'      => $this->getCardText('w+4'),
            )
        );
        $this->sendMessage($chan, $msg);

        $cardsTexts = array_map(
            array($this, 'getCardText'),
            $challenge['hand']
        );
        sort($cardsTexts);

        $msg = $fmt->_(
            '<b><var name="nick"/></b>\'s cards: '.
            '<for from="cards" item="card" separator=" ">'.
            '<var name="card"/></for>',
            array(
                'nick'  => $lastNick,
                'cards' => $cardsTexts,
            )
        );
        $this->sendMessage($nick, $msg);

        if (!$challenge['legal']) {
            $msg = $fmt->_(
                '<b><var name="nick"/></b>\'s move '.
                '<b>WAS NOT</b> legal. <b><var name="nick"/></b> '.
                'must pick <b><var name="count"/></b> cards!',
                array(
                    'nick'  => $lastNick,
                    'count' => count($challenge['cards']),
                )
            );
            $this->sendMessage($chan, $msg);

            $cardsTexts = array_map(
                array($this, 'getCardText'),
                $challenge['cards']
            );
            sort($cardsTexts);

            $msg = $fmt->_(
                'You drew: <for from="cards" item="card" '.
                'separator=" "><var name="card"/></for>',
                array(
                    'cards' => $cardsTexts,
                    'count' => count($cardsTexts),
                )
            );
            $this->sendMessage($lastNick, $msg);
        }
        else {
            $msg = $fmt->_(
                '<b><var name="last_nick"/></b>\'s move '.
                'was legal. <b><var name="nick"/></b> must pick '.
                '<b><var name="count"/></b> cards!',
                array(
                    'last_nick' => $lastNick,
                    'nick'      => $nick,
                    'count'     => count($challenge['cards']),
                )
            );
            $this->sendMessage($chan, $msg);

            $cardsTexts = array_map(
                array($this, 'getCardText'),
                $challenge['cards']
            );
            sort($cardsTexts);

            $msg = $fmt->_(
                'You drew: <for from="cards" item="card" '.
                'separator=" "><var name="card"/></for>',
                array(
                    'cards' => $cardsTexts,
                    'count' => count($cardsTexts),
                )
            );
            $this->sendMessage($nick, $msg);
        }

        $this->showTurn($event);
        $event->preventDefault(TRUE);
    }

    public function handleChoose(
        Erebot_Interface_EventHandler   $handler,
        Erebot_Interface_Event_ChanText $event
    )
    {
        $chan       = $event->getChan();
        $nick       = $event->getSource();
        $current    = $this->getCurrentPlayer($chan);
        $fmt        = $this->getFormatter(FALSE);

        if ($current === NULL) return;
        $currentNick    =   (string) $current->getPlayer();
        if (strcasecmp($nick, $currentNick)) return;

        $color  = strtolower($event->getText()->getTokens(1, 1));
        try {
            $this->_chans[$chan]['game']->chooseColor($color);
            $msg = $fmt->_(
                '<var name="logo"/> The color is now <var name="color"/>',
                array(
                    'color' => $this->getCardText($color),
                    'logo'  => $this->getLogo(),
                )
            );
            $this->sendMessage($chan, $msg);
        }
        catch (Erebot_Module_Uno_Exception $e) {
            $msg = $fmt->_(
                'Hmm, yes <b><var name="nick"/></b>, what is it?',
                array('nick' => $nick)
            );
            $this->sendMessage($chan, $msg);
        }
        return $event->preventDefault(TRUE);
    }

    public function handleDraw(
        Erebot_Interface_EventHandler   $handler,
        Erebot_Interface_Event_ChanText $event
    )
    {
        $chan       = $event->getChan();
        $nick       = $event->getSource();
        $current    = $this->getCurrentPlayer($chan);
        $fmt        = $this->getFormatter($chan);

        if ($current === NULL) return;
        $currentNick = (string) $current->getPlayer();
        if (strcasecmp($nick, $currentNick)) return;

        $game =& $this->_chans[$chan]['game'];
        try {
            $drawnCards = $game->draw();
        }
        catch (Erebot_Module_Uno_WaitingForColorException $e) {
            $msg = $fmt->_(
                '<var name="logo"/> <b><var name="nick"/></b>, '.
                'please choose a color with <b><var name="cmd"/> '.
                '&lt;r|b|g|y&gt;</b>',
                array(
                    'logo'  => $this->getLogo(),
                    'nick'  => $nick,
                    'cmd'   => $this->_chans[$chan]['triggers']['choose'],
                )
            );
            $this->sendMessage($chan, $msg);
        }
        catch (Erebot_Module_Uno_AlreadyDrewException $e) {
            $msg = $fmt->_('You already drew a card');
            $this->sendMessage($chan, $msg);
            return $event->preventDefault(TRUE);
        }

        $nbDrawnCards = count($drawnCards);
        if ($nbDrawnCards > 1) {
            $msg = $fmt->_(
                '<b><var name="nick"/></b> passes turn, '.
                'and has to pick <b><var name="count"/></b> cards!',
                array(
                    'nick'  => $nick,
                    'count' => $nbDrawnCards,
                )
            );
            $this->sendMessage($chan, $msg);
            $this->showTurn($event);

            $player = $game->getCurrentPlayer();
            $cardsTexts = array_map(
                array($this, 'getCardText'),
                $player->getCards()
            );
            sort($cardsTexts);

            $msg = $fmt->_(
                'Your cards: <for from="cards" item="card" '.
                'separator=" "><var name="card"/></for>',
                array(
                    'cards' => $cardsTexts,
                    'count' => count($cardsTexts),
                )
            );
            $this->sendMessage((string) $player->getPlayer(), $msg);
        }
        else {
            $msg = $fmt->_(
                '<b><var name="nick"/></b> draws a card',
                array('nick' => $nick)
            );
            $this->sendMessage($chan, $msg);
        }

        $cardsTexts = array_map(array($this, 'getCardText'), $drawnCards);
        sort($cardsTexts);

        $msg = $fmt->_(
            'You drew: <for from="cards" item="card" '.
            'separator=" "><var name="card"/></for>',
            array(
                'cards' => $cardsTexts,
                'count' => count($cardsTexts),
            )
        );
        $this->sendMessage($nick, $msg);
        return $event->preventDefault(TRUE);
    }

    public function handleJoin(
        Erebot_Interface_EventHandler   $handler,
        Erebot_Interface_Event_ChanText $event
    )
    {
        $nick   = $event->getSource();
        $chan   = $event->getChan();
        $fmt    = $this->getFormatter($chan);

        if (!isset($this->_chans[$chan])) return;
        $game =& $this->_chans[$chan]['game'];

        $players =& $game->getPlayers();
        foreach ($players as &$player) {
            if (!strcasecmp((string) $player->getPlayer(), $nick)) {
                $msg = $fmt->_(
                    '<var name="logo"/> You\'re already '.
                    'in the game <b><var name="nick"/></b>!',
                    array(
                        'logo'  => $this->getLogo(),
                        'nick'  => $nick,
                    )
                );
                $this->sendMessage($chan, $msg);
                return $event->preventDefault(TRUE);
            }
        }

        $msg = $fmt->_(
            '<b><var name="nick"/></b> joins this '.
            '<var name="logo"/> game.',
            array(
                'nick'  => $nick,
                'logo'  => $this->getLogo(),
            )
        );
        $this->sendMessage($chan, $msg);

        $tracker = $this->_connection->getModule('Erebot_Module_IrcTracker');
        $token  =   $tracker->startTracking($nick);
        $player =&  $game->join($token);
        $cards  =   $player->getCards();
        $cards  =   array_map(array($this, 'getCardText'), $cards);
        sort($cards);

        $msg = $fmt->_(
            'Your cards: <for from="cards" item="card" '.
            'separator=" "><var name="card"/></for>',
            array(
                'cards' => $cards,
                'count' => count($cards),
            )
        );
        $this->sendMessage($nick, $msg);

        // If this is the second player.
        $players =& $game->getPlayers();
        if (count($players) == 2) {
            $names = array();
            foreach ($players as &$player) {
                $names[] = (string) $player->getPlayer();
            }
            unset($player);

            // Display playing order.
            $this->handleShowOrder($event);

            $player         = $game->getCurrentPlayer();
            $currentNick    = (string) $player->getPlayer();
            $msg            = $fmt->_(
                '<b><var name="nick"/></b> deals '.
                'the first card from the stock',
                array('nick' => $currentNick)
            );
            $this->sendMessage($chan, $msg);

            $firstCard  = $game->getFirstCard();
            $discard    = $this->getCardText($firstCard);
            $msg        = $fmt->_(
                '<var name="logo"/> Current discard: <var name="discard"/>',
                array(
                    'logo'      => $this->getLogo(),
                    'discard'   => $discard,
                )
            );
            $this->sendMessage($chan, $msg);

            $skippedPlayer  = $game->play($firstCard);
            if ($skippedPlayer) {
                $skippedNick    = (string) $skippedPlayer->getPlayer();
                $msg            = $fmt->_(
                    '<var name="logo"/> <b><var name="nick"/></b> '.
                    'skips his turn!',
                    array(
                        'nick'  => $skippedNick,
                        'logo'  => $this->getLogo(),
                    )
                );
                $this->sendMessage($chan, $msg);
            }

            $this->showTurn($event);
            return $event->preventDefault(TRUE);
        }

        return $event->preventDefault(TRUE);
    }

    public function handlePass(
        Erebot_Interface_EventHandler   $handler,
        Erebot_Interface_Event_ChanText $event
    )
    {
        $chan       = $event->getChan();
        $nick       = $event->getSource();
        $current    = $this->getCurrentPlayer($chan);
        $fmt        = $this->getFormatter($chan);

        if ($current === NULL) return;
        $currentNick = (string) $current->getPlayer();
        if (strcasecmp($nick, $currentNick)) return;

        $game       =&  $this->_chans[$chan]['game'];
        try {
            $drawnCards = $game->pass();
        }
        catch (Erebot_Module_Uno_WaitingForColorException $e) {
            $msg = $fmt->_(
                '<var name="logo"/> <b><var name="nick"/></b>, '.
                'please choose a color with <b><var name="cmd"/> '.
                '&lt;r|b|g|y&gt;</b>',
                array(
                    'logo'  => $this->getLogo(),
                    'nick'  => $nick,
                    'cmd'   => $this->_chans[$chan]['triggers']['choose'],
                )
            );
            $this->sendMessage($chan, $msg);
        }
        catch (Erebot_Module_Uno_MustDrawBeforePassException $e) {
            $msg = $fmt->_('You must draw a card first');
            $this->sendMessage($chan, $msg);
            return $event->preventDefault(TRUE);
        }

        $nbDrawnCards   = count($drawnCards);
        $vars           = array(
            'nick'  => $nick,
            'count' => $nbDrawnCards,
        );
        if ($nbDrawnCards > 1)
            $msg = $fmt->_(
                '<b><var name="nick"/></b> passes turn, '.
                'and has to pick <b><var name="count"/></b> cards!',
                $vars
            );
        else
            $msg = $fmt->_('<b><var name="nick"/></b> passes turn', $vars);
        $this->sendMessage($chan, $msg);

        if ($nbDrawnCards) {
            $cardsTexts = array_map(array($this, 'getCardText'), $drawnCards);
            sort($cardsTexts);

            $msg = $fmt->_(
                'You drew: <for from="cards" item="card" '.
                'separator=" "><var name="card"/></for>',
                array(
                    'cards' => $cardsText,
                    'count' => $nbDrawnCards,
                )
            );
            $this->sendMessage($nick, $msg);
        }

        $this->showTurn($event);

        $player = $game->getCurrentPlayer();
        $cardsTexts = array_map(
            array($this, 'getCardText'),
            $player->getCards()
        );
        sort($cardsTexts);

        $msg = $fmt->_(
            'Your cards: <for from="cards" item="card" '.
            'separator=" "><var name="card"/></for>',
            array(
                'cards' => $cardsTexts,
                'count' => count($cardsTexts),
            )
        );
        $this->sendMessage((string) $player->getPlayer(), $msg);
        return $event->preventDefault(TRUE);
    }

    public function handlePlay(
        Erebot_Interface_EventHandler   $handler,
        Erebot_Interface_Event_ChanText $event
    )
    {
        $chan       = $event->getChan();
        $nick       = $event->getSource();
        $current    = $this->getCurrentPlayer($chan);
        $fmt        = $this->getFormatter($chan);

        if ($current === NULL) return;
        $currentNick = (string) $current->getPlayer();
        if (strcasecmp($nick, $currentNick)) return;

        $game =& $this->_chans[$chan]['game'];
        $card = $event->getText()->getTokens(1);
        $card = str_replace(' ', '', $card);

        $waitingForColor    = FALSE;
        $skippedPlayer      = NULL;

        try {
            $skippedPlayer = $game->play($card);
        }
        catch (Erebot_Module_Uno_WaitingForColorException $e) {
            $waitingForColor = TRUE;
        }
        catch (Erebot_Module_Uno_InvalidMoveException $e) {
            $msg = $fmt->_('This move is not valid');
            $this->sendMessage($chan, $msg);
            return $event->preventDefault(TRUE);
        }
        catch (Erebot_Module_Uno_MoveNotAllowedException $e) {
            switch ($e->getCode()) {
                case 1:
                    $msg = $fmt->_(
                        'You cannot play multiple reverses/skips '.
                        'in a non 1vs1 game'
                    );
                    break;

                case 2:
                    $msg = $fmt->_('You cannot play multiple cards');
                    break;

                case 3:
                    $msg = $fmt->_('You may only play the card you just drew');
                    break;

                case 4:
                    $allowed = $e->getAllowedCards();
                    if (!$allowed) {
                        $msg = $fmt->_('You cannot play that move now');
                        $this->sendMessage($chan, $msg);
                        return $event->preventDefault(TRUE);
                    }
                    else {
                        $cardsTexts = array_map(
                            array($this, 'getCardText'),
                            $allowed
                        );
                        sort($cardsTexts);

                        $msg = $fmt->_(
                            'You may only play one of the following cards: '.
                            '<for from="cards" item="card" separator=" ">'.
                            '<var name="card"/></for>',
                            array(
                                'cards' => $cardsTexts,
                                'count' => count($cardsTexts),
                            )
                        );
                        $this->sendMessage($chan, $msg);
                    }
                    return $event->preventDefault(TRUE);

                default:
                    $msg = $fmt->_('You cannot play that move now');
                    break;
            }
            $this->sendMessage($chan, $msg);
            return $event->preventDefault(TRUE);
        }
        catch (Erebot_Module_Uno_MissingCardsException $e) {
            $msg = $fmt->_('You do not have the cards required for that move');
            $this->sendMessage($chan, $msg);
            return $event->preventDefault(TRUE);
        }

        $played = $game->extractCard($card, NULL);
        $msg    = $fmt->_(
            '<b><var name="nick"/></b> plays <var name="card"/> '.
            '<b><var name="count"/> times!</b>',
            array(
                'nick'  => $nick,
                'card'  => $this->getCardText($played['card']),
                'count' => $played['count'],
            )
        );
        $this->sendMessage($chan, $msg);

        $cardsCount = $current->getCardsCount();
        $next       = $game->getCurrentPlayer($chan);
        if ($cardsCount == 1) {
            $msg = $fmt->_(
                '<b><var name="nick"/></b> has <var name="logo"/>',
                array(
                    'logo'  => $this->getLogo(),
                    'nick'  => $nick,
                )
            );
            $this->sendMessage($chan, $msg);
        }
        else if (!$cardsCount) {
            if ($game->getPenalty()) {
                $drawnCards = count($game->draw());
                $msg        = $fmt->_(
                    '<var name="logo"/> <b><var name="nick"/></b> must draw '.
                    '<b><var name="count"/></b> cards.',
                    array(
                        'logo'  => $this->getLogo(),
                        'nick'  => (string) $next->getPlayer(),
                        'count' => $drawnCards,
                    )
                );
                $this->sendMessage($chan, $msg);
            }

            $msg = $fmt->_(
                '<var name="logo"/> game finished in <var name="duration"/>. '.
                'The winner is <b><var name="nick"/></b>!',
                array(
                    'logo'      => $this->getLogo(),
                    'duration'  => new Erebot_Styling_Duration(
                        $game->getElapsedTime()
                    ),
                    'nick'      => $nick,
                )
            );
            $this->sendMessage($chan, $msg);

            $score      = 0;
            $players    = $game->getPlayers();
            foreach ($players as &$player) {
                $token = $player->getPlayer();
                if ($player !== $current) {
                    $score += $player->getScore();

                    $cards = array_map(
                        array($this, 'getCardText'),
                        $player->getCards()
                    );
                    sort($cards);

                    $msg = $fmt->_(
                        '<var name="nick"/> still had '.
                        '<for from="cards" item="card" separator=" ">'.
                        '<var name="card"/></for>',
                        array(
                            'nick'  => (string) $token,
                            'cards' => $cards,
                            'count' => count($cards),
                        )
                    );
                    $this->sendMessage($chan, $msg);
                }
            }
            unset($player);

            $msg = $fmt->_(
                '<var name="nick"/> wins with '.
                '<b><var name="score"/></b> points',
                array(
                    'nick'  => $nick,
                    'score' => $score,
                )
            );
            $this->sendMessage($chan, $msg);

            $registry = $this->_connection->getModule(
                'Erebot_Module_TriggerRegistry'
            );
            $registry->freeTriggers($this->_chans[$chan]['triggers_token']);

            foreach ($this->_chans[$chan]['handlers'] as &$handler)
                $this->_connection->removeEventHandler($handler);
            unset($handler);

            unset($this->_chans[$chan]);
            return $event->preventDefault(TRUE);
        }

        if ($skippedPlayer) {
            $skippedNick    = (string) $skippedPlayer->getPlayer();
            $msg            = $fmt->_(
                '<var name="logo"/> '.
                '<b><var name="nick"/></b> skips his turn!',
                array(
                    'nick'  => $skippedNick,
                    'logo'  => $this->getLogo(),
                )
            );
            $this->sendMessage($chan, $msg);
        }

        if ($waitingForColor) {
            $msg = $fmt->_(
                '<var name="logo"/> <b><var name="nick"/></b>, '.
                'please choose a color with <b><var name="cmd"/> '.
                '&lt;r|b|g|y&gt;</b>',
                array(
                    'logo'  => $this->getLogo(),
                    'nick'  => $nick,
                    'cmd'   => $this->_chans[$chan]['triggers']['choose'],
                )
            );
            $this->sendMessage($chan, $msg);
        }

        else {
            if (substr($played['card'], 0, 1) == 'w') {
                $msg = $fmt->_(
                    '<var name="logo"/> '.
                    'The color is now <var name="color"/>',
                    array(
                        'logo'  => $this->getLogo(),
                        'color' => $this->getCardText($played['color']),
                    )
                );
                $this->sendMessage($chan, $msg);
            }

            if ($game->getPenalty()) {
                $msg = $fmt->_(
                    '<var name="logo"/> '.
                    'Next player must respond correctly or pick '.
                    '<b><var name="count"/></b> cards',
                    array(
                        'logo'  => $this->getLogo(),
                        'count' => $game->getPenalty(),
                    )
                );
                $this->sendMessage($chan, $msg);
            }
        }

        $this->showTurn($event);

        $cards  =   array_map(array($this, 'getCardText'), $next->getCards());
        sort($cards);

        $msg = $fmt->_(
            'Your cards: <for from="cards" item="card" '.
            'separator=" "><var name="card"/></for>',
            array(
                'cards' => $cards,
                'count' => count($cards),
            )
        );
        $this->sendMessage((string) $next->getPlayer(), $msg);
        return $event->preventDefault(TRUE);
    }

    public function handleShowCardsCount(
        Erebot_Interface_EventHandler   $handler,
        Erebot_Interface_Event_ChanText $event
    )
    {
        $chan   = $event->getChan();
        $nick   = $event->getSource();
        $fmt    = $this->getFormatter($chan);

        if (!isset($this->_chans[$chan]['game'])) return;
        $game       =&  $this->_chans[$chan]['game'];
        $players    =&  $game->getPlayers();
        $counts     =   array();
        $ingame     =   NULL;

        foreach ($players as &$player) {
            $pnick          = (string) $player->getPlayer();
            $counts[$pnick] = $player->getCardsCount();
            if ($nick == $pnick)
                $ingame =& $player;
        }
        unset($player);

        $msg = $fmt->_(
            '<var name="logo"/> Cards: <for from="counts" '.
            'item="count" key="nick"><b><var name="nick"/></b>: '.
            '<var name="count"/></for>',
            array(
                'logo'      => $this->getLogo(),
                'counts'    => $counts,
            )
        );
        $this->sendMessage($chan, $msg);

        if ($ingame !== NULL) {
            $cards = array_map(
                array($this, 'getCardText'),
                $ingame->getCards()
            );
            sort($cards);

            $msg = $fmt->_(
                'Your cards: <for from="cards" item="card" '.
                'separator=" "><var name="card"/></for>',
                array('cards' => $cards)
            );
            $this->sendMessage($nick, $msg);
        }

        return $event->preventDefault(TRUE);
    }

    public function handleShowDiscard(
        Erebot_Interface_EventHandler   $handler,
        Erebot_Interface_Event_ChanText $event
    )
    {
        $chan   = $event->getChan();
        $fmt    = $this->getFormatter($chan);

        if (!isset($this->_chans[$chan]['game'])) return;
        $game       =&  $this->_chans[$chan]['game'];

        $card       =   $game->getLastPlayedCard();
        if ($card === NULL) {
            $msg = $fmt->_('No card has been played yet');
            $this->sendMessage($chan, $msg);
            return $event->preventDefault(TRUE);
        }

        $count      = $game->getRemainingCardsCount();
        $discard    = $this->getCardText($card['card']);
        $vars       = array(
            'logo'      => $this->getLogo(),
            'discard'   => $discard,
            'count'     => $count,
        );

        if ($count === NULL)
            $msg = $fmt->_(
                '<var name="logo"/> Current discard: <var name="discard"/>',
                $vars
            );
        else
            $msg = $fmt->_(
                '<var name="logo"/> Current discard: '.
                '<var name="discard"/> (<b><var name="count"/></b>'.
                ' cards left in stock)',
                $vars
            );
        $this->sendMessage($chan, $msg);

        if ($card['card'][0] == 'w' && !empty($card['color'])) {
            $msg = $fmt->_(
                '<var name="logo"/> The current color is '.
                '<var name="color"/>',
                array(
                    'logo'  => $this->getLogo(),
                    'color' => $this->getCardText($card['color']),
                )
            );
            $this->sendMessage($chan, $msg);
        }

        return $event->preventDefault(TRUE);
    }

    public function handleShowOrder(
        Erebot_Interface_EventHandler   $handler,
        Erebot_Interface_Event_ChanText $event
    )
    {
        $chan   = $event->getChan();
        $fmt    = $this->getFormatter($chan);

        if (!isset($this->_chans[$chan]['game'])) return;
        $game       =&  $this->_chans[$chan]['game'];
        $players    =&  $game->getPlayers();
        $nicks      =   array();
        foreach ($players as &$player) {
            $nicks[] = (string) $player->getPlayer();
        }
        unset($player);

        $msg = $fmt->_(
            '<var name="logo"/> Playing order: <for '.
            'from="nicks" item="nick"><b><var name="nick"/>'.
            '</b></for>',
            array(
                'logo'  => $this->getLogo(),
                'nicks' => $nicks,
            )
        );
        $this->sendMessage($chan, $msg);
        return $event->preventDefault(TRUE);
    }

    public function handleShowTime(
        Erebot_Interface_EventHandler   $handler,
        Erebot_Interface_Event_ChanText $event
    )
    {
        $chan       = $event->getChan();
        $current    = $this->getCurrentPlayer($chan);
        $fmt        = $this->getFormatter($chan);

        if ($current === NULL) return;
        $game       =&  $this->_chans[$chan]['game'];

        $msg = $fmt->_(
            '<var name="logo"/> game running since '.
            '<var name="duration"/>',
            array(
                'logo'      => $this->getLogo(),
                'duration'  => new Erebot_Styling_Duration(
                    $game->getElapsedTime()
                ),
            )
        );
        $this->sendMessage($chan, $msg);
        return $event->preventDefault(TRUE);
    }

    public function handleShowTurn(
        Erebot_Interface_EventHandler   $handler,
        Erebot_Interface_Event_ChanText $event
    )
    {
        $chan       = $event->getChan();
        $nick       = $event->getSource();
        $current    = $this->getCurrentPlayer($chan);
        $fmt        = $this->getFormatter($chan);

        if ($current === NULL) return;
        $currentNick = (string) $current->getPlayer();

        $vars = array(
            'logo'  => $this->getLogo(),
            'nick'  => $currentNick,
        );
        if (!strcasecmp($nick, $currentNick))
            $msg = $fmt->_(
                '<var name="logo"/> <b><var name="nick"'.
                '/></b>: it\'s your turn sleepyhead!',
                $vars
            );
        else
            $msg = $fmt->_(
                '<var name="logo"/> It\'s <b><var name='.
                '"nick"/></b>\'s turn.',
                $vars
            );
        $this->sendMessage($chan, $msg);
        return $event->preventDefault(TRUE);
    }
}

