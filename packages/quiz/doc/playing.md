# Playing

## At one's own pace

`SelfPacedQuizzes::start()` starts a play of a quiz for a member; `answer()` answers the current question for that
member and shows the next one, whose timer starts at that moment. After the last answer, the play is over:
`QuizCompleted` is dispatched, then `PerfectScore` when every answer was right and in time and the play counts.

```php
$quizzes->start('play-7', 'lore', Player::member('member:44'));
$quizzes->answer('play-7', 'member:44', ChoiceAnswer::of('b'));   // null: the play is not over
$play = $quizzes->play('play-7', 'member:44');
$play->position();     // the current question
$play->timer($quiz);   // its timer: remaining(), deadline()
// ... the last answer returns its AnswerResult, and the play is over
$quizzes->results('play-7', 'member:44');   // list<AnswerResult>
$quizzes->result('play-7', 'member:44');    // the View\ResultView of the result template
```

A guest never plays at their own pace: the play is refused with `QuizRefused`. A finished play takes no more answers.

### A play belongs to its player

Every call but `start()` takes the member who plays. `answer()`, `play()`, `results()` and `result()` refuse anyone
else's play with `QuizRefused`, as they refuse a play that does not exist, so that a member never learns that another's
play is there. The application passes the member of the request, never an identifier read from a form.

### Results once the play is over

A member's answers say nothing until the play is over: `answer()` returns null for every answer but the last, and
`results()` and `result()` refuse a play that is not over. A member cannot learn the right answers in the middle of a
play and use them in the same play. A quiz meant to teach as it goes shows each result at once:

| `SelfPaced\Feedback` | What a member sees before the end |
|---|---|
| `AfterPlay`, the default | Nothing: `answer()` returns null, `results()` and `result()` are refused |
| `AfterEachAnswer` | The result of each answer, returned by `answer()`, and the results and the score so far |

`SelfPaced\Play::results()` and `score()` are the record the store keeps; a page shows them through `results()` and
`result()` of the service, which apply the rule.

### Plays that count, and practice

Once a play is over, its member knows the right answers; any play started after the first had its questions seen
before. Each play carries its `attempt`: 1 for the member's first play of the quiz, one more for each play they started
before, over or not. Only the first counts:

| `SelfPaced\Replays` | A member's second play of a quiz |
|---|---|
| `Practice`, the default | Played and scored for the member to see; `isPractice()` is true, `QuizCompleted` and the result view say `practice`, and `PerfectScore` is not dispatched |
| `Refused` | Refused with `QuizRefused`: each member plays each quiz once |

`PerfectScore` is thus dispatched once per quiz and member at most: for the first play, when it is perfect. A quiz
whose practice deserves fireworks too sets `SelfPaced\PerfectScoreRule::EveryPerfectPlay`: every perfect play then
dispatches `PerfectScore`, with `practice` true for those that do not count, so that a listener awarding a badge still
leaves them out. Two plays started at once never both count: the store refuses a second play at the same attempt, and
`start()` counts again and takes the next attempt, up to `SelfPacedQuizzes::ATTEMPTS` times.

### Erasing a member

`SelfPacedQuizzes::erasePlayer()` deletes every play of a member and returns how many there were, when their account is
erased. A member who plays again afterwards starts at attempt 1: the plays that told what they had seen are gone with
the account.

## Live

The host opens a session; the library draws a six-digit PIN that no other session that is not over holds, from the
system's secure source by default. Players join by the PIN at any moment until the session is over; one who joins late
misses the questions already closed. `join()` takes the PIN as the player typed it: spaces and hyphens between the
digits are left out (`123 456`, `123-456`), and an input that is not six ASCII digits once they are, or longer than 16
bytes, is refused with `InvalidQuiz` before any store is asked; `Pin::parse()` reads it the same way for a form.

```php
$session = $live->open('night-12', 'lore');
$live->join($session->pin, Player::member('member:44'));
$live->join($session->pin, Player::guest('guest-91', 'Pixel'));

$live->next('night-12');                                   // shows the first question to all
$live->answer('night-12', 'member:44', ChoiceAnswer::of('b'));
$live->close('night-12');                                  // the players without an answer are timed out
$live->session('night-12')->leaderboard()->top(3);
// ... next(), answer(), close() for each question
$live->finish('night-12');
```

| State | From | Moves |
|---|---|---|
| `Lobby` | `open()` | `next()` |
| `Question` | `next()` | `answer()` by each player once, `close()` |
| `Review` | `close()` | `next()`, `finish()` |
| `Finished` | `finish()` | `claimGuest()` |

Each player answers a question once; an answer once the time is up is refused. Closing a question that is closed
already does nothing.

| Move | Event, once the session is saved |
|---|---|
| A player joins for the first time | `PlayerJoined` |
| `next()` | `QuestionShown`, with the position of the question |
| `close()` of a question shown | `QuestionClosed`, with the position of the question |
| `finish()` | `LiveQuizFinished`, then `QuizCompleted` for each player, followed by `PerfectScore` for one who answered every question right, then `TeamWon` for each winning team, then `GuestInvited` for each guest |

