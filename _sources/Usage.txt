Usage
=====

This section assumes default values are used for all triggers.
Please refer to :ref:`configuration options <configuration options>`
for more information on how to customize triggers.


Provided commands
-----------------

This module provides the following commands:

..  table:: Commands provided by |project|

    +---------------------------+-------------------------------------------+
    | Command                   | Description                               |
    +===========================+===========================================+
    | :samp:`!uno {variations}` | Start a new Uno game using the given      |
    |                           | :ref:`variations <Variations>`.           |
    |                           | If no variations are selected, default    |
    |                           | variations (if configured by the bot's    |
    |                           | administrator) or strict rules will       |
    |                           | apply.                                    |
    +---------------------------+-------------------------------------------+
    | ``ca``                    | Display the number of remaining cards in  |
    |                           | each player's hand.                       |
    +---------------------------+-------------------------------------------+
    | ``cd``                    | Display the last played (and thus         |
    |                           | discarded) card.                          |
    +---------------------------+-------------------------------------------+
    | ``ch``                    | Challenge the previous "Wild +4". See the |
    |                           | official rules for more information on    |
    |                           | challenges.                               |
    +---------------------------+-------------------------------------------+
    | :samp:`co {color}`        | Choose the new color to use after a       |
    |                           | "Wild" card was played. The color's name  |
    |                           | must be given using only its first letter |
    |                           | ("b" for "blue, "y" for yellow, "g" for   |
    |                           | green or "r" for red).                    |
    +---------------------------+-------------------------------------------+
    | ``jo``                    | Join a currently started Uno game.        |
    +---------------------------+-------------------------------------------+
    | ``od``                    | Display playing order.                    |
    +---------------------------+-------------------------------------------+
    | ``pa``                    | Pass instead of playing. This command can |
    |                           | only be used after ``pe``. This command   |
    |                           | can also be used to draw penalty cards.   |
    +---------------------------+-------------------------------------------+
    | ``pe``                    | Draw a card instead of playing. Must be   |
    |                           | used prior to using ``pa``.               |
    |                           | If the ``loose_draw``                     |
    |                           | :ref:`variation <variations>` is in use   |
    |                           | and the card you just drew can be played, |
    |                           | you may choose to play it directly        |
    |                           | (without waiting for your next turn)      |
    |                           | using :samp:`pl {card}`. This command can |
    |                           | also be used to draw penalty cards.       |
    +---------------------------+-------------------------------------------+
    | :samp:`pl {card}`         | Play the given *card* (see                |
    |                           | :ref:`mnemonics` for the full syntax).    |
    |                           | If the ``multiple``                       |
    |                           | :ref:`variation <variations>` is enabled, |
    |                           | several (identical) card names may be     |
    |                           | given. Also, as a shortcut when playing   |
    |                           | wild cards, you may pass the new color to |
    |                           | use directly after the card's name.       |
    |                           | Thus, ``pl w+4r`` is identical to         |
    |                           | ``pl w+4`` followed by ``co r``.          |
    +---------------------------+-------------------------------------------+
    | ``ti``                    | Display information on how long the       |
    |                           | current game has been running for.        |
    +---------------------------+-------------------------------------------+
    | ``tu``                    | Display the name of the player whose turn |
    |                           | it is to play.                            |
    +---------------------------+-------------------------------------------+


..  _`mnemonics`:

Mnemonics for cards
-------------------

The general format used to refer to cards is the first letter of the card's
color (in english) followed by the card's figure.

The five following "colors" are available:

-   **r**\ ed
-   **b**\ lue
-   **g**\ reen
-   **y**\ ellow
-   **w**\ ild

A "wild" card is one which can be played over any other color.

The following table lists a few examples of valid mnemnics with the full name
of the card they refer to:

..  table:: Valid mnemonics for cards

    +-----------+-----------------------+
    | Mnemonic  | Actual card           |
    +===========+=======================+
    | ``g0``    | "Green 0"             |
    +-----------+-----------------------+
    | ``b9``    | "Blue 9"              |
    +-----------+-----------------------+
    | ``rr``    | "Red Reverse"         |
    +-----------+-----------------------+
    | ``ys``    | "Yellow Skip"         |
    +-----------+-----------------------+
    | ``g+2``   | "Green +2"            |
    +-----------+-----------------------+
    | ``w``     | "Wild"                |
    +-----------+-----------------------+
    | ``w+4``   | "Wild +4"             |
    +-----------+-----------------------+


..  _`variations`:

Variations
----------

This module features several variations (like chainable/reversible penalties)
and that's why this game is so much fun!

The following table lists possible variations of the rules:

..  table:: Rule variations supported by |project|

    +-------------------+---------------------------------------------------+
    | Rule              | Description                                       |
    +===================+===================================================+
    | ``cancelable`` or | Penalties can be cancelled using a "Skip" card of |
    | ``cancellable``   | the appropriate color. Eg. if someone plays "y+2" |
    |                   | (Yellow +2) and the person after that plays "ys"  |
    |                   | (Yellow Skip), the game shall continue as if the  |
    |                   | Yellow +2 had never been played and no player     |
    |                   | shall draw any cards as a result of it having     |
    |                   | been played.                                      |
    +-------------------+---------------------------------------------------+
    | ``chainable``     | Penalties can be chained together. Eg. if someone |
    |                   | plays "y+2" (Yellow +2) and the next person plays |
    |                   | "w+4" (Wild +4), the player after that must play  |
    |                   | another "w+4" or draw 6 cards (2 for the original |
    |                   | "y+2" and 4 for the additional "w+4").            |
    +-------------------+---------------------------------------------------+
    | ``loose_draw``    | A card may be played right after it was drawn.    |
    |                   | (without waiting for the player's next turn)      |
    +-------------------+---------------------------------------------------+
    | ``multiple``      | Multiple cards with the same name can be played   |
    |                   | together. Eg. you may play two "Yellow 1" at the  |
    |                   | same time using this command: ``pl y1y1``.        |
    +-------------------+---------------------------------------------------+
    | ``reversible``    | Penalties may be reversed using a "Reverse" card  |
    |                   | of the appropriate color. Eg. if someone plays    |
    |                   | "y+2" (Yellow +2) and the person after that plays |
    |                   | "yr" (Yellow Reverse), the person who played the  |
    |                   | original penalty card (Yellow +2) must now draw   |
    |                   | 2 cards instead of the person who used the        |
    |                   | Yellow Reverse.                                   |
    +-------------------+---------------------------------------------------+
    | ``skippable``     | Penalties can be skipped using a "Skip" card of   |
    |                   | the appropriate color. Eg. if someone plays "y+2" |
    |                   | (Yellow +2) and the person after that plays "ys"  |
    |                   | (Yellow Skip), the person who played the          |
    |                   | Yellow Skip won't have to draw the penalty cards. |
    |                   | Instead, the person playing after that will have  |
    |                   | to draw the 2 additional cards.                   |
    +-------------------+---------------------------------------------------+
    | ``unlimited``     | The game is played with an unlimited number of    |
    |                   | cards. This makes it impossible to predict the    |
    |                   | other players' move based on what cards have been |
    |                   | played before, as new cards are dealt randomly    |
    |                   | each time from the set of all valid cards instead |
    |                   | of just the set of remaining cards in the deck.   |
    +-------------------+---------------------------------------------------+

..  note::
    Those variations can be mixed together (with the exception of the
    ``skippable`` and ``cancelable`` variations) to build even more complex
    (and fun) games.


..  vim: ts=4 et
