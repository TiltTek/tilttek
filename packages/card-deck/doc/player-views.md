# Player views

A browser that holds the whole deck lets anyone read the order of the draw pile, whatever the page shows. The library
never gives the browser the game: `CardTable::view()` — or `DeckState::seenBy()` — builds what one player may see, on
the server, and that view is the only thing to send.

## What a player sees

| Card | Seen as |
|---|---|
| In the draw pile | Nothing: the zone shows its number of cards only |
| Face up, outside the draw pile | Its identifier and the face it shows, by everyone |
| Face down, in the viewer's own hand | Its identifier and its face, by the viewer alone |
| Any other card | Its back alone: no identifier, no face |

A spectator — a view with no player — sees the cards face up and the backs of the others.

```php
$view = $table->view($game, $alice);

echo json_encode($view->toArray());
```

```json
{"viewer": "u-101", "commitment": "59a3…", "version": 3, "finished": false, "zones": [
  {"zone": "draw", "count": 38, "cards": []},
  {"zone": "hand:u-101", "count": 7, "cards": [{"card": "card-12", "face": "QS", "back": "back", "face_up": false}, …]},
  {"zone": "hand:u-102", "count": 7, "cards": [{"card": null, "face": null, "back": "back", "face_up": false}, …]}
]}
```

## What a view never holds

- The order of the draw pile, or any card in it.
- The identifier or the face of a hidden card: an identifier a browser could follow would say which hidden card moved
  where.
- The seed, the client seed or the journal of gestures, which would give the order of every shuffle.

The tests check it on random games: for every player and the spectator, after every gesture, no hidden card appears in
the view.

## What the application keeps in mind

- A back is visible information: a deck whose backs differ — two decks shuffled together — shows the difference, as a
  real table does. Give every card of one deck the same back.
- A gesture names a card by its identifier: a player names the cards they see. To take a hidden card, a gesture names
  its zone — `Draw` from another player's hand at random, say.
- Push a new view to each player after each gesture — each their own, never one view for all.
