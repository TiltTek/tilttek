# tilttek/math-trade

**A math trade of games: everyone lists what they offer and what they would take for it, and the most trades
possible are found, as loops in which each gives one game and receives another.**

In a math trade, nobody swaps with one partner. Each participant offers games and, for each, lists the offers they
would accept in exchange, most wished first. The library finds the set of trades that moves the most games, and among
those, the one that best honours the priorities. The trades form loops: Alice sends her game to Carol, Carol hers to
Bob, Bob his to Alice. Each participant then reads their part as "you send this to X, you receive that from Y".

A wish may be a group — "any of these three" — at one priority. A participant who offers several games and wishes
for a game of which several copies are offered receives it once at most. The solver is deterministic: a published
seed breaks ties, so anyone can solve the same lists with the same seed and find the same trades. Lists are written
and read as JSON, on a documented format, and every list is checked against bounds before it is solved.

The library keeps identifiers only: no name and no postal address. The application shows addresses once the trade is
confirmed. It is a plain PHP library with no dependency.

## Status

| Part | State |
|---|---|
| `Participant`, `Offer`, `Want`, `WantGroup`: the immutable trade list | Implemented and tested |
| `TradeList`, `Limits`: the list checked as a whole, and its bounds | Implemented and tested |
| `TradeSolver`: the most trades, the best priorities, a game received once, ties broken by the seed | Implemented and tested |
| `TradeResult`, `TradeCycle`, `Trade`, `Swap`: the loops, and what each participant sends and receives | Implemented and tested |
| `TradeListJson`: the lists written and read as JSON | Implemented, tested and fuzzed |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements and installing. |
| [Running a math trade](doc/math-trade.md) | Lists, wishes, groups, copies, the seed and the result, from end to end. |
| [The JSON format](doc/json-format.md) | The document the lists are written and read as. |
| [Extension points](doc/extension-points.md) | The limits, the identifiers, and what stays in the application. |
| [Exceptions](doc/exceptions.md) | `MathTradeException` and its exceptions, with their messages. |
| [Testing](doc/testing.md) | The library's tests, its benchmark and its fuzz target, and the solver's complexity. |
| [Reference](doc/reference.md) | Every public type, with its role. |

## Quick start

```sh
composer require tilttek/math-trade
```

```php
use Tilttek\MathTrade\Offer;
use Tilttek\MathTrade\Participant;
use Tilttek\MathTrade\TradeList;
use Tilttek\MathTrade\TradeSolver;
use Tilttek\MathTrade\Want;
use Tilttek\MathTrade\WantGroup;

$list = new TradeList([
    new Participant('alice', [new Offer('azul-1', [new Want('catan-1'), new WantGroup(['wingspan-1', 'carcassonne-1'])])]),
    new Participant('bob', [new Offer('catan-1', [new Want('wingspan-1')])]),
    new Participant('carol', [new Offer('wingspan-1', [new Want('azul-1')])]),
    new Participant('dave', [new Offer('carcassonne-1')]),
]);

$result = new TradeSolver()->solve($list, 'spring-trade-2026');

foreach ($result->forParticipant('alice') as $swap) {
    echo "Send {$swap->sent} to {$swap->sendTo}, receive {$swap->received} from {$swap->receiveFrom}\n";
}
// Send azul-1 to carol, receive catan-1 from bob
```

## Requirements

PHP 8.5 or later.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. See [LICENSE](LICENSE).
