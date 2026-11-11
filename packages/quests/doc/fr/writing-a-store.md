<!-- phil-dev source-sha256=cd8d7d1c8227e703c862cd4fb1e0be738fea2bd1235801c57b862629bcfae6ac -->
# Écrire un magasin

La bibliothèque ne garde rien elle-même : quatre magasins s'en chargent, et une application les implémente sur son
stockage, ou prend ceux en mémoire pour les tests. `tilttek/quests-bundle` les implémente sur Doctrine DBAL.

| Contrat | Garde | Ce qui compte |
|---|---|---|
| `ContributionStore` | Ce que chaque activité a apporté à une étape | Écrite une fois par quête, par étape et par activité (`add()` retourne faux la seconde fois) ; lue par quête, membres et période, triée par moment, puis activité, puis étape ; `forget($since)` avant une reconstruction |
| `EnrolmentStore` | Les acceptations et les abandons | Une par quête et par membre, remplacée à l'enregistrement ; lue par quête, triée par membre, et par membre, triée par quête |
| `CompletionStore` | Le journal des quêtes accomplies | Écrit une fois par quête, par occurrence et par sujet ; `latest()` le plus récent du sujet ; `forget($since)` retourne ce qu'il a oublié |
| `DuelStore` | Les duels par clé | Remplacés à l'enregistrement ; `accepted()` les duels acceptés d'une quête dont un membre est un côté ; `due()` les offres à un moment ou avant et les duels acceptés terminés à présent ; `reopen($since)` remet à l'état accepté les duels gagnés depuis |

Chaque magasin efface un membre.

Les tests de la bibliothèque définissent un cas de test de contrat par magasin (`ContributionStoreTestCase`,
`EnrolmentStoreTestCase`, `CompletionStoreTestCase`, `DuelStoreTestCase`) : étendez-en un avec votre magasin pour
vérifier qu'il se comporte comme la bibliothèque l'attend.
