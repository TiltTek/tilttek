# Referring and rewarding

## The code

`code($member)` gives the member's referral code, drawn the first time it is asked for and kept afterwards: ten
characters of Crockford's base 32 — digits and capital letters without I, L, O and U —, 50 random bits that tell
nothing of the member. The application puts it in the link the member shares, and reads it back from that link or
from a form:

```php
$code = $referrals->code($member);
$link = $urlGenerator->generate('join', ['ref' => $code->value]);
```

A code is read back whatever its case, with I and L as 1, O as 0 and hyphens left out, as a person copies it. When
the code drawn already belongs to another member, another is drawn, five times at most.

## The referral

`refer($code, $newcomer)` records that the newcomer joined through the code, at the clock's moment. The guards refuse
it, each with a `Refusal`:

| Refusal | When |
|---|---|
| `SelfReferral` | The code is the newcomer's own, or `Contract\SamePerson` tells the two accounts are one person |
| `AlreadyReferred` | The newcomer was referred by another member |
| `NewMember` | `Terms::$referrerSeniority` is set, and the referrer joined more recently than that, or is unknown |
| `NotNewcomer` | `Terms::$newcomerWindow` is set, and the newcomer joined longer ago than that, or is unknown |

The same newcomer referred again through the same code is the same referral, returned without checking the dates
again. A newcomer is referred once: the store keeps one referral per newcomer, even when two processes record two at
the same moment, and the second is refused as `AlreadyReferred`.

## The reward

`reward($newcomer)` pays the newcomer's referral. The application calls it when the newcomer acts — a game played, a
sheet published —, and the library asks `Contract\FirstActions` for the newcomer's first real action rather than
trusting the call: without one, or with one made before the referral, the reward is refused as `NoFirstAction`, and
the referral stays pending until a later call.

When `Contract\SamePerson` now tells the two accounts are one person — a signal the application learned since the
referral, such as the same verified address —, the referral is refused for good, and every later call is refused as
`SelfReferral`.

Otherwise, the store settles the referral as rewarded, in one step: the newcomer receives `Terms::$newcomerPoints`,
and the referrer `Terms::$referrerPoints` while fewer than `Terms::$maxRewards` of their referrals gave them points.
The referral keeps both amounts, so that a reward asked again pays the same whatever the terms became meanwhile.

The points are granted through `Points::grant()`, from the issuance account `Terms::$source` — `referrals` by default
— in `Terms::$currency`, under two keys built from the referral's random identifier:

```text
referral:6f1c…e2:newcomer
referral:6f1c…e2:referrer
```

The ledger writes a key once, so the reward asked again, or by two processes at the same moment, gives nothing more:
`Reward::$new` says whether this call wrote a movement. A payment cut short between the settlement and the grants is
completed by the next call. A member who was not referred has no reward: `reward()` returns null.

## The cap

The cap counts the referrals that gave the referrer points, ever. It holds when two newcomers of the same referrer act
at the same moment, since the store checks and settles in one step. Past it, the newcomer still receives their
points; the referrer's are 0. `remaining($referrer)` tells how many more referrals can reward them.

## Reading

`referralOf($newcomer)` gives the newcomer's referral; `referralsOf($referrer, $offset, $limit)` the referrer's, oldest
first, at most 100 at once. A `Referral` holds its status — `Pending`, `Rewarded` or `Refused` —, its moments in UTC,
and the points each side received.

## Erasing a member

`erase($member)` leaves no code and no referral that links the erased member to anyone:

- their code is deleted;
- their pending referral as a newcomer is deleted;
- in their other referrals, on either side, `erased:member` takes their place: a rewarded referral still counts
  towards its referrer's cap, and a pending one still rewards its newcomer, without any points for the erased
  referrer.

The keys of the movements carry the referral's random identifier, never a member: once the referral names
`erased:member`, the ledger links nobody. The points themselves are erased by `Points::erase()`, which the
application calls too.
