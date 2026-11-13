# Usage

## A scenario

A `Scenario` holds 1 to 200 puzzles and, optionally, its finale: the puzzle that ends it. Without a finale, the scenario
ends when every puzzle is solved. Identifiers — of the scenario and of each puzzle — hold 1 to 64 characters among
`a-z`, `0-9`, inner `-` and `_`, and start with a letter.

A `Puzzle` has:

| Field | Meaning | Bounds |
|---|---|---|
| `id`, `title` | Its identifier and the title the players read | Title of 1 to 200 characters |
| `answer` | How its guesses are checked: an `Answer\AnswerKey`, or a check of the application's own | — |
| `hints` | Its tiers of hints, revealed in this order | 10 at most, each 1 to 2 000 characters |
| `unlock` | When it opens | See below |

A scenario is built in PHP or read from plain values or JSON — within 256 KiB and 8 levels, a field the library does
not know refused:

```json
{
    "id": "manor",
    "finale": "vault",
    "puzzles": [
        {"id": "clock", "title": "The clock", "answers": ["Midnight", "Minuit"],
         "partials": {"twelve": "Which twelve?"},
         "hints": [{"text": "Look at the hands", "penalty": 60}, {"text": "Both point up"}]},
        {"id": "painting", "title": "The painting", "answers": ["Le Chêne"],
         "normalize": {"keep_accents": true}},
        {"id": "letter", "title": "A letter", "answers": ["Rosebud"], "unlock": {"manual": true}},
        {"id": "vault", "title": "The vault", "answers": ["4521"],
         "unlock": {"requires": ["clock", "painting"]}}
    ]
}
```

```php
$scenario = Scenario::fromJson($json);
$scenario->order();   // ['clock', 'painting', 'letter', 'vault']: an order a game can solve them in
```

## Answers

An `Answer\AnswerKey` holds 1 to 10 accepted answers and up to 20 partial answers, each with the message the players
read. Every answer is normalized once, when the key is made, and every guess the same way, by an
`Answer\AnswerNormalizer`:

| Step | Default | Option |
|---|---|---|
| Compatibility form: a full-width `Ａ` is an `a` | Always | — |
| Case folding: `Straße` is `strasse` | Always | — |
| Invisible characters removed: zero-width spaces, controls | Always | — |
| Accents removed: `chêne` is `chene` | On | `keep_accents` |
| Punctuation and symbols removed: `l'oak!` is `loak` | On | `keep_punctuation` |
| Spaces removed: `old oak` is `oldoak` | On | `keep_spaces`, which keeps one space between words |

A text holds 200 characters at most. A partial answer equal to an accepted one, or given twice once normalized, is
refused when the key is made.

```php
$key = new AnswerKey(['Le Chêne'], ['chene' => 'Which tree, exactly?'], new AnswerNormalizer(keepAccents: true));
$key->check('le chêne')->outcome;   // GuessOutcome::Correct
$key->check('chene')->message;      // "Which tree, exactly?"
```

## Guesses

`PuzzleEngine::guess()` takes the scenario, the team's `Progress`, the team, the puzzle and the guess, and gives a
`GuessResult`:

| Field | Meaning |
|---|---|
| `outcome` | `Correct`, `Partial`, `Wrong` or `Throttled` |
| `message` | The message of a partial answer |
| `retryAfter` | When a throttled team may guess again |
| `opened` | The puzzles the solve opened, in the order of the scenario |
| `completed` | Whether this solve ended the scenario |
| `progress` | The progress to store |

A guess of a puzzle that is locked or solved, or not in the scenario, is refused with `PuzzleRefused`. A guess empty
once normalized, longer than 200 characters or not UTF-8 is refused with `InvalidAnswer`: it counts against the limit,
not among the guesses of the progress. A team is named by a stable identifier, never an email address.

Each guess counts one attempt under the key `scenario|team|puzzle` of the limiter. Past the limit of the period, the
guess is not checked: the result is `Throttled`, with `retryAfter`, and the progress is unchanged.

