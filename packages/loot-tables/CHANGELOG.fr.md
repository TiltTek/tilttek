<!-- phil-dev source-sha256=6deaeb3088d66612491899cf4ecbe18787d9740b6e84c95d29433f0c8821b84b -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `tilttek/loot-tables` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.3.0-alpha - 2026-11-17

### Modifié

- `PendingDraw::commitment()` est l'engagement de `tilttek/dice` envers le germe et le nonce du tirage, pour qu'il ne
  vaille que pour ce tirage, et `LootDrawer::verify()` vérifie contre lui le nonce d'une preuve. Avec `tilttek/dice`
  0.3, une preuve publiée avec un engagement envers le germe seul ne se vérifie plus ; réservez une seule fois chaque
  nonce d'un germe.
- Il requiert `tilttek/dice` 0.3.

## 0.2.0-alpha - 2026-11-14

### Modifié

- `tilttek/dice` 0.2 accepté, tout comme 0.1.
- `LootDrawer::verify()` vérifie le germe du client d'une preuve comme le fait `reveal()` — de 1 à 256 octets de texte
  UTF-8 — avant de relancer les dés : une preuve avec un germe du client vide, qu'aucune révélation ne donne, ou avec un
  germe trop long ou qui n'est pas du texte UTF-8, est fausse, et un germe long envoyé dans une requête publique ne
  coûte plus un lancer de sa longueur.

## 0.1.0-alpha - 2026-11-11

### Ajouté

- `LootEntry` : une entrée avec sa clé, son nom d'au plus 1000 caractères, le chemin ou l'URL de son image et son
  emplacement.
- `LootTable` : de 2 à 1000 entrées lancées avec un dé d'autant de faces, l'entrée de la face 1 en premier ; `ofTexts()`
  pour une liste de textes.
- `LootBox` : les 1 à 10 tables qu'un produit lance, dans l'ordre, chacune avec son propre dé ; `single()`, `dice()` et
  le lot `tilttek/dice` de la boîte.
- `LootCatalog` : les tables et les boîtes d'une application par clé, chaque boîte lançant des tables du catalogue et
  écrivant ses tirages en 1000 caractères au plus ; `CatalogReader` le lit depuis du YAML ou du JSON décodé, en nommant
  le chemin d'une valeur qu'il refuse.
- `PendingDraw` : un germe secret tiré d'une source sûre, son engagement publié d'abord, et un nonce ; restauré depuis
  son germe enregistré.
- `LootDrawer` : la révélation d'une boîte à partir d'un tirage en attente et du germe du client de l'acheteur, de 1 à
  256 octets de texte UTF-8, donné après l'engagement, par les lancers vérifiables de `tilttek/dice`, et la vérification
  d'une preuve publiée ; `RevealedDraw` et sa preuve.
- `LootResult`, `Pick` et `DrawSource` : une face et une entrée par table, vérifiables ou entrées depuis des dés
  physiques.
- `ShopCode` : un caractère par dé, les chiffres puis l'hexadécimal prolongé jusqu'à `K` pour un d20, écrit et relu dans
  l'une ou l'autre casse.
- `DrawText` : les faces jointes par `-` et les noms des entrées, écrits et relus à partir des faces seules.
- `LootException`, `InvalidLoot`, `UnknownLoot` et `InvalidCode`.
- Des cibles de fuzzing pour les codes de boutique et les textes de tirage, et des bancs d'essai de la révélation, de la
  vérification et des codes.
- La documentation : installation, tables et boîtes, tirage, codes, exceptions, points d'extension, tests et référence.
- Les fichiers de la communauté : guide de contribution, code de conduite, politique de sécurité et licence MIT.
