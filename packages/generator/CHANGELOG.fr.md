<!-- phil-dev source-sha256=5e4b0662840a5d5fa2ebb35397745395129098fd3aed0547e1ab7f0b392d71a9 -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `tilttek/generator` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.2.4-alpha - 2026-11-25

### Sécurité

- `twig/twig` 3.31.1 ou plus récent requis, pour les correctifs de son bac à sable dans 3.31.1 : la politique est
  vérifiée à plus d'endroits, et un gabarit ne peut plus remplacer le contenu d'un `Markup`.

## 0.2.3-alpha - 2026-11-24

### Modifié

- Les tests comptent le travail du tirage le plus coûteux en lectures d'une horloge qui avance à chaque lecture, plutôt
  que sur l'horloge murale : une machine occupée ne fait plus échouer le test de charge, et un tirage qui produit son
  rendu sans lire l'horloge le fait encore échouer.
- `tilttek/dice` 0.3 requis : 0.2 n'est plus accepté.

### Corrigé

- La documentation en français : ses liens vers la section sur l'origine du texte mènent à cette section.

### Sécurité

- `twig/twig` 3.31 ou plus récent requis, pour les correctifs de son bac à sable dans 3.29 : un gabarit qui en nomme un
  autre avec `use` est refusé avant le chargement de cet autre gabarit. Les gabarits produisent leur rendu dans un
  `Twig\Sandbox\Sandbox`, dans un environnement Twig à eux, sous la même politique stricte.

## 0.2.2-alpha - 2026-11-20

### Modifié

- `tilttek/dice` 0.2 ou 0.3 requis : 0.1 n'est plus accepté.

### Corrigé

- Une liste dont les entrées, ou un générateur de texte dont les gabarits, ont des poids dont la somme dépasse
  `PHP_INT_MAX` est refusé avec `InvalidDefinition` à sa construction, plutôt que d'échouer avec une `TypeError` à son
  premier tirage.

### Sécurité

- Un germe compte au plus `Draw::SEED_LENGTH` (256) caractères ; un germe plus long est refusé avec `InvalidDefinition`.

## 0.2.1-alpha - 2026-11-17

### Modifié

- `tilttek/dice` 0.3 accepté, tout comme 0.1 et 0.2.

## 0.2.0-alpha - 2026-11-14

### Ajouté

- Le budget d'un résultat vérifié à l'intérieur de ses gabarits : chaque tour d'une boucle `for` et chaque `{% set %}`
  est une étape, comptée contre `Limits::$steps` (10 000), avec la mémoire dont le résultat a grandi vérifiée contre
  `Limits::$megabytes` (16) et les temps du résultat et du tirage vérifiés à chaque étape.
- `between(min, max)` dans les gabarits : un nombre entier dans une plage, avec le hasard du tirage, compté comme un
  choix.
- `generate(name, count, with: {…})` : des valeurs données au générateur appelé, comme variables de ses variables, de
  son corps et de ses champs.
- Les attributions : `source`, `licence` et `attribution` sur une liste, une entrée ou un générateur (`Attribution`),
  celle d'une entrée prenant la place de celle de sa liste ; `DrawResult::$attributions`, une fois chacune dans l'ordre
  où le tirage s'en est servi la première fois, et `attributions` dans `DrawResult::toArray()`.
- `TraceryImporter` : des grammaires Tracery en listes et en générateurs — symboles, `#symbol#`, les modificateurs
  `.capitalize`, `.capitalizeAll`, `.a` et `.s`, actions `[name:rule]` et `[name:one,other]` dont les variables sont
  données aux générateurs tirés après elles, aussi loin qu'ils les lisent —, avec un préfixe, une attribution et une
  langue.
- `PerchanceImporter` : des tables dans le sous-ensemble de la syntaxe de Perchance sans JavaScript, tel que la
  spécification du projet perhaps le délimite — sections, poids, références, `selectUnique` et `selectMany`, choix en
  ligne, plages et dés, commentaires, blocs Markdown délimités —, avec un préfixe, une attribution et une langue.
- `Imported` : le document qu'une importation a produit, le générateur par lequel commencer, et le document lu dans un
  catalogue.
- `MapSchema` : une carte exportée en JSON avec l'identifiant et la version de son schéma, et le JSON Schema (draft
  2020-12) des cartes carrées, hexagonales et en graphe, fourni sous `schema/map.v1.json`.
