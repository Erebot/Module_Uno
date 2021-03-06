<?php

require_once(dirname(__FILE__).'/StrictRulePlayableCardsTest.php');

class UnoChainableRulePlayableCardsTest
extends UnoStrictRulePlayableCardsTest
{
    const RULES = \Erebot\Module\Uno\Game::RULES_CHAINABLE_PENALTIES;

    public function testChainPenalty()
    {
        // r+2, g+2 -> OK
        $this->uno->play('r+2');
        $this->uno->play('g+2');
    }

    public function testChainPenalty2()
    {
        // r+2, w+4g -> OK
        $this->uno->play('r+2');
        $this->uno->play('w+4g');
    }

    public function testChainPenalty4()
    {
        // +4, +4 -> OK
        $this->uno->play('w+4r');
        $this->uno->play('w+4g');
    }
}

