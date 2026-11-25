# Erasing a member

A ledger only grows, so that the audit adds up; a member who leaves still has the right to be forgotten. The erasure
decides column by column:

| Column | Effect | Why |
|---|---|---|
| `tilttek_luck_tokens_entry.holder` | Pseudonymised | The entries of a table are numbered one after the other, and an operation can join two holders, as when a token is passed. Deleting the member's entries would leave holes in the positions and half-operations in the ledger of the others, whose audit would no longer add up. |
| `tilttek_luck_tokens_entry.actor` | Pseudonymised | An entry the member wrote as a game master or as a player belongs to the ledger of another holder, who keeps its audit. |
| `tilttek_luck_tokens_entry.reason` | Deleted, on the entries the member holds | A reason is written about the holder, and the audit of the balances needs only the changes. The reason of an entry the member wrote for another holder stays: it is about that holder. |
| `tilttek_luck_tokens_rules.game_masters` | Deleted | With `rules: doctrine`, the member is taken off the game masters of every table: a pseudonym there would grant nobody anything, and the rest of each table's rules stays. |

The pseudonym is `erased:` and 32 random hexadecimal digits, drawn for this erasure alone: nothing joins it back to the
account, nor to another erased member. The rows stay personal data all the same — the operation identifiers, the
sessions and the times can be matched with the application's own records — so the report says `pseudonymised`, never
`anonymised`. `LuckTokens::eraseHolder()` leaves the positions, the changes, the balances and the order as they were,
and dispatches `HolderErased` with the pseudonym and the number of entries rewritten. An erasure sent again finds no
entry naming the member and does nothing.

In the database, `DbalTokenLedger` runs it in one transaction: a count of the entries naming the member, an update of
the holder column with the reason, an update of the actor column, each by its index. `DbalTokenRules::removeGameMaster()`
reads the rules of the tables by pages of 500 and rewrites those that name the member.

What stays: the reasons the member wrote for others, as they were typed — keep names out of them, or offer reasons from
a list —, and, with `rules: configuration`, the game masters named in the configuration, which the application changes
itself.

## Through `phil/privacy-bundle`

When the privacy bundle is installed, the bundle registers `LuckTokensPrivacy`, tagged as an eraser of the module
`tilttek_luck_tokens`. An erasure request for the data subject `44` erases the member `member:44`, or of the kind
`privacy.member_kind` names, under the member's lock when the locks are enabled. The report names
`tilttek_luck_tokens_entry.holder` and `tilttek_luck_tokens_entry.actor` as pseudonymised,
`tilttek_luck_tokens_entry.reason` as deleted, and, with `rules: doctrine`, `tilttek_luck_tokens_rules.game_masters` as
deleted.

## From the application's own flow

An application that deletes its members in its own way calls the erasure from a listener of its event:

```php
use Phil\Resource\ResourceReference;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Tilttek\LuckTokens\LuckTokens;

#[AsEventListener]
final readonly class EraseTheTokensOfADeletedMember
{
    public function __construct(private LuckTokens $tokens) {}

    public function __invoke(MemberDeleted $deleted): void
    {
        $this->tokens->eraseHolder(ResourceReference::of('member', (string) $deleted->memberId));
    }
}
```

With the locks enabled, inject `LockedLuckTokens` instead, whose `eraseHolder()` takes the member's lock, so that no
operation of theirs is half-way through when they are erased. Run the erasure once the account is closed: an operation
of the member that starts after it writes their reference again.
