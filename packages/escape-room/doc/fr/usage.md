<!-- phil-dev source-sha256=354e40743d995ed7bde93faf864ee8a4957c19fa4ba7ed74813ad7ba932a21d8 -->
# Utilisation

## Une salle

Une `Room` a :

| Champ | Sens | Valeur par défaut | Bornes |
|---|---|---|---|
| `id`, `title` | Son identifiant et le titre affiché | — | 1 à 64 caractères parmi `a-z`, `0-9`, `-` et `_` à l'intérieur, en commençant par une lettre ; titre de 1 à 200 caractères |
| `scenario` | Ses énigmes, un `Tilttek\PuzzleBuilder\Scenario` | — | — |
| `duration` | Le temps qu'a une équipe, en secondes | 3 600 | 60 à 86 400 |
| `hintPenalty` | Les secondes qu'un indice sans pénalité propre ajoute au temps final | 0 | 0 à 3 600 |
| `skipPenalty` | Les secondes qu'une énigme sautée par l'animateur ajoute au temps final | 0 | 0 à 3 600 |
| `minPlayers`, `maxPlayers` | La plus petite et la plus grande équipe ; la plus grande est le nombre de places d'une séance | 2, 6 | 1 à 100 |

`Room::chain()` fait la salle la plus courante : ses énigmes s'ouvrent l'une après l'autre, la première dès le départ,
chaque suivante une fois la précédente résolue, et la dernière est la finale. Une salle d'un autre graphe — deux pistes
jouées en même temps qui se rejoignent à la chambre forte, une énigme que l'animateur distribue — prend un `Scenario`
construit comme le décrit `tilttek/puzzle-builder`.

```php
$room = Room::chain('crypt', 'The crypt', [$candle, $crest, $tomb], duration: 3_600, hintPenalty: 120, skipPenalty: 300);
```

## Une partie

`EscapeRooms::start()` lance la minuterie pour une équipe, nommée par un identifiant stable — jamais une adresse
courriel —, avec la séance qu'elle a réservée s'il y en a une. Chaque coup prend ensuite la salle et la `Game` de
l'équipe, et donne la partie après lui. Une équipe ou un animateur du genre `erased` (`EscapeRooms::ERASED_KIND`) est
refusé avec `InvalidRoom` : seul un effacement écrit une telle référence, à la place d'un membre :

| Coup | Ce qu'il fait |
|---|---|
| `guess($room, $game, $puzzle, $answer)` | Vérifie une proposition ; résoudre la finale termine la partie, par une évasion |
| `requestHint($room, $game, $puzzle)` | Révèle l'indice suivant, demandé par les joueurs |
| `pushHint($room, $game, $puzzle, $host)` | Révèle l'indice suivant, envoyé par l'animateur à l'écran d'indices |
| `skip($room, $game, $puzzle, $host)` | Marque une énigme résolue pour une équipe bloquée |
| `open($room, $game, $puzzle)` | Ouvre une énigme que l'animateur distribue |
| `pause($room, $game, $host)`, `resume($room, $game, $host)` | Arrête la minuterie et la relance |
| `message($room, $game, $host, $text)`, `clearScreen($room, $game, $host)` | Pilote l'écran d'indices |
| `expire($room, $game)` | Termine une partie dont le temps est écoulé |
| `abandon($room, $game)` | Termine une partie avant son temps |
| `consent($room, $game, $public)` | Enregistre si l'équipe montre son résultat sur le classement public |

Une proposition, un indice, un saut ou une ouverture est refusé avec `GameRefused` tant que la partie est en pause,
une fois son temps écoulé, ou une fois qu'elle est terminée ; les règles des énigmes — verrouillée, résolue, aucun
indice restant, la limite de propositions — tiennent toujours, avec les exceptions de `tilttek/puzzle-builder`. Une
partie terminée n'accepte aucun coup sauf `consent()`.

## La minuterie

La bibliothèque ne garde aucune horloge en marche : `EscapeRooms::timer()` lit l'horloge à chaque fois et donne un
`Timer`, en secondes entières :

