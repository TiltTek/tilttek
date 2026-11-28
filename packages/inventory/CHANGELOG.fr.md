<!-- phil-dev source-sha256=d9ed4cac965bae6854d8b85d1fec00e1bc395b0b2eaff33142b63a9bae0b257f -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `tilttek/inventory` seront consignés dans ce fichier.

Les mises à jour devraient suivre les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.1.0-alpha - 2026-11-27

### Ajouté

- `Inventory` : un inventaire sous la référence de l'application, pour un propriétaire — un personnage ou un groupe —,
  avec un nom de 1 à 100 caractères et la clé de son système de jeu, ou aucune.
- `Inventories` : ouvrir, renommer, changer le système et fermer un inventaire ; lire les inventaires d'un propriétaire,
  les objets et les objets à un endroit ; effacer une référence de chaque inventaire et de chaque journal. L'application
  décide qui peut agir et passe la référence de celui qui agit, que portent le journal et les événements.
- `Item` et `Burden` : des objets sous des clés, une quantité de 1 à 1 000 000, le poids d'une unité dans l'unité
  entière du système et les emplacements d'une pile, pour qu'un même objet serve aux systèmes qui comptent le poids et à
  ceux qui comptent les emplacements ; ajouter, augmenter, diminuer et retirer.
- `Place` : la réserve, un porteur sous la référence de l'application, ou un contenant du même inventaire, écrit et relu
  sous forme de texte.
- `Container` et `Fill` : des contenants bornés en poids et en emplacements, imbriqués jusqu'à une profondeur bornée,
  jamais en eux-mêmes, dont le contenu peut ne rien peser ; ce qu'un contenant tient par rapport à ses bornes.
- Déplacer un objet en entier ou une partie d'une pile sous une nouvelle clé, un contenant avec ce qu'il tient ; équiper
  ce qu'un porteur tient directement ; transférer un objet ou une partie d'une pile vers un autre inventaire.
- `CarryRules`, `LoadBand`, `Measure` et `Load` : les règles de port d'un système — poids ou emplacements, 1 à 10
  tranches de charge en fractions exactes de la capacité du porteur, refus au-delà de la capacité ou non — et la charge
  de chaque porteur, en nombres entiers.
- `LogEntry` : le journal de chaque changement — objet, unités, d'où vers où, qui, pourquoi, quand —, les plus récents
  gardés dans une borne et lus du plus récent au plus ancien.
- `Limits` : les inventaires par propriétaire, les objets par inventaire, la profondeur des contenants et les entrées de
  journal par inventaire, par défaut 20, 1000, 4 et 1000.
- `Contract\InventoryStore`, avec `Store\InMemoryInventoryStore`.
- `Contract\RuleBook` et `Contract\CarrierCapacity`, avec `Rules\StaticRuleBook` et `Rules\DefaultCapacity`.
- `Contract\InventoryObserver`, averti par `InventoryChanged` avec son `Change`, identifiants seulement.
- Des exceptions sous une seule interface `InventoryException` : `InventoryRefused` avec son `Refusal`,
  `UnknownInventory` et `InvalidInventory`.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