- Des cibles de fuzzing pour les deux importateurs, `tracery` et `perchance`.
- La documentation des importations, des attributions, du budget d'un résultat et de l'exportation des cartes.

### Modifié

- `replace` et `join` dans les gabarits sont ceux du générateur : ils refusent de rendre un texte plus long que
  `Limits::$bytes` (65 536), mesuré avant que le texte soit produit, et refusent une partie qui n'est pas un texte.
- Les limites de temps sont aussi vérifiées à chaque étape à l'intérieur d'un gabarit, pour qu'une boucle qui ne fait
  jamais de choix s'arrête au tour qui les franchit.
- `source`, `licence` et `attribution` ne sont plus des noms de champ d'une entrée.
- La documentation dit que des gabarits écrits par d'autres — membres d'un site, auteurs d'une grammaire importée —
  peuvent être chargés et tirés dans les limites.
- `tilttek/dice` 0.2 accepté, tout comme 0.1.

### Corrigé

- La table `hook`, en anglais et en français : l'entrée dont le texte contient une virgule est entre guillemets, pour
  être lue en entier au lieu d'être coupée à la virgule ; Symfony Yaml 8.2.0-BETA2 refuse la forme sans guillemets.
- Une liste ou un générateur dont le nom n'est fait que de chiffres, que YAML et JSON décodent comme une clé entière,
  est lu.

### Sécurité

- Un gabarit pouvait boucler sur des choix sans compter les tours, et faire grandir un texte par `~`, `replace` ou
  `join` jusqu'à épuiser la mémoire : les étapes, la mémoire et les octets produits sont maintenant bornés à l'intérieur
  de chaque rendu.

## 0.1.0-alpha - 2026-11-12

### Ajouté

- Les définitions : `Entry` avec son texte, son poids, ses champs et ses étiquettes, lisible comme un tableau ;
  `EntryList` ; `Template` ; `TextBody` avec ses filtres, `ObjectBody` avec `TemplateField` et `GenerateField`,
  `GridBody` avec `MapLayout` carré ou hexagonal, `GraphBody` avec des nœuds fixes ou lancés et des passages en plus ;
  `GeneratorDefinition` avec ses variables et sa langue et son état de `StateValue`, gabarits de départ et suivants, au
  plus 32, par des noms qu'aucune variable ni aucun champ du générateur ne prend ; `MarkovChain` avec son ordre, ses
  longueurs et le choix de laisser sortir ou non des entrées ; `EntryList` qui invente des mots par une chaîne ;
  `Catalog` par nom, qui refuse les doublons.
- `Reader` et `FileLoader` : des documents YAML et JSON, une ou plusieurs listes et générateurs par fichier, une liste
  comme suite d'entrées ou comme table de ses `entries` et de sa chaîne `markov`, le `state` d'un générateur, des
  dossiers parcourus en profondeur dans l'ordre de leurs chemins, et chaque erreur avec son emplacement ;
  `Reader::isName()`, que ne passe pas un nom suivi d'un saut de ligne.
- `Generator` : chaque gabarit, ceux d'un état compris, compilé dans le bac à sable de Twig à la construction du
  catalogue, et chaque problème énuméré dans une seule `InvalidCatalog` — syntaxe, balises, filtres, fonctions et tests
  hors du bac à sable, opérateur de plage, listes, étiquettes et générateurs inconnus, langues et filtres inconnus,
  expressions de dés qui ne s'analysent pas.
- Les fonctions des gabarits : `pick` par poids, par étiquette, plusieurs sans doublons, avec exclusions, les entrées
  d'une liste filtrées sur les mêmes étiquettes gardées d'un choix à l'autre avec leurs poids cumulés, jusqu'à 64
  filtres par liste ; `generate`, une fois ou plusieurs fois sans doublons ; `roll` sur `tilttek/dice` ; des variables
  partagées par un résultat ; des résultats structurés dont les champs voient les précédents ; du texte dont les espaces
  sont réduites à une seule.
