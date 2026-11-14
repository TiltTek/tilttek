<!-- phil-dev source-sha256=69984c73677e4fbf696421c356ff711686ce88f6cbb25c6bb043330843e6e0ac -->
# Lancer les dés

`DiceRolls` est le service que l'application appelle. Une expression est un `Pool` ou une chaîne, lue dans la notation
de la locale donnée, ou dans la notation canonique si elle est nulle. `readRolisteam()` lit une commande du DiceParser
de Rolisteam, et `DiceMacros` une entrée qui nomme des [macros](macros.md).

```php
$dice->parse('2W6', 'de');                      // a Pool
$dice->readRolisteam('5D10K3');                 // a Pool, from Rolisteam's DiceParser
$dice->write($pool, 'de');                      // "2W6"
$dice->translate($pool, 'foundry')->text();     // "/r 2d6"
$dice->roll('4d6kh3')->totals();                // [13], for instance
```

Les lancers simples viennent du moteur sûr de PHP. Chaque lancer prend l'arrondi de ses divisions, vers le bas par
défaut. Un jeu qui arrondit vers le haut pour un camp et vers le bas pour l'autre passe le mode de celui qui lance :

```php
$dice->roll('1d6/3', null, RoundingMode::PositiveInfinity)->totals();             // [2], for instance
$dice->translate($pool, 'roll20', RoundingMode::PositiveInfinity)->notices;       // the divisions a table rounds down
```

## Lancers rejouables

```php
$recorded = $dice->rollReplayable('4d6kh3', $member);   // kept in the journal
$atTable = $dice->rollReplayable('1d20', $member, context: $table);
$replay = $dice->replay($recorded->id());
$replay->result;      // the roll, die for die
$replay->matches();   // whether it gave what the journal holds
```

`rollReplayable()` tire un identifiant et un germe, lance avec l'arrondi donné et enregistre un `RecordedRoll` daté par
l'horloge, arrondi compris ; le propriétaire et le contexte (l'endroit où le lancer est fait, une table ou une
partie), chacun une `ResourceReference`, sont facultatifs. Il émet ensuite `RollRecorded`, quand l'application a un
répartiteur d'événements : voyez les [points d'extension](extension-points.md#un-lancer-enregistré). `replay()` le
relance à partir de son expression, de son germe et de son arrondi, et lève `UnknownRoll` pour un identifiant que le
journal ne contient pas.

## Lire le journal

```php
$dice->rollsOf($member, 20);                // a member's latest rolls, the latest first
$dice->rollsIn($table, 50);                 // the latest rolls made at a table, the latest first
$dice->recorded([$id, $otherId]);           // these rolls, in the order asked
```

`recorded()` donne chaque lancer une fois et saute un identifiant que le journal ne contient pas. Elle lit au plus
`RecordedRollRepository::MANY` identifiants distincts, soit 500, à la fois, et lève `LimitExceeded` au-delà : une page
des lancers d'une table, un fil, le rejeu d'une partie lu par tranches.

## Lancers vérifiables

```php
$seed = $dice->newSeed();
$commitment = $seed->commitment()->hex;          // publish it, keep the seed secret
$roll = $dice->rollVerifiable('1d20', $seed, $clientSeed, $nonce);
// … once the rolls of this seed are done, publish $roll->toArray(), which reveals the seed
$dice->verify($commitment, $seed, $clientSeed, $nonce, '1d20', $claimed);
```

Un lancer vérifiable fait avec un autre arrondi se vérifie avec le même, que `toArray()` publie : passez-le à
`rollVerifiable()` et à `verify()`. Chaque construction de la notation (instructions, branches, listes de textes,
phrases) est rejouée et vérifiée de la même façon.

L'endroit où le germe est gardé entre l'engagement et la révélation est le choix de l'application : une session, une
ligne d'une table.

## Effacer les lancers d'un membre

`eraseOwner($member)` efface du journal chaque lancer d'un membre et retourne leur nombre ; appelez-la quand la
personne est effacée.
