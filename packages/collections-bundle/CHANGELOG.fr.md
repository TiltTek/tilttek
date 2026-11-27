<!-- phil-dev source-sha256=e7fe30ba6f4184b510048047d0efde48d369b4efa85cc9b761d8cd65b43421b4 -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `tilttek/collections-bundle` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.2.2-alpha - 2026-11-25

### Sécurité

- `twig/twig` antérieur à 3.31.1 refusé, pour les correctifs de sécurité de cette version : son bac à sable vérifie la
  politique à plus d'endroits, et un gabarit ne peut plus remplacer le contenu d'un `Markup`.

## 0.2.1-alpha - 2026-11-24

### Modifié

- Requiert la dernière version mineure de chaque dépendance : `doctrine/orm` 3.8, `doctrine/dbal` 4.6,
  `doctrine/doctrine-bundle` 3.4, `twig/twig` 3.31.

## 0.2.0-alpha - 2026-11-14

### Modifié

- `DbalOpeningStore::complete()` : les exemplaires d'une ouverture insérée sans eux, écrits par une mise à jour une fois
  donnés, pour `tilttek/collections`, dont `Packs::open()` réserve la clé avant de donner aucun exemplaire.
- Le bundle requiert `tilttek/collections` `^0.2@dev`, dont `DbalOpeningStore` implémente
  `Pack\OpeningStore::complete()`.
- `tilttek/dice-bundle` 0.2 accepté, tout comme 0.1.
- `CollectionDiceAppearance::appearance()` prend l'accessibilité de la personne qui regarde de `tilttek/dice-bundle`
  0.2, et donne au dé d'un ensemble la même allure quelle qu'elle soit.

### Corrigé

- `CollectionsPrivacy` implémente `Phil\PrivacyBundle\Application\Contract\PersonalDataExporter` et
  `PersonalDataEraser`, les interfaces qu'expose `phil/privacy-bundle`, et le bundle l'enregistre quand elles existent :
  l'exportation et l'effacement d'un membre l'atteignent maintenant.

## 0.1.0-alpha - 2026-11-11

### Ajouté

- `AlmanacDateRule` : un objet du jour aux dates que calcule une règle de `phil/almanac`, pour 1 à 366 jours.
- `CollectionsTables`, `CollectionsSchemaListener` et `Rows` : les six tables — exemplaires, ensembles complétés,
  ouvertures, choix, objets du jour, définitions — ajoutées au schéma que l'ORM génère pour leur connexion, l'insertion
  qui n'écrit rien sur un doublon selon la plateforme, et la lecture des textes, des entiers, des booléens, des dates,
  des listes et des références.
- `DbalCopyStore`, `DbalCompletionStore`, `DbalOpeningStore`, `DbalChoiceStore` et `DbalDailyStore` : les stockages de
  la bibliothèque sur DBAL, un exemplaire réattribué par une mise à jour qui nomme son propriétaire.
- `DbalCatalog` : le catalogue dans `tilttek_collections_definition`, chaque définition en JSON dans l'ordre où elle a
  été définie la première fois, le catalogue entier vérifié avant qu'une définition soit écrite ou retirée.
- `TilttekCollectionsBundle` : la configuration `tilttek_collections` — stockage, connexion, fuseau horaire, locale,
  catalogue dans la configuration ou dans la base de données (pas les deux), raretés, objets, ensembles et paquets par
  code avec leurs clés gardées telles qu'écrites, l'objet du jour (activé, verbes, un sel secret de 32 octets ou plus ou
  un sel dérivé de `kernel.secret`, ensembles, dates fixes et calculées, projection), domaine de traduction et type de
  membre —, et le module de vie privée quand `phil/privacy-bundle` est installé.
- `CollectionsServices` : les cinq stockages sur DBAL ou en mémoire, le catalogue vérifié à la compilation du conteneur
  ou lu dans la base de données, le fuseau horaire et chaque date vérifiés eux aussi, `Collections`, `Packs` avec des
  germes sûrs, `DiceShelf`, `DailyCalendar`, `Daily`, `MemberData`, la projection étiquetée pour
  `tilttek/activity-bundle` quand l'objet du jour est activé, et l'inventaire, l'apparence des dés, les commandes et les
  fonctions Twig quand leurs paquets sont installés. Les stockages en mémoire sont étiquetés `kernel.reset`.
- `CollectionsInventory` : les exemplaires comme inventaire de `tilttek/exchange`, un refus des collections transformé
  en son `NotOwned`.
- `CollectionDiceAppearance` : le dé qu'un membre a choisi comme apparence que montre `tilttek/dice-bundle`, nommé dans
  la locale configurée.
- `DispatchingObserver` et `CopyGranted` : un exemplaire donné et le `SetCompleted` de la bibliothèque distribués comme
  événements.
- `CollectionsExtension` et les gabarits `album.html.twig`, `daily.html.twig` et `odds.html.twig`, avec leurs
  traductions en anglais et en français.
- `DailyCommand`, `GrantCommand` et `AlbumCommand` : `tilttek:collections:daily`, `tilttek:collections:grant` et
  `tilttek:collections:album`.
- `CollectionsPrivacy` : le module `tilttek_collections` de `phil/privacy-bundle`.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
