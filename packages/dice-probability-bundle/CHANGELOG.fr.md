<!-- phil-dev source-sha256=8e638fcdc3e1fc9134825656f33761f855d0b0c606e06d150792d45ff0a55c05 -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `tilttek/dice-probability-bundle` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.1.2-alpha - 2026-11-25

### Sécurité

- `symfony/ux-live-component` et `symfony/ux-icons` antérieurs à 3.8.1, `symfony/ux-twig-component` antérieur à 3.8 et
  `twig/twig` antérieur à 3.31.1 refusés, pour les correctifs de sécurité de ces versions.
  `<twig:TilttekDiceProbability:Distribution>` est testé sous LiveComponent 3.8.1 : recalculé quand il est atteint par
  son nom, refusé par sa classe, et non affiché quand un 422 est levé avant l'appel de son action.

## 0.1.1-alpha - 2026-11-24

### Modifié

- Requiert la dernière version mineure de chaque dépendance : `twig/twig` 3.31, `symfony/ux-twig-component` 3.8,
  `symfony/ux-live-component` 3.8, `symfony/stimulus-bundle` 3.8.
- `tilttek/dice` 0.3 requis : 0.2 n'est plus accepté.

## 0.1.0-alpha - 2026-11-20

### Ajouté

- `TilttekDiceProbabilityBundle` et sa configuration : profondeur d'explosion, limite de travail, échantillons, germe et
  limite de lancers des estimations, nombre maximal de dés d'une expression et de valeurs d'une distribution, arrondi
  des divisions, sous-dossier des recettes du kit UX, travail de la recherche des groupes, et limite de débit du
  composant ; `TilttekDiceBundle` requis, dont les probabilités utilisent les dés personnalisés et les limites. Les
  valeurs par défaut tiennent une requête à une fraction de seconde.
- `DiceStatistics` : la distribution d'une expression écrite comme texte, deux expressions comparées, un événement sur
  un ensemble de dés, dans la notation d'une locale, avec l'arrondi configuré ou un autre ; `Rounding` ;
  `TooManyOutcomes` pour une distribution de plus de valeurs que n'en affiche le bundle.
- `GroupSearch` : les étapes de la recherche des groupes que cherche une estimation, `6d6g10`, pesées avant qu'aucun dé
  soit lancé — exhaustive jusqu'à 12 dés, gloutonne au-delà, le pire nombre pour un ensemble calculé ou qui explose,
  `repeat()` et chaque branche comptés —, et `GroupsTooCostly` au-delà de `group_work_limit` : `12d6g12` et `10d10g10`
  sont refusés tout de suite.
- `Refusal`, implémentée par `GroupsTooCostly`, `TooManyOutcomes` et `Throttled` : la clé et les paramètres de la raison
  d'un refus dans le domaine `tilttek_dice_probability`, traduits en anglais et en français.
- Twig : `tilttek_dice_distribution()`, `tilttek_dice_versus()`, `tilttek_dice_probabilities()`, le filtre
  `tilttek_dice_fraction`, dont la réduction est bornée — termes d'au plus 18 chiffres sur des entiers natifs, plus
  longs par `ext-gmp` ou pas du tout, `digits` entre 1 et 60 —, et `tilttek_dice_probability_kit()` ; les gabarits d'un
  résumé, d'un graphique à barres caché des technologies d'assistance et d'un tableau toujours présent, avec la
  comparaison de deux expressions, les estimations et leur marge, et la part des explosions coupées à leur profondeur,
  dans des cartes du kit UX ; traductions en anglais et en français.
- Le Live Component `TilttekDiceProbability:Distribution` : une expression, une seconde à laquelle la comparer, et ce
  que montre le graphique, recalculés à mesure de la saisie, dans des champs du kit UX, les résultats annoncés poliment
  et les erreurs dites dans son alerte, un refus du bundle dans sa raison traduite.
- La limite de débit du Live Component, activée par défaut : `ComputationThrottle` compte chaque calcul que demande le
  navigateur, 60 par minute par adresse IPv4 ou par /64 IPv6 dans une fenêtre glissante de `symfony/rate-limiter`, sous
  la clé d'une empreinte, en échouant fermé ; un pool de cache ou une fabrique de l'application ; le conteneur refuse de
  compiler sans `symfony/rate-limiter` à moins que `rate_limit` soit désactivé.
- Les écrans affichés avec les recettes `Alert`, `Card`, `Chart`, `Field`, `Input`, `RadioGroup` et `Table` du kit PHIL
  UX, installées par le Symfony UX Toolkit, nommées dans le sous-dossier de sa configuration ou de `kit_subdirectory` :
  `KitComponents` et `KitSubdirectoryPass`.
- `group_work_limit` donné aussi aux `Settings` de `tilttek/dice-probability` 0.1.4 : son estimation compte les étapes
  des groupes qu'elle cherche vraiment et s'arrête au-delà de la même limite, derrière la pesée de `GroupSearch`.
- La limite de débit du Live Component refuse, dans l'environnement `prod`, un pool de cache local à chaque serveur, où
  un client pourrait épuiser la limite une fois par serveur : `rate_limit.cache_pool` nomme un pool sur un stockage
  partagé, ou `rate_limit.single_server: true` dit que l'application s'exécute sur un seul serveur. La vérification est
  le `RateLimitGuard` de `phil/rate-limit`.
- La recette Flex dans `recipe/0.1` : le bundle et `config/packages/tilttek_dice_probability.yaml`, avec l'arrondi et la
  limite de débit du composant.
- La documentation : installation, configuration, statistiques en PHP, gabarits, points d'extension, tests et référence.
- Les fichiers de la communauté : guide de contribution, code de conduite, politique de sécurité et licence MIT.
