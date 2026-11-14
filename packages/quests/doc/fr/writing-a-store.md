<!-- phil-dev source-sha256=c90a5546b62db1b4ac2bc7ecd0a7ef38728ffd37ecebaf5490d35161be8e16ca -->
# Écrire un magasin

La bibliothèque ne garde rien elle-même : quatre magasins s'en chargent, et une application les implémente sur son
stockage, ou prend ceux en mémoire pour les tests. `tilttek/quests-bundle` les implémente sur Doctrine DBAL.

| Contrat | Garde | Ce qui compte |
|---|---|---|
| `ContributionStore` | Ce que chaque activité a apporté à une étape | Écrite une fois par quête, par étape et par activité (`add()` retourne faux la seconde fois) ; lue par quête, membres et période, triée par moment, puis activité, puis étape ; `forget($since)` avant une reconstruction |
| `EnrolmentStore` | Les acceptations et les abandons | Une par quête et par membre, remplacée à l'enregistrement ; lue par quête, triée par membre, et par membre, triée par quête |
| `CompletionStore` | Le journal des quêtes accomplies | Écrit une fois par quête, par occurrence et par sujet ; `latest()` le plus récent du sujet ; `forget($since)` retourne ce qu'il a oublié |
| `DuelStore` | Les duels par clé | Remplacés à l'enregistrement ; `transition($duel, $from)` n'écrit que si le duel gardé sous la clé a encore l'état `from`, en une seule étape atomique, et renvoie faux sinon ; `accepted()` les duels acceptés d'une quête dont un membre est un côté ; `due()` les offres à un moment ou avant et les duels acceptés terminés à présent ; `reopen($since)` remet à l'état accepté les duels gagnés depuis |

Chaque magasin efface un membre.

Les tests de la bibliothèque définissent un cas de test de contrat par magasin (`ContributionStoreTestCase`,
`EnrolmentStoreTestCase`, `CompletionStoreTestCase`, `DuelStoreTestCase`) : étendez-en un avec votre magasin pour
vérifier qu'il se comporte comme la bibliothèque l'attend.
