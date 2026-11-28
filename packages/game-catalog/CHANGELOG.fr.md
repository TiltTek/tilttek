<!-- phil-dev source-sha256=541b29986e51794f2f3731577d3a5eea47bd4e63c3d53c3078418297e2f859f3 -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `tilttek/game-catalog` seront consignés dans ce fichier.

Les mises à jour devraient suivre les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.1.0-alpha - 2026-11-27

### Ajouté

- `Details` : ce qu'une entrée dit d'un jeu tel que son éditeur l'indique — nom, `EntryType`, `GameKind`, système de
  jeu, éditeurs, auteurs, année, nombres de joueurs, durée de partie, âge minimal, langues en étiquettes BCP 47, niveaux
  d'un scénario, `LanguageDependence`, description et couverture —, chaque champ dans ses bornes, comparé et fusionné
  par `Field`.
- `Entry` et `Origin` : une entrée saisie à la main ou envoyée par une source sous sa clé, avec les valeurs de sa source
  et, à côté, les champs que l'administration a corrigés, que la source laisse en place quand elle envoie l'entrée de
  nouveau ; au plus 500 errata, et masquée ou affichée.
- `ExternalId` et `IdentifierScheme` : l'identifiant d'un jeu dans BoardGameGeek, RPGGeek ou Wikidata, et le lien vers
  sa page ; rien d'autre de ces bases de données n'est gardé.
- `Gtin` : le code-barres d'une boîte, de 8, 12, 13 ou 14 chiffres, son chiffre de contrôle vérifié, gardé sur 14
  chiffres.
- `Erratum`, `Link` et `LinkType` : des errata dans l'ordre de leur publication, et des liens entre entrées — les types
  de relations d'OpenTabletop, avec `edition_of` et `translation_of`.
- `Catalog` : des entrées ajoutées à la main et synchronisées depuis les sources qui alimentent le catalogue, avec
  `feed()` borné ; des identifiants détenus chacun par une seule entrée ; des errata publiés avec l'observateur averti ;
  des liens, au plus 200 par entrée ; des codes-barres donnés à une entrée, et `identify()`, qui trouve une boîte
  scannée localement, sinon par un `Contract\BarcodeResolver` une fois ; le masquage ; la page d'un jeu en `Sheet` ;
  l'historique en lignes `Change`.
- `Corrections` : une correction proposée sur n'importe quel champ, au plus 20 en attente par membre ; acceptée avec
  `CorrectionConflict` quand un champ qu'elle change a changé sur l'entrée depuis, à moins de la forcer ; refusée avec
  une note ; un champ corrigé ramené à la valeur de sa source.
- `Ballot`, `Polls`, `PollResults` et `PlayerCountResult` : un bulletin par membre et par entrée, avec une note de 1 à 5
  et l'un des trois `PlayerCountChoice` pour chaque nombre de joueurs, la complexité, l'âge minimal et la dépendance à
  la langue ; comptés en centièmes entiers — les nombres de joueurs notés 4,00 et 3,00 ou plus, ceux que la plupart des
  membres choisissent comme meilleurs ou recommandés, la complexité moyenne, l'âge médian, la dépendance la plus votée.
- `Reviews` : les jeux joués et menés par l'auteur d'une critique, lus depuis un `Contract\PlayHistory` et figés dans le
  `Contract\ReviewSystem` de l'application.
- `Import\CollectionReader`, `Import\Columns` et `Import\Limits` : un fichier de collection en CSV lu dans des bornes de
  taille, de lignes, de colonnes et de cellules, les colonnes d'un export de BoardGameGeek ou de RPGGeek données par
  `Columns::geekExport()`, une ligne illisible signalée avec son numéro.
- `Import\CollectionImporter` : les jeux du fichier trouvés dans le catalogue par identifiant, les jeux de rôle à moins
  que d'autres sortes soient données, les identifiants que le catalogue ne détient pas renvoyés pour le membre.
- `Contract\CatalogStore` et `Store\InMemoryCatalogStore` pour les tests ; `Contract\EntrySource`,
  `Contract\BarcodeResolver`, `Contract\CatalogObserver`, `Contract\PlayHistory` et `Contract\ReviewSystem`.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README ; des tests unitaires et d'intégration, et
  une cible de fuzzing du lecteur de collection.
- `composer.json`, `phil-dev.php`, `composer-dependency-analyser.php`, `CONTRIBUTING.md`, `SECURITY.md` et
  `CODE_OF_CONDUCT.md`.
