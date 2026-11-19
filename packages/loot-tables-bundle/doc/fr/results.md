<!-- phil-dev source-sha256=3cc931890ab0f273ec52e781d04f28174c085ef18a5cef9c144db02497a617ba -->
<!-- phil-dev source-sha256=0 -->
# Résultats

`LootResults` lit les tirages des commandes :

| Méthode | Donne |
|---|---|
| `ofOrder($number)` | les tirages d'une commande, par ligne, option et unité |
| `draw($id)` | le tirage de cet identifiant, ou `null` |
| `result($draw)` | le `LootResult` d'un tirage révélé ou saisi (ses faces et ses entrées, avec leurs images et leurs emplacements), ou `null` |
| `shopCode($draw)` | son code de boutique, ou `null` tant qu'il n'est pas révélé ou quand sa boîte a un dé de plus de 20 faces |
| `verify($draw)` | s'il est vérifiable et si sa preuve se vérifie : le germe correspond à l'engagement montré avant le paiement, et les dés relancés donnent les mêmes faces |
| `replay($draw)` | le tirage relancé à partir du germe qu'il a révélé, ou `null` tant que le germe est secret et pour des dés physiques |

La production compose ce qu'elle fabrique à partir des entrées et de leurs emplacements, par
[`LootPrint`](production-sheet.md) ; un reçu affiche la ligne que la commande a gardée ; la page de l'acheteur montre
l'engagement avant le paiement et, après, le germe et les entrées.

## Relancer un tirage

Une fois révélé, un tirage publie son germe dans sa preuve, `LootDraw::proof()`, et sur la page de l'acheteur.
N'importe qui peut alors le vérifier, en deux étapes :

1. le SHA-256 du germe (ses 32 octets, lus dans les 64 chiffres hexadécimaux) est l'engagement montré avant le
   paiement ;
2. les dés de la boîte relancés avec `tilttek/loot-tables`, à partir de ce germe, avec l'identifiant du tirage comme
   germe client et le nonce 0, donnent les faces gardées.

`LootResults::replay()` fait la deuxième étape, `LootResults::verify()` les deux, et la console fait les deux pour un
tirage :

```sh
php bin/console tilttek:loot-tables:replay 0f5c…e21a
```

La commande écrit l'engagement, le germe, le germe client et les entrées que les dés redonnent, et ne réussit que si ce
sont celles qui sont gardées et que la preuve se vérifie par rapport à l'engagement. Elle échoue pour un identifiant
qu'aucun tirage n'a, et pour un tirage dont le germe est encore secret ou dont les dés ont été saisis à une caisse.

## Les tirages gardés

Un `LootDraw` est le tirage d'une unité :

| Méthode | Donne |
|---|---|
| `id()`, `orderNumber()`, `line()`, `option()`, `unit()`, `drawer()` | l'unité à laquelle il correspond |
| `commitment()` | l'engagement montré, ou `null` pour des dés physiques |
| `revealedSeed()` | le germe, une fois révélé ; `null` tant qu'il est secret et pour des dés physiques |
| `clientSeedOfReveal()` | le germe client que prend le lancer : l'identifiant du tirage |
| `texts()` | les entrées avec lesquelles un texte a été engagé |
| `isRevealed()`, `drawn()`, `proof()`, `result($box)` | ce qu'il a donné, une fois révélé ou saisi |
| `createdAt()`, `revealedAt()` | quand |
