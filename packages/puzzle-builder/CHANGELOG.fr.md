<!-- phil-dev source-sha256=4f22495ca1d4354925cd46b5878b1743471209df0569ce5a8041e76e74bcfaa4 -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `tilttek/puzzle-builder` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.1.1-alpha - 2026-11-20

### Sécurité

- `Progress::fromArray()` refuse avec `InvalidProgress` des coups qui n'auraient pas pu être joués : des énigmes
  résolues hors de l'ordre de leurs heures, et une énigme résolue, un indice révélé ou une énigme tentée alors qu'elle
  était verrouillée — avant que l'animateur l'ouvre, ou avant que soient résolues les énigmes qui l'ouvrent. Une
  progression écrite par le moteur se relit comme avant.

## 0.1.0-alpha - 2026-11-13

### Ajouté

- `Scenario`, `Puzzle`, `Unlock` et `Hint` : de 1 à 200 énigmes avec un identifiant et un titre, une vérification des
  réponses, jusqu'à 10 paliers d'indices avec une pénalité en secondes, et un déverrouillage par les énigmes requises,
  par un nombre d'entre elles, par les résolutions du scénario, ou par l'animateur ; une finale, ou chaque énigme,
  termine le scénario ; le graphe vérifié à la création du scénario — chaque prérequis présent, aucun cycle, nommé avec
  son chemin, et chaque énigme accessible —, avec un ordre dans lequel une partie peut le résoudre ; lu à partir de
  valeurs simples ou de JSON dans les limites de 256 Kio et de 8 niveaux, un champ inconnu refusé.
- `Answer\AnswerKey`, `Answer\AnswerNormalizer` et `Answer\Verdict` : de 1 à 10 réponses acceptées et jusqu'à 20
  réponses partielles avec leur message, normalisées sur le serveur — forme de compatibilité, casse, caractères
  invisibles, accents, ponctuation et symboles, espaces, les trois derniers gardés chacun sur demande —, comparées en
  temps constant.
- `PuzzleEngine` : les propositions des énigmes ouvertes vérifiées sur le serveur et comptées sous le scénario, l'équipe
  et l'énigme, une proposition au-delà de la limite non vérifiée et accompagnée du temps avant de réessayer ; des
  indices révélés palier après palier, demandés ou poussés ; des énigmes que l'animateur ouvre ou saute ; le statut de
  chaque énigme ; les énigmes qu'ouvre une résolution et si elle termine le scénario ; une équipe nommée par une adresse
  courriel refusée.
- `Progress`, `Record\Solved` et `Record\HintTaken` : l'état d'une partie gardé par l'application, écrit en valeurs
  simples avec chaque heure en UTC à la microseconde, et relu en le vérifiant contre le scénario.
- `View\ScenarioView` et `View\PuzzleView` : ce que voient les joueurs, dans l'ordre du scénario, sans les réponses ni
  le titre et les indices d'une énigme verrouillée.
- `Contract\AnswerCheck` et `Contract\GuessLimiter`, avec `Limiter\RateLimiterGuessLimiter` sur `symfony/rate-limiter`,
  suggéré, `Limiter\InMemoryGuessLimiter` dans une fenêtre fixe, et `Limiter\Allowance`.
- `Event\PuzzleSolved`, `Event\PartialAnswerGiven`, `Event\WrongAnswerGiven`, `Event\GuessThrottled`,
  `Event\HintRevealed`, `Event\PuzzleOpened` et `Event\ScenarioCompleted`, distribués par PSR-14 et qui ne nomment que
  des identifiants.
- `Exception\PuzzleBuilderException`, avec `InvalidScenario`, `InvalidAnswer`, `InvalidProgress` et `PuzzleRefused`.
- Une cible de fuzzing pour les scénarios, et des bancs d'essai d'un scénario de 200 énigmes lu, tenté et affiché.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
