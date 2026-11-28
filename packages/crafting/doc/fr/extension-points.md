<!-- phil-dev source-sha256=34d56ac565b78acd5c1b9202d77d41ee51331d1cf65102f7c8cdd56df2ff6302 -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension sont stables seulement à partir de sa version 1.0. D'ici là, une
version mineure peut les changer, et son CHANGELOG dit comment.

| Point | Ce que l'application fournit | Défaut |
|---|---|---|
| `Contract\Inventory` | Les objets des détenteurs et les fabrications qui leur sont appliquées | `Store\InMemoryInventory` dans les tests |
| `Contract\ConsumptionPolicy` | Les objets qu'aucune fabrication ne consomme | Tout objet peut être consommé |
| `Contract\CraftObserver` | Ce qu'il faut faire une fois une fabrication appliquée | Aucun |
| `Psr\Clock\ClockInterface` | Le moment de chaque fabrication | — |

## Écrire un inventaire

Les objets restent là où l'application les garde — les exemplaires de `tilttek/collections`, l'inventaire d'un
personnage, le stock de l'économie d'un grandeur nature —, et son `Contract\Inventory` les lit et les change :

- `holdings($holder)` donne ce que le détenteur a, un `Holding` par objet, avec les étiquettes de l'objet — le plus
  souvent depuis le catalogue qui définit les objets ;
- `find($key)` renvoie la fabrication enregistrée sous une clé, ou aucune ;
- `apply($craft)` vérifie que le détenteur a encore les objets que la fabrication consomme et utilise, puis retire ceux
  qu'elle consomme, ajoute ceux qu'elle rend et produit, et enregistre la fabrication sous sa clé, dans une seule
  transaction ; elle renvoie faux, sans rien changer, quand une fabrication est déjà enregistrée sous la clé, et lance
  `Exception\CraftRefused`, sans rien changer, quand un objet manque.

Le fabricant planifie à partir de ce que `holdings()` a lu, puis `apply()` vérifie de nouveau dans sa transaction : deux
fabrications d'un même détenteur en même temps ne dépensent jamais un objet deux fois. Un inventaire relationnel garde
un index unique sur la clé des fabrications. Un inventaire d'objets qui ont des identifiants garde l'objet nommé par
`Craft::$evolved` sous son objet produit, plutôt que de le retirer et d'en ajouter un autre, quand il veut qu'un objet
évolué reste le même objet.

## Politiques de consommation

Une `ConsumptionPolicy` répond à `mayConsume($item)`. `Policy\ProtectedItems` reçoit une liste fixe ; un site dont la
boutique vend des objets répond depuis son catalogue. Le livre l'interroge pour chaque ingrédient consommé nommé par son
code, et le fabricant pour chaque objet qu'une étiquette consommée pourrait prendre.

## Observateurs

Un `CraftObserver` est informé d'un `Crafted` une fois une fabrication appliquée : le détenteur, la recette, la clé et le
moment. Un observateur le relaie — une réussite, une ligne de l'activité, un message sur un bus — et relit la
fabrication depuis l'inventaire par sa clé quand il a besoin des objets. Il ne garde aucun état entre deux appels.
