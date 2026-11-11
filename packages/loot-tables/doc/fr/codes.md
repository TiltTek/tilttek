<!-- phil-dev source-sha256=d342fa0167e387cfa805c8201fec8f1dab505091ad9cb616ec4aa8d9ddf1d15a -->
# Codes

## Codes de boutique

`ShopCode` écrit les faces d'un résultat comme une boutique les écrit dans ses codes : un caractère par dé, dans l'ordre
des tables. Les chiffres 1 à 9 se représentent eux-mêmes, puis l'hexadécimal continue au-delà de `F` jusqu'à `K` :

| Faces | 1–9 | 10 | 11 | 12 | 13–16 | 17–20 |
|---|---|---|---|---|---|---|
| Caractère | `1`–`9` | `A` | `B` | `C` | `D`–`G` | `H`–`K` |

La plus haute face d'un d10 est `A`, celle d'un d12 `C`, celle d'un d20 `K`. Six d6 qui montrent `3 5 2 6 1 4`
s'écrivent `352614`.

- `ShopCode::supports($box)` : si chaque dé de la boîte a 20 faces ou moins.
- `ShopCode::write($result)` : le code ; `InvalidCode` pour une boîte avec un dé plus grand.
- `ShopCode::read($box, $code, $source)` : le résultat d'un code saisi à une caisse, en majuscules ou en minuscules, un
  caractère par dé ; la source est `Physical` si elle n'est pas donnée. `InvalidCode` refuse un code d'une autre
  longueur, un caractère qui n'est pas une face de son dé, ou une boîte sans code.

## Textes de tirage

`DrawText` écrit un résultat sur une ligne qu'un reçu affiche et qu'un programme relit : les faces reliées par `-`, un
deux-points et une espace, puis les noms des entrées séparés par des virgules.

```text
3-5-2-6-1-4: Sword, Helmet, Boots, Shield, Ring, Cloak
```

- `DrawText::write($result)` : la ligne.
- `DrawText::read($box, $text, $source)` : le résultat de la ligne, lu à partir de ses seules faces, pour qu'une entrée
  renommée depuis se lise encore. `InvalidCode` refuse une ligne qui ne commence pas par une face par dé, chacune étant
  une face de son dé.
- `DrawText::longest($box)` et `DrawText::fits($box)` : la plus longue ligne qu'un tirage de la boîte peut écrire, et si
  elle tient dans `DrawText::MAX_LENGTH`, soit 1000 caractères.
