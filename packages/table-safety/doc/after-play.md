# After play

## Stars and wishes

After a session, each participant gives their **stars** — what they enjoyed — and their **wishes** — what they would
like to see next time:

```php
$safety->giveFeedback($table, '2026-11-12', $participant, new StarsAndWishes(['The heist'], ['More downtime']), $now);
```

Each text is trimmed and has at most 500 characters; empty texts are left out; at most 10 stars and 10 wishes are
given. A session is named by a key the application chooses — a date, `session-3` —, written like a theme key. Giving
them again replaces the participant's earlier stars and wishes for that session.

## The summary of a session

`TableSafety::feedback()` gathers a session's stars and wishes without saying who gave which: every star and every
wish in alphabetical order rather than in the order they were given, and how many participants' answers it holds.

```php
$summary = $safety->feedback($table, '2026-11-12');

$summary->hasEnoughAnswers();  // false: "not enough answers yet"
$summary->stars;               // ['Bards', 'More maps', 'The heist'] once it has enough
$summary->wishes;              // ['More downtime']
$summary->respondents;         // 3
```

## The threshold

In a small group, a summary of one or two answers says who wrote them. A summary therefore holds answers only in whole
groups of a **threshold** of participants, 3 by default, taken in the order they were given:

- under the threshold, it holds nothing: `hasEnoughAnswers()` is false, `stars` and `wishes` are empty and
  `respondents` is 0, whatever the number of answers;
- past it, the answers of a group that is not full wait: with a threshold of 3, four or five answers give the summary of
  the first three, six give all six.

One more answer thus never changes the summary by the answers of one person alone. The application chooses the
threshold — never a table, whose game master reads the summary:

```php
$safety = new TableSafety($catalogue, $tables, $participations, $signals, $feedback, feedbackThreshold: 4);
```

A threshold of 1 shows every answer as soon as it is given. [Privacy](privacy.md#the-summary-of-stars-and-wishes) says
what the threshold does not hide.
