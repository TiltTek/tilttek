<!-- phil-dev source-sha256=bdc39f9ceaed79a8e493d3d0d8f00584edf2d405e88f312182b5023fa2bb5b8f -->
# Conserver et publier

`Tables` fait tourner les tables d'une application : il les ouvre, joue et reprend leurs coups par le moteur, garde
leurs journaux dans un `Contract\LogStore`, leurs états dans un `Contract\StateCache`, et informe un
`Contract\TablePublisher` de chaque changement.

| Méthode | Ce qu'elle fait |
|---|---|
| `open()` | Ouvre une table d'un jeu, par le nom du jeu |
| `play()` | Fait un coup, refusé quand le journal contient un autre nombre de coups que celui que l'appelant attendait |
| `undo()` | Reprend le dernier coup, refusé avec `GameOver` une fois la partie terminée |
| `state()` | L'état courant |
| `view()` | Ce qu'un siège voit, façonné par les préférences de son joueur, ou le public |
| `log()` | Le journal entier d'une table, tous ses coups compris |
| `finishedLog()` | Le journal entier d'une table dont la partie est terminée, germe compris, refusé avec `GameInProgress` tant qu'elle continue |
| `header()` | Ce que le journal contient en plus de ses coups : germe, sièges, options, nombre de coups et révision |
| `commitment()` | L'engagement sur le germe, lu depuis l'en-tête |
| `reset()` | Lâche les états qu'elle garde, entre deux requêtes d'un *worker* |

## Le magasin de journaux

Un `LogStore` garde les journaux : `create()` en crée un nouveau, `load()` en charge un en entier, `header()` lit un
`LogHeader` sans les coups, `moves()` donne les coups d'une plage fenêtre par fenêtre, à mesure qu'on les demande,
`append()` ajoute un coup et `removeLast()` retire le dernier.

L'en-tête porte le nombre de coups et la révision du journal. La révision compte chaque changement — chaque coup
ajouté, chaque coup repris — et ne diminue jamais : deux lectures d'une même révision lisent donc les mêmes coups. Son
écart avec le nombre de coups, la filiation, ne change que lorsqu'un coup est repris.

Ajouter et retirer prennent l'en-tête que l'appelant a lu, et lèvent `ConcurrentMove` quand le journal contient un autre
nombre de coups ou est à une autre révision. De deux coups faits en même temps, un atterrit, et l'autre joueur relit la
table. Un coup repris puis un autre fait à sa place laissent le même nombre de coups, mais pas la même révision : un
coup calculé sur l'état d'avant est donc refusé. Un magasin sur une base de données compare les deux dans l'instruction
qui les change, dans la transaction qui insère le coup.

`moves()` peut donner moins de coups que demandé quand le journal change pendant leur lecture ; l'appelant relit
l'en-tête pour le savoir.

`Tables::play()` prend aussi le nombre de coups que le joueur a vus, `expected`, de sorte qu'un coup fait sur un écran
périmé est refusé plutôt qu'appliqué à une table qui a changé sous lui.

## Le cache d'états

Un `StateCache` épargne un rejeu à chaque lecture. Il garde un `Snapshot` par table : l'état, la révision du journal
dont il est construit et l'engagement sur le germe de sa table.

| L'instantané en cache | Ce que fait `Tables` |
|---|---|
| Reflète l'en-tête : mêmes table, jeu, germe, révision et nombre de coups | Emploie son état ; aucun coup n'est lu |
| Le précède : mêmes table, jeu, germe et filiation, moins de coups | Applique les coups faits depuis, lus fenêtre par fenêtre |
| Autre chose, ou rien | Rejoue le journal depuis la mise en place, fenêtre par fenêtre |

Un état construit de coups repris depuis, ou d'une table antérieure du même identifiant, ne correspond jamais à
l'en-tête : il n'est donc jamais servi. `put()` garde un instantané, sauf si le cache contient une révision plus récente
de la même table : un processus qui a lu le journal avant un changement n'écrit pas son état périmé par-dessus le plus
récent. Un cache sans comparaison et échange peut encore laisser une telle écriture atterrir entre deux autres ; la
lecture suivante voit que sa révision n'est pas celle du journal, reconstruit l'état et le met en cache à sa place.

Quand le journal change pendant la lecture de ses coups, `Tables` relit l'en-tête et construit l'état de nouveau, jusqu'à
`Tables::READS` fois, puis lève `ConcurrentMove`. Un journal qui ne peut pas être rejoué alors que son en-tête reste le
même lève `InvalidGame`.

`NullStateCache`, la valeur par défaut, rejoue à chaque lecture — quelques microsecondes par coup — ;
`InMemoryStateCache` garde les instantanés dans le processus, pour les tests et les scripts. Un cache partagé entre des
processus garde `Snapshot::toArray()` et le relit avec `Snapshot::fromArray()`
([Le journal et le rejeu](log-and-replay.md#létat-en-valeurs-simples)).

## Une lecture par requête

Chaque appel de `Tables` lit l'en-tête du journal, une ligne. Les états qu'elle a construits ou lus sont gardés, jusqu'à
`Tables::REMEMBERED`, celui lu le moins récemment étant lâché le premier, et réemployés tant que l'en-tête dit que le
journal n'a pas changé. La vue d'un siège, son état et l'engagement sur le germe coûtent alors une lecture des coups au
plus, et aucune quand le cache est à jour. Un état gardé n'est jamais cru au-delà d'un changement du journal : il reste
sûr quand un autre processus joue entre-temps.

Dans un *worker* qui sert plusieurs requêtes, `reset()` lâche les états gardés entre deux d'entre elles ; le bundle
étiquette le service pour cela. `InMemoryLogStore` et `InMemoryStateCache` ont aussi `reset()`, pour les tests qu'ils
servent.

## La publication

Après chaque changement, `Tables` publie un `TableUpdate` : l'identifiant de la table, le nombre de coups, la phase, la
ronde, les sièges qui peuvent agir maintenant et si la partie est terminée. Il ne contient que des identifiants et des
compteurs, jamais les données du jeu. Un éditeur sur un concentrateur Mercure le publie sur un sujet de la table ; la
page de chaque joueur lit alors la vue de son siège, par un point de terminaison qui vérifie qui est le joueur.
`NullTablePublisher`, la valeur par défaut, ne publie rien.
