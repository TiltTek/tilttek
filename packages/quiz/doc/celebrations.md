# Celebrations

The end of a run is a moment to celebrate: confetti for a quiz completed, fireworks for a perfect score. The library
says when; the page plays it, with the celebration component of the PHIL UX kit or anything else.

## The events

| Event | When | Carries |
|---|---|---|
| `Event\QuizCompleted` | A play is over, or a live session is over, for each of its players | `quiz`, `run`, `player`, `perfect`, `practice` |
| `Event\PerfectScore` | After `QuizCompleted`, for a run with every answer right and in time; at one's own pace, once per quiz and member by default | `quiz`, `run`, `player`, `practice` |
| `Event\LiveQuizFinished` | A live session is over, before the events of its players | `quiz`, `session` |
| `Event\TeamWon` | A live session played by teams is over, for each team at the first rank with a score above 0 | `quiz`, `session`, `team` |
| `Event\GuestInvited` | A live session is over, for each guest | `session`, `guest` |

`run` is the play or the live session. `practice` is true for a play at one's own pace that does not count, the member
having played the quiz before (see [Plays that count, and practice](playing.md#plays-that-count-and-practice)); it is
always false live. A listener that awards points or a badge leaves practice out. Every event carries identifiers only, never a pseudonym: a listener that sends a
Messenger message, awards points or pushes an update reads the rest from the stores. The moves of a live session —
`PlayerJoined`, `QuestionShown`, `QuestionClosed` — are described in [Playing](playing.md#live).

## The result template

`templates/result.html.twig` shows a player's result from a `View\ResultView`: the score out of the most the quiz gives,
the rank of a live run and, in a session played by teams, the rank of the player's team. `SelfPacedQuizzes::result()`
gives the view of a member's play once it may be shown; `ResultView::ofPlay()` shows a play as it stands, whatever the
rule, and is for the application's own screens. Register the folder under a
namespace of the application:

```yaml
# config/packages/twig.yaml
twig:
    paths:
        '%kernel.project_dir%/vendor/tilttek/quiz/templates': TilttekQuiz
```

```php
$view = $selfPaced->result('play-7', 'member:44');   // or ResultView::ofLive($session, $quiz, $player)
```

The block `summary` holds the score, and the block `celebration`, empty by default, is drawn only for a run that is
over. With the kit's celebration:

```twig
{% embed '@TilttekQuiz/result.html.twig' with {result: view} %}
    {% block celebration %}
        <twig:Celebration message="{{ 'quiz.completed'|trans }}" :effect="result.perfect ? 'fireworks' : 'confetti'" :autoplay="true" />
    {% endblock %}
{% endembed %}
```

## The Stimulus controller

The template carries the controller `tilttek--quiz--result`, from the package's `assets/package.json`. Once the run is
over, it dispatches the DOM event `quiz:completed` on the window, then `quiz:perfect` for a perfect run, then
`quiz:team-won` when the player's team is among those `TeamWon` names; their detail holds `quiz`, `run`, `perfect`,
`team`, empty without teams, and `practice`, true for a practice play. It says them once per run in a browser tab, so that coming back to the page does not
play them again; where the browser refuses storage, it says them every time. The kit's recipe plays on them:

```twig
<twig:Celebration message="{{ 'quiz.perfect'|trans }}" effect="fireworks" data-action="quiz:perfect@window->celebration#play" />
<twig:Celebration message="{{ 'quiz.team_won'|trans }}" effect="confetti" data-action="quiz:team-won@window->celebration#play" />
```

The recipe is the kit's own, used as it is: the library adds no component of its own.

## Accessibility

The kit's celebration says its message in a polite live region, draws nothing where motion is reduced, plays no sound
and lasts a few seconds at most. The score is text, in `data` elements a script can read; the words around it are the
application's, in its language.
