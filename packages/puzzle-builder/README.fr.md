<!-- phil-dev source-sha256=9f050a26003ab91057793795ef2476abc0692e60bcb410946f81a808bb3b28d3 -->
# tilttek/puzzle-builder

**Des énigmes pour un scénario, vérifiées sur le serveur : réponses normalisées, réponses partielles, propositions
bornées, indices par paliers et graphe de déverrouillage validé.**

Un scénario contient ses énigmes : une devinette, un chiffre, un code à trouver. Chaque énigme accepte une ou plusieurs
réponses, lues comme les joueurs les tapent — `Le Chêne!`, `le chene` et `LECHENE` sont une seule réponse — et peut dire
aux joueurs qu'une proposition est une étape en chemin : « Quels douze ? ». Les propositions d'une équipe sur une énigme
sont comptées sur une période, pour qu'un script ne puisse pas essayer tous les mots. Les indices viennent par paliers,
le premier un coup de pouce, le dernier près de la réponse, demandés par les joueurs ou envoyés par l'animateur. Une
énigme s'ouvre quand celles qu'elle exige sont résolues, quand assez d'énigmes sont résolues, ou quand l'animateur la
distribue ; le graphe est vérifié à la création du scénario : aucun cycle, et aucune énigme qu'une partie ne peut
atteindre.

Les réponses restent sur le serveur. Une proposition y est vérifiée, comparée en temps constant, et rien de ce qui est
envoyé au navigateur — la vue d'une partie, les événements — ne contient une réponse ni rien qui en vérifie une.

C'est une bibliothèque PHP ordinaire qui ne garde rien : chaque coup prend l'avancement de l'équipe et donne
l'avancement à stocker ensuite, et ses événements permettent à une application, ou à un bundle, de pousser les coups
vers les écrans des joueurs. C'est le moteur d'énigmes des escape games et des soirées de meurtre et mystère, qui y
ajoutent leurs propres règles.

## État

| Partie | État |
|---|---|
| `Scenario`, `Puzzle`, `Unlock`, `Hint` : les énigmes et leur graphe, vérifiés ; lus depuis des valeurs simples ou du JSON | Implémentés et testés |
| `Answer\AnswerKey`, `Answer\AnswerNormalizer`, `Answer\Verdict` : réponses acceptées et partielles, normalisées | Implémentés et testés |
| `PuzzleEngine` : les propositions, les indices, les énigmes ouvertes ou sautées par l'animateur, l'état de chaque énigme et la vue | Implémenté et testé |
| `Progress`, `Record\Solved`, `Record\HintTaken` : l'état d'une partie, écrit et relu en valeurs simples | Implémentés et testés |
| `Contract\GuessLimiter`, avec `Limiter\RateLimiterGuessLimiter` et `Limiter\InMemoryGuessLimiter` | Implémentés et testés |
| `View\ScenarioView`, `View\PuzzleView` : ce que voient les joueurs, sans réponses | Implémentés et testés |
| Les événements envoyés par PSR-14, qui nomment des identifiants seulement | Implémentés et testés |
| Les exceptions, sous une seule interface `PuzzleBuilderException` | Implémentées et testées |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'installation et le branchement. |
| [Utilisation](doc/fr/usage.md) | Les scénarios, les réponses, les propositions, les indices, les déverrouillages, les coups de l'animateur, le stockage d'une partie et la vue. |
| [Sécurité](doc/fr/security.md) | Pourquoi les réponses restent sur le serveur, la limite de propositions, et les bornes de chaque entrée. |
| [Moteurs et garde-fous](doc/fr/drives-and-safeguards.md) | La place de la bibliothèque dans la grille des moteurs de Tilttek, et les règles qu'elle respecte. |
| [Points d'extension](doc/fr/extension-points.md) | Les vérifications de réponses, les limiteurs, les événements, et les jeux construits sur le moteur. |
| [Tests](doc/fr/testing.md) | Les tests, la cible de fuzzing et les bancs d'essai de la bibliothèque. |
| [Référence](doc/fr/reference.md) | Chaque type de la bibliothèque, avec son rôle, et ses exceptions. |

## Démarrage rapide

```php
use Phil\Resource\ResourceReference;
use Tilttek\PuzzleBuilder\Limiter\InMemoryGuessLimiter;
use Tilttek\PuzzleBuilder\Progress;
use Tilttek\PuzzleBuilder\PuzzleEngine;
use Tilttek\PuzzleBuilder\Scenario;

$scenario = Scenario::fromJson('{"id": "manor", "finale": "vault", "puzzles": [
    {"id": "clock", "title": "The clock", "answers": ["Midnight"], "partials": {"twelve": "Which twelve?"},
     "hints": [{"text": "Look at the hands"}, {"text": "Both point up"}]},
    {"id": "painting", "title": "The painting", "answers": ["Le Chêne"]},
    {"id": "vault", "title": "The vault", "answers": ["4521"], "unlock": {"requires": ["clock", "painting"]}}
]}');
$engine = new PuzzleEngine(new InMemoryGuessLimiter(10, 60), $eventDispatcher);
$team = ResourceReference::of('team', '7');

$result = $engine->guess($scenario, Progress::start(), $team, 'clock', 'twelve');
$result->outcome;   // GuessOutcome::Partial
$result->message;   // "Which twelve?"
$result = $engine->guess($scenario, $result->progress, $team, 'clock', 'MIDNIGHT');
$result->solved();  // true
$engine->view($scenario, $result->progress)->toArray();   // for the browser: statuses, hints, no answer
$result->progress->toArray();                             // to store until the next move
```

## Prérequis

PHP 8.5 ou une version plus récente avec `intl` et `mbstring`, `phil/resource` 0.1, `psr/clock`,
`psr/event-dispatcher` et `symfony/clock` ; `symfony/rate-limiter` pour compter les propositions avec ses limiteurs.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. Voir [LICENSE](LICENSE).
