<!-- phil-dev source-sha256=eb5e7756c9c6f582af4ea88cdca3d57db8ab53c5f3728848847bdcb22ef9c493 -->
# Référence

| Type | Rôle |
|---|---|
| `Scenario` | Les énigmes et la finale d'un scénario, son graphe vérifié ; lu depuis des valeurs simples ou du JSON |
| `Puzzle` | Une énigme : identifiant, titre, vérification des réponses, indices, déverrouillage |
| `Unlock` | Quand une énigme s'ouvre : énigmes exigées, combien d'entre elles, résolutions du scénario, ou par l'animateur |
| `Hint` | Un palier d'indices : son texte et sa pénalité en secondes |
| `PuzzleEngine` | Les propositions, les indices, les énigmes ouvertes ou sautées par l'animateur, les états et la vue |
| `Progress` | Où en est une équipe : résolutions, énigmes ouvertes, indices, propositions ; écrit et relu en valeurs simples |
| `PuzzleStatus` | `Locked`, `Open` ou `Solved` |
| `GuessOutcome` | `Correct`, `Partial`, `Wrong` ou `Throttled` |
| `GuessResult` | Ce qu'a donné une proposition ou un saut, et l'avancement après lui |
| `HintResult` | L'indice révélé, son palier, les paliers restants, et l'avancement après lui |
| `Answer\AnswerKey` | Les réponses acceptées et partielles, normalisées, comparées en temps constant |
| `Answer\AnswerNormalizer` | La façon dont réponses et propositions sont normalisées : casse, accents, ponctuation, espaces |
| `Answer\Verdict` | Ce qu'une vérification dit d'une proposition : correcte, partielle avec son message, ou fausse |
| `Record\Solved` | Une énigme résolue, à une heure, peut-être sautée |
| `Record\HintTaken` | Un palier d'indices révélé, à une heure, demandé ou envoyé |
| `View\ScenarioView` | Ce que les joueurs voient de leur partie |
| `View\PuzzleView` | Ce que les joueurs voient d'une énigme |
| `Limiter\Allowance` | Si une proposition est vérifiée maintenant, et quand elle le sera |
| `Limiter\RateLimiterGuessLimiter` | Propositions comptées par un limiteur de `symfony/rate-limiter` |
| `Limiter\InMemoryGuessLimiter` | Propositions comptées dans une fenêtre fixe, en mémoire |
| `Contract\AnswerCheck` | La façon dont les propositions d'une énigme sont vérifiées |
| `Contract\GuessLimiter` | Combien de propositions une équipe fait sur une énigme dans une période |
| `Event\PuzzleSolved` | Une énigme résolue ou sautée |
| `Event\PartialAnswerGiven` | Une réponse partielle |
| `Event\WrongAnswerGiven` | Une mauvaise réponse |
| `Event\GuessThrottled` | Une proposition au-delà de la limite |
| `Event\HintRevealed` | Un indice révélé |
| `Event\PuzzleOpened` | Une énigme ouverte par une résolution ou par l'animateur |
| `Event\ScenarioCompleted` | Un scénario terminé |

`Instant` est interne : la seule façon dont la bibliothèque écrit une heure, en UTC.

## Exceptions

| Exception | Lancée quand |
|---|---|
| `Exception\PuzzleBuilderException` | L'interface de toutes les exceptions de la bibliothèque |
| `Exception\InvalidScenario` | Un scénario, une énigme, une clé de réponses, un indice, un déverrouillage ou un limiteur que la bibliothèque refuse |
| `Exception\InvalidAnswer` | Une proposition qui n'est pas en UTF-8, vide une fois normalisée, ou de plus de 200 caractères |
| `Exception\InvalidProgress` | Un avancement stocké qui ne se relit pas d'après son scénario |
| `Exception\PuzzleRefused` | Un coup que la partie n'autorise pas : une énigme inconnue, verrouillée, résolue, déjà ouverte ou sans indice restant ; une équipe nommée par une adresse courriel |

Rien ne change quand une exception est lancée. Chaque message dit ce qui était attendu, comme
`The puzzle "vault" is still locked.`
