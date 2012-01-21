Configuration
=============

..  _`configuration options`:

Options
-------

This module provides several configuration options.

..  table:: Options for |project|

    +---------------+-----------+-----------+-------------------------------+
    | Name          | Type      | Default   | Description                   |
    |               |           | value     |                               |
    +===============+===========+===========+===============================+
    | |trigger_uno| | string    | "uno"     | The command to use to start   |
    |               |           |           | a new Uno game.               |
    +---------------+-----------+-----------+-------------------------------+
    | |trigger_ch|  | string    | "ch"      | The command to use to         |
    |               |           |           | challenge a Wild +4.          |
    +---------------+-----------+-----------+-------------------------------+
    | |trigger_co|  | string    | "co"      | The command to use to choose  |
    |               |           |           | a color after playing a Wild  |
    |               |           |           | or Wild +4.                   |
    +---------------+-----------+-----------+-------------------------------+
    | |trigger_pe|  | string    | "pe"      | The command to use to draw a  |
    |               |           |           | card.                         |
    +---------------+-----------+-----------+-------------------------------+
    | |trigger_jo|  | string    | "jo"      | The command to use to join a  |
    |               |           |           | game after it has been        |
    |               |           |           | created.                      |
    +---------------+-----------+-----------+-------------------------------+
    | |trigger_pa|  | string    | "pa"      | The command to use to pass    |
    |               |           |           | after drawing a card.         |
    +---------------+-----------+-----------+-------------------------------+
    | |trigger_pl|  | string    | "pl"      | The command to use to play a  |
    |               |           |           | card or combination of cards. |
    +---------------+-----------+-----------+-------------------------------+
    | |trigger_ca|  | string    | "ca"      | The command to use to show    |
    |               |           |           | how many cards each player    |
    |               |           |           | has in his hand.              |
    +---------------+-----------+-----------+-------------------------------+
    | |trigger_cd|  | string    | "cd"      | The command to use to show    |
    |               |           |           | the last discarded card.      |
    +---------------+-----------+-----------+-------------------------------+
    | |trigger_od|  | string    | "od"      | The command to use to show    |
    |               |           |           | playing order.                |
    +---------------+-----------+-----------+-------------------------------+
    | |trigger_ti|  | string    | "ti"      | The command to use to show    |
    |               |           |           | for how long a game has been  |
    |               |           |           | running.                      |
    +---------------+-----------+-----------+-------------------------------+
    | |trigger_tu|  | string    | "tu"      | The command to use to show    |
    |               |           |           | whose player's turn it is.    |
    +---------------+-----------+-----------+-------------------------------+
    | start_delay   | integer   | 20        | How many seconds does the bot |
    |               |           |           | wait after enough players     |
    |               |           |           | have joined the game before   |
    |               |           |           | the game actually starts.     |
    +---------------+-----------+-----------+-------------------------------+

..  warning::
    All triggers should be written without any prefixes. Moreover, triggers
    should only contain alphanumeric characters.


Example
-------

Here, we enable the Uno module at the general configuration level.
Therefore, the game will be available on all networks/servers/channels.
Of course, you can use a more restrictive configuration file if it suits your
needs better.

..  parsed-code:: xml

    <?xml version="1.0"?>
    <configuration
      xmlns="http://localhost/Erebot/"
      version="0.20"
      language="fr-FR"
      timezone="Europe/Paris">

      <modules>
        <!-- Other modules ignored for clarity. -->

        <!--
          Make the Uno game available on all networks/servers/channels,
          using the default values for every setting.
        -->
        <module name="|project|" />
      </modules>
    </configuration>

..  |trigger_uno|   replace:: trigger_create
..  |trigger_ch|    replace:: trigger_challenge
..  |trigger_co|    replace:: trigger_choose
..  |trigger_pe|    replace:: trigger_draw
..  |trigger_jo|    replace:: trigger_join
..  |trigger_pa|    replace:: trigger_pass
..  |trigger_pl|    replace:: trigger_play
..  |trigger_ca|    replace:: trigger_show_cards
..  |trigger_cd|    replace:: trigger_show_discard
..  |trigger_od|    replace:: trigger_show_order
..  |trigger_ti|    replace:: trigger_show_time
..  |trigger_tu|    replace:: trigger_show_turn


.. vim: ts=4 et
