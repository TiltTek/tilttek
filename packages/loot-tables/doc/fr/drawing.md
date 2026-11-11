<!-- phil-dev source-sha256=a940befb87bacf58448c68bf982deb88aee38402e9831e79a67a918d88ce2d80 -->
# Tirer

## Un tirage vérifiable

Un tirage est vérifiable quand le résultat n'a pas pu être choisi. La bibliothèque suit l'engagement de
`tilttek/dice` :

1. **Engager.** `PendingDraw::open($secure, $nonce)` tire un germe secret d'une source sûre. Son `commitment()`,
   l'empreinte SHA-256 du germe, est montré tout de suite : avant le paiement, sur la commande. Le *nonce* numérote les
   tirages faits à partir d'un même germe. Le germe reste secret ; `PendingDraw::restore()` reconstruit le tirage à
   partir du germe stocké.
2. **Germe du client.** L'acheteur, après avoir vu l'engagement, donne un germe du client de 1 à 256 octets de texte UTF-8 : des mots
   qu'il tape, ou des octets que son propre appareil tire.
3. **Révéler.** `LootDrawer::reveal($box, $pending, $clientSeed)` lance les dés de la boîte à partir du germe et du
   germe du client, un dé par table, et retourne un `RevealedDraw` : son `LootResult` et le jet vérifiable de
   `tilttek/dice`. Les mêmes germe, germe du client et *nonce* donnent toujours les mêmes faces.
4. **Publier.** `RevealedDraw::proof()` est ce qui est publié une fois le tirage révélé : la boîte, les faces,
   l'engagement, le germe, le germe du client, le *nonce*, l'expression, l'arrondi et le jet.
5. **Vérifier.** `LootDrawer::verify($box, $proof)` est vrai quand le germe correspond à l'engagement, que l'expression
   est celle de la boîte, que relancer les dés donne le jet publié et que les faces sont celles du jet. Une preuve
   illisible (un champ manquant ou d'un autre type, un germe qui n'est pas hexadécimal) donne faux, jamais une
   exception.

Le tirage est fait une seule fois : qui fait le tirage garde le résultat et le redonne quand on le demande, plutôt que
de révéler une seconde fois.

```php
$pending = PendingDraw::open(new Randomizer(new Secure()));
$store->keep($pending->seed->hex(), $pending->commitment()->hex);  // the commitment is shown

// the buyer gives their client seed, then pays
$revealed = new LootDrawer()->reveal($box, PendingDraw::restore($seed), $clientSeed);
$store->keepResult($revealed->proof());
```

### Ce que protège le germe du client

L'engagement empêche qui fait le tirage de changer le germe après coup ; il ne l'empêche pas d'ouvrir plusieurs
tirages, de révéler chacun en privé et de ne montrer que l'engagement d'un germe dont le résultat lui convient. Seul un
germe du client qu'il ne connaissait pas au moment de s'engager l'en empêche. Un germe du client que la boutique
connaît ou choisit (un numéro de commande, une ligne, une unité) n'apporte aucune équité : recueillez-le auprès de
l'acheteur une fois l'engagement affiché à son écran.

## Résultats

Un `LootResult` est un `Pick` par table (la table, la face que son dé a montrée et l'entrée de cette face) et la
`DrawSource` de ses faces :

| Source | Faces |
|---|---|
| `Verifiable` | lancées par `LootDrawer` à partir d'un germe engagé |
| `Physical` | des dés physiques, lancés devant un caissier et saisis tels qu'ils sont tombés |

`LootResult::ofFaces($box, $faces, $source)` construit un résultat à partir des faces, une par table ; `InvalidLoot`
refuse un mauvais nombre de faces ou une face qu'un dé n'a pas. `faces()` et `entries()` les énumèrent dans l'ordre des
tables.

## Un texte tiré d'une liste

Un texte tiré après le paiement est une boîte d'une seule table de textes :

```php
$box = LootBox::single(LootTable::ofTexts('epitaphs', 'Epitaphs', $texts));
$text = new LootDrawer()->reveal($box, $pending, $clientSeed)->result->entries()[0]->name;
```
