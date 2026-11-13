<!-- phil-dev source-sha256=8c1ecbbe26eeade5a36051610d337e8a3ff347d14dcecb89c2ecfbd65f4e70f2 -->
# Le journal et le rejeu

## Le journal est la vérité

Un `GameLog` contient tout ce qu'est une table : l'identifiant de la table, le jeu et la version de ses règles, le
germe, les sièges, les options choisies, et les coups faits, numérotés à partir de 1, chacun avec la mention qu'il a
tiré au hasard ou non. Il contient 100 000 coups au plus. `toArray()` l'écrit en valeurs simples, que JSON conserve, et
`fromArray()` les relit, en refusant un champ du mauvais type.

L'état, un `GameState`, n'est qu'un cache : la phase, le tour, l'initiative, les données du jeu et son issue après les
coups du journal. Rien n'y est gardé que le journal ne puisse reconstruire.

## Le hasard tiré du germe

Chaque étape d'une table tire son hasard d'un `Randomness` dont le germe vient de celui de la table et de la place de
l'étape dans le journal — 0 pour la mise en place, puis le numéro du coup —, par le
`Random\Engine\Xoshiro256StarStar` de PHP. Rejouer un coup tire donc les mêmes valeurs, et le hasard d'un coup ne
dépend pas de ce que les coups précédents ont tiré.

```php
$random->int(1, 6);
$random->pick(['north', 'south']);
$random->shuffle($deck);
$random->randomizer();       // for a library that takes one, as tilttek/dice does
```

Le germe est de 64 chiffres hexadécimaux tirés de la source sécurisée du système à l'ouverture de la table. Qui détient
le journal peut rejouer le hasard de chaque coup : un jeu à information cachée garde donc le journal loin des joueurs,
comme il garde son état, jusqu'à la fin de la partie.

## L'engagement sur le germe

`GameLog::commitment()` est l'empreinte SHA-256 du germe. Elle ne dit rien des tirages à venir : on peut donc la
montrer aux joueurs à l'ouverture de la table ; une fois la partie terminée, on leur montre le journal, germe compris,
et chacun vérifie que chaque tirage venait du germe engagé :

```php
$commitment = $log->commitment();                    // shown at the opening

$state = $engine->verify($game, $log, $commitment);  // once the game is over
```

`verify()` compare l'empreinte du germe du journal avec l'engagement, en temps constant, puis rejoue le journal. Il
refuse avec `InvalidGame` un germe qui n'est pas celui de l'engagement — un hasard tiré de nouveau d'un autre germe
jusqu'à ce qu'un résultat plaise à quelqu'un — ainsi qu'un journal qui ne se rejoue pas.

## Rejouer

```php
$state = $engine->replay($game, $log);
```

Le rejeu vérifie que le journal est de ce jeu et de cette version de ses règles, exécute la mise en place, puis
applique chaque coup tel qu'il a été joué. Il refuse avec `InvalidGame` un coup qui ne s'applique plus, et un coup qui
tire au hasard là où il ne l'avait pas fait, ou l'inverse : le jeu n'est pas pur, ou ses règles ont changé sans
nouvelle version. Un jeu dont les règles changent augmente sa version ; ses anciennes tables sont alors rejouées par la
version qui les a écrites, ou fermées.

Le journal garde si un coup a tiré au hasard, pas ce qu'il a tiré : les valeurs reviennent du germe. Un journal auquel
on donne un autre germe se rejoue aussi, vers un autre état, et c'est ce contre quoi l'engagement protège.

## L'état en valeurs simples

`GameState::toArray()` écrit l'état en valeurs simples — les données du jeu telles que le jeu les a écrites —, et
`GameState::fromArray()` les relit, en vérifiant le type de chaque champ, l'ordre du tour, et que le siège qui agit et
les sièges en attente font partie de l'ordre du tour. Un cache partagé garde l'état ainsi, plutôt que comme un objet
PHP, dans un `Snapshot` écrit par `Snapshot::toArray()` avec la révision du journal et l'engagement sur le germe : ce
qu'il redonne est refusé avec `InvalidGame` quand c'est autre chose, et le journal est rejoué à la place.

## Reprendre un coup

```php
$table = $engine->undo($game, $table, 'seat:ann');
```

Le dernier coup est repris quand la définition du jeu le permet, que la partie n'est pas terminée, que le coup n'a rien
tiré au hasard — ce qu'il a révélé ne peut plus être caché —, et que le siège qui le reprend l'a fait ; la table
elle-même, un siège null, reprend n'importe quel coup de ce genre. L'état de la table est le journal sans le coup,
rejoué. `Engine::checkUndo()` applique les mêmes règles à un état et à un dernier coup, pour un appelant qui ne détient
aucun journal entier.

Une fois la partie terminée, toute reprise lève `GameOver`, quel que soit le demandeur. Le journal d'une table terminée,
germe compris, peut être montré à quiconque pour vérifier ses tirages, et le germe dit chaque tirage à venir : un coup
repris alors permettrait à un joueur de rejouer en les connaissant. Aucun coup n'est fait non plus : le moteur refuse
tout coup à une table terminée avec `IllegalMove`.

`Tables::finishedLog()` est le journal à montrer : elle lève `GameInProgress` tant que la partie continue, et
`ConcurrentMove` quand le journal qu'elle lit n'est pas celui de l'état terminé — un autre germe ou un autre nombre de
coups, comme lorsqu'une table est rouverte sous le même identifiant entre les deux lectures —, de sorte que le germe
d'une table qui continue n'est jamais montré.

## Mettre un état à jour

```php
$header = $log->header();
$state = $engine->advance($game, $header, $engine->setup($game, $header), $log->moves);
$step = $engine->step($game, $header, $state, $move);   // $step->move to append, $step->state after it
```

`setup()` donne l'état avant le premier coup d'un journal connu par son en-tête. `advance()` applique les coups qui
suivent un état, chacun avec le hasard de sa place, tels qu'ils viennent d'un itérable : un rejeu est la mise en place et
chaque coup, et un état en cache avec les coups faits depuis est mis à jour sans les coups d'avant. Il refuse un coup qui
ne suit pas l'état, un coup au-delà de l'en-tête, ou un état d'une autre table. `step()` applique un coup de plus à
l'état après chaque coup de l'en-tête, et donne le coup à ajouter avec la mention qu'il a tiré au hasard ou non.
