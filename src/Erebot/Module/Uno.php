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
    static protected $_metadata = array(
        'requires'  =>  array(
            'Erebot_Module_TriggerRegistry',
            'Erebot_Module_IrcTracker',
            'Erebot_Module_Helper',
        ),
    );
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
        $import = new Doctrine_Import_Schema();
        $builder = new Doctrine_Import_Builder();
        $array = $import->buildSchema(
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
###            $this->_db = Doctrine_Manager::connection("sqlite:////tmp/uno.sqlite");
###            $this->_db->setAttribute(Doctrine_Core::ATTR_QUOTE_IDENTIFIER, true);

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
                $this->_connection->removeEventHandler($this->_creator['handler']);
                $registry->freeTriggers($this->creator['trigger'], $matchAny);
            }

            $triggerCreate              = $this->parseString('trigger_create', 'uno');
            $this->_creator['trigger']  = $registry->registerTriggers($triggerCreate, $matchAny);
            if ($this->_creator['trigger'] === NULL)
                throw new Exception($this->_translator->gettext(
                    'Could not register UNO creation trigger'
                ));

            $this->_creator['handler']  = new Erebot_EventHandler(
                array($this, 'handleCreate'),
                new Erebot_Event_Match_All(
                    new Erebot_Event_Match_InstanceOf('Erebot_Interface_Event_ChanText'),
                    new Erebot_Event_Match_Any(
                        new Erebot_Event_Match_TextStatic($triggerCreate, TRUE),
                        new Erebot_Event_Match_TextWildcard($triggerCreate.' *', TRUE)
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

    public function getHelp(Erebot_Interface_Event_Base_TextMessage $event, $words)
    {
        if ($event instanceof Erebot_Interface_Event_Base_Private) {
            $target = $event->getSource();
            $chan   = NULL;
        }
        else
            $target = $chan = $event->getChan();

        $translator     = $this->getTranslator($chan);
        $triggerCreate  = $this->parseString('trigger_create', 'uno');

        $commands   =   array(
            'challenge'    => $this->parseString('trigger_challenge',    'ch'),
            'choose'       => $this->parseString('trigger_choose',       'co'),
            'draw'         => $this->parseString('trigger_draw',         'pe'),
            'join'         => $this->parseString('trigger_join',         'jo'),
            'pass'         => $this->parseString('trigger_pass',         'pa'),
            'play'         => $this->parseString('trigger_play',         'pl'),
            'show_cards'   => $this->parseString('trigger_show_cards',   'ca'),
            'show_discard' => $this->parseString('trigger_show_discard', 'cd'),
            'show_order'   => $this->parseString('trigger_show_order',   'od'),
            'show_time'    => $this->parseString('trigger_show_time',    'ti'),
            'show_turn'    => $this->parseString('trigger_show_turn',    'tu'),
        );

        $bot        =&  $this->_connection->getBot();
        $moduleName =   get_class();
        $nbArgs     =   count($words);

        if ($nbArgs == 1 && $words[0] == strtolower($moduleName)) {
            $msg = $translator->gettext(
                'Provides the <b><var name="trigger_create"/></b> command '.
                'which starts a new Uno game. Once a game has been created, '.
                'other commands become available to interact with the bot '.
                '(<for item="command" from="commands"><b><var '.
                'name="command"/></b></for>). Use "!help <var '.
                'name="module"/>&lt;<u>command</u>&gt;" to get help '.
                'on some &lt;<u>command</u>&gt;.'
            );
            $formatter = new Erebot_Styling($msg, $translator);
            $formatter->assign('trigger_create', $triggerCreate);
            $formatter->assign('commands',  $commands);
            $formatter->assign('module',    $moduleName);
            $this->sendMessage($target, $formatter->render());
            return TRUE;
        }

        else if (($words[0] == $moduleName || isset($this->_chans[$chan])) &&
                $nbArgs > 1) {
            foreach ($commands as $cmd => $trigger) {
                if (!strcasecmp($trigger, $words[1])) {
                    switch ($cmd) {
                        case 'challenge':
                            $msg = $translator->gettext(
                                'You may only use this command after someone '.
                                'played a <var name="w+4"/> and no other '.
                                'penalty had been played before. It shows you '.
                                'the hand of the player you challenged. '.
                                'If that person played a <var name="w+4"/> '.
                                'while he or she had a card of the proper '.
                                'color (except for special cards like +2, '.
                                'Skip or Reverse), that player must draw 4 '.
                                'cards. Otherwise, you must draw the 4 '.
                                'initial cards, plus 2 additional cards.'
                            );
                            break;

                        case 'choose':
                            $msg = $translator->gettext(
                                'Select the new color after you played a '.
                                '<var name="w"/> or <var name="w+4"/>, eg. '.
                                '"<var name="choose"/> &lt;<u>color</u>&gt;". '.
                                'Valid &lt;<u>color</u>&gt;s: <b>r</b> (red), '.
                                '<b>b</b> (blue), <b>g</b> (green) &amp; '.
                                '<b>y</b> (yellow). The new color may also '.
                                'be selected directly when playing the card, '.
                                'eg. "<var name="play"/> w+4b".'
                            );
                            break;

                        case 'draw':
                            $msg = $translator->gettext(
                                'Draw a new card. You may choose to play the '.
                                'card you just drew afterwards, using the '.
                                '"<var name="play"/>" command. This command '.
                                'can also be used to draw penalty cards and '.
                                'pass your turn.'
                            );
                            break;

                        case 'join':
                            $msg = $translator->gettext(
                                'Join the current <var name="logo"/> game. '.
                                'The bot will send you the list of your cards '.
                                'in a separate query.'
                            );
                            break;

                        case 'pass':
                            $msg = $translator->gettext(
                                'Pass your turn. Note that you must first '.
                                'draw a card with <var name="draw"/> before '.
                                'you pass. This command can also be used '.
                                'to draw penalty cards and pass your turn.'
                            );
                            break;

                        case 'play':
                            $msg = $translator->gettext(
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
                                '<var name="w+4"/><var name="reset"/>.'
                            );
                            break;

                        case 'show_cards':
                            $msg = $translator->gettext(
                                'Displays the number of cards in '.
                                'each players\'s hand. Also displays '.
                                'your hand in a separate query.'
                            );
                            break;

                        case 'show_discard':
                            $msg = $translator->gettext(
                                'Displays the top card of the discard.'
                            );
                            break;

                        case 'show_order':
                            $msg = $translator->gettext(
                                'Displays the order in which players '.
                                'take turns to play.'
                            );
                            break;

                        case 'show_time':
                            $msg = $translator->gettext(
                                'Displays how much time has elapsed '.
                                'since the beginning of the game.'
                            );
                            break;

                        case 'show_turn':
                            $msg = $translator->gettext(
                                'Displays the nickname of the player '.
                                'whose turn it is to play'
                            );
                            break;

                        default:
                            throw new Erebot_InvalidValueException('Unknown command');
                    }
                    $formatter = new Erebot_Styling($msg, $translator);
                    $formatter->assign('w', $this->getCardText('w'));
                    $formatter->assign('w+4', $this->getCardText('w+4'));
                    $formatter->assign('r1', $this->getCardText('r1'));
                    $formatter->assign('r+2', $this->getCardText('r+2'));
                    $formatter->assign('rs', $this->getCardText('rs'));
                    $formatter->assign('rr', $this->getCardText('rr'));
                    $formatter->assign('logo', $this->getLogo());
                    $reflector = new ReflectionObject($formatter);
                    $resetCode = $reflector->getConstant('CODE_RESET');
                    $formatter->assign('reset', $resetCode);
                    foreach ($commands as $cmd => $trg)
                        $formatter->assign($cmd, $trg);
                    $this->sendMessage($target, $formatter->render());
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

        if (!isset($colorCodes[$color]))
            throw new Exception(sprintf(
                'Unknown color! (%s, %s)',
                $color,
                $text
            ));

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
                        '', '');
        $this->handleShowTurn($synEvent);
    }

    public function handleCreate(Erebot_Interface_Event_ChanText $event)
    {
        $nick       =   $event->getSource();
        $chan       =   $event->getChan();
        $rules      =   strtolower($event->getText()->getTokens(1));
        $translator =   $this->getTranslator($chan);

        if (isset($this->_chans[$chan])) {
            $infos      =&  $this->_chans[$chan];
            $creator    =   $infos['game']->getCreator();
            $message    =   $translator->gettext(
                '<var name="logo"/> A game '.
                'is already running, managed by <var name="'.
                'creator"/>. The following rules apply: <for '.
                'from="rules" item="rule"><var name="rule"/>'.
                '</for>. Say "<b><var name="trigger"/></b>" '.
                'to join it.'
            );
            $tpl        = new Erebot_Styling($message, $translator);

            $tpl->assign('logo',    $this->getLogo());
            $tpl->assign('creator', (string) $creator);
            $tpl->assign('rules',   $infos['game']->getRules(TRUE));
            $tpl->assign('trigger', $infos['triggers']['join']);
            $this->sendMessage($chan, $tpl->render());
            return $event->preventDefault(TRUE);
        }

        $registry   =   $this->_connection->getModule(
            'Erebot_Module_TriggerRegistry'
        );
        $triggers   =   array(
            'challenge'    => $this->parseString('trigger_challenge',    'ch'),
            'choose'       => $this->parseString('trigger_choose',       'co'),
            'draw'         => $this->parseString('trigger_draw',         'pe'),
            'join'         => $this->parseString('trigger_join',         'jo'),
            'pass'         => $this->parseString('trigger_pass',         'pa'),
            'play'         => $this->parseString('trigger_play',         'pl'),
            'show_cards'   => $this->parseString('trigger_show_cards',   'ca'),
            'show_discard' => $this->parseString('trigger_show_discard', 'cd'),
            'show_order'   => $this->parseString('trigger_show_order',   'od'),
            'show_time'    => $this->parseString('trigger_show_time',    'ti'),
            'show_turn'    => $this->parseString('trigger_show_turn',    'tu'),
        );
        $token  = $registry->registerTriggers($triggers, $chan);
        if ($token === NULL) {
            $message = $translator->gettext(
                'Unable to register triggers for '.
                '<var name="logo"/> game!'
            );
            $tpl = new Erebot_Styling($message, $translator);
            $tpl->assign('logo', $this->getLogo());
            $this->sendMessage($chan, $tpl->render());
            return $event->preventDefault(TRUE);
        }

        $this->_chans[$chan] = array();
        $infos  =&  $this->_chans[$chan];

        if (trim($rules) == '')
            $rules = $this->parseString('default_rules', '');

        $tracker = $this->_connection->getModule('Erebot_Module_IrcTracker');
        $creator                    =   $tracker->startTracking($nick);
        $infos['triggers_token']    =   $token;
        $infos['triggers']          =&  $triggers;
        $infos['game']              =   new Erebot_Module_Uno_Game(
            $creator,
            $rules
        );

        $infos['handlers']['challenge']     =   new Erebot_EventHandler(
            array($this, 'handleChallenge'),
            new Erebot_Event_Match_All(
                new Erebot_Event_Match_InstanceOf('Erebot_Interface_Event_ChanText'),
                new Erebot_Event_Match_TextStatic($triggers['challenge'], NULL),
                new Erebot_Event_Match_Chan($chan)
            )
        );

        $infos['handlers']['choose']        =   new Erebot_EventHandler(
            array($this, 'handleChoose'),
            new Erebot_Event_Match_All(
                new Erebot_Event_Match_InstanceOf('Erebot_Interface_Event_ChanText'),
                new Erebot_Event_Match_TextWildcard($triggers['choose'].' *', NULL),
                new Erebot_Event_Match_Chan($chan)
            )
        );

        $infos['handlers']['draw']          =   new Erebot_EventHandler(
            array($this, 'handleDraw'),
            new Erebot_Event_Match_All(
                new Erebot_Event_Match_InstanceOf('Erebot_Interface_Event_ChanText'),
                new Erebot_Event_Match_TextStatic($triggers['draw'], NULL),
                new Erebot_Event_Match_Chan($chan)
            )
        );

        $infos['handlers']['join']          =   new Erebot_EventHandler(
            array($this, 'handleJoin'),
            new Erebot_Event_Match_All(
                new Erebot_Event_Match_InstanceOf('Erebot_Interface_Event_ChanText'),
                new Erebot_Event_Match_TextStatic($triggers['join'], NULL),
                new Erebot_Event_Match_Chan($chan)
            )
        );

        $infos['handlers']['pass']          =   new Erebot_EventHandler(
            array($this, 'handlePass'),
            new Erebot_Event_Match_All(
                new Erebot_Event_Match_InstanceOf('Erebot_Interface_Event_ChanText'),
                new Erebot_Event_Match_TextStatic($triggers['pass'], NULL),
                new Erebot_Event_Match_Chan($chan)
            )
        );

        $infos['handlers']['play']          =   new Erebot_EventHandler(
            array($this, 'handlePlay'),
            new Erebot_Event_Match_All(
                new Erebot_Event_Match_InstanceOf('Erebot_Interface_Event_ChanText'),
                new Erebot_Event_Match_TextWildcard($triggers['play'].' *', NULL),
                new Erebot_Event_Match_Chan($chan)
            )
        );

        $infos['handlers']['show_cards']    =   new Erebot_EventHandler(
            array($this, 'handleShowCardsCount'),
            new Erebot_Event_Match_All(
                new Erebot_Event_Match_InstanceOf('Erebot_Interface_Event_ChanText'),
                new Erebot_Event_Match_TextStatic($triggers['show_cards'], NULL),
                new Erebot_Event_Match_Chan($chan)
            )
        );

        $infos['handlers']['show_discard']  =   new Erebot_EventHandler(
            array($this, 'handleShowDiscard'),
            new Erebot_Event_Match_All(
                new Erebot_Event_Match_InstanceOf('Erebot_Interface_Event_ChanText'),
                new Erebot_Event_Match_TextStatic($triggers['show_discard'], NULL),
                new Erebot_Event_Match_Chan($chan)
            )
        );

        $infos['handlers']['show_order']    =   new Erebot_EventHandler(
            array($this, 'handleShowOrder'),
            new Erebot_Event_Match_All(
                new Erebot_Event_Match_InstanceOf('Erebot_Interface_Event_ChanText'),
                new Erebot_Event_Match_TextStatic($triggers['show_order'], NULL),
                new Erebot_Event_Match_Chan($chan)
            )
        );

        $infos['handlers']['show_time']     =   new Erebot_EventHandler(
            array($this, 'handleShowTime'),
            new Erebot_Event_Match_All(
                new Erebot_Event_Match_InstanceOf('Erebot_Interface_Event_ChanText'),
                new Erebot_Event_Match_TextStatic($triggers['show_time'], NULL),
                new Erebot_Event_Match_Chan($chan)
            )
        );

        $infos['handlers']['show_turn']     =   new Erebot_EventHandler(
            array($this, 'handleShowTurn'),
            new Erebot_Event_Match_All(
                new Erebot_Event_Match_InstanceOf('Erebot_Interface_Event_ChanText'),
                new Erebot_Event_Match_TextStatic($triggers['show_turn'], NULL),
                new Erebot_Event_Match_Chan($chan)
            )
        );

        foreach ($infos['handlers'] as &$handler)
            $this->_connection->addEventHandler($handler);

        $message = $translator->gettext(
            '<var name="logo"/> A new game has been '.
            'created in <var name="chan"/>. The following rules '.
            'apply: <for from="rules" item="rule"><var '.
            'name="rule"/></for>. Say "<b><var name="trigger"/>'.
            '</b>" to join it.'
        );
        $tpl = new Erebot_Styling($message, $translator);
        $tpl->assign('logo',    $this->getLogo());
        $tpl->assign('chan',    $chan);
        $tpl->assign('rules',   $infos['game']->getRules(TRUE));
        $tpl->assign('trigger', $infos['triggers']['join']);
        $this->sendMessage($chan, $tpl->render());
        return $event->preventDefault(TRUE);
    }

    public function handleChallenge(Erebot_Interface_Event_ChanText $event)
    {
        $chan       =   $event->getChan();
        $nick       =   $event->getSource();
        $current    =   $this->getCurrentPlayer($chan);
        $game       =&  $this->_chans[$chan]['game'];
        $translator =   $this->getTranslator($chan);

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
            $message = $translator->gettext(
                '<var name="logo"/> Previous move cannot be challenged!'
            );
            $tpl = new Erebot_Styling($message, $translator);
            $tpl->assign('logo', $this->getLogo());
            $this->sendMessage($chan, $tpl->render());
            return $event->preventDefault();
        }

        $lastNick   = (string) $lastPlayer->getPlayer();
        $message = $translator->gettext(
            '<var name="logo"/> <b><var name="nick"/></b> challenges '.
            '<b><var name="last_nick"/></b>\'s <var name="card"/>.'
        );
        $tpl = new Erebot_Styling($message, $translator);
        $tpl->assign('logo',        $this->getLogo());
        $tpl->assign('nick',        $nick);
        $tpl->assign('last_nick',   $lastNick);
        $tpl->assign('card',        $this->getCardText('w+4'));
        $this->sendMessage($chan, $tpl->render());

        $cardsTexts = array_map(
            array($this, 'getCardText'),
            $challenge['hand']
        );
        sort($cardsTexts);

        $message = $translator->gettext(
            '<b><var name="nick"/></b>\'s cards: '.
            '<for from="cards" item="card" separator=" ">'.
            '<var name="card"/></for>'
        );
        $tpl = new Erebot_Styling($message, $translator);
        $tpl->assign('nick',    $lastNick);
        $tpl->assign('cards',   $cardsTexts);
        $this->sendMessage($nick, $tpl->render());

        if (!$challenge['legal']) {
            $message = $translator->gettext(
                '<b><var name="nick"/></b>\'s move '.
                '<b>WAS NOT</b> legal. <b><var name="nick"/></b> '.
                'must pick <b><var name="count"/></b> cards!'
            );
            $tpl = new Erebot_Styling($message, $translator);
            $tpl->assign('nick',    $lastNick);
            $tpl->assign('count',   count($challenge['cards']));
            $this->sendMessage($chan, $tpl->render());

            $cardsTexts = array_map(array($this, 'getCardText'), $challenge['cards']);
            sort($cardsTexts);

            $message = $translator->gettext(
                'You drew: <for from="cards" item="card" '.
                'separator=" "><var name="card"/></for>'
            );
            $tpl = new Erebot_Styling($message, $translator);
            $tpl->assign('cards',   $cardsTexts);
            $this->sendMessage($lastNick, $tpl->render());
        }
        else {
            $message = $translator->gettext(
                '<b><var name="last_nick"/></b>\'s move '.
                'was legal. <b><var name="nick"/></b> must pick '.
                '<b><var name="count"/></b> cards!'
            );
            $tpl = new Erebot_Styling($message, $translator);
            $tpl->assign('last_nick',   $lastNick);
            $tpl->assign('nick',        $nick);
            $tpl->assign('count',       count($challenge['cards']));
            $this->sendMessage($chan, $tpl->render());

            $cardsTexts = array_map(
                array($this, 'getCardText'),
                $challenge['cards']
            );
            sort($cardsTexts);

            $message = $translator->gettext(
                'You drew: <for from="cards" item="card" '.
                'separator=" "><var name="card"/></for>'
            );
            $tpl = new Erebot_Styling($message, $translator);
            $tpl->assign('cards',   $cardsTexts);
            $this->sendMessage($nick, $tpl->render());
        }

        $this->showTurn($event);
        $event->preventDefault(TRUE);
    }

    public function handleChoose(Erebot_Interface_Event_ChanText $event)
    {
        $chan       =   $event->getChan();
        $nick       =   $event->getSource();
        $current    =   $this->getCurrentPlayer($chan);
        $translator =   $this->getTranslator(FALSE);

        if ($current === NULL) return;
        $currentNick    =   (string) $current->getPlayer();
        if (strcasecmp($nick, $currentNick)) return;

        $color  = strtolower($event->getText()->getTokens(1, 1));
        try {
            $this->_chans[$chan]['game']->chooseColor($color);
            $message    = $translator->gettext(
                '<var name="logo"/> The color is now <var name="color"/>'
            );
            $tpl        = new Erebot_Styling($message, $translator);
            $tpl->assign('color', $this->getCardText($color));
            $this->sendMessage($chan, $tpl->render());
        }
        catch (Erebot_Module_Uno_Exception $e) {
            $message    = $translator->gettext(
                'Hmm, yes <b><var name="nick"/></b>, what is it?'
            );
            $tpl        = new Erebot_Styling($message, $translator);
            $tpl->assign('nick', $nick);
            $this->sendMessage($chan, $tpl->render());
        }

        return $event->preventDefault(TRUE);
    }

    public function handleDraw(Erebot_Interface_Event_ChanText $event)
    {
        $chan       =   $event->getChan();
        $nick       =   $event->getSource();
        $current    =   $this->getCurrentPlayer($chan);
        $translator =   $this->getTranslator($chan);

        if ($current === NULL) return;
        $currentNick = (string) $current->getPlayer();
        if (strcasecmp($nick, $currentNick)) return;

        $game =& $this->_chans[$chan]['game'];
        try {
            $drawnCards = $game->draw();
        }
        catch (Erebot_Module_Uno_WaitingForColorException $e) {
            $message = $translator->gettext(
                '<var name="logo"/> <b><var name="nick"/></b>, '.
                'please choose a color with <b><var name="cmd"/> '.
                '&lt;r|b|g|y&gt;</b>'
            );
            $tpl = new Erebot_Styling($message, $translator);
            $tpl->assign('logo', $this->getLogo());
            $tpl->assign('nick', $nick);
            $tpl->assign('cmd', $this->_chans[$chan]['triggers']['choose']);
            $this->sendMessage($chan, $tpl->render());
        }
        catch (Erebot_Module_Uno_AlreadyDrewException $e) {
            $message = $translator->gettext('You already drew a card');
            $this->sendMessage($chan, $message);
            return $event->preventDefault(TRUE);
        }

        $nbDrawnCards = count($drawnCards);
        if ($nbDrawnCards > 1) {
            $message = $translator->gettext(
                '<b><var name="nick"/></b> passes turn, '.
                'and has to pick <b><var name="count"/></b> cards!'
            );
            $tpl = new Erebot_Styling($message, $translator);
            $tpl->assign('nick', $nick);
            $tpl->assign('count', $nbDrawnCards);
            $this->sendMessage($chan, $tpl->render());

            $this->showTurn($event);

            $player = $game->getCurrentPlayer();
            $cardsTexts = array_map(
                array($this, 'getCardText'),
                $player->getCards()
            );
            sort($cardsTexts);

            $message = $translator->gettext(
                'Your cards: <for from="cards" item="card" '.
                'separator=" "><var name="card"/></for>'
            );
            $tpl = new Erebot_Styling($message, $translator);
            $tpl->assign('cards', $cardsTexts);
            $this->sendMessage(
                (string) $player->getPlayer(),
                $tpl->render()
            );
        }
        else {
            $message = $translator->gettext(
                '<b><var name="nick"/></b> draws a card'
            );
            $tpl = new Erebot_Styling($message, $translator);
            $tpl->assign('nick', $nick);
            $this->sendMessage($chan, $tpl->render());
        }

        $cardsTexts = array_map(array($this, 'getCardText'), $drawnCards);
        sort($cardsTexts);

        $message = $translator->gettext(
            'You drew: <for from="cards" item="card" '.
            'separator=" "><var name="card"/></for>'
        );
        $tpl = new Erebot_Styling($message, $translator);
        $tpl->assign('cards', $cardsTexts);

        $this->sendMessage($nick, $tpl->render());
        return $event->preventDefault(TRUE);
    }

    public function handleJoin(Erebot_Interface_Event_ChanText $event)
    {
        $nick       =   $event->getSource();
        $chan       =   $event->getChan();
        $translator =   $this->getTranslator($chan);

        if (!isset($this->_chans[$chan])) return;
        $game =& $this->_chans[$chan]['game'];

        $players =& $game->getPlayers();
        foreach ($players as &$player) {
            if (!strcasecmp((string) $player->getPlayer(), $nick)) {
                $message    = $translator->gettext(
                    '<var name="logo"/> You\'re already '.
                    'in the game <b><var name="nick"/></b>!'
                );
                $tpl        = new Erebot_Styling($message, $translator);
                $tpl->assign('logo', $this->getLogo());
                $tpl->assign('nick', $nick);
                $this->sendMessage($chan, $tpl->render());
                return $event->preventDefault(TRUE);
            }
        }

        $message = $translator->gettext(
            '<b><var name="nick"/></b> joins this '.
            '<var name="logo"/> game.'
        );
        $tpl = new Erebot_Styling($message, $translator);
        $tpl->assign('nick', $nick);
        $tpl->assign('logo', $this->getLogo());
        $this->sendMessage($chan, $tpl->render());

        $tracker = $this->_connection->getModule('Erebot_Module_IrcTracker');
        $token  =   $tracker->startTracking($nick);
        $player =&  $game->join($token);
        $cards  =   $player->getCards();
        $cards  =   array_map(array($this, 'getCardText'), $cards);
        sort($cards);

        $message = $translator->gettext(
            'Your cards: <for from="cards" item="card" '.
            'separator=" "><var name="card"/></for>'
        );
        $tpl = new Erebot_Styling($message, $translator);
        $tpl->assign('cards', $cards);
        $this->sendMessage($nick, $tpl->render());

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
            $message        = $translator->gettext(
                '<b><var name="nick"/></b> deals '.
                'the first card from the stock'
            );
            $tpl            = new Erebot_Styling($message, $translator);
            $tpl->assign('nick', $currentNick);
            $this->sendMessage($chan, $tpl->render());

            $firstCard  =   $game->getFirstCard();
            $discard    =   $this->getCardText($firstCard);
            $message    =   $translator->gettext(
                '<var name="logo"/> Current discard: <var name="discard"/>'
            );

            $tpl        =   new Erebot_Styling($message, $translator);
            $tpl->assign('logo',    $this->getLogo());
            $tpl->assign('discard', $discard);
            $this->sendMessage($chan, $tpl->render());

            $skippedPlayer  = $game->play($firstCard);
            if ($skippedPlayer) {
                $skippedNick    = (string) $skippedPlayer->getPlayer();
                $message        = $translator->gettext(
                    '<var name="logo"/> <b><var name="nick"/></b> '.
                    'skips his turn!'
                );
                $tpl            = new Erebot_Styling($message, $translator);
                $tpl->assign('nick', $skippedNick);
                $tpl->assign('logo', $this->getLogo());
                $this->sendMessage($chan, $tpl->render());
            }

            $this->showTurn($event);
            return $event->preventDefault(TRUE);
        }

        return $event->preventDefault(TRUE);
    }

    public function handlePass(Erebot_Interface_Event_ChanText $event)
    {
        $chan       =   $event->getChan();
        $nick       =   $event->getSource();
        $current    =   $this->getCurrentPlayer($chan);
        $translator =   $this->getTranslator($chan);

        if ($current === NULL) return;
        $currentNick = (string) $current->getPlayer();
        if (strcasecmp($nick, $currentNick)) return;

        $game       =&  $this->_chans[$chan]['game'];
        try {
            $drawnCards = $game->pass();
        }
        catch (Erebot_Module_Uno_WaitingForColorException $e) {
            $message = $translator->gettext(
                '<var name="logo"/> <b><var name="nick"/></b>, '.
                'please choose a color with <b><var name="cmd"/> '.
                '&lt;r|b|g|y&gt;</b>'
            );
            $tpl = new Erebot_Styling($message, $translator);
            $tpl->assign('logo', $this->getLogo());
            $tpl->assign('nick', $nick);
            $tpl->assign('cmd', $this->_chans[$chan]['triggers']['choose']);
            $this->sendMessage($chan, $tpl->render());
        }
        catch (Erebot_Module_Uno_MustDrawBeforePassException $e) {
            $message = $translator->gettext('You must draw a card first');
            $this->sendMessage($chan, $message);
            return $event->preventDefault(TRUE);
        }

        $nbDrawnCards = count($drawnCards);
        if ($nbDrawnCards > 1)
            $message = $translator->gettext(
                '<b><var name="nick"/></b> passes turn, '.
                'and has to pick <b><var name="count"/></b> cards!'
            );
        else
            $message = $translator->gettext(
                '<b><var name="nick"/></b> passes turn'
            );

        $tpl = new Erebot_Styling($message, $translator);
        $tpl->assign('nick', $nick);
        $tpl->assign('count', $nbDrawnCards);
        $this->sendMessage($chan, $tpl->render());

        if (count($drawnCards)) {
            $cardsTexts = array_map(array($this, 'getCardText'), $drawnCards);
            sort($cardsTexts);

            $message = $translator->gettext(
                'You drew: <for from="cards" item="card" '.
                'separator=" "><var name="card"/></for>'
            );
            $tpl = new Erebot_Styling($message, $translator);
            $tpl->assign('cards', $cardsTexts);
            $this->sendMessage($nick, $tpl->render());
        }

        $this->showTurn($event);

        $player = $game->getCurrentPlayer();
        $cardsTexts = array_map(
            array($this, 'getCardText'),
            $player->getCards()
        );
        sort($cardsTexts);

        $message = $translator->gettext(
            'Your cards: <for from="cards" item="card" '.
            'separator=" "><var name="card"/></for>'
        );
        $tpl = new Erebot_Styling($message, $translator);
        $tpl->assign('cards', $cardsTexts);
        $this->sendMessage(
            (string) $player->getPlayer(),
            $tpl->render()
        );

        return $event->preventDefault(TRUE);
    }

    public function handlePlay(Erebot_Interface_Event_ChanText $event)
    {
        $chan       =   $event->getChan();
        $nick       =   $event->getSource();
        $current    =   $this->getCurrentPlayer($chan);
        $translator =   $this->getTranslator($chan);

        if ($current === NULL) return;
        $currentNick = (string) $current->getPlayer();
        if (strcasecmp($nick, $currentNick)) return;

        $game =&    $this->_chans[$chan]['game'];
        $card =     $event->getText()->getTokens(1);
        $card =     str_replace(' ', '', $card);

        $waitingForColor    = FALSE;
        $skippedPlayer      = NULL;

        try {
            $skippedPlayer = $game->play($card);
        }
        catch (Erebot_Module_Uno_WaitingForColorException $e) {
            $waitingForColor = TRUE;
        }
        catch (Erebot_Module_Uno_InvalidMoveException $e) {
            $message = $translator->gettext('This move is not valid');
            $this->sendMessage($chan, $message);
            return $event->preventDefault(TRUE);
        }
        catch (Erebot_Module_Uno_MoveNotAllowedException $e) {
            switch ($e->getCode()) {
                case 1:
                    $message = $translator->gettext(
                        'You cannot play multiple reverses/skips '.
                        'in a non 1vs1 game'
                    );
                    break;

                case 2:
                    $message = $translator->gettext(
                        'You cannot play multiple cards'
                    );
                    break;

                case 3:
                    $message = $translator->gettext(
                        'You may only play the card you just drew'
                    );
                    break;

                case 4:
                    $allowed = $e->getAllowedCards();
                    if (!$allowed) {
                        $message = $translator->gettext(
                            'You cannot play that move now'
                        );
                        $this->sendMessage($chan, $message);
                        return $event->preventDefault(TRUE);
                    }
                    else {
                        $cardsTexts = array_map(
                            array($this, 'getCardText'),
                            $allowed
                        );
                        sort($cardsTexts);

                        $message = $translator->gettext(
                            'You may only play one of the following cards: '.
                            '<for from="cards" item="card" separator=" ">'.
                            '<var name="card"/></for>'
                        );
                        $tpl = new Erebot_Styling($message, $translator);
                        $tpl->assign('cards', $cardsTexts);
                        $this->sendMessage($chan, $tpl->render());
                    }
                    return $event->preventDefault(TRUE);

                default:
                    $message = $translator->gettext(
                        'You cannot play that move now'
                    );
                    break;
            }
            $this->sendMessage($chan, $message);
            return $event->preventDefault(TRUE);
        }
        catch (Erebot_Module_Uno_MissingCardsException $e) {
            $message = $translator->gettext(
                'You do not have the cards required '.
                'for that move'
            );
            $this->sendMessage($chan, $message);
            return $event->preventDefault(TRUE);
        }

        $played     = $game->extractCard($card, NULL);
        $message    = $translator->gettext(
            '<b><var name="nick"/></b> plays <var name="card"/> '.
            '<b><var name="count"/> times!</b>'
        );
        $tpl = new Erebot_Styling($message, $translator);
        $tpl->assign('nick',    $nick);
        $tpl->assign('card',    $this->getCardText($played['card']));
        $tpl->assign('count',   $played['count']);
        $this->sendMessage($chan, $tpl->render());

        $cardsCount = $current->getCardsCount();
        $next       = $game->getCurrentPlayer($chan);
        if ($cardsCount == 1) {
            $message    = $translator->gettext(
                '<b><var name="nick"/></b> has <var name="logo"/>'
            );

            $tpl = new Erebot_Styling($message, $translator);
            $tpl->assign('logo', $this->getLogo());
            $tpl->assign('nick', $nick);
            $this->sendMessage($chan, $tpl->render());
        }
        else if (!$cardsCount) {
            if ($game->getPenalty()) {
                $drawnCards = count($game->draw());
                $message    = $translator->gettext(
                    '<var name="logo"/> <b><var name="nick"/></b> must draw '.
                    '<b><var name="count"/></b> cards.'
                );

                $tpl = new Erebot_Styling($message, $translator);
                $tpl->assign('logo', $this->getLogo());
                $tpl->assign('nick', (string) $next->getPlayer());
                $tpl->assign('count', $drawnCards);
                $this->sendMessage($chan, $tpl->render());
            }

            $message    = $translator->gettext(
                '<var name="logo"/> game finished in <var name="duration"/>. '.
                'The winner is <b><var name="nick"/></b>!'
            );

            $tpl = new Erebot_Styling($message, $translator);
            $tpl->assign('logo',        $this->getLogo());
            $tpl->assign(
                'duration',
                $translator->formatDuration($game->getElapsedTime())
            );
            $tpl->assign('nick',        $nick);
            $this->sendMessage($chan, $tpl->render());

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

                    $message = $translator->gettext(
                        '<var name="nick"/> still had '.
                        '<for from="cards" item="card" separator=" ">'.
                        '<var name="card"/></for>'
                    );
                    $tpl = new Erebot_Styling($message, $translator);
                    $tpl->assign('nick', (string) $token);
                    $tpl->assign('cards', $cards);
                    $this->sendMessage($chan, $tpl->render());
                }
            }
            unset($player);

            $message = $translator->gettext(
                '<var name="nick"/> wins with '.
                '<b><var name="score"/></b> points'
            );
            $tpl = new Erebot_Styling($message, $translator);
            $tpl->assign('nick', $nick);
            $tpl->assign('score', $score);
            $this->sendMessage($chan, $tpl->render());

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
            $message        = $translator->gettext(
                '<var name="logo"/> '.
                '<b><var name="nick"/></b> skips his turn!'
            );
            $tpl            = new Erebot_Styling($message, $translator);
            $tpl->assign('nick', $skippedNick);
            $tpl->assign('logo', $this->getLogo());
            $this->sendMessage($chan, $tpl->render());
        }

        if ($waitingForColor) {
            $message = $translator->gettext(
                '<var name="logo"/> <b><var name="nick"/></b>, '.
                'please choose a color with <b><var name="cmd"/> '.
                '&lt;r|b|g|y&gt;</b>'
            );
            $tpl = new Erebot_Styling($message, $translator);
            $tpl->assign('logo', $this->getLogo());
            $tpl->assign('nick', $nick);
            $tpl->assign('cmd', $this->_chans[$chan]['triggers']['choose']);
            $this->sendMessage($chan, $tpl->render());
        }

        else {
            if (substr($played['card'], 0, 1) == 'w') {
                $message = $translator->gettext(
                    '<var name="logo"/> '.
                    'The color is now <var name="color"/>'
                );
                $tpl = new Erebot_Styling($message, $translator);
                $tpl->assign('logo',    $this->getLogo());
                $tpl->assign('color',   $this->getCardText($played['color']));
                $this->sendMessage($chan, $tpl->render());
            }

            if ($game->getPenalty()) {
                $message = $translator->gettext(
                    '<var name="logo"/> '.
                    'Next player must respond correctly or pick '.
                    '<b><var name="count"/></b> cards'
                );
                $tpl = new Erebot_Styling($message, $translator);
                $tpl->assign('logo',    $this->getLogo());
                $tpl->assign('count',   $game->getPenalty());
                $this->sendMessage($chan, $tpl->render());
            }
        }
 
        $this->showTurn($event);

        $cards  =   array_map(array($this, 'getCardText'), $next->getCards());
        sort($cards);

        $message = $translator->gettext(
            'Your cards: <for from="cards" item="card" '.
            'separator=" "><var name="card"/></for>'
        );
        $tpl = new Erebot_Styling($message, $translator);
        $tpl->assign('cards', $cards);
        $this->sendMessage((string) $next->getPlayer(), $tpl->render());

        return $event->preventDefault(TRUE);
    }

    public function handleShowCardsCount(Erebot_Interface_Event_ChanText $event)
    {
        $chan       =   $event->getChan();
        $nick       =   $event->getSource();
        $translator =   $this->getTranslator($chan);

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

        $message = $translator->gettext(
            '<var name="logo"/> Cards: <for from="counts" '.
            'item="count" key="nick"><b><var name="nick"/></b>: '.
            '<var name="count"/></for>'
        );
        $tpl = new Erebot_Styling($message, $translator);
        $tpl->assign('logo',    $this->getLogo());
        $tpl->assign('counts',  $counts);
        $this->sendMessage($chan, $tpl->render());

        if ($ingame !== NULL) {
            $cards = array_map(
                array($this, 'getCardText'),
                $ingame->getCards()
            );
            sort($cards);

            $message = $translator->gettext(
                'Your cards: <for from="cards" item="card" '.
                'separator=" "><var name="card"/></for>'
            );
            $tpl = new Erebot_Styling($message, $translator);
            $tpl->assign('cards', $cards);
            $this->sendMessage($nick, $tpl->render());
        }

        return $event->preventDefault(TRUE);
    }

    public function handleShowDiscard(Erebot_Interface_Event_ChanText $event)
    {
        $chan       =   $event->getChan();
        $translator =   $this->getTranslator($chan);

        if (!isset($this->_chans[$chan]['game'])) return;
        $game       =&  $this->_chans[$chan]['game'];

        $card       =   $game->getLastPlayedCard();
        if ($card === NULL) {
            $message = $translator->gettext('No card has been played yet');
            $this->sendMessage($chan, $message);
            return $event->preventDefault(TRUE);
        }

        $count      = $game->getRemainingCardsCount();
        $discard    = $this->getCardText($card['card']);
        if ($count === NULL)
            $message = $translator->gettext(
                '<var name="logo"/> Current discard: '.
                '<var name="discard"/>'
            );
        else
            $message = $translator->gettext(
                '<var name="logo"/> Current discard: '.
                '<var name="discard"/> (<b><var name="count"/></b>'.
                ' cards left in stock)'
            );

        $tpl = new Erebot_Styling($message, $translator);
        $tpl->assign('logo',    $this->getLogo());
        $tpl->assign('discard', $discard);
        $tpl->assign('count',   $count);
        $this->sendMessage($chan, $tpl->render());

        if ($card['card'][0] == 'w' && !empty($card['color'])) {
            $message = $translator->gettext(
                '<var name="logo"/> The current color is '.
                '<var name="color"/>'
            );
            $tpl = new Erebot_Styling($message, $translator);
            $tpl->assign('logo',    $this->getLogo());
            $tpl->assign('color',   $this->getCardText($card['color']));
            $this->sendMessage($chan, $tpl->render());
        }

        return $event->preventDefault(TRUE);
    }

    public function handleShowOrder(Erebot_Interface_Event_ChanText $event)
    {
        $chan       =   $event->getChan();
        $translator =   $this->getTranslator($chan);

        if (!isset($this->_chans[$chan]['game'])) return;
        $game       =&  $this->_chans[$chan]['game'];
        $players    =&  $game->getPlayers();
        $nicks      =   array();
        foreach ($players as &$player) {
            $nicks[] = (string) $player->getPlayer();
        }
        unset($player);

        $message = $translator->gettext(
            '<var name="logo"/> Playing order: <for '.
            'from="nicks" item="nick"><b><var name="nick"/>'.
            '</b></for>'
        );
        $tpl = new Erebot_Styling($message, $translator);
        $tpl->assign('logo',    $this->getLogo());
        $tpl->assign('nicks',   $nicks);
        $this->sendMessage($chan, $tpl->render());
        return $event->preventDefault(TRUE);
    }

    public function handleShowTime(Erebot_Interface_Event_ChanText $event)
    {
        $chan       =   $event->getChan();
        $current    =   $this->getCurrentPlayer($chan);
        $translator =   $this->getTranslator($chan);

        if ($current === NULL) return;
        $game       =&  $this->_chans[$chan]['game'];

        $message    = $translator->gettext(
            '<var name="logo"/> game running since '.
            '<var name="duration"/>'
        );
        $tpl = new Erebot_Styling($message, $translator);
        $tpl->assign('logo',        $this->getLogo());
        $tpl->assign(
            'duration',
            $translator->formatDuration($game->getElapsedTime())
        );
        $this->sendMessage($chan, $tpl->render());
        return $event->preventDefault(TRUE);
    }

    public function handleShowTurn(Erebot_Interface_Event_ChanText $event)
    {
        $chan       =   $event->getChan();
        $nick       =   $event->getSource();
        $current    =   $this->getCurrentPlayer($chan);
        $translator =   $this->getTranslator($chan);

        if ($current === NULL) return;
        $currentNick = (string) $current->getPlayer();

        if (!strcasecmp($nick, $currentNick))
            $message = $translator->gettext(
                '<var name="logo"/> <b><var name="nick"'.
                '/></b>: it\'s your turn sleepyhead!'
            );
        else
            $message = $translator->gettext(
                '<var name="logo"/> It\'s <b><var name='.
                '"nick"/></b>\'s turn.'
            );

        $tpl = new Erebot_Styling($message, $translator);
        $tpl->assign('logo', $this->getLogo());
        $tpl->assign('nick', $currentNick);
        $this->sendMessage($chan, $tpl->render());
        return $event->preventDefault(TRUE);
    }
}
