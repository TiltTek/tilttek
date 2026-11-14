<!-- phil-dev source-sha256=b29daca94514d3d0af3f7bed18166c4eb7b0bb97a97f333a3a3d0115754a9a20 -->
# Écrire un magasin

Une application garde les ouvertures dans son propre stockage en implémentant `Contract\OpeningStore` :

| Méthode | Fait |
|---|---|
| `find($calendar, $member, $day)` | L'ouverture de cette case par ce membre, ou null |
| `add($opening)` | Garde l'ouverture sauf si une existe déjà pour le même calendrier, membre et jour ; vrai quand elle est gardée |
| `undelivered($member)` | Les ouvertures du membre pas encore marquées remises, dans tous les calendriers, par calendrier puis par jour ; une ouverture est ajoutée non remise |
| `markDelivered($opening)` | Marque remise l'ouverture de ce calendrier, de ce membre et de ce jour ; rien quand elle n'y est pas ou est déjà remise |
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
    delivered BOOLEAN NOT NULL DEFAULT FALSE,
    PRIMARY KEY (calendar, member, day)
);

INSERT INTO advent_opening (calendar, member, day, opened_at) VALUES (?, ?, ?, ?) ON CONFLICT DO NOTHING;
UPDATE advent_opening SET delivered = TRUE WHERE calendar = ? AND member = ? AND day = ?;
```

La colonne `delivered` garde si le `BoxOpened` de l'ouverture a été envoyé ; une table écrite pour 0.1 la reçoit avec
`DEFAULT TRUE` pour les lignes déjà là, dont les événements ont été envoyés, puis `DEFAULT FALSE` pour les nouvelles.

Le membre est gardé sous la forme `ResourceReference::toString()` et relu avec `ResourceReference::fromString()` ; le
moment garde son décalage. `Opening::toArray()` donne les quatre colonnes.

Les tests de la bibliothèque contiennent `OpeningStoreTestCase`, le contrat que réussit tout magasin ; copiez-le dans les
tests de l'application et étendez-le avec le magasin à vérifier.
