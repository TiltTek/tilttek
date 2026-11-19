<!-- phil-dev source-sha256=d69d4a0ca3c122054f3bfa6c5038c3446010b4809d52eff3cab96c2064648ea9 -->
# Dés physiques

À un congrès, l'acheteur lance des dés physiques devant le caissier. La page de la caisse montre le formulaire
`PhysicalDiceType` avec `<twig:TilttekLootTables:PhysicalDice>`, comme l'expliquent [les écrans](screens.md), et
`PhysicalDice::enter()` enregistre les codes sur la ligne de commande, un code de boutique par unité :

```php
$physicalDice->enter($order, $line->reference, 'loot', ['352614', '114263']);
$orders->save($order);
```

- Chaque code est lu par `ShopCode` selon la boîte que tire l'option : un caractère par dé, de `1` à `9`, puis `A`
  pour 10 jusqu'à `K` pour 20, en majuscules ou en minuscules.
- La ligne de commande enregistre le `DrawText` de chaque unité comme ses tirages, et le bundle garde chacun comme un
  tirage de dés physiques, sans germe ni preuve.
- Une unité déjà engagée et non révélée prend les dés à la place de son germe.
- Refusés avec `LootDrawRefused` : une ligne que la commande n'a pas, une option qui n'est pas une boîte qui reste à
  tirer ici (un texte, une boîte déjà tirée, une option d'un autre tireur), ou l'absence d'un code par unité. Un code
  qui ne se lit pas est refusé avec `InvalidCode` avant que rien ne soit enregistré.

`PhysicalDice::toEnter()` donne la boîte que tire l'option et le nombre d'unités de la ligne, ce que demande le
formulaire, et refuse les mêmes lignes et les mêmes options que `enter()`.

La commande est passée, comme pour tout tirage ; l'enregistrer ensuite relève de l'application.
