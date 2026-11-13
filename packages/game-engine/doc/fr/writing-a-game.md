<!-- phil-dev source-sha256=4f96fb085d165887230302f9536d1f8e1263da3ed35f52d5abf5309252b0f155 -->
# Écrire un jeu

Un jeu implémente `Contract\Game`. Il contient toutes les règles ; le moteur n'en contient aucune. Chaque méthode est
pure : elle lit l'état, le coup et le hasard qu'on lui donne, et rien d'autre — ni horloge, ni stockage, ni service
avec un état —, de sorte que rejouer un journal redonne le même état ([Le journal et le rejeu](log-and-replay.md)).

| Méthode | Ce qu'elle donne |
|---|---|
| `definition()` | La `GameDefinition`, la même à chaque appel |
| `setup()` | Les données du jeu à l'ouverture d'une table |
| `allows()` | Si un coup est permis maintenant, coups du moteur compris |
| `play()` | Les données du jeu après l'un de ses propres coups, ou `IllegalMove` |
| `enter()` | Les données du jeu quand la table entre dans une phase par une transition |
| `outcome()` | Comment la partie s'est terminée, avec ses statistiques, ou null tant qu'elle continue |
| `view()` | Ce qu'un siège voit de l'état, façonné par les préférences de son joueur, ou le public quand le siège est null |

Les données du jeu sont un tableau associatif de valeurs simples que le jeu seul lit et écrit, dans
`GameState::$data`.

## La définition

```php
new GameDefinition(
    'race',
    phases: [new Phase('race', TurnOrder::Sequential, movesPerTurn: 1)],
    transitions: [],
    initial: null,                 // the first phase
    minimumSeats: 2,
    maximumSeats: 4,
    options: [Option::integer('target', 20, 10, 100), Option::choice('track', 'short', ['short', 'long'])],
    outOfTurn: ['race.taunt'],
    undo: true,
    version: '1',
    preferences: [Option::boolean('leader-first', false)],
    statistics: [Statistic::table('rounds'), Statistic::seat('position')],
);
```

| Argument | Ce qu'il déclare |
|---|---|
| `game` | Le nom du jeu, des mots en minuscules joints par `-` ou `_` |
| `phases` | Ses phases, au moins une, chacune avec son ordre de tour et les coups qui terminent un tour |
| `transitions` | Les changements de phase qu'il permet ([Les phases et la machine à états](phases.md)) |
| `initial` | La phase où commence une table ; la première quand il est null |
| `minimumSeats`, `maximumSeats` | Le nombre de sièges d'une table, de 1 à 64 |
| `options` | Les options qu'il offre à ses tables |
| `outOfTurn` | Les types de coups qu'un siège peut faire hors de son tour |
| `undo` | Si le dernier coup peut être repris, quand il n'a rien tiré au hasard |
| `version` | La version de ses règles : un journal écrit sous une autre n'est pas rejoué |
| `preferences` | Les préférences qu'il offre à chaque joueur, qui ne changent que la vue d'un siège |
| `statistics` | Les statistiques que son issue rapporte, pour la table ou pour chaque siège |

Une définition qui ne tient pas ensemble est refusée avec `InvalidGame` : deux phases, options, préférences ou
statistiques de même nom, une transition vers une phase que le jeu n'a pas, deux transitions de même nom quittant une même phase, un coup du moteur
déclaré hors tour.

## Les ordres de tour

| `TurnOrder` | Qui agit |
|---|---|
| `Free` | N'importe qui, à tout moment : il n'y a pas de tours |
| `Sequential` | Un siège après l'autre, dans l'ordre de la table, ronde après ronde |
| `Initiative` | Un siège après l'autre, la plus haute initiative d'abord, ronde après ronde |
| `Simultaneous` | Chaque siège une fois par ronde, dans n'importe quel ordre ; la ronde se termine quand tous ont terminé leur tour |

