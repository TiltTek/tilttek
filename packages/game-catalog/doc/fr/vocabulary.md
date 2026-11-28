<!-- phil-dev source-sha256=c53eec00de5ff67fde42717d06763c3a01a0bca0125b716cd837008a8a71138a -->
# Vocabulaire

La bibliothèque nomme les types d'entrées et les liens entre elles d'après le modèle de données
d'[OpenTabletop](https://github.com/tabletop-commons/opentabletop), une spécification ouverte des données de jeux de
société, dont la spécification et la documentation sont publiées sous la licence
[Creative Commons Attribution 4.0](https://creativecommons.org/licenses/by/4.0/). Les termes ci-dessous marqués
« OpenTabletop » en sont tirés, comme valeurs ; les autres sont propres à la bibliothèque.

## Types d'entrées

| `EntryType` | Valeur | Provenance |
|---|---|---|
| `BaseGame` | `base_game` | OpenTabletop |
| `Expansion` | `expansion` | OpenTabletop |
| `StandaloneExpansion` | `standalone_expansion` | OpenTabletop |
| `Promo` | `promo` | OpenTabletop |
| `Accessory` | `accessory` | OpenTabletop |
| `FanExpansion` | `fan_expansion` | OpenTabletop |
| `Scenario` | `scenario` | La bibliothèque : une aventure, un module ou une campagne d'un jeu de rôle, avec ses niveaux |
| `Supplement` | `supplement` | La bibliothèque : un ouvrage de règles, de cadre de jeu ou d'options |

## Liens

| `LinkType` | Valeur | Provenance |
|---|---|---|
| `Expands` | `expands` | OpenTabletop |
| `Reimplements` | `reimplements` | OpenTabletop |
| `Contains` | `contains` | OpenTabletop |
| `Requires` | `requires` | OpenTabletop |
| `Recommends` | `recommends` | OpenTabletop |
| `IntegratesWith` | `integrates_with` | OpenTabletop ; lu dans les deux sens |
| `EditionOf` | `edition_of` | La bibliothèque : une autre édition du même jeu |
| `TranslationOf` | `translation_of` | La bibliothèque : la même édition dans une autre langue |

## Votes

La note de 1 à 5 par nombre de joueurs, et les trois choix gardés à côté — idéal, recommandé, non recommandé —, suivent
le modèle de nombre de joueurs d'OpenTabletop ; les seuils de `topPlayerCounts()` et de `recommendedPlayerCounts()` sont
ceux de la bibliothèque. Le poids de 1 à 5 est l'échelle qu'OpenTabletop emploie pour la complexité. `GameKind`,
`LanguageDependence`, l'âge minimal voté, les niveaux d'un scénario et les statuts de collection sont propres à la
bibliothèque.
