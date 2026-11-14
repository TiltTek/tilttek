# Privacy and standings

A rating compares a member with others, and a public comparison puts pressure on them. The library therefore keeps
every rating **private by default**: the member sees their own; nobody else does until the member chooses otherwise.

## Publishing

```php
$ratings->publish('chess', 'member-1042');   // the member's rating of chess appears in the standings
$ratings->hide('chess', 'member-1042');      // and leaves them
```

The visibility is chosen game by game, and only by the member: the application calls `publish()` from the member's own
action, never on their behalf. A member may publish before their first rated game; their rating then appears once they
play one.

## The standings

```php
$ratings->standings('chess', limit: 100);    // list<MemberRating>, 1 to 1 000
```

The standings list the public ratings of members with at least one rated game, the highest score first — the Elo
rating, or the conservative estimate of a skill —, then by member. A private rating never appears, nor a count of the
members who keep theirs private.

## Provisional ratings

A rating after a few games says little: one lucky win would put a member at the top. `isProvisional($rating)` is true
while the member has played fewer rated games of the game than `provisionalGames` asks for, 10 by default
(`Ratings::PROVISIONAL_GAMES`), or while the system finds the rating uncertain: an OpenSkill skill whose deviation is
above `provisionalSigma`, when one is set. Elo has no measure of its own, so its ratings are provisional by their games
alone; `provisionalGames: 0` leaves the games out.

```php
$chess = new Ratings($store, new EloSystem(), provisionalGames: 20);
$chess->isProvisional($chess->rating('chess', 'member-1042'));   // shown as "1 512?"
$chess->standings('chess', 100, provisional: false);             // the provisional ratings left out
```

A page marks a provisional rating beside its score, as a question mark. `standings()` lists them by default; with
`provisional: false`, it leaves them out and still gives up to `$limit` members, reading further down the standings when
the system leaves some out, up to `Ratings::MAX_STANDINGS` ratings read.

## Showing a rating

`MemberRating::value()` is the score to two decimals; `$games`, `$peak` and `$lastResult` tell how many rated games,
the highest score reached and the last result counted. Show a private rating to its member alone, and a change — the
`RatingChange` of `record()` — on the member's own page. A friendly game, played outside the ratings, is there for the
members who do not want to be rated at all.

## Export and erasure

```php
$ratings->export('member-1042');   // every rating of the member, whatever the game
$ratings->forget('member-1042');   // erased
```

`forget()` erases the member's ratings; the results stay in their source, whose erasure of the member is the source's
own. A replay after it rates the member again only if the source still names them.
