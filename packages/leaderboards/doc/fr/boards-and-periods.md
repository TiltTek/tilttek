<!-- phil-dev source-sha256=b7d5d6f7ed9780b2b42fbb8df768f171c75b35b74052f5830f1ca1ae996cf15a -->
# Classements et périodes

## Un classement

Un `Board` a un nom (de 1 à 64 lettres minuscules, chiffres, `_`, `.` ou `-`), la `Measure` selon laquelle il classe
les membres, le `Schedule` de ses périodes, les verbes et les conditions de contexte des activités qu'il lit, la façon
dont il départage une égalité, le nombre de membres que garde un instantané (de 1 à 1000, 100 par défaut), et ses
options : une ligue, une fenêtre glissante, un classement par objet, un titre et la chance des équipes.

| Mesure | Ce qu'elle classe | Lue à partir de |
|---|---|---|
| `count` | Le nombre de gestes | Le journal |
| `total` | La somme de leurs quantités : points gagnés, pages lues, montant d'une commande | Le journal |
| `best` | La meilleure quantité d'un seul geste : un meilleur score | Le journal |
| `days` | Le nombre de jours distincts où il y a un geste, dans le fuseau horaire du classement : la régularité d'un membre | Le journal |
| `latest` | La dernière valeur rapportée par l'application : un niveau, un solde | L'application |
| `highest` | La plus haute valeur rapportée par l'application : la plus longue série | L'application |

Un classement lu à partir du journal nomme au moins un verbe, sous forme de motifs comme `game.played` ou `game.*`, et
peut exiger que le contexte de l'activité contienne des faits, comme `ranked: true` ; un classement dont la mesure est
rapportée ne nomme ni l'un ni l'autre.

## Périodes

| Période | Code | Commence |
|---|---|---|
| `day` | `2026-11-07` | À minuit dans le fuseau horaire du classement |
| `week` | `2026-w45` | Le lundi à minuit, semaines ISO |
| `month` | `2026-11` | Le premier du mois à minuit |
| `quarter` | `2026-q4` | Le premier janvier, avril, juillet ou octobre |
| `year` | `2026` | Le premier janvier |
| `season` | Le code de la saison | Au début que donne l'application |
| `ever` | `ever` | Ne finit jamais |
| `window` | `window` | Ne finit jamais : les gestes plus anciens que la fenêtre en sortent |

Les périodes du calendrier se suivent sans interruption, lues dans le fuseau horaire du classement : une partie jouée à
03 h UTC le premier novembre compte pour octobre à Toronto. Les saisons sont nommées et datées par l'application, ne se
chevauchent jamais et peuvent laisser du temps entre elles. Une activité entre deux saisons ne compte pour aucune, et un
classement entre deux saisons n'a aucune période en cours.

