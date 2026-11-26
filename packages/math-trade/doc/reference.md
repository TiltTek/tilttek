# Reference

Every public type of the package, with its role.

| Type | Role |
|---|---|
| `TradeList` | The whole trade: participants, offers and wishes, checked against each other and against the limits |
| `Participant` | Someone who takes part, and the offers they make |
| `Offer` | A copy of a game put in the trade, its game, and its wishes, most wished first |
| `Contract\Wish` | One line of a wish list: the offers it names |
| `Want` | A wish for one offer |
| `WantGroup` | A wish for any one of several offers, at one priority |
| `Limits` | How large a list and its JSON document may be |
| `TradeSolver` | Finds the most trades, at the best priorities, with ties broken by the seed |
| `TradeResult` | The trades found, the seed, and the offers kept |
| `TradeCycle` | A loop of trades |
| `Trade` | One offer changing hands: offer, giver, receiver |
| `Swap` | What a traded offer means for its owner: what they send to whom, and what they receive from whom |
| `TradeListJson` | Writes a trade list as JSON, and reads it back |
| `Exception\MathTradeException` and its three exceptions | [Exceptions](exceptions.md) |

The `Internal` namespace is the library's own: its classes may change in any version.
