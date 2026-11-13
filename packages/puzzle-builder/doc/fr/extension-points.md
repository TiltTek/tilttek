<!-- phil-dev source-sha256=2cd6f44f8257a67f1e449c9c2f125b92da0638988b645f3c78e2226c3484be31 -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension sont stables seulement à partir de sa version 1.0. D'ici là, une
version mineure peut les changer, et son CHANGELOG dit comment.

| Point | Ce qu'une application donne | Fourni |
|---|---|---|
| `Contract\AnswerCheck` | La façon dont les propositions d'une énigme sont vérifiées | `Answer\AnswerKey` |
| `Contract\GuessLimiter` | Combien de propositions une équipe fait sur une énigme dans une période | `Limiter\RateLimiterGuessLimiter`, `Limiter\InMemoryGuessLimiter` |
| `Psr\EventDispatcher\EventDispatcherInterface` | Où vont les événements | aucun : rien n'est envoyé |
| `Psr\Clock\ClockInterface` | L'heure de chaque coup | `Symfony\Component\Clock\NativeClock` |

Chaque point est passé à un constructeur ; `PuzzleEngine` ne garde rien entre deux appels.

## Vérifier les réponses autrement

Une `Contract\AnswerCheck` lit une proposition et donne un `Answer\Verdict` : `Verdict::correct()`,
`Verdict::partial($message)` ou `Verdict::wrong()`. Une accusation qui nomme un suspect, une arme et une pièce, lue d'un
formulaire comme `"butler|candlestick|library"`, est une vérification ; un code dont les chiffres peuvent venir dans
n'importe quel ordre en est une autre. La vérification :

- garde sa réponse pour elle, et on ne la lui demande jamais ;
- lance `Exception\InvalidAnswer` pour une proposition qu'elle ne lit pas, ce qui ne change rien à l'avancement ;
- compare en temps constant quand elle compare des textes, avec `hash_equals()`.

## Compter les propositions autrement

Un `Contract\GuessLimiter` compte une tentative par appel sous une clé et répond par une `Limiter\Allowance` :
`Allowance::granted()`, ou `Allowance::refused($retryAfter)`. Ses comptes vivent là où chaque *worker* les lit. Un
limiteur qui borne aussi une équipe sur toutes les énigmes construit sa propre clé à partir de celle qu'on lui donne,
soit `scenario|team|puzzle` (`PuzzleEngine::key()`).

## Les événements

Chaque événement est envoyé une fois son coup joué, et nomme des identifiants seulement — jamais une proposition ni une
réponse :

| Événement | Porte |
|---|---|
| `Event\PuzzleSolved` | Le scénario, l'équipe, l'énigme, l'heure, et si l'animateur l'a sautée |
| `Event\PartialAnswerGiven` | Le scénario, l'équipe, l'énigme et l'heure |
| `Event\WrongAnswerGiven` | Le scénario, l'équipe, l'énigme et l'heure |
| `Event\GuessThrottled` | Le scénario, l'équipe, l'énigme et le moment où l'équipe pourra proposer de nouveau |
| `Event\HintRevealed` | Le scénario, l'équipe, l'énigme, l'heure, le palier, et si l'animateur l'a envoyé |
| `Event\PuzzleOpened` | Le scénario, l'équipe, l'énigme, l'heure, et si l'animateur l'a ouverte |
| `Event\ScenarioCompleted` | Le scénario, l'équipe et l'heure, après le `PuzzleSolved` qui le termine |

Un bundle qui pousse les coups vers les écrans d'une équipe par Mercure ou Messenger envoie ces identifiants ; l'écran
relit la vue depuis le serveur.

## Des jeux construits sur le moteur

Le moteur contient ce que partagent tous les jeux d'énigmes, et un jeu ajoute ses propres règles par-dessus, par l'API
publique seulement :

- Un escape game enchaîne ses énigmes avec `Unlock::after()`, met une minuterie autour des coups, et ajoute les
  pénalités des indices à son temps final.
- Une soirée de meurtre et mystère ouvre ses indices acte après acte avec `Unlock::manual()` et `PuzzleEngine::open()`,
  demande l'accusation comme une énigme dont l'`AnswerCheck` lit le suspect, l'arme et le mobile, et se termine avec
  cette énigme comme finale ; ses autres énigmes restent facultatives, puisqu'une finale termine le scénario quoi qu'il
  y ait d'autre de résolu.

Les deux gardent leur propre état à côté du `Progress` et leurs propres événements à côté de ceux du moteur.
