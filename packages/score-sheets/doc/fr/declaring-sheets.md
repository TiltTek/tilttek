<!-- phil-dev source-sha256=5698f8df1d65c20981eb04713215865a0aab1e07f992db46a3da4063c49a0d88 -->
# Déclarer une feuille

Une feuille se déclare une fois par jeu, en données : un fichier JSON, une ligne de base de données, ou des objets
construits en code. Les exemples ci-dessous utilisent Lantern Harbor, un jeu inventé pour cette documentation ; ne
déclarez la feuille d'un jeu publié qu'avec l'accord de son éditeur, ou pour un jeu du domaine public.

```json
{
    "game": "lantern-harbor",
    "categories": [
        {"key": "boats", "label": "Boats moored", "counted": false, "min": 0, "max": 12},
        {"key": "lanterns", "label": "Lanterns lit", "counted": false, "min": 0, "max": 40},
        {"key": "harbor", "label": "Harbour", "operation": {"type": "sum", "of": ["boats", "lanterns"]}},
        {"key": "sets", "label": "Sets of lanterns", "counted": false, "min": 0, "max": 10},
        {"key": "set_points", "label": "Points for sets", "operation": {"type": "product", "of": ["sets"], "factor": 4}},
        {"key": "fleet", "label": "Largest fleet", "operation": {"type": "majority", "of": "boats", "points": [6, 3]}},
        {"key": "lighthouse", "label": "Lighthouse", "operation": {"type": "bonus", "of": "lanterns", "at_least": 10, "points": 5}},
        {"key": "coins", "label": "Coins", "counted": false, "min": 0}
    ],
    "tiebreakers": [{"category": "coins", "prefer": "most"}, {"category": "boats", "prefer": "fewest"}],
    "teams": "sum"
}
```

`SheetDefinition::fromJson()` la lit — au plus 64 Kio, sur 16 niveaux de profondeur —, `fromArray()` lit le tableau
décodé, `toArray()` la réécrit. Chaque champ est vérifié, et une déclaration qui ne tient pas ensemble est refusée avec
`InvalidSheet`.

## Le jeu

`game` est une clé : de 1 à 64 lettres ASCII, chiffres, points, soulignés et traits d'union, en commençant par une
lettre. Les clés de catégorie suivent le même motif.

## Catégories

Une feuille déclare de 1 à 64 catégories, chaque clé une seule fois, dans l'ordre où la feuille les montre.

| Champ | Sens | Valeur par défaut |
|---|---|---|
| `key` | La clé de la catégorie | — |
| `label` | Ce que la feuille imprime : de 1 à 100 caractères, aucun caractère de contrôle | La clé |
| `counted` | Si la catégorie compte dans le total | `true` |
| `min`, `max` | Les bornes d'une valeur saisie, entre -1 000 000 et 1 000 000 | Les plus larges |
| `operation` | Comment la catégorie est calculée ; aucune pour une catégorie saisie | — |

Une catégorie calculée ne lit que des catégories déclarées avant elle, et n'a pas de bornes. Une catégorie qui en
alimente une autre — un nombre d'ensembles dont les points sont calculés par une autre catégorie — se déclare avec
`"counted": false`, pour ne pas compter deux fois.

## Opérations

| Opération | Déclarée | Valeur |
|---|---|---|
| Somme | `{"type": "sum", "of": ["a", "b"]}` | La somme de 1 à 16 catégories |
| Produit | `{"type": "product", "of": ["sets"], "factor": 4}` | Le produit de 1 à 16 catégories et du facteur, 1 par défaut |
| Majorité | `{"type": "majority", "of": "boats", "points": [6, 3], "prefer": "most", "at_least": 1}` | Les points de la place du participant : le plus — ou le moins — prend les premiers points, la valeur suivante les deuxièmes, jusqu'à 8 places |
| Prime | `{"type": "bonus", "of": "lanterns", "at_least": 10, "points": 5}` | Les points quand la catégorie est `at_least`, `at_most` ou `equals` le seuil, 0 autrement |

Dans une majorité, les participants à égalité prennent tous les points de leur place, et les places qu'ils occupent
sont sautées : deux premiers, puis un troisième. Un participant sous `at_least`, quand il est donné, ne prend aucune
place. Les constantes — facteurs, points, seuils — se situent entre -1 000 000 et 1 000 000 ; une valeur trop grande
pour être calculée est refusée avec `InvalidEntry` plutôt que convertie en flottant.

## Bris d'égalité

Jusqu'à 8 bris d'égalité, lus dans l'ordre entre participants de même total : le plus — `"prefer": "most"`, la valeur
par défaut — ou le moins d'une catégorie déclarée. Les participants encore à égalité partagent leur rang.

## Équipes

`"teams": "sum"` — la valeur par défaut — fait du score d'une équipe la somme des totaux de ses membres ;
`"teams": "best"`, le meilleur d'entre eux.

## En code

```php
$sheet = new SheetDefinition('lantern-harbor', [
    new Category('boats', 'Boats moored', counted: false, min: 0, max: 12),
    new Category('fleet', 'Largest fleet', new Majority('boats', [6, 3])),
], [new Tiebreaker('boats', Direction::Fewest)]);
```
