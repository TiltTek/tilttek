<!-- phil-dev source-sha256=ee29c58591a32145dcd46d4a11ee5b54b43df4cea25826ec28a3b28dde07a824 -->
# Base de données

Le bundle ajoute cinq tables au schéma de sa connexion :

| Table | Clé primaire | Contenu |
|---|---|---|
| `tilttek_quests_contribution` | quête, étape, activité | Ce que chaque activité a apporté à une étape : membre, valeur, moment |
| `tilttek_quests_enrolment` | quête, membre | L'acceptation d'un membre, sous une équipe, et son abandon |
| `tilttek_quests_completion` | quête, occurrence, sujet | Le journal des quêtes accomplies |
| `tilttek_quests_duel` | duel_key | Les duels : camps, statut, acceptation, fin, gagnant |
| `tilttek_quests_definition` | code | Les définitions des quêtes en JSON, pour `catalog: doctrine` |

Les membres, les équipes et les sujets s'écrivent `kind:identifier`, jusqu'à 320 octets. Les dates sont en UTC, à la
seconde près.

**Une contribution et un accomplissement ne s'écrivent qu'une fois.** Leur clé primaire en décide : l'insertion
n'écrit rien quand la ligne existe déjà. C'est `ON CONFLICT DO NOTHING` sur PostgreSQL, `INSERT IGNORE` sur MySQL et
MariaDB, et `INSERT OR IGNORE` sur SQLite. Sur une autre plateforme, c'est une simple insertion, dont la violation
d'unicité remonte à l'appelant. Une inscription, un duel et une définition sont remplacés dans une seule transaction.

## Un catalogue en base de données

Avec `catalog: doctrine`, le service `WritableCatalog` est `DbalCatalog` :

```php
$catalog->save('winter-reading', ['admission' => 'accept', 'steps' => [['verbs' => ['book.read'], 'total' => 300]]]);
$catalog->remove('winter-reading');
```

Une définition est lue avant d'être enregistrée, et les chaînes de tout le catalogue sont vérifiées. Une quête qui en
suit une autre inexistante est refusée, tout comme un retrait qui laisserait une quête ne suivre rien. Chaque
interrogation relit la table : une quête enregistrée est donc visible aussitôt. Retirer une quête laisse ses
accomplissements dans le journal. Après avoir modifié une quête déjà en cours, reconstruisez la projection.
