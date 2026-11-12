# Leagues

A league splits the members of a weekly board into divisions of about thirty, ranked among themselves, in tiers from
the lowest to the highest. Each member sees only their division, and nothing of it is public: a member who has not
chosen to appear is a line without a name, in their division as on any board. At the end of the week, in the board's
timezone, the first of each division go up a tier, the last go down, and the divisions of the next week form again —
in the order the members arrive, or by the volume of their activity the week before.

## Declaring a league

A league belongs to a board ranked by ISO week; a board of another period with a league is refused.

```php
$boards = Boards::fromArray([
    'weekly-xp' => [
        'measure' => 'total',
        'verbs' => ['lesson.done'],
        'period' => 'week',
        'league' => ['tiers' => ['bronze', 'silver', 'gold', 'sapphire', 'diamond'], 'division_size' => 30, 'promoted' => 7, 'relegated' => 5],
    ],
], 'America/Toronto');
```

`League` holds 1 to 50 tiers, each named once like a board; a division of 2 to 100 members; how many of the first go
up (`promoted`, 7 by default) and of the last go down (`relegated`, 5 by default), never more in all than a division
holds; and how its divisions are formed (`formed_by`): by `arrival` (the default) or by `volume`. The measure is any: a
total of points, a count of games, a value the application reports. A board ranked by object has no league.

## Entering a division

`Leagues` places a member the first time they score in a week: the projection does it for a board read from the
journal, `Leaderboards::report()` for a reported board. The member enters a division of their tier — the lowest for a
new member, or a tier the league no longer has — in the order of arrival: the lowest numbered division with fewer
members than the division size, or a new one. A member who takes no part in a week has no division that week, and is
neither moved nor counted.

```php
$leagues = new Leagues($boards, $scores, new InMemoryLeagueStore(), $clock, $observer);
$projection = new LeaderboardProjection($boards, $scores, leagues: $leagues);
$leaderboards = new Leaderboards($boards, $scores, $snapshots, $appearances, $clock, $observer, $leagues);
```

An activity recorded late for a week already settled still counts in the week's scores, but enters no division.

## Divisions formed by volume

With `formed_by: volume`, a member enters a division of their tier among the members of their cohort: their score on the
board the week before, in bands that double — no score (cohort 0), 1 (cohort 1), 2 to 3 (2), 4 to 7 (3), and so on,
`League::cohortOf()` —. A division holds the members of one cohort; a newcomer meets members who did about as much as
they did, not one who scores a hundred times more. The cohort is kept on the `Membership`, and the lowest numbered
division of the cohort with room takes the member, or the number after the highest of the tier.

## A member's division

```php
$division = $leaderboards->division('weekly-xp', $member);   // the week under way
$division = $leaderboards->division('weekly-xp', $member, '2026-w45');
```

`division()` returns null when the member takes no part in that week. A `Division` holds the `Membership` — tier,
division number, week, the moment they joined, the cohort and, once settled, their `Movement` —, the `Standing` of the
division ranked among its members only, how many members it holds, and how many go up and down. Each line appears by its
member's choice, as on any board ([Appearing on a board](appearance.md)): a member who has not chosen to appear is a
line without a name, in the division as elsewhere. `movementAt($rank)` says where a line of that rank goes if the week
ended now, for the zones a template shows.

## The end of the week

`settleDue()` settles every week over that a board with a league has not settled, the earliest first; a scheduler runs
it soon after the week ends. `settle($board, $week)` settles one week, refuses a week not over with `PeriodNotOver`,
and returns null for a week already settled. A member entering a division settles first the weeks still open before
theirs, so that they enter at the tier they reached even when the scheduler is late.

In each division, ranked by score in the board's order — a member left without a score, by a cancellation, last in
their order of arrival —:

- the first `promoted` go up a tier, none from the highest tier;
- the last go down a tier, none from the lowest tier: as many as rank below the places a full division keeps, so a
  division of 30 sends 5 down, one of 26 sends 1, and one of 25 or fewer none;
- the others stay.

A week is settled once: the store writes every movement, every new tier and the week's mark together, and a second
settlement writes nothing. The `LeagueObserver` is told `leagueSettled()` once, with the `Settlement` — every
membership of the week with its movement —, so that the application congratulates or rewards the members who went up.

```php
final class Congratulate implements LeagueObserver
{
    public function leagueSettled(Settlement $settlement): void
    {
        foreach ($settlement->moved(Movement::Promoted) as $membership) {
            // tell $membership->member they reached the next tier
        }
    }
}
```

`Leagues::tier()` reads the tier a member reached — the lowest before their first settled week —; `membership()` their
place in a week.

## Export and erasure

`Leaderboards::export()` holds the member's tiers and memberships under `leagues`; `erase()` removes them, and the
others keep their divisions ([Erasure](erasure.md)).
