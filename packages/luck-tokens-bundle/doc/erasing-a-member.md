# Erasing a member

A ledger only grows, so that the audit adds up; a member who leaves still has the right to be forgotten. The library
reconciles both by pseudonymisation: `LuckTokens::eraseHolder()` writes a pseudonym in place of the member, as holder
and as actor, in every entry of every table, and leaves the positions, the changes, the balances and the order as they
were. The pseudonym is `erased:` and 32 random hexadecimal digits, drawn for this erasure alone: nothing joins it back
to the account, nor to another erased member. `HolderErased` is dispatched with the pseudonym and the number of entries
rewritten. An erasure sent again finds no entry naming the member and does nothing.

In the database, `DbalTokenLedger` runs it in one transaction: a count of the entries naming the member, an update of
the holder column, an update of the actor column, each by its index.

What stays: the reasons, as they were typed — keep names out of them, or offer reasons from a list —, and the game
masters named in the rules of a table, which the application changes with the rest of the table's settings.

## Through `phil/privacy-bundle`

When the privacy bundle is installed, the bundle registers `LuckTokensPrivacy`, tagged as an eraser of the module
`tilttek_luck_tokens`. An erasure request for the data subject `44` erases the member `member:44`, or of the kind
`privacy.member_kind` names, under the member's lock when the locks are enabled. The report names
`tilttek_luck_tokens_entry.holder` and `tilttek_luck_tokens_entry.actor` as pseudonymised.

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
