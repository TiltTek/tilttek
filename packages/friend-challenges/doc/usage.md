# Usage

## Creating a challenge

A member asks for a challenge with a `ChallengeDraft`: its identifier, chosen by the application — a ULID, a UUID —,
its title of 120 characters at most, its prize, its end, the friends it invites, who may take part, who judges its
proofs, its terms of 2 000 characters at most and, for a vote, the votes needed.

```php
use Tilttek\FriendChallenges\ChallengeDraft;
use Tilttek\FriendChallenges\Prize;
use Tilttek\FriendChallenges\Validation;
use Tilttek\FriendChallenges\Visibility;

$challenge = $challenges->create(new ChallengeDraft(
    id: '01JD3',
    title: 'Paint ten minis in a week',
    prize: new Prize(100),                       // 100 points; new Prize(5, 'gems') for another unit of points
    endsAt: new DateTimeImmutable('+7 days'),
    invitees: [$bea, $cy, $dee],
    visibility: Visibility::Friends,             // the default; Visibility::Open lets any member take part
    validation: Validation::Vote,                // or Validation::Creator, the default, or Validation::Moderator
    description: "Ten minis.\nOne photo of each.",
    votesNeeded: 2,
), $ana);
```

The creation checks the draft against the site's `ChallengeRules` — the length of the challenge, the largest prize —,
checks that each invitee is a friend of the creator through `Contract\Friendship`, holds the prize out of the
creator's points, keeps the challenge, then dispatches `ChallengeCreated` and one `MemberInvited` for each friend. The
same draft sent again by the same creator gives back the challenge it created and holds nothing more; the same
identifier with another draft is refused. A prize the creator's points do not cover throws `InsufficientPoints`, and
nothing is created.

A challenge between friends invites 1 to 50 of them; an open challenge invites nobody in particular. The creator sets
and judges the challenge, and never takes part in it.

## Taking part

```php
$challenges->join('01JD3', $bea);
$challenges->decline('01JD3', $dee);
```

An invited friend — or any member, in an open challenge — takes part until the challenge ends; a challenge takes 100
participants at most. An invited friend may decline; the challenge keeps who declined, and an application shows the
creator who takes part, never who declined. A friend who declined may still take part before the end.

## Proofs and their review

```php
$challenges->submitProof('01JD3', 'proof-1', $bea, 'upload:8812');
$challenges->approve('01JD3', 'proof-1', $ana);
$challenges->reject('01JD3', 'proof-2', $ana);
```

A participant submits a proof until the challenge ends: an identifier of the proof, chosen by the application, and an
opaque reference to the evidence the application keeps — an upload, a play log, a post —, of 500 bytes at most without
whitespace. A participant submits five proofs at most, and a challenge holds 200.

The proofs are reviewed until `reviewEndsAt`, the end of the challenge and the review the rules give, three days by
default. Who reviews them depends on its `Validation`:

| Validation | Who approves or rejects | When a proof is accepted |
|---|---|---|
| `Creator` | The creator | At the creator's approval |
| `Moderator` | A moderator of the site, as `Contract\Moderation::mayModerate()` says | At a moderator's approval |
| `Vote` | The creator and the participants, each on the proofs of the others | When the votes needed approve it; rejected when the votes needed reject it |

A voter votes once on a proof and does not change their vote. The first proof accepted wins: its participant is the
winner, the prize is paid to them, and `ChallengeWon` is dispatched.

## How a challenge ends

| Move | By | When | The prize |
|---|---|---|---|
| A proof accepted | Its judge | Before the review ends | Paid to the winner |
| `cancel()` | The creator | Before any proof is submitted | Given back to the creator |
| `expire()`, `expireDue()` | A scheduled task | Once the review is over, no proof accepted | Given back to the creator |
| `remove()` | A moderator | While the challenge is open or frozen | Given back to the creator |

```text
open ──► won
 │ ▲  └─► cancelled | expired
 ▼ │
frozen ──► removed ◄── open
```

A settled challenge never changes again. Each move that ends a challenge dispatches `ChallengeWon` or
`ChallengeEnded`, after its escrow move.

## Reading a challenge

`get($id)` gives the `Challenge`: its state, its participants, its proofs with their state and votes, its winner and
when it was settled. `Challenge::toArray()` gives the record an application keeps, and `Challenge::fromArray()` reads
it back, checked field by field and as a whole: a record no move could have written is refused with
`InvalidChallenge`.

## Times

Every time is kept in UTC to the microsecond, whatever the time zone of the clock or of the draft. The library orders
times with `<` and `>=`, and compares two times only by what they write, never with `==` or `===`.
