<!-- phil-dev source-sha256=1067e8c8251df7d633ff760918ebbc18fb20f96cd846a5798b5119758dd27a36 -->
# Le catalogue

Un `Catalog` contient ce dont est faite la collection d'un site, chaque définition sous son code : de 1 à 64 lettres
minuscules, chiffres, `_`, `.` ou `-`, en commençant par une lettre.

## Raretés

Une `Rarity` est un code et un poids, de 1 à 10^6 : la part des tirages qui tombent sur elle quand un paquet ou le
calendrier ne dit rien d'autre. Le catalogue garde ses raretés dans l'ordre de leur déclaration, de la plus commune à
la plus rare.

## Objets

Un `Item` a un code, un nom sous forme de `Texts` — un texte par locale, `en`, `fr`, `fr_CA`, lu dans la locale du
lecteur, puis dans sa langue, puis en anglais, puis dans le premier écrit —, une rareté, jusqu'à 20 ensembles, une
description et une image facultatives (un chemin ou une URL sans espace blanc), le fait que ses exemplaires puissent
changer de mains ou non, et le `CollectibleDie` qu'il représente pour les objets d'un ensemble de dés : de 2 à 1 000
faces, une couleur écrite `#rrggbb` et une matière.

## Ensembles

Un `ItemSet` a un code, un nom, un `SetKind` et jusqu'à 20 `Reward`. La sorte indique comment l'ensemble parvient à un
membre :

| Sorte | Comment |
|---|---|
| `plain` | Objet par objet : l'objet du jour, les paquets, les récompenses |
| `base` | Chaque membre le reçoit à son arrivée, par `Collections::welcome()` |
| `earned` | Contre les points du site, par le catalogue de `tilttek/exchange` ; remis en entier quand l'échange est confirmé |
| `paid` | Acheté pour soi ou en cadeau ; remis en entier quand le site confirme l'achat |

Une récompense est déclarée, jamais exécutée : sa sorte — `points`, `badge`, `item`, ou toute autre que l'application
prend en charge —, ce qu'elle nomme dans cette sorte, et combien. Un ensemble peut ne contenir encore aucun objet ; il
n'est alors jamais complet.

## Paquets

Un `Pack` a un code, un nom, une taille de 1 à 50, les ensembles dans lesquels il tire — tous les objets quand aucun
n'est nommé — et les poids de ses raretés quand ils diffèrent de ceux du catalogue. Une rareté absente des poids d'un
paquet n'y est jamais tirée.

## À partir de valeurs simples

`CatalogReader::read()` et `FixedCatalog::fromArray()` lisent la définition que contient une configuration :

```php
[
    'rarities' => ['common' => 70, 'rare' => ['weight' => 25], 'legendary' => 5],
    'items' => [
        'pirate' => ['name' => ['en' => 'Pirate', 'fr' => 'Pirate'], 'rarity' => 'rare', 'sets' => ['crew'], 'image' => '/img/pirate.webp'],
        'ember-d6' => ['name' => 'Ember d6', 'rarity' => 'common', 'sets' => ['ember'], 'die' => ['sides' => 6, 'color' => '#c0392b', 'material' => 'resin']],
        'medal' => ['name' => 'Medal', 'rarity' => 'rare', 'tradable' => false, 'description' => 'For the bravest.'],
    ],
    'sets' => [
        'crew' => ['name' => 'The crew', 'rewards' => [['kind' => 'points', 'code' => 'xp', 'amount' => 50]]],
        'ember' => ['name' => 'Ember dice', 'kind' => 'base'],
    ],
    'packs' => ['booster' => ['name' => 'Booster', 'size' => 5, 'sets' => ['crew'], 'weights' => ['common' => 80, 'rare' => 20]]],
]
```

Les clés inconnues sont refusées, pour qu'une faute de frappe ne passe jamais pour une valeur par défaut. Le catalogue
vérifie chaque référence — la rareté et les ensembles d'un objet, les ensembles et les poids d'un paquet — et qu'un
paquet a de quoi tirer.

## Un catalogue gardé ailleurs

Les services demandent le catalogue à un `CatalogProvider` à chaque opération. `FixedCatalog` en contient un construit
une seule fois. Un `WritableCatalog` se modifie une définition à la fois, par `define(DefinitionKind, code,
definition)` et `remove(DefinitionKind, code)` ; le catalogue qui en résulte est vérifié en entier avant toute
écriture. `tilttek/collections-bundle` en garde un dans la base de données.

## Chances

`Catalog::odds(sets, weights)` donne les `Odds` d'un tirage parmi les objets de certains ensembles : les raretés qui ont
un objet dans le lot et un poids, la chance de chaque rareté — son poids sur le total des leurs — et de chaque objet —
la chance de sa rareté partagée entre les objets de cette rareté —, ainsi que `draw(Randomizer)`, qui choisit une
rareté selon son poids, puis l'un de ses objets.
