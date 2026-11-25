# Usage

## The kinds of tokens

A table's `TableRules` list the kinds of tokens it hands out, 16 at most, and its game masters, 16 at most. A
`TokenKind` has:

| Field | Meaning | Default |
|---|---|---|
| `key`, `name` | The key, 1 to 64 characters among `a-z`, `0-9` and inner `-`, and the name shown | — |
| `cap` | How many a holder keeps at most, 1 to 1 000 | — |
| `scope` | `character` or `player`: whose tokens they are, and so what the cap counts | `character` |
| `refresh`, `refresh_to` | `never`, or `each-session` up to `refresh_to`, 1 to the cap | `never`, 0 |
| `carry_over` | How many a holder keeps from one session to the next, 0 to the cap, or null for all | null |
| `grants` | `game-masters` or `anyone` | `game-masters` |
| `transferable` | Whether they pass from one holder to another | false |

Some tables, written as `TableRules::fromJson()` reads them:

```json
{
    "kinds": [
        {"key": "inspiration", "name": "Inspiration", "cap": 1, "transferable": true},
        {"key": "bennies", "name": "Bennies", "cap": 5, "scope": "player", "refresh": "each-session",
         "refresh_to": 3, "carry_over": 0, "grants": "anyone", "transferable": true},
        {"key": "fate", "name": "Fate points", "cap": 10, "refresh": "each-session", "refresh_to": 3}
    ],
    "game_masters": ["member:7"]
}
```

Inspiration is kept and never comes back on its own; three bennies come back each session and none is kept; fate
points come back up to three, and those above are kept. `fromJson()` refuses, with `InvalidTokenRule`, a field it does
not know, a value of the wrong type, two kinds of the same key, a refresh amount on a kind never refreshed, and a text
over 64 KiB or deeper than 6 levels.

## Granting, spending, passing

```php
$tokens->grant($table, 'fate', $ana, 2, $gm, 'op-1', 'A clever plan');
$tokens->spend($table, 'fate', $ana, 1, $ana, 'op-2', 'Reroll');
$tokens->transfer($table, 'bennies', $ana, $bo, 1, $ana, 'op-3');
```

| Operation | Who may | Refused when |
|---|---|---|
| `grant()` | A game master; anyone for a kind granted by `anyone` | The holder would go over the cap |
| `spend()` | A game master, or an actor who controls the holder | The holder has fewer tokens |
| `transfer()` | A game master, or an actor who controls the giver | The kind is not transferable, the giver has fewer, the receiver would go over the cap, or both are the same |

An amount is 1 to the cap of its kind. A reason, if given, is 1 to 280 characters without control characters. Each
operation returns a `Receipt`: the entries it wrote, the balance it left its first holder, and `replayed` when it had
been done already.

## The identifier of an operation

The caller names each operation, 1 to 64 characters among letters, digits, `.`, `_`, `:` and `-` — a ULID made when
the form is drawn, for instance. The same identifier with the same values gives back what the operation wrote, writes
nothing more and dispatches nothing; with other values it is refused with `TokenRefused`. A spending sent again after
the tokens ran out is still a replay, not a refusal.

## Sessions

```php
$tokens->startSession($table, 'session-14', [$newPlayer], $gm, 'op-9');
```

A game master starts a session once. For each kind and each holder — those the ledger names, and those given, who
join the table —, the holder keeps what they hold, or at most the carry-over, then a kind refreshed each session brings
them up to its refresh amount. A holder whose tokens do not change gets no entry; the start itself is always written.
The same session started by another operation is refused.

## Reading the ledger

```php
$tokens->balance($table, 'fate', $ana);    // 1
$tokens->balances($table);                 // every holder and kind the ledger names, zero included
$tokens->log($table);                      // every entry, by position
$tokens->log($table, $ana);                // the entries of one holder
```

A `LedgerEntry` gives its position, the operation that wrote it, its `EntryType`, the kind and the holder, the change
and the balance it left, the actor, the time in UTC, the reason and the session. `toArray()` writes it for the audit
page or the storage, and `fromArray()` reads it back.

## Erasing a member

```php
$erased = $tokens->eraseHolder(ResourceReference::of('member', '8'));   // HolderErased, or null
```

When a member leaves, their reference gives way to a pseudonym in every entry of every ledger, as holder and as actor:
`erased:` and 32 random hexadecimal digits, drawn for this erasure alone, so that two erased members are never told
apart from one another nor joined back to their account. The positions, the changes, the balances and the order stay:
the audit of the other holders still adds up, and the tokens the member held stay with the pseudonym. It is the one
rewriting of a ledger that otherwise only grows.

An erasure sent again finds no entry naming the member, writes nothing, dispatches nothing and returns null. Otherwise
`HolderErased` is dispatched, with the pseudonym and the number of entries rewritten, and returned; it names neither the
member nor a table. An operation of the member sent again after the erasure no longer matches what was written, and is
refused as an identifier reused.

The reason of an entry the member holds is cleared: it was written about them, and the audit of the balances needs only
the changes. The reason of an entry the member wrote for another holder stays, since it is about that holder: an
application that lets players type reasons keeps names out of them, or offers reasons from a list. The game masters
named in the rules of a table are the application's to change.

## Concurrent operations

The library reads the last position of the ledger, checks the operation against the balances, and appends its entries
right after that position. A store that finds another entry there refuses the append with `LedgerConflict`; the
library then reads again — the operation done meanwhile under the same identifier is a replay — and checks again,
three times in all, before the conflict reaches the caller, who may retry later. A ledger takes 100 000 entries at most:
an operation that would go past them is refused at the first entry beyond, before it builds the others — a session
started for many holders included.
