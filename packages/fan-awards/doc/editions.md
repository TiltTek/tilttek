# Editions and phases

An edition is one year of the awards, or one convention's: its key, its name, its categories, and whether it holds a
semifinal. `FanAwards` runs it on a `Contract\AwardStore`.

## Categories

```php
new Category(
    'novel',            // 1 to 64 lower-case letters, digits, "_" or "-", starting with a letter
    'Best novel',       // 1 to 100 characters
    nominations: 5,     // nominees on a nomination ballot, 1 to 10
    semifinalists: 10,  // nominees the semifinal takes, 2 to 20
    finalists: 5,       // nominees the final takes, 2 to the semifinalists
    method: 'instant_runoff', // the ranked method of the final: instant_runoff, borda, schulze
    noAward: true,      // "no award" among the final's options
);
```

An edition holds 1 to 50 categories with unique keys. `create()` refuses a category whose method is not a ranked
method of the application's `CountingMethods`, and a key another edition holds.

## Nominees

A `Nominee` has a key — the option of the ballots, 1 to 64 ASCII letters, digits, `_` or `-` —, a title of 1 to 200
characters, and the application's reference of what it names, if any — `book:12`. The key `no-award` is reserved for
"no award". The application derives the key from its catalog, so that two voters writing in the same work give the
same key; the first title given is kept.

## The phases

| Phase | What happens | `advance()` goes to |
|---|---|---|
| `Draft` | The edition is prepared; organisers `propose()` nominees | `Nomination` |
| `Nomination` | Organisers still propose; voters nominate, writing in nominees | `Semifinal`, or `Final` without semifinal |
| `Semifinal` | Voters choose one nominee of the shortlist, or write one in | `Final` |
| `Final` | Voters rank the finalists and "no award" | `Counted` |
| `Counted` | The organisers `preview()` the results | `Announced` |
| `Announced` | Everyone reads `results()` | — |

Advancing from a voting phase counts every category of it, as [counting and results](counting.md) describes. Advancing
an announced edition is refused with `Refusal::WrongPhase`. The application decides when each phase ends — by hand, or
from its scheduler.

## Voting

```php
$awards->vote('2027', 'novel', $voter, ['the-long-road', new Nominee('night-ferry', 'Night Ferry')]);
$awards->ballot('2027', 'novel', $voter);   // the voter's own ballot of the current phase
```

| Phase | A ballot holds | Write-ins |
|---|---|---|
| `Nomination` | 1 to the category's `nominations` nominees, each once | Yes |
| `Semifinal` | Exactly one nominee | Yes |
| `Final` | A ranking of 1 to all the options, keys only, `no-award` among them when the category offers it | No |

A voter casts one ballot per category and phase; voting again replaces it until the phase ends. A ballot outside a
voting phase is refused with `Refusal::WrongPhase`, a voter the application's `VoterEligibility` turns away with
`Refusal::VoterNotEligible`, and a ballot that breaks the phase's rules with `InvalidAward`, saying how. `finalOptions()`
lists the final's options in their order.

While a category offers no nominee or one, a nomination or semifinal ballot holds that nominee alone, or one written in:
the first nominees of an open category arrive one by one. A final with fewer than two options takes no ballot.

## Write-ins and eligibility

A `Nominee` in a ballot whose key is not yet a candidate is written in: the application's `NomineeEligibility` checks
it first, and a refusal is thrown as `Refusal::Ineligible` with the reason it gave, for the voter to read. A nominee an
organiser proposes is checked the same way. A category offers at most 100 nominees in a phase, the most a ballot of
`phil/poll` holds; beyond, `Refusal::TooManyCandidates`. A write-in joins the candidates only when its ballot is valid.

## Voters

`VoterEligibility::mayVote($voter, $edition)` says who votes: a member since before the nominations, a member of the
community the awards belong to. It is never a payment nor a purchase. `Eligibility\AnyVoter`, the default, lets in
every member the application lets reach the ballot.
