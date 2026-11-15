# tilttek/luck-tokens

**Luck, inspiration or fate tokens a table hands out and spends, every move in an append-only ledger.**

A game master hands out a token for a clever plan; a player spends it to reroll; another passes a benny to a friend in
trouble; at the start of the next session, the fate points come back up to their refresh. Each table says which
tokens it hands out — their name, how many a holder keeps at most, whether they carry over from one session to the
next and how they refresh, who may grant them and whether they pass from one holder to another — and the library keeps
every move in the table's ledger, which only grows, so that the game master and the players audit every token.

A balance never goes under zero nor over its cap. Every operation carries an identifier chosen by the caller: sent
again — a double click, a retried request — it writes nothing more and gives back what it wrote. Two operations at
once on the same table do not both write after the same entry: the second reads the ledger again and is checked
again. Once written, a grant, a spending, a transfer or the start of a session is dispatched as an event, so that a
dice roller offers its reroll or a dice tower shows the token spent. Every time is written in UTC. A member who leaves
is erased from every ledger under a pseudonym, the balances of the others unchanged.

It is a plain PHP library. The application keeps the rules and the ledger in its own storage, through two ports, and
the library ships a memory store of each. The tokens are not points: they are neither earned by activity nor
exchanged.

## Status

| Part | State |
|---|---|
| `LuckTokens`: grants, spendings, transfers and the start of a session, idempotent, checked against the rules | Implemented and tested |
| `TokenKind`, `TableRules`: the kinds of a table, their cap, carry-over, refresh, grants and transfers; read from JSON | Implemented and tested |
| `Ledger\LedgerEntry`, `Ledger\EntryType`, `Receipt`, `Balance`: the append-only ledger and what an operation wrote | Implemented and tested |
| `Event\TokensGranted`, `TokensSpent`, `TokensTransferred`, `SessionStarted`, dispatched through PSR-14 | Implemented and tested |
| `eraseHolder()`: a member erased under a pseudonym, and `Event\HolderErased` | Implemented and tested |
| `Contract\TokenRules`, `Contract\TokenLedger`, `Contract\HolderAccess`, with memory stores | Implemented and tested |
| Exceptions under one `LuckTokensException` interface | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing and wiring. |
| [Usage](doc/usage.md) | The kinds of tokens, granting, spending, passing, sessions, reading the ledger, and erasing a member. |
| [Accessibility](doc/accessibility.md) | Showing balances and announcing what changes. |
| [Extension points](doc/extension-points.md) | The ports, writing a ledger, and the events. |
| [Testing](doc/testing.md) | The library's tests, fuzz target and benchmarks. |
| [Reference](doc/reference.md) | Every type of the library, with its role, and its exceptions. |

## Quick start

```php
use Phil\Resource\ResourceReference;
use Tilttek\LuckTokens\LuckTokens;
use Tilttek\LuckTokens\Store\InMemoryTokenLedger;
use Tilttek\LuckTokens\Store\InMemoryTokenRules;
use Tilttek\LuckTokens\TableRules;

$table = ResourceReference::of('table', '12');
$gm = ResourceReference::of('member', '7');
$ana = ResourceReference::of('member', '8');

$rules = new InMemoryTokenRules();
$rules->save(TableRules::fromJson($table, '{"kinds": [{"key": "inspiration", "name": "Inspiration", "cap": 1}],
    "game_masters": ["member:7"]}'));
$tokens = new LuckTokens($rules, new InMemoryTokenLedger(), $eventDispatcher);

$tokens->grant($table, 'inspiration', $ana, 1, $gm, 'op-01J2', 'A clever plan');
$tokens->spend($table, 'inspiration', $ana, 1, $ana, 'op-01J3', 'Reroll the attack');
$tokens->log($table);   // both entries, for the audit
```

## Requirements

PHP 8.5 or later, `phil/resource` 0.1, `psr/clock`, `psr/event-dispatcher` and `symfony/clock`.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. See [LICENSE](LICENSE).
