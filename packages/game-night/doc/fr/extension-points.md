<!-- phil-dev source-sha256=ffb4a59b00bdfbb11cf802f5f2a46895ec59e3be0ec1f7064267144804037245 -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension sont stables seulement à partir de sa version 1.0. D'ici là, une
version mineure peut les changer, et son CHANGELOG dit comment.

| Pour | Faites |
|---|---|
| Conserver les soirées | Implémentez `Contract\NightStore`, et passez-la à `GameNights` et `NightPlays`. |
| Informer les membres de ce qui s'est passé | Implémentez `Contract\NightObserver`, et passez-le à `GameNights`. |
| Lire les jeux que les membres peuvent apporter | Implémentez `Contract\ShelfReader`, et passez-le à `Choice\GameChooser`. |
| Conserver les jeux joués | Implémentez `Contract\PlayJournal`, et passez-le à `NightPlays`. |

## L'entrepôt

`Contract\NightStore` conserve les soirées, chacune à une version : `night()` en lit une, `save()` la conserve à la
version suivante et la retourne. Une soirée à la version 0 est nouvelle, et l'entrepôt n'a aucune soirée de son
identifiant ; toute autre n'est conservée que si l'entrepôt la détient à cette version, et `NightConflict` est levée
sinon, sans rien conserver. Une soirée contient ses tables, ses inscriptions, ses jeux proposés et ses votes : une seule
écriture les conserve ensemble.

`Store\InMemoryNightStore` conserve les soirées en mémoire, aussi longtemps que l'objet vit : il sert aux tests. Une
application les conserve dans sa base de données ; chaque valeur ne contient que des chaînes, des nombres, des
booléens, des énumérations et des dates.

## L'observateur et ses événements

`Contract\NightObserver::notify()` apprend chaque fait une fois la soirée conservée, par identifiants seulement :

| Événement | Quand |
|---|---|
| `Event\NightPublished` | La soirée s'est ouverte aux inscriptions. |
| `Event\NightFull` | Le dernier siège libre a été pris. |
| `Event\NightCancelled` | La soirée a été annulée. |
| `Event\SeatOffered` | Un membre en attente détient ses places ; il porte le membre. |

Chaque événement implémente `Event\NightEvent`, dont `night` est l'identifiant de la soirée. Une application envoie un
message ou une notification à partir d'eux — par Messenger, avec les identifiants seuls — et lit le reste dans la
soirée. Aucun événement ne porte une adresse ni un nom, et aucun n'est répété.

## Les étagères

`Contract\ShelfReader::games()` donne les jeux que les membres nommés peuvent apporter, sous forme de
`Choice\OwnedGame` : l'identifiant du jeu dans le catalogue et son propriétaire. Il en donne au plus la limite qu'on lui
demande ; `Choice\GameChooser` ne lit pas plus de 1000 jeux.

## Le journal des parties

`Contract\PlayJournal::record()` reçoit un `PlayedGame`, une fois sa soirée, sa table, ses joueurs et ses invités
vérifiés.

## Compatible avec les workers

Les services et tout ce qu'ils utilisent ne conservent rien entre les appels : ils peuvent vivre aussi longtemps qu'un
worker, et servir plusieurs sites d'une même application à la fois, l'entrepôt décidant quelles soirées il lit.
