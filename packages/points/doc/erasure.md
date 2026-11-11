# Erasure

```php
$erasure = $points->erase($member);
$erasure->deleted;   // movements between the member and the ledger's own accounts
$erasure->detached;  // transfers with other members, kept
```

Erasing a member deletes every movement between the member's account and the ledger's own accounts — earnings,
grants, spendings, expiries. A transfer between the member and another member is kept, with `erased:member` written
in place of the erased member: the other member's points do not change.

The keys of the movements stay as the application built them. Build them from identifiers that say nothing about the
member — an order number, a game number — rather than from a name or an e-mail address.

The journal of activity is erased by `tilttek/activity`; the points derived from it are erased here. A Symfony
application gets both from their bundles' modules for `phil/privacy-bundle`.
