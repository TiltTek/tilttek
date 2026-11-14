# Questions and scoring

## Questions

Every question has an identifier, unique in its quiz, a prompt of 1 to 1 000 bytes, its points, 0 to 10 000 and 100 by
default, and an optional time limit of 5 to 600 seconds. A question without a time limit takes the quiz's; a quiz
without one leaves its questions untimed.

| Question | Answer | Right when |
|---|---|---|
| `SingleChoiceQuestion` | `ChoiceAnswer` | It picks the right choice alone, among 2 to 12 |
| `SingleChoiceQuestion::trueOrFalse()` | `ChoiceAnswer` of `true` or `false` | It picks the truth |
| `MultipleChoiceQuestion` | `ChoiceAnswer` | It picks the right choices exactly; with partial credit, see below |
| `OrderingQuestion` | `OrderAnswer` | Every item is in its place; with partial credit, each item in place earns its share |
| `TypedAnswerQuestion` | `TextAnswer` | The words, folded, match one of 1 to 20 accepted answers |
| `NumericQuestion` | `NumberAnswer` | The whole number is within the tolerance of the answer, both ends included |

A multiple-choice question with partial credit gives each right choice picked its share of the points and takes one
share back for each wrong choice picked, never below nothing. A typed answer and the accepted ones are folded before
they are compared: lower case, spaces trimmed and runs of spaces made one, and accents removed unless the question
keeps them (`keepAccents: true`). The items of an ordering question are given in the right order; the application
shuffles them when it shows them.

A question refuses an answer of another kind with `InvalidQuiz`. An application adds a kind of its own by implementing
`Contract\Question`; its grade must depend on the answer alone.

## Grades

`Grade` is how right an answer is, in thousandths: 1000 is right, 0 is wrong, and partial credit lies between. Whole
numbers keep every score exact: the points an answer earns are the question's points times the grade, divided by
1 000 and rounded down.

## The timer

`QuestionTimer` holds the moment a question was shown and its time limit. It counts milliseconds between two moments of
the clock the application gives, so a test moves time by moving its clock. A timed question runs out after its last
millisecond: an answer at exactly 20 seconds of a 20-second question is in time, one millisecond later is not.

At one's own pace, an answer after the time scores nothing and the play moves on; live, it is refused, and the closing
of the question records the player as timed out.

## Accuracy and speed

`Scoring` is the quiz's rule:

| Mode | Points of a right answer |
|---|---|
| `Scoring::accuracy()`, the default | The question's points |
| `Scoring::speed($bonus = 50)` | The question's points, plus up to `$bonus` percent of them, falling in a straight line from the moment shown to nothing at the end of the time |

The bonus goes to a fully right answer to a timed question only; it is 0 to 100 percent. `Quiz::maxScore()` is the most
a quiz can give: every answer right, and instant in speed mode.

## Composing a quiz from a bank

`Quiz::fromSource()` asks a `Contract\QuestionSource` for up to the count of questions of a bank, and keeps no more
than asked. `Store\InMemoryQuestionSource` holds banks in memory; an assessment library or a file of questions under a
free licence plugs in through the same contract.
