# Teams and titles

## Titles

A board may give a `Title`: a name — like a board's — and a threshold of 1 to 10^15. A member holds it while their score
on the board in the period under way reaches the threshold:

```php
'regular' => ['measure' => 'days', 'verbs' => ['lesson.done'], 'period' => 'window', 'window' => 7,
    'title' => ['name' => 'regular', 'threshold' => 5]],
```

A title with `leader` goes to the leader alone, among those who reach the threshold: nobody ranks before them. On a
board ranked by object over a window, it names the *local legend* of each object — the member who played a game most
in ninety days:

```php
'legend' => ['measure' => 'count', 'verbs' => ['game.played'], 'period' => 'window', 'window' => 90, 'per_object' => true,
    'title' => ['name' => 'local-legend', 'threshold' => 3, 'leader' => true]],
```

`Leaderboards::holds($board, $member, $object)` says whether the member holds a board's title now — for the object on a
board ranked by object —; `titles($member)` returns the titles of the boards not ranked by object the member holds,
each once, sorted: `['regular']` for a member active five days of the last seven. Two boards may give the same title. A
title is read, not kept: it goes with the score that earns it, and the application shows it where it shows the member.

## Teams

Tilttek keeps no team: the application gives each `Team` — a name like a board's and a `Circle` of its members — when it
asks for their ranking. A team's score is the sum of its members' scores on the board in the period:

```php
$rows = $leaderboards->teams('house-cup', [
    new Team('red', Circle::of('member:1', 'member:2')),
    new Team('blue', Circle::of('member:3')),
]);
```

`teams()` takes 1 to 100 teams, each named once, the period — the one under way by default — and the object on a board
ranked by object. Each `TeamRow` holds the rank — equal totals share it, listed by name —, the team's name, how many of
its members hold a score, the sum of their scores, the roll of luck and the total.

## Luck in a team score

A board enables luck with a `Chance`: 1 to 10 dice of 2 to 100 faces, rolled once for each team and period and added
to its score.

```php
'house-cup' => ['measure' => 'total', 'verbs' => ['quest.done'], 'period' => 'month', 'chance' => ['dice' => 2, 'faces' => 6]],
```

The roll is drawn from the secret given to `Leaderboards`, the board, the period and the team, through an HMAC that
seeds the random engine: the same team gets the same roll for a period, whoever looks and however often, and nobody
without the secret can foresee it. A board without a chance adds none; a board with one and no secret raises
`InvalidLeaderboard`.

```php
$leaderboards = new Leaderboards($boards, $scores, $snapshots, $appearances, $clock, secret: $applicationSecret);
```
