# Crazy Eights

`Game\CrazyEights`, named `crazy-eights`, for 2 to 6 seats, with the 52 cards of the French-suited deck.

## The rules

Each seat is dealt a hand and one card is turned up on the discard pile. In their turn, a seat plays a card of the
same rank or suit as the top of the discard pile, or an eight, which is wild and names the suit to follow; a seat that
cannot or will not play draws one card, then plays or passes. When the draw pile is empty, the discard pile under its
top card is shuffled back into it. An eight turned up at the start names its own suit.

| Part | What it is |
|---|---|
| Option | `hand`, the cards dealt to each seat: 5, from 3 to 8 |
| Moves | `crazy-eights.play` (`{card, suit}`, the suit `S`, `H`, `D` or `C`, for an eight only), `crazy-eights.draw`, `crazy-eights.pass`; the timeout of a turn draws a card when none was drawn and one is left, then passes |
| Refusals | A card not in the seat's hand, a card that follows neither the suit nor the rank, an eight without a suit, a second draw in a turn, a pass before drawing while a card is left to draw, a draw with nothing left |

## The endings

| Reason | When | Winners |
|---|---|---|
| `emptied-hand` | A seat plays its last card | That seat |
| `blocked` | Nothing is left to draw, and every seat passes in a row | The fewest cards, equal hands sharing the win |
| `too-long` | The cards have seen `CrazyEights::MAX_GESTURES` gestures, 1 000 | The fewest cards, equal hands sharing the win |

The outcome reports the cards left in each hand as `cards`.

## The cards

The cards are kept by `tilttek/card-deck`. The setup draws a seed of 32 bytes from the table's chance and deals from a
deck shuffled with it; the data of the table holds that seed and the gestures of the cards. Every move and every view
replays the gestures into a `DeckState`: a game stays under 1 000 gestures, which keeps the replay bounded
([Testing](testing.md#benchmarks)). Seat `n` of the table, from 0, is the player `p(n+1)` of `tilttek/card-deck`.

## What each seat sees

| Field | What it holds |
|---|---|
| `hand` | The seat's own cards, each `{card, face}`; none for the audience |
| `top` | The face of the top of the discard pile, `9C` |
| `suit` | The suit to follow: the one an eight named, else the top card's |
| `draw` | The cards left in the draw pile |
| `hands` | How many cards each seat holds |
| `turn` | The seat playing, null once the game is over |
| `drawn` | Whether the seat playing has drawn this turn |

A seat never sees another seat's cards, the order of the draw pile, the seed of the cards or their gestures.
