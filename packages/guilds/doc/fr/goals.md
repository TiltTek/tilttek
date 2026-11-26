<!-- phil-dev source-sha256=15340f3dc5e84356fa528b7b058e5e481c2ad5a4fd2859fc0d78fd77fca53438 -->
# La piste de groupe

Une guilde poursuit des objectifs communs sur sa piste de groupe : un objectif est une cible de points que les
contributions de ses membres additionnent, avant une échéance ou sans échéance. `Goals` les gère sur un
`Contract\GoalStore` et demande à `Guilds` qui appartient à la guilde.

Les points sont des compteurs de l'application — parties jouées, pages lues, heures de peinture —, jamais de l'argent.

## Ouvrir un objectif

```php
$goals->open(
    'lanterns-december',                         // the key the application gives
    $guild,
    'Play 200 games in December',
    200,
    $founder,                                    // the founder or an administrator
    new DateTimeImmutable('2027-01-01 00:00:00', new DateTimeZone('America/Toronto')), // or none
);
```

| Valeur | Bornes |
|---|---|
| Clé | 1 à 100 lettres ASCII, chiffres, `_`, `.`, `:` ou `-`, unique d'une guilde à l'autre |
| Nom | 1 à 100 caractères UTF-8, sans caractère de contrôle, sans espace autour |
| Cible | 1 à 1 000 000 000 de points |
| Échéance | après l'ouverture, ou aucune |

Une clé déjà employée est refusée avec `Refusal::GoalExists` ; une guilde qui compte autant d'objectifs ouverts que sa
limite le permet — dix par défaut — en refuse un autre avec `Refusal::TooManyGoals`. Un objectif atteint, fermé ou dont
l'échéance est passée ne compte plus dans cette limite.

## Contribuer

```php
$progress = $goals->contribute('lanterns-december', $member, 3, 'game:9812');
```

Seul un membre de la guilde contribue, sinon `Refusal::NotMember` ; une contribution apporte de 1 à 1 000 000 000 de
points. Sous une clé — 1 à 100 lettres ASCII, chiffres, `_`, `.`, `:` ou `-` —, une contribution refaite ne compte
qu'une fois et retourne la progression de l'objectif, même quand l'objectif est terminé : une partie enregistrée deux
fois apporte ses points une seule fois. Sans clé, chaque appel compte.

La contribution qui atteint ou dépasse la cible atteint l'objectif : son `reachedAt` est fixé, les observateurs
reçoivent un `GoalReached` — l'objectif, la guilde, les points amassés, le moment —, et l'objectif n'accepte plus de
contributions. Un objectif cesse aussi d'en accepter à son échéance, à la seconde près, ou dès que son fondateur ou un
administrateur appelle `close($goal, $by)`. Une contribution à un objectif terminé est refusée avec
`Refusal::GoalOver`.

Un membre qui quitte la guilde garde ses points dans son total ; il ne contribue plus.

## Progression

`progress($goal)` retourne un `GoalProgress` :

| Membre | Contient |
|---|---|
| `goal` | Le `Goal`, avec son `reachedAt` et son `closedAt` |
| `points` | Les points amassés, qui peuvent dépasser la cible |
| `contributors` | Combien de membres ont apporté des points, les membres effacés comptés ensemble comme un seul |
| `percent()` | La part de la cible, de 0 à 100, arrondie à l'entier inférieur |
| `remaining()` | Les points qui manquent encore, 0 une fois la cible atteinte |
| `isReached()` | Si la cible a été atteinte |

`contributed($goal, $member)` est la part propre du membre, pour qu'il la lise. La piste ne classe personne dans la
guilde : rien ne dit qui a apporté le plus.

## Piste de groupe ou quête

La piste de groupe est l'objectif propre de la guilde, alimenté par des points que l'application donne. Un objectif lu
sur l'activité enregistrée des membres — *jouer trois parties*, avec des étapes, des chaînes et des récompenses — est
une quête d'équipe de `tilttek/quests`, à laquelle une guilde se joint comme équipe : voyez
[quêtes et classements](quests-and-leaderboards.md).

## Effacement

`erase($member)` oublie le membre sur chaque contribution qu'il a faite ; les points restent à la guilde, de sorte
qu'un objectif atteint le reste.
