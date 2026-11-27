<!-- phil-dev source-sha256=027f4a77b59f2b9a3c3ba687afe9ed9f41113192f950dbe27f8bbdead18acc37 -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `tilttek/dice-probability` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.1.5-alpha - 2026-11-24

### Modifié

- Les tests comptent le temps processeur des expressions les plus coûteuses, et du plus grand commun diviseur de grands
  nombres, en tours d'une boucle de référence chronométrée à côté, plutôt qu'en secondes : une machine occupée ralentit
  les deux et ne fait plus échouer les bornes, alors qu'un calcul au-delà de ses limites les fait encore échouer.
- `tilttek/dice` 0.3 requis : 0.2 n'est plus accepté.

## 0.1.4-alpha - 2026-11-20

### Modifié

- `tilttek/dice` 0.2 ou 0.3 requis : 0.1 n'est plus accepté.

### Corrigé

- La recherche des groupes qui atteignent une somme compte dans le budget d'une estimation : `Settings` prend un
  `groupWorkLimit`, 20 000 000 d'étapes sur l'ensemble des échantillons par défaut — environ une seconde —, au-delà
  duquel `CannotCompute` dit que l'estimation chercherait ses groupes trop longtemps. Un échantillon de `n` dés qui
  comptent coûte `n × 2ⁿ` étapes jusqu'à douze dés, `n²` au-delà. Avec les valeurs par défaut, `12d6g12` et `10d10g10`
  sont refusés dans cette seconde plutôt que cherchés pendant des dizaines de secondes ; sur moins d'échantillons, ils
  sont estimés.
- Les coefficients binomiaux que construit une sélection ou un événement sur les multiensembles des dés, environ `n²/2`
  additions pour `n` dés, sont comptés dans la limite de travail du calcul exact : au-delà, l'expression est estimée,
  comme pour le reste de son travail. `4d6kh3` coûte 6 opérations de plus.

## 0.1.3-alpha - 2026-11-17

### Modifié

- `tilttek/dice` 0.3 accepté, tout comme 0.1 et 0.2.

## 0.1.2-alpha - 2026-11-14

### Modifié

- `tilttek/dice` 0.2 accepté, tout comme 0.1.

## 0.1.1-alpha - 2026-11-12

### Corrigé

- La limite de travail couvre tout le calcul exact : une opération sur des poids de nombreux membres compte aussi
  longtemps que dure son arithmétique, et une fraction ramenée à sa forme irréductible, le cube de sa taille. Une
  expression courte comme `100d6!` occupait le processeur pendant environ une minute avant de céder la place à une
  estimation ; à la limite par défaut, un calcul exact s'arrête maintenant après environ une seconde.
- Une estimation est bornée elle aussi : `Settings` prend un `rollLimit`, 200 000 dés sur l'ensemble des échantillons
  par défaut, au-delà duquel `CannotCompute` dit que l'estimation lancerait trop de dés, plutôt que de s'exécuter
  pendant des dizaines de secondes.
- `Natural::divide()` commence les chiffres du quotient là où finit le diviseur : le plus grand commun diviseur de deux
  nombres d'une centaine de membres prend une seconde plutôt qu'une minute.

## 0.1.0-alpha - 2026-11-11

### Ajouté

- `Probabilities` : la distribution exacte d'une expression de `tilttek/dice`, deux expressions comparées, et la
  probabilité d'un événement sur un ensemble de dés, avec les dés personnalisés et les limites de l'appelant, avec
  l'arrondi d'un lancer.
- `Distribution` et `Outcome` : chaque valeur et son poids sur les façons de lancer ; probabilité, au moins, au plus,
  probabilité d'un validateur, moyenne et variance en fractions exactes, écart type.
- Le calcul exact : arithmétique et fonctions sur des issues indépendantes, dés simples additionnés par une fenêtre
  glissante et les autres par élévation au carré, relances répétées ou une seule fois, explosions étalées, composées ou
  pénétrantes avec leur limite, planchers, plafonds, transformations, filtres, garder et écarter sur les multiensembles
  triés des dés, succès et échecs, jugements de l'ensemble entier, nombres, faces et limites d'explosion lancés d'abord,
  listes de valeurs ; textes, listes et `repeat()` refusés, faute de total.
- Les événements : `Event`, `Faces`, `Count`, `OfAKind`, `Straight`, `AllEqual`, `AllDifferent`, `AllMatch`, `AnyOf`,
  `AllOf` et `Not`, calculés sur les multiensembles triés des dés, relances suivies ; `Chance`.
- `Versus` : la probabilité que la première expression soit plus élevée, que les deux soient égales, que la seconde soit
  plus élevée.
- `Settings` : les explosions suivies jusqu'à une profondeur avec la probabilité qui reste ; au-delà d'une limite de
  travail, ou pour les constructions que le calcul ne suit pas, une estimation avec germe avec `Estimate`, sa
  `EstimateReason` et sa marge.
- `Natural` et `Fraction` : des nombres exacts de toute taille, sans extension.
- `ProbabilityException` et `CannotCompute`.
- La documentation : installation, distributions, événements, comparaison, explosions et estimations, nombres exacts,
  exceptions, points d'extension, tests et référence.
- Les fichiers de la communauté : guide de contribution, code de conduite, politique de sécurité et licence MIT.
