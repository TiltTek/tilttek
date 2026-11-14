# Installation

## Requirements

- PHP 8.5 or later, with `ext-intl` and `ext-mbstring`, which fold typed answers before they are compared.
- `psr/clock`: the clock every timer reads.
- `psr/event-dispatcher`: the dispatcher of the library's events.
- Twig and Stimulus only for the result template and its controller; see [Celebrations](celebrations.md).
- `symfony/mercure` only to push the moves of a live session; see [Live updates](live-updates.md).

## Installing

```sh
composer require tilttek/quiz
```

## Wiring the library

```php
use Tilttek\Quiz\{LiveQuizzes, SelfPacedQuizzes};
use Tilttek\Quiz\SelfPaced\{Feedback, PerfectScoreRule, Replays};

$selfPaced = new SelfPacedQuizzes(
    $catalog,            // Contract\QuizCatalog: where the quizzes are read
    $plays,              // Contract\PlayStore: the plays at one's own pace
    $dispatcher,         // a PSR-14 dispatcher
    $clock,              // a PSR-20 clock
    Replays::Practice,                        // a member's later plays of a quiz: Practice, the default, or Refused
    Feedback::AfterPlay,                      // when the results show: AfterPlay, the default, or AfterEachAnswer
    PerfectScoreRule::OncePerQuizAndMember,   // or EveryPerfectPlay
);

$live = new LiveQuizzes(
    $catalog,
    $sessions,           // Contract\LiveSessionStore: the live sessions
    $dispatcher,
    $clock,
    $pseudonymFilter,    // Contract\PseudonymFilter, such as Pseudonym\WordListFilter::defaults(), or null
    $randomizer,         // the source of the PINs; the system's secure source by default
);
```

`SelfPacedQuizzes` and `LiveQuizzes` keep nothing between two calls, so one instance serves every request of a worker.
The stores in memory, `Store\InMemoryQuizCatalog`, `Store\InMemoryPlayStore` and `Store\InMemoryLiveSessionStore`,
keep everything in the process, for tests and scripts.
