<!-- phil-dev source-sha256=a95dd9298ad1491a81ee42e927cf54e7004ba96b218fb0100be3faa9b4517acd -->
# Vérifier

## L'engagement

L'engagement d'un jet est le SHA-256, en 64 chiffres hexadécimaux minuscules, du JSON canonique de son ouverture :

```text
["tilttek-secret-rolls/1", roll, scene, position, expression, rounding, seed, result, context]
```

Le JSON canonique est le `json_encode()` de PHP avec `JSON_UNESCAPED_SLASHES`, `JSON_UNESCAPED_UNICODE` et
`JSON_PRESERVE_ZERO_FRACTION` : aucune espace, aucune barre oblique échappée, les caractères hors ASCII écrits en
UTF-8. L'expression est dans la forme canonique de `tilttek/dice`, l'arrondi est le nom d'un cas du `RoundingMode` de
PHP, le germe fait 64 chiffres hexadécimaux et le résultat est la liste que `PoolResult::toArray()` écrit.

## Vérifier un jet révélé

`RollVerifier::verify($opening, $commitment)` est vrai quand :

1. l'ouverture a pour empreinte l'engagement publié quand le jet a été fait ;
2. l'arrondi est un `RoundingMode` que PHP connaît, et l'expression se lit, dans les limites du `DiceRoller`, comme un
   ensemble de dés dont la forme canonique est l'expression ;
3. relancer cet ensemble avec `DiceRoller::rollVerifiable()` — le germe, l'identifiant du jet comme germe du client, le
   nonce 0, l'arrondi — donne le résultat révélé.

`verifyPublished($data, $hex)` prend l'ouverture et l'engagement tels que publiés, et est faux pour des données qui ne
se lisent ni comme l'une ni comme l'autre. Une ouverture forgée ne coûte pas plus cher qu'une ouverture honnête :
l'expression est lue dans les limites des dés.

## Le journal

Chaque entrée du journal d'une scène porte l'empreinte de l'entrée qui la précède — 64 zéros pour la première — et sa
propre empreinte, le SHA-256 du JSON canonique de :

```text
["tilttek-secret-rolls/1", scene, position, kind, roll, commitment, roller, opening, at, previous]
```

où `at` est écrit en UTC à la microseconde près, `2026-11-12T20:15:03.250000Z`. Une entrée retirée, modifiée ou
déplacée brise toutes les empreintes qui la suivent.

`SceneJournal::problems()` liste ce qui brise un journal, une phrase par défaut, dans l'ordre des entrées :

- une entrée d'une autre scène, ou à une position autre que sa place ;
- une entrée qui ne porte pas l'empreinte de la précédente, ou qui ne correspond pas à sa propre empreinte ;
- un jet engagé après la clôture, deux fois, ou sous un identifiant autre que celui que sa position donne ;
- la révélation d'un jet non engagé auparavant, déjà révélé, d'une ouverture d'une autre scène ou d'une autre position,
  ou d'une ouverture qui ne correspond pas à l'engagement publié ou qui ne relance pas son résultat ;
- une seconde clôture.

`holds()` est vrai quand la liste est vide. Les outils du meneur de jeu ne peuvent cacher aucun défaut : la vérification
ne lit que le journal public.

## L'export qu'un joueur vérifie seul

```php
$json = $rolls->journal('scene:12')->toJson();          // published, or offered for download
$problems = SceneJournal::fromJson($json)->problems();  // on the player's side
```

`toJson()` écrit le journal dans le JSON canonique sur lequel ses empreintes sont calculées. `fromJson()` lit 32 Mio au
plus, sur 64 niveaux de profondeur, chaque entrée et chaque ouverture vérifiées champ par champ — une ouverture avec un
champ qu'il ne connaît pas est refusée, afin que rien de ce qui est montré n'échappe à l'engagement —, et lance
`InvalidSecretRoll` pour tout le reste ; savoir si le journal tient, c'est à `problems()` de le dire.

Un joueur sans PHP vérifie les engagements et la chaîne d'empreintes avec n'importe quel outil SHA-256 et un
générateur de JSON qui suit les règles ci-dessus ; relancer un résultat exige `tilttek/dice`, dont les jets
vérifiables tirent de `Xoshiro256**` initialisé avec le HMAC-SHA256 du germe du client et du nonce sous le germe.
