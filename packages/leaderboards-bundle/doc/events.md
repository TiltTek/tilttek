# Events

| Event | Dispatched when | Holds |
|---|---|---|
| `Tilttek\LeaderboardsBundle\Infrastructure\Event\SnapshotTaken` | A snapshot is taken, once per board and period | `snapshot`, the library's `Snapshot`: board, period, when, how many ranked, and the first members' `Placing`s |
| `Tilttek\LeaderboardsBundle\Infrastructure\Event\LeagueSettled` | A week of a league is settled, once per board and week | `settlement`, the library's `Settlement`: board, week, when, and every `Membership` with its `Movement` |
| `Tilttek\LeaderboardsBundle\Infrastructure\Event\TopReached` | A member reached the top, once per board, period and member: first of a snapshot right after it — several on a board whose ties are shared —, with `celebrate_on: period_close`; in the lead of the period under way as their score is kept, with `celebrate_on: score_change`; or promoted into the highest tier of a league right after the settlement | `board` — the board's key, with the object's digest on a board ranked by object —, `period` — the period's or the week's code —, `member`, `summit` — `Summit::FirstPlace`, `Summit::Lead` or `Summit::HighestTier` —, `at`, when the snapshot was taken, the score reached or the week settled, and `appearance`, how the member chose to appear |

## Rewarding the first

```php
#[AsEventListener]
final readonly class RewardTheFirst
{
    public function __invoke(SnapshotTaken $event): void
    {
        foreach (array_slice($event->snapshot->placings, 0, 3) as $placing) {
            // grant points, a badge or a title to $placing->member for $placing->rank
        }
    }
}
```

The snapshot names every member it ranks, hidden or not: whether a hidden member is rewarded is the application's call.

## Congratulating those who went up

```php
#[AsEventListener]
final readonly class CongratulateThePromoted
{
    public function __invoke(LeagueSettled $event): void
    {
        foreach ($event->settlement->moved(Movement::Promoted) as $membership) {
            // tell $membership->member they reached the tier after $membership->tier
        }
    }
}
```

## Celebrating the top

```php
#[AsEventListener]
final readonly class AnnounceTheTop
{
    public function __invoke(TopReached $event): void
    {
        // send $event->member a notification whose link opens the board's page, which includes
        // @TilttekLeaderboards/celebration.html.twig — see the templates
    }
}
```

`TopReached` follows the event that tells it, so a listener of `SnapshotTaken` or `LeagueSettled` has run first. Its
rules are those of `tilttek_leaderboards_celebration()`, which the screen reads later: the member who opens it sees the
celebration whether or not a listener told them.

## When a first place is told

`celebration.celebrate_on` is the site's choice ([Configuration](configuration.md)).

- **`period_close`**, the default: a first place is told once the period closed, by the snapshot. The member learns it
  when the period is over, and the final standing stands behind it.
- **`score_change`**: a first place is told as it happens. `LeadWatch` decorates the store of scores the library is
  given; when a score kept in the period under way puts its member first — or first with others, on a board whose ties
  are shared — among at least two members, and they were not first before, the lead is claimed in
  `tilttek_leaderboards_lead` and a `TopReached` of `Summit::Lead` is dispatched. A member is told once per board and
  period: taking the lead back after losing it tells nothing new, and neither does a rebuild of the projection, which
  counts the same activities again. A reported value goes through `keep()`: one the store does not keep takes no lead. A period over is never watched. The snapshot then tells no first place; a listener
  of `SnapshotTaken` still rewards the final standing.

Each score kept reads the member's score held and its rank before and after it: two or three indexed reads more per
score, the participants counted only when the member is first. The event is dispatched in the process that keeps the
score — the projection following the journal, or the request that reports a value —, inside the same work: a listener
with heavy work sends itself a message.

## Telling the others

Every member is hidden until they choose to appear, and the events keep to that choice. `TopReached::$appearance` is
the member's `Appearance` at the moment of the event: a listener that tells the others — a feed, a message to a
channel, a screen in a venue — names the member only when `appearance->visibility` is `Visibility::Named`, writes
`appearance->pseudonym` for `Visibility::Pseudonym`, and says nothing of who they are for `Visibility::Hidden`:

```php
#[AsEventListener]
final readonly class TellTheChannel
{
    public function __construct(
        private MemberNames $members, // the application's own service that names a member
    ) {
    }

    public function __invoke(TopReached $event): void
    {
        $who = match ($event->appearance->visibility) {
            Visibility::Named => $this->members->name($event->member),
            Visibility::Pseudonym => $event->appearance->pseudonym,
            Visibility::Hidden => null, // congratulate the member alone, tell the others nothing
        };
    }
}
```

The member is always told themselves, whatever their choice: the celebration is shown to them alone.

The other events are dispatched in the process that takes the snapshot or settles the week — the scheduler's worker, a
command, or the request of a member entering the next week —: a listener with heavy work sends itself a message that
carries the board and the period, and reads the rest when it is handled.
