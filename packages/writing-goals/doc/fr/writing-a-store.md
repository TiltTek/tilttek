<!-- phil-dev source-sha256=fd6522b9535328d726fb6a29c1ffa1c06e7d07012bebc5eea2e00d9b6ca3ab4b -->
# Écrire un magasin

Une application garde les objectifs et les comptes dans son propre stockage en implémentant deux contrats.

## `Contract\GoalStore`

| Méthode | Fait |
|---|---|
| `save($goal)` | Garde l'objectif, en remplaçant celui du même identifiant |
| `find($id)` | L'objectif, ou null |
| `goalsOf($author)` | Les objectifs de l'auteur, par identifiant |
| `remove($id)` | Supprime l'objectif ; rien s'il n'y en a pas |

Un objectif se reconstruit avec son constructeur à partir de ses colonnes : l'identifiant, l'auteur sous la forme
`ResourceReference::toString()`, la cible, les deux jours sous la forme `Day::toString()`, les valeurs de l'unité et de
la forme de la courbe, les `points` de la courbe personnalisée, les `weekdays` et `excluded()` des jours d'écriture, et
le nom du fuseau horaire ; `Curve::of()` relit une courbe à partir de sa forme et de ses points.

## `Contract\LogStore`

| Méthode | Fait |
|---|---|
| `progress($goal)` | Les comptes de l'objectif, comme un `Progress` — `Progress::fromArray()` les lit par date |
| `add($goal, $day, $delta)` | Ajoute au compte d'un jour en une seule étape, jamais sous 0 ; le compte du jour après |
| `set($goal, $day, $count)` | Remplace le compte d'un jour |
| `forget($goal)` | Supprime tous les comptes de l'objectif |

`add()` ne doit perdre aucun compte quand deux requêtes ajoutent en même temps. En SQL, une ligne par objectif et par
jour, et un upsert qui additionne :

```sql
CREATE TABLE writing_log (
    goal VARCHAR(128) NOT NULL,
    day DATE NOT NULL,
    count INTEGER NOT NULL,
    PRIMARY KEY (goal, day)
);

INSERT INTO writing_log (goal, day, count) VALUES (?, ?, LEAST(1000000000, GREATEST(0, ?)))
ON CONFLICT (goal, day) DO UPDATE SET count = LEAST(1000000000, GREATEST(0, writing_log.count + EXCLUDED.count)) RETURNING count;
```

Les tests de la bibliothèque contiennent `GoalStoreTestCase` et `LogStoreTestCase`, les contrats que réussit tout
magasin ; copiez-les dans les tests de l'application et étendez-les avec les magasins à vérifier.
