<!-- phil-dev source-sha256=18bcd13c70dd8f7231e51cd318f6a72cd48f95692f3304bb4ac8dca069d6d265 -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `tilttek/accessible-dice` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## Non publié

La prochaine version est la 0.1.6-alpha.

Tests seulement.

### Modifié

- La suite navigateur installe son propre Stimulus depuis le fichier de verrou npm de `tests/Browser` et s'exécute avec
  `composer test:browser` dans le monodépôt : elle ne cherche plus les dépendances navigateur du kit PHIL UX, que
  l'archive publiée du kit laisse de côté, et ne saute plus ses quatre tests.

## 0.1.5-alpha - 2026-11-25

### Sécurité

- `twig/twig` antérieur à 3.31.1 refusé, pour les correctifs de sécurité de cette version : son bac à sable vérifie la
  politique à plus d'endroits, et un gabarit ne peut plus remplacer le contenu d'un `Markup`.

## 0.1.4-alpha - 2026-11-24

### Modifié

- Requiert la dernière version mineure de chaque dépendance : `twig/twig` 3.31, `symfony/stimulus-bundle` 3.8,
  `symfony/translation-contracts` 3.8.
- `tilttek/dice` 0.3 requis : 0.2 n'est plus accepté.

## 0.1.3-alpha - 2026-11-20

### Modifié

- `tilttek/dice` 0.2 ou 0.3 et `tilttek/dice-bundle` 0.2 requis : la 0.1 de chacun n'est plus acceptée.

### Corrigé

- Deux formulaires d'une page ne partagent plus leurs identifiants, vers lesquels pointent les libellés, `aria-controls`
  et `aria-describedby` : `accessible_dice()` et `AccessibleDiceExtension::view()` prennent un `id` nul par défaut, et
  chaque formulaire affiché sans identifiant reçoit `tilttek-accessible-dice-` et un suffixe aléatoire. Un `id` donné
  est une lettre, puis jusqu'à 63 lettres, chiffres, `_` ou `-` ; tout autre est refusé avec `InvalidChoice`.
  `ext-sodium` est requis pour le suffixe.

## 0.1.2-alpha - 2026-11-17

### Modifié

- `tilttek/dice` 0.3 accepté, tout comme 0.1 et 0.2.

## 0.1.1-alpha - 2026-11-14

### Modifié

- `tilttek/dice-bundle` 0.2 accepté, tout comme 0.1.
- `tilttek/dice` 0.2 accepté, tout comme 0.1.

## 0.1.0-alpha - 2026-11-12

### Ajouté

- `TilttekAccessibleDiceBundle` et sa configuration : les dés standard offerts, les dés personnalisés offerts, la façon
  d'annoncer un résultat — poliment par défaut —, et le domaine de traduction des libellés des faces ;
  `TilttekDiceBundle` requis, dont le formulaire utilise les dés personnalisés et les limites.
- `AccessibleDice` : les dés offerts comme `DieChoice`, les standard par leurs faces et les personnalisés par leur nom,
  et l'expression d'un choix — un dé parmi ceux-là, de un jusqu'à la limite de dés, et un modificateur de -1000 à 1000
  —, `InvalidChoice` pour tout autre.
- `FaceDescriber` et `FaceDescription` : chaque face d'un dé personnalisé par son libellé, puis sa forme et sa valeur en
  mots ; les libellés de chaque face pour le contrôleur.
- `RollAnnouncer` : un lancer en une phrase, chaque dé par son libellé ou sa valeur, un dé qui n'a pas compté dit comme
  tel, puis son total ou ce qu'il a donné, le nom de la personne qui lance d'abord quand il est donné ; `Words`, par le
  traducteur ou en anglais ; `Politeness`.
- Twig : `accessible_dice()`, `accessible_dice_announcement()` et `accessible_dice_faces()` ; le formulaire, ses dés
  comme grands choix, les faces des dés personnalisés décrites, le nombre et le modificateur avec des boutons de chaque
  côté, l'expression qu'ils forment, une option pour entendre les résultats, et une région active ; traductions en
  anglais et en français.
- Le contrôleur Stimulus : l'expression montrée à mesure que le choix change, le nombre de dés ajusté pas à pas, le
  résultat annoncé dans la région active, chaque lancer d'une tour de dés sur la même page annoncé, la lecture à voix
  haute seulement sur demande, et le lancer animé seulement sans demande de mouvement réduit ; sa feuille de style, avec
  des cibles d'au moins 48 pixels et les formes des faces.
- Une suite navigateur avec Panther, exécutée à part.
- La documentation : installation, configuration, le formulaire, accessibilité, points d'extension, tests et référence.
- Les fichiers de la communauté : guide de contribution, code de conduite, politique de sécurité et licence MIT.