`Schedule::at()` dit dans quelle période tombe un moment, `named()` quelle période nomme un code (`2026-13` ou
`2026-w54` n'en nomment aucune), et `previous()` quelle période s'est terminée en dernier, pour les instantanés.

## Une fenêtre glissante

Un classement sur une `window` compte les gestes de ses derniers jours, aujourd'hui compris (de 1 à 366, `window: 7`
pour les sept derniers jours), à partir de minuit dans le fuseau horaire du classement : `Board::windowStart()` donne le
premier moment qui compte encore. Il a une seule période, `window`, et ne prend aucun instantané. Les gestes sortent de
la fenêtre à mesure que les jours passent, quand `LeaderboardProjection::slide()` s'exécute (un planificateur l'appelle
peu après minuit) ; voir [Scores et projection](scores-and-projection.md#sliding-windows).

*Actif cinq jours sur les sept derniers* : `['measure' => 'days', 'verbs' => ['lesson.done'], 'period' => 'window',
'window' => 7]`.

## Égalités

Les membres qui ont le même score sont ordonnés, tour à tour :

1. par les critères de départage du classement, au plus deux, chacun dans son sens ;
2. par `Ties::FirstReached` (`first`, la valeur par défaut) : le membre qui a atteint le score en premier se classe
   premier, et chaque membre a son propre rang ; les membres qui l'ont atteint au même moment sont ordonnés par leur
   référence ; ou par `Ties::Shared` (`shared`) : les membres égaux sur le score et sur chaque critère de départage
   partagent un rang, et le suivant en saute autant : 1, 2, 2, 4.

Un `TieBreak` réunit un `Aggregate` des gestes du membre dans la période (leur `count`, le `total`, la plus haute
(`highest`) ou la plus basse (`lowest`) de leurs quantités, ou d'un entier de leur contexte qu'il nomme) et l'ordonne
selon une `Direction` : `desc`, le plus haut d'abord (la valeur par défaut), ou `asc`, le plus bas d'abord. Dans un
classement dont la mesure est rapportée, chaque critère de départage est `reported` : l'application donne ses valeurs
avec le score, `Leaderboards::report()` en prenant une pour chacun.

| Critère de départage | Lit |
|---|---|
| `['measure' => 'count', 'order' => 'asc']` | Le moins de tentatives d'abord, dans un classement du meilleur score |
| `['measure' => 'lowest', 'order' => 'asc', 'context' => 'seconds']` | Le temps le plus court d'abord, lu dans l'entier de contexte `seconds` |
| `['measure' => 'highest']` | Le meilleur geste d'abord, dans un classement du total |
| `['measure' => 'reported', 'order' => 'asc']` | Une valeur que rapporte l'application, la plus basse d'abord |

Un geste sans l'entier que lit un critère de départage (de 0 à 10^15 sous son nom de contexte) ne compte pas dans le
classement. `Ranking` contient l'ordre d'un classement : `Board::$ranking`, que lisent les stockages.

Le moment où un score est atteint est le dernier geste compté pour un `count` ou un `total`, le premier geste qui a
atteint le meilleur pour un `best`, le premier geste du dernier jour pour `days`, et le moment du rapport pour une
mesure rapportée.

## Un classement par objet

Un classement avec `perObject` (`per_object: true`) classe les membres pour chaque objet des activités (chaque jeu,
chaque livre, chaque niveau) : une activité sans objet n'y compte pas, et les classements, les rangs, les instantanés
et les équipes du classement nomment l'objet pour lequel ils sont faits. Les scores sont gardés sous le nom du
classement suivi de `@` et d'une empreinte de l'objet (`Board::key()`) : les stockages ne contiennent donc jamais la
référence de l'objet. Un classement par objet n'a pas de ligue, lit le journal, et ses instantanés sont à l'application
de les prendre, objet par objet ; voir [Classements affichés](standings.md).

## Titres et chance

Un `Title` (un nom et un seuil de 1 à 10^15) est détenu par un membre tant que son score dans le classement, pour la
période en cours, atteint le seuil, et personne ne se classe avant lui pour un titre du `leader` : `regular` à partir
de cinq jours sur les sept derniers, la légende locale d'un jeu. Une `Chance` (de 1 à 10 dés de 2 à 100 faces) ajoute
de la chance au score de chaque équipe du classement. Voir [Équipes et titres](teams-and-titles.md).

## Les classements d'une application

`Boards::fromArray()` les lit tels que les donne une configuration, avec le fuseau horaire des classements qui n'en
nomment aucun :

```php
Boards::fromArray([
    'games-month' => ['measure' => 'count', 'verbs' => ['game.played'], 'context' => ['ranked' => true], 'period' => 'month'],
    'high-score' => ['measure' => 'best', 'verbs' => ['arcade.scored'], 'period' => 'week', 'ties' => 'shared', 'snapshot_size' => 10,
        'tie_breaks' => [['measure' => 'count', 'order' => 'asc']]],
    'summer' => ['measure' => 'total', 'verbs' => ['quest.*'], 'period' => 'season', 'timezone' => 'Europe/Paris',
        'seasons' => [['code' => 'summer-2026', 'start' => '2026-06-21', 'end' => '2026-09-22']]],
    'level' => ['measure' => 'latest'],
    'regular' => ['measure' => 'days', 'verbs' => ['lesson.done'], 'period' => 'window', 'window' => 7,
        'title' => ['name' => 'regular', 'threshold' => 5]],
    'game-records' => ['measure' => 'best', 'verbs' => ['game.scored'], 'per_object' => true],
    'house-cup' => ['measure' => 'total', 'verbs' => ['quest.done'], 'period' => 'month', 'chance' => ['dice' => 2, 'faces' => 6]],
], 'America/Toronto');
```

Un classement porte sur « depuis toujours » à moins de nommer sa période. Sont refusés avec une `InvalidLeaderboard` :
deux classements du même nom, une mesure, une période, une égalité, un critère de départage ou un ordre inconnus, un
fuseau horaire ou une date qui n'en sont pas, plus de deux critères de départage, une fenêtre sur un classement qui ne
porte pas sur une fenêtre (ou aucune sur un classement qui y porte), et un critère de départage rapporté sur un
classement lu à partir du journal.
