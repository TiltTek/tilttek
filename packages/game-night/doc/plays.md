# Plays

`NightPlays::record()` sends a game played at a table of the night to the application's play log, through
`Contract\PlayJournal`. It keeps nothing itself.

A game is recorded once the night has started, unless it is cancelled. It is played at a table of the night, by
confirmed members, at least one and each once, and by guests those members brought; the game is in the catalogue.
The `PlayedGame` the play log receives holds the night, the table, the game, the members by their identifiers, the
guests as a number, the moment it was recorded and, when known, the minutes it lasted.
