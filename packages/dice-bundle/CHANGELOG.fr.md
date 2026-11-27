<!-- phil-dev source-sha256=610bc05a081f1abe5199983a09465ca5a2d3266f21f32acef0585f043cf00587 -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `tilttek/dice-bundle` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.2.4-alpha - 2026-11-25

### Sécurité

- `twig/twig` antérieur à 3.31.1 refusé, pour les correctifs de sécurité de cette version : son bac à sable vérifie la
  politique à plus d'endroits, et un gabarit ne peut plus remplacer le contenu d'un `Markup`.

## 0.2.3-alpha - 2026-11-24

### Modifié

- Requiert la dernière version mineure de chaque dépendance : `doctrine/orm` 3.8, `doctrine/dbal` 4.6,
  `doctrine/doctrine-bundle` 3.4, `twig/twig` 3.31, `symfony/translation-contracts` 3.8.
- `tilttek/dice` 0.3 requis : 0.2 n'est plus accepté.

## 0.2.2-alpha - 2026-11-20

### Modifié

- `tilttek/dice` 0.2 ou 0.3 requis : 0.1 n'est plus accepté.

### Corrigé

- Le dossier du paquet dans le monodépôt ne contient plus de lien symbolique `dice` vers `tilttek/dice` ; rien ne le
  lisait, et le paquet publié ne l'a jamais contenu.

## 0.2.1-alpha - 2026-11-17

### Modifié

- `DiceRolls::verify()` suit les engagements de `tilttek/dice` 0.3, qui lient le nonce d'un lancer : publiez
  `$seed->commitment($nonce)` avant que le joueur envoie le germe du client, comme le montre la documentation des
  lancers vérifiables.
- `tilttek/dice` 0.3 accepté, tout comme 0.1 et 0.2.

## 0.2.0-alpha - 2026-11-14

### Ajouté

- `DieAppearance` prend `ink`, la couleur des chiffres écrite `#rrggbb`, et `texture`, une clé que l'application résout
  en sa propre image — de 1 à 128 caractères parmi `a-z`, `0-9`, `.`, `_`, `/` et `-`, commençant par une lettre ou un
  chiffre, sans `..`, `//` ni `/` final —, après `image`, par nom. `readableInk()` donne l'encre ou, sur un dé coloré
  qui n'en a pas, le noir ou le blanc, celui qui contraste le plus avec la couleur, au moins 4,5:1 ; `toArray()` donne
  l'apparence avec cette encre.
- `Contrast` : le contraste de deux couleurs tel que le mesure WCAG 2, la luminance relative d'une couleur, et l'encre
  qui se lit sur une face.
- Le contexte d'un lancer — une table, une partie — : une `ResourceReference` facultative donnée à `rollReplayable()`
  par nom, gardée dans la colonne nullable `context` de `tilttek_dice_roll`, indexée avec `rolled_at`, et lue par
  `RecordedRoll::context()`.
- `rollReplayable()` prend une `key`, par nom, après le contexte : l'identifiant du lancer est tiré du propriétaire et
  de la clé, et le même appel répété renvoie le lancer gardé au lieu de lancer de nouveau.
- `RollRecorded`, distribué par `DiceRolls` après chaque lancer rejouable qu'il enregistre quand l'application a un
  répartiteur d'événements, avec les identifiants du lancer, de son propriétaire et de son contexte ;
  `RollRecorded::key()`, l'identifiant du lancer. Il est distribué au moins une fois : un lancer dont l'écouteur a
  échoué garde son événement dû, et le même appel avec clé ou `DiceRolls::deliver()` le distribue de nouveau,
  `deliver()` renvoyant false quand il était déjà distribué. Ses écouteurs doivent être idempotents.
- `RecordedRoll::isDelivered()` et `markDelivered()`, gardés dans la nouvelle colonne `delivered` de
  `tilttek_dice_roll`, vrai pour les lignes déjà gardées.
- `DiceRolls::rollsIn()`, les derniers lancers d'un contexte, et `DiceRolls::recorded()`, des lancers par leurs
  identifiants dans l'ordre demandé, au plus 500 distincts à la fois.
