# Session zero

A session zero is the questionnaire a table answers before it plays: the tone, the themes, the expectations, the
schedule. Its answers, gathered, form the table's **pact**.

## Questions

A `Question` has a key, a kind and, for a choice, its options:

| Kind | Answer |
|---|---|
| `QuestionKind::Text` | a text, trimmed, up to 2,000 characters |
| `QuestionKind::Choice` | one option |
| `QuestionKind::Choices` | several options, kept in the order of the question |

A built-in question has no prompt and no option labels: the application translates them from their keys, as
`session_zero.questions.<key>` and `session_zero.options.<question>.<option>`. A question the game master writes carries
its own prompt — 1 to 300 characters — and its own labels:

```php
new Question('snacks', QuestionKind::Choice, 'What do we snack on?', ['chips' => 'Chips', 'fruit' => 'Fruit']);
```

A choice question has 2 to 20 options; a question may be `required`.

## The built-in templates

| Template | Questions |
|---|---|
| `one-shot` | tone, pillars, themes welcome, conflict between characters, character death, comfort |
| `campaign` — the default | tone, pillars, expectations, themes welcome, conflict between characters, character death, frequency, schedule, table rules, comfort |
| `convention` | experience, tone, conflict between characters, comfort |
| `online` | tone, pillars, themes welcome, camera, recording, table rules, comfort |

`Templates::get('online')` returns one, `Templates::all()` every one. The tone is required, and so is the recording of
an online table.

## A game master's template

A game master starts from a template and changes it; a `Template` has 1 to 50 questions, each key once:

```php
$template = Templates::get('convention')
    ->with(new Question('own-1', QuestionKind::Text, 'Anything we should know?'))
    ->without('experience');
```

`with()` adds a question at the end, or puts it in the place of the one with its key. The template is kept in the
table's [settings](settings.md).

## Answers

`Template::answer()` accepts a participant's answers by question key: a text is trimmed, an option must be one of the
question's, an empty answer is left out, a required question needs an answer, and a key the template does not have is
refused.

```php
$safety->answerSessionZero($table, $participant, ['tone' => 'dark', 'pillars' => ['mystery', 'social']], $now);
```

## The pact

`TableSafety::pact()` gathers the answers of a table without saying who gave which: for a choice question, how many
chose each option; for a text question, the answers in alphabetical order rather than in the order they were given.

```php
$pact = $safety->pact($table);

$pact->hasEnoughAnswers(); // true
$pact->tally('tone');      // ['light' => 0, 'mixed' => 1, 'serious' => 0, 'dark' => 2]
$pact->texts('comfort');   // ['Breaks every hour', 'Captions']
$pact->respondents;        // 3
```

An answer to an option or a question the template no longer has is left out.

### The threshold

In a small group, a tally of one or two answers says who chose what. The pact therefore holds answers only in whole
groups of a **threshold** of participants, 3 by default, taken in the order of their first answer — the same threshold
as the [agreement shown to people](before-play.md#the-summary-shown-to-people):

- under the threshold, it holds nothing: `hasEnoughAnswers()` is false, every tally is at 0, every list of texts is
  empty and `respondents` is 0;
- past it, the answers of a group that is not full wait: with a threshold of 3, four or five participants who answered
  give the pact of the first three, six give all six.

```php
// Two participants answered "dark" and "light":
$pact->tally('tone');      // ['light' => 0, 'mixed' => 0, 'serious' => 0, 'dark' => 0]
$pact->respondents;        // 0

// A third answers "dark":
$pact->tally('tone');      // ['light' => 1, 'mixed' => 0, 'serious' => 0, 'dark' => 2]

// A fourth answers "serious": the tally waits for two more.
$pact->tally('tone');      // ['light' => 1, 'mixed' => 0, 'serious' => 0, 'dark' => 2]
```

One more answer thus never changes the pact by the answers of one person alone. Answers to none of the template's
questions are not an answer and take no place in a group. The application chooses the threshold, as
`TableSafety`'s `answersThreshold`; `Pact::of()` takes it too.

The pact never filters content: what must keep a theme out of the game belongs in the
[lines and veils and the consent checklist](before-play.md), which apply every answer.
