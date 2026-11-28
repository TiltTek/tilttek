# Writing a tavern game

A tavern game implements `Contract\TavernGame`, which extends the `Contract\Game` of `tilttek/game-engine`: everything
the engine's guide to writing a game says holds — a definition, a setup, moves, an outcome, a view, every method pure.
A tavern game adds `toAct()` and accepts the timeout of a turn.

## Turns

The game's phase has no turns of the engine — `TurnOrder::Free`, the default —, and its data holds `Turns`:

```php
public function setup(GameState $state, Randomness $random): array
{
    return ['turns' => Turns::start($state->seats)->toData(), /* … */];
}

public function allows(GameState $state, Move $move): bool
{
    $turns = Turns::fromData($state->data['turns'] ?? null);

    return match ($move->type) {
        'my-game.play' => $turns->isTurnOf($move->seat),
        self::TIMEOUT => null === $move->seat && $turns->isTurnOf($move->text('seat')),
        default => false,
    };
}
```

`play()` returns `Turns::next()` in the data when the turn ends. `toAct()` returns the seat playing, or null once the
game is over.

## The timeout

`TavernGame::TIMEOUT`, `tavern.timeout` with `{seat}`, is made by the table itself — no seat — for the seat playing.
The game ends that seat's turn by its rules, and takes away no more than the turn: no points lost beyond the turn, no
seat removed from the table.

## Dice

`DiceCup::roll($count, $random)` rolls 1 to 10 six-sided dice through `tilttek/dice`, with the `Randomness` of the
move, so that replaying the log rolls the same dice and the engine records that the move drew at random.

## Cards

A card game keeps its cards with `tilttek/card-deck`, without changing it. Crazy Eights shows the way: its setup draws
a seed of 32 bytes from the table's chance, and its data holds that seed and the gestures of the cards, written by
their `toArray()`; each move replays them into a `DeckState`, appends its own gestures, and its view builds each seat's
hand from `DeckState::seenBy()`, which never shows another hand. Since the deck's seed comes from the table's, the
commitment to the table's seed covers the shuffles too.

## Ties

An outcome may name several winners — equal cargoes, equal hands —, and its reason says so: `tie`, `blocked`. An
estimate of the odds counts ties apart ([Odds](odds.md)).

## The rules

The games of the library are traditional games, free of rights, and their rules are written for it. A game of a
publisher is published only with the publisher's consent on record.
