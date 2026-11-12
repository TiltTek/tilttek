<!-- phil-dev source-sha256=f76ad222ce7523a43cc3dbfb4093d069f0fbc88bc5375167b360e915276077ab -->
# Écrire un magasin

Une application garde les ouvertures dans son propre stockage en implémentant `Contract\OpeningStore` :

| Méthode | Fait |
|---|---|
| `find($calendar, $member, $day)` | L'ouverture de cette case par ce membre, ou null |
| `add($opening)` | Garde l'ouverture sauf si une existe déjà pour le même calendrier, membre et jour ; vrai quand elle est gardée |
| `openingsOf($calendar, $member)` | Les ouvertures du membre dans le calendrier, par jour |
| `eraseMember($member)` | Supprime toutes les ouvertures du membre, dans tous les calendriers ; combien ont été supprimées |

`add()` est le garde-fou de l'idempotence : il doit garder au plus une ouverture par calendrier, membre et jour même
quand deux requêtes écrivent en même temps. En SQL, une clé unique sur les trois colonnes et une insertion qui ignore un
doublon y suffisent :

```sql
CREATE TABLE advent_opening (
    calendar VARCHAR(64) NOT NULL,
    member VARCHAR(320) NOT NULL,
    day SMALLINT NOT NULL,
    opened_at TIMESTAMP NOT NULL,
    PRIMARY KEY (calendar, member, day)
);

INSERT INTO advent_opening (calendar, member, day, opened_at) VALUES (?, ?, ?, ?) ON CONFLICT DO NOTHING;
```

Le membre est gardé sous la forme `ResourceReference::toString()` et relu avec `ResourceReference::fromString()` ; le
moment garde son décalage. `Opening::toArray()` donne les quatre colonnes.

Les tests de la bibliothèque contiennent `OpeningStoreTestCase`, le contrat que réussit tout magasin ; copiez-le dans les
tests de l'application et étendez-le avec le magasin à vérifier.
