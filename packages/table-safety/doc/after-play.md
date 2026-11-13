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
wish in alphabetical order rather than in the order they were given, and how many participants answered.

```php
$summary = $safety->feedback($table, '2026-11-12');

$summary->stars;        // ['Bards', 'The heist']
$summary->wishes;       // ['More downtime']
$summary->respondents;  // 2
```