| Champ | Sens |
|---|---|
| `played` | Les secondes jouées, pauses exclues, dans la limite de la durée |
| `remaining` | Les secondes qui restent sur la minuterie ; `timeUp()` une fois qu'elle atteint 0 |
| `penalty` | Les secondes qu'ajoutent les indices et les énigmes sautées |
| `hints`, `skips` | Les indices révélés, demandés ou envoyés, et les énigmes sautées |
| `paused` | Si la minuterie est arrêtée |
| `final()` | Le temps qui classe l'équipe : `played` plus `penalty` |

Une partie en pause, ou terminée, lit la minuterie à sa pause ou à sa fin. Une partie dont le temps est écoulé reste en
cours jusqu'à ce que `expire()` la termine, hors du temps : une tâche planifiée l'appelle quand la minuterie devrait
atteindre 0, ou l'application l'appelle quand un coup est refusé avec `GameRefused::timeUp`. La fin qu'il enregistre est
le moment où la minuterie a atteint 0, pauses comptées, quel que soit le moment où `expire()` est appelé.

## Indices et pénalités

Un indice coûte sa propre `penalty` quand le scénario lui en donne une, et sinon le `hintPenalty` de la salle ; une
énigme sautée coûte le `skipPenalty` de la salle. Les indices demandés et envoyés coûtent la même chose. Les pénalités
s'ajoutent au temps final, jamais ne se retranchent de la minuterie : une équipe n'est jamais plus près de perdre pour
avoir demandé. `RoomHint::$penalty` dit ce qu'a coûté l'indice révélé, pour que l'écran d'indices le montre.

## La console de l'animateur et l'écran d'indices

Les coups de la console sont des événements, qu'un bundle pousse vers l'écran d'indices dans la salle :

| Événement | L'écran d'indices |
|---|---|
| `Event\GameStarted` | Lance la minuterie |
| `Event\HintRequested`, `Event\HintPushed` | Montre l'indice — lu dans le scénario par son énigme et son palier — et sa pénalité |
| `Event\ScreenMessageSent` | Montre le message de l'animateur, 280 caractères au plus |
| `Event\ScreenCleared` | Revient à la minuterie seule |
| `Event\GamePaused`, `Event\GameResumed` | Arrête et relance la minuterie, en le disant |
| `Event\PuzzleSkipped` | Dit que l'énigme a été sautée, et sa pénalité |
| `Event\GameFinished` | La fin : évasion, hors du temps ou abandon, avec le temps final |
| `Event\ConsentChanged` | Rien : le classement lit le consentement |

Les événements de `tilttek/puzzle-builder` — une énigme résolue, ouverte, une proposition fausse ou partielle — sont
aussi envoyés par le moteur, nommés par le scénario, dont l'identifiant est celui de la salle dans une salle enchaînée.

## Stocker une partie

`Game::toArray()` donne des valeurs simples — la salle, l'équipe, la séance, le départ, la pause, les secondes en pause,
la fin et son issue, l'avancement des énigmes et le consentement —, chaque heure en UTC à la microseconde ;
`Game::fromArray()` les relit, vérifiées d'après la salle, et refuse avec `InvalidRoom` ce qui ne se relit pas. Deux
coups en même temps sur la même partie lisent les mêmes valeurs : l'application stocke la partie avec une version et
refuse la seconde écriture.

## Résultats et classement

`EscapeRooms::result()` donne le `RoomResult` d'une équipe qui s'est échappée : son temps final, le temps joué, les
pénalités, les indices, les sauts, le moment de son évasion et si elle a consenti à le montrer. `Leaderboard::rank()`
classe les résultats d'une salle :

- seulement ceux dont l'équipe a consenti — une équipe qui ne l'a pas fait n'est jamais montrée, et le classement est
  privé par défaut ;
- par temps final, puis par moins d'indices, puis par qui s'est échappé le premier ;
- les équipes au même temps final et aux mêmes indices partagent une position ;
- 10 places par défaut, 1 000 au plus.

```php
$standings = Leaderboard::rank('crypt', $results, 20);
$standings[0]->position;              // 1
$standings[0]->result->finalSeconds;  // shown with the hints beside it
```

Effacer une équipe, à sa demande, efface ses parties et ses résultats du stockage de l'application ; la bibliothèque
n'en garde aucun.

## Célébrer

Une évasion est le moment du composant de célébration de PHIL UX, sur `Event\GameFinished` avec
`GameOutcome::Escaped` ; il respecte `prefers-reduced-motion`.
