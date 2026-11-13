# Security

## The answers stay on the server

A puzzle checked in the browser is solved by reading its code: the answer, its hash or the function that checks it
travel with the page. The library checks every guess on the server, and nothing it gives for the browser holds an
answer:

- `View\ScenarioView` and `View\PuzzleView` hold statuses, titles, the hints revealed and counts; a locked puzzle shows
  neither its title nor its hints.
- The events name the scenario, the team, the puzzle and a time; none carries a guess nor an answer, so a log of them
  shows no answer either.
- `Answer\AnswerKey` keeps its answers private and compares a guess to each of them with `hash_equals()`, in constant
  time.

A check of the application's own, implementing `Contract\AnswerCheck`, keeps the same rule: it holds its answer and
never gives it out.

## The limit of guesses

`PuzzleEngine` cannot be made without a `Contract\GuessLimiter`. Each guess counts one attempt under the scenario, the
team and the puzzle; past the limit of the period, the guess is not checked and `Event\GuessThrottled` is dispatched,
so that a log shows a script trying every word. The counts live where every worker reads them —
`Limiter\RateLimiterGuessLimiter` on a cache pool —, never in one process alone.

A guess refused as malformed still counts against the limit: sending empty guesses does not get around it.

## Bounded inputs

| Input | Bound |
|---|---|
| A scenario in JSON | 256 KiB, 8 levels, fields known only |
| Puzzles of a scenario | 200, counted before they are read |
| Accepted and partial answers of a puzzle | 10 and 20 |
| An answer, a partial answer, a guess | 200 characters, UTF-8 |
| Hints of a puzzle | 10, each 2 000 characters |
| Puzzles a puzzle requires | 50 |
| Guesses kept in a progress for a puzzle | 1 000 000 |

Checking a scenario visits each puzzle and each requirement a bounded number of times: a cycle is found in one walk of
the graph, and reachability in at most one pass per puzzle. The benchmarks measure the largest scenario.

## Members

A team is a `ResourceReference` from `phil/resource`, an opaque reference the application gives: the library reads no
name, email or account. A reference whose identifier looks like an email address is refused.
