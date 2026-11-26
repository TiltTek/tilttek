# Asking and settling

## The question

`ask($key, $outcomes, $closesAt, $points, $scoring)` asks a question:

```php
$predictions->ask('final:2026', ['home', 'away', 'draw'], new DateTimeImmutable('2026-12-05 19:00 UTC'), 50);
$predictions->ask('campaign:12:end', ['on-time', 'late'], new DateTimeImmutable('2026-12-01 00:00 UTC'), 100, Scoring::Probability);
```

- The key is the application's, from the event — 1 to 200 bytes, without whitespace or control characters, not
  starting with `#`. The same question asked again is returned as it is; asked under the same key with other outcomes,
  another deadline, other points or another scoring, it is refused.
- The outcomes are 2 to 32 keys of the event's results, each 1 to 64 lower-case letters, digits, `_`, `.` or `-`. They
  hold no colon, so a member's reference such as `member:12` is never an outcome.
- The deadline is in the future; it is kept in UTC.
- The points are those a member can earn on the question, 1 to 1 000 000.

**A question is about an outcome of an event, never about a person.** Which team wins the final, which faction holds
the city at the end of the campaign, whether the convention sells out: yes. Who will be crowned at a beauty contest,
who will leave the group, how a member will behave: no. The application declares the outcome; the library refuses
outcomes shaped like a member's reference, and the documentation forbids the rest.

## The pick

`predict($question, $member, $pick)` records the member's pick, in place of the one they made before:

```php
$predictions->predict('final:2026', $member, Pick::choice('home'));
$predictions->predict('campaign:12:end', $member, Pick::probabilities(['on-time' => 70, 'late' => 30]));
```

A question scored by choice takes `Pick::choice()`; one scored by probability takes `Pick::probabilities()`, whole
percents from 0 to 100 summing to 100, an outcome left out given 0 %. The prediction keeps its random identifier when
the member changes their pick.

Nothing is staked: predicting costs the member no points, and the library never takes any.

## The deadline

A question takes predictions while it is open and the clock is before its deadline: at the deadline itself, it is
closed. The store checks again as it writes, in one step that a resolution cannot slip between, so that no prediction
is written once the outcome is declared.

## Resolving and settling

`resolve($question, $outcome)` declares the outcome, which closes the question, even before its deadline, and settles
it: each prediction earns the points its pick scores — [Scoring](scoring.md) gives the rules —, granted through
`Points::grant()` from the issuance account `predictions` under a key built from the prediction's identifier:

```text
prediction:9a3e…41
```

The ledger writes a key once: `settle($question)`, run again after a settlement cut short, or by two processes at
once, pays nothing twice. The predictions are read in pages of the identifiers' order, so a member erased during a
settlement shifts no other prediction. `Settlement` tells how many predictions it read, how many earned points, the
points in all, and how many movements this call wrote.

A question resolved cannot be resolved with another outcome, nor cancelled; declared again with the same outcome, it
settles again.

## Cancelling

`cancel($question)` closes an open question without any points: an event called off, a question asked by mistake. Its
settlement reads nothing and pays nothing; it takes no more predictions and cannot be resolved afterwards.

## Reading

`question($key)`, `predictionOf($question, $member)`, and `predictionsOf($member, $offset, $limit)`, the member's
predictions in the order they were first made, at most 100 at once.

## Erasing a member

`erase($member)` deletes the member's predictions: none names them afterwards. The keys of the movements carry the
prediction's random identifier, never the member; the points themselves are erased by `Points::erase()`, which the
application calls too. A member erased before a settlement earns nothing from it.
