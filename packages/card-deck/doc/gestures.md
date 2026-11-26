# Gestures

Every change to a game is a gesture: a value object under `Tilttek\CardDeck\Gesture`, applied by
`DeckState::apply()`, recorded in the journal of the game by `CardTable::play()`. `apply()` returns a new state and
leaves the one it was given as it was; a gesture it refuses throws `IllegalGesture` and changes nothing — a deal that
lacks cards deals none.

| Gesture | What it does | Refused when |
|---|---|---|
| `Shuffle(?ZoneId $zone)` | Shuffles one zone in place, the draw pile by default, face down, from the next number of the seed | The zone is not open |
| `Draw(PlayerId $player, Pick $from, ?ZoneId $zone, bool $faceUp)` | One card from the top, the bottom or at random of a zone, the draw pile by default, to the end of the player's hand | The zone is not open, or empty |
| `Deal(list<PlayerId> $players, int $count, bool $faceUp, ?ZoneId $from)` | Cards from the top of a zone, one at a time around the players in the order given, until each holds `count` more | The zone holds fewer than `count` × players cards |
| `Discard(CardId $card)` | The card on top of the discard pile, face up | The deck holds no such card |
| `Flip(CardId $card)` | The card turned over where it lies | The card lies in the draw pile |
| `TurnFace(CardId $card, int $face)` | Another face of the card shown, numbered from 0; face up or down as it was | The card has no such face |
| `Move(CardId $card, ZoneId $to, End $at, bool $faceUp)` | The card to the top or the bottom of a zone | The deck holds no such card, or the game holds 256 zones |
| `Collect(ZoneId $from, ZoneId $to, End $at, bool $faceUp)` | Every card of a zone, in their order, to the top or the bottom of another | The first zone is not open |

A card that lands in the draw pile always lies face down, whatever the gesture asks. A deal goes to 1 to 64 players,
each once, and gives 1 to 1 024 cards to each; a gesture out of those bounds is refused when it is made, with
`InvalidDefinition`.

The library checks what the state of the game allows; whose turn it is, and which player may move which card, are
rules of the game the application checks before it plays the gesture.

## Shuffling is not starting a game

A game starts once, with `DeckState::start()` — or `CardTable::start()`: every card face down in the draw pile in the
order of the deck, then shuffled from the number 0 of the seed. A `Shuffle` during the game shuffles one zone and
leaves the others: to shuffle the discard pile back into the draw pile, collect it, then shuffle.

```php
$state = $state
    ->apply(new Collect(ZoneId::discard(), ZoneId::drawPile(), End::Bottom))
    ->apply(new Shuffle());
```

A new game is a new `GameRecord`, with a new seed and a new commitment.

## Writing and reading gestures

`Gesture::toArray()` writes a gesture as plain data, the way a store keeps it; `GestureCodec::fromArray()` reads it
back, checking every field and refusing anything else with `InvalidDefinition`:

```php
['gesture' => 'draw', 'player' => 'u-101', 'from' => 'random', 'zone' => 'draw', 'face_up' => false]
```

## Replaying a journal

`DeckState::replay($deck, $seed, $clientSeed, $gestures)` starts the game and applies the journal gesture by gesture:
the same deck, seed, client seed and journal always give the same state. `DeckState::fingerprint()` is the SHA-256 of
the places, sides and faces of every card and of the next number of the seed: two states with the same fingerprint
are the same game. It holds neither the seed nor the client seed.
