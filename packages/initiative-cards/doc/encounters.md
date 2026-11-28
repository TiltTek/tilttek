# Encounters, rounds and turns

## An encounter

`InitiativeTracker::start()` opens an encounter: its identifier, its game master, a deck of `tilttek/card-deck` and the
combatants, up to 100, in the order they join. The deck is shuffled into the draw pile; no card is dealt yet. Every face
of the deck must be one the ranking knows, or the encounter is refused.

A `Combatant` has an identifier, unique in the encounter, and a controller: the member who ends its turns, a player for
their character, the game master for the others. It may be hidden from the players, and draw one to three cards in each
round. Identifiers are 1 to 128 letters, digits, `_`, `.`, `:` or `-`, starting with a letter, never a name or an
address.

The game master — and only them — deals, adds, removes, defeats, revives, hides, reveals and ends. A player ends the turns
of the combatants they control.

## Dealing a round

`deal()` ends the round in progress and deals the next one:

1. the cards the combatants hold go to the discard pile;
2. the discard pile is shuffled back into the draw pile when a card that reshuffles was dealt in the round just over, or
   when the draw pile holds fewer cards than the combatants draw;
3. every combatant not defeated draws its cards from the top of the pile and keeps the best; the others are discarded;
4. the combatants are ordered by their cards. Those who tie keep their order of the previous round, or of their joining;
   the defeated, who hold no card, come last.

When even the whole deck is too small for the combatants' draws, the deal is refused and nothing changes. The round
counts from 1; `RoundDealt` is told, then `TurnStarted` for the first combatant.

## The ranking

`StandardRanking` orders the faces of `StandardDeck`: a joker (`JK`) first, then the ace (`A`), king, queen, jack, ten
down to two; two cards of one rank follow the order of the suits, spades (`S`), hearts (`H`), diamonds (`D`), then clubs
(`C`) by default. Another order is given as a list of the four suits:

```php
new StandardRanking(['C', 'D', 'H', 'S']);   // clubs first
new StandardRanking(jokerShuffle: false);     // a joker acts first, and shuffles nothing
```

A deck of the application's own — cards of a game's art, a tarot — comes with a `Contract\CardRanking`, described in the
[extension points](extension-points.md#the-ranking). The ranking reads the first face of each card.

## Turns

The combatant holding the turn, `Encounter::current()`, is the first one, in order, who holds a card, has not acted and is
not defeated. `endTurn()` ends it, by its controller or the game master; the turn passes to the next card. Ending the turn
of a combatant who does not hold it is refused, so that two clicks never end two turns.

The one ending the turn may name the next one, among the combatants who have not acted and are not defeated: the
combatant named moves right after the one ending, and the order of the others stays. When every combatant standing has
acted, `RoundEnded` is told, and the game master deals the next round.

## Joining and leaving

`join()` adds a combatant. Before the first round, it comes last. In a round, it draws its cards at once — the discard
pile is shuffled back when the draw pile is too short — and waits among those who have not acted, after the one holding
the turn, in the order of the cards. A combatant joining once the round is over takes the turn. A combatant joining
defeated holds no card until the next deal.

`leave()` removes a combatant; its card goes to the discard pile, and when it held the turn, the turn passes on.

## Defeat and revival

`defeat()` marks a combatant defeated: it stays in the order, faded, keeps its card for the round, and its turns are
skipped; in the next rounds it draws nothing. When it held the turn, the turn passes on.

`revive()` stands it again. Its card, when it holds one, keeps its place: when a combatant after it has acted or holds the
turn, the turn has passed its place, and it waits for the next round; otherwise its turn comes in this one.

## Hidden combatants and views

`reveal()` shows a hidden combatant to the players, and `reveal(..., false)` hides one. `view()` gives what one member may
see, as an `InitiativeView` whose `toArray()` is sent to them:

- the round, the cards left in the draw pile, whether the encounter has ended, and the version read;
- a `Slot` per combatant, in turn order: its identifier, the face of its card or null, whether it has acted, is defeated
  and holds the turn.

The game master's view holds every combatant, each marked hidden or not; anyone else's, or a spectator's, leaves the
hidden ones out. The `TurnStarted` and `CombatantJoined` events say when the combatant is hidden, so that an application
does not tell the players.

`end()` closes the encounter: it changes no more, and has no current turn.
