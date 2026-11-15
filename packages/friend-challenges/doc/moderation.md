# Moderation

The moderation of the site stays the site's: its queue, its rules, its notices. The library sends it the reports and
acts on a moderator's word, through `Contract\Moderation`.

## Reports

```php
use Tilttek\FriendChallenges\ReportReason;

$challenges->report('01JD3', $cy, ReportReason::Cheating, proof: 'proof-1', note: 'Not her minis.');
```

Any member reports a challenge, or one of its proofs, with a reason chosen from a list — `Spam`, `Harassment`,
`Cheating`, `Unsafe`, `Inappropriate`, `Other` — and an optional note of 500 characters at most. The library checks
that the challenge and the proof exist and passes a `Report` to `Moderation::report()`, with its time in UTC; it keeps
nothing of it.

## What a moderator does

| Move | From | What it does to the prize |
|---|---|---|
| `freeze($id, $moderator)` | Open | Keeps it held; nothing moves — no member takes part, no proof is submitted or reviewed, the challenge does not expire |
| `unfreeze($id, $moderator)` | Frozen | Keeps it held; the challenge is open again, and expires at the next run if its review is over |
| `remove($id, $moderator)` | Open or frozen | Gives it back to the creator |
| `removeProof($id, $proof, $moderator)` | A proof waiting for its review, in an open or frozen challenge | Nothing |

Each move checks `Moderation::mayModerate()` first, and is refused with `ChallengeRefused` for any other member. When
the proofs of a challenge are left to the moderators, `mayModerate()` also says who approves and rejects them.

## Guardrails between friends

- A challenge is between friends unless its creator opens it: nothing is shown to other members without that choice.
- Each friend is invited once, by one `MemberInvited`: the library never reminds a friend who did not answer.
- A friend declines without the creator being told: the challenge shows who takes part.
- A creator does not cancel a challenge once a proof is submitted: the participants keep their chance at the prize.