- Les grammaires et leurs filtres `elide`, `article`, `agree`, `plural` et `upper_first` : élision française, formes
  devant une voyelle, contraction, h aspiré, articles, féminin et pluriel avec leurs exceptions ; `a` et `an` anglais,
  articles et pluriel avec ses mots irréguliers ; `el` espagnol devant un a tonique, `del` et `al`, articles avec le
  neutre `lo`, féminin et pluriel avec leurs accents écrits ; articles allemands au nominatif par genre, contractions
  d'une préposition et de l'article, pluriel par terminaison avec ses mots irréguliers ; articles italiens selon le son
  suivant, élision, prépositions articulées et partitif, féminin et pluriel avec leurs mots irréguliers ; articles
  portugais, contractions avec `de`, `em`, `a` et `por`, féminin et pluriel avec leurs mots irréguliers, et le compte 0
  du Brésil et du Portugal ; la marque `Grammar::KEEP` (`^`) qui garde un mot et le mot qui le précède tels qu'ils sont
  écrits ; le port `Grammar`, documenté, par lequel une application ajoute ou remplace une langue ; `Grammars` par
  locale, forme usuelle de la locale ou langue.
- Les listes de Markov : un choix invente un mot lettre par lettre à partir des suites de lettres que la chaîne a
  apprises des entrées, comptées selon leur poids, filtrées par les étiquettes du choix, que le mot porte ; avec une
  majuscule quand chaque entrée en a une ; entre la longueur la plus courte et la plus longue ; jamais une entrée à
  moins que la chaîne le permette ; plusieurs sans doublons et aucun exclu, comparés sans égard à la casse ; 50
  tentatives par mot, chacune comptée comme un choix, puis `ExhaustedList` ; la chaîne apprise une seule fois par liste
  et par étiquettes.
- Les tables avec mémoire : l'état d'un générateur, chaque valeur donnée par son gabarit de départ quand l'état
  précédent ne l'a pas, par son gabarit suivant sinon, ou gardée, ses gabarits voyant `previous` et `state`, puis les
  variables et le corps voyant les deux ; transmis d'un résultat à l'autre d'un tirage, un tirage unique écartant un
  résultat répété et l'état qu'il a laissé ; une valeur de plus de 1024 caractères arrête le tirage ; un autre
  générateur qui l'appelle le fait repartir du début.
- Des tables aléatoires dans `tables/`, chacune un fichier autonome sous `table.<name>` : rencontres par terrain, objets
  magiques, butin avec objets non identifiés, boutiques avec leur marchand et leur stock, accroches d'aventure, rumeurs
  d'un lieu, un oracle de jeu en solo qui pèse les chances et un facteur de chaos, et des présages tirés de cartes ou de
  tables ; écrites en anglais dans `tables/en/` et en français dans `tables/fr/`, les deux versions d'une table avec les
  mêmes listes, générateurs, valeurs d'état et champs ; `Tables` avec leurs noms, leurs langues, et le dossier et les
  fichiers de chaque langue.
- Les cartes : des cellules carrées avec `x` et `y`, des hexagones avec coordonnées décalées et axiales, des graphes
  dont tous les nœuds sont accessibles, avec des passages en plus, dans l'ordre.
- Les tirages : `Draw` avec un nombre, l'unicité, un germe et l'état précédent, au plus 32 noms vers des textes d'au
  plus 1024 caractères, en UTF-8, `DrawResult` qui dit quand il est incomplet et porte l'état dans lequel est laissé un
  générateur avec mémoire, des résultats uniques en un nombre borné de tentatives, des tirages avec germe sur
  `Xoshiro256StarStar`, et des `Limits` sur la profondeur, les choix, le temps d'un résultat, les tentatives, les
  résultats, les cellules et le temps du tirage entier, les temps vérifiés à chaque choix et avant le rendu de chaque
  gabarit, avec `Timer` et `SystemTimer` ; rien d'un tirage n'est gardé pour le suivant, sauf les entrées filtrées par
  étiquette, pour qu'un processus de longue durée puisse servir un tirage après l'autre.
- Les exceptions sous `GeneratorException` : `InvalidDefinition`, `InvalidCatalog` avec ses `Problem`,
  `UnknownGenerator`, `UnknownList`, `UnknownTag`, `ExhaustedList`, `LimitExceeded`, `GenerationFailed` ; une exception
  de la bibliothèque levée à l'intérieur d'un gabarit atteint l'appelant sans être enveloppée.
- Des exemples neutres : des noms syllabiques, des noms inventés par une chaîne de Markov, un oracle oui ou non, une
  auberge en français, un village, un donjon, et trois tables avec mémoire — la météo en fleur hexagonale, un voyage et
  une exploration hexagonale.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
