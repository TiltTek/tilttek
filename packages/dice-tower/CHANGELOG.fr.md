<!-- phil-dev source-sha256=eb607bf88eef095fa8529faf21d3e3a48138c1ac732ea3c5404d7e6c46276d5f -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `tilttek/dice-tower` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.1.2-alpha - 2026-11-25

### Modifié

- Les prérequis de développement acceptent `phil/ux-kit` 0.6, la version publiée le 2026-11-24, et aucune version
  antérieure, pour que le paquet s'installe seul à côté de la version publiée du kit.

### Sécurité

- `twig/twig` antérieur à 3.31.1 refusé, pour les correctifs de sécurité de cette version : son bac à sable vérifie la
  politique à plus d'endroits, et un gabarit ne peut plus remplacer le contenu d'un `Markup`.

## 0.1.1-alpha - 2026-11-24

### Modifié

- Requiert la dernière version mineure de chaque dépendance : `doctrine/orm` 3.8, `doctrine/dbal` 4.6, `twig/twig` 3.31,
  `symfony/ux-twig-component` 3.8, `symfony/stimulus-bundle` 3.8.
- `tilttek/dice` 0.3 requis : 0.2 n'est plus accepté.

## 0.1.0-alpha - 2026-11-20

### Ajouté

- `TilttekDiceTowerBundle` et sa configuration : l'historique qu'une personne qui regarde lit d'un coup, le préfixe des
  sujets, le service qui dit qui peut regarder une table, le hub Mercure et son nom, le bus de messages des relais, le
  stockage et le gestionnaire d'entités du journal, le sous-dossier des recettes du kit UX ; `TilttekDiceBundle` requis,
  dont le journal contient les lancers.
- Le journal des tours : `TowerDrop`, mappé pour Doctrine dans `tilttek_dice_tower_drop`, sa position donnée par la base
  de données ; `TowerDropRepository`, dans Doctrine ou en mémoire, vidé entre deux requêtes d'un processus de longue
  durée.
- `DiceTower` : une expression lancée dans le journal et jetée dans la tour d'une table, ou un lancer du journal jeté
  par son identifiant ; les dernières entrées d'une table, ou celles qui suivent une position, autant que l'historique,
  un lancer effacé du journal laissé de côté ; les jets d'une personne qui lance effacés.
- `TowerEntry` et `RollSummary` : une entrée telle que tout le monde à la table la voit, avec des identifiants et le
  résumé borné de son résultat seulement — l'expression, le total et la sortie de chaque instruction, et chaque jeu de
  dés avec son dé personnalisé et la valeur, la face de chaque dé et s'il a compté.
- Le sujet privé de chaque table, `TowerTopics` ; `MercureTowerPublisher`, qui y pousse chaque entrée et journalise un
  hub qu'il ne peut pas joindre ; `TowerSubscriptions`, qui donne à une personne autorisée seulement le sujet de la
  table, dans le témoin d'autorisation, et l'URL à laquelle s'abonner.
- `TowerEntriesController` et sa route, `GET /dice-tower/{table}/entries?after=`, pour les personnes autorisées
  seulement, jamais mise en cache.
- Les ports `TableAccess`, `TowerPublisher` et `TowerRelay` ; `NoTableAccess`, qui ne laisse personne regarder, et
  `NoTowerPublisher` ; `TowerRelays`, qui remet chaque entrée à chaque relais, tout de suite ou par un bus de messages
  sous forme d'un `RelayDrop` de la table et de la position, relu dans le journal par `RelayDropHandler`.
- Twig : `dice_tower()`, `dice_tower_topic()` et `dice_tower_kit()` ; la superposition, une carte du kit UX avec les
  dernières entrées sous forme de journal, chaque face par son libellé et chaque dé qui n'a pas compté dit en mots ;
  traductions en anglais et en français.
- Le contrôleur Stimulus de la superposition : chaque nouvelle entrée ajoutée une seule fois et dans l'ordre, les
  entrées manquées lues dans le journal à l'ouverture de l'abonnement, les entrées en direct retenues entre-temps, et
  chaque entrée distribuée sous forme de `tilttek--dice-tower--tower:drop`.
- La recette Flex dans `recipe/0.1` : le bundle, `config/packages/tilttek_dice_tower.yaml` et la route des entrées
  manquées.
- La documentation : installation, configuration, la tour en PHP, la superposition, points d'extension, tests et
  référence.
- Les fichiers de la communauté : guide de contribution, code de conduite, politique de sécurité et licence MIT.
