<!-- phil-dev source-sha256=9f0adcdc08d6412c8a0a59d954c8264d5d0b5df34f1472e4f973225f7e66d48d -->
# Catalogues

Un `Catalogue` contient les données d'un jeu, séparées du moteur qui les lit. L'application fournit ses catalogues ; la
bibliothèque n'en fournit aucun.

## Unités

Une `UnitEntry` est une unité que le catalogue offre :

| Valeur | Sens | Par défaut |
|---|---|---|
| `code`, `name` | Le code de l'unité et le nom montré aux gens | Obligatoires |
| `categories` | Jusqu'à 20 catégories, chacune une fois, à partir de celle qui remplit en premier un emplacement de détachement | Aucune |
| `points` | Les points de l'unité avec son plus petit nombre de figurines, de 0 à 100 000 | 0 |
| `minModels`, `maxModels` | Le plus petit et le plus grand nombre de figurines, de 1 à 1 000 | 1, et le plus petit |
| `pointsPerModel` | Les points de chaque figurine au-delà du plus petit nombre, de 0 à 100 000 | 0 |
| `optionGroups` | Jusqu'à 50 groupes d'options ; chaque code d'option une fois dans l'unité | Aucun |

## Options

Un `OptionGroup` est un choix parmi 1 à 100 options : le plus petit et le plus grand nombre de sélections qu'une unité
y fait, chaque option comptée autant de fois qu'elle est prise — `min: 1, max: 1` pour *une arme*, `max: 2` pour *jusqu'à
deux améliorations*. Une `Option` a ses points chaque fois qu'elle est prise, de 0 à 100 000, le plus grand nombre de
fois qu'une unité la prend, de 1 à 1 000, et si ses points comptent une fois pour chaque figurine de l'unité
(`perModel`).

Les points d'une unité sont les siens, plus ceux de chaque figurine au-delà du plus petit nombre, plus ceux de chaque
option prise.

## Détachements

Un `DetachmentType` a de 1 à 50 `Slot`, chacun pour une catégorie avec le plus petit et le plus grand nombre d'unités de
celle-ci, et le plus grand nombre de détachements du type qu'une armée prend. Une unité remplit l'emplacement de sa
première catégorie que le détachement prend ; une unité d'aucune de ces catégories n'y est pas permise. Le catalogue
dit combien de détachements une armée prend, de 0 à 20, et au moins 1 comme maximum.

## Contraintes

Un catalogue déclare jusqu'à 200 contraintes — des comptes d'unités et des parts de points —, que décrit
[Contraintes](constraints.md). Une contrainte qui nomme une unité en nomme une que le catalogue contient.

## Formes et bornes

| Valeur | Forme |
|---|---|
| Le code d'un catalogue, d'une unité, d'une option, d'un groupe, d'un type de détachement ou d'une catégorie | De 1 à 64 lettres minuscules, chiffres, `_`, `.` ou `-`, commençant par une lettre ou un chiffre |
| La version d'un catalogue | De 1 à 32 lettres ASCII, chiffres, `_`, `.`, `+` ou `-`, commençant par une lettre ou un chiffre |
| Un nom | De 1 à 200 caractères UTF-8, sans caractères de contrôle ni espaces en bordure |

Un catalogue contient de 1 à 2 000 unités et de 1 à 50 types de détachement, chaque code une fois.

## Lire un catalogue

`Catalogue::fromArray()` lit le tableau qu'écrit `toArray()`, et `fromJson()` lit du JSON d'au plus 4 Mio, imbriqué sur
au plus 15 niveaux. Les deux refusent une clé inconnue, une clé manquante et une valeur du mauvais type avec
`InvalidCatalogue`, jamais avec une autre exception. Un catalogue inventé, dans la forme qu'écrit `toJson()` :

```json
{
    "code": "ember-vale",
    "name": "Ember Vale Skirmish",
    "version": "2026.1",
    "units": [
        {"code": "captain", "name": "Captain", "categories": ["hero", "leader"], "points": 60, "option_groups": [
            {"code": "weapon", "name": "Weapon", "min": 1, "max": 1, "options": [
                {"code": "sword", "name": "Sword"},
                {"code": "bow", "name": "Bow", "points": 5}
            ]}
        ]},
        {"code": "scouts", "name": "Scouts", "categories": ["troops"], "points": 50,
         "min_models": 5, "max_models": 10, "points_per_model": 10, "option_groups": [
            {"code": "kit", "name": "Kit", "options": [
                {"code": "long-rifle", "name": "Long rifle", "points": 2, "per_model": true}
            ]}
        ]}
    ],
    "detachment_types": [
        {"code": "vanguard", "name": "Vanguard", "max": 1, "slots": [
            {"category": "hero", "min": 1, "max": 1},
            {"category": "troops", "min": 1, "max": 3}
        ]}
    ],
    "min_detachments": 1,
    "max_detachments": 2,
    "constraints": [
        {"type": "count", "category": "leader", "min": 1},
        {"type": "points-share", "category": "hero", "max_percent": 25}
    ]
}
```

Un catalogue modifié prend une nouvelle `version` : une liste enregistre la version avec laquelle elle a été construite,
et le validateur dit quand le catalogue a évolué.
