# Votes

A member votes on an entry with a `Ballot`; voting again replaces it, and `Polls::withdraw()` removes it.

```php
$polls->vote(new Ballot(
    entry: 'tide',
    member: 'm-104',
    ratings: [3 => 4, 4 => 5],                                // a rating of 1 to 5 by number of players
    choices: [3 => PlayerCountChoice::Recommended, 4 => PlayerCountChoice::Best],
    weight: 3,                                                // 1 light to 5 heavy
    minimumAge: 12,
    languageDependence: LanguageDependence::Moderate,
));
```

A ballot holds at least one vote, and votes on at most 30 numbers of players, each from 1 to 999. A number of players
takes both kinds of vote: the rating of 1 to 5 says how good the game is at that number, and the three choices —
best, recommended, not recommended — answer the question players already know from other catalogues.

## The results

`Polls::results()`, or the `results` of a `Sheet`, counts the ballots into `PollResults`. Averages are whole
hundredths, rounded half up — 350 for 3.5 —, so that they compare and display without floating point.

| Method | Gives |
|---|---|
| `playerCount($n)` | A `PlayerCountResult`: the ratings, their sum and `averageRating()`, the votes of each choice and `choice()` |
| `topPlayerCounts()` | The numbers of players rated 4.00 or more on average |
| `recommendedPlayerCounts()` | The numbers of players rated 3.00 or more on average |
| `bestByChoice()` | The numbers of players most members chose as the best |
| `recommendedByChoice()` | The numbers of players most members chose as the best or recommended |
| `weight()` | The average weight, 100 to 500 |
| `minimumAge()` | The median of the ages voted, the lower of the two middle votes |
| `languageDependence()` | The level most voted |

A tie of choices goes to the more cautious — not recommended, then recommended —, and a tie of dependence on language
to the higher level, which warns. A number of players without a rating has no average, and is in no list of ratings.

## Beside the publisher

The results never replace the values of the publisher: a page shows `players` as stated and the numbers the members
recommend beside it, the minimum age of the box and the age the members vote. A correction changes the values of the
publisher; a vote never does.
