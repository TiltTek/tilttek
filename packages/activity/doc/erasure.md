# Erasure

```php
$erasure = $journal->erase(ResourceReference::of('member', '44'));
$erasure->deleted;   // entries of the member's own gestures, recorded or cancelled
$erasure->detached;  // entries of other members' gestures whose object was this member
```

Erasing a member deletes every entry of their gestures, and forgets them as the **object** of other members'
gestures — a thank-you given to them, a duel fought against them —: those entries stay, with no object, since they are
the other members' activity and their rewards come from it.

What a package derived from the member's activity — points, badges, progress — is erased by that package; the
journal only answers for itself. Positions are never given again after an erasure, so a projection's checkpoint stays
valid.

`tilttek/activity-bundle` answers the export and erasure requests of `phil/privacy-bundle` with this method.
