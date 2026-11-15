# tilttek/friend-challenges

**Member challenges, between friends or open, with a points prize held in escrow and the site's moderation.**

Ana dares her friends to paint ten miniatures in a week, for 100 points. The points leave her balance at once and are
held for the prize; Bea and Cy take part, Dee declines without Ana being told; Bea sends a photo, Ana accepts it, and
the 100 points go to Bea. Had nobody met the challenge by the end of its review, the points would have gone back to
Ana, as they do when she cancels it before anyone sends a proof, or when a moderator removes it.

A challenge is between friends unless its creator opens it to every member. Its proofs are judged by its creator, by
a vote of the creator and the other participants, or by a moderator of the site. A member reports a challenge or a
proof to the moderators, who freeze it — its prize stays held, nothing moves —, unfreeze it, remove it, or remove a
proof. Each challenge is an explicit state machine; every move is checked, kept only if no other writer kept another
first, then settled in the escrow and announced by an event. A move sent again changes nothing. Every time is in UTC.

It is a plain PHP library. The application keeps the challenges, the friendships, the moderation and the points in
its own storage, through four ports; the library ships a memory implementation of each, and an escrow on the ledger of
`tilttek/points`. The prize is points, never money.

## Status

| Part | State |
|---|---|
| `FriendChallenges`: create, take part, decline, prove, approve, reject, cancel, expire, report, freeze, unfreeze, remove, settle, erase | Implemented and tested |
| `Challenge`: the state machine, its limits, and its record written and read back, checked | Implemented and tested |
| `Contract\PrizeEscrow`, with `Escrow\InMemoryPrizeEscrow` and `Escrow\PointsPrizeEscrow` on `tilttek/points` | Implemented and tested |
| `Contract\ChallengeStore`, `Contract\Friendship`, `Contract\Moderation`, with memory implementations | Implemented and tested |
| `Event\ChallengeWon` and the other events, dispatched through PSR-14 | Implemented and tested |
| Exceptions under one `FriendChallengesException` interface | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing and wiring. |
| [Usage](doc/usage.md) | Creating a challenge, taking part, proofs and their review, how a challenge ends. |
| [Escrow](doc/escrow.md) | How the prize is held, paid and given back, and an escrow on `tilttek/exchange-bundle`. |
| [Moderation](doc/moderation.md) | Reports, freezing, removing, and the guardrails between friends. |
| [Privacy](doc/privacy.md) | Erasing a member: leaving each challenge, then a pseudonym in their place. |
| [Celebrations](doc/celebrations.md) | Announcing a challenge won, with the celebration of the PHIL UX kit. |
| [Extension points](doc/extension-points.md) | The ports, writing a store, and the events. |
| [Testing](doc/testing.md) | The library's tests, fuzz targets and benchmarks. |
| [Reference](doc/reference.md) | Every type of the library, with its role, and its exceptions. |

## Quick start

```php
use Tilttek\FriendChallenges\ChallengeDraft;
use Tilttek\FriendChallenges\FriendChallenges;
use Tilttek\FriendChallenges\Prize;

$challenges = new FriendChallenges($store, $escrow, $friendship, $moderation, $eventDispatcher);

$challenges->create(new ChallengeDraft('01JD3', 'Paint ten minis in a week', new Prize(100), new DateTimeImmutable('+7 days'), [$bea, $cy]), $ana);
$challenges->join('01JD3', $bea);
$challenges->submitProof('01JD3', 'proof-1', $bea, 'upload:8812');
$challenges->approve('01JD3', 'proof-1', $ana);   // won: Bea receives the 100 points held
```

## Requirements

PHP 8.5 or later with `mbstring` and `sodium`, `phil/resource` 0.1, `tilttek/points` 0.1 or 0.2, `psr/clock`,
`psr/event-dispatcher` and `symfony/clock`.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. See [LICENSE](LICENSE).