- `Accessibility`, standard ou contraste élevé : l'accessibilité de la personne qui regarde, passée à l'apparence par
  `DiceRolls::appearance()`, `DiceExtension::view()` et `dice_roll_detail()` en quatrième argument, `'high-contrast'` ou
  `'standard'` ; tout autre texte ne marque aucune préférence.
- `doc/upgrading.md` : la migration des colonnes `context` et `delivered` et les changements de 0.1 à 0.2.

### Modifié

- `DiceAppearance::appearance()` prend l'accessibilité de la personne qui regarde, `?Accessibility $viewer = null` ; la
  propre implémentation du port par une application la déclare, même pour l'ignorer.
- `RecordedRollRepository` a `getMany()`, au plus `RecordedRollRepository::MANY` identifiants, et `ofContext()` ; la
  propre implémentation du port par une application les ajoute.
- Le détail d'un lancer écrit `--tilttek-dice-ink` à côté de `--tilttek-dice-color` et `data-dice-texture` sur le jeu
  d'un membre, et `DiceExtension::view()` donne l'`ink` et la `texture` de l'apparence.
- `psr/event-dispatcher` est requis.
- `tilttek/dice` 0.2 accepté, tout comme 0.1.

## 0.1.0-alpha - 2026-11-11

### Ajouté

- `RecordedRoll`, la ligne de la table `tilttek_dice_roll` — identifiant, expression canonique, germe, arrondi,
  résultat, propriétaire et date — et son port `RecordedRollRepository`, sur Doctrine ou en mémoire. Le journal en
  mémoire se vide quand le noyau se réinitialise.
- `DiceRolls` : analyser, écrire et traduire dans la notation du lecteur, lire une commande du DiceParser de Rolisteam,
  des lancers simples depuis une source sûre, des lancers rejouables gardés et rejoués avec leur arrondi, des lancers
  vérifiables et leur vérification, chacun avec l'arrondi que donne l'appelant, les lancers d'un membre et leur
  effacement ; `Replay` et `UnknownRoll`.
- `DiceMacro`, la ligne de la table `tilttek_dice_macro` — niveau, propriétaire, nom et expression —, `MacroLevel` et le
  port `DiceMacroRepository`, sur Doctrine ou en mémoire. Les macros en mémoire se vident quand le noyau se
  réinitialise.
- `DiceMacros` et `MacroShelves` : les macros d'un site, d'une table et d'un membre, nommées `$name` et résolues par
  jeton en leur arbre, cherchées sur l'étagère du membre, puis sur celle de la table, puis sur celle du site, les noms
  d'une macro à partir de son propre niveau ; une macro définie seulement quand elle s'analyse, une macro qui se nomme
  elle-même refusée, 32 expansions par entrée au plus, retrait, liste et effacement par propriétaire ; `InvalidMacro`.
- `DiceAppearance`, le point d'extension qui habille les dés d'un membre, avec `DieAppearance` et `NoDiceAppearance`.
- `TilttekDiceBundle`, alias `tilttek_dice` : limites — dés, faces, explosions, instructions, longueur, relances,
  répétitions et caractères d'un texte —, dés personnalisés, notations, service d'apparence, domaine de traduction,
  stockage et gestionnaire d'entités ; les tables du journal et des macros mappées pour Doctrine.
- `DiceServices` : les `Limits`, `CustomDice`, `Notations`, `Dialects` et `DiceRoller` de la bibliothèque, un générateur
  aléatoire sûr, le journal, les macros, l'apparence, `DiceRolls` et `DiceMacros` ; dés personnalisés et notations
  construits une seule fois à la compilation du conteneur ; dialectes ajoutés par l'étiquette `tilttek_dice.dialect`.
- `DiceExtension` : les filtres Twig `dice_notation` et `dice_dialect`, les fonctions `dice_roll`, `dice_roll_detail` et
  `dice_text`, et le gabarit d'un détail accessible d'un lancer, avec chaque jeu de dés du lancer et les textes et
  listes qu'il a donnés, traduits et échappés par le gabarit lui-même, même là où l'application désactive l'échappement
  automatique.
- La contrainte `DiceExpression` et son validateur, et le champ `DiceExpressionType`.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
