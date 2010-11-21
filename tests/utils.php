<?php

class   UnoStub
extends Erebot_Module_Uno_Game
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
extends Erebot_Module_Uno_Game
{}

class   UnoDeckStub
extends Erebot_Module_Uno_Deck_Official
{
    protected function chooseFirstCard()
    {
        $this->firstCard = NULL;
    }
}

class   UnoHandStub
extends Erebot_Module_Uno_Hand
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

?>
