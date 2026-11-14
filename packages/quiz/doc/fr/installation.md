<!-- phil-dev source-sha256=34a706bacdcf77eebeb36d532e367df9c49c216b33ee9d19bfdef34f1c697d6e -->
# Installation

## Prérequis

- PHP 8.5 ou une version plus récente, avec `ext-intl` et `ext-mbstring`, qui plient les réponses saisies avant qu'on
  les compare.
- `psr/clock` : l'horloge que lit chaque minuterie.
- `psr/event-dispatcher` : le répartiteur des événements de la bibliothèque.
- Twig et Stimulus seulement pour le gabarit du résultat et son contrôleur ; voir [Célébrations](celebrations.md).
- `symfony/mercure` seulement pour pousser les gestes d'une session en direct ; voir
  [Mises à jour en direct](live-updates.md).


## Installer

```sh
composer require tilttek/quiz
```

## Brancher la bibliothèque

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

`SelfPacedQuizzes` et `LiveQuizzes` ne gardent rien entre deux appels : une seule instance sert toutes les requêtes d'un
*worker*. Les dépôts en mémoire, `Store\InMemoryQuizCatalog`, `Store\InMemoryPlayStore` et
`Store\InMemoryLiveSessionStore`, gardent tout dans le processus, pour les tests et les scripts.