## Hints

`PuzzleEngine::hint()` reveals the next tier of an open puzzle and gives a `HintResult`: the hint, its tier from 1, the
tiers left and the progress. `pushed: true` marks a hint the host pushed rather than one the players asked; both are
kept in the progress with their time. A hint's `penalty`, in seconds, is for the game to use — an escape room adds it to
the final time —; the engine only keeps it. A puzzle with no tier left refuses with `PuzzleRefused`.

## Unlocks

An `Unlock` says when a puzzle opens:

| Unlock | Opens |
|---|---|
| `Unlock::open()` | From the start |
| `Unlock::after(['clock', 'painting'])` | Once both are solved |
| `Unlock::after(['a', 'b', 'c'], 2)` | Once two of the three are solved |
| `Unlock::afterSolves(3)` | Once three puzzles of the scenario, any of them, are solved |
| `new Unlock(['clock'], afterSolves: 2)` | Once the clock and two puzzles in all are solved |
| `Unlock::manual()` | When the host opens it, whatever is solved |

A puzzle requires 50 others at most, each once, and never itself. When the scenario is made, the graph is checked:

- every puzzle required is in the scenario, and the finale too;
- no puzzles require one another, directly or through others: `The puzzles b → c → d → b require one another.`;
- every puzzle is reachable by a game that solves what it opens, puzzles the host opens counted as opened: a puzzle
  waiting for more solves than the scenario allows, or on a puzzle that never opens, is refused —
  `No game can reach the puzzles b, c.`

## The host's moves

- `PuzzleEngine::open()` opens a puzzle declared `Unlock::manual()`: a clue handed out in the second act. Another
  puzzle, or one open already, is refused.
- `PuzzleEngine::skip()` marks an open puzzle solved for players who are stuck; it opens what the puzzle opens, and
  the progress keeps that it was skipped.

## Storing a game

The library stores nothing. `Progress::toArray()` gives plain values — the puzzles solved with their time and whether
they were skipped, those the host opened, the hints revealed and the guesses of each puzzle —, every time in UTC to the
microsecond; `Progress::fromArray()` reads them back, checked against the scenario: a puzzle the scenario does not
hold, a hint out of its order, a count out of bounds is refused with `InvalidProgress`.

```php
$progress = Progress::fromArray($scenario, $row['progress']);
$result = $engine->guess($scenario, $progress, $team, $puzzle, $request->getPayload()->getString('answer'));
$row['progress'] = $result->progress->toArray();
```

Two moves of the same team at once read the same progress: the application stores the progress with a version and
refuses the second write, as it does for any row two requests change.

## The view

`PuzzleEngine::view()` gives what the players see, in the order of the scenario: for each puzzle its status, its title,
the hints revealed and how many are left, its guesses and when it was solved. A locked puzzle shows its identifier and
status only. `ScenarioView::toArray()` is ready for JSON; it holds no answer, no partial answer and nothing that checks
one.

```json
{"scenario": "manor", "completed": false, "puzzles": [
    {"id": "clock", "status": "open", "title": "The clock", "hints": ["Look at the hands"], "hints_left": 1,
     "guesses": 2, "solved_at": null, "skipped": false},
    {"id": "vault", "status": "locked", "title": null, "hints": [], "hints_left": 0, "guesses": 0,
     "solved_at": null, "skipped": false}
]}
```

## Showing the game

The library draws nothing. The page that shows a game keeps it usable by everyone:

- each puzzle's status is said in words — "Solved", "Open", "Locked" —, never by a colour or an icon alone;
- the answer field has a visible label, and the outcome of a guess is announced in a polite live region as a whole
  sentence: "Not yet: which twelve?", "Solved! The vault is open.";
- a throttled guess says when the team may try again, in words, and the field stays usable;
- a solve that ends the scenario is celebrated with the celebration component of PHIL UX, which respects
  `prefers-reduced-motion`.
