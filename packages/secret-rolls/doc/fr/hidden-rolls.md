<!-- phil-dev source-sha256=129e46dfe3319c98e70db0c153c671aa55fb4db721efba7b952a8bd1d8914653 -->
# Jets cachés

## Lancer hors de vue

```php
$hidden = $rolls->roll(
    'scene:12',                          // the scene: 1 to 128 letters, digits, dots, colons, underscores or hyphens
    '2d6+1',                             // a Pool, or an input in the notation of the locale
    roller: 'seat:gm',                   // who rolled, an opaque reference, public
    context: 'The rope bridge holds?',   // what the roll was for, sealed with the result
    rounding: RoundingMode::NegativeInfinity,
    locale: 'en',
);
```

`roll()` tire un germe de 32 octets du générateur aléatoire sécurisé, lance l'expression avec
`DiceRoller::rollVerifiable()` — le germe, l'identifiant du jet comme germe du client, le nonce 0 et l'arrondi de ses
divisions —, garde le `HiddenRoll` scellé dans le dépôt de stockage et ajoute l'entrée de son engagement au journal de
la scène. Le `HiddenRoll` renvoyé est celui du meneur de jeu : `$hidden->opening->totals()` donne les totaux,
`$hidden->opening->result` le résultat entier tel que `PoolResult::toArray()` l'écrit.

L'identifiant d'un jet est dérivé de sa scène et de sa position dans le journal : 32 chiffres hexadécimaux, la première
moitié du SHA-256 de `["tilttek-secret-rolls/1", scene, position]`. Il est connu avant que le jet soit fait, ce qui lie
le germe du client du jet à sa place dans le journal.

Le lanceur et le contexte sont du texte de 1 à 320 et de 1 à 1 024 octets sans caractère de contrôle. Le lanceur est
public, dans l'entrée de l'engagement : donnez une référence opaque, un siège ou un identifiant de membre, jamais un
nom ni une adresse. Le contexte est scellé avec le résultat et révélé avec lui.

## Ce que la table voit

| Moment | La table voit | Le dépôt de stockage garde scellé |
|---|---|---|
| Le jet est fait | L'entrée `committed` : l'identifiant du jet, son engagement, le lanceur et l'heure | L'ouverture : expression, arrondi, germe, résultat, contexte |
| Le jet est révélé | L'entrée `revealed` : l'ouverture entière | L'ouverture, toujours |
| La scène est close | L'entrée `closed` | Les ouvertures des jets retenus |

L'engagement ne dit rien du résultat : les 32 octets secrets du germe y sont inclus dans l'empreinte.

## Révéler

```php
$rolls->reveal('scene:12', $roll, $otherRoll);   // entries `revealed`, in the order given, each roll once
```

Un jet est révélé une fois ; un jet que la scène n'a pas engagé, ou dont le dépôt de stockage ne garde plus l'ouverture,
est refusé, et rien de l'appel n'est ajouté quand un jet est refusé. `hidden($roll)` redonne au meneur de jeu le jet
scellé.

## Clore une scène

```php
$rolls->close('scene:12', $roll);        // reveals the rolls given — a partial reveal —, then closes
$rolls->close('scene:12');               // reveals none, then closes
$rolls->closeRevealingAll('scene:12');   // reveals every roll not revealed yet, then closes
```

Une scène close n'accepte plus de jets ni de seconde clôture. Les jets qu'elle n'a pas révélés restent retenus, listés
par `SceneJournal::withheld()`, leurs engagements dans le journal : `reveal()` les publie encore après la scène, quand
le meneur de jeu choisit de le faire.

## Limites

Une scène accepte un jet tant que son journal contient moins de 4 999 entrées (`SceneJournal::MAX_ROLLS`), de sorte
qu'un journal — chaque jet engagé et révélé une fois, et la clôture — ne contienne jamais plus de 9 999 entrées, et que
sa lecture ou sa vérification reste bornée. L'expression est lue dans les limites du `DiceRoller` ; l'expression d'une
ouverture fait 4 096 octets au plus.