Une phase à tours peut terminer un tour d'elle-même après 1 à 100 coups du jeu, `movesPerTurn` ; sinon un siège le
termine avec `engine.end-turn` ([Tables, coups et tours](tables-and-moves.md)).

## Les options

Une `Option` est un booléen, un nombre entier entre des bornes, ou un choix parmi des textes, avec sa valeur par
défaut. Les `TableOptions` d'une table sont résolues par l'OptionsResolver de Symfony à son ouverture et au rejeu de
son journal : une option que le jeu n'offre pas, ou une valeur qu'il ne permet pas, est refusée. Un jeu les lit avec
`bool()`, `int()` et `string()` sur `$state->options`.

## Les préférences

Une préférence est une `Option` que le jeu offre à chaque joueur plutôt qu'à chaque table : comment les cartes d'une
main sont triées, si les positions sont listées à partir du meneur, une palette de couleurs pour les daltoniens. Les
`Preferences` d'un siège sont résolues par le même OptionsResolver, à partir des valeurs que son joueur a choisies, et
données à `view()` ; un jeu les lit avec `bool()`, `int()` et `string()`. Elles ne font pas partie du journal et
n'atteignent jamais `play()` : deux joueurs aux préférences différentes partagent le même état, et un rejeu les ignore.
Une préférence change la façon dont un siège voit ce qu'il peut voir, jamais ce qu'il peut voir.

```php
public function view(GameState $state, ?string $seat, Preferences $preferences): array
{
    $data = $this->withoutTheOthersSecrets($state, $seat);

    if ($preferences->bool('leader-first')) {
        arsort($data['positions']);
    }

    return $data;
}
```

## Permettre et jouer des coups

Le moteur vérifie d'abord le siège et le tour, puis interroge `allows()`. Un jeu refuse ici ce que ses règles
interdisent à ce moment — coups du moteur compris : un jeu où seul le premier siège change la phase le dit pour
`engine.transition`. Un type de coup que le jeu ne connaît pas est refusé lui aussi par `allows()`.

`play()` ne reçoit que les coups propres au jeu. Il lit la charge utile avec `$move->int()` et `$move->text()`, tire
son hasard du `Randomness` qu'on lui donne, jamais d'ailleurs, et retourne les nouvelles données, ou lève
`IllegalMove` pour une charge utile que ses règles refusent.

## Vues et secrets

`view()` filtre les données pour un siège : une main de cartes, un rôle caché, un objectif secret. Le moteur n'envoie
jamais les données elles-mêmes à un joueur ; l'éditeur dit aux joueurs que la table a changé, et chacun lit la vue de
son siège.

## Les fins

`outcome()` est lu après chaque coup. Une `Outcome` liste les sièges gagnants — aucun pour une partie nulle ou une
scène qui se termine simplement — et le mot du jeu pour la fin ; dès qu'une table en a une, tout coup est refusé.

## Les statistiques de la fin

Un jeu déclare les statistiques qu'il rapporte à sa fin — `Statistic::table('rounds')`, `Statistic::seat('points')` — et
son issue en donne les valeurs, des nombres entiers, dans une `Statistics` : celles de la table par nom, celles de
chaque siège par siège et par nom. Le moteur les vérifie après chaque coup : une statistique que le jeu ne déclare pas,
ou d'une mauvaise portée, ou les statistiques d'un siège qui n'est pas à la table, est refusée avec `InvalidGame`, une
faute du jeu plutôt que du joueur. Un jeu ne rapporte que ce qu'il compte ; une statistique qu'il omet est lue comme
null.

```php
return Outcome::won(['ann'], 'target-reached', new Statistics(
    ['rounds' => $state->turn->round],
    ['ann' => ['position' => 21], 'bob' => ['position' => 17]],
));
```

Les statistiques font partie de l'état, reconstruit avec lui à partir du journal ; `$state->outcome->statistics->ofSeat('ann',
'position')` en lit une.
