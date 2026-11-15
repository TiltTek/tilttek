# Usage

## Offering a bounty

A member offers a bounty with a `BountyDraft`: its identifier, chosen by the application — a ULID, a UUID —, the
subject it is offered on, the reason, the amount and the end.

```php
use Phil\Resource\ResourceReference;
use Tilttek\Bounties\BountyDraft;
use Tilttek\Bounties\BountyReason;
use Tilttek\Bounties\Prize;

$bounty = $bounties->offer(new BountyDraft(
    id: '01JD4',
    subject: ResourceReference::of('question', '42'),
    reason: BountyReason::Detail,
    prize: new Prize(100),                      // 100 points; new Prize(5, 'gems') for another unit of points
    endsAt: new DateTimeImmutable('+7 days'),   // required, within the site's rules
), $ana);
```

The reasons are `Attention`, `Detail`, `Sources`, `Reward` — an answer given already deserves it —, `Creativity` and
`Other`. The offer checks the draft against the site's `BountyRules` — the length, the smallest and largest amount —,
checks that the subject has no open bounty and that the sponsor has fewer open bounties than the rules allow — five
by default, `openPerSponsor` —, holds the points out of the sponsor's, keeps the bounty, then dispatches
`BountyOffered`. The same draft sent again by the same sponsor gives back the bounty it offered and holds nothing more;
the same identifier with another draft is refused. Points the sponsor does not have throw `InsufficientPoints`, and
nothing is offered.

## Claims

```php
$bounties->claim('01JD4', 'answer-901', $bea, 'answer:901');
```

Any member but the sponsor claims the bounty until it ends: an identifier of the claim, chosen by the application, and
an opaque reference to what answers it — an answer, a post, an upload —, of 500 bytes at most without whitespace. A
member claims five times at most, and a bounty takes 200 claims.

## Limits a member

Each bound keeps one member from flooding the others:

| Bound | Default | Where |
|---|---|---|
| Open bounties a sponsor | 5, from 1 to 100 | `BountyRules::$openPerSponsor` |
| Open bounties a subject | 1 | `BountyStore::openOn()` |
| Claims a member on one bounty | 5 | `Bounty::MAX_CLAIMS_PER_MEMBER` |
| Claims on one bounty | 200 | `Bounty::MAX_CLAIMS` |
| Awards on one bounty | 20 | `Bounty::MAX_AWARDS` |
| Length of a bounty | 1 to 30 days, at most 90 | `BountyRules::$shortest`, `$longest` |
| Amount of a bounty | 10 to 10,000 points, at most 1,000,000 | `BountyRules::$smallestPrize`, `$largestPrize` |

The sponsor's points bound the rest: a bounty is paid in advance. The count of open bounties is read before the offer
is kept, so offers of one sponsor sent at the same instant may all pass it; a site that needs the bound exact counts
in the same transaction as the offer, in its store.

## Awards

```php
$bounties->award('01JD4', 'award-1', 'answer-901', 60, $ana);
$bounties->award('01JD4', 'award-2', 'answer-902', 30, $ana);
$bounties->close('01JD4', $ana);
```

The sponsor awards points to claims until the grace ends — the end and the grace of the rules, a day by default —:
the whole amount to one claim, or shared between several, 20 awards at most, never more than what is left. Each award
has an identifier: sent again, it changes nothing. Once the whole amount is awarded, the bounty is settled at once.

## How a bounty is settled

| Move | By | When | The points |
|---|---|---|---|
| The whole amount awarded | The sponsor | Before the grace ends | Paid to the winners |
| `close()` | The sponsor | With at least one award, before the grace ends | The awards paid, the rest given back |
| `cancel()` | The sponsor | Before any claim | Given back |
| `expire()`, `expireDue()` | A scheduled task | Once the grace is over | The awards paid and the rest given back; everything given back when nothing was awarded |

```text
open ──► awarded
  └───► expired | cancelled
```

A bounty with claims is not cancelled: it is awarded, or it expires. The awards to one member are paid together, in
one payout. A settled bounty never changes again, and its subject is free for a new bounty. Each settlement dispatches
one `BountyAwarded` for each winner and one `BountyClosed`, after its escrow move.

## Reading a bounty

`get($id)` gives the `Bounty`: its state, claims and awards, `awarded()` and `payouts()`. `Bounty::toArray()` gives
the record an application keeps, and `Bounty::fromArray()` reads it back, checked field by field and as a whole: a
record no move could have written is refused with `InvalidBounty`.

## Times

Every time is kept in UTC to the microsecond, whatever the time zone of the clock or of the draft. The library orders
times with `<` and `>=`, and compares two times only by what they write, never with `==` or `===`.