A player who joins again, and a question closed already, say nothing. The screens follow these moves through
[live updates](live-updates.md).

The events of the end are kept in the session that is over, in the same version, and each stays there until it was
dispatched without an exception. A listener that throws leaves its event there; the other events are dispatched all
the same, and the first exception is thrown again once every event was tried. The session stays over: calling
`finish()` again dispatches the events left, and `deliver($session)` does the same and returns how many went. Once
every event went, `finish()` is refused again.

```php
try {
    $live->finish('night-12');
} catch (ListenerFailed $failure) {   // whatever the listener threw
    $live->deliver('night-12');
}
```

An event of the end may thus be dispatched more than once. The listeners are idempotent: each of these events carries
a `key()`, the same each time it is dispatched again — `night-12/finished`, `trivia/night-12/member:44/completed` — and
a listener that awards points or a badge does it once per key.

A session takes up to 1 000 players. Many players answer at once: each answer is a new version of the session, and
`LiveQuizzes` reads the session again and makes the change again when another answer was saved first, up to eight
times; see [Storing](storing.md).

## Guests

A guest plays live only, under a pseudonym of 1 to 24 characters, trimmed, without control or invisible characters,
taken once per session whatever its case, accents included. The application gives the guest an opaque identifier; the
pseudonym never leaves the session, and no event carries it.

Once the session is over, `GuestInvited` tells the application to offer the guest to become a member. If they do,
`LiveQuizzes::claimGuest()` gives the guest's place, team and answers to the member, unless the member played the
session already; the session then forgets the pseudonym.

### Filtering pseudonyms

A `Contract\PseudonymFilter` given to `LiveQuizzes` refuses the pseudonyms the application does not want; without one,
every pseudonym of the right shape is allowed. The library gives two:

| Filter | Refuses |
|---|---|
| `Pseudonym\WordListFilter::defaults()` | A short list of the library's own, in English and in French: the names that would pass a guest for the host or the staff (`admin`, `host`, `animateur`, `moderator`…) and common insults |
| `new Pseudonym\WordListFilter($words, $anywhere)` | The application's own list alone |
| `Pseudonym\ChainFilter` | What any of its filters refuses, asked in order |

A word list folds the pseudonym and its words before it compares them: lower case, accents left out, the usual digit
and symbol stand-ins read as letters (`4dm1n` is `admin`), everything but letters left out. A word of `words` is
refused as a whole word of the pseudonym, or as the whole pseudonym once its words are joined (`Ad Min`); a word of
`anywhere` is refused wherever it sits, for the terms no common word holds. `Scunthorpe`, `Cassandra` and `Salopette`
pass. A list holds up to 10 000 words of up to 64 characters; a text longer than 64 characters is refused.

The library's list is short on purpose and no moderation: an application adds its own words, or a moderation service,
and keeps the library's list in front:

```php
use Tilttek\Quiz\Pseudonym\{ChainFilter, WordListFilter};

$filter = new ChainFilter(
    WordListFilter::defaults()->with($applicationWords, $applicationTermsAnywhere),
    $moderationService,          // a Contract\PseudonymFilter of the application
);
```

## Teams

A session may be played by teams: `open($session, $quiz, ['red', 'blue', 'green'])` declares 2 to 50 teams, by
identifier; the application keeps their names and colours. A player joins the team given, `join($pin, $player,
'blue')`, or, without one, the team with the fewest players, the first declared on a tie; a player who joins again stays
in their team. A session without teams refuses a team.

```php
$session = $live->open('night-12', 'lore', ['red', 'blue'], TeamScoring::Average);
$live->join($session->pin, Player::member('member:44'), 'red');
$live->join($session->pin, Player::guest('guest-91', 'Pixel'));          // the smaller team
$live->session('night-12')->teamLeaderboard()->top(1);                    // list<Team\TeamStanding>
```

Each player still answers for themselves, and keeps their own place in `leaderboard()`. `teamLeaderboard()` ranks the
teams by the session's `Team\TeamScoring`:

| `TeamScoring` | A team's score and time |
|---|---|
| `Total`, the default | The sum of its players' scores and times: a larger team may score more |
| `Average` | Their mean, rounded to the nearest whole number, half up: teams of different sizes compete evenly; a team without players scores 0 |

The highest score comes first, then the fewer milliseconds; teams equal on both share a rank (1, 1, 3). A
`Team\TeamStanding` holds the rank, the team, its score, the answers of its players fully right, its time and its
number of players. `members($team)` lists the players of a team, `teamOf($player)` the team of a player. At the end,
`TeamWon` is dispatched for each team at the first rank with a score above 0.

## Standings

`LiveSession::leaderboard()` ranks the players: the highest score first; between equal scores, the player whose
scoring answers took the fewer milliseconds; players equal on both share a rank, and the next rank skips the places
they take (1, 1, 3). A wrong answer, or none, never counts in the time. `top(3)` keeps the ranks up to 3, ties at the
edge included; `standingOf()` reads one player's place.
