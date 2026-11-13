<!-- phil-dev source-sha256=1ca12f112ee5512cac870f21c70103e882ed51d9878df3f76d2b2b9ce171db04 -->
# Tables, coups et tours

## Ouvrir une table

```php
$engine = new Engine();
$table = $engine->start($game, 'table:42', ['seat:ann', 'seat:bob'], ['target' => 30]);

$table->log;     // the truth: game, version, seed, seats, options, moves
$table->state;   // the state after the setup
```

Les sièges sont des identifiants que l'application donne, dans l'ordre de la table : une lettre ASCII, puis des
lettres, des chiffres, `:`, `.`, `_` ou `-`, 128 caractères au plus — `seat:3`, `member:44` —, jamais un nombre seul,
pour qu'un siège reste un texte comme clé d'un tableau associatif JSON. Leur nombre doit se situer dans les bornes du
jeu, et aucun n'est donné deux fois. Le germe est tiré de la source sécurisée du système, sauf si l'appelant en donne
un.

## Les coups

Un `Move` a un type, le siège qui le fait, et une charge utile de valeurs simples — des textes, des nombres, des
booléens, null et des tableaux de ceux-ci, sur 8 niveaux de profondeur et 16 384 octets de JSON au plus. Un coup sans
siège est l'application qui agit pour la table — un minuteur qui termine un tour — : il n'est pas tenu aux tours, mais
l'`allows()` du jeu décide quand même.

```php
$table = $engine->play($game, $table, new Move('race.step', 'seat:ann', ['steps' => 2]));
```

Le moteur refuse avec `IllegalMove` un coup à une table dont la partie est terminée, d'un siège qui n'est pas à la
table, hors du tour du siège, ou que le jeu refuse. Les coups propres au moteur sont :

| Coup | Charge utile | Ce qu'il fait |
|---|---|---|
| `Move::endTurn($seat)` | — | Termine le tour du siège ; dans une ronde simultanée, la table elle-même termine la ronde |
| `Move::transition($name, $seat)` | `{transition}` | Change la phase ([Les phases et la machine à états](phases.md)) |
| `Move::initiative($scores, $seat)` | `{scores: {seat: int}}` | Fixe l'initiative de sièges |
| `Move::delay($seat, $after)` | `{after}` | Place le siège qui agit après un siège qui agit plus tard |

`engine.end-turn` et `engine.delay` sont tenus aux tours ; `engine.transition` et `engine.initiative` ne le sont pas,
et le jeu décide qui peut les faire.

## Les tours

`$state->turn` est un `Turn` : son genre, la ronde à partir de 1, l'ordre des sièges, le siège qui agit, les sièges
qui doivent encore agir dans une ronde simultanée, et les coups faits dans le tour. `toAct()` liste les sièges qui
peuvent agir maintenant, et `allows()` dit si l'un d'eux le peut.

- Dans une phase libre, chaque siège peut agir et il n'y a aucun tour à terminer.
- Dans une phase séquentielle ou par initiative, le tour passe au siège suivant dans l'ordre, et après le dernier au
  premier d'une nouvelle ronde.
- Dans une phase simultanée, chaque siège termine son propre tour une fois ; la ronde se termine quand il n'en reste
  aucun.
- Une phase avec `movesPerTurn` termine d'elle-même le tour d'un siège après ce nombre de coups du jeu.
- Un type de coup que le jeu déclare hors tour peut être fait par n'importe quel siège à tout moment, et ne compte pas
  dans le tour.

## Initiative et retards

`engine.initiative` fusionne ses scores dans `$state->initiative`, conservée d'une phase à l'autre. Une phase par
initiative ordonne les sièges à partir du score le plus haut ; des scores égaux gardent l'ordre de la table, et les
sièges sans score viennent en dernier, dans l'ordre de la table. Fixer l'initiative pendant une telle phase ordonne de
nouveau la ronde à partir de son premier siège, le numéro de ronde étant conservé.

Un siège retarde son tour avec `engine.delay` après un siège qui agit plus tard dans la ronde : il se place là, et le
siège qui le suivait agit.
