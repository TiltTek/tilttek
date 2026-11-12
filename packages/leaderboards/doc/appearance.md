# Appearing on a board

**Nobody is ranked publicly without choosing it.** A ranking shows something of a member to the others; a member who
has not chosen to be shown is not.

## The choice

| Visibility | What the others see on the member's line |
|---|---|
| `Hidden` — every member's until they choose | The rank and the score, without a name |
| `Named` | The member's reference, for the application to name |
| `Pseudonym` | The pseudonym the member chose: 1 to 40 characters, no control character, no space at either end |

```php
$leaderboards->appear($member, Appearance::named());
$leaderboards->appear($member, Appearance::pseudonym('Le Nain Gris'));
$leaderboards->appear($member, Appearance::hidden());
$leaderboards->appearance($member); // the choice kept, hidden by default
```

The choice holds on every board. Choosing to be hidden forgets the choice kept.

## What each viewer sees

A hidden member still holds their rank — the others' ranks do not change because someone chose not to appear —, and
sees their own line with their reference and `self` set. The others see a `Row` whose member and pseudonym are both
null; `Row::isHidden()` says so, and a template writes "A member" there. A pseudonymous line never carries the member's
reference, so that a page cannot link the pseudonym to an account.

A snapshot is shown by each member's choice at the moment it is shown, not when it was taken: a member who hides
afterwards disappears from the final standings too.

## The application's part

The application asks the member, in their profile, how they want to appear, and calls `appear()`; it gives the names of
the named members when it renders a standing. A member's export holds their choice ([Erasure](erasure.md)).
