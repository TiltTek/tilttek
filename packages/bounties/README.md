# tilttek/bounties

**Playful bounties prepaid in points, held in escrow, claimed and awarded within a bounded time; never money.**

Ana wants a better answer to her rules question and offers a bounty of 100 points, open for a week. The points leave
her balance at once and are held. Bea and Cy answer and claim it; Ana awards 60 points to Bea's answer and 30 to Cy's,
then closes the bounty: Bea receives 60, Cy 30, and the 10 left go back to Ana. Had Ana awarded nothing by the end of
the grace that follows the week, every point would have gone back to her; had she awarded something and forgotten to
close, the awards would have been paid then, the rest given back.

A bounty is offered on a subject — a question, a quest, a request —, one open bounty at a time on each, with a reason
chosen from a list. Its length is bounded and required. Each bounty is an explicit state machine; every move is
checked, kept only if no other writer kept another first, then settled in the escrow and announced by an event. A
move sent again changes nothing. Every time is in UTC.

It is a plain PHP library. The application keeps the bounties and the points in its own storage, through two ports;
the library ships a memory implementation of each, and an escrow on the ledger of `tilttek/points`. A bounty is points,
never money: the library has no field and no adapter for money.

## Status

| Part | State |
|---|---|
| `Bounties`: offer, claim, award, close, cancel, expire, settle, erase, with a limit of open bounties a sponsor | Implemented and tested |
| `Bounty`: the state machine, its limits, and its record written and read back, checked | Implemented and tested |
| `Contract\BountyEscrow`, with `Escrow\InMemoryBountyEscrow` and `Escrow\PointsBountyEscrow` on `tilttek/points` | Implemented and tested |
| `Contract\BountyStore`, with `Store\InMemoryBountyStore` | Implemented and tested |
| `Event\BountyAwarded` and the other events, dispatched through PSR-14 | Implemented and tested |
| Exceptions under one `BountiesException` interface | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing and wiring. |
| [Usage](doc/usage.md) | Offering a bounty, claims, awards and how a bounty is settled. |
| [Escrow](doc/escrow.md) | How the points are held, paid and given back, and an escrow on `tilttek/exchange-bundle`. |
| [Privacy](doc/privacy.md) | Erasing a member: leaving each bounty, then a pseudonym in their place. |
| [Celebrations](doc/celebrations.md) | Announcing a bounty awarded, with the celebration of the PHIL UX kit. |
| [Extension points](doc/extension-points.md) | The ports, writing a store, and the events. |
| [Testing](doc/testing.md) | The library's tests, fuzz targets and benchmarks. |
| [Reference](doc/reference.md) | Every type of the library, with its role, and its exceptions. |

## Quick start

```php
use Phil\Resource\ResourceReference;
use Tilttek\Bounties\Bounties;
use Tilttek\Bounties\BountyDraft;
use Tilttek\Bounties\BountyReason;
use Tilttek\Bounties\Prize;

$bounties = new Bounties($store, $escrow, $eventDispatcher);

$bounties->offer(new BountyDraft('01JD4', ResourceReference::of('question', '42'), BountyReason::Detail, new Prize(100), new DateTimeImmutable('+7 days')), $ana);
$bounties->claim('01JD4', 'answer-901', $bea, 'answer:901');
$bounties->award('01JD4', 'award-1', 'answer-901', 100, $ana);   // the whole amount: Bea receives 100 points at once
```

## Requirements

PHP 8.5 or later with `sodium`, `phil/resource` 0.1, `tilttek/points` 0.1 or 0.2, `psr/clock`, `psr/event-dispatcher` and `symfony/clock`.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. See [LICENSE](LICENSE).
